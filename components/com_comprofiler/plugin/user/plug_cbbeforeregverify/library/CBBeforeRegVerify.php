<?php
/**
 * CBBeforeRegVerify Plugin
 * @version $Id: $
 * @package CBBeforeRegVerify
 * @copyright (C) 2026 Yak Shaver https://www.kayakshaver.com/
 * @license http://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU/GPL version 2
 */

namespace CB\Plugin\BeforeRegVerify;

use CB\Plugin\BeforeRegVerify\Table\VerificationTable;
use CBLib\Application\Application;
use CBLib\Language\CBTxt;
use CBLib\Registry\Registry;
use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Mail\MailTemplate;
use Joomla\Database\DatabaseInterface;

\defined( 'CBLIB' ) or die();

class CBBeforeRegVerify
{
	public const SESSION_FLOW_EMAIL		=	'cbbeforeregverify_flow_email';
	public const SESSION_VERIFIED_EMAIL	=	'cbbeforeregverify_verified_email';
	public const MAIL_TEMPLATE_KEY		=	'comprofiler.cbbeforeregverify.verification_code';
	public const LOG_CATEGORY			=	'cbbeforeregverify';
	public const NOTE_MAIL_SEND_FAILED	=	'mail_send_failed';

	/** @var bool */
	private static $maintenanceRan		=	false;

	/**
	 * @return Registry
	 */
	public static function getGlobalParams(): Registry
	{
		global $_PLUGINS;

		static $params	=	null;

		if ( ! $params ) {
			$plugin		=	$_PLUGINS->getLoadedPlugin( 'user', 'cbbeforeregverify' );
			$params		=	new Registry();

			if ( $plugin ) {
				$params->load( $plugin->params );
			}
		}

		return $params;
	}

	/**
	 * @return bool
	 */
	public static function isEnabled(): bool
	{
		return self::getGlobalParams()->getBool( 'gateway_enabled', true );
	}

	private const PURGE_GATE_SEC	=	3600;

	/**
	 * @return void
	 */
	public static function runMaintenance(): void
	{
		if ( self::$maintenanceRan ) {
			return;
		}

		self::$maintenanceRan	=	true;

		$marker	=	self::getTmpPath() . '/.cbbeforeregverify_purge';

		if ( @filemtime( $marker ) > ( time() - self::PURGE_GATE_SEC ) ) {
			return;
		}

		try {
			self::purgeOldRows( self::getPurgeAfterDays() );
			@touch( $marker );
		} catch ( \Throwable $e ) {
			// Ignore maintenance errors to avoid blocking unrelated requests.
		}
	}

	/**
	 * @return string
	 */
	private static function getTmpPath(): string
	{
		$tmpPath	=	'';

		try {
			$tmpPath	=	(string) Factory::getApplication()->get( 'tmp_path', '' );
		} catch ( \Throwable $e ) {
			$tmpPath	=	'';
		}

		if ( $tmpPath === '' ) {
			$tmpPath	=	( \defined( 'JPATH_ROOT' ) ? JPATH_ROOT . '/tmp' : sys_get_temp_dir() );
		}

		return rtrim( $tmpPath, '/\\' );
	}

	/**
	 * Mirrors the guest and registration-enabled checks of CB registerForm().
	 *
	 * @return bool
	 */
	public static function canRegister(): bool
	{
		global $_CB_framework;

		if ( $_CB_framework->myId() ) {
			return false;
		}

		return ( $_CB_framework->getCfg( 'allowUserRegistration' ) != '0' )
			|| ( Application::Config()->getInt( 'reg_admin_allowcbregistration', 0 ) === 1 );
	}

	/**
	 * @param string $email
	 * @return string
	 */
	public static function normalizeEmail( string $email ): string
	{
		return strtolower( trim( $email ) );
	}

	/**
	 * @return int
	 */
	public static function getVerificationTtl(): int
	{
		return max( 60, self::getGlobalParams()->getInt( 'verification_ttl_sec', 900 ) );
	}

	/**
	 * @return int
	 */
	public static function getPurgeAfterDays(): int
	{
		$params		=	self::getGlobalParams();
		$keepSec	=	self::getVerificationTtl();

		// Rows still counted by an enabled limiter must survive the purge.
		if ( $params->getBool( 'rl_ip_enabled', true ) ) {
			$keepSec	=	max(
				$keepSec,
				max( 1, $params->getInt( 'rl_ip_short_window_min', 15 ) ) * 60,
				max( 1, $params->getInt( 'rl_ip_day_window_hours', 24 ) ) * 3600
			);
		}

		if ( $params->getBool( 'rl_email_enabled', true ) ) {
			$keepSec	=	max(
				$keepSec,
				max( 1, $params->getInt( 'rl_email_short_window_min', 30 ) ) * 60,
				max( 1, $params->getInt( 'rl_email_day_window_hours', 24 ) ) * 3600
			);
		}

		if ( $params->getBool( 'rl_resend_enabled', true ) ) {
			$keepSec	=	max( $keepSec, max( 1, $params->getInt( 'rl_resend_cooldown_sec', 60 ) ) );
		}

		$minDays	=	(int) ceil( $keepSec / 86400 );
		$configDays	=	max( 1, $params->getInt( 'purge_after_days', 30 ) );

		return max( $minDays, $configDays );
	}

	/**
	 * @return int
	 */
	public static function getCodeLength(): int
	{
		return max( 4, self::getGlobalParams()->getInt( 'code_length', 6 ) );
	}

	/**
	 * @return string
	 */
	public static function getSecret(): string
	{
		return trim( self::getGlobalParams()->getString( 'secret', '' ) );
	}

	/**
	 * @param string $func
	 * @param array  $vars
	 * @return string
	 */
	public static function getGatewayUrl( string $func = 'step_email', array $vars = [] ): string
	{
		global $_CB_framework;

		return $_CB_framework->pluginClassUrl( 'cbbeforeregverify', false, array_merge( [ 'func' => $func ], $vars ) );
	}

	/**
	 * @return string
	 */
	public static function getRegistrationUrl(): string
	{
		global $_CB_framework;

		return $_CB_framework->viewUrl( 'registers', false );
	}

	/**
	 * @return string
	 */
	public static function getFlowEmail(): string
	{
		return self::normalizeEmail( Application::Session()->getString( self::SESSION_FLOW_EMAIL, '' ) );
	}

	/**
	 * @param string $email
	 * @return void
	 */
	public static function setFlowEmail( string $email ): void
	{
		Application::Session()->set( self::SESSION_FLOW_EMAIL, self::normalizeEmail( $email ) );
	}

	/**
	 * @return void
	 */
	public static function clearFlowEmail(): void
	{
		Application::Session()->set( self::SESSION_FLOW_EMAIL, '' );
	}

	/**
	 * @return string
	 */
	public static function getVerifiedEmail(): string
	{
		return self::normalizeEmail( Application::Session()->getString( self::SESSION_VERIFIED_EMAIL, '' ) );
	}

	/**
	 * @param string $email
	 * @return void
	 */
	public static function setVerifiedEmail( string $email ): void
	{
		Application::Session()->set( self::SESSION_VERIFIED_EMAIL, self::normalizeEmail( $email ) );
	}

	/**
	 * @return void
	 */
	public static function clearVerifiedEmail(): void
	{
		Application::Session()->set( self::SESSION_VERIFIED_EMAIL, '' );
	}

	/**
	 * @return void
	 */
	public static function clearVerificationSession(): void
	{
		self::clearFlowEmail();
		self::clearVerifiedEmail();
	}

	/**
	 * @return bool
	 */
	public static function hasVerifiedSession(): bool
	{
		return ( self::getVerifiedEmail() !== '' );
	}

	/**
	 * @param string $code
	 * @return string
	 */
	public static function hashCode( string $code ): string
	{
		return hash_hmac( 'sha256', $code, self::getSecret() );
	}

	/**
	 * @return string
	 */
	public static function generateCode(): string
	{
		$length	=	self::getCodeLength();
		$code	=	'';

		for ( $i = 0; $i < $length; $i++ ) {
			$code .= (string) random_int( 0, 9 );
		}

		return $code;
	}

	/**
	 * @param string $email
	 * @param string $ip
	 * @return VerificationTable
	 */
	public static function issueInitialCode( string $email, string $ip ): VerificationTable
	{
		return self::issueCode( $email, $ip, VerificationTable::STATUS_SENT );
	}

	/**
	 * @param string $email
	 * @param string $ip
	 * @return VerificationTable
	 */
	public static function issueResendCode( string $email, string $ip ): VerificationTable
	{
		return self::issueCode( $email, $ip, VerificationTable::STATUS_RESENT );
	}

	/**
	 * @param string $email
	 * @param string $ip
	 * @return array{allowed:bool,message:string}
	 */
	public static function canProceedByRateLimits( string $email, string $ip ): array
	{
		$params		=	self::getGlobalParams();
		$email		=	self::normalizeEmail( $email );
		$ip		=	trim( $ip );

		if ( $params->getBool( 'rl_ip_enabled', true ) && ( $ip !== '' ) ) {
			$shortWindow	=	max( 1, $params->getInt( 'rl_ip_short_window_min', 15 ) );
			$shortMax	=	max( 0, $params->getInt( 'rl_ip_short_max', 5 ) );

			if ( $shortMax && ( self::countIssuedRowsByIp( $ip, '-' . $shortWindow . ' MINUTES' ) >= $shortMax ) ) {
				return [
					'allowed'	=>	false,
					'message'	=>	CBTxt::T(
						'CBBEFOREREGVERIFY_RATE_LIMIT_IP_SHORT',
						'Too many verification requests from this IP address. Please try again in [minutes] minute(s).',
						[ '[minutes]' => $shortWindow ]
					)
				];
			}

			$dayWindow	=	max( 1, $params->getInt( 'rl_ip_day_window_hours', 24 ) );
			$dayMax		=	max( 0, $params->getInt( 'rl_ip_day_max', 25 ) );

			if ( $dayMax && ( self::countIssuedRowsByIp( $ip, '-' . $dayWindow . ' HOURS' ) >= $dayMax ) ) {
				return [
					'allowed'	=>	false,
					'message'	=>	CBTxt::T(
						'CBBEFOREREGVERIFY_RATE_LIMIT_IP_DAY',
						'Too many verification requests from this IP address. Please try again in [hours] hour(s).',
						[ '[hours]' => $dayWindow ]
					)
				];
			}
		}

		if ( $params->getBool( 'rl_email_enabled', true ) && ( $email !== '' ) ) {
			$shortWindow	=	max( 1, $params->getInt( 'rl_email_short_window_min', 30 ) );
			$shortMax	=	max( 0, $params->getInt( 'rl_email_short_max', 3 ) );

			if ( $shortMax && ( self::countIssuedRowsByEmail( $email, '-' . $shortWindow . ' MINUTES' ) >= $shortMax ) ) {
				return [
					'allowed'	=>	false,
					'message'	=>	CBTxt::T(
						'CBBEFOREREGVERIFY_RATE_LIMIT_EMAIL_SHORT',
						'Too many verification requests for this email. Please try again in [minutes] minute(s).',
						[ '[minutes]' => $shortWindow ]
					)
				];
			}

			$dayWindow	=	max( 1, $params->getInt( 'rl_email_day_window_hours', 24 ) );
			$dayMax		=	max( 0, $params->getInt( 'rl_email_day_max', 10 ) );

			if ( $dayMax && ( self::countIssuedRowsByEmail( $email, '-' . $dayWindow . ' HOURS' ) >= $dayMax ) ) {
				return [
					'allowed'	=>	false,
					'message'	=>	CBTxt::T(
						'CBBEFOREREGVERIFY_RATE_LIMIT_EMAIL_DAY',
						'Too many verification requests for this email. Please try again in [hours] hour(s).',
						[ '[hours]' => $dayWindow ]
					)
				];
			}
		}

		// The cooldown applies to every new code for the email, including a repeated step-email submit.
		if ( $params->getBool( 'rl_resend_enabled', true ) && ( $email !== '' ) ) {
			$cooldown	=	max( 1, $params->getInt( 'rl_resend_cooldown_sec', 60 ) );
			$lastSentAt	=	self::getLastIssuedAtByEmail( $email );

			if ( $lastSentAt ) {
				$elapsed	=	Application::Date( 'now', 'UTC' )->getTimestamp() - Application::Date( $lastSentAt, 'UTC' )->getTimestamp();

				if ( $elapsed < $cooldown ) {
					return [
						'allowed'	=>	false,
						'message'	=>	CBTxt::T(
							'CBBEFOREREGVERIFY_RATE_LIMIT_RESEND',
							'Please wait [seconds] second(s) before requesting another code.',
							[ '[seconds]' => max( 1, $cooldown - $elapsed ) ]
						)
					];
				}
			}
		}

		return [ 'allowed' => true, 'message' => '' ];
	}

	/**
	 * @param string $email
	 * @return null|VerificationTable
	 */
	public static function getActiveVerificationRow( string $email ): ?VerificationTable
	{
		$email	=	self::normalizeEmail( $email );

		if ( $email === '' ) {
			return null;
		}

		return VerificationTable::loadActiveByEmail( $email );
	}

	/**
	 * @param VerificationTable $row
	 * @return bool
	 */
	public static function isExpired( VerificationTable $row ): bool
	{
		$sentAt	=	$row->getString( 'sent_at', '' );

		if ( ( ! $sentAt )
			|| ( ! in_array( $row->getString( 'status', '' ), [ VerificationTable::STATUS_SENT, VerificationTable::STATUS_RESENT ], true ) )
			|| ( $row->getString( 'outcome', '' ) !== VerificationTable::OUTCOME_PENDING )
		) {
			return false;
		}

		$expiresAt	=	Application::Date( $sentAt, 'UTC' )->getTimestamp() + max( 1, $row->getInt( 'ttl', self::getVerificationTtl() ) );

		return ( Application::Date( 'now', 'UTC' )->getTimestamp() >= $expiresAt );
	}

	/**
	 * @param VerificationTable $row
	 * @param string            $code
	 * @return bool
	 */
	public static function verifyCode( VerificationTable $row, string $code ): bool
	{
		$hash	=	$row->getString( 'code_hash', '' );

		if ( ! $hash ) {
			return false;
		}

		$code	=	trim( $code );

		if ( $code === '' ) {
			return false;
		}

		return hash_equals( $hash, self::hashCode( $code ) );
	}

	/**
	 * @param VerificationTable $row
	 * @return bool
	 */
	public static function markVerified( VerificationTable $row ): bool
	{
		return self::transitionPending( $row, VerificationTable::OUTCOME_VERIFIED, null );
	}

	/**
	 * Move a row out of pending only if it is still pending in the database, so a
	 * stale copy can never overwrite a state another request has already written.
	 *
	 * @param VerificationTable $row
	 * @param string            $outcome
	 * @param null|string       $note
	 * @return bool True when this call made the transition
	 */
	private static function transitionPending( VerificationTable $row, string $outcome, ?string $note ): bool
	{
		global $_CB_database;

		$id	=	$row->getInt( 'id', 0 );

		if ( ! $id ) {
			return false;
		}

		$now	=	Application::Database()->getUtcDateTime();
		$query	=	'UPDATE ' . $_CB_database->NameQuote( '#__comprofiler_plugin_beforeregverify' )
				.	"\n SET " . $_CB_database->NameQuote( 'outcome' ) . " = " . $_CB_database->Quote( $outcome )
				.	', ' . $_CB_database->NameQuote( 'note' ) . ' = ' . ( $note === null ? 'NULL' : $_CB_database->Quote( $note ) )
				.	', ' . $_CB_database->NameQuote( 'modified_at' ) . ' = ' . $_CB_database->Quote( $now )
				.	"\n WHERE " . $_CB_database->NameQuote( 'id' ) . " = " . (int) $id
				.	"\n AND " . $_CB_database->NameQuote( 'outcome' ) . " = " . $_CB_database->Quote( VerificationTable::OUTCOME_PENDING );
		$_CB_database->setQuery( $query );

		if ( ( ! $_CB_database->query() ) || ( (int) $_CB_database->getAffectedRows() !== 1 ) ) {
			return false;
		}

		$row->set( 'outcome', $outcome );
		$row->set( 'note', $note );
		$row->set( 'modified_at', $now );

		return true;
	}

	/**
	 * @param VerificationTable $activeRow
	 * @param string            $email
	 * @param string            $ip
	 * @return VerificationTable
	 */
	public static function recordFailedAttempt( VerificationTable $activeRow, string $email, string $ip ): VerificationTable
	{
		$row	=	new VerificationTable();
		$now	=	Application::Database()->getUtcDateTime();

		$row->set( 'email', self::normalizeEmail( $email ) );
		$row->set( 'code_hash', null );
		$row->set( 'status', VerificationTable::STATUS_ATTEMPT );
		$row->set( 'outcome', VerificationTable::OUTCOME_FAILED );
		$row->set( 'ttl', max( 1, $activeRow->getInt( 'ttl', self::getVerificationTtl() ) ) );
		$row->set( 'sent_at', $now );
		$row->set( 'modified_at', $now );
		$row->set( 'request_ip', $ip );
		$row->set( 'note', 'failed_code' );

		if ( $row->getError() || ( ! $row->check() ) ) {
			self::logError( 'record_failed_attempt_check', (string) $row->getError() );

			throw new \RuntimeException( CBTxt::T( 'CBBEFOREREGVERIFY_ATTEMPT_STORE_FAILED', 'Failed to record verification attempt.' ) );
		}

		if ( $row->getError() || ( ! $row->store() ) ) {
			self::logError( 'record_failed_attempt_store', (string) $row->getError() );

			throw new \RuntimeException( CBTxt::T( 'CBBEFOREREGVERIFY_ATTEMPT_STORE_FAILED', 'Failed to record verification attempt.' ) );
		}

		return $row;
	}

	/**
	 * Seconds until the email drops below the failed-attempts limit; 0 when it is not locked.
	 *
	 * @param string $email
	 * @return int
	 */
	public static function getAttemptsLockoutSeconds( string $email ): int
	{
		global $_CB_database;

		$params	=	self::getGlobalParams();
		$email	=	self::normalizeEmail( $email );

		if ( ( $email === '' ) || ( ! $params->getBool( 'rl_attempts_enabled', true ) ) ) {
			return 0;
		}

		$max	=	$params->getInt( 'rl_attempts_max', 8 );

		if ( $max < 1 ) {
			return 0;
		}

		$expiresAt	=	'DATE_ADD(' . $_CB_database->NameQuote( 'sent_at' ) . ', INTERVAL ' . $_CB_database->NameQuote( 'ttl' ) . ' SECOND)';
		$query		=	'SELECT ' . $expiresAt
					.	"\n FROM " . $_CB_database->NameQuote( '#__comprofiler_plugin_beforeregverify' )
					.	"\n WHERE " . $_CB_database->NameQuote( 'email' ) . " = " . $_CB_database->Quote( $email )
					.	"\n AND " . $_CB_database->NameQuote( 'status' ) . " = " . $_CB_database->Quote( VerificationTable::STATUS_ATTEMPT )
					.	"\n AND " . $_CB_database->NameQuote( 'outcome' ) . " = " . $_CB_database->Quote( VerificationTable::OUTCOME_FAILED )
					.	"\n AND " . $expiresAt . ' > ' . $_CB_database->Quote( Application::Database()->getUtcDateTime() )
					.	"\n ORDER BY 1 ASC";
		$_CB_database->setQuery( $query );
		$expiries	=	(array) $_CB_database->loadResultArray();
		$count		=	\count( $expiries );

		if ( $count < $max ) {
			return 0;
		}

		// The lock lifts once enough of the oldest attempts expire to leave max - 1 counted.
		$releaseAt	=	Application::Date( (string) $expiries[$count - $max], 'UTC' )->getTimestamp();

		return max( 1, $releaseAt - Application::Date( 'now', 'UTC' )->getTimestamp() );
	}

	/**
	 * @param string $email
	 * @return bool
	 */
	public static function isAttemptsLimitReached( string $email ): bool
	{
		return ( self::getAttemptsLockoutSeconds( $email ) > 0 );
	}

	/**
	 * @param string $email
	 * @return string
	 */
	public static function getAttemptsLockoutMessage( string $email ): string
	{
		return CBTxt::T(
			'CBBEFOREREGVERIFY_ATTEMPTS_EXCEEDED',
			'Too many incorrect code attempts. Please try again in [minutes] minute(s).',
			[ '[minutes]' => max( 1, (int) ceil( self::getAttemptsLockoutSeconds( $email ) / 60 ) ) ]
		);
	}

	/**
	 * Rotate the session ID the way Joomla's user plugin does on login.
	 *
	 * @return bool
	 */
	public static function regenerateSessionId(): bool
	{
		try {
			$app			=	Factory::getApplication();
			$session		=	$app->getSession();
			$oldSessionId	=	$session->getId();

			if ( ! $session->fork( true ) ) {
				self::logError( 'regenerate_session', 'session fork failed' );

				return false;
			}
		} catch ( \Throwable $e ) {
			self::logError( 'regenerate_session', $e->getMessage() );

			return false;
		}

		// The ID has already rotated; a metadata failure must not block verification.
		try {
			if ( $app->get( 'session_metadata', true ) ) {
				$app->checkSession();

				$db		=	Factory::getContainer()->get( DatabaseInterface::class );
				$query	=	$db->createQuery()
							->delete( $db->quoteName( '#__session' ) )
							->where( $db->quoteName( 'session_id' ) . ' = :sessionid' )
							->bind( ':sessionid', $oldSessionId );

				$db->setQuery( $query )->execute();
			}
		} catch ( \Throwable $e ) {
			self::logError( 'regenerate_session_metadata', $e->getMessage() );
		}

		return true;
	}

	/**
	 * @param int $days
	 * @return int
	 */
	public static function purgeOldRows( int $days ): int
	{
		global $_CB_database;

		$days		=	max( 1, $days );
		$now		=	Application::Database()->getUtcDateTime();
		$threshold	=	Application::Date( 'now', 'UTC' )->modify( '-' . $days . ' DAYS' )->format( 'Y-m-d H:i:s' );

		$query		=	'DELETE FROM ' . $_CB_database->NameQuote( '#__comprofiler_plugin_beforeregverify' )
					.	"\n WHERE " . $_CB_database->NameQuote( 'modified_at' ) . " < " . $_CB_database->Quote( $threshold )
					// Pending requests and failed attempts are kept until their own TTL ends; attempts still count toward the lockout.
					.	"\n AND ( ( " . $_CB_database->NameQuote( 'outcome' ) . " <> " . $_CB_database->Quote( VerificationTable::OUTCOME_PENDING )
					.	' AND ' . $_CB_database->NameQuote( 'status' ) . " <> " . $_CB_database->Quote( VerificationTable::STATUS_ATTEMPT ) . ' )'
					.	"\n OR DATE_ADD(" . $_CB_database->NameQuote( 'sent_at' ) . ', INTERVAL ' . $_CB_database->NameQuote( 'ttl' ) . ' SECOND) <= ' . $_CB_database->Quote( $now )
					.	' )';
		$_CB_database->setQuery( $query );
		$_CB_database->query();

		if ( method_exists( $_CB_database, 'getAffectedRows' ) ) {
			return (int) $_CB_database->getAffectedRows();
		}

		return 0;
	}

	/**
	 * @param string $email
	 * @param string $ip
	 * @param string $status
	 * @return VerificationTable
	 */
	private static function issueCode( string $email, string $ip, string $status ): VerificationTable
	{
		$secret		=	self::getSecret();
		$email		=	self::normalizeEmail( $email );

		if ( ! $secret ) {
			throw new \RuntimeException( CBTxt::T( 'CBBEFOREREGVERIFY_SECRET_REQUIRED', 'Verification secret is required before this gateway can be used.' ) );
		}

		if ( ( $email === '' ) || ( ! cbIsValidEmail( $email ) ) ) {
			throw new \InvalidArgumentException( CBTxt::T( 'CBBEFOREREGVERIFY_EMAIL_INVALID', 'Enter a valid email address.' ) );
		}

		// A new code does not reset failed attempts, so don't mail one that can't be used yet.
		if ( self::isAttemptsLimitReached( $email ) ) {
			throw new \DomainException( self::getAttemptsLockoutMessage( $email ) );
		}

		$rateLimit	=	self::canProceedByRateLimits( $email, $ip );

		if ( ! $rateLimit['allowed'] ) {
			throw new \DomainException( $rateLimit['message'] ?: CBTxt::T( 'CBBEFOREREGVERIFY_RATE_LIMITED', 'Too many verification requests. Please try again later.' ) );
		}

		self::softCancelPendingRows( $email, 'replaced_by_new_issue' );

		$row		=	new VerificationTable();
		$code		=	self::generateCode();
		$now		=	Application::Database()->getUtcDateTime();

		$row->set( 'email', $email );
		$row->set( 'code_hash', self::hashCode( $code ) );
		$row->set( 'status', $status );
		$row->set( 'outcome', VerificationTable::OUTCOME_PENDING );
		$row->set( 'ttl', self::getVerificationTtl() );
		$row->set( 'sent_at', $now );
		$row->set( 'modified_at', $now );
		$row->set( 'request_ip', $ip );
		$row->set( 'note', null );

		if ( $row->getError() || ( ! $row->check() ) ) {
			self::logError( 'issue_code_check', (string) $row->getError() );

			throw new \RuntimeException( CBTxt::T( 'CBBEFOREREGVERIFY_ISSUE_FAILED', 'Failed to create verification request.' ) );
		}

		if ( $row->getError() || ( ! $row->store() ) ) {
			self::logError( 'issue_code_store', (string) $row->getError() );

			throw new \RuntimeException( CBTxt::T( 'CBBEFOREREGVERIFY_ISSUE_FAILED', 'Failed to create verification request.' ) );
		}

		if ( ! self::sendVerificationEmail( $email, $code, $row->getInt( 'ttl', self::getVerificationTtl() ) ) ) {
			self::cancelRequest( $row, self::NOTE_MAIL_SEND_FAILED );

			throw new \RuntimeException( CBTxt::T( 'CBBEFOREREGVERIFY_EMAIL_SEND_FAILED', 'Verification email failed to send. Please try again.' ) );
		}

		return $row;
	}

	/**
	 * @param string $email
	 * @param string $code
	 * @param int    $ttl
	 * @return bool
	 */
	private static function sendVerificationEmail( string $email, string $code, int $ttl ): bool
	{
		$minutes			=	(int) ceil( max( 1, $ttl ) / 60 );

		if ( self::sendVerificationEmailTemplate( $email, $code, $minutes ) ) {
			return true;
		}

		$mailSubject		=	CBTxt::T( 'CBBEFOREREGVERIFY_MAIL_SUBJECT', 'Your verification code' );
		$mailBody			=	CBTxt::T(
			'CBBEFOREREGVERIFY_MAIL_BODY',
			'Your verification code is [code]. This code expires in [minutes] minute(s).',
			[
				'[code]'	=>	$code,
				'[minutes]'	=>	$minutes
			]
		);
		$notification		=	new \cbNotification();

		return $notification->sendFromSystem( $email, $mailSubject, $mailBody, false, 1 );
	}

	/**
	 * @param string $email
	 * @param string $code
	 * @param int    $minutes
	 * @return bool
	 */
	private static function sendVerificationEmailTemplate( string $email, string $code, int $minutes ): bool
	{
		if ( ! class_exists( MailTemplate::class ) ) {
			return false;
		}

		$languageTag	=	'';

		try {
			$languageTag	=	Factory::getApplication()->getLanguage()->getTag();
		} catch ( \Throwable $e ) {
			$languageTag	=	'';
		}

		try {
			$mailTemplate	=	new MailTemplate( self::MAIL_TEMPLATE_KEY, $languageTag );

			$mailTemplate->addTemplateData(
				[
					'code'		=>	$code,
					'minutes'	=>	$minutes
				]
			);
			$mailTemplate->addRecipient( $email );

			return $mailTemplate->send();
		} catch ( \Throwable $e ) {
			return false;
		}
	}

	/**
	 * @param string $email
	 * @param string $reason
	 * @return void
	 */
	public static function softCancelPendingRows( string $email, string $reason ): void
	{
		global $_CB_database;

		$email	=	self::normalizeEmail( $email );

		if ( $email === '' ) {
			return;
		}

		$query	=	'UPDATE ' . $_CB_database->NameQuote( '#__comprofiler_plugin_beforeregverify' )
				.	"\n SET " . $_CB_database->NameQuote( 'outcome' ) . " = " . $_CB_database->Quote( VerificationTable::OUTCOME_CANCELLED )
				.	', ' . $_CB_database->NameQuote( 'note' ) . ' = ' . $_CB_database->Quote( $reason )
				.	', ' . $_CB_database->NameQuote( 'modified_at' ) . ' = ' . $_CB_database->Quote( Application::Database()->getUtcDateTime() )
				.	"\n WHERE " . $_CB_database->NameQuote( 'email' ) . " = " . $_CB_database->Quote( $email )
				.	"\n AND " . $_CB_database->NameQuote( 'outcome' ) . " = " . $_CB_database->Quote( VerificationTable::OUTCOME_PENDING )
				.	"\n AND " . $_CB_database->NameQuote( 'status' ) . " IN ( " . $_CB_database->Quote( VerificationTable::STATUS_SENT ) . ', ' . $_CB_database->Quote( VerificationTable::STATUS_RESENT ) . ' )';
		$_CB_database->setQuery( $query );
		$_CB_database->query();
	}

	/**
	 * @param VerificationTable $row
	 * @param string            $reason
	 * @return bool
	 */
	public static function cancelRequest( VerificationTable $row, string $reason ): bool
	{
		return self::transitionPending( $row, VerificationTable::OUTCOME_CANCELLED, $reason );
	}

	/**
	 * @param string $view
	 * @param array  $vars
	 * @return string
	 */
	public static function renderView( string $view, array $vars = [] ): string
	{
		if ( ! in_array( $view, [ 'step_email', 'step_code' ], true ) ) {
			return '';
		}

		$template	=	__DIR__ . '/../templates/default/' . $view . '.php';

		if ( ! file_exists( $template ) ) {
			return '';
		}

		$flowEmail	=	$vars['flowEmail'] ?? '';

		ob_start();
		include $template;
		$html	=	(string) ob_get_clean();

		return '<div class="cbBeforeRegVerify">' . $html . '</div>';
	}

	/**
	 * @param string $ip
	 * @param string $offset
	 * @return int
	 */
	private static function countIssuedRowsByIp( string $ip, string $offset ): int
	{
		global $_CB_database;

		if ( $ip === '' ) {
			return 0;
		}

		$since	=	Application::Date( 'now', 'UTC' )->modify( $offset )->format( 'Y-m-d H:i:s' );
		$query	=	'SELECT COUNT(*)'
				.	"\n FROM " . $_CB_database->NameQuote( '#__comprofiler_plugin_beforeregverify' )
				.	"\n WHERE " . $_CB_database->NameQuote( 'request_ip' ) . " = " . $_CB_database->Quote( $ip )
				.	"\n AND " . $_CB_database->NameQuote( 'status' ) . " IN ( " . $_CB_database->Quote( VerificationTable::STATUS_SENT ) . ', ' . $_CB_database->Quote( VerificationTable::STATUS_RESENT ) . ' )'
				.	"\n AND " . $_CB_database->NameQuote( 'sent_at' ) . " >= " . $_CB_database->Quote( $since );
		$_CB_database->setQuery( $query );

		return (int) $_CB_database->loadResult();
	}

	/**
	 * @param string $email
	 * @param string $offset
	 * @return int
	 */
	private static function countIssuedRowsByEmail( string $email, string $offset ): int
	{
		global $_CB_database;

		if ( $email === '' ) {
			return 0;
		}

		$since	=	Application::Date( 'now', 'UTC' )->modify( $offset )->format( 'Y-m-d H:i:s' );
		$query	=	'SELECT COUNT(*)'
				.	"\n FROM " . $_CB_database->NameQuote( '#__comprofiler_plugin_beforeregverify' )
				.	"\n WHERE " . $_CB_database->NameQuote( 'email' ) . " = " . $_CB_database->Quote( $email )
				.	"\n AND " . $_CB_database->NameQuote( 'status' ) . " IN ( " . $_CB_database->Quote( VerificationTable::STATUS_SENT ) . ', ' . $_CB_database->Quote( VerificationTable::STATUS_RESENT ) . ' )'
				.	"\n AND " . $_CB_database->NameQuote( 'sent_at' ) . " >= " . $_CB_database->Quote( $since );
		$_CB_database->setQuery( $query );

		return (int) $_CB_database->loadResult();
	}

	/**
	 * @param string $email
	 * @return string|null
	 */
	private static function getLastIssuedAtByEmail( string $email ): ?string
	{
		global $_CB_database;

		if ( $email === '' ) {
			return null;
		}

		$query	=	'SELECT ' . $_CB_database->NameQuote( 'sent_at' )
				.	"\n FROM " . $_CB_database->NameQuote( '#__comprofiler_plugin_beforeregverify' )
				.	"\n WHERE " . $_CB_database->NameQuote( 'email' ) . " = " . $_CB_database->Quote( $email )
				.	"\n AND " . $_CB_database->NameQuote( 'status' ) . " IN ( " . $_CB_database->Quote( VerificationTable::STATUS_SENT ) . ', ' . $_CB_database->Quote( VerificationTable::STATUS_RESENT ) . ' )'
				// A code that never reached the mailbox must not start a cooldown.
				.	"\n AND ( " . $_CB_database->NameQuote( 'note' ) . ' IS NULL OR ' . $_CB_database->NameQuote( 'note' ) . ' <> ' . $_CB_database->Quote( self::NOTE_MAIL_SEND_FAILED ) . ' )'
				.	"\n ORDER BY " . $_CB_database->NameQuote( 'sent_at' ) . ' DESC';
		$_CB_database->setQuery( $query, 0, 1 );
		$sentAt	=	$_CB_database->loadResult();

		return ( $sentAt ? (string) $sentAt : null );
	}

	/**
	 * @param string $context
	 * @param string $details
	 * @return void
	 */
	public static function logError( string $context, string $details = '' ): void
	{
		static $loggerAdded	=	false;

		$message	=	$context;

		if ( $details !== '' ) {
			$message	.=	': ' . $details;
		}

		try {
			// Joomla only writes categories that have a logger registered.
			if ( ! $loggerAdded ) {
				Log::addLogger( [ 'text_file' => 'cbbeforeregverify.php' ], Log::ALL, [ self::LOG_CATEGORY ] );

				$loggerAdded	=	true;
			}

			Log::add( $message, Log::ERROR, self::LOG_CATEGORY );
		} catch ( \Throwable $e ) {
			error_log( 'cbbeforeregverify: ' . $message );
		}
	}
}
