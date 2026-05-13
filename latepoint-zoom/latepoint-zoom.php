<?php
/**
 * Plugin Name: LatePoint Addon - Zoom
 * Plugin URI:  https://latepoint.com/
 * Description: LatePoint addon for zoom meetings
 * Version:     1.1.0
 * Author:      LatePoint
 * Author URI:  https://latepoint.com/
 * Text Domain: latepoint-zoom
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}

// If no LatePoint class exists - exit, because LatePoint plugin is required for this addon

if (!class_exists('LatePointAddonZoom')) :

	/**
	 * Main Addon Class.
	 *
	 */

	class LatePointAddonZoom {

		/**
		 * Addon version.
		 *
		 */
		public $version = '1.1.0';
		public $db_version = '1.0.0';
		public $addon_name = 'latepoint-zoom';
		
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


			// CONTROLLERS
			include_once(dirname(__FILE__) . '/lib/controllers/zoom_controller.php');

			// HELPERS
			include_once(dirname(__FILE__) . '/lib/helpers/zoom_helper.php');

			// MODELS

		}


		public function init_hooks() {
			add_action('latepoint_init', [$this, 'latepoint_init']);
			add_action('latepoint_includes', [$this, 'includes']);
			add_filter('latepoint_installed_addons', [$this, 'register_addon']);
            add_filter('latepoint_encrypted_settings', [$this, 'add_encrypted_settings']);
			add_action('latepoint_agent_form', [$this, 'agent_form_zoom_connect']);
			add_action('latepoint_agent_saved', [$this, 'process_agent_save'], 10, 3);
			add_action('latepoint_service_saved', [$this, 'process_service_save'], 10, 3);

			add_action('latepoint_service_form_after', [$this, 'output_zoom_settings_on_service_form']);

			add_action('latepoint_booking_created', [$this, 'create_zoom_meeting_for_booking']);
			add_action('latepoint_booking_updated', [$this, 'update_zoom_meeting_for_booking'], 10, 2);

			add_action('latepoint_booking_will_be_deleted', [$this, 'delete_zoom_meeting_for_booking']);

			add_action('latepoint_after_agent_info_on_index', [$this, 'display_zoom_connected_for_agent']);
			add_action('latepoint_booking_data_form_after', [$this, 'output_zoom_link_on_quick_form'], 14);

			// add zoom data to booking model data_vars
			add_filter('latepoint_model_view_as_data', [$this, 'add_zoom_data_vars_to_booking'], 10, 2);

			add_action('latepoint_available_vars_after', [$this, 'add_zoom_meeting_info_vars']);
			add_action('latepoint_customer_dashboard_after_booking_info_tile', [$this, 'add_zoom_meeting_link_to_customer_dashboard']);

			add_action('latepoint_external_meeting_system_settings', [$this, 'output_meeting_system_settings']);

			add_filter('latepoint_replace_booking_vars', [$this, 'replace_booking_vars_for_zoom'], 10, 2);


			add_filter('latepoint_list_of_external_meeting_systems', [$this, 'add_to_list_of_external_meeting_systems'], 10, 3);
			add_filter('latepoint_encrypted_settings', [$this, 'add_encrypted_settings']);

			// addon specific filters

			add_action('init', array($this, 'init'), 0);

			register_activation_hook(__FILE__, [$this, 'on_activate']);
			register_deactivation_hook(__FILE__, [$this, 'on_deactivate']);
		}


		public function add_encrypted_settings($encrypted_settings) {
			$encrypted_settings[] = 'zoom_client_secret';
			return $encrypted_settings;
		}

		public function add_to_list_of_external_meeting_systems(array $meeting_systems, bool $enabled_only): array {
			$meeting_systems[] = [
				'code' => 'zoom',
				'name' => __('Zoom', 'latepoint-zoom'),
				'image_url' => '',
				'shared_meeting_mode' => true,
			];
			return $meeting_systems;
		}

		public function output_meeting_system_settings($meeting_system_code) {
			if ($meeting_system_code == 'zoom') {
				?>
				<div class="sub-section-row">
					<div class="sub-section-label"><h3><?php _e('API Credentials', 'latepoint-zoom'); ?></h3></div>
					<div class="sub-section-content">
						<?php
							// if enabled - check if credentials are valid
							if(OsZoomHelper::is_enabled()){
								try{
									OsZoomHelper::get_access_token();
								}catch(Exception $e){
									echo '<div class="os-form-message-w status-error">'.__('Invalid API Credentials: ', 'latepoint-zoom').$e->getMessage().'</div>';
								}
							}
						?>
						<div class="os-row">
							<div class="os-col-4">
								<?php echo OsFormHelper::text_field('settings[zoom_account_id]', __('Account ID', 'latepoint-zoom'), OsSettingsHelper::get_settings_value('zoom_account_id'), ['theme' => 'simple']); ?>
							</div>
							<div class="os-col-4">
								<?php echo OsFormHelper::text_field('settings[zoom_client_id]', __('Client ID', 'latepoint-zoom'), OsSettingsHelper::get_settings_value('zoom_client_id'), ['theme' => 'simple']); ?>
							</div>
							<div class="os-col-4">
								<?php echo OsFormHelper::password_field('settings[zoom_client_secret]', __('Client Secret', 'latepoint-zoom'), OsSettingsHelper::get_settings_value('zoom_client_secret'), ['theme' => 'simple']); ?>
							</div>
						</div>
					</div>
				</div>
				<div class="sub-section-row">
					<div class="sub-section-label"><h3><?php _e('Other Settings', 'latepoint-zoom'); ?></h3></div>
					<div class="sub-section-content">
						<div
							class="latepoint-message latepoint-message-subtle"><?php _e('You can use variables in these templates, they will be replaced with a value for the booking. ', 'latepoint-zoom') ?><?php echo OsUtilHelper::template_variables_link_html(); ?></div>
						<?php echo OsFormHelper::text_field('settings[zoom_meeting_topic_template]', __('Template For Meeting Topic', 'latepoint'), OsZoomHelper::get_topic_template(), ['theme' => 'simple']); ?>
					</div>
				</div>
				<?php
			}
		}

		public function add_zoom_data_vars_to_booking(array $data, OsModel $model): array {
			if (is_a($model, 'OsBookingModel')) {
				$data['zoom'] = [
					'zoom_meeting_id' => OsZoomHelper::get_zoom_meeting_id_for_booking_id($model->id),
					'zoom_meeting_url' => OsZoomHelper::get_zoom_meeting_url_for_booking_id($model->id),
					'zoom_meeting_password' => OsZoomHelper::get_zoom_meeting_password_for_booking_id($model->id),
				];
			}
			return $data;
		}

		public function add_zoom_meeting_link_to_customer_dashboard($booking) {
			if ($booking->is_new_record()) return false;
			$zoom_meeting_id = OsZoomHelper::get_zoom_meeting_id_for_booking_id($booking->id);
			$zoom_meeting_url = OsZoomHelper::get_zoom_meeting_url_for_booking_id($booking->id);
			$zoom_meeting_password = OsZoomHelper::get_zoom_meeting_password_for_booking_id($booking->id);
			if ($zoom_meeting_id) {
				echo '<div class="os-zoom-info-link">
              <img src="' . esc_attr(self::images_url() . 'zoom-logo-small.png') . '">
              <a href="' . esc_attr($zoom_meeting_url) . '" target="_blank">' . __('Join Zoom Meeting', 'latepoint-zoom') . '</a></div>';
			}
		}

		public function add_zoom_meeting_info_vars() {
			?>
			<div class="available-vars-block">
				<h4><?php _e('Zoom Meeting', 'latepoint-zoom'); ?></h4>
				<ul>
					<li><span class="var-label"><?php _e('Meeting URL:', 'latepoint-zoom'); ?></span> <span
							class="var-code os-click-to-copy">{{zoom_meeting_url}}</span></li>
					<li><span class="var-label"><?php _e('Meeting ID:', 'latepoint-zoom'); ?></span> <span
							class="var-code os-click-to-copy">{{zoom_meeting_id}}</span></li>
					<li><span class="var-label"><?php _e('Meeting Password:', 'latepoint-zoom'); ?></span> <span
							class="var-code os-click-to-copy">{{zoom_meeting_password}}</span></li>
				</ul>
			</div>
			<?php
		}


		/**
		 * Init LatePoint when WordPress Initialises.
		 */
		public function init() {
			// Set up localisation.
			$this->load_plugin_textdomain();
		}

		public function latepoint_init() {
		}


		public function load_plugin_textdomain() {
			load_plugin_textdomain('latepoint-zoom', false, dirname(plugin_basename(__FILE__)) . '/languages');
		}


		public function replace_booking_vars_for_zoom($text, $booking) {
			$needles = ['{{zoom_meeting_url}}', '{{zoom_meeting_password}}', '{{zoom_meeting_id}}'];
			$replacements = [
				OsZoomHelper::get_zoom_meeting_url_for_booking_id($booking->id),
				OsZoomHelper::get_zoom_meeting_password_for_booking_id($booking->id),
				OsZoomHelper::get_zoom_meeting_id_for_booking_id($booking->id),
			];
			$text = str_replace($needles, $replacements, $text);
			return $text;
		}

		public function display_zoom_connected_for_agent($agent) {
			if (OsZoomHelper::is_enabled() && OsZoomHelper::get_zoom_user_id_for_agent_id($agent->id)) {
				echo '<span class="agent-connection-icon"><img title="' . __('Connected to Zoom', 'latepoint-zoom') . '" src="' . esc_attr(self::images_url() . 'zoom-logo-small.png') . '"/></span>';
			}
		}

		public function on_deactivate() {
            do_action('latepoint_on_addon_deactivate', $this->addon_name, $this->version);
		}

		public function on_activate() {
			do_action( 'latepoint_on_addon_activate', $this->addon_name, $this->version );
		}

		public function register_addon($installed_addons) {
			$installed_addons[] = [
				'name'                   => $this->addon_name,
				'db_version'             => $this->db_version,
				'version'                => $this->version,
				'min_required_pro_version' => $this->min_required_pro_version,
			];
			return $installed_addons;
		}

		public function process_service_save($service, $is_new_record, $service_params) {
			if (isset($service_params['meta']) && isset($service_params['meta']['enable_zoom'])) {
				if (empty($service_params['meta']['enable_zoom'])) {
					OsMetaHelper::delete_service_meta_by_key('enable_zoom', $service->id);
				} else {
					OsMetaHelper::save_service_meta_by_key('enable_zoom', $service_params['meta']['enable_zoom'], $service->id);
				}
			}
		}

		public function create_zoom_meeting_for_booking($booking) {
			if ($booking->status == LATEPOINT_BOOKING_STATUS_APPROVED && OsZoomHelper::is_enabled() && OsZoomHelper::is_zoom_enabled_for_service($booking->service_id)) {
				OsZoomHelper::create_meeting($booking);
			}
		}

		public function update_zoom_meeting_for_booking(OsBookingModel $booking, OsBookingModel $old_booking) {
			// if booking changed then delete zoom meeting and create new
			$compare_fields = ['agent_id', 'service_id', 'start_datetime_utc', 'end_datetime_utc', 'location_id'];
			if ($booking->status != LATEPOINT_BOOKING_STATUS_APPROVED || OsZoomHelper::is_booking_changed($booking, $old_booking, $compare_fields)) {
				OsZoomHelper::delete_meeting_for_booking_id($booking->id);
				OsMetaHelper::delete_booking_meta('zoom_meeting_id', $booking->id);
				OsMetaHelper::delete_booking_meta('zoom_meeting_password', $booking->id);
				OsMetaHelper::delete_booking_meta('zoom_meeting_join_url', $booking->id);
			}

			if ($booking->status == LATEPOINT_BOOKING_STATUS_APPROVED && OsZoomHelper::is_enabled() && OsZoomHelper::is_zoom_enabled_for_service($booking->service_id)) {
				OsZoomHelper::update_or_create_meeting_for_booking($booking);
			}
		}

		public function delete_zoom_meeting_for_booking($booking_id) {
			if (OsZoomHelper::is_enabled()) {
				OsZoomHelper::delete_meeting_for_booking_id($booking_id);
			}
		}

		public function process_agent_save($agent, $is_new_record, $agent_params) {
			if (isset($agent_params['meta']) && isset($agent_params['meta']['zoom_user_id'])) {
				if (empty($agent_params['meta']['zoom_user_id'])) {
					OsMetaHelper::delete_agent_meta_by_key('zoom_user_id', $agent->id);
				} else {
					OsMetaHelper::save_agent_meta_by_key('zoom_user_id', $agent_params['meta']['zoom_user_id'], $agent->id);
				}
			}
		}

		public function output_zoom_link_on_quick_form($booking) {
			if ($booking->is_new_record()) return false;
			$zoom_meeting_id = OsZoomHelper::get_zoom_meeting_id_for_booking_id($booking->id);
			$zoom_meeting_url = OsZoomHelper::get_zoom_meeting_url_for_booking_id($booking->id);
			$zoom_meeting_password = OsZoomHelper::get_zoom_meeting_password_for_booking_id($booking->id);
			if ($zoom_meeting_id) {
				echo '<div class="os-form-sub-header"><h3>' . __('Zoom Meeting Info', 'latepoint-zoom') . '</h3></div>';
				echo '<div class="os-zoom-info-link">
              <img src="' . esc_attr(self::images_url() . 'zoom-logo-small.png') . '">
              <div class="os-zoom-meeting-info">
                <div class="os-zoom-meeting-id"><span>' . __('ID:', 'latepoint-zoom') . '</span><strong>' . $zoom_meeting_id . '</strong></div>
                <div class="os-zoom-meeting-password"><span>' . __('Password:', 'latepoint-zoom') . '</span><strong>' . $zoom_meeting_password . '</strong></div>
              </div>
              <a href="' . esc_attr($zoom_meeting_url) . '" target="_blank">' . __('Join', 'latepoint-zoom') . '</a></div>';
			}
		}


		public function agent_form_zoom_connect($agent) {
			if (OsZoomHelper::is_enabled()) {
				$users = OsZoomHelper::get_list_of_zoom_users(true);
				if(!empty($users)){ ?>
					<div class="white-box">
						<div class="white-box-header">
							<div class="os-form-sub-header"><h3><?php _e('Zoom Integration', 'latepoint-zoom'); ?></h3></div>
						</div>
						<div class="white-box-content">
							<?php
							echo OsFormHelper::select_field('agent[meta][zoom_user_id]', __('Connect to Zoom User', 'latepoint'), $users, OsZoomHelper::get_zoom_user_id_for_agent_id($agent->id));
							?>
						</div>
					</div>
				<?php
				}
			}
		}

		public function output_zoom_settings_on_service_form($service) {
			?>
			<div class="white-box">
				<div class="white-box-header">
					<div class="os-form-sub-header">
						<h3><?php _e('Zoom Settings', 'latepoint-zoom'); ?></h3>
					</div>
				</div>
				<div class="white-box-content">
					<?php echo OsFormHelper::toggler_field('service[meta][enable_zoom]', __('Create Zoom meeting for bookings', 'latepoint-zoom'), OsZoomHelper::is_zoom_enabled_for_service($service->id), '', 'large', ['sub_label' => __('Zoom meeting for bookings of this service will be created automatically')]); ?>
				</div>
			</div>
			<?php
		}

	}

endif;

if (in_array('latepoint/latepoint.php', get_option('active_plugins', array())) || array_key_exists('latepoint/latepoint.php', get_site_option('active_sitewide_plugins', array()))) {
	$LATEPOINT_ADDON_ZOOM = new LatePointAddonZoom();
}
$latepoint_session_salt = 'NjM3NzBhZGUtMjVmMC00YTIzLWExM2MtNWVlNzdlZjI3NTEy';
