<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
?>
<form action="" data-os-action="<?php echo esc_attr(OsRouterHelper::build_route_name( 'settings', 'update' )); ?>">
<div class="latepoint-page-with-side-nav">
<div class="latepoint-settings-w os-form-w">
		<?php wp_nonce_field( 'update_settings' ); ?>
        <div class="white-box section-anchor" id="stickySectionAppointment">
            <div class="white-box-header">
                <div class="os-form-sub-header"><h3><?php esc_html_e( 'Appointments', 'latepoint' ); ?></h3></div>
            </div>
            <div class="white-box-content no-padding">
                <div class="sub-section-row">
                    <div class="sub-section-label">
                        <h3><?php esc_html_e( 'Statuses', 'latepoint' ) ?></h3>
                    </div>
                    <div class="sub-section-content">
                        <div class="os-row os-mb-3">
                            <div class="os-col-lg-6">
								<?php echo OsFormHelper::select_field( 'settings[default_booking_status]', __( 'Default status', 'latepoint' ), OsBookingHelper::get_statuses_list(), OsBookingHelper::get_default_booking_status() ); ?>
                            </div>
                            <div class="os-col-lg-6">
								<?php echo OsFormHelper::multi_select_field( 'settings[timeslot_blocking_statuses]', __( 'Statuses that block timeslot', 'latepoint' ), OsBookingHelper::get_statuses_list(), OsBookingHelper::get_timeslot_blocking_statuses() ); ?>
                            </div>
                        </div>
                        <div class="os-row os-mb-3">
                            <div class="os-col-lg-6">
								<?php echo OsFormHelper::multi_select_field( 'settings[need_action_statuses]', __( 'Statuses that appear on pending page', 'latepoint' ), OsBookingHelper::get_statuses_list(), OsBookingHelper::get_booking_statuses_for_pending_page() ); ?>
                            </div>
                            <div class="os-col-lg-6">
								<?php echo OsFormHelper::multi_select_field( 'settings[calendar_hidden_statuses]', __( 'Statuses hidden on calendar', 'latepoint' ), OsBookingHelper::get_statuses_list(), OsCalendarHelper::get_booking_statuses_hidden_from_calendar() ); ?>
                            </div>
                        </div>
                        <div class="os-row">
                            <div class="os-col-12">
								<?php echo OsFormHelper::text_field( 'settings[additional_booking_statuses]', __( 'Additional Statuses (comma separated)', 'latepoint' ), OsSettingsHelper::get_settings_value( 'additional_booking_statuses' ), [ 'theme' => 'simple' ] ); ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="sub-section-row">
                    <div class="sub-section-label">
                        <h3><?php esc_html_e( 'Date and time', 'latepoint' ) ?></h3>
                    </div>
                    <div class="sub-section-content">
                        <div class="os-row os-mb-3">
                            <div class="os-col-6">
								<?php echo OsFormHelper::select_field( 'settings[time_system]', __( 'Time system', 'latepoint' ), OsTimeHelper::get_time_systems_list_for_select(), OsTimeHelper::get_time_system() ); ?>
                            </div>
                            <div class="os-col-6">
								<?php echo OsFormHelper::select_field( 'settings[date_format]', __( 'Date format', 'latepoint' ), OsTimeHelper::get_date_formats_list_for_select(), OsSettingsHelper::get_date_format() ); ?>
                            </div>
                        </div>
						<?php echo OsFormHelper::text_field( 'settings[timeblock_interval]', __( 'Selectable intervals', 'latepoint' ), OsSettingsHelper::get_default_timeblock_interval(), [
							'class' => 'os-mask-minutes',
							'theme' => 'simple'
						] ); ?>
                        <div class="os-row os-mb-3">
                            <div class="os-col-lg-6">
								<?php echo OsFormHelper::toggler_field( 'settings[show_booking_end_time]', __( 'Show appointment end time', 'latepoint' ), OsSettingsHelper::is_on( 'show_booking_end_time' ), false, false, [ 'sub_label' => __( 'Show booking end time during booking process and on summary', 'latepoint' ) ] ); ?>
                            </div>
                            <div class="os-col-lg-6">
								<?php echo OsFormHelper::toggler_field( 'settings[disable_verbose_date_output]', __( 'Disable verbose date output', 'latepoint' ), OsSettingsHelper::is_on( 'disable_verbose_date_output' ), false, false, [ 'sub_label' => __( 'Use number instead of name of the month when outputting dates', 'latepoint' ) ] ); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="white-box section-anchor" id="stickySectionRestrictions">
            <div class="white-box-header">
                <div class="os-form-sub-header"><h3><?php esc_html_e( 'Restrictions', 'latepoint' ); ?></h3></div>
            </div>
            <div class="white-box-content no-padding">

                <div class="sub-section-row">
                    <div class="sub-section-label">
                        <h3><?php esc_html_e( 'Time Restrictions', 'latepoint' ) ?></h3>
                    </div>
                    <div class="sub-section-content">
                        <div class="latepoint-message latepoint-message-subtle"><?php esc_html_e( 'You can set restrictions on earliest/latest dates in the future when your customer can place an appointment. You can either use a relative values like for example "+1 month", "+2 weeks", "+5 days", "+3 hours", "+30 minutes" (entered without quotes), or you can use a fixed date in format YYYY-MM-DD. Leave blank to remove any limitations.', 'latepoint' ); ?></div>
                        <div class="os-row">
                            <div class="os-col-lg-6">
								<?php echo OsFormHelper::text_field( 'settings[earliest_possible_booking]', __( 'Earliest Possible Booking', 'latepoint' ), OsSettingsHelper::get_settings_value( 'earliest_possible_booking' ), [ 'theme' => 'simple' ] ); ?>
                            </div>
                            <div class="os-col-lg-6">
								<?php echo OsFormHelper::text_field( 'settings[latest_possible_booking]', __( 'Latest Possible Booking', 'latepoint' ), OsSettingsHelper::get_settings_value( 'latest_possible_booking' ), [ 'theme' => 'simple' ] ); ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="sub-section-row">
                    <div class="sub-section-label">
                        <h3><?php esc_html_e( 'Quantity Restrictions', 'latepoint' ) ?></h3>
                    </div>
                    <div class="sub-section-content">
						<?php echo OsFormHelper::text_field( 'settings[max_future_bookings_per_customer]', __( 'Maximum Number of Future Bookings per Customer', 'latepoint' ), OsSettingsHelper::get_settings_value( 'max_future_bookings_per_customer' ), [ 'theme' => 'simple' ] ); ?>
                    </div>
                </div>
				<?php
				/**
				 * Plug after general settings section called restrictions
				 *
				 * @since 5.0.0
				 * @hook latepoint_general_settings_section_restrictions_after
				 *
				 */
				do_action( 'latepoint_general_settings_section_restrictions_after' ); ?>
            </div>
        </div>
        <div class="white-box section-anchor" id="stickySectionCurrency">
            <div class="white-box-header">
                <div class="os-form-sub-header"><h3><?php esc_html_e( 'Currency & Price', 'latepoint' ); ?></h3></div>
            </div>
            <div class="white-box-content no-padding">
                <div class="sub-section-row">
                    <div class="sub-section-label">
                        <h3><?php esc_html_e( 'Symbol', 'latepoint' ) ?></h3>
                    </div>
                    <div class="sub-section-content">
                        <div class="os-row">
                            <div class="os-col-lg-4">
								<?php echo OsFormHelper::text_field( 'settings[currency_symbol_before]', __( 'Symbol before the price', 'latepoint' ), OsSettingsHelper::get_settings_value( 'currency_symbol_before', '$' ), [ 'theme' => 'simple' ] ); ?>
                            </div>
                            <div class="os-col-lg-4">
								<?php echo OsFormHelper::text_field( 'settings[currency_symbol_after]', __( 'Symbol after the price', 'latepoint' ), OsSettingsHelper::get_settings_value( 'currency_symbol_after' ), [ 'theme' => 'simple' ] ); ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="sub-section-row">
                    <div class="sub-section-label">
                        <h3><?php esc_html_e( 'Formatting', 'latepoint' ) ?></h3>
                    </div>
                    <div class="sub-section-content">
                        <div class="os-row">
                            <div class="os-col-lg-4">
								<?php echo OsFormHelper::select_field( 'settings[thousand_separator]', __( 'Thousand Separator', 'latepoint' ), [
									',' => __( 'Comma', 'latepoint' ) . ' (1,000)',
									'.' => __( 'Dot', 'latepoint' ) . ' (1.000)',
									' ' => __( 'Space', 'latepoint' ) . ' (1 000)',
									''  => __( 'None', 'latepoint' ) . ' (1000)'
								], OsSettingsHelper::get_settings_value( 'thousand_separator', ',' ) ); ?>
                            </div>
                            <div class="os-col-lg-4">
								<?php echo OsFormHelper::select_field( 'settings[decimal_separator]', __( 'Decimal Separator', 'latepoint' ), [
									'.' => __( 'Dot', 'latepoint' ) . ' (0.99)',
									',' => __( 'Comma', 'latepoint' ) . ' (0,99)'
								], OsSettingsHelper::get_settings_value( 'decimal_separator', '.' ) ); ?>
                            </div>
                            <div class="os-col-lg-4">
								<?php echo OsFormHelper::select_field( 'settings[number_of_decimals]', __( 'Number of Decimals', 'latepoint' ), [ 0, 1, 2, 3, 4 ], OsSettingsHelper::get_settings_value( 'number_of_decimals', '2' ) ); ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="sub-section-row">
                    <div class="sub-section-label">
                        <h3><?php esc_html_e( 'Prices', 'latepoint' ) ?></h3>
                    </div>
                    <div class="sub-section-content">
                        <?php echo OsFormHelper::toggler_field( 'settings[hide_breakdown_if_subtotal_zero]', __( 'Do not show price breakdown, if service price is zero', 'latepoint' ), OsSettingsHelper::is_on( 'hide_breakdown_if_subtotal_zero' ) ); ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="white-box section-anchor" id="stickySectionPhone">
            <div class="white-box-header">
                <div class="os-form-sub-header"><h3><?php esc_html_e( 'Phone', 'latepoint' ); ?></h3></div>
            </div>
            <div class="white-box-content no-padding">
                <div class="sub-section-row phone-country-picker-settings">
                    <div class="sub-section-label">
                        <h3><?php esc_html_e( 'Countries', 'latepoint' ) ?></h3>
                    </div>
                    <div class="sub-section-content">
                        <div class="phone-country-picker-settings">
                            <div class="os-row os-mb-2">
                                <div class="os-col-lg-4">
									<?php echo OsFormHelper::select_field( 'settings[list_of_phone_countries]', __( 'Countries shown in phone field', 'latepoint' ), [
										LATEPOINT_ALL => __( 'Show all countries', 'latepoint' ),
										'select'      => __( 'Show selected countries', 'latepoint' )
									], OsSettingsHelper::get_settings_value( 'list_of_phone_countries', LATEPOINT_ALL ) ); ?>
                                </div>
                                <div class="os-col-lg-8">
									<?php echo OsFormHelper::select_field( 'settings[default_phone_country]', __( 'Default Country (if not auto-detected)', 'latepoint' ), OsUtilHelper::get_countries_list(), OsSettingsHelper::get_default_phone_country() ); ?>
                                </div>
                            </div>
                            <div class="os-row">
                                <div class="os-col-12 select-phone-countries-wrapper"
                                     style="<?php echo ( OsSettingsHelper::get_settings_value( 'list_of_phone_countries', LATEPOINT_ALL ) == LATEPOINT_ALL ) ? 'display: none;' : ''; ?>">
									<?php echo OsFormHelper::multi_select_field( 'settings[included_phone_countries]', __( 'Select countries available for phone number field', 'latepoint' ), OsUtilHelper::get_countries_list(), OsSettingsHelper::get_included_phone_countries() ); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="sub-section-row phone-country-picker-settings">
                    <div class="sub-section-label">
                        <h3><?php esc_html_e( 'Validation', 'latepoint' ) ?></h3>
                    </div>
                    <div class="sub-section-content">
						<?php echo OsFormHelper::toggler_field( 'settings[validate_phone_number]', __( 'Validate phone typed fields if they are set as required', 'latepoint' ), OsSettingsHelper::is_on( 'validate_phone_number' ), false, false, [ 'sub_label' => __( 'Reject invalid phone for customers and agents if the phone field is set as required', 'latepoint' ) ] ); ?>
						<?php echo OsFormHelper::toggler_field( 'settings[mask_phone_number_fields]', __( 'Format phone number on input', 'latepoint' ), OsSettingsHelper::is_on( 'mask_phone_number_fields', LATEPOINT_VALUE_ON ), false, false, [ 'sub_label' => __( 'Applies formatting on phone fields based on the country selected (not recommended for countries that have multiple NSN lengths)', 'latepoint' ) ] ); ?>
						<?php echo OsFormHelper::toggler_field( 'settings[show_dial_code_with_flag]', __( 'Show country dial code next to flag', 'latepoint' ), OsSettingsHelper::is_enabled_show_dial_code_with_flag(), false, false, [ 'sub_label' => __( 'If enabled, will show a country code next to a flag, for example +1 for United States', 'latepoint' ) ] ); ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="white-box section-anchor" id="stickySectionAvailability">
            <div class="white-box-header">
                <div class="os-form-sub-header"><h3><?php esc_html_e( 'Availability Logic', 'latepoint' ); ?></h3>
                </div>
            </div>
            <div class="white-box-content no-padding">
                <div class="sub-section-row">
                    <div class="sub-section-label">
                        <h3><?php esc_html_e( 'Restrictions', 'latepoint' ) ?></h3>
                    </div>
                    <div class="sub-section-content">
						<?php echo OsFormHelper::toggler_field( 'settings[one_agent_at_location]', __( 'Location can only be used by one agent at a time', 'latepoint' ), OsSettingsHelper::is_on( 'one_agent_at_location' ), '', 'large', [ 'sub_label' => __( 'At any given location, only one agent can be booked at a time', 'latepoint' ) ] ); ?>
						<?php echo OsFormHelper::toggler_field( 'settings[one_location_at_time]', __( 'Agents can only be present in one location at a time', 'latepoint' ), OsSettingsHelper::is_on( 'one_location_at_time' ), '', 'large', [ 'sub_label' => __( 'If an agent is booked at one location, he will not be able to accept any bookings for the same timeslot at other locations', 'latepoint' ) ] ); ?>
                    </div>
                </div>
                <div class="sub-section-row">
                    <div class="sub-section-label">
                        <h3><?php esc_html_e( 'Permissions', 'latepoint' ) ?></h3>
                    </div>
                    <div class="sub-section-content">
						<?php echo OsFormHelper::toggler_field( 'settings[multiple_services_at_time]', __( 'One agent can perform different services simultaneously', 'latepoint' ), OsSettingsHelper::is_on( 'multiple_services_at_time' ), '', 'large', [ 'sub_label' => __( 'Allows an agent to be booked for different services within the same timeslot', 'latepoint' ) ] ); ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="white-box section-anchor" id="stickySectionCustomer">
            <div class="white-box-header">
                <div class="os-form-sub-header"><h3><?php esc_html_e( 'Customers', 'latepoint' ); ?></h3></div>
            </div>
            <div class="white-box-content no-padding">
                <div class="sub-section-row">
                    <div class="sub-section-label">
                        <h3><?php esc_html_e( 'Authentication', 'latepoint' ) ?></h3>
                    </div>
                    <div class="sub-section-content">
                        <div class="latepoint-message latepoint-message-subtle">
                            <?php _e('Make sure to install an SMS processor if you choose to text one-time codes (OTP) to customer phone numbers for authentication or verification', 'latepoint'); ?>
                        </div>
                        <div class="os-row os-mb-2">
                            <div class="os-col-lg-6">
                                <?php echo OsFormHelper::select_field('settings[selected_customer_authentication_field_type]', __('Field used for authentication', 'latepoint'), OsAuthHelper::get_customer_authentication_field_type_options(), OsAuthHelper::get_selected_customer_authentication_field_type(), ['data-os-on-change' => 'latepoint_settings_customer_authentication_field_type_changed']); ?>
                            </div>
                            <div class="os-col-lg-6" id="authDefaultContactType" <?php if(OsAuthHelper::get_selected_customer_authentication_field_type() != 'email_or_phone'){ ?>style="display: none;"<?php } ?>>
                                <div>
                                    <?php echo OsFormHelper::select_field('settings[default_contact_type_for_customer_auth]', __('Default to', 'latepoint'), OsAuthHelper::get_available_contact_types_for_customer_auth(), OsAuthHelper::get_default_contact_type_for_customer_auth()); ?>
                                </div>
                            </div>
                            <div class="os-col-lg-6" id="authDefaultMergeBehavior" <?php if(OsAuthHelper::get_selected_customer_authentication_field_type() != 'disabled'){ ?>style="display: none;"<?php } ?>>
                                <div>
                                    <?php echo OsFormHelper::select_field('settings[default_contact_merge_behavior]', __('Reuse existing customer if', 'latepoint'), ['email' => __('Email matches', 'latepoint'), 'phone' => __('Phone matches', 'latepoint'), 'none' => __('Do not reuse, always create new customer', 'latepoint')], OsSettingsHelper::get_settings_value('default_contact_merge_behavior', 'email')); ?>
                                </div>
                            </div>
                        </div>
                        <div id="passwordFields" <?php if(OsAuthHelper::is_customer_auth_disabled()){ ?>style="display: none;"<?php } ?>>
                            <div class="os-row os-mb-2">
                                <div class="os-col-lg-6">
                                    <?php echo OsFormHelper::select_field('settings[selected_customer_authentication_method]', __('Authentication method', 'latepoint'), OsAuthHelper::get_customer_authentication_method_options(), OsAuthHelper::get_selected_customer_authentication_method(), ['data-os-on-change' => 'latepoint_settings_customer_authentication_method_changed']); ?>
                                </div>
                                <div id="authDefaultMethod" class="os-col-lg-6" <?php if(OsAuthHelper::get_selected_customer_authentication_method() != 'password_or_otp'){ ?>style="display: none;"<?php } ?>>
                                    <div>
                                        <?php echo OsFormHelper::select_field('settings[default_customer_authentication_method]', __('Default to', 'latepoint'), OsAuthHelper::get_available_customer_authentication_methods(), OsAuthHelper::get_default_customer_authentication_method()); ?>
                                    </div>
                                </div>
                            </div>
                            <?php echo OsFormHelper::toggler_field( 'settings[require_otp_for_new_contacts]', __( 'Require OTP verification for new contacts', 'latepoint' ), OsSettingsHelper::is_on( 'require_otp_for_new_contacts' ), false, false, [ 'sub_label' => __( 'Require customers to verify their primary contact (email or phone) when they change it or add a new one, a 6 digit one-time code will be sent to their email or phone', 'latepoint' ) ] ); ?>
                        </div>
                    </div>
                </div>
                <div class="sub-section-row" id="customerStepSettings" <?php if(OsAuthHelper::is_customer_auth_disabled()){ ?>style="display: none;"<?php } ?>>
                    <div class="sub-section-label">
                        <h3><?php esc_html_e( 'Customer Step', 'latepoint' ) ?></h3>
                    </div>
                    <div class="sub-section-content">
                            <?php // echo OsFormHelper::toggler_field( 'settings[modern_auth_flow_for_customers]', __( 'Simplified authentication flow', 'latepoint' ), !OsAuthHelper::is_classic_auth_flow(), false, false, [ 'sub_label' => __( 'Instead of having customer to pick if they want to create a new account or login, ask them their phone/email and then present a form to register if email/phone not found, or show OTP/password form to login', 'latepoint' ) ] ); ?>
                            <?php echo OsFormHelper::toggler_field( 'settings[steps_require_setting_password]', __( 'Require customers to set password', 'latepoint' ), OsSettingsHelper::is_on( 'steps_require_setting_password' ), '-registrationPrompt', false, [ 'sub_label' => __( 'Shows password field on registration step, customer will be required to set a password in order to create an account', 'latepoint' ) ] ); ?>
                            <div id="registrationPrompt" <?php if(OsSettingsHelper::is_on( 'steps_require_setting_password' )){ ?>style="display: none;"<?php } ?>>
                                <?php echo OsFormHelper::toggler_field( 'settings[steps_hide_registration_prompt]', __( 'Do not show "Create Account" prompt on confirmation step', 'latepoint' ), OsSettingsHelper::is_on( 'steps_hide_registration_prompt' ), false, false, [ 'sub_label' => __( 'If a customer has not set password for their account, they will be presented with a prompt to do it after a booking is placed.', 'latepoint' ) ] ); ?>
                            </div>
                    </div>
                </div>
                <div class="sub-section-row">
                    <div class="sub-section-label">
                        <h3><?php esc_html_e( 'WordPress', 'latepoint' ) ?></h3>
                    </div>
                    <div class="sub-section-content">
                        <?php echo OsFormHelper::toggler_field( 'settings[wp_users_as_customers]', __( 'Use WordPress users as customers', 'latepoint' ), OsSettingsHelper::is_on( 'wp_users_as_customers' ), 'defaultWPUserRole', false, [ 'sub_label' => __( 'Customers can login using their WordPress credentials (if authentication is enabled above), a linked customer account is created automatically. If a WordPress user is logged in - a customer with the same email will be created automatically and data will be prefilled. If a new customer provided an email address, a linked WordPress user is automatically created for that customer, if not present already.', 'latepoint' ) ] ); ?>
                        <div id="defaultWPUserRole" class="os-mt-1" <?php if(!OsAuthHelper::can_wp_users_login_as_customers()){ ?>style="display: none;"<?php } ?>>
                        <?php echo OsFormHelper::select_field( 'settings[default_wp_role_for_customer]', __('Default role for a created WP user', 'latepoint'), OsRolesHelper::get_wp_roles_list(), OsSettingsHelper::get_default_wp_role_for_new_customers() ); ?>
                        <?php
                        $default_fields = OsSettingsHelper::get_default_fields_for_customer();
                        if(empty($default_fields['email']) || !$default_fields['email']['required'] || !$default_fields['email']['active']){
                            echo '<div class="latepoint-message latepoint-message-invalid">'.esc_html__('Important: WordPress users are required to have an email address. You have to set email address field as required in order to create a matching WP user for new customers, otherwise customers without email address on file will not be able to login and make bookings', 'latepoint').'</div>';
                        }
                        ?>
                        </div>

                    </div>
                </div>
                <div class="sub-section-row">
                    <div class="sub-section-label">
                        <h3><?php esc_html_e( 'Rescheduling', 'latepoint' ) ?></h3>
                    </div>
                    <div class="sub-section-content">
                        <?php echo apply_filters('latepoint_customer_reschedule_settings', '<div>'.OsUtilHelper::generate_missing_addon_link(__('Upgrade to the Premium version to let customers reschedule appointments', 'latepoint')).'</div>'); ?>
                    </div>
                </div>
                <div class="sub-section-row">
                    <div class="sub-section-label">
                        <h3><?php esc_html_e( 'Cancellation', 'latepoint' ) ?></h3>
                    </div>
                    <div class="sub-section-content">
						<?php echo OsFormHelper::toggler_field( 'settings[allow_customer_booking_cancellation]', __( 'Allow customers cancel their bookings', 'latepoint' ), OsSettingsHelper::is_on( 'allow_customer_booking_cancellation' ), 'cancellation_settings', 'normal', [ 'sub_label' => __( 'If enable, shows a button on customer cabinet to cancel an appointment', 'latepoint' ) ] ); ?>
                        <div class="os-mb-2"
                             id="cancellation_settings" <?php echo OsSettingsHelper::is_on( 'allow_customer_booking_cancellation' ) ? '' : 'style="display:none"' ?>>
							<?php echo OsFormHelper::toggler_field( 'settings[limit_when_customer_can_cancel]', __( 'Set restriction on when customer can cancel', 'latepoint' ), OsSettingsHelper::is_on( 'limit_when_customer_can_cancel' ), 'cancellation_limit_settings' ); ?>
                            <div class="os-mb-4"
                                 id="cancellation_limit_settings" <?php echo OsSettingsHelper::is_on( 'limit_when_customer_can_cancel' ) ? '' : 'style="display:none"' ?>>
                                <div class="merged-fields os-mt-1">
                                    <div class="merged-label"><?php esc_html_e( 'Can cancel when it is at least', 'latepoint' ); ?></div>
									<?php echo OsFormHelper::text_field( 'settings[cancellation_limit_value]', false, OsSettingsHelper::get_settings_value( 'cancellation_limit_value', 5 ), [ 'placeholder' => __( 'Value', 'latepoint' ) ] ); ?>
									<?php echo OsFormHelper::select_field( 'settings[cancellation_limit_unit]', false,
										array(
											'minute' => __( 'minutes', 'latepoint' ),
											'hour'   => __( 'hours', 'latepoint' ),
											'day'    => __( 'days', 'latepoint' )
										),
										OsSettingsHelper::get_settings_value( 'cancellation_limit_unit', 'hour' ) ); ?>
                                    <div class="merged-label"><?php esc_html_e( 'before appointment start time', 'latepoint' ); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="sub-section-row">
                    <div class="sub-section-label">
                        <h3><?php esc_html_e( 'Customer Cabinet', 'latepoint' ) ?></h3>
                    </div>
                    <div class="sub-section-content">
                        <?php echo OsFormHelper::text_field( 'settings[customer_dashboard_book_shortcode]', __( 'Shortcode for contents of New Appointment tab', 'latepoint' ), OsSettingsHelper::get_settings_value( 'customer_dashboard_book_shortcode', '[latepoint_book_form]' ), [ 'theme' => 'simple' ] ); ?>
                        <div class="os-mt-2">
                            <div class="latepoint-message latepoint-message-subtle"><?php esc_html_e( 'You can set attributes for a new appointment button tile in a format', 'latepoint' ); ?>
                                <strong>data-selected-agent="ID" data-selected-location="ID" etc...</strong></div>
							<?php echo OsFormHelper::text_field( 'settings[customer_dashboard_book_button_attributes]', __( 'Attributes for New Appointment button', 'latepoint' ), OsSettingsHelper::get_settings_value( 'customer_dashboard_book_button_attributes', '' ), [ 'theme' => 'simple' ] ); ?>
                        </div>
                    </div>
                </div>

                <div class="sub-section-row">
                            <div class="sub-section-label">
                                <h3><?php _e( 'Security & Spam', 'latepoint' ) ?></h3>
                </div>
                <div class="sub-section-content">
                    <?php echo apply_filters('latepoint_general_settings_customer_security', OsUtilHelper::generate_missing_addon_link(__('Upgrade to the Premium version to unlock CAPTCHA protection and IP Address logging to fight with spam bookings.', 'latepoint'))); ?>
                </div>
                </div>

				<?php
				/**
				 * Plug after customer general settings output
				 *
				 * @since 5.1.0
				 * @hook latepoint_settings_general_customer_after
				 *
				 */
				do_action( 'latepoint_settings_general_customer_after' ); ?>
            </div>
        </div>
        <div class="white-box section-anchor" id="stickySectionSetup">
            <div class="white-box-header">
                <div class="os-form-sub-header"><h3><?php esc_html_e( 'Setup Pages', 'latepoint' ); ?></h3></div>
            </div>
            <div class="white-box-content no-padding">
                <div class="sub-section-row">
                    <div class="sub-section-label">
                        <h3><?php esc_html_e( 'Set Page URLs', 'latepoint' ) ?></h3>
                    </div>
                    <div class="sub-section-content">
						<?php echo OsFormHelper::text_field( 'settings[page_url_customer_dashboard]', __( 'Customer Dashboard Page URL', 'latepoint' ), OsSettingsHelper::get_customer_dashboard_url( false ), [ 'theme' => 'simple' ] ); ?>
						<?php echo OsFormHelper::text_field( 'settings[page_url_customer_login]', __( 'Customer Login Page URL', 'latepoint' ), OsSettingsHelper::get_customer_login_url( false ), [ 'theme' => 'simple' ] ); ?>
                    </div>
                </div>
            </div>
        </div>
		<?php
		/**
		 * Plug before "Other" section in general settings
		 *
		 * @since 5.1.0
		 * @hook latepoint_settings_general_before_other
		 *
		 */
		do_action( 'latepoint_settings_general_before_other' ); ?>
        <div class="white-box section-anchor" id="stickySectionOther">
            <div class="white-box-header">
                <div class="os-form-sub-header"><h3><?php esc_html_e( 'Other', 'latepoint' ); ?></h3></div>
            </div>
            <div class="white-box-content no-padding">
                <div class="sub-section-row">
                    <div class="sub-section-label">
                        <h3><?php esc_html_e( 'Business Information', 'latepoint' ) ?></h3>
                    </div>
                    <div class="sub-section-content">
                        <div class="os-row os-mb-2">
                            <div class="os-col-lg-12">
								<?php echo OsFormHelper::media_uploader_field( 'settings[business_logo]', 0, __( 'Company Logo', 'latepoint' ), __( 'Remove Image', 'latepoint' ), OsSettingsHelper::get_settings_value( 'business_logo' ) ); ?>
                            </div>
                        </div>
                        <div class="os-row">
                            <div class="os-col-lg-3">
								<?php echo OsFormHelper::text_field( 'settings[business_name]', __( 'Company Name', 'latepoint' ), OsSettingsHelper::get_settings_value( 'business_name' ), [ 'theme' => 'simple' ] ); ?>
                            </div>
                            <div class="os-col-lg-3">
								<?php echo OsFormHelper::text_field( 'settings[business_phone]', __( 'Business Phone', 'latepoint' ), OsSettingsHelper::get_settings_value( 'business_phone' ), [ 'theme' => 'simple' ] ); ?>
                            </div>
                            <div class="os-col-lg-6">
								<?php echo OsFormHelper::text_field( 'settings[business_address]', __( 'Business Address', 'latepoint' ), OsSettingsHelper::get_settings_value( 'business_address' ), [ 'theme' => 'simple' ] ); ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="sub-section-row">
                    <div class="sub-section-label">
                        <h3><?php esc_html_e( 'Calendar Settings', 'latepoint' ) ?></h3>
                    </div>
                    <div class="sub-section-content">
						<?php echo OsFormHelper::text_field( 'settings[day_calendar_min_height]', __( 'Minimum height of a daily calendar (in pixels)', 'latepoint' ), OsSettingsHelper::get_day_calendar_min_height(), [ 'theme' => 'simple' ] ); ?>


                        <div class="latepoint-message latepoint-message-subtle"><?php esc_html_e( 'You can use variables in your booking template, they will be replaced with a value for the booking. ', 'latepoint' ) ?><?php echo OsUtilHelper::template_variables_link_html(); ?></div>
						<?php echo OsFormHelper::text_field( 'settings[booking_template_for_calendar]', __( 'Booking tile information to display on calendar', 'latepoint' ), OsSettingsHelper::get_booking_template_for_calendar(), [ 'theme' => 'simple' ] ); ?>
                    </div>
                </div>
                <div class="sub-section-row">
                    <div class="sub-section-label">
                        <h3><?php esc_html_e( 'Conversion Tracking', 'latepoint' ) ?></h3>
                    </div>
                    <div class="sub-section-content">
                        <div class="latepoint-message latepoint-message-subtle">
                            <div><?php esc_html_e( 'You can include some javascript or html that will be appended to the confirmation step. For example you can track ad conversions by triggering a tracking code or a facebook pixel. You can use these variables within your code. Click on the variable to copy.', 'latepoint' ); ?></div>
                        </div>
                        <div class="tracking-info-w">
                            <div class="available-vars-w">
                                <div class="available-vars-i">
                                    <div class="available-vars-block">
                                        <ul>
                                            <li>
                                                <span class="var-label"><?php esc_html_e( 'Order ID#:', 'latepoint' ); ?></span>
                                                <span class="var-code os-click-to-copy">{{order_id}}</span>
                                            </li>
                                            <li>
                                                <span class="var-label"><?php esc_html_e( 'Customer ID#:', 'latepoint' ); ?></span>
                                                <span class="var-code os-click-to-copy">{{customer_id}}</span>
                                            </li>
                                            <li>
                                                <span class="var-label"><?php esc_html_e( 'Order Total:', 'latepoint' ); ?></span>
                                                <span class="var-code os-click-to-copy">{{order_total}}</span>
                                            </li>
                                            <li>
                                                <span class="var-label"><?php esc_html_e( 'Service IDs#:', 'latepoint' ); ?></span>
                                                <span class="var-code os-click-to-copy">{{service_ids}}</span>
                                            </li>
                                            <li>
                                                <span class="var-label"><?php esc_html_e( 'Agent IDs#:', 'latepoint' ); ?></span>
                                                <span class="var-code os-click-to-copy">{{agent_ids}}</span>
                                            </li>
                                            <li>
                                                <span class="var-label"><?php esc_html_e( 'Bundle IDs#:', 'latepoint' ); ?></span>
                                                <span class="var-code os-click-to-copy">{{bundle_ids}}</span>
                                            </li>
                                            <li>
                                                <span class="var-label"><?php esc_html_e( 'Location IDs#:', 'latepoint' ); ?></span>
                                                <span class="var-code os-click-to-copy">{{location_ids}}</span>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
							<?php echo OsFormHelper::textarea_field( 'settings[confirmation_step_tracking_code]', false, OsSettingsHelper::get_settings_value( 'confirmation_step_tracking_code', '' ), array(
								'theme' => 'bordered',
								'rows' => 9,
								'placeholder' => __( 'Enter Tracking code here', 'latepoint' )
							), [ 'class' => 'tracking-code-input-w' ] ); ?>
                        </div>
                    </div>
                </div>

                <div class="sub-section-row">
                    <div class="sub-section-label">
                        <h3><?php esc_html_e( 'Data Tables', 'latepoint' ) ?></h3>
                    </div>
                    <div class="sub-section-content">
                        <div class="os-row">
                            <div class="os-col-lg-6">
								<?php echo OsFormHelper::toggler_field( 'settings[allow_non_admins_download_csv]', __( 'Allow non admins to download table data as csv', 'latepoint' ), OsSettingsHelper::is_on( 'allow_non_admins_download_csv' ), false, false, [ 'sub_label' => __( 'Only admins will be able to download table data as csv', 'latepoint' ) ] ); ?>
                            </div>

                            <div class="os-col-lg-3">
								<?php echo OsFormHelper::select_field( 'settings[number_of_records_per_page]', __( 'Number of records per page', 'latepoint' ), [ 20, 50, 100, 200 ], OsSettingsHelper::get_settings_value( 'number_of_records_per_page', 20 ) ); ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="sub-section-row">
                    <div class="sub-section-label">
                        <h3><?php esc_html_e( 'Activity Logs', 'latepoint' ) ?></h3>
                    </div>
                    <div class="sub-section-content">
                        <div class="os-row">
                            <div class="os-col-lg-12">
								<?php echo OsFormHelper::toggler_field( 'settings[should_clear_old_activity_log]', __( 'Automatically clear old activity logs', 'latepoint' ), OsSettingsHelper::is_on( 'should_clear_old_activity_log' ), false, false, [ 'sub_label' => __( 'Activity logs older than 6 months will be automatically deleted', 'latepoint' ) ] ); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="sub-section-row">
                    <div class="sub-section-label">
                        <h3><?php esc_html_e( 'Improve LatePoint', 'latepoint' ) ?></h3>
                    </div>
                    <div class="sub-section-content">
                        <div class="os-row">
                            <div class="os-col-lg-12">
								<?php
                                    $ctl_sub_label = __( 'Share how you use the plugin so we can build features that matter, fix issues faster, and make smarter decisions. %1$sLearn More%2$s', 'latepoint' );

                                    $ctl_sub_label = sprintf(
                                        $ctl_sub_label,
                                        '<a href="https://latepoint.com/privacy-policy/" target="_blank" rel="noopener noreferrer">',
                                        '</a>'
                                    );

                                    $ctl_option_value = get_option( 'latepoint_usage_optin', 'no' );
                                    $ctl_is_active = $ctl_option_value === 'yes' ? true : false;

                                    echo OsFormHelper::toggler_field( 'settings[contribute_to_latepoint]', __( 'Help shape the future of LatePoint', 'latepoint' ), $ctl_is_active, false, false, [ 'sub_label' => $ctl_sub_label ] ); ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="sub-section-row">
                    <div class="sub-section-label">
                        <h3><?php esc_html_e( 'Export/Import', 'latepoint' ) ?></h3>
                    </div>
                    <div class="sub-section-content">
                        <a
                            class="latepoint-btn latepoint-btn-grey latepoint-btn-outline"
                            target="_blank"
                            href="<?php echo OsRouterHelper::build_admin_post_link(
                                [ 'settings', 'export_data' ],
                                [ '_wpnonce' => wp_create_nonce( 'export_data' ) ]
                            ); ?>"
                        >
                            <i class="latepoint-icon latepoint-icon-external-link"></i>
                            <span><?php esc_html_e('Export Data'); ?></span>
                        </a>
                        <a data-os-lightbox-classes="width-700" data-os-action="<?php echo esc_attr(OsRouterHelper::build_route_name('settings', 'import_modal')); ?>" href="#" data-os-output-target="lightbox" class="latepoint-btn latepoint-btn-grey latepoint-btn-outline"><i class="latepoint-icon latepoint-icon-download"></i><span><?php esc_html_e('Import Data', 'latepoint'); ?></span></a>
                    </div>
                </div>

				<?php
				/**
				 * Plug after other general settings output
				 *
				 * @since 4.7.0
				 * @hook latepoint_settings_general_other_after
				 *
				 */
				do_action( 'latepoint_settings_general_other_after' ); ?>
            </div>
        </div>
		<?php
		/**
		 * Plug after general settings output, before buttons
		 *
		 * @since 4.7.8
		 * @hook latepoint_settings_general_after
		 *
		 */
		do_action( 'latepoint_settings_general_after' ); ?>

        <?php
		/**
		 * Hidden plugin settings
		 *
		 * @since 5.2.10
		 */
        if(isset($_GET['danger_zone'])) { ?>
        <div class="white-box section-anchor" id="stickySectionOther">
            <div class="white-box-header">
                <div class="os-form-sub-header"><h3><?php esc_html_e( 'Danger Zone', 'latepoint' ); ?></h3></div>
            </div>
            <div class="white-box-content no-padding">

                <!-- Cleanup Section -->
                <div class="sub-section-row">
                    <div class="sub-section-label">
                        <h3><?php esc_html_e( 'Plugin Deletion', 'latepoint' ) ?></h3>
                    </div>
                    <div class="sub-section-content">
                        <div class="os-row">
                            <div class="os-col-lg-12">
                                <?php
                                echo OsFormHelper::toggler_field(
                                    'settings[remove_data_on_plugin_delete]',
                                    __( 'Remove all data on plugin deletion', 'latepoint' ),
                                    OsSettingsHelper::is_on( 'remove_data_on_plugin_delete' ),
                                    false,
                                    false,
                                    [ 'sub_label' => __( 'All LatePoint database tables and settings will be permanently deleted when the plugin is deleted', 'latepoint' ) ]
                                );
                                ?>
                            </div>
                        </div>
                        <div class="latepoint-message latepoint-message-subtle">
                            <?php esc_html_e( 'This action is irreversible. Before enabling this option, we recommend exporting your data so you have a backup.', 'latepoint' ); ?>
                            <a
                                target="_blank"
                                href="<?php echo esc_url(
                                    OsRouterHelper::build_admin_post_link(
                                        [ 'settings', 'export_data' ],
                                        [ '_wpnonce' => wp_create_nonce( 'export_data' ) ]
                                    )
                                ); ?>"
                            >
                                <?php esc_html_e( 'Export your data', 'latepoint' ); ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php } // Danger zone end. ?>
        <div class="os-form-buttons">
			<?php echo OsFormHelper::button( 'submit', __( 'Save Settings', 'latepoint' ), 'submit', [ 'class' => 'latepoint-btn' ] ); ?>
        </div>
</div>
<div class="latepoint-page-side-nav">
    <div class="side-nav-actions">
        <button type="submit" class="latepoint-btn latepoint-btn-block"><i class="latepoint-icon latepoint-icon-check"></i><span><?php _e( 'Save Changes', 'latepoint' ); ?></span></button>
    </div>
    <div class="side-nav-body">
        <div><a href="#stickySectionAppointment" class="is-active"><?php esc_html_e( 'Appointments', 'latepoint' ); ?></a></div>
        <div><a href="#stickySectionRestrictions"><?php esc_html_e( 'Restrictions', 'latepoint' ); ?></a></div>
        <div><a href="#stickySectionCurrency"><?php esc_html_e( 'Currency & Price', 'latepoint' ); ?></a></div>
        <div><a href="#stickySectionPhone"><?php esc_html_e( 'Phone', 'latepoint' ); ?></a></div>
        <div><a href="#stickySectionAvailability"><?php esc_html_e( 'Availability Logic', 'latepoint' ); ?></a></div>
        <div><a href="#stickySectionCustomer"><?php esc_html_e( 'Customers', 'latepoint' ); ?></a></div>
        <div><a href="#stickySectionSetup"><?php esc_html_e( 'Setup Pages', 'latepoint' ); ?></a></div>
        <?php

        /**
         * Sticky menu items links for the general settings
         *
         * @since 5.1.94
         * @hook latepoint_general_settings_sticky_section_items
         *
         * @param {array} $sticky_menu_items items that go into sticky menu on the right of settings, in format ['href' => '', 'label' => '']
         * @returns {array} The filtered array of sticky menu items
         */
        $before_other_items = apply_filters('latepoint_general_settings_sticky_section_items', []);
        foreach($before_other_items as $item){
            echo '<div><a href="#'.esc_attr($item['href']).'">'.esc_html( $item['label'] ).'</a></div>';
        }
        ?>
        <div><a href="#stickySectionOther"><?php esc_html_e( 'Other', 'latepoint' ); ?></a></div>
    </div>

</div>
</div>
</form>
