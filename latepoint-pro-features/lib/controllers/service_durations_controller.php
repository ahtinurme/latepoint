<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}


if ( ! class_exists( 'OsServiceDurationsController' ) ) :


	class OsServiceDurationsController extends OsController {



		public function __construct() {
			parent::__construct();
	  
			$this->views_folder          = plugin_dir_path( __FILE__ ) . '../views/service_durations/';
			$this->vars['page_header']   = __( 'Service Durations', 'latepoint-pro-features' );
			$this->vars['breadcrumbs'][] = array(
				'label' => __( 'Service Durations', 'latepoint-pro-features' ),
				'link'  => OsRouterHelper::build_link( OsRouterHelper::build_route_name( 'service_durations', 'index' ) ),
			);
		}

		public function duration_fields() {
			$this->vars['duration'] = [
				'id'             => 'dur_' . OsUtilHelper::random_text( 'alnum', 8 ),
				'duration'       => 30,
				'charge_amount'  => 0,
				'deposit_amount' => 0,
			];
			$this->format_render( __FUNCTION__ );
		}
	}


endif;
