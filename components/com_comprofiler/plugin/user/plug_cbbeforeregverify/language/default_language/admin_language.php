<?php
/**
 * CBBeforeRegVerify Default (English) language file Administration
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
	'CB_BEFORE_REGISTRATION_VERIFY'					=>	'CB Before Registration Verify',
	'CBBEFOREREGVERIFY_MANIFEST_NAME'					=>	'Yak Shaver CB Before Registration Email Verify',
	'CBBEFOREREGVERIFY_MANIFEST_DESCRIPTION'			=>	'Pre-registration email verification gateway for CB user registration form.',
	'CBBEFOREREGVERIFY_MANIFEST_COPYRIGHT'				=>	'(C) 2026 Yak Shaver https://www.kayakshaver.com',
	'CBBEFOREREGVERIFY_MANIFEST_LICENSE'				=>	'GNU General Public License version 2 or later',
	'CBBEFOREREGVERIFY_TAB_GENERAL'					=>	'General',
	'CBBEFOREREGVERIFY_TAB_IP_RATE_LIMITS'				=>	'IP Rate Limits',
	'CBBEFOREREGVERIFY_TAB_EMAIL_RATE_LIMITS'			=>	'Email Rate Limits',
	'CBBEFOREREGVERIFY_TAB_RESEND_AND_ATTEMPTS'			=>	'Resend and Attempts',
	'CBBEFOREREGVERIFY_OPTION_ENABLE'					=>	'Enable',
	'CBBEFOREREGVERIFY_OPTION_DISABLE'					=>	'Disable',
	'CBBEFOREREGVERIFY_PARAM_GATEWAY_ENABLED_LABEL'			=>	'Gateway Enabled',
	'CBBEFOREREGVERIFY_PARAM_GATEWAY_ENABLED_DESC'			=>	'Enable or disable the pre-registration verification gateway.',
	'CBBEFOREREGVERIFY_PARAM_VERIFICATION_TTL_SEC_LABEL'		=>	'Verification TTL (seconds)',
	'CBBEFOREREGVERIFY_PARAM_VERIFICATION_TTL_SEC_DESC'		=>	'Number of seconds a verification request remains valid after issuance.',
	'CBBEFOREREGVERIFY_PARAM_CODE_LENGTH_LABEL'			=>	'Code Length',
	'CBBEFOREREGVERIFY_PARAM_CODE_LENGTH_DESC'			=>	'Numeric code length for new verification requests.',
	'CBBEFOREREGVERIFY_PARAM_SECRET_LABEL'				=>	'Verification Secret',
	'CBBEFOREREGVERIFY_PARAM_SECRET_DESC'				=>	'Secret used for verification code hashing as sha256(code + secret). Must be non-empty when gateway is enabled.',
	'CBBEFOREREGVERIFY_PARAM_PURGE_AFTER_DAYS_LABEL'		=>	'Purge After (days)',
	'CBBEFOREREGVERIFY_PARAM_PURGE_AFTER_DAYS_DESC'		=>	'Delete stale rows older than this many days while retaining unexpired pending requests.',
	'CBBEFOREREGVERIFY_PARAM_RL_IP_ENABLED_LABEL'			=>	'IP Limits Enabled',
	'CBBEFOREREGVERIFY_PARAM_RL_IP_ENABLED_DESC'			=>	'Enable or disable IP based send limiting.',
	'CBBEFOREREGVERIFY_PARAM_RL_IP_SHORT_WINDOW_MIN_LABEL'		=>	'IP Short Window (minutes)',
	'CBBEFOREREGVERIFY_PARAM_RL_IP_SHORT_WINDOW_MIN_DESC'		=>	'Short IP limiter window length in minutes.',
	'CBBEFOREREGVERIFY_PARAM_RL_IP_SHORT_MAX_LABEL'			=>	'IP Short Window Max',
	'CBBEFOREREGVERIFY_PARAM_RL_IP_SHORT_MAX_DESC'			=>	'Maximum sends per IP in the short window.',
	'CBBEFOREREGVERIFY_PARAM_RL_IP_DAY_WINDOW_HOURS_LABEL'		=>	'IP Day Window (hours)',
	'CBBEFOREREGVERIFY_PARAM_RL_IP_DAY_WINDOW_HOURS_DESC'		=>	'Day IP limiter window length in hours.',
	'CBBEFOREREGVERIFY_PARAM_RL_IP_DAY_MAX_LABEL'			=>	'IP Day Window Max',
	'CBBEFOREREGVERIFY_PARAM_RL_IP_DAY_MAX_DESC'			=>	'Maximum sends per IP in the day window.',
	'CBBEFOREREGVERIFY_PARAM_RL_EMAIL_ENABLED_LABEL'			=>	'Email Limits Enabled',
	'CBBEFOREREGVERIFY_PARAM_RL_EMAIL_ENABLED_DESC'			=>	'Enable or disable email based send limiting.',
	'CBBEFOREREGVERIFY_PARAM_RL_EMAIL_SHORT_WINDOW_MIN_LABEL'	=>	'Email Short Window (minutes)',
	'CBBEFOREREGVERIFY_PARAM_RL_EMAIL_SHORT_WINDOW_MIN_DESC'	=>	'Short email limiter window length in minutes.',
	'CBBEFOREREGVERIFY_PARAM_RL_EMAIL_SHORT_MAX_LABEL'		=>	'Email Short Window Max',
	'CBBEFOREREGVERIFY_PARAM_RL_EMAIL_SHORT_MAX_DESC'		=>	'Maximum sends per email in the short window.',
	'CBBEFOREREGVERIFY_PARAM_RL_EMAIL_DAY_WINDOW_HOURS_LABEL'	=>	'Email Day Window (hours)',
	'CBBEFOREREGVERIFY_PARAM_RL_EMAIL_DAY_WINDOW_HOURS_DESC'	=>	'Day email limiter window length in hours.',
	'CBBEFOREREGVERIFY_PARAM_RL_EMAIL_DAY_MAX_LABEL'			=>	'Email Day Window Max',
	'CBBEFOREREGVERIFY_PARAM_RL_EMAIL_DAY_MAX_DESC'			=>	'Maximum sends per email in the day window.',
	'CBBEFOREREGVERIFY_PARAM_RL_RESEND_ENABLED_LABEL'			=>	'Resend Cooldown Enabled',
	'CBBEFOREREGVERIFY_PARAM_RL_RESEND_ENABLED_DESC'			=>	'Enable or disable resend cooldown limiting.',
	'CBBEFOREREGVERIFY_PARAM_RL_RESEND_COOLDOWN_SEC_LABEL'		=>	'Resend Cooldown (seconds)',
	'CBBEFOREREGVERIFY_PARAM_RL_RESEND_COOLDOWN_SEC_DESC'		=>	'Minimum delay between resend requests for the same email.',
	'CBBEFOREREGVERIFY_PARAM_RL_ATTEMPTS_ENABLED_LABEL'		=>	'Attempts Limit Enabled',
	'CBBEFOREREGVERIFY_PARAM_RL_ATTEMPTS_ENABLED_DESC'			=>	'Enable or disable failed verification attempt limiting.',
	'CBBEFOREREGVERIFY_PARAM_RL_ATTEMPTS_MAX_LABEL'			=>	'Failed Attempts Max',
	'CBBEFOREREGVERIFY_PARAM_RL_ATTEMPTS_MAX_DESC'			=>	'Maximum failed code submissions per email in the active attempt window.',
);
