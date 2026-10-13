<?php
/**
 * CBBeforeRegVerify Plugin
 * @version $Id: $
 * @package CBBeforeRegVerify
 * @copyright (C) 2026 Yak Shaver https://www.kayakshaver.com/
 * @license http://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU/GPL version 2
 */

use CB\Database\Table\PluginTable;
use Joomla\CMS\Factory;
use Joomla\CMS\Mail\MailTemplate;
use Joomla\Database\DatabaseInterface;

if ( ! ( defined( '_VALID_CB' ) || defined( '_JEXEC' ) || defined( '_VALID_MOS' ) ) ) { die( 'Direct Access to this location is not allowed.' ); }

class cbbeforeregverifyInstaller
{
	private const MAIL_TEMPLATE_KEY			=	'comprofiler.cbbeforeregverify.verification_code';
	// Joomla's mail editor builds its insert buttons from these names, so they must match the body placeholders.
	private const MAIL_TEMPLATE_TAGS		=	[ 'code', 'minutes' ];
	// Joomla uppercases language keys when it loads them, so one spelling is enough.
	private const MAIL_TEMPLATE_TITLE_KEY	=	'COMPROFILER_MAIL_CBBEFOREREGVERIFY_VERIFICATION_CODE_TITLE';
	private const MAIL_TEMPLATE_DESC_KEY	=	'COMPROFILER_MAIL_CBBEFOREREGVERIFY_VERIFICATION_CODE_DESC';
	private const MAIL_TEMPLATE_SHORT_KEY	=	'COMPROFILER_MAIL_CBBEFOREREGVERIFY_VERIFICATION_CODE_SHORT';
	private const DEFAULT_LANGUAGE_TAG	=	'en-GB';

	/**
	 * @param string            $method
	 * @param cbInstallerPlugin $installer
	 * @param PluginTable       $plugin
	 * @param bool|string       $newRelease
	 * @param bool|string       $currentRelease
	 * @return bool
	 */
	public function postflight( string $method, cbInstallerPlugin $installer, PluginTable $plugin, $newRelease, $currentRelease ): bool
	{
		$this->ensureMailTemplate();
		$this->fixMailTemplateTags();
		$this->ensureMailTemplateLanguageOverrides();

		return true;
	}

	/**
	 * @return void
	 */
	private function ensureMailTemplate(): void
	{
		if ( ! class_exists( MailTemplate::class ) ) {
			return;
		}

		try {
			if ( MailTemplate::getTemplate( self::MAIL_TEMPLATE_KEY, '' ) !== null ) {
				return;
			}

			MailTemplate::createTemplate(
				self::MAIL_TEMPLATE_KEY,
				'Your verification code',
				'Your verification code is {CODE}. This code expires in {MINUTES} minute(s).',
				self::MAIL_TEMPLATE_TAGS,
				'<p>Your verification code is <strong>{CODE}</strong>.</p><p>This code expires in {MINUTES} minute(s).</p>'
			);
		} catch ( \Throwable $e ) {
			// Keep installation non-blocking if mail templates are unavailable.
		}
	}

	/**
	 * Releases up to 0.1.1 stored the tags as a name => label map, which made the mail
	 * editor insert {VERIFICATION CODE} instead of {CODE}. Rewrite only the tag list;
	 * subject and body stay as the admin left them.
	 *
	 * @return void
	 */
	private function fixMailTemplateTags(): void
	{
		try {
			$db		=	Factory::getContainer()->get( DatabaseInterface::class );
			$key	=	self::MAIL_TEMPLATE_KEY;
			$query	=	$db->createQuery()
						->select( $db->quoteName( [ 'language', 'params' ] ) )
						->from( $db->quoteName( '#__mail_templates' ) )
						->where( $db->quoteName( 'template_id' ) . ' = :key' )
						->bind( ':key', $key );
			$rows	=	$db->setQuery( $query )->loadObjectList();

			foreach ( $rows as $row ) {
				$params	=	json_decode( (string) $row->params, true );

				if ( ( ! \is_array( $params ) ) || ( ! isset( $params['tags'] ) ) || ( $params['tags'] === self::MAIL_TEMPLATE_TAGS ) ) {
					continue;
				}

				$params['tags']	=	self::MAIL_TEMPLATE_TAGS;
				$json			=	json_encode( $params );
				$language		=	(string) $row->language;
				$update			=	$db->createQuery()
									->update( $db->quoteName( '#__mail_templates' ) )
									->set( $db->quoteName( 'params' ) . ' = :params' )
									->where( $db->quoteName( 'template_id' ) . ' = :key' )
									->where( $db->quoteName( 'language' ) . ' = :language' )
									->bind( ':params', $json )
									->bind( ':key', $key )
									->bind( ':language', $language );

				$db->setQuery( $update )->execute();
			}
		} catch ( \Throwable $e ) {
			// Keep installation non-blocking if mail templates are unavailable.
		}
	}

	/**
	 * @return void
	 */
	private function ensureMailTemplateLanguageOverrides(): void
	{
		$overrides	=	[
			self::MAIL_TEMPLATE_TITLE_KEY	=>	'CB Before Registration Verify: Verification Code',
			self::MAIL_TEMPLATE_DESC_KEY	=>	'Email sent with the verification code before Community Builder registration.',
			self::MAIL_TEMPLATE_SHORT_KEY	=>	'Verification Code'
		];

		foreach ( $this->getOverrideLanguageTags() as $languageTag ) {
			$this->appendMissingOverrideLines( $languageTag, $overrides );
		}
	}

	/**
	 * @return array
	 */
	private function getOverrideLanguageTags(): array
	{
		$tags	=	[ self::DEFAULT_LANGUAGE_TAG ];

		try {
			$tag	=	(string) Factory::getApplication()->getLanguage()->getTag();

			if ( $tag !== '' ) {
				$tags[]	=	$tag;
			}
		} catch ( \Throwable $e ) {
			// Ignore and keep default fallback only.
		}

		return array_values( array_unique( $tags ) );
	}

	/**
	 * @param string $languageTag
	 * @param array  $overrides
	 * @return void
	 */
	private function appendMissingOverrideLines( string $languageTag, array $overrides ): void
	{
		$directory	=	JPATH_ADMINISTRATOR . '/language/overrides';
		$filePath	=	$directory . '/' . $languageTag . '.override.ini';

		try {
			if ( ! is_dir( $directory ) ) {
				mkdir( $directory, 0755, true );
			}

			$current	=	[];

			if ( file_exists( $filePath ) ) {
				$parsed	=	parse_ini_file( $filePath, false, INI_SCANNER_RAW );

				// Unreadable file: appending blind would duplicate keys on every install.
				if ( ! is_array( $parsed ) ) {
					return;
				}

				$current	=	array_change_key_case( $parsed, CASE_UPPER );
			}

			$lines		=	[];

			foreach ( $overrides as $constant => $text ) {
				if ( array_key_exists( $constant, $current ) ) {
					continue;
				}

				$lines[]	=	$constant . '="' . str_replace( '"', '\"', $text ) . '"';
			}

			if ( ! $lines ) {
				return;
			}

			$prefix		=	'';

			if ( file_exists( $filePath ) ) {
				$prefix	=	(string) file_get_contents( $filePath );

				if ( $prefix !== '' ) {
					$lastChar	=	substr( $prefix, -1 );

					if ( ( $lastChar !== "\n" ) && ( $lastChar !== "\r" ) ) {
						$prefix	.=	PHP_EOL;
					}
				}
			}

			file_put_contents( $filePath, $prefix . implode( PHP_EOL, $lines ) . PHP_EOL );
		} catch ( \Throwable $e ) {
			// Keep installation non-blocking if overrides cannot be written.
		}
	}
}
