<?php
/**
 * CBBeforeRegVerify Plugin
 * @version $Id: $
 * @package CBBeforeRegVerify
 * @copyright (C) 2026 Yak Shaver https://www.kayakshaver.com/
 * @license http://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU/GPL version 2
 */

namespace CB\Plugin\BeforeRegVerify\Trigger;

use CB\Plugin\BeforeRegVerify\CBBeforeRegVerify;
use CBLib\Application\Application;
use CBLib\Language\CBTxt;

\defined( 'CBLIB' ) or die();

class UserTrigger extends \cbPluginHandler
{
	/**
	 * Sync flow email from verified session before registration form path.
	 *
	 * @param string|null $msg
	 * @param string      $emailpass
	 * @param string|null $regErrorMSG
	 * @return void
	 */
	public function onBeforeRegisterFormRequest( &$msg, $emailpass, &$regErrorMSG ): void
	{
		if ( ! CBBeforeRegVerify::isEnabled() ) {
			return;
		}

		$verifiedEmail	=	CBBeforeRegVerify::getVerifiedEmail();

		if ( $verifiedEmail !== '' ) {
			CBBeforeRegVerify::setFlowEmail( $verifiedEmail );
		}
	}

	/**
	 * Replace registration UI with gateway step-email form when no verified session exists.
	 *
	 * @param string      $option
	 * @param string      $emailpass
	 * @param string|null $regErrorMSG
	 * @param mixed       $fieldsQuery
	 * @return null|string
	 */
	public function onBeforeRegisterForm( $option, $emailpass, &$regErrorMSG, $fieldsQuery )
	{
		if ( ! CBBeforeRegVerify::isEnabled() ) {
			return null;
		}

		if ( CBBeforeRegVerify::hasVerifiedSession() ) {
			return null;
		}

		return CBBeforeRegVerify::renderView(
			'step_email',
			[
				'flowEmail'	=>	CBBeforeRegVerify::getFlowEmail()
			]
		);
	}

	/**
	 * Prefill and lock the email field to the verified address before form display.
	 *
	 * @param mixed $user
	 * @param mixed $regErrorMSG
	 * @return void
	 */
	public function onBeforeRegisterFormDisplay( &$user, $regErrorMSG ): void
	{
		if ( ! CBBeforeRegVerify::isEnabled() ) {
			return;
		}

		$verifiedEmail	=	CBBeforeRegVerify::getVerifiedEmail();

		if ( $verifiedEmail === '' ) {
			return;
		}

		if ( is_object( $user ) && method_exists( $user, 'set' ) ) {
			$user->set( 'email', $verifiedEmail );
		} elseif ( is_object( $user ) ) {
			$user->email	=	$verifiedEmail;
		}

		// CB reads the email field value from $_POST during registration form rendering;
		// setting it here ensures the verified address populates the form input.
		$_POST['email']	=	$verifiedEmail;
	}

	/**
	 * Inject verified email as read-only value and add restart control after form display.
	 *
	 * @param mixed $user
	 * @param mixed $tabContent
	 * @param mixed $return
	 * @return void
	 */
	public function onAfterRegisterFormDisplay( $user, $tabContent, &$return ): void
	{
		if ( ! CBBeforeRegVerify::isEnabled() ) {
			return;
		}

		$verifiedEmail	=	CBBeforeRegVerify::getVerifiedEmail();

		if ( $verifiedEmail === '' ) {
			return;
		}

		$emailEscaped	=	htmlspecialchars( $verifiedEmail, ENT_QUOTES, 'UTF-8' );
		$restartAction	=	htmlspecialchars( CBBeforeRegVerify::getGatewayUrl( 'restart' ), ENT_QUOTES, 'UTF-8' );
		$restartText	=	htmlspecialchars( CBTxt::T( 'CBBEFOREREGVERIFY_RESTART', 'Use a different email' ), ENT_QUOTES, 'UTF-8' );
		$restartHint	=	htmlspecialchars(
			CBTxt::T( 'CBBEFOREREGVERIFY_RESTART_HINT', 'Need to sign up with another email address? Restart verification first.' ),
			ENT_QUOTES,
			'UTF-8'
		);
		$restartTokenInput	=	(string) Application::Session()->getFormTokenInput();
		$restartInjected	=	false;

		$return			=	(string) preg_replace_callback(
			'/<input\b[^>]*\bname\s*=\s*(["\'])email\1[^>]*>/i',
			static function( array $match ) use ( $emailEscaped, $restartAction, $restartText, $restartHint, $restartTokenInput, &$restartInjected ): string {
				$input	=	$match[0];

				if ( preg_match( '/\btype\s*=\s*(["\'])hidden\1/i', $input ) ) {
					return $input;
				}

				if ( preg_match( '/\bvalue\s*=\s*(["\']).*?\1/i', $input ) ) {
					$input	=	(string) preg_replace( '/\bvalue\s*=\s*(["\']).*?\1/i', 'value="' . $emailEscaped . '"', $input );
				} else {
					$input	=	rtrim( $input, '>' ) . ' value="' . $emailEscaped . '">';
				}

				if ( stripos( $input, 'readonly' ) === false ) {
					$input	=	rtrim( $input, '>' ) . ' readonly="readonly">';
				}

				if ( ! $restartInjected ) {
					$restartInjected	=	true;
					$input			.=	'<div class="cbBeforeRegVerifyRestart mt-2">'
							.	'<form action="' . $restartAction . '" method="post" class="cbBeforeRegVerifyRestartForm d-inline">'
							.	'<button type="submit" class="btn btn-outline-secondary btn-sm">' . $restartText . '</button>'
							.	$restartTokenInput
							.	'</form>'
							.	'<p class="small mt-1 mb-0">' . $restartHint . '</p>'
							.	'</div>';
				}

				return $input;
			},
			(string) $return
		);
	}

	/**
	 * Block registration save unless verified session exists and email matches.
	 *
	 * @param string|null $msg
	 * @return void
	 */
	public function onBeforeSaveUserRegistrationRequest( &$msg ): void
	{
		if ( ! CBBeforeRegVerify::isEnabled() ) {
			return;
		}

		if ( ! Application::Session()->checkFormToken( 'post', 0 ) ) {
			CBBeforeRegVerify::clearVerificationSession();

			cbRedirect(
				CBBeforeRegVerify::getGatewayUrl( 'step_email' ),
				CBTxt::T( 'CBBEFOREREGVERIFY_INVALID_CSRF', 'Your session token is invalid. Please try again.' ),
				'error'
			);
		}

		$verifiedEmail	=	CBBeforeRegVerify::getVerifiedEmail();
		$postedEmail	=	CBBeforeRegVerify::normalizeEmail( Application::Input()->getString( 'post/email', '' ) );

		if ( $verifiedEmail === '' ) {
			CBBeforeRegVerify::clearVerificationSession();

			cbRedirect(
				CBBeforeRegVerify::getGatewayUrl( 'step_email' ),
				CBTxt::T( 'CBBEFOREREGVERIFY_VERIFY_REQUIRED', 'Complete email verification before submitting registration.' ),
				'error'
			);
		}

		if ( ( $postedEmail === '' ) || ( $postedEmail !== $verifiedEmail ) ) {
			CBBeforeRegVerify::clearVerificationSession();

			cbRedirect(
				CBBeforeRegVerify::getGatewayUrl( 'step_email' ),
				CBTxt::T( 'CBBEFOREREGVERIFY_EMAIL_MISMATCH', 'Verified email does not match submitted email. Please verify again.' ),
				'error'
			);
		}

		// CB re-reads $_POST['email'] during the save pipeline; overwriting it here
		// prevents a tampered POST value from diverging from the verified address.
		$_POST['email']	=	$verifiedEmail;
		CBBeforeRegVerify::setFlowEmail( $verifiedEmail );
	}

	/**
	 * Milestone 6: set confirmed state at registration-time for gateway-verified users.
	 *
	 * @param mixed $user
	 * @param mixed ...$args
	 * @return void
	 */
	public function onBeforeUserRegistration( &$user, ...$args ): void
	{
		if ( ! CBBeforeRegVerify::isEnabled() ) {
			return;
		}

		$verifiedEmail	=	CBBeforeRegVerify::getVerifiedEmail();

		if ( $verifiedEmail === '' ) {
			return;
		}

		$registrationEmail	=	$this->getUserEmail( $user );

		if ( ( $registrationEmail === '' ) || ( CBBeforeRegVerify::normalizeEmail( $registrationEmail ) !== $verifiedEmail ) ) {
			return;
		}

		$this->setUserField( $user, 'confirmed', 1 );
	}

	/**
	 * @param mixed $user
	 * @return string
	 */
	private function getUserEmail( $user ): string
	{
		if ( is_object( $user ) && method_exists( $user, 'get' ) ) {
			return (string) $user->get( 'email', '' );
		}

		if ( is_array( $user ) ) {
			return (string) ( $user['email'] ?? '' );
		}

		if ( is_object( $user ) ) {
			return (string) ( $user->email ?? '' );
		}

		return '';
	}

	/**
	 * @param mixed  $user
	 * @param string $field
	 * @param mixed  $value
	 * @return void
	 */
	private function setUserField( &$user, string $field, $value ): void
	{
		if ( is_object( $user ) && method_exists( $user, 'set' ) ) {
			$user->set( $field, $value );

			return;
		}

		if ( is_array( $user ) ) {
			$user[$field]	=	$value;

			return;
		}

		if ( is_object( $user ) ) {
			$user->{$field}	=	$value;
		}
	}

	/**
	 * Clear verification session state after successful registration.
	 *
	 * @param mixed $user
	 * @param mixed $messagesToUser
	 * @param mixed $ui
	 * @return void
	 */
	public function onAfterSaveUserRegistration( &$user, &$messagesToUser, $ui ): void
	{
		if ( ! CBBeforeRegVerify::isEnabled() ) {
			return;
		}

		CBBeforeRegVerify::clearVerificationSession();
	}
}
