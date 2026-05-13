<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}


if ( ! class_exists( 'OsLocationCategoriesController' ) ) :


	class OsLocationCategoriesController extends OsController {



		public function __construct() {
			parent::__construct();

			$this->views_folder            = plugin_dir_path( __FILE__ ) . '../views/location_categories/';
			$this->vars['page_header']     = OsMenuHelper::get_menu_items_by_id( 'locations' );
			$this->vars['pre_page_header'] = OsMenuHelper::get_label_by_id( 'locations' );
			$this->vars['breadcrumbs'][]   = array(
				'label' => __( 'Location Categories', 'latepoint-pro-features' ),
				'link'  => OsRouterHelper::build_link( OsRouterHelper::build_route_name( 'location_categories', 'index' ) ),
			);
		}



		/*
		Edit location
		*/

		public function edit() {
		}



		/*
		New category form
		*/

		public function new_form() {
			$this->vars['page_header']   = __( 'Create New Location', 'latepoint-pro-features' );
			$this->vars['breadcrumbs'][] = array(
				'label' => __( 'Create New Location', 'latepoint-pro-features' ),
				'link'  => false,
			);

			$this->vars['category'] = new OsLocationCategoryModel();

			if ( $this->get_return_format() == 'json' ) {
				$response_html = $this->render( $this->views_folder . 'new_form', 'none' );
				wp_send_json(
					array(
						'status'  => 'success',
						'message' => $response_html,
					) 
				);
				exit();
			} else {
				echo $this->render( $this->views_folder . 'new_form', $this->get_layout() );
			}
		}



		/*
		List of categories for select box
		*/

		public function list_for_select() {


			$categories    = new OsLocationCategoryModel();
			$categories    = $categories->get_results();
			$response_html = '<option value="0">' . __( 'Not categorized', 'latepoint-pro-features' ) . '</option>';
			foreach ( $categories as $category ) {
				$response_html .= '<option>' . $category->name . '</option>';
			}
			wp_send_json(
				array(
					'status'  => 'success',
					'message' => $response_html,
				) 
			);
		}



		/*
		Index of categories
		*/

		public function index() {
			$this->vars['page_header']         = OsMenuHelper::get_menu_items_by_id( 'locations' );
			$location_categories               = new OsLocationCategoryModel();
			$location_categories               = $location_categories->get_results_as_models();
			$this->vars['location_categories'] = $location_categories;

			$locations                             = new OsLocationModel();
			$this->vars['uncategorized_locations'] = $locations->where( array( 'category_id' => [ 'OR' => [ 0, 'IS NULL' ] ] ) )->order_by( 'order_number asc' )->get_results_as_models();

			$this->format_render( __FUNCTION__ );
		}



		public function destroy() {
			if ( filter_var( $this->params['id'], FILTER_VALIDATE_INT ) ) {
				$this->check_nonce( 'destroy_location_category_' . $this->params['id'] );
				$location_category = new OsLocationCategoryModel( $this->params['id'] );
				$locations         = new OsLocationModel();
				$locations         = $locations->where( [ 'category_id' => $location_category->id ] )->get_results_as_models();
				if ( ! empty( $locations ) ) {
					// clear category from locations that are in this category
					foreach ( $locations as $location ) {
						$location->update_attributes( [ 'category_id' => 0 ] );
					}
				}
				if ( $location_category->delete() ) {
					$status        = LATEPOINT_STATUS_SUCCESS;
					$response_html = __( 'Location Category Removed', 'latepoint-pro-features' );
				} else {
					$status        = LATEPOINT_STATUS_ERROR;
					$response_html = __( 'Error Removing Location Category. Error: FJI8321', 'latepoint-pro-features' );
				}
			} else {
				$status        = LATEPOINT_STATUS_ERROR;
				$response_html = __( 'Error Removing Location Category. Error: SIF2348', 'latepoint-pro-features' );
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



		public function update_order_of_categories() {
			foreach ( $this->params['item_datas'] as $location_data ) {
				$location               = new OsLocationModel( $location_data['id'] );
				$location->category_id  = $location_data['category_id'];
				$location->order_number = $location_data['order_number'];
				if ( $location->save() ) {
					$response_html = __( 'Location Order Updated', 'latepoint-pro-features' );
					$status        = LATEPOINT_STATUS_SUCCESS;
				} else {
					$response_html = $location->get_error_messages();
					$status        = LATEPOINT_STATUS_ERROR;
					break;
				}
			}
			if ( $status == LATEPOINT_STATUS_SUCCESS && is_array( $this->params['category_datas'] ) ) {
				foreach ( $this->params['category_datas'] as $category_data ) {
					$location_category               = new OsLocationCategoryModel( $category_data['id'] );
					$location_category->order_number = $category_data['order_number'];
					$location_category->parent_id    = ( $category_data['parent_id'] ) ? $category_data['parent_id'] : null;
					if ( $location_category->save() ) {
						$response_html = __( 'Location Categories Order Updated', 'latepoint-pro-features' );
						$status        = LATEPOINT_STATUS_SUCCESS;
					} else {
						$response_html = $location_category->get_error_messages();
						$status        = LATEPOINT_STATUS_ERROR;
						break;
					}
				}
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


		/*
		Create location
		*/

		public function create() {
			$this->check_nonce( 'new_location_category' );
			$location_category = new OsLocationCategoryModel();
			$location_category->set_data( $this->params['location_category'] );
			if ( $location_category->save() ) {
				$response_html = __( 'Location Category Created.', 'latepoint-pro-features' );
				$status        = LATEPOINT_STATUS_SUCCESS;
			} else {
				$response_html = $location_category->get_error_messages();
				$status        = LATEPOINT_STATUS_ERROR;
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


		/*
		Update location category
		*/

		public function update() {
			if ( filter_var( $this->params['location_category']['id'], FILTER_VALIDATE_INT ) ) {
				$this->check_nonce( 'edit_location_category_' . $this->params['location_category']['id'] );
				$location_category = new OsLocationCategoryModel( $this->params['location_category']['id'] );
				$location_category->set_data( $this->params['location_category'] );
				if ( $location_category->save() ) {
					$response_html = __( 'Location Category Updated. ID: ', 'latepoint-pro-features' ) . $location_category->id;
					$status        = LATEPOINT_STATUS_SUCCESS;
				} else {
					$response_html = $location_category->get_error_messages();
					$status        = LATEPOINT_STATUS_ERROR;
				}
			} else {
				$status        = LATEPOINT_STATUS_ERROR;
				$response_html = __( 'Error Updating Location (Invalid ID)', 'latepoint-pro-features' );
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
