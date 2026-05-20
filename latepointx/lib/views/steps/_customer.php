<?php
/**
 * @var $current_step_code string
 * @var $booking OsBookingModel
 * @var $restrictions array
 * @var $presets array
 * @var $customer OsCustomerModel
 * @var $customer_contact_verified_via bool
 * @var $auth_action string
 * @var $selected_auth_method string
 * @var $enabled_contact_types_for_customer_auth array
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


?>
<div class="step-customer-w latepoint-step-content" data-step-code="<?php echo esc_attr( $current_step_code ); ?>" data-next-btn-label="<?php echo esc_attr( OsStepsHelper::get_next_btn_label_for_step( $current_step_code ) ); ?>">
	<?php
	do_action( 'latepoint_before_step_content', $current_step_code );
	echo OsStepsHelper::get_formatted_extra_step_content( $current_step_code, 'before' );
	echo '<div class="latepoint-customer-otp-input-container"></div>';
	?>
	<?php
	if ( OsAuthHelper::is_customer_auth_disabled() ) {
		// JUST THE FORM, NO LOGIN/REGISTER TABS
		include( 'partials/_contact_form.php' );
	} else {
		if ( OsAuthHelper::is_customer_logged_in() ) {
			// ALREADY LOGGED IN, SHOW DATA FORM
			?>
            <div class="hide-when-entering-otp">
                <div class="step-customer-logged-in-header-w">
                    <div><?php esc_html_e( 'Contact Information', 'latepoint' ); ?></div>
                    <span><?php esc_html_e( 'Not You?', 'latepoint' ); ?></span><a
                            data-btn-action="<?php echo esc_attr( OsRouterHelper::build_route_name( 'auth', 'logout_customer' ) ); ?>"
                            data-nonce="<?php echo esc_attr( wp_create_nonce( 'auth_nonce' ) ); ?>"
                            href="#"
                            class="step-customer-logout-btn"><?php esc_html_e( 'Logout', 'latepoint' ); ?></a>
                </div>
				<?php include( 'partials/_contact_form.php' ); ?>
            </div>
			<?php
		} else {
			// NOT LOGGED IN, SHOW AUTH OPTIONS
			if ( OsAuthHelper::is_classic_auth_flow() ) {
				// CLASSIC AUTH FLOW
				?>
                <div class="os-step-tabs-w latepoint-customer-auth-options-wrapper hide-when-entering-otp <?php echo $auth_action == 'otp_requested' ? 'os-hidden' : ''; ?>">
                    <div class="os-step-tabs">
                        <div class="os-step-tab active" data-auth-action="register" data-next-btn="show" data-target=".os-step-new-customer-w"><?php esc_html_e( 'New Customer', 'latepoint' ); ?></div>
                        <div class="os-step-tab" data-auth-action="login" data-target=".os-step-existing-customer-login-w"><?php esc_html_e( 'Already have an account?', 'latepoint' ); ?></div>
                    </div>
                    <div class="os-step-tab-content os-step-new-customer-w">
						<?php include( 'partials/_contact_form.php' ); ?>
                    </div>
                    <div class="os-step-tab-content os-step-existing-customer-login-w" style="display: none;">
						<?php echo OsAuthHelper::auth_form_html( true, $customer, $selected_auth_method ); ?>
                    </div>
                    <div class="os-password-reset-form-holder"></div>
                </div>
				<?php if ( apply_filters( 'latepoint_customer_login_show_other_options', false ) ) { ?>
                    <div class="os-social-or"><span><?php _e( 'OR', 'latepoint' ); ?></span></div>
				<?php } ?>
				<?php
                do_action( 'latepoint_after_customer_login_form' );
			} else {
                // MODERN AUTH FLOW
                if($customer_contact_verified_via){
				    include( 'partials/_contact_form.php' );
                }else{
                    echo OsAuthHelper::auth_form_html( false, $customer, $selected_auth_method );
                    if ( apply_filters( 'latepoint_customer_login_show_other_options', false ) || in_array( 'email', $enabled_contact_types_for_customer_auth ) || in_array( 'phone', $enabled_contact_types_for_customer_auth ) ) { ?>
                        <div class="os-social-or"><span><?php _e( 'OR', 'latepoint' ); ?></span></div>
                        <?php
                    }
                    if ( in_array( 'email', $enabled_contact_types_for_customer_auth ) ) { ?>
                        <div class="alternative-login-option <?php echo ( $selected_auth_method == 'email' ) ? 'os-hidden' : ''; ?>" data-auth-via="email" data-otp-delivery-method="email">
                            <i class="latepoint-icon latepoint-icon-mail"></i>
                            <span><?php _e( 'Continue with Email', 'latepoint' ); ?></span>
                        </div>
                        <?php
                    }
                    if ( in_array( 'phone', $enabled_contact_types_for_customer_auth ) ) { ?>
                        <div class="alternative-login-option <?php echo ( $selected_auth_method == 'phone' ) ? 'os-hidden' : ''; ?>" data-auth-via="phone" data-otp-delivery-method="sms">
                            <i class="latepoint-icon latepoint-icon-smartphone"></i>
                            <span><?php _e( 'Continue with Phone', 'latepoint' ); ?></span>
                        </div>
                        <?php
                    }
                    do_action( 'latepoint_after_customer_login_form' );
                }
			}
		}
	}
	echo OsStepsHelper::get_formatted_extra_step_content( $current_step_code, 'after' );
	do_action( 'latepoint_after_step_content', $current_step_code );
	?>
</div>