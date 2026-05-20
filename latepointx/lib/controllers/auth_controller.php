<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}


if ( ! class_exists( 'OsAuthController' ) ) :


	class OsAuthController extends OsController {

		function __construct() {
			parent::__construct();
			$this->action_access['public'] = array_merge(
				$this->action_access['public'],
				[
					'logout_customer',
					'login_customer',
					'login_customer_using_social_data',
					'login_customer_using_google_token',
					'login_customer_using_facebook_token',
					'request_otp',
					'verify_otp',
					'resend_otp',
				] 
			);
			$this->views_folder            = LATEPOINT_VIEWS_ABSPATH . 'auth/';
		}

		public function verify_otp() {
			$this->check_nonce( 'otp_verify_otp_nonce', $this->params['otp']['verify_nonce'] );
			$otp_verification_params = $this->params_for_otp_verification();
			$otp_code                = $otp_verification_params['otp_code'];
			$contact_type            = $otp_verification_params['contact_type'];
			$contact_value           = $otp_verification_params['contact_value'];
			$delivery_method         = $otp_verification_params['delivery_method'];

			$result = OsOTPHelper::verifyOTP( $otp_code, $contact_value, $contact_type, $delivery_method );

			$message = __( 'Invalid Code', 'latepoint' );
			$status  = LATEPOINT_STATUS_ERROR;

			if ( is_wp_error( $result ) ) {
				$message = $result->get_error_message();
			} elseif ( $result['status'] == LATEPOINT_STATUS_ERROR ) {
				$message = $result['message'];
			} elseif ( $result['status'] == LATEPOINT_STATUS_SUCCESS ) {
				// Success
				$status  = LATEPOINT_STATUS_SUCCESS;
				$message = OsOTPHelper::create_verification_token( $contact_value, $contact_type );
				// if auth is enabled - make sure customer is logged in
				if ( OsAuthHelper::is_customer_auth_enabled() && ! OsAuthHelper::is_customer_logged_in() ) {
					$customer = OsCustomerHelper::get_by_contact( $contact_value, $contact_type );
					if ( $customer && ! $customer->is_new_record() ) {
						OsAuthHelper::authorize_customer( $customer->id );
					}
				}
			}
			$this->send_json(
				array(
					'status'  => $status,
					'message' => $message,
				) 
			);
		}

		public function resend_otp() {
			$this->check_nonce( 'otp_resend_otp_nonce', $this->params['otp']['resend_nonce'] );
			$otp_verification_params = $this->params_for_otp_verification();
			$contact_type            = $otp_verification_params['contact_type'];
			$contact_value           = $otp_verification_params['contact_value'];
			$delivery_method         = $otp_verification_params['delivery_method'];

			$result = OsOTPHelper::generateAndSendOTP( $contact_value, $contact_type, $delivery_method );

			$message = __( 'Error sending OTP', 'latepoint' );
			$status  = LATEPOINT_STATUS_ERROR;

			if ( is_wp_error( $result ) ) {
				$message = $result->get_error_message();
			} elseif ( $result['status'] == LATEPOINT_STATUS_ERROR ) {
				$message = $result['message'];
			} elseif ( $result['status'] == LATEPOINT_STATUS_SUCCESS ) {
				// Success
				$status  = LATEPOINT_STATUS_SUCCESS;
				$message = OsOTPHelper::otp_input_box_html( $contact_type, $contact_value, $delivery_method );
			}
			$this->send_json(
				array(
					'status'  => $status,
					'message' => $message,
				) 
			);
		}

		public function request_otp() {
			$this->check_nonce( 'auth_nonce', $this->params['auth']['nonce'] );

			$auth_params     = $this->params_for_otp_request();
			$contact_type    = $auth_params['contact_type'];
			$contact_value   = $auth_params[ $contact_type ];
			$delivery_method = $auth_params['delivery_method'];

			if ( OsAuthHelper::is_classic_auth_flow() ) {
				// in classic flow - you can't send a OTP request to a non existent account
				$customer = new OsCustomerModel();
				if ( $contact_type == 'email' ) {
					$existing_customer = $customer->where( [ 'email' => $contact_value ] )->set_limit( 1 )->get_results_as_models();
					if ( ! $existing_customer ) {
						$this->send_json(
							array(
								'status'  => LATEPOINT_STATUS_ERROR,
								'message' => __( 'We don\'t recognize this email. Double-check it or create an account.', 'latepoint' ),
							) 
						);
					}
				} elseif ( $contact_type == 'phone' ) {
					$existing_customer = $customer->where( [ 'phone' => $contact_value ] )->set_limit( 1 )->get_results_as_models();
					if ( ! $existing_customer ) {
						$this->send_json(
							array(
								'status'  => LATEPOINT_STATUS_ERROR,
								'message' => __( 'We don\'t recognize this phone number. Double-check it or create an account.', 'latepoint' ),
							) 
						);
					}
				}
			}

			$result = OsOTPHelper::generateAndSendOTP( $contact_value, $contact_type, $delivery_method );

			$message = __( 'Error sending OTP', 'latepoint' );
			$status  = LATEPOINT_STATUS_ERROR;

			if ( is_wp_error( $result ) ) {
				$message = $result->get_error_message();
			} elseif ( $result['status'] == LATEPOINT_STATUS_ERROR ) {
				$message = $result['message'];
			} elseif ( $result['status'] == LATEPOINT_STATUS_SUCCESS ) {
				// Success
				$status  = LATEPOINT_STATUS_SUCCESS;
				$message = OsOTPHelper::otp_input_box_html( $contact_type, $contact_value, $delivery_method );
			}
			$this->send_json(
				array(
					'status'  => $status,
					'message' => $message,
				) 
			);
		}


		private function params_for_otp_verification(): array {
			$params = OsParamsHelper::get_param( 'otp' );
			if ( empty( $params ) ) {
				return [];
			}

			$otp_params = OsParamsHelper::permit_params(
				$params,
				[
					'contact_value',
					'contact_type',
					'delivery_method',
					'otp_code',
				] 
			);

			$otp_params['otp_code']        = sanitize_text_field( $otp_params['otp_code'] );
			$otp_params['delivery_method'] = sanitize_text_field( $otp_params['delivery_method'] );

			if ( $otp_params['contact_type'] == 'phone' ) {
				$otp_params['contact_value'] = sanitize_text_field( $otp_params['contact_value'] );
			}

			if ( $otp_params['contact_type'] == 'email' ) {
				$otp_params['contact_value'] = sanitize_email( $otp_params['contact_value'] );
			}

			/**
			 * Filtered auth params for steps
			 *
			 * @param {array} $otp_params a filtered array of auth params
			 * @param {array} $params unfiltered 'auth' params
			 * @returns {array} $otp_params a filtered array of auth params
			 *
			 * @since 5.2.0
			 * @hook latepoint_auth_params_for_otp_verification
			 *
			 */
			return apply_filters( 'latepoint_auth_params_for_otp_verification', $otp_params, $params );
		}

		private function params_for_otp_request(): array {
			$params = OsParamsHelper::get_param( 'auth' );
			if ( empty( $params ) ) {
				return [];
			}

			$auth_params = OsParamsHelper::permit_params(
				$params,
				[
					'email',
					'phone',
					'contact_type',
					'delivery_method',
					'otp_code',
				] 
			);

			if ( ! empty( $auth_params['email'] ) ) {
				$auth_params['email'] = sanitize_email( $auth_params['email'] );
			}
			if ( ! empty( $auth_params['phone'] ) ) {
				$auth_params['phone'] = sanitize_text_field( $auth_params['phone'] );
			}
			if ( ! empty( $auth_params['otp_code'] ) ) {
				$auth_params['otp_code'] = sanitize_text_field( $auth_params['otp_code'] );
			}

			/**
			 * Filtered auth params for steps
			 *
			 * @param {array} $auth_params a filtered array of auth params
			 * @param {array} $params unfiltered 'auth' params
			 * @returns {array} $auth_params a filtered array of auth params
			 *
			 * @since 5.2.0
			 * @hook latepoint_params_for_otp_request
			 *
			 */
			return apply_filters( 'latepoint_params_for_otp_request', $auth_params, $params );
		}


		// Logs out customer and shows blank contact step
		public function logout_customer() {
			// Verify nonce.
			$this->check_nonce( 'auth_nonce', $this->params['auth']['nonce'] );

			OsAuthHelper::logout_customer();

			if ( $this->get_return_format() == 'json' ) {
				$this->send_json(
					array(
						'status'  => LATEPOINT_STATUS_SUCCESS,
						'message' => __( 'You have been logged out of your account.', 'latepoint' ),
					) 
				);
			}
		}

		// Login customer and show contact step with prefilled info
		public function login_customer() {
			// Verify nonce.
			$this->check_nonce( 'auth_nonce', $this->params['auth']['nonce'] );

			$contact_type  = $this->params['auth']['contact_type'];
			$contact_value = ( $contact_type == 'email' ) ? $this->params['auth']['email'] : $this->params['auth']['phone'];
			$customer      = OsAuthHelper::login_customer( $contact_value, $this->params['auth']['password'], $this->params['auth']['contact_type'] );
			if ( $customer ) {
				$status        = LATEPOINT_STATUS_SUCCESS;
				$customer_id   = $customer->id;
				$response_html = __( 'Welcome back', 'latepoint' );
			} else {
				$status = LATEPOINT_STATUS_ERROR;
				if ( $contact_type == 'email' ) {
					$response_html = __( 'Sorry, that email or password didn\'t work.', 'latepoint' );
				} elseif ( $contact_type == 'phone' ) {
					$response_html = __( 'Sorry, that phone number or password didn\'t work.', 'latepoint' );
				} else {
					$response_html = __( 'Sorry, that didn\'t work.', 'latepoint' );
				}
				$customer_id = '';
			}
			if ( $this->get_return_format() == 'json' ) {
				$this->send_json(
					array(
						'status'      => $status,
						'message'     => $response_html,
						'customer_id' => $customer_id,
					) 
				);
			}
		}

		public function login_customer_using_social_data( $network, $social_user ) {
			$customer_id = '';
			if ( isset( $social_user['social_id'] ) ) {
				$customer_was_updated = false;
				$old_customer_data    = [];
				$social_id_field_name = $network . '_user_id';
				$status               = LATEPOINT_STATUS_SUCCESS;
				$response_html        = $social_user['social_id'];
				// Search for existing customer with email that google provided
				$customer = new OsCustomerModel();
				$customer = $customer->where( array( 'email' => $social_user['email'] ) )->set_limit( 1 )->get_results_as_models();
				if ( OsAuthHelper::can_wp_users_login_as_customers() ) {
					if ( $customer->wordpress_user_id != email_exists( $social_user['email'] ) ) {
						$old_customer_data = $customer->get_data_vars();
						$customer->update_attributes( [ 'wordpress_user_id' => null ] );
						$wp_user_id           = OsCustomerHelper::create_wp_user_for_customer( $customer );
						$customer_was_updated = true;
						if ( ! $wp_user_id ) {
							$status        = LATEPOINT_STATUS_ERROR;
							$response_html = __( 'Error creating wp user', 'latepoint' );
						}
					}
				}
				// Create customer if its not found
				if ( ! $customer ) {
					$customer                        = new OsCustomerModel();
					$customer->first_name            = $social_user['first_name'];
					$customer->last_name             = $social_user['last_name'];
					$customer->email                 = $social_user['email'];
					$customer->$social_id_field_name = $social_user['social_id'];
					if ( ! $customer->save( true ) ) {
						$response_html = $customer->get_error_messages();
						$status        = LATEPOINT_STATUS_ERROR;
					} else {
						do_action( 'latepoint_customer_created', $customer );
					}
				}

				if ( ( $status == LATEPOINT_STATUS_SUCCESS ) && $customer->id ) {
					$customer_id = $customer->id;
					// Update customer google user id if its not set yet
					if ( $customer->$social_id_field_name != $social_user['social_id'] ) {
						$old_customer_data               = $customer->get_data_vars();
						$customer->$social_id_field_name = $social_user['social_id'];
						$customer->save();
						$customer_was_updated = true;
					}
					OsAuthHelper::authorize_customer( $customer->id );
					$response_html = __( 'Welcome back', 'latepoint' );
				}
				if ( $customer_was_updated && $old_customer_data ) {
					do_action( 'latepoint_customer_updated', $customer, $old_customer_data );
				}
			} else {
				// ERROR WITH GOOGLE LOGIN
				$status        = LATEPOINT_STATUS_ERROR;
				$response_html = $social_user['error'];
			}
			if ( $this->get_return_format() == 'json' ) {
				$this->send_json(
					array(
						'status'      => $status,
						'message'     => $response_html,
						'customer_id' => $customer_id,
					) 
				);
			}
		}


		public function login_customer_using_google_token() {
			// Condition for pro compatibility. Remove later.
			if ( isset( $this->params['nonce'] ) ) {
				// Verify nonce.
				$this->check_nonce( 'social_login_nonce', $this->params['nonce'] );
			}

			$social_user = [];
			$token       = sanitize_text_field( $this->params['token'] );
			$social_user = apply_filters( 'latepoint_get_social_user_by_token', $social_user, 'google', $token );
			if ( ! empty( $social_user ) ) {
				$this->login_customer_using_social_data( 'google', $social_user );
			}
		}

		public function login_customer_using_facebook_token() {
			// Condition for pro compatibility. Remove later.
			if ( isset( $this->params['nonce'] ) ) {
				// Verify nonce.
				$this->check_nonce( 'social_login_nonce', $this->params['nonce'] );
			}

			$social_user = [];
			$token       = sanitize_text_field( $this->params['token'] );
			$social_user = apply_filters( 'latepoint_get_social_user_by_token', $social_user, 'facebook', $token );
			if ( ! empty( $social_user ) ) {
				$this->login_customer_using_social_data( 'facebook', $social_user );
			}
		}
	}
endif;
