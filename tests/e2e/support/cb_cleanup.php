<?php
/**
 * Delete end-to-end test data from a Joomla + Community Builder site.
 *
 * Runs inside the web container from stdin: `ddev exec php -- example.test < cb_cleanup.php`.
 * Deletes every user whose email is under the given domain through CB's UserTable::delete(),
 * so CB and its plugins remove their own rows. CB Paid Subscriptions keeps the payment basket
 * of a deleted user and Kunena keeps its user row, so those are deleted here too. Last, it
 * deletes the domain's rows from the plugin's verification table.
 */

const _JEXEC = 1;

$domain = $argv[1] ?? '';

if (!preg_match('/^[a-z0-9-]+(\.[a-z0-9-]+)*\.test$/', $domain)) {
	fwrite(STDERR, "Refusing domain '$domain': only a .test domain is allowed\n");
	exit(2);
}

define('JPATH_BASE', getcwd());

// The site application expects a web request.
$_SERVER['HTTP_HOST']   = 'localhost';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

require JPATH_BASE . '/includes/defines.php';
require JPATH_BASE . '/includes/framework.php';

// Same container set-up as includes/app.php, without executing the application.
$container = \Joomla\CMS\Factory::getContainer();
$container->alias('session.web', 'session.web.site')
	->alias('session', 'session.web.site')
	->alias('JSession', 'session.web.site')
	->alias(\Joomla\CMS\Session\Session::class, 'session.web.site')
	->alias(\Joomla\Session\Session::class, 'session.web.site')
	->alias(\Joomla\Session\SessionInterface::class, 'session.web.site');
\Joomla\CMS\Factory::$application = $container->get(\Joomla\CMS\Application\SiteApplication::class);
// execute() would register the extension namespaces; Joomla's user plugin needs them on delete.
\Joomla\CMS\Factory::$application->createExtensionNamespaceMap();
\Joomla\CMS\Factory::$application->loadLanguage();
// CB reads the current user's id; a guest stands in for the missing session.
\Joomla\CMS\Factory::$application->loadIdentity(new \Joomla\CMS\User\User());

require_once JPATH_ADMINISTRATOR . '/components/com_comprofiler/plugin.foundation.php';
cbimport('cb.html');

$db      = \Joomla\CMS\Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
$pattern = '%@' . $domain;
$tables  = array_flip($db->getTableList());
$prefix  = $db->getPrefix();

$userIds = $db->setQuery(
	$db->getQuery(true)
		->select($db->quoteName('id'))
		->from($db->quoteName('#__users'))
		->where($db->quoteName('email') . ' LIKE :pattern')
		->bind(':pattern', $pattern)
)->loadColumn();

$failed  = [];
$deleted = [];

foreach ($userIds as $id) {
	$user = new \CB\Database\Table\UserTable();

	try {
		if (!$user->load((int) $id) || !$user->delete((int) $id)) {
			$failed[] = $id . ': ' . $user->getError();
		} else {
			$deleted[] = (int) $id;
		}
	} catch (\Throwable $e) {
		// Keep going, so the users already deleted still get their leftover rows removed.
		$failed[] = $id . ': ' . $e->getMessage();
	}
}

// Leftover rows belong to the users whose #__users row is gone. That includes a user whose
// CB delete failed after Joomla had already deleted the row: no later run could find that id
// again. A user that still exists keeps everything.
$remaining = $userIds ? $db->setQuery(
	$db->getQuery(true)
		->select($db->quoteName('id'))
		->from($db->quoteName('#__users'))
		->whereIn($db->quoteName('id'), array_map('intval', $userIds))
)->loadColumn() : [];
$userIds   = array_values(array_diff(array_map('intval', $userIds), array_map('intval', $remaining)));

$baskets = 0;

if ($userIds && isset($tables[$prefix . 'cbsubs_payment_baskets'])) {
	$basketIds = $db->setQuery(
		$db->getQuery(true)
			->select($db->quoteName('id'))
			->from($db->quoteName('#__cbsubs_payment_baskets'))
			->whereIn($db->quoteName('user_id'), array_map('intval', $userIds))
	)->loadColumn();

	if ($basketIds) {
		foreach (['cbsubs_payment_items', 'cbsubs_payment_totalizers'] as $table) {
			if (isset($tables[$prefix . $table])) {
				$db->setQuery(
					$db->getQuery(true)
						->delete($db->quoteName('#__' . $table))
						->whereIn($db->quoteName('payment_basket_id'), array_map('intval', $basketIds))
				)->execute();
			}
		}

		$db->setQuery(
			$db->getQuery(true)
				->delete($db->quoteName('#__cbsubs_payment_baskets'))
				->whereIn($db->quoteName('id'), array_map('intval', $basketIds))
		)->execute();
		$baskets = count($basketIds);
	}
}

if ($userIds && isset($tables[$prefix . 'kunena_users'])) {
	$db->setQuery(
		$db->getQuery(true)
			->delete($db->quoteName('#__kunena_users'))
			->whereIn($db->quoteName('userid'), array_map('intval', $userIds))
	)->execute();
}

$db->setQuery(
	$db->getQuery(true)
		->delete($db->quoteName('#__comprofiler_plugin_beforeregverify'))
		->where($db->quoteName('email') . ' LIKE :pattern')
		->bind(':pattern', $pattern)
)->execute();
$rows = $db->getAffectedRows();

echo json_encode(['users' => count($deleted), 'baskets' => $baskets, 'verification_rows' => $rows, 'failed' => $failed]), "\n";

exit($failed ? 1 : 0);
