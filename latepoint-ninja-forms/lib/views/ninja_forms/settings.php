<?php
if ( ! defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly.
}

$page_options = [ '' => __( 'Select a page...', 'latepoint-ninja-forms' ) ];
foreach ( get_pages() as $page ) {
  $page_options[ $page->ID ] = $page->post_title;
}

$form_id           = OsNinjaFormsHelper::get_form_id();
$selected_page_id  = OsNinjaFormsHelper::get_form_page_id();
$admin_email       = OsSettingsHelper::get_settings_value( 'ninja_forms_admin_email', '' );
$notify_customer   = OsSettingsHelper::get_settings_value( 'ninja_forms_notify_customer', LATEPOINT_VALUE_ON ) == LATEPOINT_VALUE_ON;
$notify_admin      = OsSettingsHelper::get_settings_value( 'ninja_forms_notify_admin', LATEPOINT_VALUE_ON ) == LATEPOINT_VALUE_ON;
$auto_append_email = OsSettingsHelper::get_settings_value( 'ninja_forms_auto_append_email', LATEPOINT_VALUE_ON ) == LATEPOINT_VALUE_ON;
?>
<form action="" data-os-action="<?php echo esc_attr( OsRouterHelper::build_route_name( 'ninja_forms', 'save_settings' ) ); ?>">
    <div class="latepoint-settings-w os-form-w">
		<?php wp_nonce_field( 'ninja_forms_settings' ); ?>
        <div class="white-box">
            <div class="white-box-header">
                <div class="os-form-sub-header"><h3><?php esc_html_e( 'Ninja Forms Integration', 'latepoint-ninja-forms' ); ?></h3></div>
            </div>
            <div class="white-box-content">
                <div class="sub-section-row">
                    <div class="sub-section-label">
                        <h3><?php esc_html_e( 'Form', 'latepoint-ninja-forms' ); ?></h3>
                        <div class="sub-section-description"><?php esc_html_e( 'The Ninja Form id used for all services. Leave empty to disable.', 'latepoint-ninja-forms' ); ?></div>
                    </div>
                    <div class="sub-section-content">
                        <div class="os-row">
                            <div class="os-col-lg-8">
								<?php echo OsFormHelper::text_field( 'settings[ninja_forms_form_id]', __( 'Ninja Form ID', 'latepoint-ninja-forms' ), $form_id ? (string) $form_id : '', [ 'theme' => 'bordered', 'placeholder' => __( 'e.g. 3', 'latepoint-ninja-forms' ) ] ); ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="sub-section-row">
                    <div class="sub-section-label">
                        <h3><?php esc_html_e( 'Form Page', 'latepoint-ninja-forms' ); ?></h3>
                        <div class="sub-section-description"><?php esc_html_e( 'The page that contains the [latepoint_ninja_form] shortcode. The booking form link points here.', 'latepoint-ninja-forms' ); ?></div>
                    </div>
                    <div class="sub-section-content">
                        <div class="os-row">
                            <div class="os-col-lg-8">
								<?php echo OsFormHelper::select_field( 'settings[ninja_forms_page_id]', __( 'Form Page', 'latepoint-ninja-forms' ), $page_options, $selected_page_id ); ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="sub-section-row">
                    <div class="sub-section-label">
                        <h3><?php esc_html_e( 'Notifications', 'latepoint-ninja-forms' ); ?></h3>
                        <div class="sub-section-description"><?php esc_html_e( 'Who gets notified when a customer submits the form.', 'latepoint-ninja-forms' ); ?></div>
                    </div>
                    <div class="sub-section-content">
                        <div class="os-row">
                            <div class="os-col-lg-8">
								<?php echo OsFormHelper::text_field( 'settings[ninja_forms_admin_email]', __( 'Admin notification email', 'latepoint-ninja-forms' ), $admin_email, [ 'theme' => 'bordered', 'placeholder' => get_bloginfo( 'admin_email' ) ] ); ?>
                            </div>
                        </div>
                        <div class="os-row os-mt-3">
                            <div class="os-col-lg-6">
								<?php echo OsFormHelper::toggler_field( 'settings[ninja_forms_notify_customer]', __( 'Notify customer', 'latepoint-ninja-forms' ), $notify_customer ); ?>
                            </div>
                            <div class="os-col-lg-6">
								<?php echo OsFormHelper::toggler_field( 'settings[ninja_forms_notify_admin]', __( 'Notify admin', 'latepoint-ninja-forms' ), $notify_admin ); ?>
                            </div>
                        </div>
                        <div class="os-row os-mt-3">
                            <div class="os-col-lg-12">
								<?php echo OsFormHelper::toggler_field( 'settings[ninja_forms_auto_append_email]', __( 'Auto-append form link to booking emails', 'latepoint-ninja-forms' ), $auto_append_email, false, false, [ 'sub_label' => __( 'Adds the link to the customer booking email automatically when a form is set. Disable if you place {{order_ninja_form_link}} in your template manually.', 'latepoint-ninja-forms' ) ] ); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="os-form-buttons">
			<?php echo OsFormHelper::button( 'submit', __( 'Save Settings', 'latepoint-ninja-forms' ), 'submit', [ 'class' => 'latepoint-btn' ] ); ?>
        </div>
    </div>
</form>
<div class="white-box os-mt-3">
    <div class="white-box-header">
        <div class="os-form-sub-header"><h3><?php esc_html_e( 'Setup', 'latepoint-ninja-forms' ); ?></h3></div>
    </div>
    <div class="white-box-content">
        <ol class="latepoint-ninja-forms-setup-steps">
            <li><?php esc_html_e( 'Create a Ninja Form. Add two Hidden fields with the field keys "lp_nf_order" and "lp_nf_token" — this addon fills them automatically.', 'latepoint-ninja-forms' ); ?></li>
            <li><?php esc_html_e( 'Enter that form\'s ID above. The same form is used for all services.', 'latepoint-ninja-forms' ); ?></li>
            <li><?php esc_html_e( 'Create a WordPress page containing the shortcode [latepoint_ninja_form] and select it above.', 'latepoint-ninja-forms' ); ?></li>
            <li><?php esc_html_e( 'The link is emailed automatically (Auto-append above). To place it yourself instead, turn that off and insert {{order_ninja_form_link}} into your "Booking Created → Customer" email template.', 'latepoint-ninja-forms' ); ?></li>
        </ol>
    </div>
</div>
