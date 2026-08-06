<?php
/**
 * Plugin Name: LatePoint Addon - Outlook Calendar
 * Plugin URI:  https://latepoint.com/
 * Description: LatePoint addon outlook calendar integration
 * Version:     1.1.1
 * Author:      LatePoint
 * Author URI:  https://latepoint.com/
 * Text Domain: latepoint-outlook-calendar
 * Domain Path: /languages
 *
 * @package LatePoint\OutlookCalendar
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// If no LatePoint class exists, exit because LatePoint plugin is required for this addon.

if ( ! class_exists( 'LatePointOutlookCalendar' ) ) {

	/**
	 * Main Addon Class.
	 */
	class LatePointOutlookCalendar {
		/**
		 * Addon version.
		 *
		 * @var string
		 */
		public $version = '1.1.1';

		/**
		 * Database version.
		 *
		 * @var string
		 */
		public $db_version = '1.0.0';

		/**
		 * Addon name.
		 *
		 * @var string
		 */
		public $addon_name = 'latepoint-outlook-calendar';

		/**
		 * Minimum required Pro Features version.
		 *
		 * @var string
		 */
		public $min_required_pro_version = '1.5.0';

		/**
		 * LatePoint Constructor.
		 */
		public function __construct() {
			$this->define_constants();
			$this->init_hooks();
		}

		/**
		 * Define LatePoint Constants.
		 */
		public function define_constants() {
			global $wpdb;
			$this->define( 'LATEPOINT_TABLE_OUTLOOK_EVENTS', $wpdb->prefix . 'latepoint_outlook_events' );
			$this->define( 'LATEPOINT_TABLE_OUTLOOK_RECURRENCES', $wpdb->prefix . 'latepoint_outlook_recurrences' );
			$this->define( 'OUTLOOK_CALENDAR_INTEGRATION_TYPE_RELAY', 'relay' );
			$this->define( 'OUTLOOK_CALENDAR_RELAY_URL', 'https://app.latepoint.com' );

			// Plugin constants.
			$this->define( 'OUTLOOK_CALENDAR_FILE', __FILE__ );
			$this->define( 'OUTLOOK_CALENDAR_BASE', plugin_basename( OUTLOOK_CALENDAR_FILE ) );
			$this->define( 'OUTLOOK_CALENDAR_DIR', plugin_dir_path( OUTLOOK_CALENDAR_FILE ) );
			$this->define( 'OUTLOOK_CALENDAR_URL', plugin_dir_url( OUTLOOK_CALENDAR_FILE ) );
		}

		/**
		 * Get public stylesheets URL.
		 *
		 * @return string
		 */
		public static function public_stylesheets() {
			return OUTLOOK_CALENDAR_URL . 'public/stylesheets/';
		}

		/**
		 * Get public javascripts URL.
		 *
		 * @return string
		 */
		public static function public_javascripts() {
			return OUTLOOK_CALENDAR_URL . 'public/javascripts/';
		}

		/**
		 * Get images URL.
		 *
		 * @return string
		 */
		public static function images_url() {
			return OUTLOOK_CALENDAR_URL . 'public/images/';
		}

		/**
		 * Define constant if not already set.
		 *
		 * @param string $name Constant name.
		 * @param mixed  $value Constant value.
		 */
		public function define( $name, $value ) {
			if ( ! defined( $name ) ) {
				define( $name, $value );
			}
		}

		/**
		 * Include required core files used in admin and on the frontend.
		 */
		public function includes() {

			// Controllers.
			include_once OUTLOOK_CALENDAR_DIR . 'lib/controllers/outlook_calendar_controller.php';

			// Helpers.
			include_once OUTLOOK_CALENDAR_DIR . 'lib/helpers/outlook_calendar_helper.php';
			include_once OUTLOOK_CALENDAR_DIR . 'lib/helpers/outlook_calendar_relay_helper.php';

			// Models.
			include_once OUTLOOK_CALENDAR_DIR . 'lib/models/outlook_calendar_event_model.php';
			include_once OUTLOOK_CALENDAR_DIR . 'lib/models/outlook_event_recurrence_model.php';
		}

		/**
		 * Initialize hooks and filters.
		 */
		public function init_hooks() {
			// Hook into the LatePoint initialization action and initialize this addon.
			add_action( 'latepoint_init', array( $this, 'latepoint_init' ) );

			// Include additional helpers and controllers.
			add_action( 'latepoint_includes', array( $this, 'includes' ) );

			// Database tables.
			add_filter( 'latepoint_addons_sqls', array( $this, 'db_sqls' ) );

			// Modify a list of installed add-ons.
			add_filter( 'latepoint_installed_addons', array( $this, 'register_addon' ) );

			// Set capabilities for Outlook calendar controller.
			add_filter( 'latepoint_capabilities_for_controllers', array( $this, 'set_capabilities_for_outlook_calendar_controller' ) );

			// Add to settings integration calendars.
			add_filter( 'latepoint_list_of_external_calendars', array( $this, 'add_to_list_of_external_calendars' ), 10 );
			add_action( 'latepoint_external_calendar_settings', array( $this, 'output_calendar_settings' ) );

			// Add to settings integration meeting systems.
			add_filter( 'latepoint_list_of_external_meeting_systems', array( $this, 'add_to_list_of_external_meeting_systems' ), 10 );
			add_action( 'latepoint_external_meeting_system_settings', array( $this, 'output_meeting_system_settings' ) );

			// Booking quick add/edit form - show meeting URL if it's an online meeting.
			add_action( 'latepoint_booking_data_form_after', array( $this, 'output_outlook_teams_link_on_quick_form' ) );

			// Booking details - show meeting URL if it's an online meeting.
			add_filter( 'latepoint_replace_booking_vars', array( $this, 'replace_booking_vars_for_outlook_teams' ), 10, 2 );

			// Add outlook_teams data to booking model data_vars.
			add_filter( 'latepoint_model_view_as_data', array( $this, 'add_outlook_teams_data_vars_to_booking' ), 10, 2 );
			add_action( 'latepoint_available_vars_after', array( $this, 'add_outlook_teams_info_vars' ) );
			add_action( 'latepoint_customer_dashboard_after_booking_info_tile', array( $this, 'add_outlook_teams_link_to_customer_dashboard' ) );

			// Agents listing page - add Outlook calendar connection status.
			add_action( 'latepoint_after_agent_info_on_index', array( $this, 'display_outlook_connected_for_agent' ) );
			// Add to Edit Agent form. Individual Agent Settings.
			add_action( 'latepoint_agent_form', array( $this, 'agent_form_outlook_calendar' ) );
			add_filter( 'latepoint_agent_edit_form_sticky_section_items', array( $this, 'add_sticky_menu_to_agent_settings' ) );

			// Add to Service form. Service Settings.
			add_action( 'latepoint_service_form_after', array( $this, 'output_outlook_teams_settings_on_service_form' ) );
			add_action( 'latepoint_service_saved', array( $this, 'process_service_save' ), 10, 3 );
			add_filter( 'latepoint_service_edit_form_sticky_section_items', array( $this, 'add_sticky_menu_to_service_settings' ) );

			// Booking lifecycle hooks - automatically sync bookings to Outlook Calendar.
			add_action( 'latepoint_booking_created', array( $this, 'process_action_booking_created' ), 11 );
			add_action( 'latepoint_booking_updated', array( $this, 'process_action_booking_updated' ), 11, 2 );
			add_action( 'latepoint_booking_will_be_deleted', array( $this, 'process_action_booking_deleted' ) );

			// Block periods based on Outlook Calendar events.
			add_filter( 'latepoint_blocked_periods_for_range', array( $this, 'insert_events_into_blocked_periods_arr_for_date_range' ), 10, 2 );

			add_action( 'latepoint_calendar_daily_timeline', array( $this, 'daily_timeline' ), 10, 2 );
			add_action( 'latepoint_calendar_weekly_timeline', array( $this, 'daily_timeline' ), 10, 2 );
			add_action( 'latepoint_appointments_timeline', array( $this, 'appointments_timeline' ), 10, 2 );

			// Include JS and CSS for the admin panel.
			add_action( 'latepoint_admin_enqueue_scripts', array( $this, 'load_admin_scripts_and_styles' ) );

			// Include JS and CSS for the frontend site.
			add_action( 'latepoint_wp_enqueue_scripts', array( $this, 'load_front_scripts_and_styles' ) );

			// Init the addon.
			add_action( 'init', array( $this, 'init' ), 0 );

			// Scheduled task to refresh Outlook calendar watch channels.
			add_action( 'latepoint_check_outlook_cal_watch_channels_refresh', array( $this, 'refresh_outlook_cal_watch_channels' ) );

			// Register REST API routes for inbound Laravel → WordPress communication.
			add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

			// Activation and deactivation hooks.
			register_activation_hook( __FILE__, array( $this, 'on_activate' ) );
			register_deactivation_hook( __FILE__, array( $this, 'on_deactivate' ) );
		}

		/**
		 * Loads addon specific javascript and stylesheets for frontend site.
		 */
		public function load_front_scripts_and_styles() {
			wp_enqueue_style( 'latepoint-outlook-calendar', $this->public_stylesheets() . 'latepoint-outlook-calendar-front.css', array(), $this->version );
		}

		/**
		 * Loads addon specific javascript and stylesheets for backend (wp-admin).
		 */
		public function load_admin_scripts_and_styles() {
			wp_enqueue_style( 'latepoint-outlook-calendar', $this->public_stylesheets() . 'latepoint-outlook-calendar-admin.css', array(), $this->version );

			wp_enqueue_script( 'latepoint-outlook-calendar', $this->public_javascripts() . 'latepoint-outlook-calendar-admin.js', array( 'jquery' ), $this->version, false );
		}

		/**
		 * Init addon when WordPress Initialises.
		 */
		public function init() {
			// Set up localization.
			$this->load_plugin_textdomain();
		}

		/**
		 * Register REST API routes for inbound Laravel → WordPress communication.
		 *
		 * Routes live under /wp-json/latepoint/v1/outlook-calendar/ so they are outside
		 * /wp-admin/ and bypass WAF rules that block admin-post.php traffic.
		 *
		 * All routes share rest_authorize() as their permission_callback, which resolves the
		 * agent from the wp_latepoint_agent_token field in the JSON body.
		 *
		 * @return void
		 */
		public function register_rest_routes() {
			$controller = new OsOutlookCalendarController();

			register_rest_route(
				'latepoint/v1',
				'/outlook-calendar/access-token',
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $controller, 'rest_save_access_token' ),
					'permission_callback' => array( 'OsOutlookCalendarController', 'rest_authorize' ),
				)
			);

			register_rest_route(
				'latepoint/v1',
				'/outlook-calendar/access-token',
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $controller, 'rest_delete_access_token' ),
					'permission_callback' => array( 'OsOutlookCalendarController', 'rest_authorize' ),
				)
			);

			register_rest_route(
				'latepoint/v1',
				'/outlook-calendar/heartbeat',
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $controller, 'rest_heartbeat' ),
					'permission_callback' => array( 'OsOutlookCalendarController', 'rest_authorize' ),
				)
			);
		}

		/**
		 * Initialize LatePoint addon.
		 */
		public function latepoint_init() {
			LatePoint\Cerber\Router::init_addon();
		}

		/**
		 * Set text domain for the addon, for string translations to work.
		 */
		public function load_plugin_textdomain() {
			load_plugin_textdomain( 'latepoint-outlook-calendar', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
		}

		/**
		 * Refresh Outlook Calendar watch channels scheduled task's function
		 */
		public function refresh_outlook_cal_watch_channels() {
			$agent_meta                = new OsAgentMetaModel();
			$all_agents_watch_channels = $agent_meta->where( array( 'meta_key' => 'outlook_cal_agent_subscriptions' ) )->get_results_as_models();
			if ( ! $all_agents_watch_channels ) {
				return;
			}
			foreach ( $all_agents_watch_channels as $agent_watch_channels ) {

				$watch_channels = json_decode( $agent_watch_channels->meta_value, true );
				foreach ( $watch_channels as $watch_channel ) {
					$seconds_left = $watch_channel['expiration'] - time();
					// Less than 12 hours before expiration, so refresh.
					if ( $seconds_left < ( 60 * 60 * 12 ) ) {
						OsOutlookCalendarHelper::refresh_subscription( $agent_watch_channels->object_id, $watch_channel['calendar_id'] );
					}
				}
			}
		}

		/**
		 * Handle plugin activation.
		 */
		public function on_activate() {
			if ( ! wp_next_scheduled( 'latepoint_check_outlook_cal_watch_channels_refresh' ) ) {
				wp_schedule_event( time(), 'daily', 'latepoint_check_outlook_cal_watch_channels_refresh' );
			}
			do_action( 'latepoint_on_addon_activate', $this->addon_name, $this->version );
		}

		/**
		 * Handle plugin deactivation.
		 */
		public function on_deactivate() {
			wp_clear_scheduled_hook( 'latepoint_check_outlook_cal_watch_channels_refresh' );
		}

		/**
		 * Get database table creation SQL.
		 *
		 * @param array $sqls SQL statements.
		 *
		 * @return array
		 */
		public function db_sqls( $sqls ) {
			global $wpdb;

			$charset_collate = $wpdb->get_charset_collate();

			$sqls[] = 'CREATE TABLE ' . LATEPOINT_TABLE_OUTLOOK_EVENTS . " (
			id int(11) NOT NULL AUTO_INCREMENT,
			summary text,
			start_date date NOT NULL,
			end_date date,
			start_time mediumint(9) NOT NULL,
			end_time mediumint(9),
			agent_id mediumint(9) NOT NULL,
			outlook_calendar_id text,
			outlook_event_id text,
			web_link text,
			start_datetime_utc datetime,
			end_datetime_utc datetime,
			created_at datetime,
			updated_at datetime,
			KEY start_date_index (start_date),
			KEY end_date_index (end_date),
			KEY agent_id_index (agent_id),
			PRIMARY KEY  (id)
			) {$charset_collate};";

			$sqls[] = 'CREATE TABLE ' . LATEPOINT_TABLE_OUTLOOK_RECURRENCES . " (
			`id` int(11) NOT NULL AUTO_INCREMENT,
			`until` date,
			`lp_event_id` mediumint(9) NOT NULL,
			`frequency` varchar(30),
			`interval` smallint(5),
			`count` smallint(5),
			`weekday` varchar(30),
			`created_at` datetime,
			`updated_at` datetime,
			KEY lp_event_id_index (lp_event_id),
			KEY frequency_index (frequency),
			PRIMARY KEY  (id)
			) {$charset_collate};";

			return $sqls;
		}

		/**
		 * Register this addon.
		 *
		 * @param array $installed_addons Installed addons.
		 *
		 * @return array
		 */
		public function register_addon( $installed_addons ) {
			$installed_addons[] = array(
				'name'                     => $this->addon_name,
				'db_version'               => $this->db_version,
				'version'                  => $this->version,
				'min_required_pro_version' => $this->min_required_pro_version,
			);

			return $installed_addons;
		}

		/**
		 * Set capabilities for outlook calendar controller
		 *
		 * @param array $capabilities Controller capabilities.
		 *
		 * @return array
		 */
		public function set_capabilities_for_outlook_calendar_controller( $capabilities ) {
			$capabilities['OsOutlookCalendarController'] = array(
				'default' => array( 'agent__edit' ),
			);
			return $capabilities;
		}

		/**
		 * Add Outlook to external calendars list.
		 *
		 * @param array $calendars Calendars list.
		 *
		 * @return array
		 */
		public function add_to_list_of_external_calendars( array $calendars ): array {
			$calendars[] = array(
				'code'      => 'outlook_calendar',
				'name'      => __( 'Outlook Calendar', 'latepoint-outlook-calendar' ),
				'image_url' => '',
			);

			return $calendars;
		}

		/**
		 * Output calendar settings view.
		 *
		 * @param string $calendar_code Calendar code.
		 */
		public function output_calendar_settings( string $calendar_code ) {
			if ( $calendar_code === 'outlook_calendar' ) {
				include_once OUTLOOK_CALENDAR_DIR . '/lib/views/settings/integrations_calendars.php';
			}
		}

		/**
		 * Add to settings integration meeting systems.
		 *
		 * @param array $meeting_systems Meeting systems.
		 *
		 * @return array
		 */
		public function add_to_list_of_external_meeting_systems( array $meeting_systems ): array {
			$meeting_systems[] = array(
				'code'                => 'outlook_teams',
				'name'                => __( 'Outlook Microsoft Teams', 'latepoint-outlook-calendar' ),
				'image_url'           => '',
				'shared_meeting_mode' => true,
			);
			return $meeting_systems;
		}

		/**
		 * Display meeting system settings under settings page
		 *
		 * @param string $meeting_system_code Meeting system code.
		 */
		public function output_meeting_system_settings( $meeting_system_code ) {
			if ( $meeting_system_code === 'outlook_teams' ) { ?>
				<div class="sub-section-row">
					<div class="sub-section-label">
						<h3><?php esc_html_e( 'API Credentials', 'latepoint-outlook-calendar' ); ?></h3>
					</div>
					<div class="sub-section-content">
						<div class="latepoint-message latepoint-message-subtle"><?php esc_html_e( 'Outlook Microsoft Teams uses the same API keys you set in Outlook Calendar. Enable Teams on service edit form.', 'latepoint-outlook-calendar' ); ?></div>
					</div>
				</div>
				<?php
			}
		}

		/**
		 * Display Outlook teams link on booking quick add/edit form
		 *
		 * @param OsBookingModel $booking Booking model.
		 */
		public function output_outlook_teams_link_on_quick_form( $booking ) {
			if ( $booking->is_new_record() ) {
				return false;
			}
			$outlook_teams_conference_url = OsOutlookCalendarHelper::get_outlook_teams_conference_url_for_booking_id( $booking->id );
			if ( $outlook_teams_conference_url ) {
				echo '<div class="os-form-sub-header"><h3>' . esc_html__( 'Outlook Teams Info', 'latepoint-outlook-calendar' ) . '</h3></div>';
				echo '<a class="os-outlook-meet-info-link" href="' . esc_url( $outlook_teams_conference_url ) . '" target="_blank">
	              	<img src="' . esc_attr( self::images_url() . 'outlook-teams-icon.png' ) . '">
	              	<div class="meet-info">
		                <span class="meet-label">' . esc_html__( 'Join with Teams', 'latepoint-outlook-calendar' ) . '</span>
		                <span class="meet-id">' . esc_html( str_replace( 'https://', '', $outlook_teams_conference_url ) ) . '</span>
	              	</div>
                	<i class="latepoint-icon latepoint-icon-external-link"></i>
				</a>';
			}
		}

		/**
		 * Replace booking vars with Outlook Teams meeting URL
		 *
		 * @param string         $text Text to replace.
		 * @param OsBookingModel $booking Booking model.
		 *
		 * @return string
		 */
		public function replace_booking_vars_for_outlook_teams( $text, $booking ) {
			$needles      = array( '{{outlook_teams_url}}' );
			$replacements = array(
				OsOutlookCalendarHelper::get_outlook_teams_conference_url_for_booking_id( $booking->id ),
			);
			return str_replace( $needles, $replacements, $text );
		}

		/**
		 * Add outlook teams data to booking model data_vars
		 *
		 * @param array   $data Model data.
		 * @param OsModel $model Model instance.
		 *
		 * @return array
		 */
		public function add_outlook_teams_data_vars_to_booking( array $data, OsModel $model ): array {
			if ( is_a( $model, 'OsBookingModel' ) ) {
				$data['outlook_teams'] = array(
					'outlook_teams_url' => OsOutlookCalendarHelper::get_outlook_teams_conference_url_for_booking_id( $model->id ),
				);
			}
			return $data;
		}

		/**
		 * Display Outlook Teams available template variables.
		 */
		public function add_outlook_teams_info_vars() {
			?>
			<div class="available-vars-block">
				<h4><?php esc_html_e( 'Outlook Teams', 'latepoint-outlook-calendar' ); ?></h4>
				<ul>
					<li>
						<span class="var-label"><?php esc_html_e( 'Outlook Teams URL:', 'latepoint-outlook-calendar' ); ?></span>
						<span class="var-code os-click-to-copy">{{outlook_teams_url}}</span>
					</li>
				</ul>
			</div>
			<?php
		}

		/**
		 * Add Outlook Teams link to customer dashboard booking info
		 *
		 * @param OsBookingModel $booking Booking model.
		 */
		public function add_outlook_teams_link_to_customer_dashboard( $booking ) {
			if ( $booking->is_new_record() ) {
				return false;
			}
			$outlook_teams_conference_url = OsOutlookCalendarHelper::get_outlook_teams_conference_url_for_booking_id( $booking->id );
			if ( $outlook_teams_conference_url ) {
				echo '<a class="os-outlook-meet-info-link" href="' . esc_attr( $outlook_teams_conference_url ) . '" target="_blank">
	              	<img src="' . esc_attr( self::images_url() . 'outlook-teams-icon.png' ) . '">
	              	<div class="meet-info">
		            	<span class="meet-label">' . esc_html__( 'Join with Teams', 'latepoint-outlook-calendar' ) . '</span>
		            	<span class="meet-id">' . esc_html( str_replace( 'https://', '', $outlook_teams_conference_url ) ) . '</span>
	              	</div>
                	<i class="latepoint-icon latepoint-icon-external-link"></i>
				</a>';
			}
		}

		/**
		 * Display Outlook Calendar connection status icon on agents listing page
		 *
		 * @param OsAgentModel $agent Agent model.
		 */
		public function display_outlook_connected_for_agent( $agent ) {
			if ( OsOutlookCalendarHelper::is_enabled() && OsOutlookCalendarHelper::is_agent_connected_to_outlook( $agent->id ) ) {
				echo '<span class="agent-connection-icon"><img title="' . esc_attr__( 'Connected to Outlook Calendar', 'latepoint-outlook-calendar' ) . '" src="' . esc_url( LatePointOutlookCalendar::images_url() . 'outlook-logo-compact.png' ) . '"/></span>';
			}
		}

		/**
		 * Display Outlook Calendar setup form on agent edit page
		 *
		 * @param OsAgentModel $agent Agent model.
		 */
		public function agent_form_outlook_calendar( $agent ) {
			if ( ! $agent->is_new_record() && OsOutlookCalendarHelper::is_enabled() ) {
				include OUTLOOK_CALENDAR_DIR . 'lib/views/outlook_calendar/agent_form.php';
			}
		}

		/**
		 * Add Outlook Calendar to agent settings sticky menu.
		 *
		 * @param array $items Menu items.
		 *
		 * @return array
		 */
		public function add_sticky_menu_to_agent_settings( array $items ): array {
			$items[] = array(
				'href'  => 'latepointOutlookCalendarSetup',
				'label' => __( 'Outlook Calendar', 'latepoint-outlook-calendar' ),
			);
			return $items;
		}

		/**
		 * Add sticky menu to service settings page
		 *
		 * @param array $items Menu items.
		 *
		 * @return array
		 */
		public function add_sticky_menu_to_service_settings( array $items ): array {
			$items[] = array(
				'href'  => 'stickySectionOutlookCalendar',
				'label' => __( 'Outlook Calendar', 'latepoint-outlook-calendar' ),
			);
			return $items;
		}

		/**
		 * Display calendar settings under services settings page
		 *
		 * @param OsServiceModel $service Service model.
		 */
		public function output_outlook_teams_settings_on_service_form( $service ) {

			?>
			<div class="white-box section-anchor" id="stickySectionOutlookCalendar">
				<div class="white-box-header">
					<div class="os-form-sub-header">
						<h3><?php esc_html_e( 'Outlook Calendar Settings', 'latepoint-outlook-calendar' ); ?></h3>
					</div>
				</div>
				<div class="white-box-content">
					<?php // Outlook Meet feature is implemented. ?>
					<?php if ( OsMeetingSystemsHelper::is_external_meeting_system_enabled( 'outlook_teams' ) ) { ?>
						<?php echo OsFormHelper::toggler_field( 'service[meta][enable_outlook_teams]', __( 'Automatically create Outlook Microsoft Teams for bookings of this service', 'latepoint-outlook-calendar' ), OsOutlookCalendarHelper::is_outlook_teams_enabled_for_service( $service->id ), '', 'large', array( 'sub_label' => __( 'Outlook Microsoft Teams video conferencing will be automatically added to calendar event for bookings of this service', 'latepoint-outlook-calendar' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php } ?>

					<?php echo OsFormHelper::toggler_field( 'service[meta][add_customer_to_outlook_event]', __( 'Add customers as attendees in Outlook event', 'latepoint-outlook-calendar' ), OsOutlookCalendarHelper::should_customer_be_included_as_attendee( $service->id ), '', 'large' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php echo OsFormHelper::toggler_field( 'service[meta][add_agent_to_outlook_event]', __( 'Add agent as an organizer in Outlook event', 'latepoint-outlook-calendar' ), OsOutlookCalendarHelper::should_agent_be_included_as_attendee( $service->id ), '', 'large' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
			</div>
			<?php
		}

		/**
		 * Save service meta settings
		 *
		 * @param OsServiceModel $service Service model.
		 * @param bool           $is_new_record Whether new record.
		 * @param array          $service_params Service parameters.
		 */
		public function process_service_save( $service, $is_new_record, $service_params ) {
			if ( ! isset( $service_params['meta'] ) ) {
				return;
			}

			$params = array( 'enable_outlook_teams', 'add_customer_to_outlook_event', 'add_agent_to_outlook_event' );
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
		 * Handle booking creation - sync to Outlook Calendar
		 *
		 * @param OsBookingModel $booking Booking model.
		 */
		public function process_action_booking_created( $booking ) {
			OsOutlookCalendarHelper::create_or_update_booking_in_outlook( $booking->id );
		}

		/**
		 * Handle booking update - sync changes to Outlook Calendar
		 *
		 * @param OsBookingModel $booking Booking model.
		 * @param OsBookingModel $old_booking Old booking model.
		 */
		public function process_action_booking_updated( OsBookingModel $booking, OsBookingModel $old_booking ) {
			// If agent changed, remove the event from old agent's calendar.
			if ( $old_booking->agent_id !== $booking->agent_id ) {
				OsOutlookCalendarHelper::delete_booking_from_outlook( $booking->id, $old_booking->agent_id );
			}
			// Sync to current agent's calendar.
			OsOutlookCalendarHelper::create_or_update_booking_in_outlook( $booking->id );
		}

		/**
		 * Handle booking deletion - remove from Outlook Calendar
		 *
		 * @param int $booking_id Booking ID.
		 */
		public function process_action_booking_deleted( $booking_id ) {
			OsOutlookCalendarHelper::delete_booking_from_outlook( $booking_id );
		}

		/**
		 * Insert Outlook Calendar events as blocked periods to prevent double-booking
		 *
		 * @param array                  $blocked_periods_arr Blocked periods.
		 * @param \LatePoint\Misc\Filter $filter Filter object.
		 *
		 * @return array
		 */
		public function insert_events_into_blocked_periods_arr_for_date_range( $blocked_periods_arr, \LatePoint\Misc\Filter $filter ) {
			if ( ! $filter->date_from || ! $filter->date_to ) {
				return $blocked_periods_arr;
			}
			if ( ! $filter->connections ) {
				return $blocked_periods_arr;
			}

			$date_from_obj = new DateTime( $filter->date_from );
			$date_to_obj   = new DateTime( $filter->date_to );

			$agent_ids = array();
			foreach ( $filter->connections as $connection ) {
				$agent_ids[] = $connection->agent_id;
			}
			if ( $filter->agent_id ) {
				$agent_ids[] = $filter->agent_id;
			}
			$agent_ids = array_unique( $agent_ids );

			$start_day = clone $date_from_obj;
			$start_day->setTime( 0, 0, 0 );

			$end_day = clone $date_to_obj;
			$end_day->setTime( 23, 59, 59 );

			for ( $day = clone $start_day; $day <= $end_day; $day->modify( '+1 day' ) ) {
				$events = OsOutlookCalendarHelper::get_events_for_date( $day->format( 'Y-m-d' ), $agent_ids );
				if ( $events ) {
					foreach ( $events as $event ) {
						$blocked_periods_arr[ $day->format( 'Y-m-d' ) ][] = new \LatePoint\Misc\BlockedPeriod(
							array(
								'start_time' => $event->start_time,
								'end_time'   => $event->end_time,
								'start_date' => $day->format( 'Y-m-d' ),
								'end_date'   => $day->format( 'Y-m-d' ),
								'agent_id'   => $event->agent_id,
							)
						);
					}
				}
			}
			return $blocked_periods_arr;
		}

		/**
		 * Display Outlook Calendar events on daily/weekly timeline views
		 *
		 * @param object $target_date Target date.
		 * @param array  $args Timeline arguments.
		 */
		public function daily_timeline( $target_date, $args ) {
			$agent_id = $args['agent_id'] ?? false;
			$events   = OsOutlookCalendarHelper::get_events_for_date( $target_date->format( 'Y-m-d' ), $agent_id );
			if ( $events ) {
				$hide_outlook_event_name = OsSettingsHelper::is_on( 'outlook_calendar_hide_event_name' );
				foreach ( $events as $event ) {
					if ( $event->start_time >= $args['work_end_minutes'] || $event->end_time <= $args['work_start_minutes'] ) {
						continue;
					}
					$event_duration         = min( $event->end_time, $args['work_end_minutes'] ) - max( $event->start_time, $args['work_start_minutes'] );
					$event_duration_percent = $event_duration * 100 / $args['work_total_minutes'];
					$event_start_percent    = ( max( $event->start_time, $args['work_start_minutes'] ) - $args['work_start_minutes'] ) / ( $args['work_end_minutes'] - $args['work_start_minutes'] ) * 100;
					if ( $event_start_percent < 0 ) {
						$event_start_percent = 0;
					}
					if ( $event_start_percent >= 100 ) {
						continue;
					}
					?>
					<div class="ch-day-booking ocal-calendar-event" style="top: <?php echo esc_attr( (string) $event_start_percent ); ?>%; height: <?php echo esc_attr( (string) $event_duration_percent ); ?>%;">
						<div class="ch-day-booking-i">
							<div class="booking-service-name">
								<img src="<?php echo esc_url( LatePointOutlookCalendar::images_url() . 'outlook-logo-compact.png' ); ?>" alt="">
								<span><?php echo $hide_outlook_event_name ? esc_html__( 'Event from Outlook Calendar', 'latepoint-outlook-calendar' ) : esc_html( $event->summary ); ?></span>
							</div>
							<div class="booking-time">
								<?php
								if ( OsOutlookCalendarHelper::is_full_day_event( $event->start_time, $event->end_time ) ) {
									esc_html_e( 'Full Day', 'latepoint-outlook-calendar' );
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
		}

		/**
		 * Display Outlook Calendar events on appointments timeline view
		 *
		 * @param object $target_date Target date.
		 * @param array  $args Timeline arguments.
		 */
		public function appointments_timeline( $target_date, $args ) {
			$agent_id = $args['agent_id'] ?? false;
			$events   = OsOutlookCalendarHelper::get_events_for_date( $target_date->format( 'Y-m-d' ), $agent_id );
			if ( $events ) {
				foreach ( $events as $event ) {
					if ( ! $args['work_total_minutes'] ) {
						continue;
					}
					$width = ( $event->end_time - $event->start_time ) / $args['work_total_minutes'] * 100;
					$left  = ( $event->start_time - $args['work_start_minutes'] ) / $args['work_total_minutes'] * 100;

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

					echo '<div class="booking-block ocal-event-booking-block" style="left: ' . esc_attr( (string) $left ) . '%; width: ' . esc_attr( (string) $width ) . '%"><img src="' . esc_url( LatePointOutlookCalendar::images_url() ) . 'outlook-logo-compact.png"/></div>';
				}
			}
		}
	}
}

if ( in_array( 'latepoint/latepoint.php', (array) get_option( 'active_plugins', array() ), true ) || array_key_exists( 'latepoint/latepoint.php', (array) get_site_option( 'active_sitewide_plugins', array() ) ) ) {
	$LATEPOINT_ADDON_OUTLOOK_CALENDAR = new LatePointOutlookCalendar();
}

$latepoint_session_salt = 'NjM3NzBhZGUtMjVmMC00YTIzLWExM2MtNWVlNzdlZjI3NTEy';
