<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class OsAnalyticsHelper {

	/**
	 * BSF Analytics Events instance.
	 *
	 * @var BSF_Analytics_Events|null
	 */
	private static $events = null;

	/**
	 * Initialize BSF Analytics.
	 *
	 * @return void
	 */
	public static function init() {

		add_action( 'latepoint_settings_updated', array( self::class, 'update_contribute_option' ) );

		if ( ! class_exists( 'BSF_Analytics_Loader' ) ) {
			require_once LATEPOINT_ABSPATH . 'lib/kit/bsf-analytics/class-bsf-analytics-loader.php';
		}

		if ( ! class_exists( 'BSF_Admin_Notices' ) ) {
			require_once LATEPOINT_ABSPATH . 'lib/kit/astra-notices/class-bsf-admin-notices.php';
		}

		$bsf_analytics = \BSF_Analytics_Loader::get_instance();

		$bsf_analytics->set_entity(
			[
				'latepoint' => [
					'product_name'        => 'LatePoint',
					'path'                => LATEPOINT_ABSPATH . 'lib/kit/bsf-analytics',
					'author'              => 'LatePoint',
					'time_to_display'     => '+24 hours',
					'hide_optin_checkbox' => true,
					'deactivation_survey' => apply_filters(
						'latepoint_deactivation_survey_data',
						[
							[
								'id'                => 'deactivation-survey-latepoint',
								'popup_logo'        => LATEPOINT_IMAGES_URL . 'logo.svg',
								'plugin_slug'       => 'latepoint',
								'popup_title'       => 'Quick Feedback',
								'support_url'       => 'https://latepoint.com/support/',
								'popup_description' => 'If you have a moment, please share why you are deactivating LatePoint:',
								'show_on_screens'   => [ 'plugins' ],
								'plugin_version'    => LATEPOINT_VERSION,
							],
						]
					),
				],
			]
		);

		add_filter( 'bsf_core_stats', [ __CLASS__, 'add_latepoint_analytics_data' ] );

		// Initialize events instance.
		self::events();

		// Plugin activated (dedup ensures).
		$referer_key   = defined( 'BSF_UTM_ANALYTICS_REFERER' ) ? BSF_UTM_ANALYTICS_REFERER : 'bsf_product_referers';
		$bsf_referrers = get_option( $referer_key, array() );
		$source        = ! empty( $bsf_referrers['latepoint'] ) ? $bsf_referrers['latepoint'] : 'self';
		self::events()->track( 'plugin_activated', LATEPOINT_VERSION, [ 'source' => $source ] );

		// Plugin updated. Fires once per version change via OsUpdateHelper.
		add_action( 'latepoint_update_after', [ __CLASS__, 'on_plugin_updated' ] );
		add_action( 'latepoint_update_after', [ __CLASS__, 'on_plugin_updated_payment_state' ] );

		// Event hooks.
		add_action( 'latepoint_onboarding_started', [ __CLASS__, 'on_onboarding_started' ] );
		add_action( 'latepoint_onboarding_skipped', [ __CLASS__, 'on_onboarding_skipped' ] );
		add_action( 'latepoint_onboarding_completed', [ __CLASS__, 'on_onboarding_completed' ] );
		add_action( 'activated_plugin', [ __CLASS__, 'on_pro_addon_activated' ] );
		add_action( 'latepoint_settings_updated', [ __CLASS__, 'on_payment_processors_connected' ] );
		add_action( 'latepoint_booking_created',   [ __CLASS__, 'on_first_booking_created' ], 15 );
		add_action( 'latepoint_service_saved',     [ __CLASS__, 'on_first_service_created' ], 15, 2 );
		add_action( 'latepoint_agent_saved',       [ __CLASS__, 'on_first_agent_created' ], 15, 2 );
		add_action( 'latepoint_process_created',   [ __CLASS__, 'on_first_process_created' ], 15 );
		add_action( 'latepoint_customer_created',  [ __CLASS__, 'on_first_customer_created' ], 15 );
		add_action( 'latepoint_customer_imported', [ __CLASS__, 'on_first_customer_imported' ], 15 );
	}

	/**
	 * Get the BSF Analytics Events instance, initializing if needed.
	 *
	 * @return BSF_Analytics_Events
	 */
	public static function events() {
		if ( null === self::$events ) {
			if ( ! class_exists( 'BSF_Analytics_Events' ) ) {
				require_once LATEPOINT_ABSPATH . 'lib/kit/bsf-analytics/class-bsf-analytics-events.php';
			}
			self::$events = new \BSF_Analytics_Events( 'latepoint' );
		}
		return self::$events;
	}

	/**
	 * Handle onboarding started event.
	 *
	 * @return void
	 */
	public static function on_onboarding_started() {
		self::events()->track( 'onboarding_started', LATEPOINT_VERSION );
	}

	/**
	 * Handle onboarding skipped event.
	 *
	 * @param string $current_step The step the user was on when they skipped.
	 * @return void
	 */
	public static function on_onboarding_skipped( $current_step ) {
		$analytics       = get_option( 'latepoint_onboarding_analytics', [] );
		$completed_steps = isset( $analytics['completed_steps'] ) && is_array( $analytics['completed_steps'] ) ? $analytics['completed_steps'] : [];

		$props = [
			'current_step'    => $current_step,
			'completed_steps' => implode( ',', $completed_steps ),
			'exited_early'    => 'yes',
		];

		self::events()->track( 'onboarding_skipped', LATEPOINT_VERSION, $props );
	}

	/**
	 * Handle onboarding completion event.
	 *
	 * @return void
	 */
	public static function on_onboarding_completed() {
		$analytics       = get_option( 'latepoint_onboarding_analytics', [] );
		$completed_steps = isset( $analytics['completed_steps'] ) && is_array( $analytics['completed_steps'] ) ? $analytics['completed_steps'] : [];

		$props = [
			'completed_steps' => implode( ',', $completed_steps ),
		];

		self::events()->track( 'onboarding_completed', LATEPOINT_VERSION, $props );
	}

	/**
	 * Handle pro addon activation event.
	 *
	 * @param string $plugin Plugin basename.
	 * @return void
	 */
	public static function on_pro_addon_activated( $plugin ) {
		if ( 'latepoint-pro-features/latepoint-pro-features.php' === $plugin ) {
			$version = defined( 'LATEPOINT_ADDON_PRO_VERSION' ) ? LATEPOINT_ADDON_PRO_VERSION : 'unknown';
			self::events()->track( 'pro_addon_activated', $version );
		}
	}

	/**
	 * Track first_booking_created event. Called via latepoint_booking_created hook.
	 *
	 * Sets a WP option on the 0 → 1 booking transition so the payment-gateway
	 * nudge knows when to show. The BSF analytics dedup ensures the event is
	 * transmitted only once even if the hook fires multiple times (e.g. recurring
	 * bookings in a single checkout).
	 *
	 * @return void
	 */
	public static function on_first_booking_created() {
		if ( get_option( 'latepoint_first_booking_created' ) ) {
			return;
		}
		update_option( 'latepoint_first_booking_created', LATEPOINT_VERSION, false );
		self::events()->track( 'first_booking_created', LATEPOINT_VERSION );
	}

	/**
	 * Track first_service_created event. Called via latepoint_service_saved hook.
	 *
	 * @param OsServiceModel $service       The saved service model instance.
	 * @param bool           $is_new_record Whether this is a new record or an update.
	 * @return void
	 */
	public static function on_first_service_created( $service, $is_new_record ) {
		if ( ! $is_new_record || get_option( 'latepoint_first_service_created' ) ) {
			return;
		}
		update_option( 'latepoint_first_service_created', LATEPOINT_VERSION, false );
		self::events()->track( 'first_service_created', LATEPOINT_VERSION );
	}

	/**
	 * Track first_agent_created event. Called via latepoint_agent_saved hook.
	 *
	 * @param OsAgentModel $agent         The saved agent model instance.
	 * @param bool         $is_new_record Whether this is a new record or an update.
	 * @return void
	 */
	public static function on_first_agent_created( $agent, $is_new_record ) {
		if ( ! $is_new_record || get_option( 'latepoint_first_agent_created' ) ) {
			return;
		}
		update_option( 'latepoint_first_agent_created', LATEPOINT_VERSION, false );
		self::events()->track( 'first_agent_created', LATEPOINT_VERSION );
	}

	/**
	 * Track first_process_created event. Called via latepoint_process_created hook.
	 *
	 * @return void
	 */
	public static function on_first_process_created() {
		if ( get_option( 'latepoint_first_process_created' ) ) {
			return;
		}
		update_option( 'latepoint_first_process_created', LATEPOINT_VERSION, false );
		self::events()->track( 'first_process_created', LATEPOINT_VERSION );
	}

	/**
	 * Track first_customer_created event. Called via latepoint_customer_created hook.
	 *
	 * Fires from multiple call sites (admin, front-end booking flow, auth).
	 * The option guard ensures the event is transmitted only once.
	 *
	 * @return void
	 */
	public static function on_first_customer_created() {
		if ( get_option( 'latepoint_first_customer_created' ) ) {
			return;
		}
		update_option( 'latepoint_first_customer_created', LATEPOINT_VERSION, false );
		self::events()->track( 'first_customer_created', LATEPOINT_VERSION );
	}

	/**
	 * Track first_customer_imported event. Called via latepoint_customer_imported hook.
	 *
	 * CSV import implies rows exist; no count check needed.
	 *
	 * @return void
	 */
	public static function on_first_customer_imported() {
		if ( get_option( 'latepoint_first_customer_imported' ) ) {
			return;
		}
		update_option( 'latepoint_first_customer_imported', LATEPOINT_VERSION, false );
		self::events()->track( 'first_customer_imported', LATEPOINT_VERSION );
	}

	/**
	 * Backfill a milestone event for existing installs during plugin update.
	 *
	 * If the guard option is not yet set and the entity table already has rows,
	 * sets the guard to `migrated_{version}` and queues the event with
	 * `source=migration` so analytics can distinguish the one-time baseline from
	 * live adoption.
	 *
	 * @param string  $option_name WP option name used as the dedup guard.
	 * @param OsModel $model       Fresh model instance used to check if any rows exist (LIMIT 1).
	 * @param string  $event_name  BSF Analytics event name to track.
	 * @return void
	 */
	private static function maybe_backfill_milestone( $option_name, $model, $event_name ) {
		if ( get_option( $option_name ) ) {
			return;
		}
		$row = $model->select( 'id' )->set_limit( 2 )->get_results();
		if ( ! empty( $row ) ) {
			update_option( $option_name, 'migrated_' . LATEPOINT_VERSION, false );
			self::events()->track( $event_name, LATEPOINT_VERSION, [ 'source' => 'migration' ] );
		}
	}

	/**
	 * Track plugin_updated event. Called via latepoint_update_after hook.
	 *
	 * @param string $old_version The version before the update.
	 * @return void
	 */
	public static function on_plugin_updated( $old_version ) {
		self::events()->track(
			'plugin_updated',
			LATEPOINT_VERSION,
			[
				'from_version' => $old_version,
			],
			true
		);

		// Backfill milestones for sites that existed before this feature shipped.
		self::maybe_backfill_milestone( 'latepoint_first_booking_created', new OsBookingModel(), 'first_booking_created' );
		self::maybe_backfill_milestone( 'latepoint_first_service_created', new OsServiceModel(), 'first_service_created' );
		self::maybe_backfill_milestone( 'latepoint_first_agent_created', new OsAgentModel(), 'first_agent_created' );
		self::maybe_backfill_milestone( 'latepoint_first_process_created', new OsProcessModel(), 'first_process_created' );
		self::maybe_backfill_milestone( 'latepoint_first_customer_created', new OsCustomerModel(), 'first_customer_created' );
	}

	/**
	 * Capture current payment processor state on plugin update.
	 * Reads from DB settings, not from a form submission.
	 *
	 * @return void
	 */
	public static function on_plugin_updated_payment_state() {
		if ( ! class_exists( 'OsPaymentsHelper' ) && ! class_exists( 'OsSettingsHelper' ) ) {
			return;
		}

		$env = OsSettingsHelper::get_payments_environment();

		$processors = OsPaymentsHelper::get_payment_processors();
		foreach ( $processors as $processor ) {
			$code = $processor['code'] ?? '';
			if ( ! empty( $code ) && OsPaymentsHelper::is_payment_processor_enabled( $code ) ) {
				self::events()->track( $code . '_payment_enabled', $env, [], true );
			}
		}

		if ( OsPaymentsHelper::is_local_payments_enabled() ) {
			self::events()->track( 'local_payment_enabled', $env, [], true );
		}
	}

	/**
	 * Handle payment processors connected event.
	 *
	 * Records each enabled payment processor as a separate event — future-proof for new processors.
	 *
	 * @param array<mixed> $settings Settings array.
	 * @return void
	 */
	public static function on_payment_processors_connected( $settings ) {
		if ( ! is_array( $settings ) || ! class_exists( 'OsPaymentsHelper' ) ) {
			return;
		}

		$env = isset( $settings['payments_environment'] ) ? $settings['payments_environment'] : 'dev';

		$processors = OsPaymentsHelper::get_payment_processors();
		foreach ( $processors as $processor ) {
			$code = $processor['code'] ?? '';
			$key  = 'enable_payment_processor_' . $code;
			if ( ! empty( $code ) && isset( $settings[ $key ] ) && 'on' === $settings[ $key ] ) {
				self::events()->track( $code . '_payment_enabled', $env, [], true );
			}
		}

		if ( isset( $settings['enable_payments_local'] ) && 'on' === $settings['enable_payments_local'] ) {
			self::events()->track( 'local_payment_enabled', $env, [], true );
		}
	}

	/**
	 * Toggle contribute to latepoint from general settings.
	 *
	 * @param array<mixed> $settings settings array.
	 * @return bool
	 */
	public static function update_contribute_option( $settings ) {
		if ( isset( $settings['contribute_to_latepoint'] ) ) {

			$enable_contribute = 'on' === $settings['contribute_to_latepoint'] ? 'yes' : 'no';

			return update_option( 'latepoint_usage_optin', $enable_contribute );
		}
	}

	/**
	 * Add LatePoint specific analytics data.
	 *
	 * @param array $stats_data Existing stats data.
	 * @return array
	 */
	public static function add_latepoint_analytics_data( $stats_data ) {
		$stats_data['plugin_data']['latepoint'] = [
			'free_version'  => LATEPOINT_VERSION,
			'db_version'    => LATEPOINT_DB_VERSION,
			'site_language' => get_locale(),
		];

		$stats_data['plugin_data']['latepoint']['numeric_values'] = [
			'total_bookings'  => self::get_table_count( LATEPOINT_TABLE_BOOKINGS ),
			'total_orders'    => self::get_table_count( LATEPOINT_TABLE_ORDERS ),
			'total_services'  => self::get_table_count( LATEPOINT_TABLE_SERVICES ),
			'total_agents'    => self::get_table_count( LATEPOINT_TABLE_AGENTS ),
			'total_customers' => self::get_table_count( LATEPOINT_TABLE_CUSTOMERS ),
			'total_locations' => self::get_table_count( LATEPOINT_TABLE_LOCATIONS ),
			'total_processes' => self::get_table_count( LATEPOINT_TABLE_PROCESSES ),
		];

		// Add KPI tracking data.
		$kpi_data = self::get_kpi_tracking_data();
		if ( ! empty( $kpi_data ) ) {
			$stats_data['plugin_data']['latepoint']['kpi_records'] = $kpi_data;
		}

		// Flush pending events into payload.
		$pending_events = self::events()->flush_pending();
		if ( ! empty( $pending_events ) ) {
			$stats_data['plugin_data']['latepoint']['events_record'] = $pending_events;
		}

		return $stats_data;
	}


	/**
	 * Get KPI tracking data for the last 2 days (excluding today).
	 *
	 * @return array KPI data organized by date.
	 */
	private static function get_kpi_tracking_data() {
		$kpi_data = [];
		$today    = current_time( 'Y-m-d' );

		for ( $i = 1; $i <= 2; $i++ ) {
			$date = gmdate( 'Y-m-d', strtotime( $today . ' -' . $i . ' days' ) );

			$kpi_data[ $date ] = [
				'numeric_values' => [
					'bookings'  => self::get_daily_count( LATEPOINT_TABLE_BOOKINGS, $date ),
					'orders'    => self::get_daily_count( LATEPOINT_TABLE_ORDERS, $date ),
					'services'  => self::get_daily_count( LATEPOINT_TABLE_SERVICES, $date ),
					'customers' => self::get_daily_count( LATEPOINT_TABLE_CUSTOMERS, $date ),
				],
			];
		}

		return $kpi_data;
	}

	/**
	 * Get count of rows created on a specific date.
	 *
	 * @param string $table_name Full table name.
	 * @param string $date Date in Y-m-d format.
	 * @return int
	 */
	private static function get_daily_count( $table_name, $date ) {
		global $wpdb;

		$start_date = $date . ' 00:00:00';
		$end_date   = $date . ' 23:59:59';

		$count = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE created_at >= %s AND created_at <= %s',
				$table_name,
				$start_date,
				$end_date
			)
		);

		return $count ? (int) $count : 0;
	}

	/**
	 * Get total row count from a table.
	 *
	 * @param string $table_name Full table name.
	 * @return int
	 */
	private static function get_table_count( $table_name ) {
		global $wpdb;
		$count = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table_name ) );
		return $count ? (int) $count : 0;
	}
}
