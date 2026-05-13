<?php
if ( ! defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly.
}


if ( ! class_exists( 'OsZoomController' ) ) :


  class OsZoomController extends OsController {



    function __construct(){
      parent::__construct();

      $this->views_folder = plugin_dir_path( __FILE__ ) . '../views/zoom/';
      $this->vars['breadcrumbs'][] = ['label' => __('Zoom Settings', 'latepoint-zoom'), 'link' => OsRouterHelper::build_link(['zoom', 'for_booking'] )];
    }


  }

endif;