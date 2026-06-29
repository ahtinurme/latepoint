<?php
/**
 * Plugin Name: LatePoint Addon - Ninja Forms
 * Plugin URI:  https://latepoint.com/
 * Description: Attaches a Ninja Form to LatePoint services. After a customer books, the link to the
 *              form is delivered in the booking confirmation email and shown on the confirmation
 *              screen. When the customer submits the form, admin and customer are notified, and the
 *              submission is stored against the booking — visible in the customer account and in the
 *              LatePoint admin order view. The form is configurable per service.
 * Version:     1.0.0
 * Author:      Yumefit
 * Text Domain: latepoint-ninja-forms
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly.
}

// If no LatePoint class exists - exit, because LatePoint plugin is required for this addon
if ( ! class_exists( 'LatePointNinjaForms' ) ) :

class LatePointNinjaForms {

  public $version = '1.0.0';
  public $db_version = '1.0.0';
  public $addon_name = 'latepoint-ninja-forms';

  public function __construct() {
    $this->define_constants();
    $this->init_hooks();
  }

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
    include_once( dirname( __FILE__ ) . '/lib/controllers/ninja_forms_controller.php' );

    // HELPERS
    include_once( dirname( __FILE__ ) . '/lib/helpers/ninja_forms_helper.php' );
  }

  public function init_hooks() {
    add_action( 'latepoint_init', [ $this, 'latepoint_init' ] );
    add_action( 'latepoint_includes', [ $this, 'includes' ] );
    add_filter( 'latepoint_installed_addons', [ $this, 'register_addon' ] );

    // Admin side menu entry for the addon settings page.
    add_filter( 'latepoint_side_menu', [ $this, 'add_menu_links' ] );

    // --- Requirement 1: deliver the form link ---
    // Smart variable {{order_ninja_form_url}} / {{order_ninja_form_link}} usable in any
    // notification template (email, SMS) and in the confirmation message.
    add_filter( 'latepoint_replace_order_vars', [ $this, 'replace_order_vars' ], 10, 5 );
    // Zero-config: also print the link directly on the on-screen confirmation step.
    add_action( 'latepoint_step_confirmation_head_info_after', [ $this, 'output_confirmation_link' ] );
    // Zero-config: auto-append the link to the customer's notification email when a form exists.
    add_filter( 'latepoint_process_prepare_data_for_run', [ $this, 'maybe_append_link_to_email' ] );

    // --- Per-service: toggle whether booking a service delivers the form link ---
    add_action( 'latepoint_service_form_after', [ $this, 'output_service_form_field' ] );
    add_action( 'latepoint_service_saved', [ $this, 'save_service_form_field' ], 10, 3 );

    // --- Requirement 4: display stored submissions ---
    // Customer cabinet: a dedicated tab (trigger + content panel) rather than dumped before appointments.
    add_action( 'latepoint_customer_dashboard_after_tabs', [ $this, 'output_customer_cabinet_tab_trigger' ] );
    add_action( 'latepoint_customer_dashboard_after_tab_contents', [ $this, 'output_customer_cabinet_tab_content' ] );
    add_action( 'latepoint_customer_edit_form_after', [ $this, 'output_customer_dashboard_submissions' ] );
    add_action( 'latepoint_order_quick_edit_form_content_after', [ $this, 'output_admin_order_submission' ] );

    // --- Requirements 2 & 3: capture submissions from Ninja Forms ---
    add_shortcode( 'latepoint_ninja_form', [ $this, 'render_form_shortcode' ] );
    add_filter( 'ninja_forms_render_default_value', [ $this, 'populate_hidden_fields' ], 10, 3 );
    add_action( 'ninja_forms_after_submission', [ $this, 'handle_form_submission' ] );

    add_action( 'latepoint_wp_enqueue_scripts', [ $this, 'load_front_scripts_and_styles' ] );

    add_action( 'init', [ $this, 'init' ], 0 );

    register_activation_hook( __FILE__, [ $this, 'on_activate' ] );
    register_deactivation_hook( __FILE__, [ $this, 'on_deactivate' ] );
  }

  public function init() {
    $this->load_plugin_textdomain();
  }

  public function latepoint_init() {
    LatePoint\Cerber\Router::init_addon();
  }

  public function load_plugin_textdomain() {
    load_plugin_textdomain( 'latepoint-ninja-forms', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
  }

  public function load_front_scripts_and_styles() {
    wp_enqueue_style( 'latepoint-ninja-forms', self::public_stylesheets() . 'latepoint-ninja-forms-front.css', false, $this->version );
  }

  public function add_menu_links( $menus ) {
    if ( ! OsAuthHelper::is_admin_logged_in() ) {
      return $menus;
    }
    $item = [
      'id'    => 'ninja_forms',
      'label' => __( 'Ninja Forms', 'latepoint-ninja-forms' ),
      'icon'  => 'latepoint-icon latepoint-icon-clipboard',
      'link'  => OsRouterHelper::build_link( [ 'ninja_forms', 'settings' ] ),
    ];
    // Show as a tab under "Settings" rather than a standalone top-level item.
    foreach ( $menus as $i => $menu ) {
      if ( isset( $menu['id'] ) && $menu['id'] === 'settings' ) {
        $menus[ $i ]['children'][] = $item;
        return $menus;
      }
    }
    // Fallback (settings menu not present): keep it top-level.
    $menus[] = $item;
    return $menus;
  }

  /* ---------------------------------------------------------------------------
   * Requirement 1 — deliver the form link
   * ------------------------------------------------------------------------- */

  public function replace_order_vars( $text, $order, $original_text, $needles, $replacements ) {
    return OsNinjaFormsHelper::replace_order_vars( $text, $order );
  }

  public function output_confirmation_link( $order ) {
    echo OsNinjaFormsHelper::confirmation_link_html( $order );
  }

  public function maybe_append_link_to_email( $action ) {
    return OsNinjaFormsHelper::maybe_append_link_to_email( $action );
  }

  public function output_service_form_field( $service ) {
    echo OsNinjaFormsHelper::service_form_field_html( $service );
  }

  public function save_service_form_field( $service, $is_new_record, $service_params ) {
    if ( ! isset( $service_params[ OsNinjaFormsHelper::SERVICE_META_ENABLED ] ) ) {
      return;
    }
    OsNinjaFormsHelper::save_service_link_enabled( $service->id, $service_params[ OsNinjaFormsHelper::SERVICE_META_ENABLED ] );
  }

  /* ---------------------------------------------------------------------------
   * Requirement 4 — display stored submissions
   * ------------------------------------------------------------------------- */

  public function output_customer_dashboard_submissions( $customer ) {
    echo OsNinjaFormsHelper::customer_submissions_html( $customer );
  }

  public function output_customer_cabinet_tab_trigger( $customer ) {
    echo OsNinjaFormsHelper::customer_cabinet_tab_trigger_html( $customer );
  }

  public function output_customer_cabinet_tab_content( $customer ) {
    echo OsNinjaFormsHelper::customer_cabinet_tab_content_html( $customer );
  }

  public function output_admin_order_submission( $order ) {
    echo OsNinjaFormsHelper::admin_order_submission_html( $order );
  }

  /* ---------------------------------------------------------------------------
   * Requirements 2 & 3 — Ninja Forms bridge
   * ------------------------------------------------------------------------- */

  public function render_form_shortcode( $atts ) {
    return OsNinjaFormsHelper::render_form_shortcode( $atts );
  }

  public function populate_hidden_fields( $default_value, $field_id, $field_settings ) {
    return OsNinjaFormsHelper::populate_hidden_fields( $default_value, $field_settings );
  }

  public function handle_form_submission( $form_data ) {
    OsNinjaFormsHelper::handle_form_submission( $form_data );
  }

  /* ------------------------------------------------------------------------- */

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

if ( in_array( 'latepoint/latepoint.php', get_option( 'active_plugins', array() ) ) || array_key_exists( 'latepoint/latepoint.php', get_site_option( 'active_sitewide_plugins', array() ) ) ) {
  $LATEPOINT_ADDON_NINJA_FORMS = new LatePointNinjaForms();
}
