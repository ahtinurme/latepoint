<?php
/*
 * Copyright (c) 2024 LatePoint LLC. All rights reserved.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}


if ( ! class_exists( 'OsServiceExtrasController' ) ) :


	class OsServiceExtrasController extends OsController {



		public function __construct() {
			parent::__construct();
	  
			$this->views_folder            = plugin_dir_path( __FILE__ ) . '../views/service_extras/';
			$this->vars['page_header']     = OsMenuHelper::get_menu_items_by_id( 'services' );
			$this->vars['pre_page_header'] = OsMenuHelper::get_label_by_id( 'services' );
			$this->vars['breadcrumbs'][]   = array(
				'label' => __( 'Service Extras', 'latepoint-pro-features' ),
				'link'  => OsRouterHelper::build_link( OsRouterHelper::build_route_name( 'service_extras', 'index' ) ),
			);
		}


		public function reload_service_extras_for_booking_data_form() {
			$order_item_id  = $this->params['order_item_id'];
			$booking_id     = $this->params['booking_id'];
			$booking_object = OsOrdersHelper::create_booking_object_from_booking_data_form( $this->params['order_items'][ $order_item_id ]['bookings'][ $booking_id ] );

			$html = OsServiceExtrasHelper::get_service_extras_selector_for_booking( $booking_object, $order_item_id );

			if ( $this->get_return_format() == 'json' ) {
				$this->send_json(
					array(
						'status'  => LATEPOINT_STATUS_SUCCESS,
						'message' => $html,
					) 
				);
			}
		}

		/*
		Edit service
		*/

		public function edit_form() {
			$service_extra_id              = $this->params['id'];
			$this->vars['pre_page_header'] = '';

			$this->vars['page_header']   = __( 'Edit Service Extra', 'latepoint-pro-features' );
			$this->vars['breadcrumbs'][] = array(
				'label' => __( 'Edit Service Extra', 'latepoint-pro-features' ),
				'link'  => false,
			);

			$service_extra = new OsServiceExtraModel( $service_extra_id );
			$services      = new OsServiceModel();

			$this->vars['service_extra'] = $service_extra;
			$this->vars['services']      = $services->get_results_as_models();

			$this->format_render( __FUNCTION__ );
		}


		/*
		New service extra form
		*/

		public function new_form() {
			$this->vars['pre_page_header'] = '';
			$this->vars['page_header']     = __( 'New Service Extra', 'latepoint-pro-features' );
			$this->vars['breadcrumbs'][]   = array(
				'label' => __( 'New Service Extra', 'latepoint-pro-features' ),
				'link'  => false,
			);

			$service_extra = new OsServiceExtraModel();
			$services      = new OsServiceModel();

			$this->vars['service_extra'] = $service_extra;
			$this->vars['services']      = $services->get_results_as_models();

	  
			$this->format_render( __FUNCTION__ );
		}


		public function index() {
			$service = new OsServiceModel();

			$service_extras = new OsServiceExtraModel();
			$service_extras = $service_extras->get_results_as_models();

			$this->vars['total_services'] = $service->count();
			$this->vars['service_extras'] = $service_extras;
			$this->format_render( __FUNCTION__ );
		}



		/*
		Create service
		*/

		public function create() {
			$this->update();
		}


		/*
		Update service extra
		*/

		public function update() {
			$is_new_record = ( isset( $this->params['service_extra']['id'] ) && $this->params['service_extra']['id'] ) ? false : true;
			$service_extra = new OsServiceExtraModel();
			$service_extra->set_data( $this->params['service_extra'] );
			$extra_response_vars = array();

			if ( $service_extra->save() && $service_extra->save_connected_services( $this->params['service_extra']['services'] ) ) {
				if ( $is_new_record ) {
					$response_html = __( 'Service Extra Created. ID:', 'latepoint-pro-features' ) . $service_extra->id;
				} else {
					$response_html = __( 'Service Extra Updated. ID:', 'latepoint-pro-features' ) . $service_extra->id;
				}
				$status                           = LATEPOINT_STATUS_SUCCESS;
				$extra_response_vars['record_id'] = $service_extra->id;
			} else {
				$response_html = $service_extra->get_error_messages();
				$status        = LATEPOINT_STATUS_ERROR;
			}
			if ( $this->get_return_format() == 'json' ) {
				$this->send_json(
					array(
						'status'  => $status,
						'message' => $response_html,
					) + $extra_response_vars 
				);
			}
		}



		/*
		Delete service extra
		*/

		public function destroy() {
			if ( filter_var( $this->params['id'], FILTER_VALIDATE_INT ) ) {
				$service_extra = new OsServiceExtraModel( $this->params['id'] );
				if ( $service_extra->delete() ) {
					$status        = LATEPOINT_STATUS_SUCCESS;
					$response_html = __( 'Service Extra Removed', 'latepoint-pro-features' );
				} else {
					$status        = LATEPOINT_STATUS_ERROR;
					$response_html = __( 'Error Removing Service Extra', 'latepoint-pro-features' );
				}
			} else {
				$status        = LATEPOINT_STATUS_ERROR;
				$response_html = __( 'Error Removing Service Extra', 'latepoint-pro-features' );
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
	}

endif;
