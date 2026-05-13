<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}


if ( ! class_exists( 'OsTimezoneSelectorController' ) ) :


	class OsTimezoneSelectorController extends OsController {


		public function __construct() {
			parent::__construct();

			$this->action_access['public'] = array_merge( $this->action_access['public'], [ 'change_timezone', 'timezone_picker' ] );
			$this->views_folder            = plugin_dir_path( __FILE__ ) . '../views/timezone_selector/';
			$this->vars['page_header']     = __( 'Timezone Selector', 'latepoint-pro-features' );
			$this->vars['breadcrumbs'][]   = array(
				'label' => __( 'Timezone Selector', 'latepoint-pro-features' ),
				'link'  => OsRouterHelper::build_link( OsRouterHelper::build_route_name( 'timezone_selector', 'index' ) ),
			);
		}


		// Changes timezone for customer, called from booking form timezone selector on change
		public function change_timezone() {
			$timezone_name = $this->params['timezone_name'];
			OsTimeHelper::set_timezone_name_in_cookie( $timezone_name );
			if ( OsAuthHelper::is_customer_logged_in() ) {
				OsMetaHelper::save_customer_meta_by_key( 'timezone_name', $timezone_name, OsAuthHelper::get_logged_in_customer_id() );
			}

			if ( $this->get_return_format() == 'json' ) {
				$this->send_json(
					array(
						'status'  => LATEPOINT_STATUS_SUCCESS,
						'message' => __( 'Timezone Updated', 'latepoint-pro-features' ),
					) 
				);
			}
		}

		public function timezone_picker() {
			$selected_timezone_name = $this->params['timezone_name'] ?? OsTimeHelper::get_wp_timezone_name();

			$html = OsFeatureTimezoneHelper::generate_timezone_picker( $selected_timezone_name );
			$this->send_json(
				array(
					'status'  => LATEPOINT_STATUS_SUCCESS,
					'message' => $html,
				) 
			);
		}
	}


endif;
