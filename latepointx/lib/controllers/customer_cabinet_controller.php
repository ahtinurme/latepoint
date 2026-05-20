<?php
/*
 * Copyright (c) 2023 LatePoint LLC. All rights reserved.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}


if ( ! class_exists( 'OsCustomerCabinetController' ) ) :


	class OsCustomerCabinetController extends OsController {

		function __construct() {
			parent::__construct();

			$this->action_access['customer'] = array_merge(
				$this->action_access['customer'],
				[
					'update',
					'request_cancellation',
					'print_booking_info',
					'print_order_info',
					'ical_download',
					'process_reschedule_request',
					'request_reschedule_calendar',
					'view_order_summary_in_lightbox',
					'view_booking_summary_in_lightbox',
					'scheduling_summary_for_bundle',
					'reload_booking_tile',
				]
			);
			$this->action_access['public']   = array_merge(
				$this->action_access['public'],
				[
					'logout',
					'dashboard',
					'login',
					'do_login',
					'password_reset_form',
					'request_password_reset_token',
					'change_password',
					'set_account_password_on_booking_completion',
				]
			);
			$this->views_folder              = LATEPOINT_VIEWS_ABSPATH . 'customer_cabinet/';
		}


		public function scheduling_summary_for_bundle() {
			if ( ! filter_var( $this->params['order_item_id'], FILTER_VALIDATE_INT ) ) {
				exit();
			}
			$order_item = new OsOrderItemModel( $this->params['order_item_id'] );
			$order      = new OsOrderModel( $order_item->order_id );

			if ( $order->is_new_record() || ( $order->customer_id != OsAuthHelper::get_logged_in_customer_id() ) ) {
				$this->send_json(
					array(
						'status'  => LATEPOINT_STATUS_ERROR,
						'message' => __( 'Not Allowed', 'latepoint' ),
					)
				);
			}

			$bundle                   = $order_item->build_original_object_from_item_data();
			$this->vars['order_item'] = $order_item;
			$this->vars['bundle']     = $bundle;
			$this->format_render( __FUNCTION__ );
		}

		public function view_order_summary_in_lightbox() {
			if ( ! filter_var( $this->params['order_id'], FILTER_VALIDATE_INT ) ) {
				exit();
			}
			$order = new OsOrderModel( $this->params['order_id'] );

			if ( $order->is_new_record() || ( $order->customer_id != OsAuthHelper::get_logged_in_customer_id() ) ) {
				$this->send_json(
					array(
						'status'  => LATEPOINT_STATUS_ERROR,
						'message' => __( 'Not Allowed', 'latepoint' ),
					)
				);
			}

			$this->vars['order']                = $order;
			$this->vars['price_breakdown_rows'] = $order->generate_price_breakdown_rows();
			$this->format_render( __FUNCTION__ );
		}

		public function view_booking_summary_in_lightbox() {
			if ( ! filter_var( $this->params['booking_id'], FILTER_VALIDATE_INT ) ) {
				exit();
			}
			$booking    = new OsBookingModel( $this->params['booking_id'] );
			$order_item = new OsOrderItemModel( $booking->order_item_id );
			$order      = new OsOrderModel( $order_item->order_id );

			if ( $order->is_new_record() || ( $order->customer_id != OsAuthHelper::get_logged_in_customer_id() ) ) {
				$this->send_json(
					array(
						'status'  => LATEPOINT_STATUS_ERROR,
						'message' => __( 'Not Allowed', 'latepoint' ),
					)
				);
			}

			$this->vars['booking']    = $booking;
			$this->vars['order_item'] = $order_item;
			$this->vars['order']      = $order;
			$this->format_render( __FUNCTION__ );
		}


		function print_order_info() {
			if ( ! filter_var( $this->params['latepoint_order_id'], FILTER_VALIDATE_INT ) ) {
				exit();
			}
			$order_id = $this->params['latepoint_order_id'];
			if ( empty( $order_id ) ) {
				return;
			}
			$order = new OsOrderModel( $order_id );
			if ( $order->id && OsAuthHelper::is_customer_logged_in() && ( $order->customer_id == OsAuthHelper::get_logged_in_customer_id() ) ) {
				$customer               = $order->customer;
				$this->vars['order']    = $order;
				$this->vars['customer'] = $customer;
				$this->set_layout( 'print' );
				$content = $this->format_render_return( __FUNCTION__, [], [], true );
				echo $content;
			}
		}

		function print_booking_info() {
			if ( ! filter_var( $this->params['latepoint_booking_id'], FILTER_VALIDATE_INT ) ) {
				exit();
			}
			$booking_id = $this->params['latepoint_booking_id'];
			if ( empty( $booking_id ) ) {
				return;
			}
			$booking = new OsBookingModel( $booking_id );
			if ( $booking->id && OsAuthHelper::is_customer_logged_in() && ( $booking->customer_id == OsAuthHelper::get_logged_in_customer_id() ) ) {
				$customer               = $booking->customer;
				$this->vars['booking']  = $booking;
				$this->vars['customer'] = $customer;
				$this->set_layout( 'print' );
				$content = $this->format_render_return( __FUNCTION__, [], [], true );
				echo $content;
			}
		}

		function ical_download() {
			if ( ! filter_var( $this->params['latepoint_booking_id'], FILTER_VALIDATE_INT ) ) {
				exit();
			}
			$booking_id = $this->params['latepoint_booking_id'];
			if ( empty( $booking_id ) ) {
				return;
			}
			$booking = new OsBookingModel( $booking_id );
			if ( $booking->id && OsAuthHelper::is_customer_logged_in() && ( $booking->customer_id == OsAuthHelper::get_logged_in_customer_id() ) ) {

				header( 'Content-Type: text/calendar; charset=utf-8' );
				header( 'Content-Disposition: attachment; filename=booking_' . $booking->id . '.ics' );

				echo OsBookingHelper::generate_ical_event_string( $booking );
			}
		}


		function process_reschedule_request() {
			if ( ! filter_var( $this->params['booking_id'], FILTER_VALIDATE_INT ) ) {
				exit();
			}
			$booking = new OsBookingModel( $this->params['booking_id'] );

			if ( empty( $booking->id ) || empty( $this->params['start_date'] ) || empty( $this->params['start_time'] ) ) {
				return;
			}

			if ( ( OsAuthHelper::get_logged_in_customer_id() == $booking->customer_id ) && OsCustomerHelper::can_reschedule_booking( $booking ) ) {
				$old_booking         = clone $booking;
				$booking->start_date = $this->params['start_date'];
				$booking->start_time = $this->params['start_time'];

				$booking->convert_start_datetime_into_server_timezone( $booking->get_customer_timezone_name() );

				if ( $booking->is_start_date_and_time_set() ) {
					$booking->calculate_end_date_and_time();
					$booking->set_utc_datetimes();
				}

				// check if booking time is still available
				if ( ! OsBookingHelper::is_booking_request_available( \LatePoint\Misc\BookingRequest::create_from_booking_model( $booking ), [ 'exclude_booking_ids' => [ $booking->id ] ] ) ) {
					$response_html = __( 'Unfortunately the selected time slot is not available anymore, please select another timeslot.', 'latepoint' );
					$status        = LATEPOINT_STATUS_ERROR;
				} else {
					if ( OsSettingsHelper::is_on( 'change_status_on_customer_reschedule' ) ) {
						$allowed_statuses = OsBookingHelper::get_statuses_list();
						if ( isset( $allowed_statuses[ OsSettingsHelper::get_settings_value( 'status_to_set_after_customer_reschedule' ) ] ) ) {
							$booking->status = OsSettingsHelper::get_settings_value( 'status_to_set_after_customer_reschedule' );
						}
					}
					if ( $booking->save() ) {
						/**
						 * Booking is updated
						 *
						 * @param {OsBookingModel} $this->>booking Updated instance of booking model
						 * @param {OsBookingModel} $old_booking Instance of booking model before it was updated
						 *
						 * @since 4.9.0
						 * @hook latepoint_booking_updated
						 *
						 */
						do_action( 'latepoint_booking_updated', $booking, $old_booking );
						$this->vars['booking']       = $booking;
						$this->vars['timezone_name'] = OsTimeHelper::get_timezone_name_from_session();
						$this->vars['viewer']        = 'customer';
						$status                      = LATEPOINT_STATUS_SUCCESS;
						$this->set_layout( 'none' );
						$response_html = $this->format_render_return( __FUNCTION__, [], [], true );
					} else {
						OsDebugHelper::log( 'Error rescheduling appointment', 'booking_reschedule_error', $booking->get_error_messages() );
						$response_html = __( 'Error! Please try again later', 'latepoint' );
						$status        = LATEPOINT_STATUS_ERROR;
					}
				}
			} else {
				$status        = LATEPOINT_STATUS_ERROR;
				$response_html = __( 'Error! LKDFU343', 'latepoint' );
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

		function request_reschedule_calendar() {
			if ( ! filter_var( $this->params['booking_id'], FILTER_VALIDATE_INT ) ) {
				exit();
			}
			$booking = new OsBookingModel( $this->params['booking_id'] );

			if ( ! empty( $booking->id ) && ( OsAuthHelper::get_logged_in_customer_id() == $booking->customer_id ) && OsCustomerHelper::can_reschedule_booking( $booking ) ) {
				$this->vars['booking']             = $booking;
				$this->vars['calendar_start_date'] = ! empty( $this->params['calendar_start_date'] ) ? new OsWpDateTime( $this->params['calendar_start_date'] ) : new OsWpDateTime( 'today' );
				$this->vars['timezone_name']       = $booking->get_customer_timezone_name();

				$this->set_layout( 'none' );
				$response_html = $this->format_render_return( __FUNCTION__, [], [], true );
			} else {
				$status        = LATEPOINT_STATUS_ERROR;
				$response_html = __( 'Reschedule is not allowed', 'latepoint' );
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

		function request_cancellation() {
			if ( ! filter_var( $this->params['id'], FILTER_VALIDATE_INT ) ) {
				exit();
			}

			$booking_id = $this->params['id'];
			$this->check_nonce( 'cancel_booking_' . $booking_id );
			$booking = new OsBookingModel( $booking_id );
			if ( ! empty( $booking->id ) && ( OsAuthHelper::get_logged_in_customer_id() == $booking->customer_id ) && OsCustomerHelper::can_cancel_booking( $booking ) ) {
				if ( $booking->update_status( LATEPOINT_BOOKING_STATUS_CANCELLED ) ) {
					$status        = LATEPOINT_STATUS_SUCCESS;
					$response_html = __( 'Appointment Status Updated', 'latepoint' );
				} else {
					$status        = LATEPOINT_STATUS_ERROR;
					$response_html = __( 'Error Updating Booking Status!', 'latepoint' ) . ' ' . implode( ',', $booking->get_error_messages() );
				}
			} else {
				$status        = LATEPOINT_STATUS_ERROR;
				$response_html = __( 'Not allowed to cancel', 'latepoint' );
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
		  Update profile
		*/

		public function update() {
			$customer = OsAuthHelper::get_logged_in_customer();
			if ( ! $customer ) {
				exit();
			}
			$this->check_nonce( 'update_customer_' . $customer->get_uuid() );


			if ( $customer ) {
				$old_customer_data = $customer->get_data_vars();
				$customer->set_data( $this->params['customer'], LATEPOINT_PARAMS_SCOPE_CUSTOMER );
				if ( $customer->save() ) {
					$response_html = __( 'Information Saved', 'latepoint' );
					$status        = LATEPOINT_STATUS_SUCCESS;
					do_action( 'latepoint_customer_updated', $customer, $old_customer_data );
				} else {
					$response_html = $customer->get_error_messages();
					$status        = LATEPOINT_STATUS_ERROR;
				}
			} else {
				$response_html = __( 'Customer not found', 'latepoint' );
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

		public function reload_booking_tile() {
			if ( ! filter_var( $this->params['booking_id'], FILTER_VALIDATE_INT ) ) {
				exit();
			}

			$booking_id = $this->params['booking_id'];
			$booking    = new OsBookingModel( $booking_id );

			if ( $booking->id && OsAuthHelper::get_logged_in_customer_id() == $booking->customer_id ) {
				$this->vars['booking']             = $booking;
				$this->vars['is_upcoming_booking'] = $booking->is_upcoming();
				$this->set_layout( 'none' );
				$response_html = $this->format_render_return( '_booking_tile' );
				$status        = LATEPOINT_STATUS_SUCCESS;
			} else {
				$response_html = __( 'Invalid Booking', 'latepoint' );
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

		public function logout() {
			OsAuthHelper::logout_customer();
			nocache_headers();
			wp_redirect( OsSettingsHelper::get_customer_dashboard_url(), 302 );
		}

		public function login() {
			$this->set_layout( 'none' );

			return $this->format_render_return( __FUNCTION__ );
		}

		public function do_login() {
			$customer = OsAuthHelper::login_customer( sanitize_email( $this->params['auth']['email'] ), $this->params['auth']['password'] );
			if ( $customer ) {
				$response_html = OsSettingsHelper::get_customer_dashboard_url();
				$status        = LATEPOINT_STATUS_SUCCESS;
			} else {
				$status        = LATEPOINT_STATUS_ERROR;
				$response_html = __( 'Invalid password or email', 'latepoint' );
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


		public function password_reset_form() {
			$this->vars['from_booking'] = ( isset( $this->params['from_booking'] ) && $this->params['from_booking'] );
			$this->set_layout( 'none' );

			return $this->format_render_return( __FUNCTION__ );
		}

		public function request_password_reset_token() {
			$this->set_layout( 'none' );
			$this->vars['from_booking'] = ( isset( $this->params['from_booking'] ) && $this->params['from_booking'] );

			if ( isset( $this->params['password_reset_email'] ) ) {
				$customer_model  = new OsCustomerModel();
				$customer        = $customer_model->where( [ 'email' => sanitize_email( $this->params['password_reset_email'] ) ] )->set_limit( 1 )->get_results_as_models();
				$customer_mailer = new OsCustomerMailer();

				if ( $customer ) {
					$customer->account_nonse = sha1( wp_rand( 10000, 99999 ) . time() . wp_generate_password( 32, true, true ) );
					$customer->save();
				}

				if ( $customer && $customer_mailer->password_reset_request( $customer, $customer->account_nonse ) ) {
					// Save timestamp for token expiration tracking (Unix timestamp for consistency)
					OsMetaHelper::save_customer_meta_by_key( 'password_reset_token_created_at', current_time( 'timestamp' ), $customer->id );
					return $this->format_render_return( 'password_reset_form' );
				} else {
					$this->vars['reset_token_error'] = ( $customer ) ? __( 'Error! Email was not sent.', 'latepoint' ) : __( 'Email does not match any customer', 'latepoint' );

					return $this->format_render_return( __FUNCTION__ );
				}
			} else {
				return $this->format_render_return( __FUNCTION__ );
			}
		}

		public function dashboard( array $params = [] ) {
			if ( ! OsAuthHelper::is_customer_logged_in() ) {
				$this->set_layout( 'none' );

				return $this->format_render_return( 'login' );
			} else {
				$customer               = OsAuthHelper::get_logged_in_customer();
				$this->vars['customer'] = $customer;
				$this->vars['orders']   = $customer->get_orders();

				$this->vars['future_bookings']       = $customer->get_future_bookings();
				$this->vars['past_bookings']         = $customer->get_past_bookings();
				$this->vars['cancelled_bookings']    = $customer->get_cancelled_bookings();
				$this->vars['not_scheduled_bundles'] = $customer->get_not_scheduled_bundles();

				$this->vars['cart_not_empty'] = ( ! OsCartsHelper::is_current_cart_empty() && OsCartsHelper::can_checkout_multiple_items() );

				$this->vars['hide_new_appointment_ui'] = $params['hide_new_appointment_ui'] ?? false;

				$this->set_layout( 'none' );

				return $this->format_render_return( __FUNCTION__ );
			}
		}

		public function change_password() {
			$params = OsParamsHelper::permit_params(
				$this->params,
				[
					'password_reset_token',
					'password',
					'password_confirmation',
					'change_password_nonce',
				]
			);

			if ( empty( $params['password'] ) ) {
				$this->send_json(
					array(
						'status'  => LATEPOINT_STATUS_ERROR,
						'message' => __( 'Password can not be blank', 'latepoint' ),
					)
				);
			}

			$status        = LATEPOINT_STATUS_ERROR;
			$response_html = __( 'Unknown Error', 'latepoint' );
			$customer      = false;

			if ( OsAuthHelper::is_customer_logged_in() ) {
				$this->check_nonce( 'change_password_' . OsAuthHelper::get_logged_in_customer_uuid(), $params['change_password_nonce'] );
				$customer = OsAuthHelper::get_logged_in_customer();
			} elseif ( ! empty( $params['password_reset_token'] ) ) {
				$params['password_reset_token'] = sanitize_text_field( $params['password_reset_token'] );
				$customer                       = OsCustomerHelper::get_by_account_nonse( $params['password_reset_token'] );

				// Validate token expiration (15 minutes)
				if ( $customer ) {
					$created_at = OsMetaHelper::get_customer_meta_by_key( 'password_reset_token_created_at', $customer->id );

					if ( empty( $created_at ) ) {
						// No timestamp - reject for security (token created before fix)
						$customer      = false;
						$status        = LATEPOINT_STATUS_ERROR;
						$response_html = __( 'Password token is invalid. Please request a new password reset.', 'latepoint' );
					} else {
						$age_seconds = current_time( 'timestamp' ) - intval( $created_at );
						if ( $age_seconds > 900 ) { // 15 minutes = 900 seconds
							// Clean up expired meta
							OsMetaHelper::delete_customer_meta_by_key( 'password_reset_token_created_at', $customer->id );
							$customer      = false;
							$status        = LATEPOINT_STATUS_ERROR;
							$response_html = __( 'Password token has expired. Please request a new password reset.', 'latepoint' );
						}
					}
				}
			}

			if ( $customer ) {
				if ( ! empty( $params['password'] ) && $params['password'] == $params['password_confirmation'] ) {
					if ( $customer->update_password( $params['password'] ) ) {
						// Invalidate token after successful password reset
						if ( ! empty( $params['password_reset_token'] ) ) {
							OsCustomerHelper::invalidate_password_reset_token( $customer );
						}
						$status        = LATEPOINT_STATUS_SUCCESS;
						$response_html = __( 'Your password was successfully updated.', 'latepoint' );
					} else {
						$response_html = __( 'Error! Message Code: KS723J', 'latepoint' );
						$status        = LATEPOINT_STATUS_ERROR;
					}
				} else {
					$response_html = __( 'Error! Passwords do not match.', 'latepoint' );
					$status        = LATEPOINT_STATUS_ERROR;
				}
			} else {
				$status        = LATEPOINT_STATUS_ERROR;
				$response_html = __( 'Customer Not Found', 'latepoint' );
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

		public function set_account_password_on_booking_completion() {
			$customer = OsAuthHelper::get_logged_in_customer();

			if ( $customer ) {
				$params = OsParamsHelper::permit_params(
					$this->params,
					[
						'password',
						'password_nonce',
					]
				);
				$this->check_nonce( 'set_initial_password_for_customer_' . $customer->get_uuid(), $params['password_nonce'] );
				if ( ! empty( $params['password'] ) ) {
					if ( $customer->update_password( $params['password'] ) ) {
						$status        = LATEPOINT_STATUS_SUCCESS;
						$response_html = __( 'Account Password Set', 'latepoint' );
					} else {
						$response_html = __( 'Error! Message Code: KS723J', 'latepoint' );
						$status        = LATEPOINT_STATUS_ERROR;
					}
				} else {
					$status        = LATEPOINT_STATUS_ERROR;
					$response_html = __( 'Error! Password is empty.', 'latepoint' );
				}
			} else {
				$response_html = __( 'Invalid request', 'latepoint' );
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
	}


endif;
