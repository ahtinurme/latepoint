<?php
/*
 * Copyright (c) 2024 LatePoint LLC. All rights reserved.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}


if ( ! class_exists( 'OsCustomFieldsController' ) ) :


	class OsCustomFieldsController extends OsController {



		public function __construct() {
			parent::__construct();

			$this->action_access['customer'] = array_merge( $this->action_access['customer'], [ 'delete_custom_field_for_customer', 'delete_custom_field_for_booking' ] );
			$this->vars['page_header']       = OsMenuHelper::get_menu_items_by_id( 'form_fields' );
			$this->vars['pre_page_header']   = OsMenuHelper::get_label_by_id( 'form_fields' );

			$this->views_folder          = plugin_dir_path( __FILE__ ) . '../views/custom_fields/';
			$this->vars['breadcrumbs'][] = [
				'label' => __( 'Custom Fields', 'latepoint-pro-features' ),
				'link'  => OsRouterHelper::build_link( [ 'custom_fields', 'for_booking' ] ),
			];
		}

		public function available_values_for_condition_property() {
			$values          = [];
			$property        = $this->params['property'];
			$custom_field_id = $this->params['custom_field_id'];
			$condition_id    = $this->params['condition_id'];
			switch ( $property ) {
				case 'agent':
				case 'service':
					$values = OsFormHelper::model_options_for_multi_select( $property );
					break;
				default:
					$values = apply_filters( 'latepoint_available_values_for_condition_property', $values, $property );
			}
			if ( $this->get_return_format() == 'json' ) {
				$this->send_json(
					array(
						'status'  => LATEPOINT_STATUS_SUCCESS,
						'message' => OsFormHelper::multi_select_field( 'custom_fields[' . $custom_field_id . '][conditions][' . $condition_id . '][value]', false, $values, false, [], [ 'class' => 'custom-field-condition-values-w' ] ),
					) 
				);
			}
		}

		// loads custom fields to show on booking data form in admin, checks which fields need to be shown depending on conditions passed
		public function reload_custom_fields_for_booking_data_form() {
			$order_item_id             = $this->params['order_item_id'];
			$booking_id                = $this->params['booking_id'];
			$booking_object            = OsOrdersHelper::create_booking_object_from_booking_data_form( $this->params['order_items'][ $order_item_id ]['bookings'][ $booking_id ] );
			$custom_fields_for_booking = OsCustomFieldsHelper::get_custom_fields_arr( 'booking', 'all', $booking_object );

			if ( isset( $custom_fields_for_booking ) && ! empty( $custom_fields_for_booking ) ) {
				$html = '<div class="os-row">' . OsCustomFieldsHelper::output_custom_fields_for_model( $custom_fields_for_booking, $booking_object, 'booking', 'order_items[' . $order_item_id . '][bookings][' . $booking_id . ']' ) . '</div>';
			} else {
				$html = '';
			}

			if ( $this->get_return_format() == 'json' ) {
				$this->send_json(
					array(
						'status'  => LATEPOINT_STATUS_SUCCESS,
						'message' => $html,
					) 
				);
			}
		}

		public function new_condition() {
			$custom_field_id = $this->params['custom_field_id'];
			$condition_html  = OsCustomFieldsHelper::generate_condition_form( $custom_field_id );
			if ( $this->get_return_format() == 'json' ) {
				$this->send_json(
					array(
						'status'  => LATEPOINT_STATUS_SUCCESS,
						'message' => $condition_html,
					) 
				);
			}
		}

		public function delete_custom_field_for_booking() {
			$custom_field_id = $this->params['custom_field_id'];
			$model_id        = $this->params['model_id'];
			$booking         = new OsBookingModel( $model_id );
			if ( OsRolesHelper::can_user_make_action_on_model_record( $booking, 'edit' ) ) {
				if ( ! empty( $booking->id ) ) {
					$booking->delete_meta_by_key( $custom_field_id );
					$status  = LATEPOINT_STATUS_SUCCESS;
					$message = __( 'Field Removed', 'latepoint-pro-features' );
				} else {
					$status  = LATEPOINT_STATUS_ERROR;
					$message = __( 'Invalid Data', 'latepoint-pro-features' );
				}
			} else {
				$status  = LATEPOINT_STATUS_ERROR;
				$message = __( 'Not Authorized', 'latepoint-pro-features' );
			}
			if ( $this->get_return_format() == 'json' ) {
				$this->send_json(
					[
						'status'  => $status,
						'message' => $message,
					] 
				);
			}
		}

		public function delete_custom_field_for_customer() {
			$custom_field_id = $this->params['custom_field_id'];
			$model_id        = $this->params['model_id'];
			$customer        = new OsCustomerModel( $model_id );
			if ( OsRolesHelper::can_user_make_action_on_model_record( $customer, 'edit' ) ) {
				if ( ! empty( $customer->id ) ) {
					$customer->delete_meta_by_key( $custom_field_id );
					$status  = LATEPOINT_STATUS_SUCCESS;
					$message = __( 'Field Removed', 'latepoint-pro-features' );
				} else {
					$status  = LATEPOINT_STATUS_ERROR;
					$message = __( 'Invalid Data', 'latepoint-pro-features' );
				}
			} else {
				$status  = LATEPOINT_STATUS_ERROR;
				$message = __( 'Not Authorized', 'latepoint-pro-features' );
			}
			if ( $this->get_return_format() == 'json' ) {
				$this->send_json(
					[
						'status'  => $status,
						'message' => $message,
					] 
				);
			}
		}


		public function destroy() {
			// Verify CSRF nonce with action parameter
			$this->check_nonce( 'latepoint_destroy_custom_field_' . ( $this->params['id'] ?? 'unknown' ) );

			if ( isset( $this->params['id'] ) && ! empty( $this->params['id'] ) ) {
				if ( OsCustomFieldsHelper::delete( $this->params['id'], $this->params['fields_for'] ) ) {
					$status        = LATEPOINT_STATUS_SUCCESS;
					$response_html = __( 'Custom Field Removed', 'latepoint-pro-features' );
				} else {
					$status        = LATEPOINT_STATUS_ERROR;
					$response_html = __( 'Error Removing Custom Field', 'latepoint-pro-features' );
				}
			} else {
				$status        = LATEPOINT_STATUS_ERROR;
				$response_html = __( 'Invalid Field ID', 'latepoint-pro-features' );
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
			$this->vars['fields_for']         = $this->params['fields_for'];
			$this->vars['custom_field_types'] = OsCustomFieldsHelper::get_custom_field_types();
			$this->vars['custom_field']       = [
				'id'          => OsCustomFieldsHelper::generate_custom_field_id(),
				'label'       => '',
				'type'        => array_key_first( $this->vars['custom_field_types'] ) ?? '',
				'value'       => '',
				'required'    => 'off',
				'width'       => 'os-col-12',
				'placeholder' => '',
				'options'     => '',
				'conditional' => 'off',
				'conditions'  => [],
				'visibility'  => 'all',
			];
			$this->set_layout( 'none' );
			$this->format_render( __FUNCTION__ );
		}

		public function default_value_field() {
			if ( ! empty( $fieldType = $this->params['field_type'] ) && ! empty( $fieldName = $this->params['field_name'] ) ) {
				$status        = LATEPOINT_STATUS_SUCCESS;
				$response_html = OsCustomFieldsHelper::get_custom_field_default_value_html(
					$fieldType,
					$fieldName,
					$this->params['field_value'] ?? ''
				);
			} else {
				$status        = LATEPOINT_STATUS_ERROR;
				$response_html = esc_html__( 'Invalid params', 'latepoint-pro-features' );
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

		public function save() {
			// Verify CSRF nonce with action parameter
			$this->check_nonce( 'latepoint_save_custom_fields' );

			if ( $this->params['custom_fields'] ) {
				foreach ( $this->params['custom_fields'] as $custom_field_id => $custom_field ) {
					$validation_errors = OsCustomFieldsHelper::has_validation_errors( $custom_field );
					if ( is_array( $validation_errors ) ) {
						$status        = LATEPOINT_STATUS_ERROR;
						$response_html = implode( ', ', $validation_errors );
					} else {
						if ( OsCustomFieldsHelper::save( $custom_field, $this->params['fields_for'] ) ) {
							$status        = LATEPOINT_STATUS_SUCCESS;
							$response_html = __( 'Custom Field Saved', 'latepoint-pro-features' );
						} else {
							$status        = LATEPOINT_STATUS_ERROR;
							$response_html = __( 'Error Saving Custom Field', 'latepoint-pro-features' );
						}
					}
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
			// Verify CSRF nonce
			$this->check_nonce( 'latepoint_update_custom_field_order' );

			$fields_for           = $this->params['fields_for'];
			$ordered_fields       = $this->params['ordered_fields'];
			$fields_in_db         = OsCustomFieldsHelper::get_custom_fields_arr( $fields_for );
			$ordered_fields_in_db = [];
			foreach ( $ordered_fields as $field_id => $field_order ) {
				if ( isset( $fields_in_db[ $field_id ] ) ) {
					$ordered_fields_in_db[ $field_id ] = $fields_in_db[ $field_id ];
				}
			}
			if ( OsCustomFieldsHelper::save_custom_fields_arr( $ordered_fields_in_db, $fields_for ) ) {
				$status        = LATEPOINT_STATUS_SUCCESS;
				$response_html = __( 'Order Updated', 'latepoint-pro-features' );
			} else {
				$status        = LATEPOINT_STATUS_ERROR;
				$response_html = __( 'Error Updating Order of Custom Fields', 'latepoint-pro-features' );
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

		public function for_booking() {
			$this->vars['page_header']               = OsMenuHelper::get_menu_items_by_id( 'form_fields' );
			$this->vars['custom_fields_for_booking'] = OsCustomFieldsHelper::get_custom_fields_arr( 'booking', 'all' );
			$this->vars['fields_for']                = 'booking';
			$this->vars['custom_field_types']        = OsCustomFieldsHelper::get_custom_field_types();
			$this->format_render( __FUNCTION__ );
		}


		public function for_customer() {
			$this->vars['page_header']   = OsMenuHelper::get_menu_items_by_id( 'form_fields' );
			$this->vars['breadcrumbs'][] = array(
				'label' => __( 'Custom Fields', 'latepoint-pro-features' ),
				'link'  => false,
			);
			$this->vars['fields_for']    = 'customer';

			$this->vars['custom_fields_for_customers'] = OsCustomFieldsHelper::get_custom_fields_arr( 'customer', 'all' );

			$this->vars['default_fields']     = OsSettingsHelper::get_default_fields_for_customer();
			$this->vars['custom_field_types'] = OsCustomFieldsHelper::get_custom_field_types();

			$this->format_render( __FUNCTION__ );
		}
	}

endif;
