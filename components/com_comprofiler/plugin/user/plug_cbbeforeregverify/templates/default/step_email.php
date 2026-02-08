<?php
/**
 * CBBeforeRegVerify Plugin
 * @version $Id: $
 * @package CBBeforeRegVerify
 * @copyright (C) 2026 Yak Shaver https://www.kayakshaver.com/
 * @license http://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU/GPL version 2
 */

defined( 'CBLIB' ) or die();

use CB\Plugin\BeforeRegVerify\CBBeforeRegVerify;
use CBLib\Application\Application;
use CBLib\Language\CBTxt;

global $_CB_framework;

$flowEmail	=	isset( $flowEmail ) ? (string) $flowEmail : CBBeforeRegVerify::getFlowEmail();
?>
<div class="cbBeforeRegVerifyStep cbBeforeRegVerifyStepEmail">
	<h3><?php echo htmlspecialchars( CBTxt::T( 'CBBEFOREREGVERIFY_STEP_EMAIL_TITLE', 'Verify your email to continue' ) ); ?></h3>
	<p><?php echo htmlspecialchars( CBTxt::T( 'CBBEFOREREGVERIFY_STEP_EMAIL_BODY', 'Enter your email address and we will send a verification code.' ) ); ?></p>
	<form action="<?php echo htmlspecialchars( $_CB_framework->pluginClassUrl( 'cbbeforeregverify', true, [ 'func' => 'submit_email' ] ) ); ?>" method="post" class="cb_form cbBeforeRegVerifyForm">
		<div class="form-group cbft_text cbtt_text cb_form_line">
			<label for="cbbeforeregverify_email"><?php echo htmlspecialchars( CBTxt::T( 'CBBEFOREREGVERIFY_EMAIL_LABEL', 'Email Address' ) ); ?></label>
			<input type="email" id="cbbeforeregverify_email" name="email" value="<?php echo htmlspecialchars( $flowEmail ); ?>" class="form-control" required="required" autocomplete="email" />
		</div>
		<div class="form-group cb_form_line">
			<input type="submit" value="<?php echo htmlspecialchars( CBTxt::T( 'CBBEFOREREGVERIFY_SEND_CODE', 'Send Verification Code' ) ); ?>" class="btn btn-primary" />
		</div>
		<?php echo Application::Session()->getFormTokenInput(); ?>
	</form>
	<form action="<?php echo htmlspecialchars( $_CB_framework->pluginClassUrl( 'cbbeforeregverify', true, [ 'func' => 'cancel' ] ) ); ?>" method="post" class="cb_form cbBeforeRegVerifyForm cbBeforeRegVerifyFormCancel">
		<div class="form-group cb_form_line">
			<input type="submit" value="<?php echo htmlspecialchars( CBTxt::T( 'CBBEFOREREGVERIFY_CANCEL', 'Cancel' ) ); ?>" class="btn btn-link" />
		</div>
		<?php echo Application::Session()->getFormTokenInput(); ?>
	</form>
</div>
