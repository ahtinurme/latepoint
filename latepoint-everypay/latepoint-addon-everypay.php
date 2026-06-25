<?php
/**
 * Plugin Name: LatePoint Addon - EveryPay
 * Plugin URI:  https://latepoint.com/
 * Description: Accept LatePoint booking and invoice payments through the EveryPay hosted payment page, with server-side payment verification. Refunds are handled manually in EveryPay, not from the LatePoint admin.
 * Version:     1.0.1
 * Author:      LatePoint
 * Author URI:  https://latepoint.com/
 * Text Domain: latepoint-addon-everypay
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly.
}

// If no LatePoint class exists - exit, because LatePoint plugin is required for this addon

if ( ! class_exists( 'LatePointAddonEverypay' ) ) :

/**
 * Main Addon Class.
 *
 */

class LatePointAddonEverypay {

  /**
   * Addon version.
   *
   */
  public $version = '1.0.1';
  public $db_version = '1.0.0';
  public $addon_name = 'latepoint-addon-everypay';


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
    // $this->define( 'LATEPOINT_ADDON_EVERYPAY_EXAMPLE_CONSTANT', 'example' );
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
    include_once( dirname( __FILE__ ) . '/lib/controllers/everypay_controller.php' );

    // HELPERS
    include_once( dirname( __FILE__ ) . '/lib/helpers/everypay_helper.php' );
    include_once( dirname( __FILE__ ) . '/lib/helpers/everypay_api_helper.php' );

    // MODELS
    // include_once(dirname( __FILE__ ) . '/lib/models/example_model.php' );

    // Register payment processor, settings and checkout hooks
    OsEverypayHelper::init_hooks();

  }


  public function init_hooks(){
    // Hook into the latepoint initialization action and initialize this addon
    add_action('latepoint_init', [$this, 'latepoint_init']);

    // Include additional helpers and controllers
    add_action('latepoint_includes', [$this, 'includes']);

    // Modify a list of installed add-ons
    add_filter('latepoint_installed_addons', [$this, 'register_addon']);

    // Add a link to the side menu
    // add_filter('latepoint_side_menu', [$this, 'add_menu_links']);

    // Include JS and CSS for the admin panel
    // add_action('latepoint_admin_enqueue_scripts', [$this, 'load_admin_scripts_and_styles']);

    // Include JS and CSS for the frontend site
    add_action('latepoint_wp_enqueue_scripts', [$this, 'load_front_scripts_and_styles']);

    // Expose EveryPay route names to the frontend JavaScript
    add_filter('latepoint_localized_vars_front', [$this, 'add_localized_vars_front']);

    // All other hooks can go here, for a list of available hoooks go to: http://wpdocs.latepoint.com/list-of-latepoint-hooks-actions-and-filters/
    // add_action('EXAMPLE_HOOK', [$this, 'EXAMPLE_FUNCTION']);

    // init the addon
    add_action( 'init', array( $this, 'init' ), 0 );

    register_activation_hook(__FILE__, [$this, 'on_activate']);
    register_deactivation_hook(__FILE__, [$this, 'on_deactivate']);
  }


  // Loads addon specific javascript and stylesheets for frontend site
  public function load_front_scripts_and_styles(){
    // Stylesheets
    wp_enqueue_style( 'latepoint-addon-everypay-front', $this->public_stylesheets() . 'everypay-front.css', false, $this->version );

    // Javascripts
    wp_enqueue_script( 'latepoint-addon-everypay-front', $this->public_javascripts() . 'everypay-front.js', array('jquery', 'latepoint-main-front'), $this->version );
  }

  // Exposes EveryPay create-payment route names on the `latepoint_helper` JS object
  public function add_localized_vars_front($localized_vars){
    $localized_vars['everypay_route_create_payment'] = OsRouterHelper::build_route_name('everypay', 'create_payment');
    $localized_vars['everypay_route_create_payment_for_transaction'] = OsRouterHelper::build_route_name('everypay', 'create_payment_for_transaction');

    return $localized_vars;
  }

  // Loads addon specific javascript and stylesheets for backend (wp-admin)
  public function load_admin_scripts_and_styles($localized_vars){
    // Stylesheets
    wp_enqueue_style( 'latepoint-addon-everypay', $this->public_stylesheets() . 'latepoint-addon-everypay-admin.css', false, $this->version );

    // Javascripts
    wp_enqueue_script( 'latepoint-addon-everypay',  $this->public_javascripts() . 'latepoint-addon-everypay-admin.js', array('jquery'), $this->version );
  }


  public function add_menu_links($menus){
    if(!OsAuthHelper::is_admin_logged_in()) return $menus;
    $menus[] = ['id' => 'addon_everypay',
                'label' => __( 'EveryPay', 'latepoint-addon-everypay' ),
                'icon' => 'latepoint-icon latepoint-icon-credit-card',
                'link' => OsRouterHelper::build_link(['everypay', 'settings'])];
    return $menus;
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
    load_plugin_textdomain('latepoint-addon-everypay', false, dirname(plugin_basename(__FILE__)) . '/languages');
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
  $LATEPOINT_ADDON_EVERYPAY = new LatePointAddonEverypay();
}
