<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
?>
<h3 class="os-wizard-sub-header">
    <?php
    // translators: %1$d is current step, %2$d is total steps
    echo esc_html(sprintf(__('Step %1$d of %2$d', 'latepoint'), $current_step_number, 4)); ?>
</h3>
<h2 class="os-wizard-header"><?php esc_html_e( 'Okay, just one last step...', 'latepoint' ); ?></h2>
<div class="os-wizard-desc"><?php esc_html_e( 'Help us tailor your LatePoint experience by sharing a bit about yourself!', 'latepoint' ); ?></div>
<div class="os-wizard-step-content-i">
    <div class="os-form-w">
        <form action="" class="os-wizard-personal-info-form">
            <div class="os-row">
                <div class="os-col-6">
                    <?php echo OsFormHelper::text_field( 'personal_info[first_name]', __( 'First Name', 'latepoint' ), $wizard_first_name ); ?>
                </div>
                <div class="os-col-6">
                    <?php echo OsFormHelper::text_field( 'personal_info[last_name]', __( 'Last Name', 'latepoint' ), $wizard_last_name ); ?>
                </div>
            </div>
            <div class="os-row">
                <div class="os-col-12">
                    <?php echo OsFormHelper::text_field( 'personal_info[email]', __( 'Email Address', 'latepoint' ), $wizard_email ); ?>
                </div>
            </div>
            <div class="os-row">
                <div class="os-col-12">
                    <?php
						echo OsFormHelper::checkbox_field(
							'personal_info[email_optin]',
							// translators: %1$s and %2$s are opening and closing anchor tags for Privacy Policy link
							sprintf( __( 'Stay in the loop and help shape LatePoint! Get feature updates, and help us build a better LatePoint by sharing how you use the plugin. %1$sPrivacy Policy%2$s', 'latepoint' ), '<a href="https://latepoint.com/privacy-policy/" target="_blank">', '</a>' ),
							'on',
							$wizard_email_optin === 'on'
						);
					?>
                </div>
            </div>
        </form>
    </div>
</div>
