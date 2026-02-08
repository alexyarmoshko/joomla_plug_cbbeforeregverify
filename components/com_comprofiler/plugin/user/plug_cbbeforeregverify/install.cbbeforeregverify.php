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

if ( ! ( defined( '_VALID_CB' ) || defined( '_JEXEC' ) || defined( '_VALID_MOS' ) ) ) { die( 'Direct Access to this location is not allowed.' ); }

class cbbeforeregverifyInstaller
{
	private const MAIL_TEMPLATE_KEY			=	'comprofiler.cbbeforeregverify.verification_code';
	private const MAIL_TEMPLATE_TITLE_KEY	=	'comprofiler_MAIL_cbbeforeregverify_verification_code_TITLE';
	private const MAIL_TEMPLATE_DESC_KEY	=	'comprofiler_MAIL_cbbeforeregverify_verification_code_DESC';
	private const MAIL_TEMPLATE_SHORT_KEY	=	'comprofiler_MAIL_cbbeforeregverify_verification_code_SHORT';
	private const MAIL_TEMPLATE_TITLE_KEY_UC	=	'COMPROFILER_MAIL_CBBEFOREREGVERIFY_VERIFICATION_CODE_TITLE';
	private const MAIL_TEMPLATE_DESC_KEY_UC		=	'COMPROFILER_MAIL_CBBEFOREREGVERIFY_VERIFICATION_CODE_DESC';
	private const MAIL_TEMPLATE_SHORT_KEY_UC	=	'COMPROFILER_MAIL_CBBEFOREREGVERIFY_VERIFICATION_CODE_SHORT';
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
				[
					'CODE'		=>	'Verification code',
					'MINUTES'	=>	'Minutes until expiration'
				],
				'<p>Your verification code is <strong>{CODE}</strong>.</p><p>This code expires in {MINUTES} minute(s).</p>'
			);
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
			self::MAIL_TEMPLATE_SHORT_KEY	=>	'Verification Code',
			self::MAIL_TEMPLATE_TITLE_KEY_UC	=>	'CB Before Registration Verify: Verification Code',
			self::MAIL_TEMPLATE_DESC_KEY_UC		=>	'Email sent with the verification code before Community Builder registration.',
			self::MAIL_TEMPLATE_SHORT_KEY_UC	=>	'Verification Code'
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

				if ( is_array( $parsed ) ) {
					$current	=	$parsed;
				}
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
