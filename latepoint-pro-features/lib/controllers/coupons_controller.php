<?php
/*
 * Copyright (c) 2024 LatePoint LLC. All rights reserved.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}


if ( ! class_exists( 'OsCouponsController' ) ) :


	class OsCouponsController extends OsController {

		public function __construct() {
			parent::__construct();

			$this->action_access['public'] = array_merge( $this->action_access['public'], [ 'apply' ] );
			$this->views_folder            = plugin_dir_path( __FILE__ ) . '../views/coupons/';
			$this->vars['breadcrumbs'][]   = array(
				'label' => __( 'Coupons', 'latepoint-pro-features' ),
				'link'  => OsRouterHelper::build_link( OsRouterHelper::build_route_name( 'coupons', 'index' ) ),
			);

			$this->vars['page_header'] = false;
		}


		public function index() {

			$page_number = isset( $this->params['page_number'] ) ? $this->params['page_number'] : 1;
			$per_page    = OsSettingsHelper::get_number_of_records_per_page();
			$offset      = ( $page_number > 1 ) ? ( ( $page_number - 1 ) * $per_page ) : 0;


			$coupons    = new OsCouponModel();
			$query_args = [];

			$filter = isset( $this->params['filter'] ) ? $this->params['filter'] : false;

			// TABLE SEARCH FILTERS
			if ( $filter ) {
				if ( $filter['id'] ) {
					$query_args['id'] = $filter['id'];
				}
				if ( $filter['created_at_from'] && $filter['created_at_to'] ) {
					$query_args[ LATEPOINT_TABLE_COUPONS . '.created_at >=' ] = $filter['created_at_from'];
					$query_args[ LATEPOINT_TABLE_COUPONS . '.created_at <=' ] = $filter['created_at_to'];
				}
				if ( $filter['name'] ) {
					$query_args['name LIKE']  = '%' . $filter['name'] . '%';
					$this->vars['name_query'] = $filter['name'];
				}
				if ( $filter['code'] ) {
					$query_args['code LIKE']  = '%' . $filter['code'] . '%';
					$this->vars['code_query'] = $filter['code'];
				}
				if ( ! empty( $filter['status'] ) ) {
					$query_args[ LATEPOINT_TABLE_COUPONS . '.status' ] = $filter['status'];
				}
			}


			// OUTPUT CSV IF REQUESTED
			if ( isset( $this->params['download'] ) && $this->params['download'] == 'csv' ) {
				$csv_filename = 'coupons_' . OsUtilHelper::random_text();

				header( 'Content-Type: text/csv' );
				header( "Content-Disposition: attachment; filename={$csv_filename}.csv" );

				$labels_row = [
					__( 'ID', 'latepoint-pro-features' ),
					__( 'Name', 'latepoint-pro-features' ),
					__( 'Code', 'latepoint-pro-features' ),
					__( 'Discount', 'latepoint-pro-features' ),
					__( 'Status', 'latepoint-pro-features' ),
					__( 'Created On', 'latepoint-pro-features' ),
				];


				$coupons_data   = [];
				$coupons_data[] = $labels_row;


				$coupons_arr = $coupons->where( $query_args )->filter_allowed_records()->order_by( 'id desc' )->get_results_as_models();
				if ( $coupons_arr ) {
					foreach ( $coupons_arr as $coupon ) {
						$values_row = [
							$coupon->id,
							$coupon->name,
							$coupon->code,
							$coupon->readable_discount(),
							$coupon->status,
							$coupon->formatted_created_date(),
						];

						/**
						 * Row of values for coupons csv export
						 *
						 * @since 1.0.11
						 * @hook latepoint_coupon_row_for_csv_export
						 *
						 * @param {array} $values_row array of values for the row
						 * @param {OsCouponModel} $coupon coupon code
						 * @param {array} $params array of params
						 * @returns {array} The filtered array of row values
						 */
						$values_row     = apply_filters( 'latepoint_coupon_row_for_csv_export', $values_row, $coupon, $this->params );
						$coupons_data[] = $values_row;
					}
				}

				/**
				 * Data for csv export of coupons
				 *
				 * @since 1.0.11
				 * @hook latepoint_coupons_data_for_csv_export
				 *
				 * @param {array} $coupons_data array of rows for a coupon csv export
				 * @param {array} $params array of params
				 * @returns {array} The filtered array of rows with values
				 */
				$coupons_data = apply_filters( 'latepoint_coupons_data_for_csv_export', $coupons_data, $this->params );
				OsCSVHelper::array_to_csv( $coupons_data );

				return;
			}

			$coupons->where( $query_args )->filter_allowed_records();
			$count_total_coupons = clone $coupons;

			$total_coupons = $count_total_coupons->count();
			$total_pages   = ceil( $total_coupons / $per_page );

			$this->vars['coupons']       = $coupons->set_limit( $per_page )->set_offset( $offset )->order_by( 'id desc' )->get_results_as_models();
			$this->vars['total_coupons'] = $total_coupons;

			$this->vars['total_pages']         = ceil( $total_coupons / $per_page );
			$this->vars['per_page']            = $per_page;
			$this->vars['current_page_number'] = $page_number;

			$this->vars['showing_from'] = ( ( $page_number - 1 ) * $per_page ) ? ( ( $page_number - 1 ) * $per_page ) : 1;
			$this->vars['showing_to']   = min( $page_number * $per_page, $this->vars['total_coupons'] );

			$this->format_render(
				[
					'json_view_name' => '_table_body',
					'html_view_name' => __FUNCTION__,
				],
				[],
				[
					'total_pages'   => $total_pages,
					'showing_from'  => $this->vars['showing_from'],
					'showing_to'    => $this->vars['showing_to'],
					'total_records' => $total_coupons,
				] 
			);
		}


		public function apply() {
			OsStepsHelper::set_required_objects( $this->params );
			$coupon_code = strtoupper( trim( $this->params['coupon_code'] ) );
			// clear coupon code if empty is passed
			if ( empty( $coupon_code ) ) {
				OsStepsHelper::$cart_object->clear_coupon_code();
				$status        = LATEPOINT_STATUS_SUCCESS;
				$response_html = __( 'Coupon Code was removed from your cart', 'latepoint-pro-features' );
			} else {
				$is_valid = OsCouponsHelper::is_coupon_code_valid( $coupon_code, OsStepsHelper::$cart_object );
				if ( ! is_wp_error( $is_valid ) ) {
					$is_valid = true;
					OsStepsHelper::$cart_object->set_coupon_code( $coupon_code );
				}
				if ( is_wp_error( $is_valid ) ) {
					$status        = LATEPOINT_STATUS_ERROR;
					$response_html = $is_valid->get_error_message();
				} else {
					$status        = LATEPOINT_STATUS_SUCCESS;
					$response_html = __( 'Coupon Code was applied to your cart', 'latepoint-pro-features' );
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


		public function destroy() {
			$this->check_nonce( 'destroy_coupon_' . $this->params['id'] );

			if ( filter_var( $this->params['id'], FILTER_VALIDATE_INT ) ) {
				$coupon = new OsCouponModel( $this->params['id'] );
				if ( $coupon->delete() ) {
					$status        = LATEPOINT_STATUS_SUCCESS;
					$response_html = __( 'Coupon Removed', 'latepoint-pro-features' );
				} else {
					$status        = LATEPOINT_STATUS_ERROR;
					$response_html = __( 'Error Removing Coupon', 'latepoint-pro-features' );
				}
			} else {
				$status        = LATEPOINT_STATUS_ERROR;
				$response_html = __( 'Error Removing Coupon', 'latepoint-pro-features' );
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
			$this->vars['coupon'] = new OsCouponModel();
			$this->set_layout( 'none' );
			$this->format_render( __FUNCTION__ );
		}

		/*
		  Create coupon
		*/

		public function create() {
			$this->update();
		}


		/**
		 * Quick edit modal
		 *
		 * @return void
		 */
		public function view_coupon_log() {
			$activities = new OsActivityModel();
			$activities = $activities->where( [ 'coupon_id' => absint( $this->params['coupon_id'] ) ] )->order_by( 'id desc' )->get_results_as_models();

			$coupon = new OsCouponModel( absint( $this->params['coupon_id'] ) );

			$this->vars['coupon']     = $coupon;
			$this->vars['activities'] = $activities;

			$this->format_render( __FUNCTION__ );
		}


		public function quick_edit() {
			$coupon = new OsCouponModel( $this->params['coupon_id'] );

			$this->vars['coupon'] = $coupon;

			if ( ! $coupon->is_new_record() ) {
				$this->vars['coupon_stats'] = OsCouponsHelper::get_usage_stats( $coupon->code );
			}

			$this->format_render( __FUNCTION__ );
		}


		/*
		  Update coupon
		*/

		public function update() {


			if ( ! empty( $this->params['coupon']['id'] ) ) {
				$this->check_nonce( 'edit_coupon_' . $this->params['coupon']['id'] );
			} else {
				$this->check_nonce( 'new_coupon' );
			}


			$coupon_params          = $this->params['coupon'];
			$coupon_params['rules'] = wp_json_encode( $coupon_params['rules'] );


			$coupon        = new OsCouponModel( $coupon_params['id'] );
			$is_new_record = $coupon->is_new_record();

			// if we are updating a coupon - save a copy by cloning old coupon
			$old_coupon = $is_new_record ? [] : clone $coupon;
			$coupon->set_data( $coupon_params );

			// clean up dates from customer to DB format, and set nulls
			$coupon->active_from = empty( $coupon->active_from ) ? null : OsWpDateTime::date_to_db_format( $coupon->active_from );
			$coupon->active_to   = empty( $coupon->active_to ) ? null : OsWpDateTime::date_to_db_format( $coupon->active_to );

			$extra_response_vars = array();

			if ( $coupon->save() ) {
				$response_html = sprintf( ( ( ! $is_new_record ) ? __( 'Coupon Updated ID: %s', 'latepoint-pro-features' ) : __( 'Coupon Created ID: %s', 'latepoint-pro-features' ) ), '<span class="os-notification-link" ' . OsCouponsHelper::quick_coupon_btn_html( $coupon->id ) . '>' . $coupon->id . '</span>' );
				if ( $is_new_record ) {
					/**
					 * Coupon was created
					 *
					 * @param {OsCouponModel} $coupon instance of coupon model that was created
					 *
					 * @since 1.1.4
					 * @hook latepoint_coupon_created
					 *
					 */
					do_action( 'latepoint_coupon_created', $coupon );
					OsActivitiesHelper::create_activity(
						array(
							'code'      => 'coupon_create',
							'coupon_id' => $coupon->id,
						) 
					);
				} else {
					/**
					 * Coupon was updated
					 *
					 * @param {OsCouponModel} $coupon instance of coupon model after it was updated
					 * @param {OsCouponModel} $old_coupon instance of coupon model before it was updated
					 *
					 * @since 1.1.4
					 * @hook latepoint_coupon_updated
					 *
					 */
					do_action( 'latepoint_coupon_updated', $coupon, $old_coupon );
					OsActivitiesHelper::create_activity(
						array(
							'code'      => 'coupon_update',
							'coupon_id' => $coupon->id,
						) 
					);
				}
				$status                           = LATEPOINT_STATUS_SUCCESS;
				$extra_response_vars['record_id'] = $coupon->id;
			} else {
				$response_html = $coupon->get_error_messages();
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
	}
endif;
