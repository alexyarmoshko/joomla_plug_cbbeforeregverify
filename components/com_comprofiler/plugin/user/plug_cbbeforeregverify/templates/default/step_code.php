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
$codeLength	=	isset( $codeLength ) ? (int) $codeLength : CBBeforeRegVerify::getCodeLength();
?>
<div class="cbBeforeRegVerifyStep cbBeforeRegVerifyStepCode">
	<h3><?php echo htmlspecialchars( CBTxt::T( 'CBBEFOREREGVERIFY_STEP_CODE_TITLE', 'Enter your verification code' ) ); ?></h3>
	<p>
		<?php echo htmlspecialchars( CBTxt::T( 'CBBEFOREREGVERIFY_STEP_CODE_BODY', 'We sent a verification code to [email]. Enter it below to continue.', [ '[email]' => $flowEmail ] ) ); ?>
	</p>
	<form action="<?php echo htmlspecialchars( $_CB_framework->pluginClassUrl( 'cbbeforeregverify', true, [ 'func' => 'submit_code' ] ) ); ?>" method="post" class="cb_form cbBeforeRegVerifyForm cbBeforeRegVerifyFormCode">
		<div class="form-group cbft_text cbtt_text cb_form_line">
			<label for="cbbeforeregverify_code"><?php echo htmlspecialchars( CBTxt::T( 'CBBEFOREREGVERIFY_CODE_LABEL', 'Verification Code' ) ); ?></label>
			<input type="text" id="cbbeforeregverify_code" name="verification_code" value="" class="form-control" inputmode="numeric" pattern="[0-9]*" maxlength="<?php echo (int) $codeLength; ?>" required="required" autocomplete="one-time-code" />
		</div>
		<div class="form-group cb_form_line">
			<input type="submit" value="<?php echo htmlspecialchars( CBTxt::T( 'CBBEFOREREGVERIFY_SUBMIT_CODE', 'Verify Code' ) ); ?>" class="btn btn-primary" />
		</div>
		<?php echo Application::Session()->getFormTokenInput(); ?>
	</form>
	<form action="<?php echo htmlspecialchars( $_CB_framework->pluginClassUrl( 'cbbeforeregverify', true, [ 'func' => 'resend' ] ) ); ?>" method="post" class="cb_form cbBeforeRegVerifyForm cbBeforeRegVerifyFormResend">
		<div class="form-group cb_form_line">
			<input type="submit" value="<?php echo htmlspecialchars( CBTxt::T( 'CBBEFOREREGVERIFY_RESEND_CODE', 'Resend Code' ) ); ?>" class="btn btn-secondary" />
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
