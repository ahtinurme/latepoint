<?php
/**
 * Plugin Name: LatePoint Addon - Apple Calendar
 * Plugin URI:  https://latepoint.com/
 * Description: LatePoint addon for Apple Calendar integration
 * Version:     1.0.0
 * Author:      LatePoint
 * Author URI:  https://latepoint.com/
 * Text Domain: latepoint-apple-calendar
 * Domain Path: /languages
 *
 * @package LatePoint
 * @subpackage LatePoint_Apple_Calendar
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// If no LatePoint class exists - exit, because LatePoint plugin is required for this addon.

if ( ! class_exists( 'LatePointAddonAppleCalendar' ) ) {

	/**
	 * Main Addon Class.
	 */
	class LatePointAddonAppleCalendar {
		/**
		 * Addon version.
		 *
		 * @since 1.0.0
		 * @var string
		 */
		public $version = '1.0.0';

		/**
		 * Database version.
		 *
		 * @since 1.0.0
		 * @var string
		 */
		public $db_version = '1.0.0';

		/**
		 * Addon name/slug.
		 *
		 * @since 1.0.0
		 * @var string
		 */
		public $addon_name = 'latepoint-apple-calendar';

		/**
		 * LatePoint Constructor.
		 *
		 * @since 1.0.0
		 */
		public function __construct() {
			$this->define_constants();
			$this->init_hooks();
		}

		/**
		 * Define LatePoint Constants.
		 *
		 * @since 1.0.0
		 */
		public function define_constants() {
			global $wpdb;

			// Database table names.
			$this->define( 'LATEPOINT_TABLE_APPLE_CALENDAR_EVENTS', $wpdb->prefix . 'latepoint_apple_calendar_events' );
			$this->define( 'LATEPOINT_TABLE_APPLE_CALENDAR_RECURRENCES', $wpdb->prefix . 'latepoint_apple_calendar_recurrences' );
		}

		/**
		 * Public_stylesheets
		 *
		 * @since 1.0.0
		 */
		public static function public_stylesheets() {
			return plugin_dir_url( __FILE__ ) . 'public/stylesheets/';
		}

		/**
		 * Public_javascripts
		 *
		 * @since 1.0.0
		 */
		public static function public_javascripts() {
			return plugin_dir_url( __FILE__ ) . 'public/javascripts/';
		}

		/**
		 * Images_url
		 *
		 * @since 1.0.0
		 */
		public static function images_url() {
			return plugin_dir_url( __FILE__ ) . 'public/images/';
		}

		/**
		 * Define constant if not already set.
		 *
		 * @param string $name Constant name.
		 * @param mixed  $value Constant value.
		 *
		 * @since 1.0.0
		 */
		public function define( $name, $value ) {
			if ( ! defined( $name ) ) {
				define( $name, $value );
			}
		}

		/**
		 * Include required core files used in admin and on the frontend.
		 *
		 * @since 1.0.0
		 */
		public function includes() {

			// CONTROLLERS.
			include_once __DIR__ . '/lib/controllers/apple_calendar_controller.php';

			// HELPERS.
			include_once __DIR__ . '/lib/helpers/apple_calendar_helper.php';
			include_once __DIR__ . '/lib/helpers/apple_calendar_caldav_helper.php';
			include_once __DIR__ . '/lib/helpers/apple_calendar_ics_helper.php';

			// MODELS.
			include_once __DIR__ . '/lib/models/apple_calendar_event_model.php';
			include_once __DIR__ . '/lib/models/apple_calendar_event_recurrence_model.php';
		}

		/**
		 * Init_hooks
		 *
		 * @since 1.0.0
		 */
		public function init_hooks() {
			register_activation_hook( __FILE__, array( $this, 'on_activate' ) );
			register_deactivation_hook( __FILE__, array( $this, 'on_deactivate' ) );

			// init the addon.
			add_action( 'init', array( $this, 'init' ), 0 );

			// Hook into the latepoint initialization action and initialize this addon.
			add_action( 'latepoint_init', array( $this, 'latepoint_init' ) );

			// Include additional helpers and controllers.
			add_action( 'latepoint_includes', array( $this, 'includes' ) );

			// Modify a list of installed add-ons.
			add_filter( 'latepoint_installed_addons', array( $this, 'register_addon' ) );

			// Add database tables SQL.
			add_filter( 'latepoint_addons_sqls', array( $this, 'db_sqls' ) );

			// Capability hooks.
			add_filter( 'latepoint_capabilities_for_controllers', array( $this, 'set_capabilities_for_apple_calendar_controller' ) );

			// Load scripts and styles.
			add_action( 'latepoint_wp_enqueue_scripts', array( $this, 'load_front_scripts_and_styles' ) );
			add_action( 'latepoint_admin_enqueue_scripts', array( $this, 'load_admin_scripts_and_styles' ) );

			// Settings hooks: Integrations > Calendars.
			add_filter( 'latepoint_list_of_external_calendars', array( $this, 'add_to_list_of_external_calendars' ), 10, 3 );
			add_action( 'latepoint_external_calendar_settings', array( $this, 'output_calendar_settings' ) );

			// Agent form integration: Agents > Edit Agent.
			add_filter( 'latepoint_agent_edit_form_sticky_section_items', array( $this, 'add_sticky_menu_to_agent_settings' ) );
			add_action( 'latepoint_agent_form', array( $this, 'agent_form_apple_calendar' ) );
			add_action( 'latepoint_agent_saved', array( $this, 'process_agent_save' ), 10, 3 );

			// Display Apple Calendar icon on agent cards.
			add_action( 'latepoint_after_agent_info_on_index', array( $this, 'display_apple_connected_for_agent' ) );

			// Service form integration: Services > New/Edit Service.
			add_filter( 'latepoint_service_edit_form_sticky_section_items', array( $this, 'add_sticky_menu_to_service_settings' ) );
			add_action( 'latepoint_service_form_after', array( $this, 'output_apple_calendar_settings_on_service_form' ) );
			add_action( 'latepoint_service_saved', array( $this, 'process_service_save' ), 10, 3 );

			// Booking lifecycle hooks.
			add_action( 'latepoint_booking_created', array( $this, 'process_booking_created' ), 11 );
			add_action( 'latepoint_booking_updated', array( $this, 'process_booking_updated' ), 11, 2 );
			add_action( 'latepoint_booking_will_be_deleted', array( $this, 'process_booking_deleted' ) );

			// Block time slots with Apple Calendar events.
			add_filter( 'latepoint_blocked_periods_for_range', array( $this, 'insert_events_into_blocked_periods' ), 10, 2 );

			// Calendar display hooks.
			add_action( 'latepoint_calendar_daily_timeline', array( $this, 'daily_timeline' ), 10, 2 );
			add_action( 'latepoint_calendar_weekly_timeline', array( $this, 'daily_timeline' ), 10, 2 );
			add_action( 'latepoint_appointments_timeline', array( $this, 'appointments_timeline' ), 10, 2 );

			// Cron hooks for background sync.
			add_filter( 'cron_schedules', array( $this, 'add_cron_schedules' ) );
			add_action( 'latepoint_check_apple_calendar_sync', array( $this, 'sync_apple_calendar_events' ) );
		}

		/**
		 * Loads addon specific javascript and stylesheets for frontend site
		 *
		 * @since 1.0.0
		 */
		public function load_front_scripts_and_styles() {
		}

		/**
		 * Loads addon specific javascript and stylesheets for backend (wp-admin)
		 *
		 * @param array $localized_vars Localized variables (unused).
		 *
		 * @since 1.0.0
		 */
		public function load_admin_scripts_and_styles( $localized_vars ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Keep parameter for future use.
			// Determine if we should use minified versions.
			$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

			// Determine RTL -- is_rtl() will be used in upcoming versions.
			$rtl_suffix = '';

			// Stylesheets.
			wp_enqueue_style(
				'latepoint-apple-calendar',
				$this->public_stylesheets() . 'latepoint-apple-calendar-admin' . $rtl_suffix . $suffix . '.css',
				array(),
				$this->version
			);

			// Javascripts.
			wp_enqueue_script(
				'latepoint-apple-calendar',
				$this->public_javascripts() . 'latepoint-apple-calendar-admin' . $suffix . '.js',
				array( 'jquery' ),
				$this->version,
				true
			);
		}

		/**
		 * Init addon when WordPress Initialises.
		 *
		 * @since 1.0.0
		 */
		public function init() {
			// Set up localisation.
			$this->load_plugin_textdomain();

			// Addon name.
			$this->define( 'LATEPOINT_APPLE_CALENDAR_ADDON_NAME', __( 'Apple Calendar', 'latepoint-apple-calendar' ) );
		}

		/**
		 * Add custom cron schedules
		 *
		 * @param array $schedules Existing schedules.
		 * @return array Modified schedules
		 *
		 * @since 1.0.0
		 */
		public function add_cron_schedules( $schedules ) {
			// 2-hour sync schedule (optimized for sync-token usage).
			if ( ! isset( $schedules['latepoint_two_hours'] ) ) {
				$schedules['latepoint_two_hours'] = array(
					'interval' => 7200, // 2 hours in seconds
					'display'  => __( 'Every 2 Hours', 'latepoint-apple-calendar' ),
				);
			}
			return $schedules;
		}

		/**
		 * Latepoint_init
		 *
		 * @since 1.0.0
		 */
		public function latepoint_init() {
			LatePoint\Cerber\Router::init_addon();
		}

		/**
		 * Set text domain for the addon, for string translations to work
		 *
		 * @since 1.0.0
		 */
		public function load_plugin_textdomain() {
			load_plugin_textdomain( 'latepoint-apple-calendar', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
		}

		/**
		 * On_activate
		 *
		 * @since 1.0.0
		 */
		public function on_activate() {
			do_action( 'latepoint_on_addon_activate', $this->addon_name, $this->version );

			// Enable Apple Calendar by default on first install.
			if ( OsSettingsHelper::get_settings_value( 'enable_apple_calendar', 'disabled' ) === 'disabled' ) {
				OsSettingsHelper::save_setting_by_name( 'enable_apple_calendar', 'on' );
			}

			// Schedule cron job for background sync (every 2 hours with sync-token optimization).
			if ( ! wp_next_scheduled( 'latepoint_check_apple_calendar_sync' ) ) {
				wp_schedule_event( time(), 'latepoint_two_hours', 'latepoint_check_apple_calendar_sync' );
			}
		}

		/**
		 * On_deactivate
		 *
		 * @since 1.0.0
		 */
		public function on_deactivate() {
			// Clear scheduled cron jobs.
			wp_clear_scheduled_hook( 'latepoint_check_apple_calendar_sync' );
		}

		/**
		 * Register_addon
		 *
		 * @param array $installed_addons Existing installed addons.
		 *
		 * @since 1.0.0
		 */
		public function register_addon( $installed_addons ) {
			$installed_addons[] = array(
				'name'       => $this->addon_name,
				'db_version' => $this->db_version,
				'version'    => $this->version,
			);
			return $installed_addons;
		}

		/**
		 * Register database tables SQL for Apple Calendar addon
		 *
		 * @param array $sqls Array of SQL statements.
		 * @return array Modified array of SQL statements
		 *
		 * @since 1.0.0
		 */
		public function db_sqls( $sqls ) {
			global $wpdb;

			$charset_collate = $wpdb->get_charset_collate();

			/**
			 * Apple Calendar Events Table
			 */
			$sqls[] = 'CREATE TABLE ' . LATEPOINT_TABLE_APPLE_CALENDAR_EVENTS . " (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				summary TEXT NULL,
				start_date DATE NOT NULL,
				end_date DATE NULL,
				start_time MEDIUMINT UNSIGNED NOT NULL,
				end_time MEDIUMINT UNSIGNED NULL,
				agent_id BIGINT UNSIGNED NOT NULL,
				apple_calendar_id TEXT NULL,
				apple_event_id TEXT NULL,
				caldav_etag VARCHAR(255) NULL,
				caldav_url TEXT NULL,
				start_datetime_utc DATETIME NULL,
				end_datetime_utc DATETIME NULL,
				created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				KEY idx_start_date (start_date),
				KEY idx_end_date (end_date),
				KEY idx_agent_id (agent_id)
			) {$charset_collate};";

			/**
			 * Apple Calendar Recurrences Table
			 */
			$sqls[] = 'CREATE TABLE ' . LATEPOINT_TABLE_APPLE_CALENDAR_RECURRENCES . " (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				lp_event_id BIGINT UNSIGNED NOT NULL,
				frequency VARCHAR(30) NULL,
				`interval` SMALLINT UNSIGNED NULL,
				`count` SMALLINT UNSIGNED NULL,
				until DATE NULL,
				weekday VARCHAR(30) NULL,
				created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				KEY idx_lp_event_id (lp_event_id),
				KEY idx_frequency (frequency)
			) {$charset_collate};";

			return $sqls;
		}

		/**
		 * Display Apple Calendar connection form on agent edit page
		 *
		 * @param OsAgentModel $agent Agent being edited.
		 *
		 * @since 1.0.0
		 */
		public function agent_form_apple_calendar( $agent ) {
			if ( ! $agent->is_new_record() && OsAppleCalendarHelper::is_enabled() ) {
				require __DIR__ . '/lib/views/apple_calendar/_connection_form.php';
			}
		}

		/**
		 * Display Apple Calendar icon on agent cards for connected agents
		 *
		 * @param OsAgentModel $agent Agent being displayed.
		 *
		 * @since 1.0.0
		 */
		public function display_apple_connected_for_agent( $agent ) {
			if ( OsAppleCalendarHelper::is_enabled() && OsAppleCalendarHelper::is_agent_connected_to_apple_calendar( $agent->id ) ) {
				?>
				<span class="agent-connection-icon">
					<img
						src="<?php echo esc_url( self::images_url() . 'apple-logo.svg' ); ?>"
						title="<?php esc_html_e( 'Connected to Apple Calendar', 'latepoint-apple-calendar' ); ?>"
						alt=""
					/>
				</span>
				<?php
			}
		}

		/**
		 * Process agent save to store Apple Calendar settings
		 * Uses the same logic as save_calendar_selections() controller method
		 *
		 * @param object $agent Agent model object.
		 * @param bool   $is_new_record Whether this is a new record.
		 * @param array  $agent_params Agent parameters from the form.
		 *
		 * @since 1.0.0
		 */
		public function process_agent_save( $agent, $is_new_record, $agent_params ) {
			if ( ! OsAppleCalendarHelper::is_enabled() ) {
				return;
			}

			// Skip if this is a new agent record.
			if ( $is_new_record ) {
				return;
			}

			// Extract calendar selections from agent params.
			$calendar_for_push  = $agent_params['apple_calendar_for_push'] ?? '';
			$calendars_for_pull = $agent_params['apple_calendars_for_pull'] ?? array();

			// Save calendar for push if provided.
			if ( ! empty( $calendar_for_push ) ) {
				OsAppleCalendarHelper::set_selected_calendar_id_for_push( $calendar_for_push, $agent->id );
			}

			// Save calendars for pull if provided.
			if ( ! empty( $calendars_for_pull ) && is_array( $calendars_for_pull ) ) {
				$calendar_ids_string = implode( ',', $calendars_for_pull );
				OsAppleCalendarHelper::set_selected_calendar_ids_for_pull( $calendar_ids_string, $agent->id );
			} else {
				// Clear calendars for pull if empty array or empty value.
				OsAppleCalendarHelper::set_selected_calendar_ids_for_pull( '', $agent->id );
			}
		}

		/**
		 * Process booking creation - sync to Apple Calendar
		 *
		 * @param OsBookingModel $booking Newly created booking.
		 *
		 * @since 1.0.0
		 */
		public function process_booking_created( $booking ) {
			if ( ! OsAppleCalendarHelper::is_enabled() ) {
				return;
			}

			// Only sync if agent is connected.
			if ( ! OsAppleCalendarHelper::is_agent_connected_to_apple_calendar( $booking->agent_id ) ) {
				return;
			}

			// Create event in Apple Calendar.
			OsAppleCalendarHelper::create_or_update_booking_in_apple_calendar( $booking->id );
		}

		/**
		 * Process booking update - sync changes to Apple Calendar
		 *
		 * @param OsBookingModel $booking Updated booking.
		 * @param OsBookingModel $old_booking Booking before update.
		 *
		 * @since 1.0.0
		 */
		public function process_booking_updated( $booking, $old_booking ) {
			if ( ! OsAppleCalendarHelper::is_enabled() ) {
				return;
			}

			// If agent changed, remove from old agent's calendar.
			if ( $old_booking->agent_id !== $booking->agent_id ) {
				if ( OsAppleCalendarHelper::is_agent_connected_to_apple_calendar( $old_booking->agent_id ) ) {
					OsAppleCalendarHelper::delete_booking_from_apple_calendar( $booking->id, $old_booking->agent_id );
				}
			}

			// Update or create in new/current agent's calendar.
			if ( OsAppleCalendarHelper::is_agent_connected_to_apple_calendar( $booking->agent_id ) ) {
				OsAppleCalendarHelper::create_or_update_booking_in_apple_calendar( $booking->id );
			}
		}

		/**
		 * Process booking deletion - remove from Apple Calendar
		 *
		 * @param int $booking_id Booking ID being deleted.
		 *
		 * @since 1.0.0
		 */
		public function process_booking_deleted( $booking_id ) {
			if ( ! OsAppleCalendarHelper::is_enabled() ) {
				return;
			}

			OsAppleCalendarHelper::delete_booking_from_apple_calendar( $booking_id );
		}

		/**
		 * Insert Apple Calendar events as blocked periods
		 *
		 * @param array                  $blocked_periods_arr Existing blocked periods.
		 * @param \LatePoint\Misc\Filter $filter Filter object with date range and agent info.
		 * @return array Modified blocked periods array
		 *
		 * @since 1.0.0
		 */
		public function insert_events_into_blocked_periods( $blocked_periods_arr, $filter ) {
			if ( ! OsAppleCalendarHelper::is_enabled() ) {
				return $blocked_periods_arr;
			}
			if ( ! $filter->date_from || ! $filter->date_to ) {
				return $blocked_periods_arr;
			}
			if ( ! $filter->connections ) {
				return $blocked_periods_arr;
			}

			$date_from_obj = new DateTime( $filter->date_from );
			$date_to_obj   = new DateTime( $filter->date_to );

			// Collect agent IDs from filter.
			$agent_ids = array();
			foreach ( $filter->connections as $connection ) {
				$agent_ids[] = $connection->agent_id;
			}
			if ( $filter->agent_id ) {
				$agent_ids[] = $filter->agent_id;
			}
			$agent_ids = array_unique( $agent_ids );

			// Fetch events for each date in range.
			$end_date_str = $date_to_obj->format( 'Y-m-d' );
			for (
				$day = clone $date_from_obj, $day_str = $day->format( 'Y-m-d' );
				$day_str <= $end_date_str;
				$day->modify( '+1 day' ), $day_str = $day->format( 'Y-m-d' )
			) {
				$events = OsAppleCalendarHelper::get_local_events_for_date( $day_str, $agent_ids );

				if ( $events ) {
					foreach ( $events as $event ) {
						$blocked_periods_arr[ $day_str ][] = new \LatePoint\Misc\BlockedPeriod(
							array(
								'start_time' => $event->start_time,
								'end_time'   => $event->end_time,
								'start_date' => $day_str,
								'end_date'   => $day_str,
								'agent_id'   => $event->agent_id,
							)
						);
					}
				}
			}

			return $blocked_periods_arr;
		}

		/**
		 * Display Apple Calendar events on daily timeline
		 *
		 * @param string $target_date Date to display.
		 * @param array  $args Additional arguments (agent_id, work_start_minutes, work_end_minutes, etc.).
		 *
		 * @since 1.0.0
		 */
		public function daily_timeline( $target_date, $args ) {
			if ( ! OsAppleCalendarHelper::is_enabled() ) {
				return;
			}
			if ( ! isset( $args['agent_id'] ) ) {
				return;
			}

			$events = OsAppleCalendarHelper::get_local_events_for_date( $target_date, array( $args['agent_id'] ) );
			if ( ! $events ) {
				return;
			}

			$work_start_minutes = $args['work_start_minutes'] ?? 0;
			$work_end_minutes   = $args['work_end_minutes'] ?? 1440;

			$hide_event_name = OsSettingsHelper::is_on( 'apple_calendar_hide_event_titles' );

			foreach ( $events as $event ) {
				// Skip events outside work hours.
				if ( $event->start_time >= $work_end_minutes || $event->end_time <= $work_start_minutes ) {
					continue;
				}

				// Calculate position on timeline.
				$event_duration         = min( $event->end_time, $work_end_minutes ) - max( $event->start_time, $work_start_minutes );
				$event_duration_percent = $event_duration * 100 / ( $work_end_minutes - $work_start_minutes );
				$event_start_percent    = ( max( $event->start_time, $work_start_minutes ) - $work_start_minutes ) / ( $work_end_minutes - $work_start_minutes ) * 100;

				if ( $event_start_percent < 0 ) {
					$event_start_percent = 0;
				}
				if ( $event_start_percent >= 100 ) {
					continue;
				}
				?>
			<div class="ch-day-booking apple-calendar-event"
				style="top: <?php echo intval( $event_start_percent ); ?>%; height: <?php echo intval( $event_duration_percent ); ?>%;"
			>
				<div class="ch-day-booking-i">
					<div class="booking-service-name">
						<img src="<?php echo esc_url( $this->images_url() . 'apple-logo.svg' ); ?>" alt="">
						<span><?php echo $hide_event_name ? esc_html__( 'Event from Apple Calendar', 'latepoint-apple-calendar' ) : esc_html( $event->summary ); ?></span>
					</div>
					<div class="booking-time">
						<?php
						if ( OsAppleCalendarHelper::is_full_day_event( $event->start_time, $event->end_time ) ) {
							esc_html_e( 'Full Day', 'latepoint-apple-calendar' );
						} else {
							echo esc_html( OsTimeHelper::minutes_to_hours_and_minutes( $event->start_time ) . ' - ' . OsTimeHelper::minutes_to_hours_and_minutes( $event->end_time ) );
						}
						?>
					</div>
				</div>
			</div>
				<?php
			}
		}

		/**
		 * Display Apple Calendar events on appointments timeline (horizontal view)
		 *
		 * @param string $target_date Date to display.
		 * @param array  $args Additional arguments.
		 *
		 * @since 1.0.0
		 */
		public function appointments_timeline( $target_date, $args ) {
			if ( ! OsAppleCalendarHelper::is_enabled() ) {
				return;
			}

			$agent_id = $args['agent_id'] ?? false;
			$events   = OsAppleCalendarHelper::get_local_events_for_date( $target_date, $agent_id );
			if ( ! $events ) {
				return;
			}

			$work_total_minutes = $args['work_total_minutes'] ?? 0;
			$work_start_minutes = $args['work_start_minutes'] ?? 0;

			foreach ( $events as $event ) {
				if ( ! $work_total_minutes ) {
					continue;
				}

				$width = ( $event->end_time - $event->start_time ) / $work_total_minutes * 100;
				$left  = ( $event->start_time - $work_start_minutes ) / $work_total_minutes * 100;

				if ( $width <= 0 || $left >= 100 || ( $left + $width <= 0 ) ) {
					continue;
				}
				if ( $left < 0 ) {
					$width += $left;
					$left   = 0;
				}
				if ( $left + $width > 100 ) {
					$width = 100 - $left;
				}

				echo '<div class="booking-block apple-calendar-event-booking-block" style="left: ' . intval( $left ) . '%; width: ' . intval( $width ) . '%"><img src="' . esc_url( $this->images_url() . 'apple-logo.svg' ) . '"/></div>';
			}
		}

		/**
		 * Add sticky menu item to agent settings navigation
		 *
		 * @param array $items Existing menu items.
		 * @return array Modified menu items
		 *
		 * @since 1.0.0
		 */
		public function add_sticky_menu_to_agent_settings( $items ) {
			if ( ! OsAppleCalendarHelper::is_enabled() ) {
				return $items;
			}

			$items[] = array(
				'href'  => 'latepointAppleCalendarSetup',
				'label' => LATEPOINT_APPLE_CALENDAR_ADDON_NAME,
			);
			return $items;
		}

		/**
		 * Add sticky menu item to service settings navigation
		 *
		 * @param array $items Existing menu items.
		 * @return array Modified menu items
		 *
		 * @since 1.0.0
		 */
		public function add_sticky_menu_to_service_settings( $items ) {
			if ( ! OsAppleCalendarHelper::is_enabled() ) {
				return $items;
			}

			$items[] = array(
				'href'  => 'stickySectionAppleCalendar',
				'label' => LATEPOINT_APPLE_CALENDAR_ADDON_NAME,
			);
			return $items;
		}

		/**
		 * Output Apple Calendar settings section on service form
		 *
		 * @param object $service Service model object.
		 *
		 * @since 1.0.0
		 */
		public function output_apple_calendar_settings_on_service_form( $service ) {
			if ( ! OsAppleCalendarHelper::is_enabled() ) {
				return $service;
			}

			// Get current values, handling both existing and new services.
			$add_customer = false;
			$add_agent    = false;

			if ( ! empty( $service->id ) ) {
				$add_customer = OsAppleCalendarHelper::should_customer_be_included_as_attendee( $service->id );
				$add_agent    = OsAppleCalendarHelper::should_agent_be_included_as_attendee( $service->id );
			}
			?>
			<div class="white-box section-anchor" id="stickySectionAppleCalendar">
				<div class="white-box-header">
					<div class="os-form-sub-header">
						<h3><?php esc_html_e( 'Apple Calendar Settings', 'latepoint-apple-calendar' ); ?></h3>
					</div>
				</div>
				<div class="white-box-content">
					<?php
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- OsFormHelper::toggler_field() returns escaped HTML.
					echo OsFormHelper::toggler_field( 'service[meta][add_customer_to_apple_event]', __( 'Add customers as attendees in Apple Calendar event', 'latepoint-apple-calendar' ), $add_customer, '', 'large' );
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- OsFormHelper::toggler_field() returns escaped HTML.
					echo OsFormHelper::toggler_field( 'service[meta][add_agent_to_apple_event]', __( 'Add agent as organizer in Apple Calendar event', 'latepoint-apple-calendar' ), $add_agent, '', 'large' );
					?>
				</div>
			</div>
			<?php
		}

		/**
		 * Process service save to store Apple Calendar meta settings
		 *
		 * @param object $service Service model object.
		 * @param bool   $is_new_record Whether this is a new record.
		 * @param array  $service_params Service parameters from the form.
		 *
		 * @since 1.0.0
		 */
		public function process_service_save( $service, $is_new_record, $service_params ) {
			if ( ! isset( $service_params['meta'] ) ) {
				return;
			}

			$params = array( 'add_customer_to_apple_event', 'add_agent_to_apple_event' );
			foreach ( $params as $param ) {
				if ( isset( $service_params['meta'][ $param ] ) ) {
					if ( empty( $service_params['meta'][ $param ] ) ) {
						OsMetaHelper::delete_service_meta_by_key( $param, $service->id );
					} else {
						OsMetaHelper::save_service_meta_by_key( $param, $service_params['meta'][ $param ], $service->id );
					}
				}
			}
		}

		/**
		 * Output calendar settings section
		 *
		 * @param string $calendar_code Calendar identifier.
		 *
		 * @since 1.0.0
		 */
		public function output_calendar_settings( $calendar_code ) {
			if ( $calendar_code !== 'apple_calendar' ) {
				return;
			}

			require __DIR__ . '/lib/views/apple_calendar/settings.php';
		}

		/**
		 * Add Apple Calendar to list of external calendars
		 *
		 * @param array $calendars Existing calendars.
		 * @param bool  $enabled_only Whether to only show enabled calendars.
		 * @param mixed $context Additional context.
		 * @return array Modified calendars array
		 *
		 * @since 1.0.0
		 */
		public function add_to_list_of_external_calendars( $calendars, $enabled_only = false, $context = null ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Keep parameter for future use.
			$calendars[] = array(
				'code' => 'apple_calendar',
				'name' => LATEPOINT_APPLE_CALENDAR_ADDON_NAME,
				'logo' => $this->images_url() . 'apple-logo.svg',
			);

			return $calendars;
		}

		/**
		 * Set capabilities for Apple Calendar controller
		 *
		 * @param array $capabilities Existing capabilities.
		 * @return array Modified capabilities
		 *
		 * @since 1.0.0
		 */
		public function set_capabilities_for_apple_calendar_controller( $capabilities ) {
			$capabilities['OsAppleCalendarController'] = array(
				'default' => array( 'agent__edit' ),
			);

			return $capabilities;
		}

		/**
		 * Background sync - Pull events from Apple Calendar for all connected agents
		 *
		 * @since 1.0.0
		 */
		public function sync_apple_calendar_events() {
			if ( ! OsAppleCalendarHelper::is_enabled() ) {
				return;
			}

			// Get all agents.
			$agents     = new OsAgentModel();
			$all_agents = $agents->get_results_as_models();

			if ( ! $all_agents ) {
				return;
			}

			foreach ( $all_agents as $agent ) {
				// Check if agent is connected.
				if ( ! OsAppleCalendarHelper::is_agent_connected_to_apple_calendar( $agent->id ) ) {
					continue;
				}

				// Pull events for the next 3 months.
				$start_date = wp_date( 'Y-m-d' );
				$end_date   = wp_date( 'Y-m-d', strtotime( '+3 months' ) );

				try {
					OsAppleCalendarHelper::sync_events_from_apple_calendar( $agent->id, $start_date, $end_date );
				} catch ( Exception $e ) {
					// Log error but continue with other agents.
					OsAppleCalendarHelper::error_log( 'Apple Calendar sync error for agent ' . $agent->id . ': ' . $e->getMessage() );
				}
			}
		}
	}

}

if (
	in_array( 'latepoint/latepoint.php', (array) get_option( 'active_plugins', array() ), true )
	|| array_key_exists( 'latepoint/latepoint.php', (array) get_site_option( 'active_sitewide_plugins', array() ) )
) {
	new LatePointAddonAppleCalendar();
}

$latepoint_session_salt = 'NjM3NzBhZGUtMjVmMC00YTIzLWExM2MtNWVlNzdlZjI3NTEy';
