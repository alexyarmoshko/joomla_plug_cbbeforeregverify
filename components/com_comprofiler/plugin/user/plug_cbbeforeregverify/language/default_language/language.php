<?php
/**
 * CBBeforeRegVerify Default (English) language file Frontend
 * @version $Id:$
 * @copyright (C) 2026 Yak Shaver https://www.kayakshaver.com/
 * @license http://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU/GPL version 2
 */

/**
 * WARNING:
 * Do not make changes to this file as it will be over-written when you upgrade CB.
 * To localize you need to create your own CB language plugin and make changes there.
 */

defined( 'CBLIB' ) or die();

return array(
	'CBBEFOREREGVERIFY_MILESTONE1_ONLY'		=>	'CBBeforeRegVerify Milestone 1 scaffold is installed. Verification flow will be implemented in the next milestone.',
	'CBBEFOREREGVERIFY_STEP_EMAIL_TITLE'	=>	'Verify your email to continue',
	'CBBEFOREREGVERIFY_STEP_EMAIL_BODY'		=>	'Enter your email address and we will send a verification code.',
	'CBBEFOREREGVERIFY_STEP_CODE_TITLE'		=>	'Enter your verification code',
	'CBBEFOREREGVERIFY_STEP_CODE_BODY'		=>	'We sent a verification code to [email]. Enter it below to continue.',
	'CBBEFOREREGVERIFY_EMAIL_REQUIRED'		=>	'Email is required.',
	'CBBEFOREREGVERIFY_STATUS_REQUIRED'		=>	'Status is required.',
	'CBBEFOREREGVERIFY_OUTCOME_REQUIRED'	=>	'Outcome is required.',
	'CBBEFOREREGVERIFY_STATUS_INVALID'		=>	'Status is invalid.',
	'CBBEFOREREGVERIFY_OUTCOME_INVALID'		=>	'Outcome is invalid.',
	'CBBEFOREREGVERIFY_EMAIL_LABEL'			=>	'Email Address',
	'CBBEFOREREGVERIFY_SEND_CODE'			=>	'Send Verification Code',
	'CBBEFOREREGVERIFY_CODE_LABEL'			=>	'Verification Code',
	'CBBEFOREREGVERIFY_SUBMIT_CODE'			=>	'Verify Code',
	'CBBEFOREREGVERIFY_RESEND_CODE'			=>	'Resend Code',
	'CBBEFOREREGVERIFY_CANCEL'				=>	'Cancel',
	'CBBEFOREREGVERIFY_RESTART'				=>	'Use a different email',
	'CBBEFOREREGVERIFY_RESTART_HINT'		=>	'Need to sign up with another email address? Restart verification first.',
	'CBBEFOREREGVERIFY_INVALID_CSRF'		=>	'Your session token is invalid. Please try again.',
	'CBBEFOREREGVERIFY_EMAIL_INVALID'		=>	'Enter a valid email address.',
	'CBBEFOREREGVERIFY_CODE_INVALID'		=>	'The verification code is incorrect. Please try again.',
	'CBBEFOREREGVERIFY_CODE_VERIFIED'		=>	'Email verification complete. Continue with registration.',
	'CBBEFOREREGVERIFY_VERIFY_STORE_FAILED'	=>	'Unable to complete verification. Please try again.',
	'CBBEFOREREGVERIFY_ATTEMPT_STORE_FAILED'=>	'Failed to record verification attempt.',
	'CBBEFOREREGVERIFY_ATTEMPTS_EXCEEDED'	=>	'Too many incorrect code attempts. Please request a new code.',
	'CBBEFOREREGVERIFY_RATE_LIMITED'		=>	'Too many verification requests. Please try again later.',
	'CBBEFOREREGVERIFY_RATE_LIMIT_IP_SHORT'	=>	'Too many verification requests from this IP address. Please try again in [minutes] minute(s).',
	'CBBEFOREREGVERIFY_RATE_LIMIT_IP_DAY'	=>	'Too many verification requests from this IP address. Please try again in [hours] hour(s).',
	'CBBEFOREREGVERIFY_RATE_LIMIT_EMAIL_SHORT'	=>	'Too many verification requests for this email. Please try again in [minutes] minute(s).',
	'CBBEFOREREGVERIFY_RATE_LIMIT_EMAIL_DAY'	=>	'Too many verification requests for this email. Please try again in [hours] hour(s).',
	'CBBEFOREREGVERIFY_RATE_LIMIT_RESEND'	=>	'Please wait [seconds] second(s) before requesting another code.',
	'CBBEFOREREGVERIFY_SECRET_REQUIRED'		=>	'Verification secret is required before this gateway can be used.',
	'CBBEFOREREGVERIFY_ISSUE_FAILED'		=>	'Failed to create verification request.',
	'CBBEFOREREGVERIFY_EMAIL_SEND_FAILED'	=>	'Verification email failed to send. Please try again.',
	'CBBEFOREREGVERIFY_EMAIL_SUBMIT_FAILED'	=>	'Unable to start verification right now. Please try again.',
	'CBBEFOREREGVERIFY_CODE_SENT'			=>	'Verification code sent. Check your email and continue with code entry.',
	'CBBEFOREREGVERIFY_CODE_RESENT'			=>	'A new verification code has been sent to your email address.',
	'CBBEFOREREGVERIFY_RESEND_FAILED'		=>	'Unable to resend verification code right now.',
	'CBBEFOREREGVERIFY_EXPIRED'				=>	'Your verification code has expired. Please request a new code.',
	'CBBEFOREREGVERIFY_NO_ACTIVE_REQUEST'	=>	'Your verification request is not active. Please start again.',
	'CBBEFOREREGVERIFY_CANCELLED'			=>	'Verification was cancelled.',
	'CBBEFOREREGVERIFY_RESTARTED'			=>	'Email verification was reset. Enter a new email address to continue.',
	'CBBEFOREREGVERIFY_START_WITH_EMAIL'	=>	'Enter your email first to begin verification.',
	'CBBEFOREREGVERIFY_VERIFY_REQUIRED'		=>	'Complete email verification before submitting registration.',
	'CBBEFOREREGVERIFY_EMAIL_MISMATCH'		=>	'Verified email does not match submitted email. Please verify again.',
	'CBBEFOREREGVERIFY_MAIL_SUBJECT'		=>	'Your verification code',
	'CBBEFOREREGVERIFY_MAIL_BODY'			=>	'Your verification code is [code]. This code expires in [minutes] minute(s).',
);
