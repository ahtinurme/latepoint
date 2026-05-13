<?php
/*
 * Copyright (c) 2024 LatePoint LLC. All rights reserved.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}


if ( ! class_exists( 'OsBundlesController' ) ) :


	class OsBundlesController extends OsController {



		public function __construct() {
			parent::__construct();

			$this->views_folder          = plugin_dir_path( __FILE__ ) . '../views/bundles/';
			$this->vars['page_header']   = OsMenuHelper::get_menu_items_by_id( 'services' );
			$this->vars['breadcrumbs'][] = array(
				'label' => __( 'Bundles', 'latepoint-pro-features' ),
				'link'  => OsRouterHelper::build_link( OsRouterHelper::build_route_name( 'bundles', 'index' ) ),
			);
		}

		/*
		Edit bundle
		*/

		public function edit() {
			$bundle_id = $this->params['id'];

			$this->vars['page_header']   = __( 'Edit Bundle', 'latepoint-pro-features' );
			$this->vars['breadcrumbs'][] = array(
				'label' => __( 'Edit Bundle', 'latepoint-pro-features' ),
				'link'  => false,
			);


			$bundle   = new OsBundleModel( $bundle_id );
			$services = new OsServiceModel();
			$services = $services->should_be_active()->get_results_as_models();


			$this->vars['bundle']   = $bundle;
			$this->vars['services'] = $services;

			$this->format_render( __FUNCTION__ );
		}


		/*
		New bundle form
		*/

		public function new_form() {
			$this->vars['page_header']   = __( 'New Bundle', 'latepoint-pro-features' );
			$this->vars['breadcrumbs'][] = array(
				'label' => __( 'New Bundle', 'latepoint-pro-features' ),
				'link'  => false,
			);


			$bundle = new OsBundleModel();

			$services = new OsServiceModel();
			$services = $services->should_be_active()->get_results_as_models();



			$this->vars['services'] = $services;
			$this->vars['bundle']   = $bundle;


			$this->format_render( __FUNCTION__ );
		}





		/*
		Index of bundles
		*/

		public function index() {
			$this->vars['pre_page_header'] = OsMenuHelper::get_label_by_id( 'services' );
			$bundles                       = new OsBundleModel();
			$this->vars['bundles']         = $bundles->order_by( 'order_number asc' )->get_results_as_models();

			$this->format_render( __FUNCTION__ );
		}




		/*
		Create bundle
		*/

		public function create() {
			$this->update();
		}


		/*
		Update bundle
		*/

		public function update() {
			$is_new_record = ( isset( $this->params['bundle']['id'] ) && $this->params['bundle']['id'] ) ? false : true;

			// Verify CSRF nonce
			$this->check_nonce( $is_new_record ? 'latepoint_new_bundle' : 'latepoint_edit_bundle_' . $this->params['bundle']['id'] );
			$bundle = new OsBundleModel();
			$bundle->set_data( $this->params['bundle'] );
			$extra_response_vars = array();

			if ( $bundle->save() && $bundle->save_services( $this->params['bundle']['services'] ) ) {
				if ( $is_new_record ) {
					$response_html = __( 'Bundle Created. ID:', 'latepoint-pro-features' ) . $bundle->id;
					OsActivitiesHelper::create_activity(
						array(
							'code'      => 'bundle_create',
							'bundle_id' => $bundle->id,
						) 
					);
				} else {
					$response_html = __( 'Bundle Updated. ID:', 'latepoint-pro-features' ) . $bundle->id;
					OsActivitiesHelper::create_activity(
						array(
							'code'      => 'bundle_update',
							'bundle_id' => $bundle->id,
						) 
					);
				}
				$status                           = LATEPOINT_STATUS_SUCCESS;
				$extra_response_vars['record_id'] = $bundle->id;
				do_action( 'latepoint_bundle_saved', $bundle, $is_new_record, $this->params['bundle'] );
			} else {
				$response_html = $bundle->get_error_messages();
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
		Delete bundle
		*/

		public function destroy() {
			// Verify CSRF nonce with action parameter
			$this->check_nonce( 'latepoint_destroy_bundle_' . ( $this->params['id'] ?? 'unknown' ) );

			if ( filter_var( $this->params['id'], FILTER_VALIDATE_INT ) ) {
				$bundle = new OsBundleModel( $this->params['id'] );
				if ( $bundle->delete() ) {
					  $status        = LATEPOINT_STATUS_SUCCESS;
					  $response_html = __( 'Bundle Removed', 'latepoint-pro-features' );
				} else {
					$status        = LATEPOINT_STATUS_ERROR;
					$response_html = __( 'Error Removing Bundle', 'latepoint-pro-features' );
				}
			} else {
				$status        = LATEPOINT_STATUS_ERROR;
				$response_html = __( 'Error Removing Bundle', 'latepoint-pro-features' );
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
