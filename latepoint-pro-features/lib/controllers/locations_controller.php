<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}


if ( ! class_exists( 'OsLocationsController' ) ) :


	class OsLocationsController extends OsController {



		public function __construct() {
			parent::__construct();

			$this->views_folder            = plugin_dir_path( __FILE__ ) . '../views/locations/';
			$this->vars['page_header']     = OsMenuHelper::get_menu_items_by_id( 'locations' );
			$this->vars['pre_page_header'] = OsMenuHelper::get_label_by_id( 'locations' );
			$this->vars['breadcrumbs'][]   = array(
				'label' => __( 'Locations', 'latepoint-pro-features' ),
				'link'  => OsRouterHelper::build_link( OsRouterHelper::build_route_name( 'locations', 'index' ) ),
			);
		}


		/*
		Edit location
		*/

		public function edit_form() {
			$location_id = $this->params['id'];

			$this->vars['pre_page_header'] = '';
			$this->vars['page_header']     = __( 'Edit Location', 'latepoint-pro-features' );
			$this->vars['breadcrumbs'][]   = array(
				'label' => __( 'Edit Location', 'latepoint-pro-features' ),
				'link'  => false,
			);

			$location            = new OsLocationModel( $location_id );
			$agents              = new OsAgentModel();
			$services            = new OsServiceModel();
			$location_categories = new OsLocationCategoryModel();


			$this->vars['location_categories_for_select'] = $location_categories->index_for_select();

			$this->vars['location'] = $location;
			$this->vars['agents']   = $agents->get_results_as_models();
			$this->vars['services'] = $services->get_results_as_models();

			$custom_work_periods               = OsWorkPeriodsHelper::get_work_periods(
				new \LatePoint\Misc\Filter(
					[
						'location_id' => $location_id,
						'exact_match' => true,
					] 
				),
				true 
			);
			$this->vars['custom_work_periods'] = $custom_work_periods;
			$this->vars['is_custom_schedule']  = ( $custom_work_periods && ( count( $custom_work_periods ) > 0 ) );

			$this->format_render( __FUNCTION__ );
		}


		/*
		New location form
		*/

		public function new_form() {
			$this->vars['pre_page_header'] = '';
			$this->vars['page_header']     = __( 'Create New Location', 'latepoint-pro-features' );
			$this->vars['breadcrumbs'][]   = array(
				'label' => __( 'Create New Location', 'latepoint-pro-features' ),
				'link'  => false,
			);

			$location            = new OsLocationModel();
			$agents              = new OsAgentModel();
			$services            = new OsServiceModel();
			$location_categories = new OsLocationCategoryModel();

			if ( isset( $this->params['location_category_id'] ) ) {
				$location->category_id = $this->params['location_category_id'];
			}

			$this->vars['location_categories_for_select'] = $location_categories->index_for_select();

			$this->vars['location'] = $location;
			$this->vars['agents']   = $agents->get_results_as_models();
			$this->vars['services'] = $services->get_results_as_models();


			$this->vars['custom_work_periods'] = [];
			$this->vars['is_custom_schedule']  = false;
	  
			$this->format_render( __FUNCTION__ );
		}





		/*
		Index of locations
		*/

		public function index() {
			// create default location
			OsLocationHelper::get_default_location( true );

			// clean up locations (legacy from when we had an issue deleting locations when location category is deleted)
			$locations = new OsLocationModel();
			$locations = $locations->select( LATEPOINT_TABLE_LOCATIONS . '.*' )->join( LATEPOINT_TABLE_LOCATION_CATEGORIES, [ 'id' => LATEPOINT_TABLE_LOCATIONS . '.category_id' ], 'left' )->where(
				[
					LATEPOINT_TABLE_LOCATION_CATEGORIES . '.id'   => 'IS NULL',
					LATEPOINT_TABLE_LOCATIONS . '.category_id !=' => 0,
				] 
			)->get_results_as_models();
			if ( ! empty( $locations ) ) {
				foreach ( $locations as $location ) {
					$location->update_attributes( [ 'category_id' => 0 ] );
				}
			}


			$location_categories = new OsLocationCategoryModel();
			$location_categories = $location_categories->order_by( 'order_number asc' )->get_results_as_models();

	  
			$this->vars['location_categories'] = $location_categories;

			$locations                             = new OsLocationModel();
			$this->vars['uncategorized_locations'] = $locations->should_be_active()->filter_allowed_records()->where( [ 'category_id' => [ 'OR' => [ 0, 'IS NULL' ] ] ] )->order_by( 'order_number asc' )->get_results_as_models();

			$locations                        = new OsLocationModel();
			$this->vars['disabled_locations'] = $locations->where( [ 'status' => LATEPOINT_LOCATION_STATUS_DISABLED ] )->filter_allowed_records()->order_by( 'order_number asc' )->get_results_as_models();

	  
			$this->format_render( __FUNCTION__ );
		}




		/*
		Create location
		*/

		public function create() {
			$this->update();
		}


		/*
		Update location
		*/

		public function update() {
			$is_new_record = ( isset( $this->params['location']['id'] ) && $this->params['location']['id'] ) ? false : true;
			$this->check_nonce( $is_new_record ? 'new_location' : 'edit_location_' . $this->params['location']['id'] );
			$location = new OsLocationModel();
			$location->set_data( $this->params['location'] );
			$extra_response_vars = array();

			if ( $location->save() && $location->save_agents_and_services( $this->params['location']['agents'] ) ) {
				if ( $is_new_record ) {
					$response_html = __( 'Location Created. ID:', 'latepoint-pro-features' ) . $location->id;
					OsActivitiesHelper::create_activity(
						array(
							'code'        => 'location_created',
							'location_id' => $location->id,
						) 
					);
				} else {
					$response_html = __( 'Location Updated. ID:', 'latepoint-pro-features' ) . $location->id;
					OsActivitiesHelper::create_activity(
						array(
							'code'        => 'location_updated',
							'location_id' => $location->id,
						) 
					);
				}
				$status = LATEPOINT_STATUS_SUCCESS;
				// save schedules
				if ( $this->params['is_custom_schedule'] == 'on' ) {
					$location->save_custom_schedule( $this->params['work_periods'] );
				} elseif ( $this->params['is_custom_schedule'] == 'off' ) {
					$location->delete_custom_schedule();
				}
				$extra_response_vars['record_id'] = $location->id;
			} else {
				$response_html = $location->get_error_messages();
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
		Delete location
		*/

		public function destroy() {
			if ( filter_var( $this->params['id'], FILTER_VALIDATE_INT ) ) {
				$this->check_nonce( 'destroy_location_' . $this->params['id'] );
				$location = new OsLocationModel( $this->params['id'] );
				if ( $location->delete() ) {
					$status        = LATEPOINT_STATUS_SUCCESS;
					$response_html = __( 'Location Removed', 'latepoint-pro-features' );
				} else {
					$status        = LATEPOINT_STATUS_ERROR;
					$response_html = __( 'Error Removing Location', 'latepoint-pro-features' );
				}
			} else {
				$status        = LATEPOINT_STATUS_ERROR;
				$response_html = __( 'Error Removing Location', 'latepoint-pro-features' );
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


		/**
		 * Duplicate a location
		 * @return void
		 */
		public function duplicate(): void {
			if ( filter_var( $this->params['id'], FILTER_VALIDATE_INT ) ) {
				$this->check_nonce( 'duplicate_location_' . $this->params['id'] );

				$original_location         = new OsLocationModel( $this->params['id'] );
				$duplicated_location       = clone $original_location;
				$duplicated_location->id   = null; // reset ID to create a new record
				$duplicated_location->name = $original_location->name . ' - ' . __( 'Copy', 'latepoint-pro-features' );

				if ( $duplicated_location->save() ) {
					$connection_model = new OsConnectorModel();
					$connectors       = $connection_model->where( [ 'location_id' => $original_location->id ] )->get_results_as_models();

					foreach ( $connectors as $connector ) {
						$new_connector              = clone $connector;
						$new_connector->id          = null;
						$new_connector->location_id = $duplicated_location->id;
						$new_connector->save();
					}

					$work_periods = new OsWorkPeriodModel();
					$work_periods = $work_periods->where( [ 'location_id' => $original_location->id ] )->get_results_as_models();
					foreach ( $work_periods as $work_period ) {
						$new_period              = clone $work_period;
						$new_period->id          = null;
						$new_period->location_id = $duplicated_location->id;
						$new_period->save();
					}

					$status        = LATEPOINT_STATUS_SUCCESS;
					$response_html = OsRouterHelper::build_link( OsRouterHelper::build_route_name( 'locations', 'edit_form' ), array( 'id' => $duplicated_location->id ) );
					OsActivitiesHelper::create_activity(
						array(
							'code'        => 'location_created',
							'location_id' => $duplicated_location->id,
						) 
					);
				} else {
					$status        = LATEPOINT_STATUS_ERROR;
					$response_html = __( 'Error Creating Location', 'latepoint-pro-features' );
				}
			} else {
				$status        = LATEPOINT_STATUS_ERROR;
				$response_html = __( 'Error Creating Location', 'latepoint-pro-features' );
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
