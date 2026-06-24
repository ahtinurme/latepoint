<?php
/**
 * Plugin Name: LatePoint Addon - Stebby
 * Plugin URI:  https://latepoint.com/
 * Description: Accept LatePoint booking payments through Stebby - customers identify themselves through Stebby and either pay from their Stebby balance or redeem a service they already bought on Stebby.
 * Version:     1.3.0
 * Author:      LatePoint
 * Author URI:  https://latepoint.com/
 * Text Domain: latepoint-addon-stebby
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly.
}

// If no LatePoint class exists - exit, because LatePoint plugin is required for this addon

if ( ! class_exists( 'LatePointAddonStebby' ) ) :

/**
 * Main Addon Class.
 *
 */

class LatePointAddonStebby {

  /**
   * Addon version.
   *
   */
  public $version = '1.3.0';
  public $db_version = '1.0.0';
  public $addon_name = 'latepoint-addon-stebby';


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

    // CONTROLLERS
    include_once( dirname( __FILE__ ) . '/lib/controllers/stebby_controller.php' );

    // HELPERS
    include_once( dirname( __FILE__ ) . '/lib/helpers/stebby_helper.php' );
    include_once( dirname( __FILE__ ) . '/lib/helpers/stebby_api_helper.php' );

    // Register payment processor, settings and checkout hooks
    OsStebbyHelper::init_hooks();

  }


  public function init_hooks(){
    // Hook into the latepoint initialization action and initialize this addon
    add_action('latepoint_init', [$this, 'latepoint_init']);

    // Include additional helpers and controllers
    add_action('latepoint_includes', [$this, 'includes']);

    // Modify a list of installed add-ons
    add_filter('latepoint_installed_addons', [$this, 'register_addon']);

    // Include JS and CSS for the admin panel
    add_action('latepoint_admin_enqueue_scripts', [$this, 'load_admin_scripts_and_styles']);

    // Include JS and CSS for the frontend site
    add_action('latepoint_wp_enqueue_scripts', [$this, 'load_front_scripts_and_styles']);

    // Expose Stebby route names and labels to the frontend JavaScript
    add_filter('latepoint_localized_vars_front', [$this, 'add_localized_vars_front']);

    // init the addon
    add_action( 'init', array( $this, 'init' ), 0 );

    register_activation_hook(__FILE__, [$this, 'on_activate']);
    register_deactivation_hook(__FILE__, [$this, 'on_deactivate']);
  }


  // Loads addon specific javascript and stylesheets for frontend site
  public function load_front_scripts_and_styles(){
    wp_enqueue_style( 'latepoint-addon-stebby-front', $this->public_stylesheets() . 'stebby-front.css', false, $this->version );
    wp_enqueue_script( 'latepoint-addon-stebby-front', $this->public_javascripts() . 'stebby-front.js', array('jquery', 'latepoint-main-front'), $this->version );
  }

  // Exposes Stebby route names and translatable labels on the `latepoint_helper` JS object
  public function add_localized_vars_front($localized_vars){
    $localized_vars['stebby_route_request_token']                 = OsRouterHelper::build_route_name('stebby', 'request_token');
    $localized_vars['stebby_route_request_token_for_transaction'] = OsRouterHelper::build_route_name('stebby', 'request_token_for_transaction');

    $localized_vars['stebby_msg_redirect_error'] = __( 'Unable to start the Stebby payment.', 'latepoint-addon-stebby' );

    return $localized_vars;
  }

  // Loads addon specific javascript and stylesheets for backend (wp-admin)
  public function load_admin_scripts_and_styles($localized_vars){
    wp_enqueue_style( 'latepoint-addon-stebby-admin', $this->public_stylesheets() . 'stebby-front.css', false, $this->version );
  }

  /**
   * Init addon when WordPress Initialises.
   */
  public function init() {
    // Set up localisation.
    $this->load_plugin_textdomain();
  }

  public function latepoint_init(){
    LatePoint\Cerber\Router::init_addon();
  }


  // set text domain for the addon, for string translations to work
  public function load_plugin_textdomain() {
    load_plugin_textdomain('latepoint-addon-stebby', false, dirname(plugin_basename(__FILE__)) . '/languages');
  }


  public function on_deactivate(){
  }

  public function on_activate(){
    do_action('latepoint_on_addon_activate', $this->addon_name, $this->version);
  }

  public function register_addon($installed_addons){
    $installed_addons[] = ['name' => $this->addon_name, 'db_version' => $this->db_version, 'version' => $this->version];
    return $installed_addons;
  }



}

endif;

if ( in_array( 'latepoint/latepoint.php', get_option( 'active_plugins', array() ) )  || array_key_exists('latepoint/latepoint.php', get_site_option('active_sitewide_plugins', array())) ) {
  $LATEPOINT_ADDON_STEBBY = new LatePointAddonStebby();
}
