<?php
if ( ! defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly.
}

if ( ! class_exists( 'OsNinjaFormsController' ) ) :

class OsNinjaFormsController extends OsController {

  function __construct() {
    parent::__construct();

    $this->views_folder = plugin_dir_path( __FILE__ ) . '../views/ninja_forms/';
    $this->vars['page_header'] = __( 'Ninja Forms', 'latepoint-ninja-forms' );
    $this->vars['breadcrumbs'][] = [
      'label' => __( 'Ninja Forms', 'latepoint-ninja-forms' ),
      'link'  => OsRouterHelper::build_link( [ 'ninja_forms', 'settings' ] ),
    ];
  }

  public function settings() {
    if ( ! OsAuthHelper::is_admin_logged_in() ) {
      return;
    }
    $this->format_render( __FUNCTION__ );
  }

  public function save_settings() {
    if ( ! OsAuthHelper::is_admin_logged_in() ) {
      return;
    }
    $this->check_nonce( 'ninja_forms_settings' );

    $settings = $this->params['settings'] ?? [];
    foreach ( $settings as $name => $value ) {
      OsSettingsHelper::save_setting_by_name( $name, $value );
    }

    if ( $this->get_return_format() == 'json' ) {
      $this->send_json( [
        'status'  => LATEPOINT_STATUS_SUCCESS,
        'message' => __( 'Settings Updated', 'latepoint-ninja-forms' ),
      ] );
    }
  }
}

endif;
