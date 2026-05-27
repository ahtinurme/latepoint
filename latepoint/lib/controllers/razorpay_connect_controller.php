<?php
/*
 * Copyright (c) 2024 LatePoint LLC. All rights reserved.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'OsRazorpayConnectController' ) ) :

	class OsRazorpayConnectController extends OsController {

		function __construct() {
			parent::__construct();
			$this->action_access['public'] = array_merge( $this->action_access['public'], [ 'webhook', 'create_razorpay_order', 'create_razorpay_order_for_transaction' ] );
			$this->views_folder            = LATEPOINT_VIEWS_ABSPATH . 'razorpay_connect/';
		}

		private function get_env_from_params(): string {
			return ( ! empty( $this->params['env'] ) && in_array( $this->params['env'], [ LATEPOINT_PAYMENTS_ENV_LIVE, LATEPOINT_PAYMENTS_ENV_DEV ] ) )
				? $this->params['env']
				: OsSettingsHelper::get_payments_environment();
		}

		public function create_razorpay_order() {
			try {
				OsStepsHelper::set_required_objects( $this->params );

				$booking_form_page_url = $this->params['booking_form_page_url'] ?? OsUtilHelper::get_referrer();
				$order_intent          = OsOrderIntentHelper::create_or_update_order_intent(
					OsStepsHelper::$cart_object,
					OsStepsHelper::$restrictions,
					OsStepsHelper::$presets,
					$booking_form_page_url,
					OsStepsHelper::get_customer_object_id()
				);

				if ( ! $order_intent->is_bookable() ) {
					throw new Exception( empty( $order_intent->get_error_messages() ) ? __( 'Booking slot is not available anymore.', 'latepoint' ) : implode( ', ', $order_intent->get_error_messages() ) );
				}

				if ( ! OsSettingsHelper::get_settings_value( OsSettingsHelper::append_payment_env_key( 'razorpay_connect_account_id' ) ) ) {
					throw new Exception( __( 'Razorpay connect account ID not set', 'latepoint' ) );
				}

				$order_data = OsRazorpayConnectHelper::generate_razorpay_order_for_order_intent( $order_intent );

				$order_data['options']['name']        = OsSettingsHelper::get_settings_value( 'razorpay_connect_company_name', 'RazorPay' );
				$order_data['options']['description'] = 'LatePoint Order Intent Key: ' . $order_intent->intent_key;
				$order_data['options']['image']       = OsImageHelper::get_image_url_by_id(
					OsSettingsHelper::get_settings_value( 'razorpay_connect_logo_image_id', false ),
					'thumbnail',
					get_site_icon_url()
				);
				$order_data['options']['theme']       = [
					'color' => OsSettingsHelper::get_settings_value( 'razorpay_connect_theme_color', '#4366ff' ),
				];
				$order_data['options']['notes']       = [
					'order_intent_key' => $order_intent->intent_key,
				];
				$customer                             = OsStepsHelper::get_customer_object();
				if ( $customer ) {
					$order_data['options']['prefill'] = [
						'name'    => $customer->full_name,
						'email'   => $customer->email,
						'contact' => $customer->phone,
					];
				}

				if ( $this->get_return_format() === 'json' ) {
					$this->send_json(
						[
							'status'                    => LATEPOINT_STATUS_SUCCESS,
							'continue_order_intent_url' => OsOrderIntentHelper::generate_continue_intent_url( $order_intent->intent_key ),
							'order_intent_key'          => $order_intent->intent_key,
							'razorpay_order_id'         => $order_data['order_id'],
							'amount'                    => $order_data['amount'],
							'currency'                  => $order_data['currency'],
							'options'                   => $order_data['options'],
						]
					);
				}
			} catch ( Exception $e ) {
				if ( $this->get_return_format() === 'json' ) {
					$this->send_json(
						[
							'status'  => LATEPOINT_STATUS_ERROR,
							'message' => $e->getMessage(),
						]
					);
				}
			}
		}

		public function create_razorpay_order_for_transaction() {
			try {
				$invoice_access_key = sanitize_text_field( $this->params['key'] ?? '' );
				if ( empty( $invoice_access_key ) ) {
					throw new Exception( __( 'Invoice not found', 'latepoint' ) );
				}
				$invoice = OsInvoicesHelper::get_invoice_by_key( $invoice_access_key );
				if ( ! ( $invoice instanceof OsInvoiceModel ) || $invoice->is_new_record() ) {
					throw new Exception( __( 'Invoice not found', 'latepoint' ) );
				}

				$transaction_intent = OsTransactionIntentHelper::create_or_update_transaction_intent( $invoice, $this->params );

				if ( ! OsSettingsHelper::get_settings_value( OsSettingsHelper::append_payment_env_key( 'razorpay_connect_account_id' ) ) ) {
					throw new Exception( __( 'Razorpay connect account ID not set', 'latepoint' ) );
				}

				$order_data = OsRazorpayConnectHelper::generate_razorpay_order_for_transaction_intent( $transaction_intent );

				$order_data['options']['name']        = OsSettingsHelper::get_settings_value( 'razorpay_connect_company_name', 'RazorPay' );
				$order_data['options']['description'] = 'LatePoint Transaction Intent Key: ' . $transaction_intent->intent_key;
				$order_data['options']['image']       = OsImageHelper::get_image_url_by_id(
					OsSettingsHelper::get_settings_value( 'razorpay_connect_logo_image_id', false ),
					'thumbnail',
					get_site_icon_url()
				);
				$order_data['options']['theme']       = [
					'color' => OsSettingsHelper::get_settings_value( 'razorpay_connect_theme_color', '#4366ff' ),
				];
				$order_data['options']['notes']       = [
					'transaction_intent_key' => $transaction_intent->intent_key,
				];
				if ( ! empty( $transaction_intent->customer_id ) ) {
					$customer                         = new OsCustomerModel( $transaction_intent->customer_id );
					$order_data['options']['prefill'] = [
						'name'    => $customer->full_name,
						'email'   => $customer->email,
						'contact' => $customer->phone,
					];
				}

				if ( $this->get_return_format() === 'json' ) {
					$this->send_json(
						[
							'status'                 => LATEPOINT_STATUS_SUCCESS,
							'continue_transaction_intent_url' => OsTransactionIntentHelper::generate_continue_intent_url( $transaction_intent->intent_key ),
							'transaction_intent_key' => $transaction_intent->intent_key,
							'razorpay_order_id'      => $order_data['order_id'],
							'amount'                 => $order_data['amount'],
							'currency'               => $order_data['currency'],
							'options'                => $order_data['options'],
						]
					);
				}
			} catch ( Exception $e ) {
				if ( $this->get_return_format() === 'json' ) {
					$this->send_json(
						[
							'status'  => LATEPOINT_STATUS_ERROR,
							'message' => $e->getMessage(),
						]
					);
				}
			}
		}

		public function webhook() {
			$payload = @file_get_contents( 'php://input' );
			$data    = json_decode( $payload, true );

			if ( empty( $data['server_token'] ) || empty( $data['razorpay_account_id'] ) || $data['server_token'] !== OsRazorpayConnectHelper::get_server_token() || $data['razorpay_account_id'] !== OsRazorpayConnectHelper::get_connect_account_id() ) {
				http_response_code( 400 );
				echo 'Validation issue with webhook';
				exit();
			}

			if ( empty( $data['event'] ) || empty( $data['event']['type'] ) ) {
				http_response_code( 400 );
				exit();
			}

			$event = $data['event'];
			switch ( $event['type'] ) {
				case 'payment.captured':
					if ( ! empty( $event['data']['order_intent_key'] ) ) {
						$order_intent = OsOrderIntentHelper::get_order_intent_by_intent_key( $event['data']['order_intent_key'] );
						if ( $order_intent->is_new_record() ) {
							OsDebugHelper::log( 'Error processing Razorpay connect webhook: Order intent not found' );
							http_response_code( 400 );
							exit();
						}
						// Store the payment ID so process_payment() can verify it
						$payment_data          = json_decode( $order_intent->payment_data, true ) ?? [];
						$payment_data['token'] = sanitize_text_field( $event['data']['payment_id'] ?? '' );
						$order_intent->update_attributes( [ 'payment_data' => wp_json_encode( $payment_data ) ] );

						if ( $order_intent->convert_to_order() ) {
							http_response_code( 200 );
						} else {
							http_response_code( 400 );
							OsDebugHelper::log( 'Error converting order intent', 'razorpay_connect_webhook', $order_intent->get_error_messages() );
						}
					}
					if ( ! empty( $event['data']['transaction_intent_key'] ) ) {
						$transaction_intent = OsTransactionIntentHelper::get_transaction_intent_by_intent_key( $event['data']['transaction_intent_key'] );
						if ( $transaction_intent->is_new_record() ) {
							OsDebugHelper::log( 'Error processing Razorpay connect webhook: Transaction intent not found' );
							http_response_code( 400 );
							exit();
						}
						$payment_data          = json_decode( $transaction_intent->payment_data, true ) ?? [];
						$payment_data['token'] = sanitize_text_field( $event['data']['payment_id'] ?? '' );
						$transaction_intent->update_attributes( [ 'payment_data' => wp_json_encode( $payment_data ) ] );

						if ( $transaction_intent->convert_to_transaction() ) {
							http_response_code( 200 );
						} else {
							http_response_code( 400 );
							OsDebugHelper::log( 'Error converting transaction intent', 'razorpay_connect_webhook' );
						}
					}
					break;
			}
			exit();
		}

		public function start_connect_process() {
			$env = $this->get_env_from_params();
			OsSettingsHelper::save_setting_by_name( OsSettingsHelper::append_payment_env_key( 'enable_payment_processor_razorpay_connect', $env ), LATEPOINT_VALUE_ON );
			$url = OsRazorpayConnectHelper::get_connect_url( $env );
			$this->send_json(
				[
					'status'  => LATEPOINT_STATUS_SUCCESS,
					'url'     => $url,
					'message' => __( 'Redirecting to Razorpay', 'latepoint' ),
				]
			);
		}

		public function disconnect_connect_account() {
			$env = $this->get_env_from_params();
			try {
				$account_id = OsRazorpayConnectHelper::get_connect_account_id( $env );
				$path       = $account_id . '/server-tokens/' . OsRazorpayConnectHelper::get_server_token( $env ) . '/disconnect/';
				$response   = OsRazorpayConnectHelper::do_request( $path, '', 'DELETE', [], [], $env );
				if ( $response['status']['code'] === 200 ) {
					OsSettingsHelper::remove_setting_by_name( OsSettingsHelper::append_payment_env_key( 'razorpay_connect_charges_enabled', $env ) );
					OsSettingsHelper::remove_setting_by_name( OsSettingsHelper::append_payment_env_key( 'razorpay_connect_account_id', $env ) );
					OsSettingsHelper::remove_setting_by_name( OsSettingsHelper::append_payment_env_key( 'razorpay_connect_public_token', $env ) );
					OsRazorpayConnectHelper::reset_server_token( $env );
				} else {
					OsDebugHelper::log( 'Razorpay Connect Error', 'razorpay_connect_disconnect_error', $response );
				}
			} catch ( Exception $e ) {
				OsDebugHelper::log( 'Error disconnecting Razorpay account', 'razorpay_connect_error', [ 'error_message' => $e->getMessage() ] );
				$this->send_json(
					[
						'status'  => LATEPOINT_STATUS_ERROR,
						'message' => $e->getMessage(),
					]
				);
			}
			$this->send_json(
				[
					'status'  => LATEPOINT_STATUS_SUCCESS,
					'message' => OsRazorpayConnectHelper::get_connection_buttons_and_status( $env ),
				]
			);
		}

		public function check_connect_status() {
			$env = $this->get_env_from_params();
			try {
				$response = OsRazorpayConnectHelper::do_request( 'server-tokens/' . OsRazorpayConnectHelper::get_server_token( $env ) . '/status', '', 'GET', [], [], $env );
				if ( $env === LATEPOINT_PAYMENTS_ENV_LIVE ) {
					$fee_info = empty( $response['data']['transaction_fee_info'] ) ? '0' : $response['data']['transaction_fee_info'];
					OsSettingsHelper::save_setting_by_name( 'razorpay_connect_transaction_fee_info', $fee_info );
				}
				if ( ! empty( $response['data'] ) && ! empty( $response['data']['account_id'] ) ) {
					OsSettingsHelper::save_setting_by_name( OsSettingsHelper::append_payment_env_key( 'razorpay_connect_account_id', $env ), $response['data']['account_id'] );
					OsSettingsHelper::save_setting_by_name( OsSettingsHelper::append_payment_env_key( 'razorpay_connect_public_token', $env ), $response['data']['public_token'] ?? '' );
					if ( ! empty( $response['data']['charges_enabled'] ) ) {
						OsSettingsHelper::save_setting_by_name( OsSettingsHelper::append_payment_env_key( 'razorpay_connect_charges_enabled', $env ), LATEPOINT_VALUE_ON );
					} else {
						OsSettingsHelper::remove_setting_by_name( OsSettingsHelper::append_payment_env_key( 'razorpay_connect_charges_enabled', $env ) );
					}
				} else {
					OsSettingsHelper::remove_setting_by_name( OsSettingsHelper::append_payment_env_key( 'razorpay_connect_charges_enabled', $env ) );
					OsSettingsHelper::remove_setting_by_name( OsSettingsHelper::append_payment_env_key( 'razorpay_connect_account_id', $env ) );
					OsSettingsHelper::remove_setting_by_name( OsSettingsHelper::append_payment_env_key( 'razorpay_connect_public_token', $env ) );
				}
				if ( ! empty( $response['data']['active_site_urls'] ) ) {
					OsSettingsHelper::save_setting_by_name( OsSettingsHelper::append_payment_env_key( 'razorpay_connect_duplicate_token_activations', $env ), $response['data']['active_site_urls'] );
				} else {
					OsSettingsHelper::remove_setting_by_name( OsSettingsHelper::append_payment_env_key( 'razorpay_connect_duplicate_token_activations', $env ) );
				}
				if ( ! empty( $response['data']['error'] ) ) {
					OsDebugHelper::log( 'Error checking Razorpay connect status', 'razorpay_connect_error', [ 'error_message' => $response['data']['error'] ] );
				}
			} catch ( Exception $e ) {
				OsDebugHelper::log( 'Error getting Razorpay connection status', 'razorpay_connect_error', [ 'error_message' => $e->getMessage() ] );
				$this->send_json(
					[
						'status'  => LATEPOINT_STATUS_ERROR,
						'message' => $e->getMessage(),
					]
				);
			}
			$this->send_json(
				[
					'status'  => LATEPOINT_STATUS_SUCCESS,
					'message' => OsRazorpayConnectHelper::get_connection_buttons_and_status( $env ),
				]
			);
		}

		public function heartbeat() {
			$payload = @file_get_contents( 'php://input' );
			$data    = json_decode( $payload, true );

			if ( empty( $data['wp_latepoint_server_token'] ) ) {
				$this->send_json(
					[
						'status'  => LATEPOINT_STATUS_ERROR,
						'message' => 'Token is missing',
					],
					404
				);
			}
			if ( $data['wp_latepoint_server_token'] !== OsRazorpayConnectHelper::get_server_token() ) {
				$this->send_json(
					[
						'status'  => LATEPOINT_STATUS_ERROR,
						'message' => 'Invalid Token',
					],
					404
				);
			}
			$this->send_json(
				[
					'status'  => LATEPOINT_STATUS_SUCCESS,
					'message' => 'Heartbeat detected',
				],
				200
			);
		}
	}

endif;
