<?php
/**
 * Plugin Name: LatePoint Addon - Whatsapp by Meta
 * Plugin URI:  https://latepoint.com/
 * Description: LatePoint addon for whatsapp notifications
 * Version:     1.1.0
 * Author:      LatePoint
 * Author URI:  https://latepoint.com/
 * Text Domain: latepoint-whatsapp-meta
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// If no LatePoint class exists - exit, because LatePoint plugin is required for this addon

if ( ! class_exists( 'LatePointWhatsappMeta' ) ) :

	/**
	 * Main Addon Class.
	 *
	 */

	class LatePointWhatsappMeta {

		/**
		 * Addon version.
		 *
		 */
		public $version = '1.1.0';
		public $db_version = '1.0.0';
		public $addon_name = 'latepoint-whatsapp-meta';
		public $whatsapp_processor_code = 'whatsapp-meta';
		public $whatsapp_processor_name = 'Whatsapp by Meta';



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
			return plugin_dir_url( __FILE__ ) . 'public/stylesheets/';
		}

		public static function public_javascripts() {
			return plugin_dir_url( __FILE__ ) . 'public/javascripts/';
		}

		public static function images_url() {
			return plugin_dir_url( __FILE__ ) . 'public/images/';
		}

		/**
		 * Define constant if not already set.
		 *
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

			// HELPERS
			include_once( dirname( __FILE__ ) . '/lib/helpers/whatsapp_meta_helper.php' );
		}


		public function init_hooks() {
			add_action( 'latepoint_whatsapp_processor_settings', [ $this, 'add_settings_fields' ], 10, 2 );
			add_filter( 'latepoint_has_whatsapp_processors', [ $this, 'has_whatsapp_processor' ] );
			add_filter( 'latepoint_whatsapp_processors', [ $this, 'register_whatsapp_processor' ], 10, 2 );
			add_filter( 'latepoint_notifications_send_whatsapp', [ $this, 'send_whatsapp' ], 10, 4 );

			add_filter( 'latepoint_encrypted_settings', [ $this, 'add_encrypted_settings' ] );


			add_filter( 'latepoint_installed_addons', [ $this, 'register_addon' ] );
			add_filter( 'latepoint_get_whatsapp_templates', 'OsWhatsappMetaHelper::get_templates' );
			add_filter( 'latepoint_whatsapp_business_id', 'OsWhatsappMetaHelper::get_business_account_id' );

			add_action( 'latepoint_admin_enqueue_scripts', [ $this, 'load_admin_scripts_and_styles' ] );

			// addon specific filters

			add_action( 'init', array( $this, 'init' ), 0 );

			register_activation_hook( __FILE__, [ $this, 'on_activate' ] );
			register_deactivation_hook( __FILE__, [ $this, 'on_deactivate' ] );

		}


		public function load_admin_scripts_and_styles( $localized_vars ) {
			wp_enqueue_script( 'latepoint-whatsapp-meta-admin', self::public_javascripts() . 'latepoint-whatsapp-meta-admin.js', [ 'jquery' ], $this->version );
			wp_enqueue_style( 'latepoint-whatsapp-meta-admin', self::public_stylesheets() . 'latepoint-whatsapp-meta-admin.css', false, $this->version );
		}


		public function add_encrypted_settings( $encrypted_settings ) {
			$encrypted_settings[] = 'notifications_whatsapp_meta_application_token';
			$encrypted_settings[] = 'notifications_whatsapp_meta_system_user_access_token';

			return $encrypted_settings;
		}

		public function has_whatsapp_processor( $has_whatsapp_processors ) {
			return true;
		}

		public function register_whatsapp_processor( $whatsapp_processors, $enabled_only ) {
			$whatsapp_processors[ $this->whatsapp_processor_code ] = [
				'code'      => $this->whatsapp_processor_code,
				'label'     => $this->whatsapp_processor_name,
				'image_url' => self::images_url() . 'whatsapp.svg',
			];

			return $whatsapp_processors;
		}

		public function add_settings_fields( $whatsapp_processor_code ) {
			if ( $whatsapp_processor_code == $this->whatsapp_processor_code ) {
				?>
                <div class="sub-section-row">
                    <div class="sub-section-label">
                        <h3><?php _e( 'API Credentials', 'latepoint-whatsapp-meta' ); ?></h3>
                    </div>
                    <div class="sub-section-content">
                        <div class="os-row">
                            <div class="os-col-lg-3">
								<?php echo OsFormHelper::text_field( 'settings[notifications_whatsapp_meta_phone_number_id]', __( 'Phone Number ID', 'latepoint-whatsapp-meta' ), OsWhatsappMetaHelper::get_phone_number_id(), [ 'theme' => 'simple' ] ); ?>
                            </div>
                            <div class="os-col-lg-3">
								<?php echo OsFormHelper::text_field( 'settings[notifications_whatsapp_meta_business_account_id]', __( 'Business Account ID', 'latepoint-whatsapp-meta' ), OsWhatsappMetaHelper::get_business_account_id(), [ 'theme' => 'simple' ] ); ?>
                            </div>
                            <div class="os-col-lg-6">
								<?php echo OsFormHelper::password_field( 'settings[notifications_whatsapp_meta_system_user_access_token]', __( 'System User Access Token', 'latepoint-whatsapp-meta' ), OsWhatsappMetaHelper::get_system_user_access_token(), [ 'theme' => 'simple' ] ); ?>
                            </div>
                        </div>
                    </div>
                </div>
				<?php
			}
		}

		public static function is_whatsapp_processor_setup() {
			$phone_number_id   = OsSettingsHelper::get_settings_value( 'notifications_whatsapp_meta_phone_number_id' );
			$application_token = OsSettingsHelper::get_settings_value( 'notifications_whatsapp_meta_application_token' );

			return ( ! empty( $phone_number_id ) && ! empty( $application_token ) );
		}

		/**
		 * @param array $result
		 * @param string $to
		 * @param array $data
		 * @param array $activity_data
		 *
		 * @return array
		 */
		public function send_whatsapp( array $result, string $to, array $data, array $activity_data ) : array {
			if ( ! OsWhatsappHelper::is_whatsapp_processor_enabled( $this->whatsapp_processor_code ) ) {
				return $result;
			}
			$result['processor_code'] = $this->whatsapp_processor_code;
			$result['processor_name'] = $this->whatsapp_processor_name;

			if ( ! empty( $to ) ) {
				try {
                    if(OsWhatsappMetaHelper::send_whatsapp_message($to, $data)){
                        $result['status'] = LATEPOINT_STATUS_SUCCESS;
                        $result['message'] = __('WhatsApp Message Sent Successfully', 'latepoint-whatsapp-meta');
                    }
				} catch ( Exception $e ) {
					$result['message']                 = $e->getMessage();
					$result['extra_data']['exception'] = $e->getTraceAsString();
					OsDebugHelper::log( 'Error sending Meta Whatsapp message', 'error_whatsapp_meta', [ 'errors' => $e->getMessage() ] );
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
		}

		public function load_plugin_textdomain() {
			load_plugin_textdomain( 'latepoint-whatsapp-meta', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
		}

		public function on_deactivate() {
		}

		public function on_activate() {
			do_action( 'latepoint_on_addon_activate', $this->addon_name, $this->version );
		}

		public function register_addon( $installed_addons ) {
			$installed_addons[] = [ 'name' => $this->addon_name, 'db_version' => $this->db_version, 'version' => $this->version ];

			return $installed_addons;
		}

	}

endif;

add_action( 'plugins_loaded', function () {
	if ( in_array( 'latepoint/latepoint.php', get_option( 'active_plugins', array() ) ) || array_key_exists( 'latepoint/latepoint.php', get_site_option( 'active_sitewide_plugins', array() ) ) ) {
		$LATEPOINT_ADDON_WHATSAPP_META = new LatePointWhatsappMeta();
	}
} );
$latepoint_session_salt = 'NjM3NzBhZGUtMjVmMC00YTIzLWExM2MtNWVlNzdlZjI3NTEy';
