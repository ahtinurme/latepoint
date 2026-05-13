<?php
/**
 * Plugin Name: LatePoint Addon - Google Calendar
 * Plugin URI:  https://latepoint.com/
 * Description: LatePoint addon for google calendar integration
 * Version:     1.6.0
 * Author:      LatePoint
 * Author URI:  https://latepoint.com/
 * Text Domain: latepoint-google-calendar
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}

// If no LatePoint class exists - exit, because LatePoint plugin is required for this addon

if (!class_exists('LatePointAddonGoogleCalendar')) :

	/**
	 * Main Addon Class.
	 */
	class LatePointAddonGoogleCalendar {

		/**
		 * Addon version.
		 */
		public $version = '1.6.0';
		public $db_version = '1.0.2';
		public $addon_name = 'latepoint-google-calendar';

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
			$upload_dir = wp_upload_dir();

			global $wpdb;
			$this->define('LATEPOINT_TABLE_GCAL_EVENTS', $wpdb->prefix . 'latepoint_gcal_events');
			$this->define('LATEPOINT_TABLE_GCAL_RECURRENCES', $wpdb->prefix . 'latepoint_gcal_recurrences');
			$this->define('GOOGLE_CALENDAR_INTEGRATION_TYPE_RELAY', 'relay');
			$this->define('GOOGLE_CALENDAR_INTEGRATION_TYPE_DIRECT', 'direct');
			$this->define('GOOGLE_CALENDAR_RELAY_URL', 'https://app.latepoint.com');
		}


		public static function public_stylesheets() {
			return plugin_dir_url(__FILE__) . 'public/stylesheets/';
		}

		public static function public_javascripts() {
			return plugin_dir_url(__FILE__) . 'public/javascripts/';
		}

		public static function images_url() {
			return plugin_dir_url(__FILE__) . 'public/images/';
		}

		/**
		 * Define constant if not already set.
		 *
		 */
		public function define($name, $value) {
			if (!defined($name)) {
				define($name, $value);
			}
		}

		/**
		 * Include required core files used in admin and on the frontend.
		 */
		public function includes() {

			// COMPOSER AUTOLOAD
			require(dirname(__FILE__) . '/vendor-prefixed/autoload.php');

			// CONTROLLERS
			include_once(dirname(__FILE__) . '/lib/controllers/google_calendar_controller.php');

			// HELPERS
			include_once(dirname(__FILE__) . '/lib/helpers/google_calendar_helper.php');
			include_once(dirname(__FILE__) . '/lib/helpers/google_calendar_relay_helper.php');

			// MODELS
			include_once(dirname(__FILE__) . '/lib/models/google_calendar_event_model.php');
			include_once(dirname(__FILE__) . '/lib/models/gcal_event_recurrence_model.php');

		}

		public function init_hooks() {
			add_action('latepoint_includes', [$this, 'includes']);
			add_action('latepoint_wp_enqueue_scripts', [$this, 'load_front_scripts_and_styles']);
			add_action('latepoint_admin_enqueue_scripts', [$this, 'load_admin_scripts_and_styles']);
			add_action('latepoint_agent_form', [$this, 'agent_form_google_calendar']);
			add_action('latepoint_check_google_cal_watch_channels_refresh', [$this, 'refresh_google_cal_watch_channels']);
			add_action('latepoint_calendar_daily_timeline', [$this, 'daily_timeline'], 10, 2);
			add_action('latepoint_calendar_weekly_timeline', [$this, 'daily_timeline'], 10, 2);
			add_action('latepoint_appointments_timeline', [$this, 'appointments_timeline'], 10, 2);
			add_action('latepoint_booking_updated', [$this, 'process_action_booking_updated'], 11, 2);
			add_action('latepoint_booking_created', [$this, 'process_action_booking_created'], 11);
			add_action('latepoint_booking_will_be_deleted', [$this, 'process_action_booking_deleted']);
			add_action('latepoint_external_calendar_settings', [$this, 'output_calendar_settings']);
			add_action('latepoint_service_form_after', [$this, 'output_google_meet_settings_on_service_form']);
			add_filter('latepoint_service_edit_form_sticky_section_items', [$this, 'add_sticky_menu_to_service_settings']);
			add_filter('latepoint_agent_edit_form_sticky_section_items', [$this, 'add_sticky_menu_to_agent_settings']);
			add_action('latepoint_booking_data_form_after', [$this, 'output_google_meet_link_on_quick_form']);

			add_action('latepoint_after_agent_info_on_index', [$this, 'display_google_connected_for_agent']);

			add_action('init', array($this, 'init'), 0);

			add_filter('latepoint_installed_addons', [$this, 'register_addon']);
			add_filter('latepoint_localized_vars_admin', [$this, 'localized_vars_for_admin']);
			add_filter('latepoint_addons_sqls', [$this, 'db_sqls']);
			add_filter('latepoint_blocked_periods_for_range', [$this, 'insert_events_into_blocked_periods_arr_for_date_range'], 10, 2);
			add_filter('latepoint_list_of_external_calendars', [$this, 'add_to_list_of_external_calendars'], 10, 3);
			add_filter('latepoint_capabilities_for_controllers', [$this, 'set_capabilities_for_google_calendar_controller']);


			// add google_meet data to booking model data_vars
			add_filter('latepoint_model_view_as_data', [$this, 'add_google_meet_data_vars_to_booking'], 10, 2);
			add_action('latepoint_available_vars_after', [$this, 'add_google_meet_info_vars']);
			add_action('latepoint_customer_dashboard_after_booking_info_tile', [$this, 'add_google_meet_link_to_customer_dashboard']);


			add_action('latepoint_external_meeting_system_settings', [$this, 'output_meeting_system_settings']);
			add_filter('latepoint_list_of_external_meeting_systems', [$this, 'add_to_list_of_external_meeting_systems'], 10, 3);
			add_action('latepoint_service_saved', [$this, 'process_service_save'], 10, 3);

			add_filter('latepoint_replace_booking_vars', [$this, 'replace_booking_vars_for_google_meet'], 10, 2);


			register_activation_hook(__FILE__, [$this, 'on_activate']);
			register_deactivation_hook(__FILE__, [$this, 'on_deactivate']);


		}

		/**
		 * Init LatePoint when WordPress Initialises.
		 */
		public function init() {
			// Set up localisation.
			$this->load_plugin_textdomain();
		}


		public function replace_booking_vars_for_google_meet($text, $booking) {
			$needles = ['{{google_meet_url}}'];
			$replacements = [
				OsGoogleCalendarHelper::get_google_meet_conference_url_for_booking_id($booking->id)
			];
			$text = str_replace($needles, $replacements, $text);
			return $text;
		}

		public function add_google_meet_data_vars_to_booking(array $data, OsModel $model): array {
			if (is_a($model, 'OsBookingModel')) {
				$data['google_meet'] = [
					'google_meet_url' => OsGoogleCalendarHelper::get_google_meet_conference_url_for_booking_id($model->id),
				];
			}
			return $data;
		}

		public function add_google_meet_link_to_customer_dashboard($booking) {
			if ($booking->is_new_record()) return false;
			$google_meet_conference_url = OsGoogleCalendarHelper::get_google_meet_conference_url_for_booking_id($booking->id);
			if ($google_meet_conference_url) {
				echo '<a class="os-google-meet-info-link" href="' . esc_attr($google_meet_conference_url) . '" target="_blank">
	              <img src="' . esc_attr(self::images_url() . 'google-meet-icon.png') . '">
	              <div class="meet-info">
		                <span class="meet-label">' . __('Join Google Meet', 'latepoint-google-calendar') . '</span>
		                <span class="meet-id">'.str_replace('https://', '', $google_meet_conference_url).'</span>
	              </div>
                <i class="latepoint-icon latepoint-icon-external-link"></i>
							</a>';
			}
		}

		public function add_google_meet_info_vars() {
			?>
			<div class="available-vars-block">
				<h4><?php _e('Google Meet', 'latepoint-google-calendar'); ?></h4>
				<ul>
					<li><span class="var-label"><?php _e('Google Meet URL:', 'latepoint-google-calendar'); ?></span> <span
							class="var-code os-click-to-copy">{{google_meet_url}}</span></li>
				</ul>
			</div>
			<?php
		}


		public function output_google_meet_link_on_quick_form($booking) {
			if ($booking->is_new_record()) return false;
			$google_meet_conference_url = OsGoogleCalendarHelper::get_google_meet_conference_url_for_booking_id($booking->id);
			if ($google_meet_conference_url) {
				echo '<div class="os-form-sub-header"><h3>' . __('Google Meet Info', 'latepoint-google-calendar') . '</h3></div>';
				echo '<a class="os-google-meet-info-link" href="' . esc_attr($google_meet_conference_url) . '" target="_blank">
	              <img src="' . esc_attr(self::images_url() . 'google-meet-icon.png') . '">
	              <div class="meet-info">
		                <span class="meet-label">' . __('Join with Google Meet', 'latepoint-google-calendar') . '</span>
		                <span class="meet-id">'.str_replace('https://', '', $google_meet_conference_url).'</span>
	              </div>
                <i class="latepoint-icon latepoint-icon-external-link"></i>
							</a>';
			}
		}


		public function process_service_save($service, $is_new_record, $service_params) {
            if (!isset($service_params['meta'])) return;

            $params = ['enable_google_meet', 'add_customer_to_google_event', 'add_agent_to_google_event'];
			foreach ( $params as $param ) {
                if (isset($service_params['meta'][$param])) {
                    if (empty($service_params['meta'][$param])) {
                        OsMetaHelper::delete_service_meta_by_key($param, $service->id);
                    } else {
                        OsMetaHelper::save_service_meta_by_key($param, $service_params['meta'][$param], $service->id);
                    }
                }
			}
		}

        public function add_sticky_menu_to_service_settings(array $items) : array{
            $items[] = ['href' => 'stickySectionGoogleCalendar', 'label' => __( 'Google Calendar', 'latepoint-google-calendar' )];
            return $items;
        }
        public function add_sticky_menu_to_agent_settings(array $items) : array{
            $items[] = ['href' => 'latepointGoogleCalendarSetup', 'label' => __( 'Google Calendar', 'latepoint-google-calendar' )];
            return $items;
        }

		public function output_google_meet_settings_on_service_form($service) { ?>
			<div class="white-box section-anchor" id="stickySectionGoogleCalendar">
				<div class="white-box-header">
					<div class="os-form-sub-header">
						<h3><?php _e('Google Calendar Settings', 'latepoint-google-calendar'); ?></h3>
					</div>
				</div>
				<div class="white-box-content">
                    <?php if(OsMeetingSystemsHelper::is_external_meeting_system_enabled('google_meet')){ ?>
					    <?php echo OsFormHelper::toggler_field('service[meta][enable_google_meet]', __('Automatically create Google Meet for bookings of this service', 'latepoint-google-calendar'), OsGoogleCalendarHelper::is_google_meet_enabled_for_service($service->id), '', 'large', ['sub_label' => __('Google Meet video conferencing will be automatically added to calendar event for bookings of this service')]); ?>
                    <?php } ?>

                    <?php echo OsFormHelper::toggler_field('service[meta][add_customer_to_google_event]', __('Add customers as attendees in Google event', 'latepoint-google-calendar'), OsGoogleCalendarHelper::should_customer_be_included_as_attendee($service->id), '', 'large'); ?>
                    <?php echo OsFormHelper::toggler_field('service[meta][add_agent_to_google_event]', __('Add agent as an organizer in Google event', 'latepoint-google-calendar'), OsGoogleCalendarHelper::should_agent_be_included_as_attendee($service->id), '', 'large'); ?>
				</div>
			</div>
			<?php
		}

		public function add_to_list_of_external_meeting_systems(array $meeting_systems, bool $enabled_only): array {
			$meeting_systems[] = [
				'code' => 'google_meet',
				'name' => __('Google Meet', 'latepoint-google-calendar'),
				'image_url' => '',
				'shared_meeting_mode' => true,
			];
			return $meeting_systems;
		}


		public function output_meeting_system_settings($meeting_system_code) {
			if ($meeting_system_code == 'google_meet') { ?>
				<div class="sub-section-row">
					<div class="sub-section-label"><h3><?php _e('API Credentials', 'latepoint-google-calendar'); ?></h3></div>
					<div class="sub-section-content">
						<div class="latepoint-message latepoint-message-subtle"><?php _e('Google Meet uses the same API keys you set in Google Calendar. Enable Google Meet on service edit form.', 'latepoint-google-calendar') ?></div>
					</div>
				</div>
				<?php
			}
		}

		public function load_plugin_textdomain() {
			load_plugin_textdomain('latepoint-google-calendar', false, dirname(plugin_basename(__FILE__)) . '/languages');
		}

		public function output_calendar_settings(string $calendar_code){
			if($calendar_code == 'google_calendar'){
				if(OsGoogleCalendarHelper::show_self_hosted_settings()){
					$selected_google_calendar_integration_type = OsGoogleCalendarHelper::get_selected_integration_type();
					?>
		        <div class="sub-section-row">
			        <div class="sub-section-label"><h3><?php _e('Integration Type', 'latepoint-google-calendar'); ?></h3></div>
				      <div class="sub-section-content">
				        <div class="latepoint-message latepoint-message-subtle"><?php _e('There are two ways to connect your agents to their Google Calendars. 1. Relay (recommended): connect through a verified LatePoint Google app. 2. Direct: You have your own google app that you want to connect to.') ?></div>
				      <?php
				      echo OsFormHelper::select_field('settings[google_calendar_selected_integration_type]', false, [GOOGLE_CALENDAR_INTEGRATION_TYPE_RELAY => __('Relay through LatePoint Application (Recommended)', 'latepoint-google-calendar'), GOOGLE_CALENDAR_INTEGRATION_TYPE_DIRECT => __('Directly through my custom application', 'latepoint-google-calendar')], $selected_google_calendar_integration_type, ['class' => 'display-toggler-control', 'data-toggler-group' => 'google-calendar-integration-type']); ?>
				      </div>
		        </div>

		        <?php $integration_api_style = ($selected_google_calendar_integration_type == GOOGLE_CALENDAR_INTEGRATION_TYPE_DIRECT) ? '' : 'style="display: none;"'; ?>
		        <div class="sub-section-row display-toggler-target" data-toggler-group="google-calendar-integration-type" data-toggler-key="<?php echo GOOGLE_CALENDAR_INTEGRATION_TYPE_DIRECT; ?>" <?php echo $integration_api_style; ?>>
			        <div class="sub-section-label"><h3><?php _e('API Credentials', 'latepoint-google-calendar'); ?></h3></div>
			        <div class="sub-section-content">
			          <div class="os-row">
			            <div class="os-col-8">
			              <?php echo OsFormHelper::text_field('settings[google_calendar_client_id]', __('Google Calendar Client ID', 'latepoint-google-calendar'), OsSettingsHelper::get_settings_value('google_calendar_client_id'), ['theme' => 'bordered']); ?>
			            </div>
			            <div class="os-col-4">
			              <?php echo OsFormHelper::password_field('settings[google_calendar_client_secret]', __('Google Calendar Client Secret', 'latepoint-google-calendar'), OsSettingsHelper::get_settings_value('google_calendar_client_secret'), ['theme' => 'bordered']); ?>
			            </div>
			          </div>
			        </div>
		        </div>
					<?php
				}
				?>
		        <div class="sub-section-row">
			        <div class="sub-section-label"><h3><?php _e('Agent Connections', 'latepoint-google-calendar'); ?></h3></div>
			        <div class="sub-section-content">
			          <div class="latepoint-message latepoint-message-subtle"><?php _e('Each agent can link their Google Calendar from their agent profile form.', 'latepoint-google-calendar') ?></div>
				        <div class="latepoint-gcal-agent-connections">
				        <?php
				        if(OsGoogleCalendarHelper::is_enabled()){
					        $agents = new OsAgentModel();
									$agents = $agents->should_be_active()->get_results_as_models();
									foreach($agents as $agent){
										$connected = OsGoogleCalendarHelper::is_agent_connected_to_gcal($agent->id);
										echo '<div class="gcal-agent-connection-status '.($connected ? 'connected' : 'not-connected').'">';
										echo '<div class="gcal-agent-avatar" style="background-image: url('.$agent->get_avatar_url().')"></div>';
										echo '<div class="gcal-agent-info">';
											echo '<div class="gcal-agent-name">'.esc_html($agent->full_name).'</div>';
											echo '<div class="gcal-agent-email">'.esc_html($agent->email).'</div>';
											echo '<a href="'.OsRouterHelper::build_link(['agents', 'edit_form'], ['id' => $agent->id]).'#latepointGoogleCalendarSetup">';
											if($connected){
												echo '<i class="latepoint-icon latepoint-icon-check"></i><span>'.esc_html__('Access Granted', 'latepoint-google-calendar').'</span>';
											}else{
												echo '<span>'.esc_html__('Not Connected', 'latepoint-google-calendar').'</span>';
											}
											echo '</a>';
											if($connected){
												$calendar_id_for_push = OsGoogleCalendarHelper::get_selected_calendar_id_for_push($agent->id);
												$calendar_name_for_push = $calendar_id_for_push ? OsGoogleCalendarHelper::get_calendar_name_by_id($calendar_id_for_push, $agent->id) : false;
												$calendar_ids_for_pull = OsGoogleCalendarHelper::get_selected_calendar_ids_for_pull($agent->id);
												$calendars_for_pull_count = ($calendar_ids_for_pull) ? count(explode(',', $calendar_ids_for_pull)) : 0;

												echo '<div class="gcal-agent-connection-breakdown">';
												echo '<div class="">'.__('Push bookings to:', 'latepoint-google-calendar').'</div><div class="">'.($calendar_name_for_push ? 'Selected' : 'Not Selected').'</div>';
												echo '</div>';
												echo '<div class="gcal-agent-connection-breakdown">';
												echo '<div class="">'.__('Pull availability from:', 'latepoint-google-calendar').'</div><div class="">'.($calendars_for_pull_count ? 'Selected' : 'Not Selected').'</div>';
												echo '</div>';
											}
										echo '</div>';
										echo '</div>';
									}
					        ?>
				        <?php } ?>
				        </div>
			        </div>
		        </div>
		        <div class="sub-section-row">
			        <div class="sub-section-label"><h3><?php _e('Event Template', 'latepoint-google-calendar'); ?></h3></div>
			        <div class="sub-section-content">
			          <div class="latepoint-message latepoint-message-subtle"><?php _e('You can use variables in your event title and description, they will be replaced with a value for the booking. ', 'latepoint-google-calendar') ?><?php echo OsUtilHelper::template_variables_link_html(); ?></div>
			          <div class="os-row">
			            <div class="os-col-12">
			              <?php echo OsFormHelper::text_field('settings[google_calendar_event_summary_template]', __('Template For Event Title', 'latepoint-google-calendar'), OsGoogleCalendarHelper::get_event_title_template(), ['theme' => 'bordered']); ?>
			              <?php OsFormHelper::wp_editor_field('settings[google_calendar_event_description_template]', 'settings_google_calendar_event_description_template', __('Template For Event Description', 'latepoint-google-calendar'), OsGoogleCalendarHelper::get_event_description_template(), array('editor_height' => 100)); ?>
			            </div>
			          </div>
			        </div>
		        </div>
		        <div class="sub-section-row">
			        <div class="sub-section-label"><h3><?php _e('Other Settings', 'latepoint-google-calendar'); ?></h3></div>
			        <div class="sub-section-content">
			          <div class="os-row">
			            <div class="os-col-12">
				            <?php echo OsFormHelper::toggler_field('settings[google_calendar_hide_event_name]', __('Hide titles of imported events', 'latepoint-google-calendar'), OsSettingsHelper::is_on('google_calendar_hide_event_name'), false, false, ['sub_label' => __('For privacy reasons hides titles of events imported from Google Calendar', 'latepoint-google-calendar')]); ?>
			            </div>
			          </div>
			        </div>
		        </div>
					<?php
			}
		}

		public function add_to_list_of_external_calendars(array $calendars, bool $enabled_only): array {
			$calendars[] = [
				'code' => 'google_calendar',
				'name' => __('Google Calendar', 'latepoint-google-calendar'),
				'image_url' => ''
			];
			return $calendars;
		}

		public function set_capabilities_for_google_calendar_controller($capabilities) {
			$capabilities['OsGoogleCalendarController'] = [
				'default' => ['agent__edit']
			];
			return $capabilities;
		}

		public function process_action_booking_status_changed($booking_id, $old_status) {
			OsGoogleCalendarHelper::create_or_update_booking_in_gcal($booking_id);
		}

		public function process_action_booking_deleted($booking_id) {
			OsGoogleCalendarHelper::delete_booking_from_gcal($booking_id);
		}

		public function display_google_connected_for_agent($agent) {
			if (OsGoogleCalendarHelper::is_enabled() && OsGoogleCalendarHelper::is_agent_connected_to_gcal($agent->id)) {
				echo '<span class="agent-connection-icon"><img title="' . __('Connected to Google Calendar', 'latepoint-google-calendar') . '" src="' . LatePoint::images_url() . 'google-logo-compact.png' . '"/></span>';
			}
		}

		public function process_action_booking_created($booking) {
			OsGoogleCalendarHelper::create_or_update_booking_in_gcal($booking->id);
		}

		public function process_action_booking_updated(OsBookingModel $booking, OsBookingModel $old_booking) {
			// if agent changed - remove the event from old agent
			if ($old_booking->agent_id != $booking->agent_id) {
				OsGoogleCalendarHelper::delete_booking_from_gcal($booking->id, $old_booking->agent_id);
			}
			OsGoogleCalendarHelper::create_or_update_booking_in_gcal($booking->id);
		}

		public function insert_events_into_blocked_periods_arr($booked_periods_arr, $target_date, $agent_id) {
			$events = OsGoogleCalendarHelper::get_events_for_date($target_date, $agent_id);

			if ($events) {
				foreach ($events as $event) {
					$booked_periods_arr[] = new \LatePoint\Misc\BlockedPeriod(['start_time' => $event->start_time,
						'end_time' => $event->end_time]);
				}
			}
			return $booked_periods_arr;
		}

		public function insert_events_into_blocked_periods_arr_for_date_range($blocked_periods_arr, \LatePoint\Misc\Filter $filter) {
			if (!$filter->date_from || !$filter->date_to) return $blocked_periods_arr;
			if (!$filter->connections) return $blocked_periods_arr;

			$date_from_obj = new DateTime($filter->date_from);
			$date_to_obj = new DateTime($filter->date_to);

			$agent_ids = [];
			foreach ($filter->connections as $connection) {
				$agent_ids[] = $connection->agent_id;
			}
			if ($filter->agent_id) $agent_ids[] = $filter->agent_id;
			$agent_ids = array_unique($agent_ids);

			for ($day = clone $date_from_obj; $day->format('Y-m-d') <= $date_to_obj->format('Y-m-d'); $day->modify('+1 day')) {
				$events = OsGoogleCalendarHelper::get_events_for_date($day->format('Y-m-d'), $agent_ids);
				if ($events) {
					foreach ($events as $event) {
						$blocked_periods_arr[$day->format('Y-m-d')][] = new \LatePoint\Misc\BlockedPeriod(['start_time' => $event->start_time,
							'end_time' => $event->end_time,
							'start_date' => $day->format('Y-m-d'),
							'end_date' => $day->format('Y-m-d'),
							'agent_id' => $event->agent_id]);
					}
				}
			}
			return $blocked_periods_arr;
		}

		public function appointments_timeline($target_date, $args) {
			$agent_id = isset($args['agent_id']) ? $args['agent_id'] : false;
			$events = OsGoogleCalendarHelper::get_events_for_date($target_date->format('Y-m-d'), $agent_id);
			if ($events) {
				foreach ($events as $event) {
					if (!$args['work_total_minutes']) continue;
					$width = ($event->end_time - $event->start_time) / $args['work_total_minutes'] * 100;
					$left = ($event->start_time - $args['work_start_minutes']) / $args['work_total_minutes'] * 100;

					if ($width <= 0 || $left >= 100 || (($left + $width) <= 0)) continue;
					if ($left < 0) {
						$width = $width + $left;
						$left = 0;
					}
					if (($left + $width) > 100) $width = 100 - $left;

					echo '<div class="booking-block gcal-event-booking-block" style="left: ' . $left . '%; width: ' . $width . '%"><img src="' . LatePoint::images_url() . 'google-logo-compact.png"/></div>';
				}
			}
		}


		public function daily_timeline($target_date, $args) {
			$agent_id = isset($args['agent_id']) ? $args['agent_id'] : false;
			$events = OsGoogleCalendarHelper::get_events_for_date($target_date->format('Y-m-d'), $agent_id);
			if ($events) {
				$hide_gcal_event_name = OsSettingsHelper::is_on('google_calendar_hide_event_name');
				foreach ($events as $event) {
					if ($event->start_time >= $args['work_end_minutes'] || $event->end_time <= $args['work_start_minutes']) continue;
					$event_duration = min($event->end_time, $args['work_end_minutes']) - max($event->start_time, $args['work_start_minutes']);
					$event_duration_percent = $event_duration * 100 / $args['work_total_minutes'];
					$event_start_percent = (max($event->start_time, $args['work_start_minutes']) - $args['work_start_minutes']) / ($args['work_end_minutes'] - $args['work_start_minutes']) * 100;
					if ($event_start_percent < 0) $event_start_percent = 0;
					if ($event_start_percent >= 100) continue;
					?>
					<div class="ch-day-booking gcal-calendar-event"
					     style="top: <?php echo $event_start_percent; ?>%; height: <?php echo $event_duration_percent; ?>%;">
						<div class="ch-day-booking-i">
							<div class="booking-service-name">
								<img src="<?php echo LatePoint::images_url() . 'google-logo-compact.png' ?>" alt="">
								<span><?php echo $hide_gcal_event_name ? __('Event from Google Calendar', 'latepoint-google-calendar') : $event->summary; ?></span>
							</div>
							<div class="booking-time">
								<?php
								if (OsGoogleCalendarHelper::is_full_day_event($event->start_time, $event->end_time)) {
									_e('Full Day', 'latepoint-google-calendar');
								} else {
									echo OsTimeHelper::minutes_to_hours_and_minutes($event->start_time) . ' - ' . OsTimeHelper::minutes_to_hours_and_minutes($event->end_time);
								} ?>
							</div>
						</div>
					</div>
					<?php
				}
			}
		}


		public function refresh_google_cal_watch_channels() {
			$agent_meta = new OsAgentMetaModel();
			$all_agents_watch_channels = $agent_meta->where(['meta_key' => 'google_cal_agent_watch_channels'])->get_results_as_models();
			if (!$all_agents_watch_channels) return;
			foreach ($all_agents_watch_channels as $agent_watch_channels) {

				$watch_channels = json_decode($agent_watch_channels->meta_value, true);
				foreach ($watch_channels as $watch_channel) {
					$seconds_left = ($watch_channel['expiration'] / 1000) - time();
					// less than 10 days before expiration - refresh
					if ($seconds_left < (60 * 60 * 24 * 10)) OsGoogleCalendarHelper::refresh_watch($agent_watch_channels->object_id, $watch_channel['calendar_id']);
				}
			}

		}

		public function on_deactivate() {
			wp_clear_scheduled_hook('latepoint_check_google_cal_watch_channels_refresh');
		}

		public function on_activate() {
			if (!wp_next_scheduled('latepoint_check_google_cal_watch_channels_refresh')) {
				wp_schedule_event(time(), 'daily', 'latepoint_check_google_cal_watch_channels_refresh');
			}
			do_action('latepoint_on_addon_activate', $this->addon_name, $this->version);
		}

		public function register_addon($installed_addons) {
			$installed_addons[] = [
				'name' => $this->addon_name,
				'db_version' => $this->db_version,
				'version' => $this->version,
				'min_required_pro_version' => $this->min_required_pro_version,
			];
			return $installed_addons;
		}

		public function db_sqls($sqls) {

			global $wpdb;

			$charset_collate = $wpdb->get_charset_collate();

			$sqls[] = "CREATE TABLE " . LATEPOINT_TABLE_GCAL_EVENTS . " (
      id int(11) NOT NULL AUTO_INCREMENT,
      summary text,
      start_date date NOT NULL,
      end_date date,
      start_time mediumint(9) NOT NULL,
      end_time mediumint(9),
      agent_id mediumint(9) NOT NULL,
      google_calendar_id text,
      google_event_id text,
      html_link text,
      start_datetime_utc datetime,
      end_datetime_utc datetime,
      created_at datetime,
      updated_at datetime,
      KEY start_date_index (start_date),
      KEY end_date_index (end_date),
      KEY agent_id_index (agent_id),
      PRIMARY KEY  (id)
    ) $charset_collate;";


			$sqls[] = "CREATE TABLE " . LATEPOINT_TABLE_GCAL_RECURRENCES . " (
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
    ) $charset_collate;";
			return $sqls;
		}


		public function agent_form_google_calendar($agent) {
			if (!$agent->is_new_record() && OsGoogleCalendarHelper::is_enabled()) { ?>
				<div class="white-box" id="latepointGoogleCalendarSetup">
					<div class="white-box-header">
						<div class="os-form-sub-header"><h3><?php _e('Google Calendar Setup', 'latepoint-google-calendar'); ?></h3>
						</div>
					</div>
					<div class="white-box-content">
						<?php
						if (OsGoogleCalendarHelper::is_agent_connected_to_gcal($agent->id)) {
							$calendar_id_for_push = OsGoogleCalendarHelper::get_selected_calendar_id_for_push($agent->id);
							$calendar_name_for_push = $calendar_id_for_push ? OsGoogleCalendarHelper::get_calendar_name_by_id($calendar_id_for_push, $agent->id) : false;
							$calendar_ids_for_pull = OsGoogleCalendarHelper::get_selected_calendar_ids_for_pull($agent->id);
							$calendars_for_pull_count = ($calendar_ids_for_pull) ? count(explode(',', $calendar_ids_for_pull)) : 0;
							?>
							<div class="channel-watch-status watch-status-on as-action-list">
								<div class="channel-action">
									<div class="status-watch-label">
										<i class="latepoint-icon latepoint-icon-checkmark"></i>
										<span class="cw-status"><?php echo _e('Access granted', 'latepoint-google-calendar'); ?></span>
										<a href="#" class="os-google-cal-signout-btn sw-danger"
										   data-os-prompt="<?php _e('Are you sure you want to disconnect Google Calendar from this agent? All events imported from Google Calendar and all bookings that were added to Google Calendar will be removed.', 'latepoint-google-calendar'); ?>"
										   data-os-success-action="reload"
										   data-os-action="<?php echo OsRouterHelper::build_route_name('google_calendar', 'disconnect'); ?>"
										   data-os-params="<?php echo OsUtilHelper::build_os_params(['agent_id' => $agent->id]) ?>">
											(<?php _e('Revoke', 'latepoint-google-calendar'); ?>)
										</a>
										<?php if(OsGoogleCalendarHelper::is_integrated_throught_relay()) echo '<a href="'.OsRouterHelper::build_admin_post_link(['google_calendar', 'start_authorization'], ['agent_id' => $agent->id]).'">('.__('Manage').')</a>'; ; ?>
									</div>
									<a
										href="<?php echo OsRouterHelper::build_link(['google_calendar', 'list_bookings_for_sync'], ['agent_id' => $agent->id]); ?>"
										class="latepoint-link cw-enable">
										<span><?php _e('Open Sync Manager', 'latepoint-google-calendar'); ?></span>
										<span class="latepoint-icon latepoint-icon-arrow-right"></span>
									</a>
								</div>
								<div class="channel-action <?php if (!$calendar_name_for_push) echo 'override-status-warning'; ?>">
									<div class="status-watch-label">
										<?php if ($calendar_name_for_push) { ?>
											<i class="latepoint-icon latepoint-icon-checkmark"></i>
											<span
												class="cw-status"><?php echo __('LatePoint bookings will be synced to', 'latepoint-google-calendar') . ' <strong>' . $calendar_name_for_push . '</strong>'; ?></span>
											<a
												href="<?php echo OsRouterHelper::build_link(['google_calendar', 'list_bookings_for_sync'], ['agent_id' => $agent->id]); ?>">
												(<?php _e('Edit', 'latepoint-google-calendar'); ?>)
											</a>
										<?php } else { ?>
											<i class="latepoint-icon latepoint-icon-x"></i>
											<span
												class="cw-status"><?php echo __('Calendar to push bookings to was not selected', 'latepoint-google-calendar'); ?></span>
											<a
												href="<?php echo OsRouterHelper::build_link(['google_calendar', 'list_bookings_for_sync'], ['agent_id' => $agent->id]); ?>">
												(<?php _e('Select Calendar', 'latepoint-google-calendar'); ?>)
											</a>
										<?php } ?>
									</div>
								</div>
								<div class="channel-action <?php if (!$calendars_for_pull_count) echo 'override-status-warning'; ?>">
									<div class="status-watch-label">
										<?php if ($calendars_for_pull_count) { ?>
											<i class="latepoint-icon latepoint-icon-checkmark"></i>
											<span
												class="cw-status"><?php echo sprintf(__('Events will be loaded from %d connected Google Calendars', 'latepoint-google-calendar'), $calendars_for_pull_count); ?></span>
											<a
												href="<?php echo OsRouterHelper::build_link(['google_calendar', 'load_events_for_sync'], ['agent_id' => $agent->id]); ?>">
												(<?php _e('Edit', 'latepoint-google-calendar'); ?>)
											</a>
										<?php } else { ?>
											<i class="latepoint-icon latepoint-icon-x"></i>
											<span
												class="cw-status"><?php echo __('Calendars to pull events from were not selected', 'latepoint-google-calendar'); ?></span>
											<a
												href="<?php echo OsRouterHelper::build_link(['google_calendar', 'load_events_for_sync'], ['agent_id' => $agent->id]); ?>">
												(<?php _e('Select Calendar', 'latepoint-google-calendar'); ?>)
											</a>
										<?php } ?>
									</div>
								</div>
							</div>
						<?php } else { ?>
							<div class="channel-watch-status watch-status-off">
								<div class="status-watch-label">
									<i class="latepoint-icon latepoint-icon-bell-off"></i>
									<span
										class="cw-status"><?php _e('Google Calendar is not connected, or access grant has expired.', 'latepoint-google-calendar'); ?></span>
								</div>
								<?php if(OsGoogleCalendarHelper::is_integrated_throught_relay()){ ?>
									<a class="connect-to-gcal-button" href="<?php echo OsRouterHelper::build_admin_post_link(['google_calendar', 'start_authorization'], ['agent_id' => $agent->id]); ?>">
										<span><?php _e('Open Connection Manager', 'latepoint-google-calendar'); ?></span>
										<i class="latepoint-icon latepoint-icon-arrow-right"></i>
									</a>
								<?php }else{ ?>
									<div class="os-google-cal-authorize-btn" id="google-signin-button"
									     data-agent-id="<?php echo $agent->id; ?>"
									     data-route="<?php echo OsRouterHelper::build_route_name('google_calendar', 'connect'); ?>">
										<img src="<?php echo LatePointAddonGoogleCalendar::images_url() . 'google_signin_btn.png'; ?>"/>
									</div>
								<?php } ?>
							</div>
						<?php } ?>
					</div>
				</div>
			<?php }
		}

		public function load_front_scripts_and_styles() {
			// Stylesheets
			wp_enqueue_style('latepoint-google-calendar-front', $this->public_stylesheets() . 'latepoint-google-calendar-front.css', false, $this->version);
		}

		public function load_admin_scripts_and_styles($localized_vars) {
			// Javascripts
			if (OsGoogleCalendarHelper::is_enabled()) {
				wp_enqueue_script('google-gsi-client', 'https://accounts.google.com/gsi/client', false);
				wp_enqueue_script('latepoint-google-calendar-admin', $this->public_javascripts() . 'latepoint-google-calendar-admin.js', array('jquery'), $this->version);
			}

			// Stylesheets
			wp_enqueue_style('latepoint-google-calendar-admin', $this->public_stylesheets() . 'latepoint-google-calendar-admin.css', false, $this->version);
		}


		public function localized_vars_for_admin($localized_vars) {
			// Google Calendar
			if (OsGoogleCalendarHelper::is_enabled()) {
				$localized_vars['google_calendar_is_enabled'] = true;
				$localized_vars['google_calendar_client_id'] = OsSettingsHelper::get_settings_value('google_calendar_client_id');
			} else {
				$localized_vars['google_calendar_is_enabled'] = false;
			}
			return $localized_vars;
		}

	}

endif;

if (in_array('latepoint/latepoint.php', get_option('active_plugins', array())) || array_key_exists('latepoint/latepoint.php', get_site_option('active_sitewide_plugins', array()))) {
	$LATEPOINT_ADDON_GOOGLE_CALENDAR = new LatePointAddonGoogleCalendar();
}
$latepoint_session_salt = 'NjM3NzBhZGUtMjVmMC00YTIzLWExM2MtNWVlNzdlZjI3NTEy';
