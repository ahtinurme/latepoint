<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}


if ( ! class_exists( 'OsTaxesController' ) ) :


	class OsTaxesController extends OsController {



		public function __construct() {
			parent::__construct();

			$this->views_folder            = plugin_dir_path( __FILE__ ) . '../views/taxes/';
			$this->vars['pre_page_header'] = OsMenuHelper::get_label_by_id( 'settings' );
			$this->vars['breadcrumbs'][]   = [
				'label' => __( 'Taxes', 'latepoint-pro-features' ),
				'link'  => OsRouterHelper::build_link( [ 'taxes', 'index' ] ),
			];
		}


		public function destroy() {
			if ( isset( $this->params['id'] ) && ! empty( $this->params['id'] ) ) {
				if ( OsTaxesHelper::delete( $this->params['id'], $this->params['fields_for'] ) ) {
					$status        = LATEPOINT_STATUS_SUCCESS;
					$response_html = __( 'Tax Removed', 'latepoint-pro-features' );
				} else {
					$status        = LATEPOINT_STATUS_ERROR;
					$response_html = __( 'Error Removing Tax', 'latepoint-pro-features' );
				}
			} else {
				$status        = LATEPOINT_STATUS_ERROR;
				$response_html = __( 'Invalid Tax ID', 'latepoint-pro-features' );
			}
			if ( $this->get_return_format() == 'json' ) {
				$this->send_json(
					array(
						'status'  => $status,
						'message' => $response_html,
					) 
				);
			}
		}

		public function new_form() {
			$this->vars['tax'] = new \LatePoint\Addons\Taxes\Tax();
			$this->set_layout( 'none' );
			$this->format_render( __FUNCTION__ );
		}

		public function save() {
			if ( $this->params['taxes'] ) {
				$result = OsTaxesHelper::save_from_params( $this->params['taxes'] );
				if ( is_wp_error( $result ) ) {
					$status        = LATEPOINT_STATUS_ERROR;
					$response_html = $result->get_error_messages();
				} else {
					$status        = LATEPOINT_STATUS_SUCCESS;
					$response_html = __( 'Taxes saved', 'latepoint-pro-features' );
				}
			} else {
				$status        = LATEPOINT_STATUS_ERROR;
				$response_html = __( 'Invalid params', 'latepoint-pro-features' );
			}
			if ( $this->get_return_format() == 'json' ) {
				$this->send_json(
					array(
						'status'  => $status,
						'message' => $response_html,
					) 
				);
			}
		}

		public function update_order() {
			$fields_for           = $this->params['fields_for'];
			$ordered_fields       = $this->params['ordered_fields'];
			$fields_in_db         = OsTaxesHelper::get_taxes_arr( $fields_for );
			$ordered_fields_in_db = [];
			foreach ( $ordered_fields as $field_id => $field_order ) {
				if ( isset( $fields_in_db[ $field_id ] ) ) {
					$ordered_fields_in_db[ $field_id ] = $fields_in_db[ $field_id ];
				}
			}
			if ( OsTaxesHelper::save_taxes_arr( $ordered_fields_in_db, $fields_for ) ) {
				$status        = LATEPOINT_STATUS_SUCCESS;
				$response_html = __( 'Order Updated', 'latepoint-pro-features' );
			} else {
				$status        = LATEPOINT_STATUS_ERROR;
				$response_html = __( 'Error Updating Order of Taxes', 'latepoint-pro-features' );
			}
			if ( $this->get_return_format() == 'json' ) {
				$this->send_json(
					array(
						'status'  => $status,
						'message' => $response_html,
					) 
				);
			}
		}

		public function index() {
			$this->vars['page_header'] = OsMenuHelper::get_menu_items_by_id( 'settings' );
			$this->vars['taxes']       = OsTaxesHelper::get_taxes_arr();
			$this->format_render( __FUNCTION__ );
		}
	}

endif;
