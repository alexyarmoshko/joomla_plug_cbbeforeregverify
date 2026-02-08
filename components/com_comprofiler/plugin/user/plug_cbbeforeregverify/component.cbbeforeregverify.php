<?php
/**
 * CBBeforeRegVerify Plugin
 * @version $Id: $
 * @package CBBeforeRegVerify
 * @copyright (C) 2026 Yak Shaver https://www.kayakshaver.com/
 * @license http://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU/GPL version 2
 */

use CB\Plugin\BeforeRegVerify\CBBeforeRegVerify;
use CB\Plugin\BeforeRegVerify\Table\VerificationTable;
use CBLib\Application\Application;
use CBLib\Language\CBTxt;
use Joomla\CMS\Log\Log;

if ( ! ( defined( '_VALID_CB' ) || defined( '_JEXEC' ) || defined( '_VALID_MOS' ) ) ) { die( 'Direct Access to this location is not allowed.' ); }

class CBplug_cbbeforeregverify extends cbPluginHandler
{
	/**
	 * @param null|CB\Database\Table\TabTable  $tab
	 * @param null|CB\Database\Table\UserTable $user
	 * @param null|int                          $ui
	 * @param null|array                        $postdata
	 */
	public function getCBpluginComponent( $tab, $user, $ui, $postdata ): void
	{
		outputCbJs();
		outputCbTemplate();

		if ( ! CBBeforeRegVerify::isEnabled() ) {
			cbRedirect( CBBeforeRegVerify::getRegistrationUrl() );
		}

		$function	=	$this->getInput()->getString( 'func', '' );

		switch ( $function ) {
			case 'submit_email':
				$this->submitEmail();
				return;
			case 'submit_code':
				$this->submitCode();
				return;
			case 'resend':
				$this->resendCode();
				return;
			case 'cancel':
				$this->cancelFlow();
				return;
			case 'restart':
				$this->restartFlow();
				return;
			case 'step_code':
				$this->showStepCode();
				return;
			case 'step_email':
			default:
				$this->renderView( 'step_email' );
				return;
		}
	}

	/**
	 * @return void
	 */
	private function submitEmail(): void
	{
		if ( ! Application::Session()->checkFormToken() ) {
			cbRedirect(
				CBBeforeRegVerify::getGatewayUrl( 'step_email' ),
				CBTxt::T( 'CBBEFOREREGVERIFY_INVALID_CSRF', 'Your session token is invalid. Please try again.' ),
				'error'
			);
		}

		$email	=	CBBeforeRegVerify::normalizeEmail( $this->getInput()->getString( 'post/email', '' ) );

		if ( ( $email === '' ) || ( ! cbIsValidEmail( $email ) ) ) {
			cbRedirect(
				CBBeforeRegVerify::getGatewayUrl( 'step_email' ),
				CBTxt::T( 'CBBEFOREREGVERIFY_EMAIL_INVALID', 'Enter a valid email address.' ),
				'error'
			);
		}

		try {
			CBBeforeRegVerify::issueInitialCode( $email, (string) Application::Input()->getRequestIP() );
		} catch ( \Throwable $e ) {
			$this->logException( $e, 'submit_email' );

			cbRedirect(
				CBBeforeRegVerify::getGatewayUrl( 'step_email' ),
				CBTxt::T( 'CBBEFOREREGVERIFY_EMAIL_SUBMIT_FAILED', 'Unable to start verification right now. Please try again.' ),
				'error'
			);
		}

		CBBeforeRegVerify::setFlowEmail( $email );
		CBBeforeRegVerify::clearVerifiedEmail();

		cbRedirect(
			CBBeforeRegVerify::getGatewayUrl( 'step_code' ),
			CBTxt::T( 'CBBEFOREREGVERIFY_CODE_SENT', 'Verification code sent. Check your email and continue with code entry.' )
		);
	}

	/**
	 * @return void
	 */
	private function showStepCode(): void
	{
		$email	=	$this->getFlowEmailOrRedirect();
		$row	=	CBBeforeRegVerify::getActiveVerificationRow( $email );

		if ( ! $row ) {
			CBBeforeRegVerify::clearFlowEmail();

			cbRedirect(
				CBBeforeRegVerify::getGatewayUrl( 'step_email' ),
				CBTxt::T( 'CBBEFOREREGVERIFY_NO_ACTIVE_REQUEST', 'Your verification request is not active. Please start again.' ),
				'error'
			);
		}

		if ( CBBeforeRegVerify::isExpired( $row ) ) {
			$this->expireAndRestart( $row );
		}

		$this->renderView( 'step_code' );
	}

	/**
	 * @return void
	 */
	private function submitCode(): void
	{
		$this->requireCsrf( 'step_code' );

		$email	=	$this->getFlowEmailOrRedirect();
		$row	=	CBBeforeRegVerify::getActiveVerificationRow( $email );

		if ( ! $row ) {
			CBBeforeRegVerify::clearFlowEmail();

			cbRedirect(
				CBBeforeRegVerify::getGatewayUrl( 'step_email' ),
				CBTxt::T( 'CBBEFOREREGVERIFY_NO_ACTIVE_REQUEST', 'Your verification request is not active. Please start again.' ),
				'error'
			);
		}

		if ( CBBeforeRegVerify::isExpired( $row ) ) {
			$this->expireAndRestart( $row );
		}

		if ( CBBeforeRegVerify::isAttemptsLimitReached( $email ) ) {
			cbRedirect(
				CBBeforeRegVerify::getGatewayUrl( 'step_code' ),
				CBTxt::T( 'CBBEFOREREGVERIFY_ATTEMPTS_EXCEEDED', 'Too many incorrect code attempts. Please request a new code.' ),
				'error'
			);
		}

		$code	=	trim( $this->getInput()->getString( 'post/verification_code', '' ) );

		if ( $code === '' ) {
			cbRedirect(
				CBBeforeRegVerify::getGatewayUrl( 'step_code' ),
				CBTxt::T( 'CBBEFOREREGVERIFY_CODE_INVALID', 'The verification code is incorrect. Please try again.' ),
				'error'
			);
		}

		if ( CBBeforeRegVerify::verifyCode( $row, $code ) ) {
			if ( ! CBBeforeRegVerify::markVerified( $row ) ) {
				cbRedirect(
					CBBeforeRegVerify::getGatewayUrl( 'step_code' ),
					CBTxt::T( 'CBBEFOREREGVERIFY_VERIFY_STORE_FAILED', 'Unable to complete verification. Please try again.' ),
					'error'
				);
			}

			CBBeforeRegVerify::regenerateSessionId();
			CBBeforeRegVerify::setFlowEmail( $email );
			CBBeforeRegVerify::setVerifiedEmail( $email );

			cbRedirect(
				CBBeforeRegVerify::getRegistrationUrl(),
				CBTxt::T( 'CBBEFOREREGVERIFY_CODE_VERIFIED', 'Email verification complete. Continue with registration.' )
			);
		}

		try {
			CBBeforeRegVerify::recordFailedAttempt( $row, $email, (string) Application::Input()->getRequestIP() );
		} catch ( \Throwable $e ) {
			$this->logException( $e, 'submit_code_failed_attempt' );

			cbRedirect(
				CBBeforeRegVerify::getGatewayUrl( 'step_code' ),
				CBTxt::T( 'CBBEFOREREGVERIFY_ATTEMPT_STORE_FAILED', 'Failed to record verification attempt.' ),
				'error'
			);
		}

		if ( CBBeforeRegVerify::isAttemptsLimitReached( $email ) ) {
			cbRedirect(
				CBBeforeRegVerify::getGatewayUrl( 'step_code' ),
				CBTxt::T( 'CBBEFOREREGVERIFY_ATTEMPTS_EXCEEDED', 'Too many incorrect code attempts. Please request a new code.' ),
				'error'
			);
		}

		cbRedirect(
			CBBeforeRegVerify::getGatewayUrl( 'step_code' ),
			CBTxt::T( 'CBBEFOREREGVERIFY_CODE_INVALID', 'The verification code is incorrect. Please try again.' ),
			'error'
		);
	}

	/**
	 * @return void
	 */
	private function resendCode(): void
	{
		$this->requireCsrf( 'step_code' );

		$email	=	$this->getFlowEmailOrRedirect();
		$row	=	CBBeforeRegVerify::getActiveVerificationRow( $email );

		if ( $row && CBBeforeRegVerify::isExpired( $row ) ) {
			$this->expireAndRestart( $row );
		}

		try {
			CBBeforeRegVerify::issueResendCode( $email, (string) Application::Input()->getRequestIP() );
		} catch ( \Throwable $e ) {
			$this->logException( $e, 'resend_code' );

			cbRedirect(
				CBBeforeRegVerify::getGatewayUrl( 'step_code' ),
				CBTxt::T( 'CBBEFOREREGVERIFY_RESEND_FAILED', 'Unable to resend verification code right now.' ),
				'error'
			);
		}

		cbRedirect(
			CBBeforeRegVerify::getGatewayUrl( 'step_code' ),
			CBTxt::T( 'CBBEFOREREGVERIFY_CODE_RESENT', 'A new verification code has been sent to your email address.' )
		);
	}

	/**
	 * @return void
	 */
	private function cancelFlow(): void
	{
		$this->requireCsrf( 'step_email' );

		$email	=	CBBeforeRegVerify::getFlowEmail();

		if ( $email !== '' ) {
			$row	=	CBBeforeRegVerify::getActiveVerificationRow( $email );

			if ( $row ) {
				if ( CBBeforeRegVerify::isExpired( $row ) ) {
					$this->expireAndRestart( $row );
				}

				CBBeforeRegVerify::cancelRequest( $row, 'user_cancelled' );
			}
		}

		CBBeforeRegVerify::clearFlowEmail();
		CBBeforeRegVerify::clearVerifiedEmail();

		cbRedirect(
			'index.php',
			CBTxt::T( 'CBBEFOREREGVERIFY_CANCELLED', 'Verification was cancelled.' )
		);
	}

	/**
	 * @return void
	 */
	private function restartFlow(): void
	{
		$this->requireCsrf( 'step_email' );

		$flowEmail	=	CBBeforeRegVerify::getFlowEmail();

		if ( $flowEmail !== '' ) {
			CBBeforeRegVerify::softCancelPendingRows( $flowEmail, 'user_restarted' );
		}

		CBBeforeRegVerify::clearVerificationSession();

		cbRedirect(
			CBBeforeRegVerify::getGatewayUrl( 'step_email' ),
			CBTxt::T( 'CBBEFOREREGVERIFY_RESTARTED', 'Email verification was reset. Enter a new email address to continue.' )
		);
	}

	/**
	 * @param VerificationTable $row
	 * @return void
	 */
	private function expireAndRestart( VerificationTable $row ): void
	{
		CBBeforeRegVerify::cancelRequest( $row, 'expired' );
		CBBeforeRegVerify::clearFlowEmail();
		CBBeforeRegVerify::clearVerifiedEmail();

		cbRedirect(
			CBBeforeRegVerify::getGatewayUrl( 'step_email' ),
			CBTxt::T( 'CBBEFOREREGVERIFY_EXPIRED', 'Your verification code has expired. Please request a new code.' ),
			'error'
		);
	}

	/**
	 * @param string $redirectFunc
	 * @return void
	 */
	private function requireCsrf( string $redirectFunc ): void
	{
		if ( ! Application::Session()->checkFormToken() ) {
			cbRedirect(
				CBBeforeRegVerify::getGatewayUrl( $redirectFunc ),
				CBTxt::T( 'CBBEFOREREGVERIFY_INVALID_CSRF', 'Your session token is invalid. Please try again.' ),
				'error'
			);
		}
	}

	/**
	 * @return string
	 */
	private function getFlowEmailOrRedirect(): string
	{
		$email	=	CBBeforeRegVerify::getFlowEmail();

		if ( $email === '' ) {
			cbRedirect(
				CBBeforeRegVerify::getGatewayUrl( 'step_email' ),
				CBTxt::T( 'CBBEFOREREGVERIFY_START_WITH_EMAIL', 'Enter your email first to begin verification.' ),
				'error'
			);
		}

		return $email;
	}

	/**
	 * @param string $view
	 * @return void
	 */
	private function renderView( string $view ): void
	{
		echo CBBeforeRegVerify::renderView(
			$view,
			[
				'flowEmail'		=>	CBBeforeRegVerify::getFlowEmail(),
				'codeLength'	=>	CBBeforeRegVerify::getCodeLength()
			]
		);
	}

	/**
	 * @param \Throwable $exception
	 * @param string     $context
	 * @return void
	 */
	private function logException( \Throwable $exception, string $context ): void
	{
		$message	=	$context . ': ' . $exception->getMessage()
				.	' in ' . $exception->getFile() . ':' . $exception->getLine();

		if ( class_exists( Log::class ) ) {
			Log::add( $message, Log::ERROR, 'cbbeforeregverify' );

			return;
		}

		error_log( 'cbbeforeregverify: ' . $message );
	}
}
