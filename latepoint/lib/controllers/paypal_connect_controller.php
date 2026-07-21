<?php
/*
 * Copyright (c) 2024 LatePoint LLC. All rights reserved.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}


if ( ! class_exists( 'OsPaypalConnectController' ) ) :


	class OsPaypalConnectController extends OsController {


		function __construct() {
			parent::__construct();

			$this->action_access['public']   = array_merge( $this->action_access['public'], [ 'webhook', 'create_order_for_transaction', 'create_order', 'capture_order' ] );
			$this->action_access['customer'] = array_merge( $this->action_access['customer'], [] );
			$this->views_folder              = LATEPOINT_VIEWS_ABSPATH . 'paypal_connect/';
		}

		public function create_order_for_transaction() {
			if ( ! filter_var( $this->params['invoice_id'], FILTER_VALIDATE_INT ) ) {
				exit();
			}
			try {

				$invoice = new OsInvoiceModel( $this->params['invoice_id'] );

				$transaction_intent = OsTransactionIntentHelper::create_or_update_transaction_intent( $invoice, $this->params );

				if ( OsPaypalConnectHelper::get_connect_merchant_id() ) {
					$order_data      = OsPaypalConnectHelper::create_order_for_transaction_intent( $transaction_intent );
					$paypal_order_id = $order_data['order_id'];
				} else {
					throw new Exception( __( 'PayPal connect merchant ID not set', 'latepoint' ) );
				}

				$transaction_intent->set_payment_data_value( 'token', $paypal_order_id, false );
				if ( ! $transaction_intent->save() ) {
					throw new Exception( __( 'Unable to save transaction intent', 'latepoint' ) );
				}

				if ( $this->get_return_format() == 'json' ) {
					$this->send_json(
						[
							'status'                 => LATEPOINT_STATUS_SUCCESS,
							'continue_transaction_intent_url' => OsTransactionIntentHelper::generate_continue_intent_url( $transaction_intent->intent_key ),
							'paypal_order_id'        => $paypal_order_id,
							'transaction_intent_key' => $transaction_intent->intent_key,
						]
					);
				}
			} catch ( Exception $e ) {
				if ( $this->get_return_format() == 'json' ) {
					$this->send_json(
						array(
							'status'  => LATEPOINT_STATUS_ERROR,
							'message' => $e->getMessage(),
						)
					);
				}
			}
		}

		public function create_order() {
			try {
				OsStepsHelper::set_required_objects( $this->params );

				$booking_form_page_url = $this->params['booking_form_page_url'] ?? OsUtilHelper::get_referrer();
				$order_intent          = OsOrderIntentHelper::create_or_update_order_intent( OsStepsHelper::$cart_object, OsStepsHelper::$restrictions, OsStepsHelper::$presets, $booking_form_page_url, OsStepsHelper::get_customer_object_id() );

				if ( ! $order_intent->is_bookable() ) {
					throw new Exception( empty( $order_intent->get_error_messages() ) ? __( 'Booking slot is not available anymore.', 'latepoint' ) : implode( ', ', $order_intent->get_error_messages() ) );
				}

				if ( OsPaypalConnectHelper::get_connect_merchant_id() ) {
					$payment_method  = OsStepsHelper::$cart_object->payment_method ?? 'paypal_buttons';
					$order_data      = OsPaypalConnectHelper::create_order_for_order_intent( $order_intent, $payment_method );
					$paypal_order_id = $order_data['order_id'];
				} else {
					throw new Exception( __( 'PayPal connect merchant ID not set', 'latepoint' ) );
				}

				// update cart with paypal order id
				OsStepsHelper::$cart_object->payment_token = $paypal_order_id;

				// cart_item_data might be changed after filters run, make sure to get the latest version
				$cart_items_data = json_decode( $order_intent->cart_items_data, true );
				$payment_data    = json_decode( $order_intent->payment_data, true );

				$payment_data['token'] = $paypal_order_id;
				$order_intent->update_attributes(
					[
						'cart_items_data' => wp_json_encode( $cart_items_data ),
						'payment_data'    => wp_json_encode( $payment_data ),
					]
				);
				if ( $this->get_return_format() == 'json' ) {
					$this->send_json(
						[
							'status'                    => LATEPOINT_STATUS_SUCCESS,
							'continue_order_intent_url' => OsOrderIntentHelper::generate_continue_intent_url( $order_intent->intent_key ),
							'paypal_order_id'           => $paypal_order_id,
							'order_intent_key'          => $order_intent->intent_key,
						]
					);
				}
			} catch ( Exception $e ) {
				if ( $this->get_return_format() == 'json' ) {
					$this->send_json(
						array(
							'status'  => LATEPOINT_STATUS_ERROR,
							'message' => $e->getMessage(),
						)
					);
				}
			}
		}

		public function capture_order() {
			try {
				$paypal_order_id = sanitize_text_field( $this->params['paypal_order_id'] ?? '' );
				if ( empty( $paypal_order_id ) ) {
					throw new Exception( __( 'PayPal order ID is required', 'latepoint' ) );
				}

				$capture_data = OsPaypalConnectHelper::capture_order( $paypal_order_id );

				if ( $this->get_return_format() == 'json' ) {
					$this->send_json(
						[
							'status'              => LATEPOINT_STATUS_SUCCESS,
							'capture_id'          => $capture_data['capture_id'] ?? '',
							'order_id'            => $capture_data['order_id'] ?? '',
							'payment_source_type' => $capture_data['payment_source_type'] ?? 'paypal',
						]
					);
				}
			} catch ( Exception $e ) {
				if ( $this->get_return_format() == 'json' ) {
					$this->send_json(
						array(
							'status'  => LATEPOINT_STATUS_ERROR,
							'message' => $e->getMessage(),
						)
					);
				}
			}
		}

		public function webhook() {
			$payload = @file_get_contents( 'php://input' );
			$data    = json_decode( $payload, true );
			if ( empty( $data['server_token'] ) || $data['server_token'] != OsPaypalConnectHelper::get_server_token() || $data['paypal_merchant_id'] != OsPaypalConnectHelper::get_connect_merchant_id() ) {
				http_response_code( 400 );
				echo 'Validation issue with webhook';
				exit();
			}
			$event = $data['event'];
			// Handle the event
			switch ( $event['type'] ) {
				case 'PAYMENT.CAPTURE.COMPLETED':
					if ( ! empty( $event['data']['order_intent_key'] ) ) {
						$order_intent = OsOrderIntentHelper::get_order_intent_by_intent_key( $event['data']['order_intent_key'] );
						if ( $order_intent->is_new_record() ) {
							OsDebugHelper::log( 'Error processing paypal connect webhook: Order intent not found for key' );
							http_response_code( 400 );
							exit();
						}

						$stored_order_id = $order_intent->get_other_data_value( 'paypal_order_id' );
						if ( $stored_order_id !== ( $event['data']['paypal_order_id'] ?? '' ) ) {
							OsDebugHelper::log( 'PayPal order ID mismatch in webhook for order intent ' . $order_intent->id, 'paypal_connect_webhook' );
							http_response_code( 400 );
							exit();
						}

						if ( $order_intent->convert_to_order() ) {
							http_response_code( 200 );
						} else {
							http_response_code( 400 );
							OsDebugHelper::log( 'Error converting order intent', 'paypal_connect_webhook', $order_intent->get_error_messages() );
						}
					}
					if ( ! empty( $event['data']['transaction_intent_key'] ) ) {
						$transaction_intent = OsTransactionIntentHelper::get_transaction_intent_by_intent_key( $event['data']['transaction_intent_key'] );
						if ( $transaction_intent->is_new_record() ) {
							OsDebugHelper::log( 'Error processing paypal connect webhook: Transaction intent not found for key' );
							http_response_code( 400 );
							exit();
						}

						$stored_order_id = $transaction_intent->get_payment_data_value( 'paypal_order_id' );
						if ( $stored_order_id !== ( $event['data']['paypal_order_id'] ?? '' ) ) {
							OsDebugHelper::log( 'PayPal order ID mismatch in webhook for transaction intent ' . $transaction_intent->id, 'paypal_connect_webhook' );
							http_response_code( 400 );
							exit();
						}

						if ( $transaction_intent->convert_to_transaction() ) {
							http_response_code( 200 );
						} else {
							http_response_code( 400 );
							OsDebugHelper::log( 'Error converting transaction intent' );
						}
					}
					break;
			}
			exit();
		}

		private function get_env_from_params(): string {
			return ( ! empty(
				$this->params['env'] && in_array(
					$this->params['env'],
					[
						LATEPOINT_PAYMENTS_ENV_LIVE,
						LATEPOINT_PAYMENTS_ENV_DEV,
					]
				)
			) ) ? $this->params['env'] : OsSettingsHelper::get_payments_environment();
		}

		public function start_connect_process() {
			$env = $this->get_env_from_params();
			OsSettingsHelper::save_setting_by_name( OsSettingsHelper::append_payment_env_key( 'enable_payment_processor_paypal_connect', $env ), LATEPOINT_VALUE_ON );
			$url = OsPaypalConnectHelper::get_connect_url( $env );
			$this->send_json(
				array(
					'status'  => LATEPOINT_STATUS_SUCCESS,
					'url'     => $url,
					'message' => __( 'Redirecting to PayPal', 'latepoint' ),
				)
			);
		}

		public function disconnect_connect_account() {
			$env = $this->get_env_from_params();
			try {
				$path     = 'server-tokens/' . OsPaypalConnectHelper::get_server_token( $env ) . '/disconnect/';
				$response = OsPaypalConnectHelper::do_account_request( $path, $env, '', 'DELETE' );
				if ( $response['status']['code'] == 200 ) {
					OsSettingsHelper::remove_setting_by_name( OsSettingsHelper::append_payment_env_key( 'paypal_connect_payments_enabled' ) );
					OsSettingsHelper::remove_setting_by_name( OsSettingsHelper::append_payment_env_key( 'paypal_connect_merchant_id' ) );
					OsPaypalConnectHelper::reset_server_token( $env );
				} else {
					OsDebugHelper::log( 'PayPal Connect Error', 'paypal_connect_disconnect_error', $response );
				}
			} catch ( Exception $e ) {
				OsDebugHelper::log( 'Error getting status of a paypal connection', 'paypal_ppcp', [ 'error_message' => $e->getMessage() ] );
				$this->send_json(
					array(
						'status'  => LATEPOINT_STATUS_ERROR,
						'message' => $e->getMessage(),
					)
				);
			}
			$this->send_json(
				array(
					'status'  => LATEPOINT_STATUS_SUCCESS,
					'message' => OsPaypalConnectHelper::get_connection_buttons_and_status( $env ),
				)
			);
		}


		public function check_connect_status() {
			$env = $this->get_env_from_params();
			try {
				$response = OsPaypalConnectHelper::do_request( 'server-tokens/' . OsPaypalConnectHelper::get_server_token( $env ) . '/status/', '', 'GET', [], [], $env );
				$data     = $response['data'] ?? [];

				if ( $env == 'live' ) {
					if ( empty( $data['transaction_fee_info'] ) ) {
						OsSettingsHelper::save_setting_by_name( 'paypal_connect_transaction_fee_info', '0' );
					} else {
						OsSettingsHelper::save_setting_by_name( 'paypal_connect_transaction_fee_info', $data['transaction_fee_info'] );
					}
				}
				if ( ! empty( $data ) && ! empty( $data['merchant_id'] ) ) {

					// Save granular status flags so the admin UI and payment gate can use them.
					$merchant_id              = sanitize_text_field( $data['merchant_id'] );
					$payments_receivable      = ! empty( $data['payments_receivable'] );
					$primary_email_confirmed  = ! empty( $data['primary_email_confirmed'] );
					$oauth_integrations_valid = ! empty( $data['oauth_integrations_valid'] );
					$acdc_vetting_status      = sanitize_text_field( $data['acdc_vetting_status'] ?? '' );
					$acdc_capability_active   = ! empty( $data['acdc_capability_active'] );

					OsSettingsHelper::save_setting_by_name( OsSettingsHelper::append_payment_env_key( 'paypal_connect_merchant_id', $env ), $merchant_id );
					OsSettingsHelper::save_setting_by_name( OsSettingsHelper::append_payment_env_key( 'paypal_connect_payments_receivable', $env ), $payments_receivable ? LATEPOINT_VALUE_ON : '' );
					OsSettingsHelper::save_setting_by_name( OsSettingsHelper::append_payment_env_key( 'paypal_connect_primary_email_confirmed', $env ), $primary_email_confirmed ? LATEPOINT_VALUE_ON : '' );
					OsSettingsHelper::save_setting_by_name( OsSettingsHelper::append_payment_env_key( 'paypal_connect_oauth_integrations_valid', $env ), $oauth_integrations_valid ? LATEPOINT_VALUE_ON : '' );
					OsSettingsHelper::save_setting_by_name( OsSettingsHelper::append_payment_env_key( 'paypal_connect_acdc_vetting_status', $env ), $acdc_vetting_status );
					OsSettingsHelper::save_setting_by_name( OsSettingsHelper::append_payment_env_key( 'paypal_connect_acdc_capability_active', $env ), $acdc_capability_active ? LATEPOINT_VALUE_ON : '' );

					// Payments are enabled when PayPal confirms the merchant can transact.
					// oauth_integrations_valid is advisory (shown in admin UI) but does not block payments.
					if ( $payments_receivable && $primary_email_confirmed ) {
						OsSettingsHelper::save_setting_by_name( OsSettingsHelper::append_payment_env_key( 'paypal_connect_payments_enabled', $env ), LATEPOINT_VALUE_ON );
					} else {
						OsSettingsHelper::remove_setting_by_name( OsSettingsHelper::append_payment_env_key( 'paypal_connect_payments_enabled', $env ) );
					}
				} else {
					OsSettingsHelper::remove_setting_by_name( OsSettingsHelper::append_payment_env_key( 'paypal_connect_payments_enabled', $env ) );
					OsSettingsHelper::remove_setting_by_name( OsSettingsHelper::append_payment_env_key( 'paypal_connect_merchant_id', $env ) );
					OsSettingsHelper::remove_setting_by_name( OsSettingsHelper::append_payment_env_key( 'paypal_connect_payments_receivable', $env ) );
					OsSettingsHelper::remove_setting_by_name( OsSettingsHelper::append_payment_env_key( 'paypal_connect_primary_email_confirmed', $env ) );
					OsSettingsHelper::remove_setting_by_name( OsSettingsHelper::append_payment_env_key( 'paypal_connect_oauth_integrations_valid', $env ) );
					OsSettingsHelper::remove_setting_by_name( OsSettingsHelper::append_payment_env_key( 'paypal_connect_acdc_vetting_status', $env ) );
					OsSettingsHelper::remove_setting_by_name( OsSettingsHelper::append_payment_env_key( 'paypal_connect_acdc_capability_active', $env ) );
				}
				if ( ! empty( $data['active_site_urls'] ) ) {
					OsSettingsHelper::save_setting_by_name( OsSettingsHelper::append_payment_env_key( 'paypal_connect_duplicate_token_activations', $env ), $data['active_site_urls'] );
				} else {
					OsSettingsHelper::remove_setting_by_name( OsSettingsHelper::append_payment_env_key( 'paypal_connect_duplicate_token_activations', $env ) );
				}
				if ( ! empty( $data['error'] ) ) {
					OsDebugHelper::log( 'Error checking status of server token', 'paypal_connect_error', [ 'error_message' => $data['error'] ] );
				}
			} catch ( Exception $e ) {
				OsDebugHelper::log( 'Error getting status of a paypal connection', 'paypal_connect_error', [ 'error_message' => $e->getMessage() ] );
				$this->send_json(
					array(
						'status'  => LATEPOINT_STATUS_ERROR,
						'message' => $e->getMessage(),
					)
				);
			}
			$this->send_json(
				array(
					'status'  => LATEPOINT_STATUS_SUCCESS,
					'message' => OsPaypalConnectHelper::get_connection_buttons_and_status( $env ),
				)
			);
		}


		public function heartbeat() {
			$payload = @file_get_contents( 'php://input' );
			$data    = json_decode( $payload, true );

			if ( empty( $data['wp_latepoint_server_token'] ) ) {
				$this->send_json(
					array(
						'status'  => LATEPOINT_STATUS_ERROR,
						'message' => 'Token is missing',
					),
					404
				);
			}
			if ( $data['wp_latepoint_server_token'] != OsPaypalConnectHelper::get_server_token() ) {
				$this->send_json(
					array(
						'status'  => LATEPOINT_STATUS_ERROR,
						'message' => 'Invalid Token',
					),
					404
				);
			}

			$this->send_json(
				array(
					'status'  => LATEPOINT_STATUS_SUCCESS,
					'message' => 'Heartbeat detected',
				),
				200
			);
		}
	}


endif;
