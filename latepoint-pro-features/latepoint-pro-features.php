<?php
/*
 * Copyright (c) 2024 LatePoint LLC. All rights reserved.
 */

/**
 * Plugin Name: LatePoint Addon - Pro Features
 * Plugin URI:  https://latepoint.com/
 * Description: LatePoint Addon that adds a set of Pro features to a base plugin
 * Version:     1.6.3
 * Author:      LatePoint
 * Author URI:  https://latepoint.com/
 * Text Domain: latepoint-pro-features
 * Domain Path: /languages
 * Requires Plugins: latepoint
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'LatePointAddonProFeatures' ) ) :

	/**
	 * Main Addon Class.
	 *
	 */

	class LatePointAddonProFeatures {

		/**
		 * Addon version.
		 *
		 */
		public $version    = '1.6.3';
		public $db_version = '1.1.3';
		public $addon_name = 'latepoint-pro-features';


		/**
		 * LatePoint Constructor.
		 */
		public function __construct() {
			$this->define_constants();
			$this->init_hooks();

			add_action( 'latepoint_includes', [ $this, 'core_includes' ] );
			add_action( 'latepoint_init_hooks', [ $this, 'core_init_hooks' ] );
		}

		public function init_hooks() {
			add_action( 'latepoint_on_addon_deactivate', [ $this, 'on_addon_deactivate' ], 10, 2 );

			// Free plugin version compatibility check.
			add_action( 'admin_init', array( $this, 'check_free_version' ), 20 );

			// Check that all installed addons meet their minimum required Pro version.
			add_action( 'admin_init', array( 'OsAddonsHelper', 'check_addon_min_pro_versions' ), 50 );

			register_activation_hook( __FILE__, [ $this, 'on_activate' ] );
			register_deactivation_hook( __FILE__, [ $this, 'on_deactivate' ] );
		}

		/**
		 * Check if the free LatePoint plugin version meets the minimum requirement.
		 */
		public function check_free_version() {
			if ( ! current_user_can( 'update_plugins' ) ) {
				return;
			}

			if (
				defined( 'LATEPOINT_VERSION' )
				&& defined( 'LATEPOINT_ADDON_PRO_MIN_REQUIRED_FREE_VERSION' )
				&& version_compare( LATEPOINT_VERSION, LATEPOINT_ADDON_PRO_MIN_REQUIRED_FREE_VERSION, '<' )
			) {
				add_action( 'admin_notices', array( $this, 'free_version_outdated_notice' ) );
			}
		}

		/**
		 * Display admin notice when the free LatePoint plugin version is outdated.
		 */
		public function free_version_outdated_notice() {
			$screen = get_current_screen();
			if ( isset( $screen->parent_file ) && 'plugins.php' === $screen->parent_file && 'update' === $screen->id ) {
				return;
			}

			$message = sprintf(
				/* translators: 1: bold open tag, 2: bold close tag, 3: required version, 4: current version */
				__( 'You are using an older version of the %1$sLatePoint%2$s plugin (v%4$s). Please update the %1$sLatePoint%2$s plugin to version %1$s%3$s or later%2$s.', 'latepoint-pro-features' ),
				'<strong>',
				'</strong>',
				esc_html( LATEPOINT_ADDON_PRO_MIN_REQUIRED_FREE_VERSION ),
				esc_html( LATEPOINT_VERSION )
			);
			$button = '<p><a href="' . esc_url( self_admin_url( 'plugins.php' ) ) . '" class="button-primary">' . esc_html__( 'Update Now', 'latepoint-pro-features' ) . '</a></p>';

			printf(
				'<div class="notice notice-error"><p>%1$s</p>%2$s</div>',
				wp_kses_post( $message ),
				wp_kses_post( $button )
			);
		}

		public function on_addon_deactivate( $addon_name, $addon_version ) {
			if ( class_exists( 'LatePoint\Cerber\RouterPro' ) ) {
				LatePoint\Cerber\RouterPro::wipe( $addon_name, $addon_version );
			}
			if ( class_exists( 'OsAddonsHelper' ) ) {
				OsAddonsHelper::remove_routed_addon( $addon_name );
			}
		}


		/**
		 * Define LatePoint Constants.
		 */
		public function define_constants() {
			global $wpdb;

			if ( ! defined( 'LATEPOINT_ADDON_PRO_VERSION' ) ) {
				define( 'LATEPOINT_ADDON_PRO_VERSION', $this->version );
			}
			if ( ! defined( 'LATEPOINT_ADDON_PRO_MIN_REQUIRED_FREE_VERSION' ) ) {
				define( 'LATEPOINT_ADDON_PRO_MIN_REQUIRED_FREE_VERSION', '5.6.5' );
			}

			/* Locations */
			if ( ! defined( 'LATEPOINT_ADDON_PRO_ABSPATH' ) ) {
				define( 'LATEPOINT_ADDON_PRO_ABSPATH', dirname( __FILE__ ) . '/' );
			}
			if ( ! defined( 'LATEPOINT_ADDON_PRO_LIB_ABSPATH' ) ) {
				define( 'LATEPOINT_ADDON_PRO_LIB_ABSPATH', LATEPOINT_ADDON_PRO_ABSPATH . 'lib/' );
			}
			if ( ! defined( 'LATEPOINT_ADDON_PRO_VIEWS_ABSPATH' ) ) {
				define( 'LATEPOINT_ADDON_PRO_VIEWS_ABSPATH', LATEPOINT_ADDON_PRO_LIB_ABSPATH . 'views/' );
			}

			/* Messages */
			if ( ! defined( 'LATEPOINT_TABLE_MESSAGES' ) ) {
				define( 'LATEPOINT_TABLE_MESSAGES', $wpdb->prefix . 'latepoint_messages' );
			}
			if ( ! defined( 'LATEPOINT_MESSAGE_CONTENT_TYPE_TEXT' ) ) {
				define( 'LATEPOINT_MESSAGE_CONTENT_TYPE_TEXT', 'text' );
			}
			if ( ! defined( 'LATEPOINT_MESSAGE_CONTENT_TYPE_ATTACHMENT' ) ) {
				define( 'LATEPOINT_MESSAGE_CONTENT_TYPE_ATTACHMENT', 'attachment' );
			}

			/* Service Extras */
			if ( ! defined( 'LATEPOINT_SERVICE_EXTRA_STATUS_ACTIVE' ) ) {
				define( 'LATEPOINT_SERVICE_EXTRA_STATUS_ACTIVE', 'active' );
			}
			if ( ! defined( 'LATEPOINT_SERVICE_EXTRA_STATUS_DISABLED' ) ) {
				define( 'LATEPOINT_SERVICE_EXTRA_STATUS_DISABLED', 'disabled' );
			}

			if ( ! defined( 'LATEPOINT_TABLE_SERVICE_EXTRAS' ) ) {
				define( 'LATEPOINT_TABLE_SERVICE_EXTRAS', $wpdb->prefix . 'latepoint_service_extras' );
			}
			if ( ! defined( 'LATEPOINT_TABLE_SERVICES_SERVICE_EXTRAS' ) ) {
				define( 'LATEPOINT_TABLE_SERVICES_SERVICE_EXTRAS', $wpdb->prefix . 'latepoint_services_service_extras' );
			}
			if ( ! defined( 'LATEPOINT_TABLE_BOOKINGS_SERVICE_EXTRAS' ) ) {
				define( 'LATEPOINT_TABLE_BOOKINGS_SERVICE_EXTRAS', $wpdb->prefix . 'latepoint_bookings_service_extras' );
			}

			/* Assets */
			if ( ! defined( 'LATEPOINT_ASSET_STATUS_ACTIVE' ) ) {
				define( 'LATEPOINT_ASSET_STATUS_ACTIVE', 'active' );
			}
			if ( ! defined( 'LATEPOINT_ASSET_STATUS_DISABLED' ) ) {
				define( 'LATEPOINT_ASSET_STATUS_DISABLED', 'disabled' );
			}
			if ( ! defined( 'LATEPOINT_TABLE_ASSETS' ) ) {
				define( 'LATEPOINT_TABLE_ASSETS', $wpdb->prefix . 'latepoint_assets' );
			}
			if ( ! defined( 'LATEPOINT_TABLE_SERVICES_ASSETS' ) ) {
				define( 'LATEPOINT_TABLE_SERVICES_ASSETS', $wpdb->prefix . 'latepoint_services_assets' );
			}
			if ( ! defined( 'LATEPOINT_TABLE_BOOKINGS_ASSETS' ) ) {
				define( 'LATEPOINT_TABLE_BOOKINGS_ASSETS', $wpdb->prefix . 'latepoint_bookings_assets' );
			}
			if ( ! defined( 'LATEPOINT_TABLE_AGENTS_ASSETS' ) ) {
				define( 'LATEPOINT_TABLE_AGENTS_ASSETS', $wpdb->prefix . 'latepoint_agents_assets' );
			}

			/* Coupons */
			if ( ! defined( 'LATEPOINT_TABLE_COUPONS' ) ) {
				define( 'LATEPOINT_TABLE_COUPONS', $wpdb->prefix . 'latepoint_coupons' );
			}
			if ( ! defined( 'LATEPOINT_COUPON_STATUS_ACTIVE' ) ) {
				define( 'LATEPOINT_COUPON_STATUS_ACTIVE', 'active' );
			}
			if ( ! defined( 'LATEPOINT_COUPON_STATUS_DISABLED' ) ) {
				define( 'LATEPOINT_COUPON_STATUS_DISABLED', 'disabled' );
			}

			if ( ! defined( 'LATEPOINT_PRO_REMOTE_HASH' ) ) {
				// https://wp.latepoint.com
				define( 'LATEPOINT_PRO_REMOTE_HASH', 'aHR0cHM6Ly93cC5sYXRlcG9pbnQuY29t' );
			}

			if ( ! defined( 'LATEPOINT_PRO_SSL_VERIFY' ) ) {
				define( 'LATEPOINT_PRO_SSL_VERIFY', true );
			}
		}


		public static function public_stylesheets() {
			return plugin_dir_url( __FILE__ ) . 'public/stylesheets/';
		}

		public static function public_javascripts() {
			return plugin_dir_url( __FILE__ ) . 'public/javascripts/';
		}


		/**
		 * Include required core files used in admin and on the frontend.
		 */
		public function core_includes() {
			// COMPOSER AUTOLOAD
			require dirname( __FILE__ ) . '/vendor/autoload.php';

			// CONTROLLERS
			include_once dirname( __FILE__ ) . '/lib/controllers/addons_controller.php';
			include_once dirname( __FILE__ ) . '/lib/controllers/coupons_controller.php';
			include_once dirname( __FILE__ ) . '/lib/controllers/timezone_selector_controller.php';
			include_once dirname( __FILE__ ) . '/lib/controllers/service_categories_controller.php';
			include_once dirname( __FILE__ ) . '/lib/controllers/bundles_controller.php';
			include_once dirname( __FILE__ ) . '/lib/controllers/debug_controller.php';
			include_once dirname( __FILE__ ) . '/lib/controllers/agents_controller.php';
			include_once dirname( __FILE__ ) . '/lib/controllers/locations_controller.php';
			include_once dirname( __FILE__ ) . '/lib/controllers/location_categories_controller.php';
			include_once dirname( __FILE__ ) . '/lib/controllers/messages_controller.php';
			include_once dirname( __FILE__ ) . '/lib/controllers/reminders_controller.php';
			include_once dirname( __FILE__ ) . '/lib/controllers/webhooks_controller.php';
			include_once dirname( __FILE__ ) . '/lib/controllers/service_extras_controller.php';
			include_once dirname( __FILE__ ) . '/lib/controllers/roles_controller.php';
			include_once dirname( __FILE__ ) . '/lib/controllers/service_durations_controller.php';
			include_once dirname( __FILE__ ) . '/lib/controllers/taxes_controller.php';
			include_once dirname( __FILE__ ) . '/lib/controllers/custom_fields_controller.php';
			include_once dirname( __FILE__ ) . '/lib/controllers/group_bookings_controller.php';
			include_once dirname( __FILE__ ) . '/lib/controllers/recurring_bookings_controller.php';
			include_once dirname( __FILE__ ) . '/lib/controllers/updates_controller.php';
			include_once dirname( __FILE__ ) . '/lib/controllers/whatsapp_controller.php';
			include_once dirname( __FILE__ ) . '/lib/controllers/assets_controller.php';
			include_once dirname( __FILE__ ) . '/lib/controllers/white_label_controller.php';
			include_once dirname( __FILE__ ) . '/lib/controllers/booking_reasons_controller.php';

			// HELPERS
			include_once dirname( __FILE__ ) . '/lib/helpers/addons_helper.php';
			include_once dirname( __FILE__ ) . '/lib/helpers/coupons_helper.php';
			include_once dirname( __FILE__ ) . '/lib/helpers/messages_helper.php';
			include_once dirname( __FILE__ ) . '/lib/helpers/webhooks_helper.php';
			include_once dirname( __FILE__ ) . '/lib/helpers/group_bookings_helper.php';
			include_once dirname( __FILE__ ) . '/lib/helpers/service_extras_helper.php';
			include_once dirname( __FILE__ ) . '/lib/helpers/service_extras_connector_helper.php';
			include_once dirname( __FILE__ ) . '/lib/helpers/taxes_helper.php';
			include_once dirname( __FILE__ ) . '/lib/helpers/custom_fields_helper.php';
			include_once dirname( __FILE__ ) . '/lib/helpers/social_helper.php';
			include_once dirname( __FILE__ ) . '/lib/helpers/updates_helper.php';
			include_once dirname( __FILE__ ) . '/lib/helpers/feature_timezone_helper.php';
			include_once dirname( __FILE__ ) . '/lib/helpers/feature_qrcode_helper.php';
			include_once dirname( __FILE__ ) . '/lib/helpers/feature_locations_helper.php';
			include_once dirname( __FILE__ ) . '/lib/helpers/feature_messages_helper.php';
			include_once dirname( __FILE__ ) . '/lib/helpers/feature_reminders_helper.php';
			include_once dirname( __FILE__ ) . '/lib/helpers/feature_webhooks_helper.php';
			include_once dirname( __FILE__ ) . '/lib/helpers/feature_service_extras_helper.php';
			include_once dirname( __FILE__ ) . '/lib/helpers/feature_group_bookings_helper.php';
			include_once dirname( __FILE__ ) . '/lib/helpers/feature_service_durations_helper.php';
			include_once dirname( __FILE__ ) . '/lib/helpers/feature_coupons_helper.php';
			include_once dirname( __FILE__ ) . '/lib/helpers/feature_custom_fields_helper.php';
			include_once dirname( __FILE__ ) . '/lib/helpers/feature_service_categories_helper.php';
			include_once dirname( __FILE__ ) . '/lib/helpers/feature_recurring_bookings_helper.php';
			include_once dirname( __FILE__ ) . '/lib/helpers/feature_cloudflare_turnstile_helper.php';
			include_once dirname( __FILE__ ) . '/lib/helpers/pro_license_helper.php';
			include_once dirname( __FILE__ ) . '/lib/helpers/analytics_pro_helper.php';
			include_once dirname( __FILE__ ) . '/lib/helpers/assets_helper.php';
			include_once dirname( __FILE__ ) . '/lib/helpers/assets_connector_helper.php';
			include_once dirname( __FILE__ ) . '/lib/helpers/agent_assets_connector_helper.php';
			include_once dirname( __FILE__ ) . '/lib/helpers/feature_assets_helper.php';
			include_once dirname( __FILE__ ) . '/lib/helpers/feature_no_show_restrictions_helper.php';
			include_once dirname( __FILE__ ) . '/lib/helpers/feature_base_fee_helper.php';
			include_once dirname( __FILE__ ) . '/lib/helpers/feature_service_display_mode_helper.php';
			include_once dirname( __FILE__ ) . '/lib/helpers/feature_white_label_helper.php';
			include_once dirname( __FILE__ ) . '/lib/helpers/feature_url_prefill_helper.php';
			include_once dirname( __FILE__ ) . '/lib/helpers/feature_max_bookings_per_day_helper.php';
			include_once dirname( __FILE__ ) . '/lib/helpers/feature_booking_reasons_helper.php';


			// MODELS
			include_once dirname( __FILE__ ) . '/lib/models/message_model.php';
			include_once dirname( __FILE__ ) . '/lib/models/service_extra_model.php';
			include_once dirname( __FILE__ ) . '/lib/models/booking_service_extra_model.php';
			include_once dirname( __FILE__ ) . '/lib/models/service_extra_connector_model.php';
			include_once dirname( __FILE__ ) . '/lib/models/coupon_model.php';
			include_once dirname( __FILE__ ) . '/lib/models/asset_model.php';
			include_once dirname( __FILE__ ) . '/lib/models/asset_connector_model.php';
			include_once dirname( __FILE__ ) . '/lib/models/booking_asset_connector_model.php';
			include_once dirname( __FILE__ ) . '/lib/models/agent_asset_connector_model.php';


			// MISC
			include_once dirname( __FILE__ ) . '/lib/misc/tax.php';
			include_once dirname( __FILE__ ) . '/lib/misc/router_pro.php';
		}

		public function core_init_hooks() {
			add_action( 'init', array( $this, 'init' ), 0 );
			add_filter( 'latepoint_installed_addons', [ $this, 'register_addon' ] );
			add_filter( 'latepoint_addons_sqls', [ $this, 'db_sqls' ] );

			add_action( 'latepoint_wp_enqueue_scripts', [ $this, 'load_front_scripts_and_styles' ] );
			add_action( 'latepoint_admin_enqueue_scripts', [ $this, 'load_admin_scripts_and_styles' ] );

			add_action( 'latepoint_check_if_addons_update_available', [ $this, 'check_addon_versions' ] );
			add_action( 'latepoint_check_if_addons_update_available', [ $this, 'route_addons' ] );

			add_filter( 'latepoint_localized_vars_front', [ $this, 'localized_vars_for_front' ] );
			add_filter( 'latepoint_localized_vars_admin', [ $this, 'localized_vars_for_admin' ] );

			add_filter( 'latepoint_missing_addon_link', [ $this, 'change_missing_addon_link' ], 10, 2 );
			add_filter( 'latepoint_pro_feature_block_html', [ $this, 'remove_pro_feature_block' ], 10, 3 );

			add_filter( 'latepoint_blocked_periods_for_range', [ $this, 'insert_blocked_periods_for_date_range' ], 10, 2 );
			add_action( 'latepoint_calendar_daily_timeline', [ $this, 'output_blocked_periods_on_timeline' ], 10, 2 );

			// Maximum bookings per day ( per-service / per-agent; global setting commented out - re-enable when needed ).
			add_filter( 'latepoint_blocked_periods_for_range', 'OsFeatureMaxBookingsPerDayHelper::enforce_daily_limit', 10, 3 );
			// add_action( 'latepoint_general_settings_section_restrictions_after', 'OsFeatureMaxBookingsPerDayHelper::render_global_field' );
			add_action( 'latepoint_service_saved', 'OsFeatureMaxBookingsPerDayHelper::save_service_setting', 10, 3 );
			add_action( 'latepoint_agent_saved', 'OsFeatureMaxBookingsPerDayHelper::save_agent_setting', 10, 3 );
			add_action( 'latepoint_quick_calendar_actions_settings', [ $this, 'output_quick_actions_on_calendar' ], 10, 6 );

			// Analytics.
			OsAnalyticsProHelper::init();

			/* ************************ */
			/* Updates */
			/* ************************ */
			add_filter( 'plugins_api', 'OsUpdatesHelper::set_addon_info', 20, 3 );
			add_filter( 'site_transient_update_plugins', 'OsUpdatesHelper::update_transient' );
			add_action( 'upgrader_process_complete', 'OsUpdatesHelper::purge_addon_cache', 10, 2 );


			/* ************************ */
			/* Invoicing */
			/* ************************ */

			// Settings hooks always registered so admin can toggle invoices on/off.
			add_action( 'latepoint_settings_general_before_other', 'OsInvoicesHelper::output_invoice_settings' );
			add_action( 'latepoint_general_settings_sticky_section_items', 'OsInvoicesHelper::add_settings_sticky_menu_item' );

			if ( OsSettingsHelper::is_on( 'enable_invoices', LATEPOINT_VALUE_ON ) ) {
				add_filter( 'latepoint_feature_invoices', '__return_true' );
				add_action( 'latepoint_order_quick_edit_form_content_after', 'OsInvoicesHelper::list_invoices_for_order' );
				add_filter( 'latepoint_transaction_invoice_link', [ $this, 'generate_invoice_link' ], 10, 2 );
				add_filter( 'latepoint_transaction_receipt_link', [ $this, 'generate_receipt_link' ], 10, 3 );
				add_filter( 'latepoint_process_event_types', 'OsInvoicesHelper::add_process_events_for_invoices' );
				add_filter( 'latepoint_process_event_names', 'OsInvoicesHelper::add_names_for_process_events_for_invoices' );
				add_filter( 'latepoint_process_event_condition_objects', 'OsInvoicesHelper::add_conditions_for_invoice_events', 10, 2 );
				add_filter( 'latepoint_process_event_trigger_condition_operators', 'OsInvoicesHelper::add_operators_to_conditions_for_invoice_events', 10, 4 );
				add_filter( 'latepoint_available_values_for_process_event_trigger_condition_property', 'OsInvoicesHelper::add_values_for_process_event_condition', 10, 4 );
				add_filter( 'latepoint_process_event_model', 'OsInvoicesHelper::add_invoice_to_process_event_models', 10, 2 );
				add_action( 'latepoint_list_transactions_transaction_after', [ $this, 'output_transaction_receipt_and_invoice_links' ] );
				add_action( 'latepoint_transaction_edit_form_after', [ $this, 'output_invoice_picker_for_transaction' ], 10, 2 );
				add_action( 'latepoint_available_vars_after', 'OsInvoicesHelper::output_invoice_vars' );

				add_action( 'latepoint_invoice_created', 'OsInvoicesHelper::log_invoice_created' );
				add_action( 'latepoint_invoice_updated', 'OsInvoicesHelper::log_invoice_updated', 10, 2 );
				add_filter( 'latepoint_process_prepare_data_for_run', 'OsInvoicesHelper::prepare_data_for_run' );

				add_action( 'latepoint_invoice_created', 'OsInvoicesHelper::handle_invoice_created', 12 );
				add_action( 'latepoint_invoice_updated', 'OsInvoicesHelper::handle_invoice_updated', 12, 2 );
				add_action( 'latepoint_replace_all_vars_in_template', 'OsInvoicesHelper::replace_invoice_vars_in_template', 12, 3 );

				add_filter( 'latepoint_get_object_model_type_for_process', 'OsInvoicesHelper::set_object_model_type_for_invoice_processes', 10, 3 );
				add_filter( 'latepoint_get_event_time_utc_for_process', 'OsInvoicesHelper::set_event_time_utc_for_invoice_processes', 10, 3 );
				add_filter( 'latepoint_process_event_data_sources', 'OsInvoicesHelper::add_data_source_for_invoice_events', 10, 2 );
				add_filter( 'latepoint_prepare_replacement_vars_from_data_objects', 'OsInvoicesHelper::prepare_replacement_vars_for_invoice', 10, 3 );
				add_filter( 'latepoint_get_process_object_by_source', 'OsInvoicesHelper::get_invoice_object_for_process', 10, 4 );
				add_filter( 'latepoint_load_templates_for_action_type', 'OsInvoicesHelper::add_invoice_templates_for_event_actions', 10, 3 );
				add_filter( 'latepoint_job_run_activity', 'OsInvoicesHelper::update_activity_after_invoice_job_run', 10, 3 );
				add_filter( 'latepoint_activity_view_vars', 'OsInvoicesHelper::add_activity_view_vars_for_invoice', 10, 2 );

				add_filter( 'latepoint_activity_codes', 'OsInvoicesHelper::add_invoice_activity_code' );
			}


			/* ************************ */
			/* Cloudflare */
			/* ************************ */
			add_action( 'latepoint_process_step', 'OsFeatureCloudflareTurnstileHelper::process_step_verify', 10, 3 );
			add_action( 'latepoint_general_settings_customer_security', 'OsFeatureCloudflareTurnstileHelper::output_customer_security_settings' );
			add_action( 'latepoint_settings_updated', 'OsFeatureCloudflareTurnstileHelper::validate_turnstile_settings' );

			/* ************************ */
			/* Social Login */
			/* ************************ */
			add_filter( 'latepoint_get_social_user_by_token', 'OsSocialHelper::set_social_user_by_token', 10, 3 );
			add_filter( 'latepoint_customer_login_show_other_options', 'OsSocialHelper::should_show_customer_login_social_options' );
			add_action( 'latepoint_settings_general_customer_after', 'OsSocialHelper::output_customer_settings' );
			add_action( 'latepoint_after_customer_login_form', 'OsSocialHelper::output_customer_login_social_options' );


			/* ************************ */
			/* URL Pre-fill */
			/* ************************ */
			add_action( 'latepoint_settings_general_customer_after', 'OsFeatureUrlPrefillHelper::output_settings' );
			if ( OsFeatureUrlPrefillHelper::is_enabled() ) {
				add_filter( 'latepoint_get_default_presets', 'OsFeatureUrlPrefillHelper::register_default_presets' );
				add_filter( 'latepoint_set_presets', 'OsFeatureUrlPrefillHelper::set_prefill_presets', 10, 2 );
				add_action( 'latepoint_booking_form_params_presets_after', 'OsFeatureUrlPrefillHelper::carry_preset_fields', 10, 5 );
				add_filter( 'latepoint_set_customer_object', 'OsFeatureUrlPrefillHelper::prefill_customer_object', 10, 2 );
			}


			/* ************************ */
			/* TimeZone */
			/* ************************ */
			add_action( 'latepoint_get_step_settings_edit_form_html', 'OsFeatureTimezoneHelper::add_timezone_settings', 10, 2 );
			add_action( 'latepoint_available_vars_booking', 'OsFeatureTimezoneHelper::add_timezone_vars_for_booking', 15 );
			add_action( 'latepoint_available_vars_customer', 'OsFeatureTimezoneHelper::add_timezone_vars_for_customer', 15 );
			add_action( 'latepoint_customer_dashboard_before_appointments', 'OsFeatureTimezoneHelper::add_timezone_selector_to_customer_dashboard', 10 );
			add_filter( 'latepoint_booking_form_classes', 'OsFeatureTimezoneHelper::add_booking_form_class' );
			add_filter( 'latepoint_replace_booking_vars', 'OsFeatureTimezoneHelper::replace_booking_vars_for_timezone', 10, 2 );
			add_filter( 'latepoint_get_resources_grouped_by_day', 'OsFeatureTimezoneHelper::apply_timeshift_to_resources_grouped_by_day', 10, 5 );
			add_filter( 'latepoint_timezone_name_from_session', 'OsFeatureTimezoneHelper::get_timezone_name_for_logged_in_customer' );
			add_filter( 'latepoint_get_nice_datetime_for_summary', 'OsFeatureTimezoneHelper::output_booking_datetime_in_timezone', 10, 3 );
			add_filter( 'latepoint_dates_and_times_picker_after', 'OsFeatureTimezoneHelper::output_timezone_after_datepicker', 10, 3 );

			// Reschedule
			add_filter( 'latepoint_customer_reschedule_settings', [ $this, 'add_reschedule_settings_for_customer' ], 10, 1 );

			// Cancellation / reschedule reason feature.
			OsFeatureBookingReasonsHelper::init_hooks();
			add_filter( 'latepoint_is_feature_reschedule_available', '__return_true' );


			/* ************************ */
			/* Webhooks */
			/* ************************ */
			add_filter( 'latepoint_activity_codes', 'OsFeatureWebhooksHelper::add_webhook_code' );
			add_filter( 'latepoint_process_action_settings_fields_html_after', 'OsFeatureWebhooksHelper::add_webhook_settings', 10, 2 );
			add_filter( 'latepoint_process_action_generate_preview', 'OsFeatureWebhooksHelper::generate_webhook_preview', 10, 2 );
			add_filter( 'latepoint_process_action_run', 'OsFeatureWebhooksHelper::process_webhook_action', 10, 2 );
			add_filter( 'latepoint_process_prepare_data_for_run', 'OsFeatureWebhooksHelper::prepare_data_for_run' );

			/* ************************ */
			/* Misc */
			/* ************************ */
			add_action( 'latepoint_general_settings_section_restrictions_after', [ $this, 'add_cart_restrictions_settings' ], 10, 2 );
			add_action( 'latepoint_general_settings_section_restrictions_after', [ $this, 'add_assets_settings' ], 11 );
			add_filter( 'latepoint_can_checkout_multiple_items', [ $this, 'enable_multiple_items_checkout' ], 10, 2 );
			add_filter(
				'latepoint_upgrade_top_bar_link_html',
				function ( $html ) {
					return '';
				}
			);
			add_filter( 'latepoint_show_upgrade_link_on_plugins_page', '__return_false' );

			/* ************************ */
			/* No-Show Restrictions */
			/* ************************ */
			add_action( 'latepoint_general_settings_section_restrictions_after', 'OsFeatureNoShowRestrictionsHelper::add_settings' );
			if ( OsSettingsHelper::is_on( 'no_show_restrictions_enabled' ) ) {
				// On bookinh change status update
				add_action( 'latepoint_booking_updated', 'OsFeatureNoShowRestrictionsHelper::handle_booking_status_change', 10, 2 );
				add_action( 'latepoint_booking_created', 'OsFeatureNoShowRestrictionsHelper::handle_booking_created', 10, 1 );
				// Notice on customer form
				add_action( 'latepoint_customer_edit_form_after', 'OsFeatureNoShowRestrictionsHelper::output_customer_no_show_warning' );
				// To stop booking process further
				add_action( 'latepoint_process_step', 'OsFeatureNoShowRestrictionsHelper::verify_step_no_show_restriction', 20, 2 );
				add_filter( 'latepoint_get_enabled_payment_times', 'OsFeatureNoShowRestrictionsHelper::filter_payment_times' );
			}

			/* ************************ */
			/* QR Code */
			/* ************************ */
			add_action( 'latepoint_booking_summary_before_summary_box', 'OsFeatureQrcodeHelper::generate_qr_code_for_booking', 10, 2 );

			add_action( 'latepoint_booking_summary_after_summary_box_inner', 'OsFeatureQrcodeHelper::generate_qr_code_link', 10, 2 );

			/* ************************ */
			/* Reminders */
			/* ************************ */
			add_filter( 'latepoint_event_time_offset_settings_html', 'OsFeatureRemindersHelper::add_event_time_offset_settings_html', 10, 2 );
			add_action( 'latepoint_process_scheduled_jobs', 'OsFeatureRemindersHelper::process_scheduled_jobs' );


			/* ************************ */
			/* Recurring Bookings */
			/* ************************ */
			add_filter( 'latepoint_get_step_codes_with_rules', 'OsFeatureRecurringBookingsHelper::add_step_for_recurring_bookings', 10, 2 );
			add_filter( 'latepoint_step_show_next_btn_rules', 'OsFeatureRecurringBookingsHelper::add_step_show_next_btn_rules', 10, 2 );
			add_filter( 'latepoint_should_step_be_skipped', 'OsFeatureRecurringBookingsHelper::should_step_be_skipped', 10, 5 );
			add_action( 'latepoint_load_step', 'OsFeatureRecurringBookingsHelper::load_step_recurring_bookings', 10, 3 );
			add_action( 'latepoint_settings_for_step_codes', 'OsFeatureRecurringBookingsHelper::add_settings_for_step' );
			add_action( 'latepoint_step_labels_by_step_codes', 'OsFeatureRecurringBookingsHelper::add_label_for_step' );
			add_action( 'latepoint_summary_booking_info_after_start_date', 'OsFeatureRecurringBookingsHelper::output_recurring_info_on_booking_summary', 10, 4 );
			add_action( 'latepoint_recurrent_bookings_sequence_info', 'OsFeatureRecurringBookingsHelper::output_recurring_info_for_sequence_of_bookings', 10, 4 );
			add_action( 'latepoint_service_form_after', 'OsFeatureRecurringBookingsHelper::output_recurrence_settings_on_service_form' );
			add_action( 'latepoint_service_saved', 'OsFeatureRecurringBookingsHelper::save_recurring_settings_in_service', 10, 3 );
			add_filter( 'latepoint_svg_for_step_code', 'OsFeatureRecurringBookingsHelper::add_svg_for_step', 10, 2 );
			add_filter( 'latepoint_after_step_content', 'OsFeatureRecurringBookingsHelper::add_recurring_modal_to_datepicker_step', 10 );
			add_filter(
				'latepoint_is_feature_recurring_bookings_on',
				function ( $is_on ) {
					return true;
				},
				10
			);


			/* ************************ */
			/* Group Bookings */
			/* ************************ */
			add_filter( 'latepoint_generated_params_for_booking_form', 'OsFeatureGroupBookingsHelper::add_total_attendees_to_booking_form_params', 10, 2 );
			add_filter( 'latepoint_step_show_next_btn_rules', 'OsFeatureGroupBookingsHelper::add_step_show_next_btn_rules', 10, 2 );
			add_filter( 'latepoint_should_step_be_skipped', 'OsFeatureGroupBookingsHelper::should_step_be_skipped', 10, 5 );
			add_action( 'latepoint_load_step', 'OsFeatureGroupBookingsHelper::load_step_group_bookings', 10, 3 );
			add_filter( 'latepoint_get_step_codes_with_rules', 'OsFeatureGroupBookingsHelper::add_step_for_group_bookings', 10, 2 );
			add_action( 'latepoint_settings_for_step_codes', 'OsFeatureGroupBookingsHelper::add_settings_for_step' );
			add_action( 'latepoint_step_labels_by_step_codes', 'OsFeatureGroupBookingsHelper::add_label_for_step' );
			add_action( 'latepoint_booking_data_form_after_service', 'OsFeatureGroupBookingsHelper::output_total_attendees_on_quick_form', 10, 2 );
			add_action( 'latepoint_service_form_after', 'OsFeatureGroupBookingsHelper::output_capacity_on_service_form' );
			add_filter( 'latepoint_booking_summary_service_attributes', 'OsFeatureGroupBookingsHelper::add_capacity_to_service_attributes', 10, 2 );
			add_action( 'latepoint_price_breakdown_service_row_for_booking', 'OsFeatureGroupBookingsHelper::add_attendees_to_service_row_item', 10, 2 );

			add_action( 'latepoint_available_vars_booking', 'OsFeatureGroupBookingsHelper::add_group_bookings_vars', 15 );
			add_action( 'latepoint_service_saved', 'OsFeatureGroupBookingsHelper::save_service_info', 15, 3 );
			add_action( 'latepoint_after_service_extra_form', 'OsFeatureGroupBookingsHelper::add_service_extra_settings' );
			add_action( 'latepoint_service_tile_info_rows_after', 'OsFeatureGroupBookingsHelper::add_capacity_info_to_service_tile' );
			add_filter( 'latepoint_replace_booking_vars', 'OsFeatureGroupBookingsHelper::replace_booking_vars_for_group_bookings', 10, 2 );
			add_filter( 'latepoint_full_amount_for_service', 'OsFeatureGroupBookingsHelper::adjust_full_amount_for_service', 10, 2 );
			add_filter( 'latepoint_full_amount_for_service_extra', 'OsFeatureGroupBookingsHelper::adjust_full_amount_for_service_extra', 10, 4 );
			add_filter( 'latepoint_deposit_amount_for_service', 'OsFeatureGroupBookingsHelper::adjust_deposit_amount_for_service', 10, 2 );
			add_filter( 'latepoint_bookings_data_for_csv_export', 'OsFeatureGroupBookingsHelper::add_columns_to_bookings_data_for_csv', 11, 2 );
			add_filter( 'latepoint_booking_row_for_csv_export', 'OsFeatureGroupBookingsHelper::add_columns_to_booking_row_for_csv', 11, 3 );
			add_filter( 'latepoint_svg_for_step_code', 'OsFeatureGroupBookingsHelper::add_svg_for_step', 10, 2 );
			// -- for webhooks addon
			add_filter( 'latepoint_webhook_variables_for_new_booking', 'OsFeatureGroupBookingsHelper::add_data_to_webhook', 10, 2 );
			add_filter( 'latepoint_bookings_table_columns', 'OsFeatureGroupBookingsHelper::add_columns_to_bookings_table' );
			add_action( 'latepoint_remove_preset_steps', 'OsFeatureGroupBookingsHelper::remove_group_bookings_step_if_preselected', 10, 4 );

			/* ************************ */
			/* Signup Fee */
			/* ************************ */
			add_action( 'latepoint_service_saved', 'OsFeatureBaseFeeHelper::save_service_info', 15, 3 );
			add_filter( 'latepoint_full_amount_for_service', 'OsFeatureBaseFeeHelper::adjust_full_amount_for_service', 11, 2 );
			add_filter( 'latepoint_price_breakdown_service_row_for_booking', 'OsFeatureBaseFeeHelper::add_base_fee_to_service_row_item', 11, 2 );

			/* ************************ */
			/* Service Display Mode */
			/* ************************ */
			add_filter( 'latepoint_default_booking_atts', 'OsFeatureServiceDisplayModeHelper::add_default_booking_att' );
			add_filter( 'latepoint_get_default_restrictions', 'OsFeatureServiceDisplayModeHelper::add_default_restriction' );
			add_filter( 'latepoint_set_restrictions', 'OsFeatureServiceDisplayModeHelper::set_restriction', 10, 2 );
			add_filter( 'latepoint_step_services', 'OsFeatureServiceDisplayModeHelper::filter_step_services', 10, 3 );
			add_filter( 'latepoint_step_bundles', 'OsFeatureServiceDisplayModeHelper::filter_step_bundles', 10, 3 );
			add_filter( 'latepoint_bundles_folder_skip_wrapper', 'OsFeatureServiceDisplayModeHelper::skip_bundles_folder_wrapper', 10, 2 );
			add_filter( 'latepoint_book_widget_allowed_params', 'OsFeatureServiceDisplayModeHelper::add_widget_allowed_param', 10, 2 );
			add_action( 'latepoint_booking_form_params', 'OsFeatureServiceDisplayModeHelper::output_form_param', 10, 2 );
			add_action( 'elementor/element/latepoint_book_button/content_section_booking_form_settings/after_section_end', 'OsFeatureServiceDisplayModeHelper::add_elementor_control', 10, 2 );
			add_action( 'elementor/element/latepoint_book_form/content_section_booking_form_settings/after_section_end', 'OsFeatureServiceDisplayModeHelper::add_elementor_control', 10, 2 );
			add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_block_editor_service_display_mode' ] );

			/* ************************ */
			/* Service Durations */
			/* ************************ */
			add_filter( 'latepoint_step_show_next_btn_rules', 'OsFeatureServiceDurationsHelper::add_step_show_next_btn_rules', 10, 2 );
			add_filter( 'latepoint_should_step_be_skipped', 'OsFeatureServiceDurationsHelper::should_step_be_skipped', 10, 5 );
			add_action( 'latepoint_service_edit_durations', 'OsFeatureServiceDurationsHelper::edit_durations_html' );
			add_action( 'latepoint_get_step_settings_edit_form_html', 'OsFeatureServiceDurationsHelper::add_duration_settings', 10, 2 );
			add_action( 'latepoint_load_step', 'OsFeatureServiceDurationsHelper::load_step_service_durations', 10, 3 );
			add_filter( 'latepoint_get_step_codes_with_rules', 'OsFeatureServiceDurationsHelper::add_step_for_service_durations', 10, 2 );
			add_action( 'latepoint_settings_for_step_codes', 'OsFeatureServiceDurationsHelper::add_settings_for_step' );
			add_action( 'latepoint_step_labels_by_step_codes', 'OsFeatureServiceDurationsHelper::add_label_for_step' );
			add_action( 'latepoint_booking_get_service_name_for_summary', 'OsFeatureServiceDurationsHelper::add_duration_to_booking_service_name_for_summary', 10, 2 );
			add_action( 'latepoint_remove_preset_steps', 'OsFeatureServiceDurationsHelper::remove_durations_step_if_preselected', 10, 4 );
			add_filter( 'latepoint_svg_for_step_code', 'OsFeatureServiceDurationsHelper::add_svg_for_step', 10, 2 );

			/* ************************ */
			/* Messages */
			/* ************************ */
			if ( OsSettingsHelper::is_on( 'pro_feature_toggle_messages', true ) ) {
				add_action( 'latepoint_booking_data_form_after', 'OsFeatureMessagesHelper::output_messages_on_quick_form', 12, 2 );
				add_action( 'latepoint_customer_dashboard_after_tabs', 'OsFeatureMessagesHelper::output_messages_tab_on_customer_dashboard' );
				add_action( 'latepoint_customer_dashboard_after_tab_contents', 'OsFeatureMessagesHelper::output_messages_tab_contents_on_customer_dashboard' );
				add_action( 'latepoint_settings_notifications_other_after', 'OsFeatureMessagesHelper::new_message_notification_template_settings' );
				add_action( 'latepoint_top_bar_before_actions', 'OsFeatureMessagesHelper::add_messages_link_to_top_bar' );
				add_action( 'latepoint_available_vars_booking', 'OsFeatureMessagesHelper::add_messages_vars', 15 );
			}
			add_action( 'latepoint_booking_deleted', 'OsFeatureMessagesHelper::delete_messages_for_deleted_booking_id' );

			/* ************************ */
			/* Locations */
			/* ************************ */
			add_action( 'latepoint_load_step', 'OsFeatureLocationsHelper::load_step_locations', 10, 3 );
			add_filter( 'latepoint_get_step_codes_with_rules', 'OsFeatureLocationsHelper::add_step_for_locations', 10, 2 );
			add_action( 'latepoint_settings_for_step_codes', 'OsFeatureLocationsHelper::add_settings_for_step' );
			add_action( 'latepoint_step_labels_by_step_codes', 'OsFeatureLocationsHelper::add_label_for_step' );
			add_action( 'latepoint_get_step_settings_edit_form_html', 'OsFeatureLocationsHelper::add_location_categories_setting', 10, 2 );
			add_filter( 'latepoint_model_view_as_data', 'OsFeatureLocationsHelper::add_location_data_vars_to_booking', 10, 2 );
			add_filter( 'latepoint_custom_field_condition_properties', 'OsFeatureLocationsHelper::add_custom_field_condition_properties' );
			add_filter( 'latepoint_available_values_for_condition_property', 'OsFeatureLocationsHelper::add_values_for_condition_property', 10, 2 );
			add_filter( 'latepoint_model_options_for_multi_select', 'OsFeatureLocationsHelper::add_options_for_multi_select', 10, 2 );
			add_filter( 'latepoint_process_event_trigger_condition_properties', 'OsFeatureLocationsHelper::add_process_event_condition_properties', 10, 2 );
			add_filter( 'latepoint_available_values_for_process_event_condition_property', 'OsFeatureLocationsHelper::add_values_for_process_event_condition_properties', 10, 2 );
			add_filter( 'latepoint_bookings_data_for_csv_export', 'OsFeatureLocationsHelper::add_location_to_bookings_data_for_csv', 11, 2 );
			add_filter( 'latepoint_booking_row_for_csv_export', 'OsFeatureLocationsHelper::add_location_to_booking_row_for_csv', 11, 3 );
			add_filter( 'latepoint_webhook_variables_for_new_booking', 'OsFeatureLocationsHelper::add_booking_location_to_webhook', 10, 2 );
			add_filter( 'latepoint_capabilities_for_controllers', 'OsFeatureLocationsHelper::add_capabilities_for_controllers' );


			/* ************************ */
			/* Coupons */
			/* ************************ */
			add_filter( 'latepoint_filter_payment_total_info', 'OsCouponsHelper::get_payment_total_info_with_coupon_html', 10, 2 );
			add_filter( 'latepoint_order_reload_price_breakdown', 'OsFeatureCouponsHelper::reload_coupon_discount' );
			add_filter( 'latepoint_step_show_next_btn_rules', 'OsFeatureCouponsHelper::add_step_show_next_btn_rules', 10, 2 );
			add_filter( 'latepoint_bookings_data_for_csv_export', 'OsFeatureCouponsHelper::add_coupon_code_to_bookings_data_for_csv', 11, 2 );
			add_filter( 'latepoint_booking_row_for_csv_export', 'OsFeatureCouponsHelper::add_coupon_code_to_booking_row_for_csv', 11, 3 );
			add_filter( 'latepoint_bookings_table_columns', 'OsFeatureCouponsHelper::add_coupon_columns_to_bookings_table' );
			add_filter( 'latepoint_cart_price_breakdown_rows', 'OsFeatureCouponsHelper::add_coupon_info_to_cart_price_breakdown_rows', 9, 3 );
			add_filter( 'latepoint_roles_get_all_available_actions_list', 'OsFeatureCouponsHelper::add_coupon_actions_to_roles' );
			add_filter( 'latepoint_roles_action_names', 'OsFeatureCouponsHelper::add_coupon_name_to_roles', 10, 2 );
			add_action( 'latepoint_order_quick_form_price_after_total', 'OsFeatureCouponsHelper::show_coupon_code_on_order_quick_edit_form' );
			add_filter( 'latepoint_model_view_as_first_level_data', 'OsFeatureCouponsHelper::add_coupon_data_vars_to_order', 10, 2 );
			add_filter( 'latepoint_capabilities_for_controllers', 'OsFeatureCouponsHelper::add_capabilities_for_controllers' );
			add_action( 'latepoint_after_verify_step_content', 'OsFeatureCouponsHelper::add_coupon_form_to_verify_step', 10, 1 );
			add_action( 'latepoint_before_step_content', 'OsFeatureCloudflareTurnstileHelper::add_turnstile_to_verify_step', 10, 1 );
			add_action( 'latepoint_cart_calculate_prices', 'OsFeatureCouponsHelper::apply_coupons_to_cart_calculations', 10, 1 );
			add_action( 'latepoint_available_vars_order', 'OsFeatureCouponsHelper::add_coupons_vars', 10, 1 );
			add_action( 'latepoint_get_step_settings_edit_form_html', 'OsFeatureCouponsHelper::add_coupon_settings', 10, 2 );
			add_action( 'latepoint_order_item_recalculate_prices', 'OsFeatureCouponsHelper::recalculate_order_item_prices_with_coupon', 10 );

			/* ************************ */
			/* Taxes */
			/* ************************ */
			add_filter( 'latepoint_cart_price_breakdown_rows', 'OsTaxesHelper::add_taxes_to_cart_price_breakdown_rows', 11, 3 );
			add_action( 'latepoint_cart_calculate_prices', 'OsTaxesHelper::calculate_taxes_for_cart', 10, 1 );


			/* ************************ */
			/* Custom Fields */
			/* ************************ */
			add_action( 'latepoint_customer_params_on_steps', 'OsFeatureCustomFieldsHelper::filter_customer_custom_fields_on_steps', 10, 2 );
			add_action( 'latepoint_step_labels_by_step_codes', 'OsFeatureCustomFieldsHelper::add_label_for_step' );
			add_action( 'latepoint_settings_for_step_codes', 'OsFeatureCustomFieldsHelper::add_settings_for_step' );
			add_filter( 'latepoint_get_step_codes_with_rules', 'OsFeatureCustomFieldsHelper::add_step_for_custom_fields', 10, 2 );
			add_action( 'latepoint_custom_step_info', 'OsFeatureCustomFieldsHelper::show_step_info' );
			add_filter( 'latepoint_step_show_next_btn_rules', 'OsFeatureCustomFieldsHelper::add_step_show_next_btn_rules', 10, 2 );
			add_filter( 'latepoint_model_loaded_by_id', 'OsFeatureCustomFieldsHelper::load_custom_fields_for_model' );
			add_filter( 'latepoint_get_results_as_models', 'OsFeatureCustomFieldsHelper::load_custom_fields_for_model' );
			add_filter( 'latepoint_should_step_be_skipped', 'OsFeatureCustomFieldsHelper::should_step_be_skipped', 10, 5 );
			add_filter( 'latepoint_generated_params_for_booking_form', 'OsFeatureCustomFieldsHelper::add_custom_fields_to_booking_form_params', 10, 2 );
			add_filter( 'latepoint_customer_import_fields', 'OsFeatureCustomFieldsHelper::add_customer_import_fields', 10 );
			add_action( 'latepoint_customer_imported', 'OsFeatureCustomFieldsHelper::customer_import_custom_fields', 10, 3 );

			// -- CSV Export Filters
			add_filter( 'latepoint_bookings_data_for_csv_export', 'OsFeatureCustomFieldsHelper::add_custom_fields_to_bookings_data_for_csv', 10, 2 );
			add_filter( 'latepoint_booking_row_for_csv_export', 'OsFeatureCustomFieldsHelper::add_custom_fields_to_booking_row_for_csv', 10, 3 );
			add_filter( 'latepoint_customers_data_for_csv_export', 'OsFeatureCustomFieldsHelper::add_custom_fields_to_customers_data_for_csv', 10, 2 );
			add_filter( 'latepoint_customer_row_for_csv_export', 'OsFeatureCustomFieldsHelper::add_custom_fields_to_customer_row_for_csv', 10, 3 );
			// -- Template variables
			add_filter( 'latepoint_replace_booking_vars', 'OsFeatureCustomFieldsHelper::replace_booking_vars_in_template', 10, 2 );
			add_filter( 'latepoint_replace_customer_vars', 'OsFeatureCustomFieldsHelper::replace_customer_vars_in_template', 10, 2 );
			// -- Model View as Data
			add_filter( 'latepoint_model_view_as_data', 'OsFeatureCustomFieldsHelper::add_customer_custom_fields_data_vars_to_customer', 10, 2 );
			add_filter( 'latepoint_model_view_as_data', 'OsFeatureCustomFieldsHelper::add_booking_custom_fields_data_vars_to_booking', 10, 2 );
			// -- Processes
			add_filter( 'latepoint_process_event_trigger_condition_properties', 'OsFeatureCustomFieldsHelper::add_custom_fields_to_processes', 10, 2 );
			// -- Booking Index
			add_filter( 'latepoint_bookings_table_columns', 'OsFeatureCustomFieldsHelper::add_custom_fields_to_bookings_table_columns' );
			add_action( 'latepoint_customer_dashboard_information_form_after', 'OsFeatureCustomFieldsHelper::output_customer_custom_fields_on_customer_dashboard' );
			add_action( 'latepoint_customer_edit_form_after', 'OsFeatureCustomFieldsHelper::output_customer_custom_fields_on_form' );
			add_action( 'latepoint_customer_inline_edit_form_after', 'OsFeatureCustomFieldsHelper::output_customer_custom_fields_on_quick_form' );
			add_action( 'latepoint_booking_data_form_after', 'OsFeatureCustomFieldsHelper::output_booking_custom_fields_on_quick_form', 10, 2 );
			add_action( 'latepoint_load_step', 'OsFeatureCustomFieldsHelper::load_step_custom_fields_for_booking', 10, 3 );
			add_action( 'latepoint_process_step', 'OsFeatureCustomFieldsHelper::process_step_custom_fields', 10, 3 );
			add_filter( 'latepoint_svg_for_step_code', 'OsFeatureCustomFieldsHelper::add_svg_for_step', 10, 2 );
			// Confirmation and Verification Booking Steps
			add_filter( 'latepoint_booking_summary_service_attributes', 'OsFeatureCustomFieldsHelper::add_booking_custom_fields_to_service_attributes', 10, 2 );
			add_filter( 'latepoint_booking_summary_customer_attributes', 'OsFeatureCustomFieldsHelper::add_customer_custom_fields_to_service_attributes', 10, 2 );
			add_filter( 'latepoint_capabilities_for_controllers', 'OsFeatureCustomFieldsHelper::add_capabilities_for_controller' );
			// TODO this needs to be integrated into the order intent (create_or_update method), read the github for old method
			add_filter( 'latepoint_cart_data_for_order_intent', 'OsFeatureCustomFieldsHelper::process_custom_fields_in_booking_data_for_order_intent' );
			add_action( 'latepoint_available_vars_after', 'OsFeatureCustomFieldsHelper::output_custom_fields_vars' );
			add_action( 'latepoint_settings_general_other_after', 'OsFeatureCustomFieldsHelper::output_google_autocomplete_settings' );
			add_action( 'latepoint_model_set_data', 'OsFeatureCustomFieldsHelper::set_custom_fields_data', 10, 2 );
			add_action( 'latepoint_model_save', 'OsFeatureCustomFieldsHelper::save_custom_fields' );
			add_action( 'latepoint_model_validate', 'OsFeatureCustomFieldsHelper::validate_custom_fields', 10, 3 );
			add_action( 'latepoint_booking_steps_contact_after', 'OsFeatureCustomFieldsHelper::add_custom_fields_for_contact_step', 10, 2 );


			/* ************************ */
			/* Service Extras */
			/* ************************ */
			add_action( 'latepoint_service_form_after', 'OsFeatureServiceExtrasHelper::output_extras_on_service_form' );
			add_action( 'latepoint_service_saved', 'OsFeatureServiceExtrasHelper::save_extras_in_service', 10, 3 );
			add_action( 'latepoint_load_step', 'OsFeatureServiceExtrasHelper::load_step_service_extras', 10, 3 );
			add_action( 'latepoint_settings_for_step_codes', 'OsFeatureServiceExtrasHelper::add_settings_for_step' );
			add_action( 'latepoint_step_labels_by_step_codes', 'OsFeatureServiceExtrasHelper::add_label_for_step' );
			add_action( 'latepoint_booking_data_form_after_service', 'OsFeatureServiceExtrasHelper::add_service_extras_to_quick_form', 10, 2 );
			add_action( 'latepoint_booking_deleted', 'OsFeatureServiceExtrasHelper::delete_service_extras_for_booking' );
			add_action( 'latepoint_booking_created', 'OsFeatureServiceExtrasHelper::save_service_extras_for_booking', 9 );
			add_action( 'latepoint_booking_updated', 'OsFeatureServiceExtrasHelper::save_service_extras_for_booking', 9, 2 );
			add_action( 'latepoint_available_vars_booking', 'OsFeatureServiceExtrasHelper::add_service_extras_vars' );
			add_action( 'latepoint_service_deleted', 'OsServiceExtrasConnectorHelper::delete_service_connections_after_deletion' );
			add_action( 'latepoint_process_step', 'OsFeatureServiceExtrasHelper::process_service_extras_step', 10, 3 );
			add_filter( 'latepoint_model_view_as_data', 'OsFeatureServiceExtrasHelper::add_service_extras_data_vars_to_booking', 10, 2 );
			add_filter( 'latepoint_bookings_data_for_csv_export', 'OsFeatureServiceExtrasHelper::add_service_extras_to_bookings_data_for_csv', 11, 2 );
			add_filter( 'latepoint_booking_row_for_csv_export', 'OsFeatureServiceExtrasHelper::add_service_extras_to_booking_row_for_csv', 11, 3 );
			add_filter( 'latepoint_replace_booking_vars', 'OsFeatureServiceExtrasHelper::replace_booking_vars_for_service_extras', 10, 2 );
			add_filter( 'latepoint_calculated_total_duration', 'OsFeatureServiceExtrasHelper::calculated_total_duration', 10, 2 );
			add_filter( 'latepoint_model_set_data', 'OsFeatureServiceExtrasHelper::set_data_for_models', 10, 2 );
			add_filter( 'latepoint_model_allowed_params', 'OsFeatureServiceExtrasHelper::set_allowed_params_for_service_extra_model', 10, 3 );
			add_filter( 'latepoint_generated_params_for_booking_form', 'OsFeatureServiceExtrasHelper::add_extras_to_form_params', 10, 2 );
			add_filter( 'latepoint_capabilities_for_controllers', 'OsFeatureServiceExtrasHelper::add_capabilities_for_controller' );
			add_filter( 'latepoint_price_breakdown_service_row_for_booking', 'OsFeatureServiceExtrasHelper::add_service_extras_to_price_breakdown_service_row', 10, 2 );
			add_filter( 'latepoint_order_reload_price_breakdown', 'OsFeatureServiceExtrasHelper::sync_service_extras_on_price_reload' );
			add_filter( 'latepoint_svg_for_step_code', 'OsFeatureServiceExtrasHelper::add_svg_for_step', 10, 2 );
			add_filter( 'latepoint_calculate_full_amount_for_booking', 'OsServiceExtrasHelper::calculate_service_extras_prices', 9, 2 );
			add_filter( 'latepoint_should_step_be_skipped', 'OsFeatureServiceExtrasHelper::should_step_be_skipped', 10, 5 );
			add_filter( 'latepoint_booking_summary_service_attributes', 'OsFeatureServiceExtrasHelper::add_service_extras_to_service_attributes', 10, 2 );
			add_filter( 'latepoint_get_step_codes_with_rules', 'OsFeatureServiceExtrasHelper::add_step_for_service_extras', 10 );
			add_filter( 'latepoint_step_show_next_btn_rules', 'OsFeatureServiceExtrasHelper::add_step_show_next_btn_rules', 10, 2 );

			/* ************************ */
			/* Assets */
			/* ************************ */
			if ( OsSettingsHelper::is_on( 'enable_assets_functionality' ) ) {
				add_action( 'latepoint_service_form_after', 'OsFeatureAssetsHelper::output_assets_on_service_form' );
				add_action( 'latepoint_service_saved', 'OsFeatureAssetsHelper::save_assets_in_service', 10, 3 );
				add_action( 'latepoint_booking_created', 'OsFeatureAssetsHelper::assign_assets_for_booking', 9 );
				add_action( 'latepoint_booking_updated', 'OsFeatureAssetsHelper::assign_assets_for_booking', 9, 2 );
				add_action( 'latepoint_booking_deleted', 'OsFeatureAssetsHelper::delete_assets_for_booking' );
				add_action( 'latepoint_service_deleted', 'OsAssetsConnectorHelper::delete_service_connections_after_deletion' );
				add_action( 'latepoint_agent_deleted', 'OsAgentAssetsConnectorHelper::delete_agent_connections_after_deletion' );
				add_filter( 'latepoint_get_resources_grouped_by_day', 'OsFeatureAssetsHelper::filter_by_asset_availability', 12, 5 );
				add_filter( 'latepoint_capabilities_for_controllers', 'OsFeatureAssetsHelper::add_capabilities_for_controller' );
				add_filter( 'latepoint_roles_get_all_available_actions_list', 'OsFeatureAssetsHelper::add_asset_capabilities' );
			}

			/* ************************ */
			/* White Label */
			/* ************************ */
			OsFeatureWhiteLabelHelper::init();

			/* Side menu */
			add_filter( 'latepoint_side_menu', [ $this, 'add_menu_links' ] );

			/* Sticky menus */
			add_filter( 'latepoint_service_edit_form_sticky_section_items', [ $this, 'add_sticky_menu_items_for_service_edit' ] );

			/* Scripts & Styles for clean layout */
			add_filter( 'latepoint_clean_layout_css_files', [ $this, 'add_styles_to_clean_layout' ], 10 );
			add_filter( 'latepoint_clean_layout_js_files', [ $this, 'add_scripts_to_clean_layout' ], 10 );
		}

		/**
		 * Init LatePoint when WordPress Initialises.
		 */
		public function init() {
			// Set up localisation.
			$this->load_plugin_textdomain();
			OsUpdatesHelper::set_update_message();
		}


		public function check_addon_versions() {
			OsUpdatesHelper::check_addons_latest_version();
		}

		public function add_styles_to_clean_layout( array $css_files ): array {
			$css_files[] = 'latepoint-pro-features-front';
			return $css_files;
		}


		public function add_scripts_to_clean_layout( array $js_files ): array {
			$js_files[] = 'latepoint-pro-features-front';


			// Google Places API
			if ( ! empty( OsSettingsHelper::get_settings_value( 'google_places_api_key' ) ) ) {
				$js_files[] = 'google-places-api';
			}

			// Clouflare turnstile
			if ( OsSettingsHelper::is_on( 'enable_cloudflare_turnstile' ) ) {
				$js_files[] = 'cf-turnstile';
			}

			// Google Login
			if ( OsSettingsHelper::is_using_google_login() ) {
				$js_files[] = 'google-gsi-client';
			}


			return $js_files;
		}

		public function add_sticky_menu_items_for_service_edit( array $items ): array {
			$items[] = [
				'href'  => 'stickySectionRecurring',
				'label' => __( 'Recurring Appointments', 'latepoint-pro-features' ),
			];
			$items[] = [
				'href'  => 'stickySectionGroupBookings',
				'label' => __( 'Group Bookings', 'latepoint-pro-features' ),
			];
			$items[] = [
				'href'  => 'stickySectionServiceExtras',
				'label' => __( 'Service Extras', 'latepoint-pro-features' ),
			];
			$items[] = [
				'href'  => 'stickySectionRestrictionsExtras',
				'label' => __( 'Restrictions for Extras', 'latepoint-pro-features' ),
			];
			$items[] = [
				'href'  => 'stickySectionAssets',
				'label' => __( 'Assets', 'latepoint-pro-features' ),
			];
			return $items;
		}

		public function add_reschedule_settings_for_customer( string $html ): string {
			ob_start();
			echo OsFormHelper::toggler_field( 'settings[allow_customer_booking_reschedule]', __( 'Allow customers reschedule their bookings', 'latepoint-pro-features' ), OsSettingsHelper::is_on( 'allow_customer_booking_reschedule' ), 'reschedule_settings', 'normal', [ 'sub_label' => __( 'If enable, shows a button on customer cabinet to reschedule an appointment', 'latepoint-pro-features' ) ] ); ?>
			<div class="os-mb-2"
				 id="reschedule_settings" <?php echo OsSettingsHelper::is_on( 'allow_customer_booking_reschedule' ) ? '' : 'style="display:none"' ?>>
				<?php echo OsFormHelper::toggler_field( 'settings[limit_when_customer_can_reschedule]', __( 'Set restriction on when customer can reschedule', 'latepoint-pro-features' ), OsSettingsHelper::is_on( 'limit_when_customer_can_reschedule' ), 'reschedule_limit_settings' ); ?>
				<div class="os-mb-2"
					 id="reschedule_limit_settings" <?php echo OsSettingsHelper::is_on( 'limit_when_customer_can_reschedule' ) ? '' : 'style="display:none"' ?>>
					<div class="merged-fields os-mt-1">
						<div class="merged-label"><?php esc_html_e( 'Can reschedule when it is at least', 'latepoint-pro-features' ); ?></div>
						<?php echo OsFormHelper::text_field( 'settings[reschedule_limit_value]', false, OsSettingsHelper::get_settings_value( 'reschedule_limit_value', 5 ), [ 'placeholder' => __( 'Value', 'latepoint-pro-features' ) ] ); ?>
						<?php echo OsFormHelper::select_field(
							'settings[reschedule_limit_unit]',
							false,
							array(
								'minute' => __( 'minutes', 'latepoint-pro-features' ),
								'hour'   => __( 'hours', 'latepoint-pro-features' ),
								'day'    => __( 'days', 'latepoint-pro-features' ),
							),
							OsSettingsHelper::get_settings_value( 'reschedule_limit_unit', 'hour' )
						); ?>
						<div class="merged-label"><?php esc_html_e( 'before appointment start time', 'latepoint-pro-features' ); ?></div>
					</div>
				</div>
				<?php echo OsFormHelper::toggler_field( 'settings[change_status_on_customer_reschedule]', __( 'Change booking status when customer reschedules', 'latepoint-pro-features' ), OsSettingsHelper::is_on( 'change_status_on_customer_reschedule' ), 'reschedule_change_status_settings' ); ?>
				<div class="os-mb-2"
					 id="reschedule_change_status_settings" <?php echo OsSettingsHelper::is_on( 'change_status_on_customer_reschedule' ) ? '' : 'style="display:none"' ?>>
					<div class="merged-fields os-mt-1">
						<div class="merged-label"><?php esc_html_e( 'Change status to:', 'latepoint-pro-features' ); ?></div>
						<?php echo OsFormHelper::select_field(
							'settings[status_to_set_after_customer_reschedule]',
							false,
							OsBookingHelper::get_statuses_list(),
							OsSettingsHelper::get_settings_value( 'status_to_set_after_customer_reschedule', LATEPOINT_BOOKING_STATUS_PENDING )
						); ?>
					</div>
				</div>
				<?php echo OsFormHelper::toggler_field( 'settings[enable_reschedule_reason]', __( 'Ask customer for a reschedule reason', 'latepoint-pro-features' ), OsSettingsHelper::is_on( 'enable_reschedule_reason' ), false, false, [ 'sub_label' => __( 'If enabled, customers are prompted for a reason before rescheduling', 'latepoint-pro-features' ) ] ); ?>
			</div>
			<?php
			$html = ob_get_clean();
			return $html;
		}

		public function output_quick_actions_on_calendar( $blocked_period, $start_date, $readable_start_date, $agents_list, $services_list, $locations_list ) {
			?>

			<form action="" data-os-after-call="latepoint_calendar_custom_period_created" data-os-action="<?php echo OsRouterHelper::build_route_name( 'calendars', 'apply_period_block' ); ?>">
			<div class="quick-calendar-action-toggler">
				<div class="quick-calendar-action-toggle <?php echo $blocked_period->is_new_record() ? 'selected' : ''; ?>" data-period-type="full"><?php esc_html_e( 'All Day', 'latepoint-pro-features' ); ?></div>
				<div class="quick-calendar-action-toggle <?php echo ! $blocked_period->is_new_record() ? 'selected' : ''; ?>" data-period-type="partial"><?php esc_html_e( 'Specific Time', 'latepoint-pro-features' ); ?></div>
			</div>
			<div class="slot-off-reason">
			<?php
				echo OsFormHelper::text_field(
					'blocked_period_settings[summary]',
					__( 'Reason for blocking (optional)', 'latepoint-pro-features' ),
					$blocked_period->summary ?? '',
					[
						'placeholder' => __( 'Enter a reason...', 'latepoint-pro-features' ),
						'theme'       => 'simple',
					]
				);
			?>
			</div>

			<?php
			$period_settings = [
				'week_day'    => $start_date->format( 'N' ),
				'start_time'  => $blocked_period->start_time,
				'end_time'    => (int) $blocked_period->start_time + 60,
				'agent_id'    => $blocked_period->agent_id,
				'location_id' => $blocked_period->location_id,
				'service_id'  => $blocked_period->service_id,
				'custom_date' => $start_date->format( 'Y-m-d' ),
			];
			echo OsWorkPeriodsHelper::generate_work_period_form( $period_settings, false );
			if ( ( count( $agents_list ) > 1 ) || ( count( $services_list ) > 1 ) || ( count( $locations_list ) > 1 ) ) {
				?>
				<div class="latepoint-message latepoint-message-subtle quick-calendar-action-settings-slot-off-title"><?php echo sprintf( esc_html__( 'Select resources you want to be unavailable during this time on %s', 'latepoint-pro-features' ), $readable_start_date ); ?></div>
				<div class="latepoint-message latepoint-message-subtle quick-calendar-action-settings-day-off-title"><?php echo sprintf( esc_html__( 'Select resources you want to be unavailable on %s', 'latepoint-pro-features' ), $readable_start_date ); ?></div>
				<?php
			}
			if ( count( $agents_list ) > 1 ) {
				$agents_list = array_merge(
					$agents_list,
					[
						[
							'value' => 0,
							'label' => __( 'All Agents', 'latepoint-pro-features' ),
						],
					]
				);
				echo OsFormHelper::select_field( 'blocked_period_settings[agent_id]', false, $agents_list, $blocked_period->agent_id );
			} else {
				echo OsFormHelper::hidden_field( 'blocked_period_settings[agent_id]', $blocked_period->agent_id );
			}

			if ( count( $services_list ) > 1 ) {
				$services_list = array_merge(
					$services_list,
					[
						[
							'value' => 0,
							'label' => __( 'All Services', 'latepoint-pro-features' ),
						],
					]
				);
				echo OsFormHelper::select_field( 'blocked_period_settings[service_id]', false, $services_list, $blocked_period->service_id );
			} else {
				echo OsFormHelper::hidden_field( 'blocked_period_settings[service_id]', 0 );
			}

			if ( count( $locations_list ) > 1 ) {
				$locations_list = array_merge(
					$locations_list,
					[
						[
							'value' => 0,
							'label' => __( 'All Locations', 'latepoint-pro-features' ),
						],
					]
				);
				echo OsFormHelper::select_field( 'blocked_period_settings[location_id]', false, $locations_list, $blocked_period->location_id );
			} else {
				echo OsFormHelper::hidden_field( 'blocked_period_settings[location_id]', 0 );
			}

			echo OsFormHelper::hidden_field( 'blocked_period_settings[date]', $start_date->format( 'Y-m-d' ) );
			echo OsFormHelper::hidden_field( 'blocked_period_settings[full_day_off]', ( $blocked_period->is_new_record() ? 'yes' : 'no' ) );
			if ( ! $blocked_period->is_new_record() ) {
				echo OsFormHelper::hidden_field( 'blocked_period_settings[id]', $blocked_period->id );
			}
			wp_nonce_field( 'save_custom_day_schedule_' . ( $blocked_period->is_new_record() ? 'new' : $blocked_period->id ) ); ?>
			<div class="quick-calendar-actions-buttons">
				<button type="submit" class="latepoint-btn latepoint-btn-block latepoint-btn-primary"><?php _e( 'Save', 'latepoint-pro-features' ) ?></button>
				<?php if ( ! $blocked_period->is_new_record() ) { ?>
					<a href="#"
					   data-os-success-action="reload"
					   data-os-action="<?php echo esc_attr( OsRouterHelper::build_route_name( 'calendars', 'delete_blocked_period' ) ); ?>"
					   data-os-params="<?php echo esc_attr( OsUtilHelper::build_os_params( [ 'id' => $blocked_period->id ], 'delete_blocked_period_' . $blocked_period->id ) ); ?>"
					   data-os-prompt="<?php esc_attr_e( 'Are you sure you want to delete this blocked time period?', 'latepoint-pro-features' ); ?>"
					   class="latepoint-delete-order latepoint-btn latepoint-btn-secondary latepoint-btn-just-icon"
					   title="<?php esc_attr_e( 'Delete Blocked Period', 'latepoint-pro-features' ); ?>">
						<i class="latepoint-icon latepoint-icon-trash1"></i>
					</a>
				<?php } ?>
			</div>
			</form>
			<?php
		}

		public function output_blocked_periods_on_timeline( DateTime $target_date, $args ) {
			if ( ! class_exists( 'OsOffPeriodModel' ) ) {
				return;
			}
			$blocked_periods = new OsOffPeriodModel();

			$filter              = new \LatePoint\Misc\Filter();
			$filter->agent_id    = $args['agent_id'] ?? 0;
			$filter->location_id = $args['location_id'] ?? 0;
			$filter->service_id  = $args['service_id'] ?? 0;

			// filter should only matter when there is a single resource being filtered, otherwise don't account for them in the search of blocked periods
			if ( is_array( $filter->location_id ) && count( $filter->location_id ) > 1 ) {
				$filter->location_id = 0;
			}
			if ( is_array( $filter->service_id ) && count( $filter->service_id ) > 1 ) {
				$filter->service_id = 0;
			}
			if ( is_array( $filter->agent_id ) && count( $filter->agent_id ) > 1 ) {
				$filter->agent_id = 0;
			}

			$filter->date_from = $target_date->format( 'Y-m-d' );
			$filter->date_to   = $target_date->format( 'Y-m-d' );

			$query_args = $filter->build_query_args_for_blocked_periods();

			$blocked_periods = $blocked_periods->where( $query_args )->get_results_as_models();

			if ( $blocked_periods ) {
				foreach ( $blocked_periods as $period ) {
					if ( $period->start_time >= $args['work_end_minutes'] || $period->end_time <= $args['work_start_minutes'] ) {
						continue;
					}
					$period_duration         = min( $period->end_time, $args['work_end_minutes'] ) - max( $period->start_time, $args['work_start_minutes'] );
					$period_duration_percent = $period_duration * 100 / $args['work_total_minutes'];
					$period_start_percent    = ( max( $period->start_time, $args['work_start_minutes'] ) - $args['work_start_minutes'] ) / ( $args['work_end_minutes'] - $args['work_start_minutes'] ) * 100;
					if ( $period_start_percent < 0 ) {
						$period_start_percent = 0;
					}
					if ( $period_start_percent >= 100 ) {
						continue;
					}
					?>
					<div data-os-after-call="latepoint_init_calendar_quick_actions" data-os-action="<?php echo OsRouterHelper::build_route_name( 'calendars', 'quick_actions' ); ?>" data-os-lightbox-classes="width-400" data-os-output-target="lightbox" data-os-params="<?php echo OsUtilHelper::build_os_params( [ 'blocked_period_id' => $period->id ] ); ?>" class="ch-day-blocked-period"
						 style="top: <?php echo $period_start_percent; ?>%; height: <?php echo $period_duration_percent; ?>%;">
						<div class="ch-day-blocked-period-i">
							<div class="blocked-period-summary">
								<?php echo empty( $period->summary ) ? __( 'Blocked Period', 'latepoint-pro-features' ) : $period->summary; ?>
							</div>
							<div class="blocked-period-time">
								<?php echo OsTimeHelper::minutes_to_hours_and_minutes( $period->start_time ) . ' - ' . OsTimeHelper::minutes_to_hours_and_minutes( $period->end_time ); ?>
							</div>
						</div>
					</div>
					<?php
				}
			}
		}

		public function insert_blocked_periods_for_date_range( $blocked_periods_arr, \LatePoint\Misc\Filter $filter ) {
			if ( ! class_exists( 'OsOffPeriodModel' ) ) {
				return $blocked_periods_arr;
			}
			if ( ! $filter->date_from || ! $filter->date_to ) {
				return $blocked_periods_arr;
			}
			if ( ! $filter->connections ) {
				return $blocked_periods_arr;
			}

			$blocked_periods = new OsOffPeriodModel();
			$query_args      = $filter->build_query_args_for_blocked_periods();
			$blocked_periods = $blocked_periods->where( $query_args )->order_by( 'agent_id DESC, service_id DESC, location_id DESC, start_time asc' )->get_results_as_models();

			foreach ( $blocked_periods as $blocked_period ) {
				$blocked_periods_arr[ $blocked_period->start_date ][] = new \LatePoint\Misc\BlockedPeriod(
					[
						'start_time'  => $blocked_period->start_time,
						'end_time'    => $blocked_period->end_time,
						'start_date'  => $blocked_period->start_date,
						'end_date'    => $blocked_period->end_date,
						'agent_id'    => $blocked_period->agent_id,
						'location_id' => $blocked_period->location_id,
						'service_id'  => $blocked_period->service_id,
					]
				);
			}
			return $blocked_periods_arr;
		}

		public function route_addons() {
			$active_addons = json_decode( OsSettingsHelper::get_settings_value( 'active_addons', '' ) ) ?? [];
			$routed_addons = OsAddonsHelper::get_routed_addons();
			$need_to_route = array_diff( $active_addons, $routed_addons );
			foreach ( $need_to_route as $addon_name ) {
				if ( class_exists( 'LatePoint\Cerber\RouterPro' ) ) {
					$addon_version = get_option( $addon_name . '_addon_db_version', 'n/a' );
					LatePoint\Cerber\RouterPro::trace( $addon_name, $addon_version );
				}
				OsAddonsHelper::add_routed_addon( $addon_name );
			}
		}

		public function generate_invoice_link( string $link, OsInvoiceModel $invoice ): string {
			$link = $invoice->get_access_url();
			return $link;
		}

		public function remove_pro_feature_block( string $html, string $label, string $label_code ): string {
			$html = '';
			return $html;
		}

		public function change_missing_addon_link( string $html, string $label ): string {
			$html = '<a target="_blank" href="' . esc_url( OsRouterHelper::build_link( [ 'addons', 'index' ] ) ) . '" class="os-add-box" >
              <div class="add-box-graphic-w"><div class="add-box-plus"><i class="latepoint-icon latepoint-icon-plus4"></i></div></div>
              <div class="add-box-label">' . esc_html( __( 'Enable this feature by installing an appropriate addon.', 'latepoint-pro-features' ) ) . '</div>
            </a>';
			return $html;
		}

		public function output_invoice_picker_for_transaction( OsTransactionModel $transaction, string $real_or_rand_id ): void {
			$order           = $transaction->order;
			$invoices        = $order->get_invoices();
			$invoices_select = [
				[
					'value' => '',
					'label' => __( 'Not applied to invoice', 'latepoint-pro-features' ),
				],
			];
			foreach ( $invoices as $invoice ) {
				$invoices_select[] = [
					'value' => $invoice->id,
					'label' => sprintf( __( '#%s', 'latepoint-pro-features' ), $invoice->get_invoice_number() ) . ' [' . OsMoneyHelper::format_price( $invoice->charge_amount, true, false ) . ']',
				];
			}
			echo OsFormHelper::select_field( 'transactions[' . $real_or_rand_id . '][invoice_id]', __( 'Apply to Invoice', 'latepoint-pro-features' ), $invoices_select, $transaction->invoice_id, false );
		}

		public function output_transaction_receipt_and_invoice_links( OsTransactionModel $transaction ): void {
			echo '<div class="topit-links">';
			echo '<a target="_blank" class="topit-receipt-link" href="' . esc_url( $transaction->get_receipt_url() ) . '"><span>' . esc_html__( 'View Receipt', 'latepoint-pro-features' ) . '</span><i class="latepoint-icon latepoint-icon-external-link"></i></a>';
			if ( $transaction->invoice_id ) {
				echo '<a target="_blank" class="topit-invoice-link" href="' . esc_url( $transaction->get_invoice_url() ) . '"><span>' . esc_html__( 'View Invoice', 'latepoint-pro-features' ) . '</span><i class="latepoint-icon latepoint-icon-external-link"></i></a>';
			}
			echo '</div>';
		}

		public function generate_receipt_link( string $link, OsInvoiceModel $invoice, OsTransactionModel $transaction ): string {
			$link = $transaction->get_receipt_url();

			return $link;
		}

		public function add_menu_links( $menus ) {
			$user_role = OsAuthHelper::get_current_user()->backend_user_type;
			switch ( $user_role ) {
				case LATEPOINT_USER_TYPE_ADMIN:
					$menus[] = array(
						'id'          => 'addons',
						'label'       => __( 'Add-ons', 'latepoint-pro-features' ),
						'show_notice' => OsUpdatesHelper::is_update_available_for_addons(),
						'icon'        => 'latepoint-icon latepoint-icon-plus1',
						'link'        => OsRouterHelper::build_link( [ 'addons', 'index' ] ),
					);
					for ( $i = 0; $i < count( $menus ); $i++ ) {
						if ( empty( $menus[ $i ]['id'] ) ) {
							continue;
						}
						// Settings
						if ( $menus[ $i ]['id'] == 'settings' && isset( $menus[ $i ]['children'] ) ) {
							$menus[ $i ]['children'][] = [
								'label' => __( 'Premium Features', 'latepoint-pro-features' ),
								'icon'  => '',
								'link'  => OsRouterHelper::build_link( [ 'updates', 'status' ] ),
							];
						}
					}
					// no break - admin also needs the menu modifications below.
				case LATEPOINT_USER_TYPE_CUSTOM:
				case LATEPOINT_USER_TYPE_AGENT:
					for ( $i = 0; $i < count( $menus ); $i++ ) {
						if ( empty( $menus[ $i ]['id'] ) ) {
							continue;
						}

						// Multi agents
						if ( $menus[ $i ]['id'] == 'agents' ) {
							$menus[ $i ]['link'] = OsRouterHelper::build_link( [ 'agents', 'index' ] );
						}


						// Services
						if ( $menus[ $i ]['id'] == 'services' && isset( $menus[ $i ]['children'] ) ) {
							for ( $j = 0; $j < count( $menus[ $i ]['children'] ); $j++ ) {
								// Categories
								if ( isset( $menus[ $i ]['children'][ $j ]['id'] ) && $menus[ $i ]['children'][ $j ]['id'] == 'categories' ) {
									$menus[ $i ]['children'][ $j ]['link'] = OsRouterHelper::build_link(
										[
											'service_categories',
											'index',
										]
									);
								}
								// Bundles
								if ( isset( $menus[ $i ]['children'][ $j ]['id'] ) && $menus[ $i ]['children'][ $j ]['id'] == 'bundles' ) {
									$menus[ $i ]['children'][ $j ]['link'] = OsRouterHelper::build_link(
										[
											'bundles',
											'index',
										]
									);
								}
								// Extras
								if ( isset( $menus[ $i ]['children'][ $j ]['id'] ) && $menus[ $i ]['children'][ $j ]['id'] == 'service_extras' ) {
									$menus[ $i ]['children'][ $j ]['link'] = OsRouterHelper::build_link(
										[
											'service_extras',
											'index',
										]
									);
								}
							}
						}


						// Settings
						if ( $menus[ $i ]['id'] == 'settings' && isset( $menus[ $i ]['children'] ) ) {
							for ( $j = 0; $j < count( $menus[ $i ]['children'] ); $j++ ) {
								// Roles
								if ( isset( $menus[ $i ]['children'][ $j ]['id'] ) && $menus[ $i ]['children'][ $j ]['id'] == 'roles' ) {
									$menus[ $i ]['children'][ $j ]['link'] = OsRouterHelper::build_link(
										[
											'roles',
											'index',
										]
									);
								}
								// Taxes
								if ( isset( $menus[ $i ]['children'][ $j ]['id'] ) && $menus[ $i ]['children'][ $j ]['id'] == 'taxes' ) {
									$menus[ $i ]['children'][ $j ]['link'] = OsRouterHelper::build_link(
										[
											'taxes',
											'index',
										]
									);
								}
								// White Label
								if ( isset( $menus[ $i ]['children'][ $j ]['id'] ) && $menus[ $i ]['children'][ $j ]['id'] == 'white_label' ) {
									$menus[ $i ]['children'][ $j ]['link'] = OsRouterHelper::build_link(
										[
											'white_label',
											'settings',
										]
									);
								}
							}
						}

						// Custom Fields
						if ( $menus[ $i ]['id'] == 'form_fields' ) {
							$menus[ $i ] = [
								'id'       => 'form_fields',
								'label'    => __( 'Form Fields', 'latepoint-pro-features' ),
								'icon'     => 'latepoint-icon latepoint-icon-browser',
								'link'     => OsRouterHelper::build_link( [ 'custom_fields', 'for_customer' ] ),
								'children' => [
									[
										'label' => __( 'Customer Fields', 'latepoint-pro-features' ),
										'icon'  => '',
										'link'  => OsRouterHelper::build_link( [ 'custom_fields', 'for_customer' ] ),
									],
									[
										'label' => __( 'Booking Fields', 'latepoint-pro-features' ),
										'icon'  => '',
										'link'  => OsRouterHelper::build_link( [ 'custom_fields', 'for_booking' ] ),
									],
								],
							];
						}


						// Locations
						if ( $menus[ $i ]['id'] == 'locations' ) {
							$menus[ $i ] = [
								'id'       => 'locations',
								'label'    => __( 'Locations', 'latepoint-pro-features' ),
								'icon'     => 'latepoint-icon latepoint-icon-map-marker',
								'link'     => OsRouterHelper::build_link( [ 'locations', 'index' ] ),
								'children' => [
									[
										'label' => __( 'Locations', 'latepoint-pro-features' ),
										'icon'  => '',
										'link'  => OsRouterHelper::build_link( [ 'locations', 'index' ] ),
									],
									[
										'label' => __( 'Categories', 'latepoint-pro-features' ),
										'icon'  => '',
										'link'  => OsRouterHelper::build_link( [ 'location_categories', 'index' ] ),
									],
								],
							];
						}

						// Assets
						if ( $menus[ $i ]['id'] == 'assets' ) {
							$menus[ $i ] = [
								'id'    => 'assets',
								'label' => __( 'Assets', 'latepoint-pro-features' ),
								'icon'  => 'latepoint-icon latepoint-icon-layers',
								'link'  => OsRouterHelper::build_link( [ 'assets', 'index' ] ),
							];
						}

						// Coupons
						if ( $menus[ $i ]['id'] == 'coupons' ) {
							$menus[ $i ]['link'] = OsRouterHelper::build_link(
								[
									'coupons',
									'index',
								]
							);
						}
					}
					break;
			}

			return $menus;
		}

		public function enable_multiple_items_checkout( $can ) {
			$can = true;

			return $can;
		}

		public function add_cart_restrictions_settings() {
			?>
			<div class="sub-section-row">
				<div class="sub-section-label">
					<h3><?php _e( 'Cart Settings', 'latepoint-pro-features' ) ?></h3>
				</div>
				<div class="sub-section-content">
					<?php echo OsFormHelper::toggler_field( 'settings[disable_checkout_multiple_items]', __( 'Disable Shopping Cart Functionality', 'latepoint-pro-features' ), OsSettingsHelper::is_on( 'disable_checkout_multiple_items' ), false, false, [ 'sub_label' => __( 'This will disable ability to book multiple services in one order', 'latepoint-pro-features' ) ] ); ?>
					<?php echo OsFormHelper::toggler_field( 'settings[reset_presets_when_adding_new_item]', __( 'Reset Presets When Adding New Item', 'latepoint-pro-features' ), OsSettingsHelper::is_on( 'reset_presets_when_adding_new_item' ), false, false, [ 'sub_label' => __( 'This will reset presets settings when adding new item', 'latepoint-pro-features' ) ] ); ?>
				</div>
			</div>
			<?php
		}


		public function add_assets_settings() {
			?>
			<div class="sub-section-row">
				<div class="sub-section-label">
					<h3><?php _e( 'Assets Settings', 'latepoint-pro-features' ) ?></h3>
				</div>
				<div class="sub-section-content">
					<?php echo OsFormHelper::toggler_field( 'settings[enable_assets_functionality]', __( 'Enable Assets Functionality', 'latepoint-pro-features' ), OsSettingsHelper::is_on( 'enable_assets_functionality' ), false, false, [ 'sub_label' => __( 'Enable to use assets for managing booking availability. When disabled, assets will not affect booking availability', 'latepoint-pro-features' ) ] ); ?>
				</div>
			</div>
			<?php
		}


		public function load_plugin_textdomain() {
			load_plugin_textdomain( 'latepoint-pro-features', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
		}

		public function on_deactivate() {
			wp_clear_scheduled_hook( 'latepoint_process_scheduled_jobs' );
			OsSettingsHelper::remove_setting_by_name( 'white_label_hide_menu' );
			do_action( 'latepoint_on_addon_deactivate', $this->addon_name, $this->version );
		}


		public function on_activate() {


			// deactivate old addons that are replaced by a PRO addon
			$plugins_to_deactivate = [
				'latepoint-custom-fields/latepoint-custom-fields.php',
				'latepoint-locations/latepoint-locations.php',
				'latepoint-webhooks/latepoint-webhooks.php',
				'latepoint-qr-code/latepoint-qr-code.php',
				'latepoint-reminders/latepoint-reminders.php',
				'latepoint-role-manager/latepoint-role-manager.php',
				'latepoint-timezone-selector/latepoint-timezone-selector.php',
				'latepoint-group-bookings/latepoint-group-bookings.php',
				'latepoint-taxes/latepoint-taxes.php',
				'latepoint-service-durations/latepoint-service-durations.php',
				'latepoint-service-extras/latepoint-service-extras.php',
				'latepoint-messages/latepoint-messages.php',
				'latepoint-coupons/latepoint-coupons.php',
				'latepoint-zoom/latepoint-zoom.php',
			];

			foreach ( $plugins_to_deactivate as $plugin ) {
				if ( is_plugin_active( $plugin ) ) {
					deactivate_plugins( $plugin );
				}
			}

			OsSettingsHelper::remove_setting_by_name( 'white_label_hide_menu' );
			do_action( 'latepoint_on_addon_activate', $this->addon_name, $this->version );

			if ( ! wp_next_scheduled( 'latepoint_process_scheduled_jobs' ) ) {
				wp_schedule_event( time(), 'latepoint_5_minutes', 'latepoint_process_scheduled_jobs' );
			}
		}

		public function register_addon( $installed_addons ) {
			$installed_addons[] = [
				'name'       => $this->addon_name,
				'db_version' => $this->db_version,
				'version'    => $this->version,
			];

			return $installed_addons;
		}

		public function db_sqls( $sqls ) {
			global $wpdb;

			$charset_collate = $wpdb->get_charset_collate();

			/* Messages */
			$sqls[] = 'CREATE TABLE ' . LATEPOINT_TABLE_MESSAGES . " (
      id mediumint(9) NOT NULL AUTO_INCREMENT,
      content text NOT NULL,
      content_type varchar(20) NOT NULL,
      author_id mediumint(9) NOT NULL,
      booking_id mediumint(9) NOT NULL,
      author_type varchar(20) NOT NULL,
      is_hidden boolean,
      is_read boolean,
      created_at datetime,
      updated_at datetime,
      KEY content_type_index (content_type),
      KEY author_id_index (author_id),
      KEY booking_id_index (booking_id),
      KEY author_type_index (author_type),
      PRIMARY KEY  (id)
    ) $charset_collate;";


			/* Service Extras */
			$sqls[] = 'CREATE TABLE ' . LATEPOINT_TABLE_SERVICE_EXTRAS . " (
      id mediumint(9) NOT NULL AUTO_INCREMENT,
      name varchar(255) NOT NULL,
      short_description text,
      charge_amount decimal(20,4),
      duration int(11) NOT NULL,
      maximum_quantity int(3),
      selection_image_id int(11),
      description_image_id int(11),
      multiplied_by_attendees varchar(10),
      status varchar(20) NOT NULL,
      created_at datetime,
      updated_at datetime,
      KEY status_index (status),
      PRIMARY KEY  (id)
    ) $charset_collate;";

			$sqls[] = 'CREATE TABLE ' . LATEPOINT_TABLE_SERVICES_SERVICE_EXTRAS . " (
      id mediumint(9) NOT NULL AUTO_INCREMENT,
      service_id int(11) NOT NULL,
      service_extra_id int(11) NOT NULL,
      created_at datetime,
      updated_at datetime,
      KEY service_id_index (service_id),
      KEY service_extra_id_index (service_extra_id),
      PRIMARY KEY  (id)
    ) $charset_collate;";

			$sqls[] = 'CREATE TABLE ' . LATEPOINT_TABLE_BOOKINGS_SERVICE_EXTRAS . " (
      id mediumint(9) NOT NULL AUTO_INCREMENT,
      booking_id int(11) NOT NULL,
      service_extra_id int(11) NOT NULL,
      duration int(11) NOT NULL,
      quantity int(3) NOT NULL,
      price decimal(20,4),
      created_at datetime,
      updated_at datetime,
      KEY booking_id_index (booking_id),
      KEY service_extra_id_index (service_extra_id),
      PRIMARY KEY  (id)
    ) $charset_collate;";

			/* Assets */
			$sqls[] = 'CREATE TABLE ' . LATEPOINT_TABLE_ASSETS . " (
      id mediumint(9) NOT NULL AUTO_INCREMENT,
      name varchar(255) NOT NULL,
      short_description text,
      quantity int(3) NOT NULL DEFAULT 1,
      status varchar(20) NOT NULL DEFAULT 'active',
      created_at datetime,
      updated_at datetime,
      KEY status_index (status),
      PRIMARY KEY  (id)
    ) $charset_collate;";

			$sqls[] = 'CREATE TABLE ' . LATEPOINT_TABLE_SERVICES_ASSETS . " (
      id mediumint(9) NOT NULL AUTO_INCREMENT,
      service_id int(11) NOT NULL,
      asset_id int(11) NOT NULL,
      created_at datetime,
      updated_at datetime,
      KEY service_id_index (service_id),
      KEY asset_id_index (asset_id),
      PRIMARY KEY  (id)
    ) $charset_collate;";

			$sqls[] = 'CREATE TABLE ' . LATEPOINT_TABLE_BOOKINGS_ASSETS . " (
      id mediumint(9) NOT NULL AUTO_INCREMENT,
      booking_id int(11) NOT NULL,
      asset_id int(11) NOT NULL,
      created_at datetime,
      updated_at datetime,
      KEY booking_id_index (booking_id),
      KEY asset_id_index (asset_id),
      PRIMARY KEY  (id)
    ) $charset_collate;";

			$sqls[] = 'CREATE TABLE ' . LATEPOINT_TABLE_AGENTS_ASSETS . " (
      id mediumint(9) NOT NULL AUTO_INCREMENT,
      agent_id int(11) NOT NULL,
      asset_id int(11) NOT NULL,
      created_at datetime,
      updated_at datetime,
      KEY agent_id_index (agent_id),
      KEY asset_id_index (asset_id),
      PRIMARY KEY  (id)
    ) $charset_collate;";

			/* Coupons */
			$sqls[] = 'CREATE TABLE ' . LATEPOINT_TABLE_COUPONS . " (
      id mediumint(9) NOT NULL AUTO_INCREMENT,
      code varchar(110) NOT NULL,
      name varchar(110),
      discount_type varchar(110),
      discount_value decimal(20,4),
      description text,
      rules text,
      status varchar(20) NOT NULL,
      active_from date,
      active_to date,
      created_at datetime,
      updated_at datetime,
      UNIQUE KEY code_index (code),
      PRIMARY KEY  (id)
    ) $charset_collate;";


			return $sqls;
		}


		public function localized_vars_for_admin( $localized_vars ) {
			/* Custom Fields */
			$localized_vars['google_places_country_restriction']           = OsSettingsHelper::get_settings_value( 'google_places_country_restriction', '' );
			$localized_vars['custom_fields_remove_file_prompt']            = __( 'Are you sure you want to remove this file?', 'latepoint-pro-features' );
			$localized_vars['custom_fields_remove_required_file_prompt']   = __( 'This file is required and cannot be removed, but you can replace it with a different file. Do you want to replace it?', 'latepoint-pro-features' );
			$localized_vars['custom_field_default_value_field_html_route'] = OsRouterHelper::build_route_name( 'custom_fields', 'default_value_field' );
			$localized_vars['custom_field_types_with_default_value']       = json_encode( OsCustomFieldsHelper::get_custom_field_types_with_default_value() );

			return $localized_vars;
		}



		public function localized_vars_for_front( $localized_vars ) {
			/* Custom Fields */
			$localized_vars['google_places_country_restriction']         = OsSettingsHelper::get_settings_value( 'google_places_country_restriction', '' );
			$localized_vars['custom_fields_remove_file_prompt']          = __( 'Are you sure you want to remove this file?', 'latepoint-pro-features' );
			$localized_vars['custom_fields_remove_required_file_prompt'] = __( 'This file is required and cannot be removed, but you can replace it with a different file. Do you want to replace it?', 'latepoint-pro-features' );

			$localized_vars['social_login_google_client_id']    = OsSettingsHelper::get_settings_value( 'google_client_id' );
			$localized_vars['social_login_google_route']        = OsRouterHelper::build_route_name( 'auth', 'login_customer_using_google_token' );
			$localized_vars['recurring_bookings_preview_route'] = OsRouterHelper::build_route_name( 'recurring_bookings', 'recurring_bookings_preview' );

			$localized_vars['social_login_facebook_app_id'] = ( OsSettingsHelper::is_using_facebook_login() && ! empty( OsSettingsHelper::get_settings_value( 'facebook_app_id' ) ) ) ? OsSettingsHelper::get_settings_value( 'facebook_app_id' ) : false;
			$localized_vars['social_login_nonce']           = wp_create_nonce( 'social_login_nonce' );

			$localized_vars['pick_datetime_on_calendar_route'] = OsRouterHelper::build_route_name( 'recurring_bookings', 'pick_datetime_on_calendar' );
			$localized_vars['change_timezone_route']           = OsRouterHelper::build_route_name( 'timezone_selector', 'change_timezone' );

			// Enable Cloudflare Turnstile if settings are properly set
			$is_turnstile_enabled = OsSettingsHelper::is_on( 'enable_cloudflare_turnstile' );
			$turnstile_site_key   = OsSettingsHelper::get_settings_value( 'cloudflare_turnstile_site_key' );
			$turnstile_secret_key = OsSettingsHelper::get_settings_value( 'cloudflare_turnstile_secret_key' );
			if ( $is_turnstile_enabled && ! empty( $turnstile_site_key ) && ! empty( $turnstile_secret_key ) ) {
				$localized_vars['enable_cloudflare_turnstile']   = LATEPOINT_VALUE_ON;
				$localized_vars['cloudflare_turnstile_site_key'] = $turnstile_site_key;
			}

			return $localized_vars;
		}

		public function load_front_scripts_and_styles() {
			// Stylesheets
			wp_enqueue_style( 'latepoint-pro-features-front', $this->public_stylesheets() . 'latepoint-pro-features-front.css', false, $this->version );

			// Javascripts
			wp_enqueue_script( 'latepoint-pro-features-front', $this->public_javascripts() . 'latepoint-pro-features-front.js', array( 'jquery' ), $this->version );

			// Google Places API
			if ( ! empty( OsSettingsHelper::get_settings_value( 'google_places_api_key' ) ) ) {
				wp_enqueue_script(
					'google-places-api',
					OsCustomFieldsHelper::get_google_places_api_url(),
					false,
					null,
					[
						'strategy'  => 'async',
						'in_footer' => true,
					]
				);
			}

			if ( OsSettingsHelper::is_on( 'enable_cloudflare_turnstile' ) ) {
				wp_enqueue_script(
					'cf-turnstile',
					'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit',
					array(),
					null,
					[
						'strategy'  => 'async',
						'in_footer' => true,
					]
				);
			}

			// Google Login
			if ( OsSettingsHelper::is_using_google_login() ) {
				wp_enqueue_script( 'google-gsi-client', 'https://accounts.google.com/gsi/client', false, null );
			}
		}

		public function load_admin_scripts_and_styles( $localized_vars ) {

			// Stylesheets
			wp_enqueue_style( 'latepoint-pro-features-admin', $this->public_stylesheets() . 'latepoint-pro-features-admin.css', false, $this->version );

			// Javascripts
			wp_enqueue_script( 'latepoint-pro-features-admin', $this->public_javascripts() . 'latepoint-pro-features-admin.js', array( 'jquery' ), $this->version );

			if ( ! empty( OsSettingsHelper::get_settings_value( 'google_places_api_key' ) ) ) {
				wp_enqueue_script(
					'google-places-api',
					OsCustomFieldsHelper::get_google_places_api_url(),
					false,
					null,
					[
						'strategy'  => 'async',
						'in_footer' => true,
					]
				);
			}
		}

		public function enqueue_block_editor_service_display_mode(): void {
			wp_enqueue_script(
				'latepoint-pro-service-display-mode-block-editor',
				plugin_dir_url( __FILE__ ) . 'lib/assets/javascripts/service-display-mode-block-editor.js',
				[ 'wp-hooks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-i18n' ],
				$this->version,
				true
			);
			wp_set_script_translations( 'latepoint-pro-service-display-mode-block-editor', 'latepoint-pro-features' );
		}
	}

endif;


if ( in_array( 'latepoint/latepoint.php', get_option( 'active_plugins', array() ) ) || array_key_exists( 'latepoint/latepoint.php', get_site_option( 'active_sitewide_plugins', array() ) ) ) {
	$LATEPOINT_ADDON_PRO_FEATURES = new LatePointAddonProFeatures();
} else {
	function latepoint_not_installed_activated() {
		$screen = get_current_screen();
		if ( isset( $screen->parent_file ) && 'plugins.php' === $screen->parent_file && 'update' === $screen->id ) {
			return;
		}
		if ( ! ( current_user_can( 'activate_plugins' ) && current_user_can( 'install_plugins' ) ) ) {
			return;
		}
		$latepoint_plugin_path = 'latepoint/latepoint.php';
		if ( file_exists( WP_PLUGIN_DIR . '/latepoint/latepoint.php' ) ) {

			$action_url   = wp_nonce_url( 'plugins.php?action=activate&amp;plugin=' . $latepoint_plugin_path . '&amp;plugin_status=all&amp;paged=1&amp;s', 'activate-plugin_' . $latepoint_plugin_path );
			$button_label = __( 'Activate LatePoint', 'latepoint-pro-features' );

		} else {
			$action_url   = wp_nonce_url( self_admin_url( 'update.php?action=install-plugin&plugin=latepoint' ), 'install-plugin_latepoint' );
			$button_label = __( 'Install LatePoint', 'latepoint-pro-features' );
		}
		$button = '<p><a href="' . esc_url( $action_url ) . '" class="button-primary">' . esc_html( $button_label ) . '</a></p><p></p>';

		$message = sprintf( __( '%1$sPRO Features for LatePoint%2$s plugin requires %1$sLatePoint%2$s core plugin installed & activated.', 'latepoint-pro-features' ), '<strong>', '</strong>' );
		$class   = 'notice notice-error';

		printf( '<div class="%1$s"><p>%2$s</p>%3$s</div>', esc_attr( $class ), wp_kses_post( $message ), wp_kses_post( $button ) );
	}
	add_action( 'admin_notices', 'latepoint_not_installed_activated' );
	add_action( 'network_admin_notices', 'latepoint_not_installed_activated' );
}


$latepoint_session_salt = 'NjM3NzBhZGUtMjVmMC00YTIzLWExM2MtNWVlNzdlZjI3NTEy';
