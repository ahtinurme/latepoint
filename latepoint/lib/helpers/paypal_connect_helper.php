<?php

class OsPaypalConnectHelper {
	public static $default_currency_iso_code = 'usd';
	public static $processor_code            = 'paypal_ppcp';


	public static function add_all_payment_methods_to_payment_times( array $payment_times ): array {
		$payment_methods = self::get_supported_payment_methods();
		foreach ( $payment_methods as $payment_method_code => $payment_method_info ) {
			$payment_times[ LATEPOINT_PAYMENT_TIME_NOW ][ $payment_method_code ][ self::$processor_code ] = $payment_method_info;
		}
		return $payment_times;
	}

	public static function add_enabled_payment_methods_to_payment_times( array $payment_times ): array {
		if ( OsPaymentsHelper::is_payment_processor_enabled( self::$processor_code ) ) {
			$payment_times = self::add_all_payment_methods_to_payment_times( $payment_times );
		}
		return $payment_times;
	}

	public static function is_supported_payment_method( $method ): bool {
		return in_array( $method, array( 'paypal_buttons', 'paypal_card' ), true );
	}

	public static function process_refund( $transaction_refund, OsTransactionModel $transaction, $custom_amount = null ) {
		if ( $transaction->processor != self::$processor_code ) {
			return $transaction_refund;
		}

		if ( ! $transaction->can_refund() ) {
			throw new Exception( 'Invalid Transaction' );
		}

		$refund_data = [
			'capture_id' => $transaction->token,
			'currency'   => strtoupper( self::get_currency_iso_code() ),
		];
		if ( $custom_amount ) {
			$refund_data['custom_amount'] = self::convert_amount_to_specs( $custom_amount );
		}

		$response = self::do_account_request( 'refunds', OsSettingsHelper::get_payments_environment(), '', 'POST', $refund_data );

		if ( empty( $response['data'] ) ) {
			throw new Exception( __( 'Error Refunding', 'latepoint' ) );
		}

		$transaction_refund                 = new OsTransactionRefundModel();
		$transaction_refund->transaction_id = $transaction->id;
		$transaction_refund->amount         = $response['data']['amount'];
		$transaction_refund->token          = $response['data']['id'];
		if ( $transaction_refund->save() ) {
			do_action( 'latepoint_transaction_refund_created', $transaction_refund );
			return $transaction_refund;
		} else {
			throw new Exception( implode( ', ', $transaction_refund->get_error_messages() ) );
		}
	}

	public static function convert_transaction_intent_charge_amount_to_specs( $amount, OsTransactionIntentModel $transaction_intent ) {
		if ( OsPaymentsHelper::should_processor_handle_payment_for_transaction_intent( self::$processor_code, $transaction_intent ) ) {
			$amount = self::convert_amount_to_specs( $amount );
		}
		return $amount;
	}

	public static function process_payment_for_transaction_intent( $result, OsTransactionIntentModel $transaction_intent ) {
		if ( ! OsPaymentsHelper::should_processor_handle_payment_for_transaction_intent( self::$processor_code, $transaction_intent ) ) {
			return $result;
		}

		if ( self::is_supported_payment_method( $transaction_intent->get_payment_data_value( 'method' ) ) ) {
			if ( $transaction_intent->get_payment_data_value( 'token' ) ) {
				$capture = self::retrieve_capture( $transaction_intent->get_payment_data_value( 'token' ) );
				if ( ! empty( $capture ) && ( $capture['status'] ?? '' ) === 'COMPLETED' ) {
					$expected_order_id = $transaction_intent->get_payment_data_value( 'paypal_order_id' );
					if ( self::validate_capture_amount( $capture, $transaction_intent->charge_amount )
						&& self::validate_capture_binding( $capture, 'TRANS_INTENT_', $transaction_intent->intent_key )
						&& self::validate_capture_order_binding( $capture, $expected_order_id ) ) {
						$result['status']    = LATEPOINT_STATUS_SUCCESS;
						$result['processor'] = self::$processor_code;
						$result['charge_id'] = $capture['id'];
						$result['kind']      = LATEPOINT_TRANSACTION_KIND_CAPTURE;
					} else {
						$result['status']  = LATEPOINT_STATUS_ERROR;
						$result['message'] = __( 'Payment verification failed', 'latepoint' );
						OsDebugHelper::log( 'PayPal capture verification failed (amount/currency/binding) for transaction intent ' . $transaction_intent->id, 'paypal_connect_error' );
						$transaction_intent->add_error( 'send_to_step', $result['message'], 'payment' );
					}
				} else {
					$result['status']  = LATEPOINT_STATUS_ERROR;
					$result['message'] = __( 'Payment Error', 'latepoint' );
					$transaction_intent->add_error( 'send_to_step', $result['message'], 'payment' );
				}
			} else {
				$result['status']  = LATEPOINT_STATUS_ERROR;
				$result['message'] = __( 'Payment Error', 'latepoint' );
				$transaction_intent->add_error( 'send_to_step', $result['message'], 'payment' );
			}
		}
		return $result;
	}

	public static function process_payment( $result, OsOrderIntentModel $order_intent ) {
		if ( ! OsPaymentsHelper::should_processor_handle_payment_for_order_intent( self::$processor_code, $order_intent ) ) {
			return $result;
		}

		if ( self::is_supported_payment_method( $order_intent->get_payment_data_value( 'method' ) ) ) {
			if ( $order_intent->get_payment_data_value( 'token' ) ) {
				$capture = self::retrieve_capture( $order_intent->get_payment_data_value( 'token' ) );
				if ( ! empty( $capture ) && ( $capture['status'] ?? '' ) === 'COMPLETED' ) {
					$expected_order_id = $order_intent->get_other_data_value( 'paypal_order_id' );
					if ( self::validate_capture_amount( $capture, $order_intent->charge_amount )
						&& self::validate_capture_binding( $capture, 'ORDER_INTENT_', $order_intent->intent_key )
						&& self::validate_capture_order_binding( $capture, $expected_order_id ) ) {
						$result['status']    = LATEPOINT_STATUS_SUCCESS;
						$result['processor'] = self::$processor_code;
						$result['charge_id'] = $capture['id'];
						$result['kind']      = LATEPOINT_TRANSACTION_KIND_CAPTURE;
					} else {
						$result['status']  = LATEPOINT_STATUS_ERROR;
						$result['message'] = __( 'Payment verification failed', 'latepoint' );
						OsDebugHelper::log( 'PayPal capture verification failed (amount/currency/binding) for order intent ' . $order_intent->id, 'paypal_connect_error' );
						$order_intent->add_error( 'payment_error', $result['message'] );
						$order_intent->add_error( 'send_to_step', $result['message'], 'payment' );
					}
				} else {
					$result['status']  = LATEPOINT_STATUS_ERROR;
					$result['message'] = __( 'Payment Error', 'latepoint' );
					$order_intent->add_error( 'payment_error', $result['message'] );
					$order_intent->add_error( 'send_to_step', $result['message'], 'payment' );
				}
			} else {
				$result['status']  = LATEPOINT_STATUS_ERROR;
				$result['message'] = __( 'Payment Error', 'latepoint' );
				$order_intent->add_error( 'payment_error', $result['message'] );
				$order_intent->add_error( 'send_to_step', $result['message'], 'payment' );
			}
		}

		return $result;
	}

	public static function convert_charge_amount_to_requirements( $charge_amount, OsCartModel $cart ) {
		if ( OsPaymentsHelper::should_processor_handle_payment_for_cart( self::$processor_code, $cart ) ) {
			$charge_amount = self::convert_amount_to_specs( $charge_amount );
		}
		return $charge_amount;
	}

	/**
	 * PayPal uses decimal strings ("50.00"), not integer cents like Stripe
	 */
	public static function convert_amount_to_specs( $charge_amount ) {
		$iso_code = self::get_currency_iso_code();
		if ( in_array( strtolower( $iso_code ), self::zero_decimal_currencies_list() ) ) {
			$charge_amount = (string) round( $charge_amount );
		} else {
			$number_of_decimals = OsSettingsHelper::get_settings_value( 'number_of_decimals', '2' );
			$charge_amount      = number_format( (float) $charge_amount, $number_of_decimals, '.', '' );
		}
		return $charge_amount;
	}

	public static function convert_amount_back_from_specs_to_db_format( $charge_amount ) {
		return OsMoneyHelper::pad_to_db_format( $charge_amount );
	}

	public static function retrieve_capture( string $capture_id ): array {
		$response = self::do_account_request( 'captures/' . $capture_id, OsSettingsHelper::get_payments_environment() );
		return $response['data'] ?? [];
	}

	public static function validate_capture_amount( array $capture, string $expected_charge_amount ): bool {
		$expected = self::convert_amount_to_specs( $expected_charge_amount );
		$actual   = $capture['amount']['value'] ?? '';
		return abs( (float) $expected - (float) $actual ) < 0.01;
	}

	public static function validate_capture_currency( array $capture ): bool {
		$expected = strtoupper( self::get_currency_iso_code() );
		$actual   = strtoupper( $capture['amount']['currency_code'] ?? '' );
		return ! empty( $actual ) && $actual === $expected;
	}

	public static function validate_capture_binding( array $capture, string $prefix, string $expected_intent_key ): bool {
		if ( empty( $expected_intent_key ) ) {
			return false;
		}
		$custom_id         = $capture['custom_id'] ?? '';
		$actual_intent_key = str_starts_with( $custom_id, $prefix ) ? substr( $custom_id, strlen( $prefix ) ) : '';
		return ! empty( $actual_intent_key ) && hash_equals( $expected_intent_key, $actual_intent_key );
	}

	public static function validate_capture_order_binding( array $capture, string $expected_order_id ): bool {
		if ( empty( $expected_order_id ) ) {
			return false;
		}
		$actual_order_id = $capture['supplementary_data']['related_ids']['order_id'] ?? '';
		return ! empty( $actual_order_id ) && $actual_order_id === $expected_order_id;
	}

	public static function output_order_payment_pay_contents( OsTransactionIntentModel $transaction_intent ) {
		if ( ! OsPaymentsHelper::should_processor_handle_payment_for_transaction_intent( self::$processor_code, $transaction_intent ) ) {
			return;
		}
		self::render_paypal_payment_view( $transaction_intent->specs_charge_amount );
	}

	public static function output_payment_step_contents( OsCartModel $cart ) {
		if ( ! OsPaymentsHelper::should_processor_handle_payment_for_cart( self::$processor_code, $cart ) ) {
			return;
		}
		self::render_paypal_payment_view( self::convert_amount_to_specs( $cart->get_total() ) );
	}

	private static function render_paypal_payment_view( string $charge_amount = '' ): void {
		echo '<div class="lp-payment-method-content" data-payment-method="paypal_buttons"'
			. ( $charge_amount !== '' ? ' data-charge-amount="' . esc_attr( $charge_amount ) . '"' : '' )
			. '>';
		echo '<div class="lp-payment-method-content-i">';
		echo '<div class="lp-paypal-connect-button-container">';
		echo '<paypal-button id="latepoint-paypal-button" type="pay" hidden></paypal-button>';
		echo '<venmo-button id="latepoint-venmo-button" type="pay" hidden></venmo-button>';
		echo '<paypal-pay-later-button id="latepoint-paylater-button" type="pay" hidden></paypal-pay-later-button>';
		echo '</div>';
		if ( self::get_acdc_eligible() ) {
			echo '<div class="lp-paypal-card-separator"><span>' . esc_html__( 'or pay with card', 'latepoint' ) . '</span></div>';

			echo '<div id="card-form" class="card_container lp-paypal-card-fields-container">';
				echo '<div id="card-number-field-container" class="lp-card-number-field"></div>';

				echo '<div class="lp-card-fields-row">';
				echo '<div id="card-expiry-field-container" class="lp-card-expiry-field"></div>';
				echo '<div id="card-cvv-field-container" class="lp-card-cvv-field"></div>';
				echo '</div>';

				echo '<button id="card-field-submit-button" type="button" class="lp-card-submit-btn latepoint-btn latepoint-btn-primary">'
					. esc_html__( 'Pay with Card', 'latepoint' )
					. '</button>';
			echo '</div>';

			// echo '<div class="lp-paypal-card-disclosure">';
			// echo '<img src="https://www.paypalobjects.com/paypal-ui/logos/svg/paypal-mark-color.svg" alt="PayPal" height="16" /> ';
			// echo esc_html__( 'Payments powered by PayPal', 'latepoint' );
			// echo '</div>';
		}
		echo '</div>';
		echo '</div>';
	}

	public static function get_acdc_eligible(): bool {
		return OsSettingsHelper::get_settings_value(
			OsSettingsHelper::append_payment_env_key( 'paypal_connect_acdc_vetting_status' ),
			''
		) === 'SUBSCRIBED';
	}

	private static function map_payment_method_for_api( string $method ): string {
		switch ( $method ) {
			case 'paypal_card':
				return 'paypal_card';
			default:
				return 'paypal_buttons';
		}
	}

	public static function get_supported_payment_methods(): array {
		return [
			'paypal_buttons' => [
				'name'      => __( 'PayPal', 'latepoint' ),
				'label'     => __( 'PayPal', 'latepoint' ),
				'image_url' => LATEPOINT_IMAGES_URL . 'payment_paypal.png',
			],
		];
	}

	public static function register_payment_processor( array $payment_processors ): array {
		$payment_processors[ self::$processor_code ] = [
			'code'       => self::$processor_code,
			'name'       => __( 'PayPal Checkout', 'latepoint' ),
			'front_name' => __( 'PayPal Checkout', 'latepoint' ),
			'image_url'  => LATEPOINT_IMAGES_URL . 'processor-paypal.png',
		];
		return $payment_processors;
	}

	public static function transaction_is_refund_available( $result, OsTransactionModel $transaction_model ) {
		if ( $transaction_model->processor == self::$processor_code ) {
			$result = true;
		}
		return $result;
	}

	/**
	 * Outputs a payment source badge on the booking confirmation step.
	 * The actual method (PayPal / Venmo / Pay Later) is read from sessionStorage
	 * in the browser — set there by the JS capture handler.
	 *
	 * @param OsOrderModel $order
	 */
	public static function output_confirmation_payment_source( $order ) {
		// Only show for PayPal-processed orders.
		$transactions = $order->get_transactions();
		$is_paypal    = false;
		foreach ( $transactions as $transaction ) {
			if ( $transaction->processor === self::$processor_code ) {
				$is_paypal = true;
				break;
			}
		}
		if ( ! $is_paypal ) {
			return;
		}
		$labels      = [
			'paypal'   => __( 'PayPal', 'latepoint' ),
			'venmo'    => __( 'Venmo', 'latepoint' ),
			'paylater' => __( 'Pay Later', 'latepoint' ),
			'card'     => __( 'Credit/Debit Card', 'latepoint' ),
		];
		$labels_json = wp_json_encode( $labels );
		?>
		<div class="lp-paypal-payment-source" style="display:none;">
			<span class="lp-paypal-payment-source-label"></span>
		</div>
		<script>
		(function() {
			var source = sessionStorage.getItem('lp_paypal_payment_source') || 'paypal';
			var labels = <?php echo $labels_json; ?>;
			var label  = labels[source] || labels['paypal'];
			var wrapper = document.querySelector('.lp-paypal-payment-source');
			if (wrapper) {
				wrapper.querySelector('.lp-paypal-payment-source-label').textContent = label;
				wrapper.style.display = '';
				sessionStorage.removeItem('lp_paypal_payment_source');
			}
		})();
		</script>
		<?php
	}

	public static function add_settings_fields( $processor_code ) {
		if ( $processor_code != self::$processor_code ) {
			return false;
		} ?>
		<div class="sub-section-row">
			<div class="sub-section-label">
				<h3><?php esc_html_e( 'Connect (Live)', 'latepoint' ); ?></h3>
			</div>
			<div class="sub-section-content">
				<div data-env="<?php echo esc_attr( LATEPOINT_PAYMENTS_ENV_LIVE ); ?>"
					 class="payment-processor-connect-status-wrapper paypal-connect-status-wrapper"
					 data-route-name="<?php echo esc_attr( OsRouterHelper::build_route_name( 'paypal_connect', 'check_connect_status' ) ); ?>">
					<div class="os-loading-spinner"></div>
				</div>
			</div>
		</div>
		<div class="sub-section-row">
			<div class="sub-section-label">
				<h3><?php esc_html_e( 'Connect (Dev)', 'latepoint' ); ?></h3>
			</div>
			<div class="sub-section-content">
				<div data-env="<?php echo esc_attr( LATEPOINT_PAYMENTS_ENV_DEV ); ?>"
					 class="payment-processor-connect-status-wrapper paypal-connect-status-wrapper"
					 data-route-name="<?php echo esc_attr( OsRouterHelper::build_route_name( 'paypal_connect', 'check_connect_status' ) ); ?>">
					<div class="os-loading-spinner"></div>
				</div>
			</div>
		</div>
		<div class="sub-section-row">
			<div class="sub-section-label">
				<h3><?php esc_html_e( 'Other Settings', 'latepoint' ); ?></h3>
			</div>
			<div class="sub-section-content">
				<?php
				$selected_paypal_country_code      = OsSettingsHelper::get_settings_value( 'paypal_connect_country_code', 'US' );
				$selected_paypal_currency_iso_code = OsSettingsHelper::get_settings_value( 'paypal_connect_currency_iso_code', 'usd' ); ?>
				<div class="os-row os-mb-2">
					<div class="os-col-6">
						<?php echo OsFormHelper::select_field( 'settings[paypal_connect_country_code]', __( 'Country', 'latepoint' ), self::load_countries_list(), $selected_paypal_country_code ); ?>
					</div>
					<div class="os-col-6">
						<?php echo OsFormHelper::select_field( 'settings[paypal_connect_currency_iso_code]', __( 'Currency Code', 'latepoint' ), self::load_all_currencies_list(), $selected_paypal_currency_iso_code ); ?>
					</div>
				</div>
				<div class="os-row os-mb-2">
					<div class="os-col-12">
						<?php echo OsFormHelper::toggler_field(
							'settings[paypal_connect_disable_pay_later]',
							__( 'Disable Pay Later', 'latepoint' ),
							OsSettingsHelper::is_on( 'paypal_connect_disable_pay_later' ),
							false,
							false,
							[ 'sub_label' => __( 'Hide PayPal Pay Later option from the payment buttons.', 'latepoint' ) ]
						); ?>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	public static function get_currency_iso_code() {
		return OsSettingsHelper::get_settings_value( 'paypal_connect_currency_iso_code', self::$default_currency_iso_code );
	}

	public static function get_server_token( string $force_env = '' ): string {
		$key          = OsSettingsHelper::append_payment_env_key( 'server_token_for_paypal_connect', $force_env );
		$server_token = OsSettingsHelper::get_settings_value( $key, '' );
		if ( empty( $server_token ) ) {
			$server_token = OsUtilHelper::generate_uuid();
			OsSettingsHelper::save_setting_by_name( $key, $server_token );
		}
		return $server_token;
	}

	public static function reset_server_token( string $force_env = '' ): string {
		$key              = OsSettingsHelper::append_payment_env_key( 'server_token_for_paypal_connect', $force_env );
		$new_server_token = OsUtilHelper::generate_uuid();
		OsSettingsHelper::save_setting_by_name( $key, $new_server_token );
		return $new_server_token;
	}

	public static function get_connect_url( string $env = '' ) {
		$url  = LATEPOINT_PAYPAL_CONNECT_URL . '/wp/paypal-connection/' . $env . '/start/';
		$url .= self::get_server_token( $env ) . '/' . base64_encode( implode( '|||', [ get_bloginfo( 'name' ), get_site_icon_url(), OsUtilHelper::get_site_url() ] ) );
		return $url;
	}

	public static function get_connect_merchant_id( string $env = '' ): string {
		return OsSettingsHelper::get_settings_value( OsSettingsHelper::append_payment_env_key( 'paypal_connect_merchant_id', $env ), '' );
	}

	public static function get_partner_client_id(): string {
		$env       = OsSettingsHelper::get_payments_environment();
		$key       = OsSettingsHelper::append_payment_env_key( 'paypal_connect_partner_client_id', $env );
		$client_id = OsSettingsHelper::get_settings_value( $key, '' );
		if ( empty( $client_id ) ) {
			$response  = self::do_request( 'partner-client-id/' );
			$client_id = $response['data']['client_id'] ?? '';
			if ( ! empty( $client_id ) ) {
				OsSettingsHelper::save_setting_by_name( $key, $client_id );
			}
		}
		return $client_id;
	}

	public static function generate_client_token(): string {
		$token = get_transient( 'latepoint_paypal_client_token' );
		if ( ! empty( $token ) ) {
			return $token;
		}
		try {
			$response = self::do_account_request( 'client-token/', OsSettingsHelper::get_payments_environment(), '', 'POST' );
			$token    = $response['data']['client_token'] ?? '';
			if ( ! empty( $token ) ) {
				set_transient( 'latepoint_paypal_client_token', $token, 3000 );
			}
			return $token;
		} catch ( \Exception $e ) {
			OsDebugHelper::log( 'Error generating PayPal client token: ' . $e->getMessage(), 'paypal_connect_error' );
			return '';
		}
	}

	public static function do_account_request( string $path, string $env = '', string $connection_data = '', string $method = 'GET', array $vars = [], array $headers = [] ) {
		if ( empty( $env ) ) {
			$env = OsSettingsHelper::get_payments_environment();
		}
		$path = self::get_connect_merchant_id( $env ) . '/' . $path;
		try {
			return self::do_request( $path, $connection_data, $method, $vars, $headers );
		} catch ( \Exception $e ) {
			OsDebugHelper::log( 'Error processing request to PayPal: ' . $e->getMessage(), 'paypal_connect_error' );
			return [];
		}
	}

	public static function do_request( string $path, string $connection_data = '', string $method = 'GET', array $vars = [], array $headers = [], string $force_env = '' ) {
		$default_vars    = [];
		$default_headers = [
			'latepoint-version'     => LATEPOINT_VERSION,
			'latepoint-domain'      => OsUtilHelper::get_site_url(),
			'latepoint-license-key' => OsLicenseHelper::get_license_key(),
		];

		if ( ! empty( $connection_data ) ) {
			$default_headers['connection-data'] = $connection_data;
		}

		$args = array(
			'timeout'   => 15,
			'headers'   => array_merge( $default_headers, $headers ),
			'body'      => array_merge( $default_vars, $vars ),
			'sslverify' => false,
			'method'    => $method,
		);

		if ( ! empty( $force_env ) && in_array( $force_env, [ LATEPOINT_PAYMENTS_ENV_DEV, LATEPOINT_PAYMENTS_ENV_LIVE ] ) ) {
			$env = ( $force_env == LATEPOINT_PAYMENTS_ENV_DEV ) ? 'test' : 'live';
		} else {
			$env = ( OsSettingsHelper::is_env_payments_dev() ? 'test' : 'live' );
		}
		$url = LATEPOINT_PAYPAL_CONNECT_URL . "/api/wp/v1/paypal-connect/{$env}/{$path}";

		$response = wp_remote_request( $url, $args );

		if ( ! is_wp_error( $response ) ) {
			$data           = json_decode( wp_remote_retrieve_body( $response ), true );
			$data['status'] = $response['response'];
			return $data;
		} else {
			$error_message = $response->get_error_message();
			throw new Exception( $error_message );
		}
	}

	public static function create_order_for_order_intent( OsOrderIntentModel $order_intent, string $payment_method = 'paypal_buttons' ): array {
		$order_data = [
			'order_options' => [
				'amount'         => self::convert_amount_to_specs( $order_intent->charge_amount ),
				'currency'       => self::get_currency_iso_code(),
				'item_name'      => __( 'Appointment Booking', 'latepoint' ),
				'brand_name'     => get_bloginfo( 'name' ),
				'payment_method' => self::map_payment_method_for_api( $payment_method ),
				'metadata'       => [
					'order_intent_key' => $order_intent->intent_key,
				],
			],
		];

		$response = self::do_account_request( 'orders', OsSettingsHelper::get_payments_environment(), '', 'POST', $order_data );

		if ( ! empty( $response['error'] ) ) {
			throw new Exception( $response['error'] );
		}

		if ( ! empty( $response['data']['order_id'] ) ) {
			$order_intent->set_other_data_value( 'paypal_order_id', $response['data']['order_id'] );
		}

		return [
			'order_id' => $response['data']['order_id'] ?? '',
			'status'   => $response['data']['status'] ?? '',
		];
	}

	public static function create_order_for_transaction_intent( OsTransactionIntentModel $transaction_intent, string $payment_method = 'paypal_buttons' ): array {
		$order_data = [
			'order_options' => [
				'amount'         => $transaction_intent->specs_charge_amount,
				'currency'       => self::get_currency_iso_code(),
				'item_name'      => __( 'Appointment Booking', 'latepoint' ),
				'brand_name'     => get_bloginfo( 'name' ),
				'payment_method' => self::map_payment_method_for_api( $payment_method ),
				'metadata'       => [
					'transaction_intent_key' => $transaction_intent->intent_key,
				],
			],
		];

		$response = self::do_account_request( 'orders', OsSettingsHelper::get_payments_environment(), '', 'POST', $order_data );

		if ( ! empty( $response['error'] ) ) {
			throw new Exception( $response['error'] );
		}

		if ( ! empty( $response['data']['order_id'] ) ) {
			$transaction_intent->set_payment_data_value( 'paypal_order_id', $response['data']['order_id'] );
		}

		return [
			'order_id' => $response['data']['order_id'] ?? '',
			'status'   => $response['data']['status'] ?? '',
		];
	}

	public static function capture_order( string $paypal_order_id ): array {
		$response = self::do_account_request( 'orders/' . $paypal_order_id . '/capture', OsSettingsHelper::get_payments_environment(), '', 'POST' );

		if ( ! empty( $response['error'] ) ) {
			throw new Exception( $response['error'] );
		}

		return [
			'order_id'            => $response['data']['order_id'] ?? '',
			'capture_id'          => $response['data']['capture_id'] ?? '',
			'status'              => $response['data']['status'] ?? '',
			'payment_source_type' => $response['data']['payment_source_type'] ?? 'paypal',
		];
	}

	public static function get_connection_buttons_and_status( string $env = '' ) {
		$paypal_connect_merchant_id  = OsSettingsHelper::get_settings_value( OsSettingsHelper::append_payment_env_key( 'paypal_connect_merchant_id', $env ), false );
		$html                        = '';
		$duplicate_token_activations = OsSettingsHelper::get_settings_value( OsSettingsHelper::append_payment_env_key( 'paypal_connect_duplicate_token_activations', $env ), '' );
		if ( ! empty( $duplicate_token_activations ) ) {
			$html .= '<div class="latepoint-fix-records-warning"><i class="latepoint-icon latepoint-icon-info"></i><div>';
			$html .= '<div>' . __( 'The following websites are using the same server token. This can happen if a site was cloned from one server to another. To fix this, disconnect each site and reconnect it.', 'latepoint' ) . '</div>';
			$html .= '<div class="latepoint-fix-records-values">' . esc_html( $duplicate_token_activations ) . '</div>';
			$html .= '</div></div>';
		}
		$html .= '<div class="payment-processor-connect-status-inner">';
		if ( $paypal_connect_merchant_id ) {
			$payments_enabled        = OsSettingsHelper::is_on( OsSettingsHelper::append_payment_env_key( 'paypal_connect_payments_enabled', $env ) );
			$payments_receivable     = OsSettingsHelper::is_on( OsSettingsHelper::append_payment_env_key( 'paypal_connect_payments_receivable', $env ) );
			$primary_email_confirmed = OsSettingsHelper::is_on( OsSettingsHelper::append_payment_env_key( 'paypal_connect_primary_email_confirmed', $env ) );
			$oauth_valid             = OsSettingsHelper::is_on( OsSettingsHelper::append_payment_env_key( 'paypal_connect_oauth_integrations_valid', $env ) );
			$acdc_vetting_status     = OsSettingsHelper::get_settings_value( OsSettingsHelper::append_payment_env_key( 'paypal_connect_acdc_vetting_status', $env ), '' );

			$disconnect_link = '<a class="payment-processor-disconnect-link" href="#"
									data-os-pass-response="yes"
									data-os-pass-this="yes"
	                data-os-before-after="none"
	                data-os-after-call="latepointPaypalConnectAdmin.reload_connect_status_wrapper"
	                data-os-params="' . OsUtilHelper::build_os_params( [ 'env' => $env ] ) . '"
									data-os-action="' . OsRouterHelper::build_route_name( 'paypal_connect', 'disconnect_connect_account' ) . '"
									><i class="latepoint-icon latepoint-icon-x"></i><span>' . __( 'disconnect', 'latepoint' ) . '</span></a>';
			if ( $payments_enabled ) {
				$html .= '<div class="payment-processor-status-connected"><i class="latepoint-icon latepoint-icon-check"></i><span>' . __( 'Connected', 'latepoint' ) . '</span></div>';
				$html .= $disconnect_link;
				$html .= '<div class="paypal-connect-account-info">' . __( 'Merchant: ', 'latepoint' ) . esc_html( $paypal_connect_merchant_id ) . '</div>';
				if ( $env == 'live' && ! empty( OsSettingsHelper::get_settings_value( 'paypal_connect_transaction_fee_info', '' ) ) ) {
					$html .= '<div class="fee-disclosure-wrapper">
                        <div class="fee-disclosure">' . esc_html( OsSettingsHelper::get_settings_value( 'paypal_connect_transaction_fee_info', '' ) ) . ' transaction fee. <a target="_blank" href="https://wpdocs.latepoint.com/understanding-payment-processing-fees/"><span>Pricing</span> <i class="latepoint-icon latepoint-icon-external-link"></i></a></div>
                    </div>';
				}
				// Advisory notice: oauth_integrations not yet propagated. Payments still work.
				if ( ! $oauth_valid ) {
					$html .= '<div class="paypal-connect-status-info-wrapper">';
					$html .= '<div class="paypal-connect-status-item paypal-connect-status-item--info"><i class="latepoint-icon latepoint-icon-info"></i> ' . __( 'Payment permission scopes not yet detected. Payments are active, but you may want to reconnect if issues arise.', 'latepoint' ) . '</div>';
					$html .= '</div>';
				}
			} else {
				$html .= '<div class="payment-processor-status-charges-disabled"><i class="latepoint-icon latepoint-icon-clock"></i><span>' . __( 'Pending Action', 'latepoint' ) . '</span></div>';

				// Show which conditions are blocking payments so seller can act.
				$html .= '<div class="paypal-connect-status-checklist">';
				if ( ! $primary_email_confirmed ) {
					$html .= '<div class="paypal-connect-status-item paypal-connect-status-item--warning"><i class="latepoint-icon latepoint-icon-alert-triangle"></i> ' . __( 'PayPal account email not yet confirmed. Please confirm your PayPal email address.', 'latepoint' ) . '</div>';
				}
				if ( ! $payments_receivable ) {
					$html .= '<div class="paypal-connect-status-item paypal-connect-status-item--warning"><i class="latepoint-icon latepoint-icon-alert-triangle"></i> ' . __( 'Your PayPal account cannot receive payments yet. Please complete your account setup on PayPal.', 'latepoint' ) . '</div>';
				}
				$html .= '</div>';

				// $html .= '<a data-env="' . esc_attr( $env ) . '" data-route-name="' . esc_attr( OsRouterHelper::build_route_name( 'paypal_connect', 'start_connect_process' ) ) . '" href="#" class="payment-start-connecting"><span>' . __( 'Continue Setup', 'latepoint' ) . '</span><i class="latepoint-icon latepoint-icon-arrow-right"></i></a>';
				$html .= '<div class="paypal-connect-account-info">';
				$html .= '<div>' . esc_html( $paypal_connect_merchant_id ) . '</div>';
				$html .= $disconnect_link;
				$html .= '</div>';
			}

			// ACDC vetting status (shown regardless of payments_enabled).
			// if ( ! empty( $acdc_vetting_status ) && 'SUBSCRIBED' !== $acdc_vetting_status ) {
			// 	$acdc_labels  = [
			// 		'NEED_MORE_DATA' => __( 'Advanced card processing: additional information required.', 'latepoint' ),
			// 		'IN_REVIEW'      => __( 'Advanced card processing: your account is under review.', 'latepoint' ),
			// 		'DENIED'         => __( 'Advanced card processing: your application was denied.', 'latepoint' ),
			// 	];
			// 	$acdc_message = $acdc_labels[ $acdc_vetting_status ] ?? '';
			// 	if ( ! empty( $acdc_message ) ) {
			// 		$html .= '<div class="paypal-connect-status-item paypal-connect-status-item--info"><i class="latepoint-icon latepoint-icon-info"></i> ' . esc_html( $acdc_message ) . '</div>';
			// 	}
			// }
		} else {
			$html .= '<a data-env="' . esc_attr( $env ) . '" data-route-name="' . esc_attr( OsRouterHelper::build_route_name( 'paypal_connect', 'start_connect_process' ) ) . '" href="#" class="payment-start-connecting"><span>' . __( 'Start Connecting', 'latepoint' ) . '</span><i class="latepoint-icon latepoint-icon-arrow-right"></i></a>';
		}
		$html .= '</div>';
		return $html;
	}

	public static function zero_decimal_currencies_list() {
		return [ 'huf', 'jpy', 'twd' ];
	}

	public static function load_countries_list() {
		return [
			'US' => 'United States',
			'AU' => 'Australia',
			'AT' => 'Austria',
			'BE' => 'Belgium',
			'BR' => 'Brazil',
			'CA' => 'Canada',
			'CZ' => 'Czech Republic',
			'DK' => 'Denmark',
			'FI' => 'Finland',
			'FR' => 'France',
			'DE' => 'Germany',
			'HK' => 'Hong Kong',
			'HU' => 'Hungary',
			'IN' => 'India',
			'IL' => 'Israel',
			'IT' => 'Italy',
			'JP' => 'Japan',
			'MY' => 'Malaysia',
			'MX' => 'Mexico',
			'NL' => 'Netherlands',
			'NZ' => 'New Zealand',
			'NO' => 'Norway',
			'PH' => 'Philippines',
			'PL' => 'Poland',
			'PT' => 'Portugal',
			'RU' => 'Russia',
			'SG' => 'Singapore',
			'ES' => 'Spain',
			'SE' => 'Sweden',
			'CH' => 'Switzerland',
			'TW' => 'Taiwan',
			'TH' => 'Thailand',
			'GB' => 'United Kingdom',
		];
	}

	public static function load_all_currencies_list() {
		return [
			'aud' => 'AUD',
			'brl' => 'BRL',
			'cad' => 'CAD',
			'cop' => 'COP',
			'czk' => 'CZK',
			'dkk' => 'DKK',
			'eur' => 'EUR',
			'hkd' => 'HKD',
			'huf' => 'HUF',
			'inr' => 'INR',
			'ils' => 'ILS',
			'jpy' => 'JPY',
			'myr' => 'MYR',
			'mxn' => 'MXN',
			'twd' => 'TWD',
			'nzd' => 'NZD',
			'nok' => 'NOK',
			'php' => 'PHP',
			'pln' => 'PLN',
			'gbp' => 'GBP',
			'rub' => 'RUB',
			'sgd' => 'SGD',
			'sek' => 'SEK',
			'chf' => 'CHF',
			'thb' => 'THB',
			'usd' => 'USD',
		];
	}
}
