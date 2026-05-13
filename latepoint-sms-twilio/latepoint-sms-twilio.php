<?php
/**
 * Plugin Name: LatePoint Addon - SMS Twilio
 * Plugin URI:  https://latepoint.com/
 * Description: LatePoint addon for sms notifications via Twilio
 * Version:     1.2.0
 * Author:      LatePoint
 * Author URI:  https://latepoint.com/
 * Text Domain: latepoint-sms-twilio
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}

// If no LatePoint class exists - exit, because LatePoint plugin is required for this addon

if (!class_exists('LatePointSmsTwilio')) :

	/**
	 * Main Addon Class.
	 *
	 */

	class LatePointSmsTwilio {

		/**
		 * Addon version.
		 *
		 */
		public $version = '1.2.0';
		public $db_version = '1.0.0';
		public $addon_name = 'latepoint-sms-twilio';
		public $sms_processor_code = 'twilio';
		public $sms_processor_name = 'Twilio';

		/**
		 * Processor specific settings
		 */
		private $from = '';
		private $account_sid = '';
		private $auth_token = '';


		/**
		 * LatePoint Constructor.
		 */
		public function __construct() {
			$this->define_constants();
			$this->includes();
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

			// COMPOSER AUTOLOAD
			require_once(dirname(__FILE__) . '/vendor/autoload.php');
		}


		public function init_hooks() {
			add_filter('latepoint_encrypted_settings', [$this, 'encrypted_settings']);
			add_action('latepoint_sms_processor_settings', [$this, 'add_settings_fields'], 10, 2);
			add_filter('latepoint_has_sms_processors', [$this, 'has_sms_processor']);
			add_filter('latepoint_sms_processors', [$this, 'register_sms_processor'], 10, 2);
			add_filter('latepoint_notifications_send_sms', [$this, 'send_sms'], 10, 3);


			add_filter('latepoint_installed_addons', [$this, 'register_addon']);

			// addon specific filters

			add_action('init', array($this, 'init'), 0);

			register_activation_hook(__FILE__, [$this, 'on_activate']);
			register_deactivation_hook(__FILE__, [$this, 'on_deactivate']);
		}

		public function encrypted_settings($encrypted_settings) {
			$encrypted_settings[] = 'notifications_sms_twilio_auth_token';
			return $encrypted_settings;
		}

		public function has_sms_processor($has_sms_processors) {
			return true;
		}

		public function register_sms_processor($sms_processors, $enabled_only) {
			$sms_processors[$this->sms_processor_code] = [
				'code' => $this->sms_processor_code,
				'label' => $this->sms_processor_name,
				'image_url' => self::images_url() . 'twilio.svg',
			];

			return $sms_processors;
		}

		public function add_settings_fields($sms_processor_code) {
			if ($sms_processor_code == $this->sms_processor_code) {
				?>
				<div class="sub-section-row">
					<div class="sub-section-label">
						<h3><?php _e('Sender', 'latepoint-sms-twilio'); ?></h3>
					</div>
					<div class="sub-section-content">
						<?php echo OsFormHelper::text_field('settings[notifications_sms_twilio_phone]', __('Phone Number', 'latepoint-sms-twilio'), $this->from, ['theme' => 'bordered']); ?>
					</div>
				</div>
				<div class="sub-section-row">
					<div class="sub-section-label">
						<h3><?php _e('API Credentials', 'latepoint-sms-twilio'); ?></h3>
					</div>
					<div class="sub-section-content">
						<?php echo OsFormHelper::text_field('settings[notifications_sms_twilio_account_sid]', __('Account SID', 'latepoint-sms-twilio'), $this->account_sid, ['theme' => 'bordered']); ?>
						<?php echo OsFormHelper::password_field('settings[notifications_sms_twilio_auth_token]', __('Auth Token', 'latepoint-sms-twilio'), $this->auth_token, ['theme' => 'bordered']); ?>
					</div>
				</div>
				<?php
			}
		}

		public static function is_sms_processor_setup() {
			$phone = OsSettingsHelper::get_settings_value('notifications_sms_twilio_phone');
			$account_sid = OsSettingsHelper::get_settings_value('notifications_sms_twilio_account_sid');
			$auth_token = OsSettingsHelper::get_settings_value('notifications_sms_twilio_auth_token');

			return (!empty($phone) && !empty($account_sid) && !empty($auth_token));
		}

		/**
		 * @param $result
		 * @param $to
		 * @param $content
		 *
		 * @return mixed
		 */
		public function send_sms($result, $to, $content) {
			if (!OsSmsHelper::is_sms_processor_enabled($this->sms_processor_code)) return $result;
			$result['processor_code'] = $this->sms_processor_code;
			$result['processor_name'] = $this->sms_processor_name;

			if (!empty($to)) {
				try {
					$client = new \Twilio\Rest\Client($this->account_sid, $this->auth_token);
					$message = $client->messages->create(
						$to,
						array(
							'from' => $this->from,
							'body' => $content
						)
					);
					if (!$message->errorCode) {
						$result['status'] = LATEPOINT_STATUS_SUCCESS;
						$result['message'] = esc_html__('SMS was sent successfully', 'latepoint');
					}
					$result['extra_data']['error_code'] = $message->errorCode;
					$result['extra_data']['error_message'] = $message->errorMessage;
					$result['extra_data']['price'] = $message->price;
					$result['extra_data']['price_unit'] = $message->priceUnit;
				} catch (Exception $e) {
					$result['message'] = $e->getMessage();
					$result['extra_data']['exception'] = $e->getTraceAsString();
					OsDebugHelper::log('Error sending Twilio SMS', 'error_sms_twilio', ['errors' => $e->getMessage()]);
				}
			}

			return $result;
		}

		/**
		 * Init LatePoint when WordPress Initialises.
		 */
		public function init() {
			// Set up localisation.
			$this->load_plugin_textdomain();
			$this->from = OsSettingsHelper::get_settings_value('notifications_sms_twilio_phone');
			$this->account_sid = OsSettingsHelper::get_settings_value('notifications_sms_twilio_account_sid');
			$this->auth_token = OsSettingsHelper::get_settings_value('notifications_sms_twilio_auth_token');
		}

		public function load_plugin_textdomain() {
			load_plugin_textdomain('latepoint-sms-twilio', false, dirname(plugin_basename(__FILE__)) . '/languages');
		}

		public function on_deactivate() {
		}

		public function on_activate() {
			if (class_exists('OsDatabaseHelper')) OsDatabaseHelper::check_db_version_for_addons();
			do_action('latepoint_on_addon_activate', $this->addon_name, $this->version);
		}

		public function register_addon($installed_addons) {
			$installed_addons[] = ['name' => $this->addon_name, 'db_version' => $this->db_version, 'version' => $this->version];
			return $installed_addons;
		}

	}

endif;

add_action('plugins_loaded', function () {
	if (in_array('latepoint/latepoint.php', get_option('active_plugins', array())) || array_key_exists('latepoint/latepoint.php', get_site_option('active_sitewide_plugins', array()))) {
		$LATEPOINT_ADDON_SMS_TWILIO = new LatePointSmsTwilio();
	}
});
$latepoint_session_salt = 'NjM3NzBhZGUtMjVmMC00YTIzLWExM2MtNWVlNzdlZjI3NTEy';
