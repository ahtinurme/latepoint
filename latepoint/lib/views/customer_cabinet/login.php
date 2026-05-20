<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
?>
<?php
$is_auth_enabled = OsAuthHelper::is_customer_auth_enabled();
if($is_auth_enabled){
    $attrs = 'data-redirect-url="'.esc_attr(OsSettingsHelper::get_customer_dashboard_url()).'" data-success-action="'.(OsAuthHelper::is_customer_logged_in() ? 'auto-redirect' : 'redirect').'"';
}else{
    $attrs = '';
}
?>
<div class="latepoint-w">
	<div class="os-form-w latepoint-login-form-w" <?php echo $attrs; ?>>
        <?php
        if($is_auth_enabled){
            if(OsAuthHelper::is_customer_logged_in()){
                echo '<div class="latepoint-customer-dashboard-redirecting">'.__('Redirecting you to your dashboard', 'latepoint').'</div>';
            }else{
             ?>
            <form action="" class="latepoint-form">
                <div class="latepoint-customer-otp-input-container"></div>
                <div class="latepoint-customer-auth-options-wrapper hide-when-entering-otp">
                    <div class="latepoint-customer-box-title"><?php _e('Sign in to your account', 'latepoint'); ?></div>
                    <?php
                    echo '<div class="os-step-existing-customer-login-w">';
                        echo OsAuthHelper::auth_form_html( true, new OsCustomerModel() );
                    echo '</div>';
                    echo '<div class="os-password-reset-form-holder"></div>';
                    if ( apply_filters( 'latepoint_customer_login_show_other_options', false ) ) { ?>
                        <div class="os-social-or"><span><?php _e( 'OR', 'latepoint' ); ?></span></div>
                        <?php
                    }
                    ?>
                    <?php do_action('latepoint_after_customer_login_form'); ?>
                </div>
            </form>
            <?php
            }
        }else{
            echo '<div>'.__('Customer authentication is disabled', 'latepoint').'</div>';
        }?>
	</div>
</div>