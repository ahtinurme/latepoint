<?php
/**
 * Plugin Name: LatePoint Addon - Shortener Short.io
 * Plugin URI:  https://latepoint.com/
 * Description: LatePoint addon for generating short links using Short.io.
 * Version:     1.0.0
 * Author:      LatePoint
 * Author URI:  https://latepoint.com/
 * Text Domain: latepoint-shortener-shortio
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// If no LatePoint class exists - exit, because LatePoint plugin is required for this addon

if ( ! class_exists( 'LatepointShortenerShortio' ) ) :

	/**
	 * Main Addon Class.
	 *
	 */

	class LatepointShortenerShortio {

		/**
		 * Addon version.
		 *
		 */
		public $version = '1.0.0';
		public $db_version = '1.0.0';
		public $addon_name = 'latepoint-shortener-shortio';
		public $processor_code = 'short_io';




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
            if ( ! defined( 'LATEPOINT_SHORT_IO_CONNECT_URL' ) ) {
                define( 'LATEPOINT_SHORT_IO_CONNECT_URL', 'https://api.short.io/links' );
            }
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
			include_once( __DIR__ . '/lib/helpers/shortener_shortio_helper.php' );
		}


		public function init_hooks() {

			add_action('latepoint_external_short_links_system_settings', [$this, 'output_short_links_settings']);
			add_filter('latepoint_list_of_external_short_links_systems', [$this, 'add_to_list_of_external_short_links_systems'], 10, 3);

            add_filter('latepoint_replace_all_vars_in_template', 'OsShortenerShortioHelper::short_content_links', 10, 2);


			add_action( 'init', array( $this, 'init' ), 0 );
			register_activation_hook( __FILE__, [ $this, 'on_activate' ] );
			register_deactivation_hook( __FILE__, [ $this, 'on_deactivate' ] );
		}


		/**
		 * Init LatePoint when WordPress Initialises.
		 */
		public function init() {
			$this->load_plugin_textdomain();
		}

		public function load_plugin_textdomain() {
			load_plugin_textdomain( 'latepoint-shortener-shortio', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
		}

		public function on_deactivate() {}

		public function on_activate() {
			do_action( 'latepoint_on_addon_activate', $this->addon_name, $this->version );
		}

		public function register_addon( $installed_addons ) {
			$installed_addons[] = [ 'name' => $this->addon_name, 'db_version' => $this->db_version, 'version' => $this->version ];

			return $installed_addons;
		}

		public function add_to_list_of_external_short_links_systems(array $short_links_systems, bool $enabled_only): array {
			$short_links_systems[] = [
				'code' => $this->processor_code,
				'name' => __('Short.io', 'latepoint-shortener-shortio'),
                'image_url' => self::images_url() . 'shortener-shortio-logo.png',
			];
			return $short_links_systems;
		}

        public function output_short_links_settings( string $short_links_code ) {
            if ( $short_links_code === $this->processor_code ) { ?>
                <div class="sub-section-row">
                    <div class="sub-section-label"><h3><?php _e( 'API Credentials', 'latepoint-shortener-shortio' ); ?></h3>
                    </div>
                    <div class="sub-section-content">
                        <div class="latepoint-message latepoint-message-subtle">
                            <?php esc_html_e( 'To use the short link service, you need to set an API key and a domain. You can generate both in your Short.io account settings.', 'latepoint-shortener-shortio' ); ?>
                        </div>
                        <div class="os-row">
                            <div class="os-col-12">
                                <?php echo OsFormHelper::text_field( 'settings[shortio_api_key]', __( 'Api Key', 'latepoint-shortener-shortio' ), OsShortenerShortioHelper::get_api_key(), [ 'theme' => 'bordered' ] ); ?>
                                <?php echo OsFormHelper::text_field( 'settings[shortio_api_domain]', __( 'Domain', 'latepoint-shortener-shortio' ), OsShortenerShortioHelper::get_domain(), [ 'theme' => 'bordered' ] ); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <?php
            }
        }

	}

endif;

add_action( 'plugins_loaded', function () {
	if ( in_array( 'latepoint/latepoint.php', get_option( 'active_plugins', array() ) ) || array_key_exists( 'latepoint/latepoint.php', get_site_option( 'active_sitewide_plugins', array() ) ) ) {
		new LatepointShortenerShortio();
	}
} );
$latepoint_session_salt = 'NjM3NzBhZGUtMjVmMC00YTIzLWExM2MtNWVlNzdlZjI3NTEy';
