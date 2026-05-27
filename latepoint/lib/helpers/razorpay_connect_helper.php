<?php
/*
 * Copyright (c) 2024 LatePoint LLC. All rights reserved.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class OsRazorpayConnectHelper {
	public static $default_currency_iso_code = 'INR';
	public static $processor_code            = 'razorpay_connect';

	// -------------------------------------------------------------------------
	// Payment processor registration
	// -------------------------------------------------------------------------

	public static function register_payment_processor( array $payment_processors ): array {
		$payment_processors[ self::$processor_code ] = [
			'code'       => self::$processor_code,
			'name'       => __( 'Razorpay Checkout', 'latepoint' ),
			'front_name' => __( 'Razorpay Checkout', 'latepoint' ),
			'image_url'  => LATEPOINT_IMAGES_URL . 'processor-razorpay-logo.png',
		];
		return $payment_processors;
	}

	public static function add_settings_fields( $processor_code ) {
		if ( $processor_code !== self::$processor_code ) {
			return false;
		}
		?>
		<div class="sub-section-row">
			<div class="sub-section-label">
				<h3><?php esc_html_e( 'Connect (Live)', 'latepoint' ); ?></h3>
			</div>
			<div class="sub-section-content">
				<div data-env="<?php echo esc_attr( LATEPOINT_PAYMENTS_ENV_LIVE ); ?>"
					 class="payment-processor-connect-status-wrapper razorpay-connect-status-wrapper"
					 data-route-name="<?php echo esc_attr( OsRouterHelper::build_route_name( 'razorpay_connect', 'check_connect_status' ) ); ?>">
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
					 class="payment-processor-connect-status-wrapper razorpay-connect-status-wrapper"
					 data-route-name="<?php echo esc_attr( OsRouterHelper::build_route_name( 'razorpay_connect', 'check_connect_status' ) ); ?>">
					<div class="os-loading-spinner"></div>
				</div>
			</div>
		</div>
		<div class="sub-section-row">
			<div class="sub-section-label">
				<h3><?php esc_html_e( 'Currency Settings', 'latepoint' ); ?></h3>
			</div>
			<div class="sub-section-content">
				<?php
				$selected_country_code  = OsSettingsHelper::get_settings_value( 'razorpay_connect_country_code', 'IN' );
				$selected_currency_code = OsSettingsHelper::get_settings_value( 'razorpay_connect_currency_iso_code', self::$default_currency_iso_code );
				?>
				<div class="os-row">
					<div class="os-col-6">
						<?php echo OsFormHelper::select_field( 'settings[razorpay_connect_country_code]', __( 'Country', 'latepoint' ), self::load_countries_list(), $selected_country_code ); ?>
					</div>
					<div class="os-col-6">
						<?php echo OsFormHelper::select_field( 'settings[razorpay_connect_currency_iso_code]', __( 'Currency Code', 'latepoint' ), self::load_currencies_list(), $selected_currency_code ); ?>
					</div>
				</div>
			</div>
		</div>
		<div class="sub-section-row">
			<div class="sub-section-label">
				<h3><?php esc_html_e( 'Other Settings', 'latepoint' ); ?></h3>
			</div>
			<div class="sub-section-content">
				<div class="os-row os-mb-1">
					<div class="os-col-6">
						<?php echo OsFormHelper::text_field( 'settings[razorpay_connect_company_name]', __( 'Company Name (Appears on Payment Modal)', 'latepoint' ), OsSettingsHelper::get_settings_value( 'razorpay_connect_company_name' ), [ 'theme' => 'simple' ] ); ?>
					</div>
					<div class="os-col-6">
						<?php echo OsFormHelper::color_picker( 'settings[razorpay_connect_theme_color]', __( 'Color for Payment Modal', 'latepoint' ), OsSettingsHelper::get_settings_value( 'razorpay_connect_theme_color', '#4366ff' ) ); ?>
					</div>
				</div>
				<div class="os-row os-mb-2">
					<div class="os-col-12">
						<?php echo OsFormHelper::media_uploader_field( 'settings[razorpay_connect_logo_image_id]', 0, __( 'Logo for Payment Modal', 'latepoint' ), __( 'Remove Logo', 'latepoint' ), OsSettingsHelper::get_settings_value( 'razorpay_connect_logo_image_id' ) ); ?>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Payment methods
	// -------------------------------------------------------------------------

	public static function get_supported_payment_methods(): array {
		return [
			'razorpay_checkout' => [
				'name'      => __( 'Razorpay Checkout', 'latepoint' ),
				'label'     => __( 'Pay with Razorpay', 'latepoint' ),
				'image_url' => LATEPOINT_IMAGES_URL . 'processor-razorpay-logo.png',
			],
		];
	}

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

	// -------------------------------------------------------------------------
	// Payment step UI
	// -------------------------------------------------------------------------

	public static function output_payment_step_contents( OsCartModel $cart ) {
		if ( ! OsPaymentsHelper::should_processor_handle_payment_for_cart( self::$processor_code, $cart ) ) {
			return;
		}
		echo '<div class="lp-payment-method-content" data-payment-method="razorpay_checkout">';
		echo '<div class="lp-payment-method-content-i">';
		echo '<div class="razorpay-payment-trigger"></div>';
		echo '</div>';
		echo '</div>';
	}

	public static function output_order_payment_pay_contents( OsTransactionIntentModel $transaction_intent ) {
		if ( ! OsPaymentsHelper::should_processor_handle_payment_for_transaction_intent( self::$processor_code, $transaction_intent ) ) {
			return;
		}
		echo '<div class="lp-payment-method-content" data-payment-method="razorpay_checkout">';
		echo '<div class="lp-payment-method-content-i">';
		echo '<div class="razorpay-payment-trigger"></div>';
		echo '</div>';
		echo '</div>';
	}

	// -------------------------------------------------------------------------
	// Amount conversion
	// -------------------------------------------------------------------------

	public static function convert_charge_amount_to_requirements( $charge_amount, OsCartModel $cart ) {
		if ( OsPaymentsHelper::should_processor_handle_payment_for_cart( self::$processor_code, $cart ) ) {
			$charge_amount = self::convert_amount_to_specs( $charge_amount );
		}
		return $charge_amount;
	}

	public static function convert_transaction_intent_charge_amount_to_specs( $amount, OsTransactionIntentModel $transaction_intent ) {
		if ( OsPaymentsHelper::should_processor_handle_payment_for_transaction_intent( self::$processor_code, $transaction_intent ) ) {
			$amount = self::convert_amount_to_specs( $amount );
		}
		return $amount;
	}

	public static function convert_amount_to_specs( $charge_amount ) {
		$iso_code = self::get_currency_iso_code();
		if ( in_array( strtolower( $iso_code ), self::zero_decimal_currencies_list() ) ) {
			$charge_amount = round( $charge_amount );
		} else {
			$number_of_decimals = OsSettingsHelper::get_settings_value( 'number_of_decimals', '2' );
			$charge_amount      = number_format( (float) $charge_amount, $number_of_decimals, '.', '' ) * pow( 10, $number_of_decimals );
		}
		return (int) $charge_amount;
	}

	public static function convert_amount_back_from_specs_to_db_format( $charge_amount ) {
		$iso_code           = self::get_currency_iso_code();
		$number_of_decimals = OsSettingsHelper::get_settings_value( 'number_of_decimals', '2' );
		if ( ! in_array( strtolower( $iso_code ), self::zero_decimal_currencies_list() ) && ! empty( $number_of_decimals ) ) {
			$charge_amount = $charge_amount / pow( 10, $number_of_decimals );
			$charge_amount = number_format( (float) $charge_amount, 4, '.', '' );
		} else {
			$charge_amount = OsMoneyHelper::pad_to_db_format( $charge_amount );
		}
		return $charge_amount;
	}

	public static function zero_decimal_currencies_list(): array {
		return array( 'bif', 'clp', 'djf', 'gnf', 'jpy', 'kmf', 'krw', 'mga', 'pyg', 'rwf', 'ugx', 'vnd', 'vuv', 'xaf', 'xof', 'xpf' );
	}

	// -------------------------------------------------------------------------
	// Payment processing
	// -------------------------------------------------------------------------

	public static function process_payment( $result, OsOrderIntentModel $order_intent ) {
		if ( ! OsPaymentsHelper::should_processor_handle_payment_for_order_intent( self::$processor_code, $order_intent ) ) {
			return $result;
		}
		switch ( $order_intent->get_payment_data_value( 'method' ) ) {
			case 'razorpay_checkout':
				if ( $order_intent->get_payment_data_value( 'token' ) ) {
					$payment_data = self::retrieve_payment( $order_intent->get_payment_data_value( 'token' ) );
					if ( ! empty( $payment_data['data'] ) && in_array( $payment_data['data']['status'], [ 'authorized', 'captured' ] ) ) {
						$result['status']    = LATEPOINT_STATUS_SUCCESS;
						$result['processor'] = self::$processor_code;
						$result['charge_id'] = $payment_data['data']['id'];
						$result['amount']    = $payment_data['data']['amount'];
						$result['kind']      = $payment_data['data']['status'] === 'authorized' ? LATEPOINT_TRANSACTION_KIND_AUTHORIZATION : LATEPOINT_TRANSACTION_KIND_CAPTURE;
					} else {
						$result['status']  = LATEPOINT_STATUS_ERROR;
						$result['message'] = __( 'Payment Error', 'latepoint' );
						$order_intent->add_error( 'payment_error', $result['message'] );
						$order_intent->add_error( 'send_to_step', $result['message'], 'payment' );
					}
				} else {
					$result['status']  = LATEPOINT_STATUS_ERROR;
					$result['message'] = __( 'Payment Error — token missing', 'latepoint' );
					$order_intent->add_error( 'payment_error', $result['message'] );
				}
				break;
		}
		return $result;
	}

	public static function process_payment_for_transaction_intent( $result, OsTransactionIntentModel $transaction_intent ) {
		if ( ! OsPaymentsHelper::should_processor_handle_payment_for_transaction_intent( self::$processor_code, $transaction_intent ) ) {
			return $result;
		}
		switch ( $transaction_intent->get_payment_data_value( 'method' ) ) {
			case 'razorpay_checkout':
				if ( $transaction_intent->get_payment_data_value( 'token' ) ) {
					$payment_data = self::retrieve_payment( $transaction_intent->get_payment_data_value( 'token' ) );
					if ( ! empty( $payment_data['data'] ) && in_array( $payment_data['data']['status'], [ 'authorized', 'captured' ] ) ) {
						$result['status']    = LATEPOINT_STATUS_SUCCESS;
						$result['processor'] = self::$processor_code;
						$result['charge_id'] = $payment_data['data']['id'];
						$result['amount']    = $payment_data['data']['amount'];
						$result['kind']      = $payment_data['data']['status'] === 'authorized' ? LATEPOINT_TRANSACTION_KIND_AUTHORIZATION : LATEPOINT_TRANSACTION_KIND_CAPTURE;
					} else {
						$result['status']  = LATEPOINT_STATUS_ERROR;
						$result['message'] = __( 'Payment Error', 'latepoint' );
						$transaction_intent->add_error( 'send_to_step', $result['message'], 'payment' );
					}
				} else {
					$result['status']  = LATEPOINT_STATUS_ERROR;
					$result['message'] = __( 'Payment Error — token missing', 'latepoint' );
					$transaction_intent->add_error( 'payment_error', $result['message'] );
				}
				break;
		}
		return $result;
	}

	public static function transaction_is_refund_available( $result, OsTransactionModel $transaction_model ): bool {
		if ( OsPaymentsHelper::is_payment_processor_enabled( self::$processor_code ) && $transaction_model->processor === self::$processor_code ) {
			$result = true;
		}
		return $result;
	}

	public static function process_refund( $transaction_refund, OsTransactionModel $transaction, $custom_amount = null ) {
		if ( $transaction->processor !== self::$processor_code ) {
			return $transaction_refund;
		}
		if ( ! $transaction->can_refund() ) {
			throw new Exception( __( 'Invalid Transaction', 'latepoint' ) );
		}

		$refund_data = [
			'payment_id' => $transaction->token,
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
		$transaction_refund->amount         = self::convert_amount_back_from_specs_to_db_format( $response['data']['amount'] );
		$transaction_refund->token          = $response['data']['id'];
		if ( $transaction_refund->save() ) {
			do_action( 'latepoint_transaction_refund_created', $transaction_refund );
			return $transaction_refund;
		} else {
			throw new Exception( implode( ', ', $transaction_refund->get_error_messages() ) );
		}
	}

	// -------------------------------------------------------------------------
	// Middleware requests
	// -------------------------------------------------------------------------

	public static function generate_razorpay_order_for_order_intent( OsOrderIntentModel $order_intent ): array {
		$options = [
			'amount'   => $order_intent->specs_charge_amount,
			'currency' => strtoupper( self::get_currency_iso_code() ),
			'notes'    => [
				'order_intent_key' => $order_intent->intent_key,
			],
		];
		$result  = self::do_account_request(
			'orders',
			OsSettingsHelper::get_payments_environment(),
			'',
			'POST',
			[ 'order_options' => $options ]
		);
		if ( empty( $result['data'] ) ) {
			$error_message = ! empty( $result['error'] ) ? sprintf( __( 'Payment Error: %s', 'latepoint' ), esc_html( $result['error'] ) ) : __( 'Error generating Razorpay order', 'latepoint' );
			OsDebugHelper::log( $error_message );
			throw new Exception( $error_message );
		}
		return $result['data'];
	}

	public static function generate_razorpay_order_for_transaction_intent( OsTransactionIntentModel $transaction_intent ): array {
		$options = [
			'amount'   => $transaction_intent->specs_charge_amount,
			'currency' => strtoupper( self::get_currency_iso_code() ),
			'notes'    => [
				'transaction_intent_key' => $transaction_intent->intent_key,
			],
		];
		$result  = self::do_account_request(
			'orders',
			OsSettingsHelper::get_payments_environment(),
			'',
			'POST',
			[ 'order_options' => $options ]
		);
		if ( empty( $result['data'] ) ) {
			$error_message = ! empty( $result['error'] ) ? sprintf( __( 'Payment Error: %s', 'latepoint' ), esc_html( $result['error'] ) ) : __( 'Error generating Razorpay order for transaction', 'latepoint' );
			OsDebugHelper::log( $error_message );
			throw new Exception( $error_message );
		}
		return $result['data'];
	}

	public static function retrieve_payment( string $payment_id ): array {
		$env = OsSettingsHelper::get_payments_environment();
		return self::do_account_request( 'payments/' . $payment_id, $env );
	}

	public static function do_account_request( string $path, string $env = '', string $connection_data = '', string $method = 'GET', array $vars = [], array $headers = [] ) {
		if ( empty( $env ) ) {
			$env = OsSettingsHelper::get_payments_environment();
		}
		$path = self::get_connect_account_id( $env ) . '/' . $path;
		try {
			return self::do_request( $path, $connection_data, $method, $vars, $headers, $env );
		} catch ( Exception $e ) {
			OsDebugHelper::log( 'Error processing request to Razorpay middleware: ' . $e->getMessage(), 'razorpay_connect_error' );
			return [];
		}
	}

	public static function do_request( string $path, string $connection_data = '', string $method = 'GET', array $vars = [], array $headers = [], string $force_env = '' ) {
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
			'body'      => $vars,
			'sslverify' => false,
			'method'    => $method,
		);

		if ( ! empty( $force_env ) && in_array( $force_env, [ LATEPOINT_PAYMENTS_ENV_DEV, LATEPOINT_PAYMENTS_ENV_LIVE ] ) ) {
			$env = ( $force_env === LATEPOINT_PAYMENTS_ENV_DEV ) ? 'test' : 'live';
		} else {
			$env = OsSettingsHelper::is_env_payments_dev() ? 'test' : 'live';
		}

		$url      = LATEPOINT_RAZORPAY_CONNECT_URL . "/api/wp/v1/razorpay-connect/{$env}/{$path}";
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

	// -------------------------------------------------------------------------
	// Connection management
	// -------------------------------------------------------------------------

	public static function get_server_token( string $force_env = '' ): string {
		$key          = OsSettingsHelper::append_payment_env_key( 'server_token_for_razorpay_connect', $force_env );
		$server_token = OsSettingsHelper::get_settings_value( $key, '' );
		if ( empty( $server_token ) ) {
			$server_token = OsUtilHelper::generate_uuid();
			OsSettingsHelper::save_setting_by_name( $key, $server_token );
		}
		return $server_token;
	}

	public static function reset_server_token( string $force_env = '' ): string {
		$key              = OsSettingsHelper::append_payment_env_key( 'server_token_for_razorpay_connect', $force_env );
		$new_server_token = OsUtilHelper::generate_uuid();
		OsSettingsHelper::save_setting_by_name( $key, $new_server_token );
		return $new_server_token;
	}

	public static function get_connect_url( string $env = '' ): string {
		$url  = LATEPOINT_RAZORPAY_CONNECT_URL . '/wp/razorpay-connection/' . $env . '/start/';
		$url .= self::get_server_token( $env ) . '/' . base64_encode( implode( '|||', [ get_bloginfo( 'name' ), get_site_icon_url(), OsUtilHelper::get_site_url() ] ) );
		return $url;
	}

	public static function get_connect_account_id( string $env = '' ): string {
		if ( empty( $env ) ) {
			$env = OsSettingsHelper::get_payments_environment();
		}
		return OsSettingsHelper::get_settings_value( OsSettingsHelper::append_payment_env_key( 'razorpay_connect_account_id', $env ), '' );
	}

	public static function get_connect_public_token( string $env = '' ): string {
		if ( empty( $env ) ) {
			$env = OsSettingsHelper::get_payments_environment();
		}
		return OsSettingsHelper::get_settings_value( OsSettingsHelper::append_payment_env_key( 'razorpay_connect_public_token', $env ), '' );
	}

	public static function get_connection_buttons_and_status( string $env = '' ): string {
		$razorpay_connect_account_id = OsSettingsHelper::get_settings_value( OsSettingsHelper::append_payment_env_key( 'razorpay_connect_account_id', $env ), false );
		$html                        = '';
		$duplicate_token_activations = OsSettingsHelper::get_settings_value( OsSettingsHelper::append_payment_env_key( 'razorpay_connect_duplicate_token_activations', $env ), '' );

		if ( ! empty( $duplicate_token_activations ) ) {
			$html .= '<div class="latepoint-fix-records-warning"><i class="latepoint-icon latepoint-icon-info"></i><div>';
			$html .= '<div>' . __( 'The following websites are using the same server token. This can happen if a site was cloned from one server to another. To fix this, disconnect each site and reconnect it.', 'latepoint' ) . '</div>';
			$html .= '<div class="latepoint-fix-records-values">' . esc_html( $duplicate_token_activations ) . '</div>';
			$html .= '</div></div>';
		}

		$html .= '<div class="payment-processor-connect-status-inner">';
		if ( $razorpay_connect_account_id ) {
			$charges_enabled = OsSettingsHelper::is_on( OsSettingsHelper::append_payment_env_key( 'razorpay_connect_charges_enabled', $env ) );
			$disconnect_link = '<a class="payment-processor-disconnect-link" href="#"
										data-os-pass-response="yes"
										data-os-pass-this="yes"
			                data-os-before-after="none"
			                data-os-after-call="latepointRazorpayConnectAdmin.reload_connect_status_wrapper"
			                data-os-params="' . esc_attr( OsUtilHelper::build_os_params( [ 'env' => $env ] ) ) . '"
										data-os-action="' . OsRouterHelper::build_route_name( 'razorpay_connect', 'disconnect_connect_account' ) . '"
										><i class="latepoint-icon latepoint-icon-x"></i><span>' . __( 'disconnect', 'latepoint' ) . '</span></a>';
			if ( $charges_enabled ) {
				$html .= '<div class="payment-processor-status-connected"><i class="latepoint-icon latepoint-icon-check"></i><span>' . __( 'Connected', 'latepoint' ) . '</span></div>';
				$html .= $disconnect_link;
				$html .= '<div class="razorpay-connect-account-info">' . __( 'Account: ', 'latepoint' ) . esc_html( $razorpay_connect_account_id ) . '</div>';
				if ( $env === LATEPOINT_PAYMENTS_ENV_LIVE && ! empty( OsSettingsHelper::get_settings_value( 'razorpay_connect_transaction_fee_info', '' ) ) ) {
					$html .= '<div class="fee-disclosure-wrapper">
	                    <div class="fee-disclosure">' . esc_html( OsSettingsHelper::get_settings_value( 'razorpay_connect_transaction_fee_info', '' ) ) . ' transaction fee. <a target="_blank" href="https://wpdocs.latepoint.com/understanding-payment-processing-fees/"><span>Pricing</span> <i class="latepoint-icon latepoint-icon-external-link"></i></a></div>
	                </div>';
				}
			} else {
				$html .= '<div class="payment-processor-status-charges-disabled"><i class="latepoint-icon latepoint-icon-clock"></i><span>' . __( 'Pending Action', 'latepoint' ) . '</span></div>';
				$html .= '<a data-env="' . esc_attr( $env ) . '" data-route-name="' . esc_attr( OsRouterHelper::build_route_name( 'razorpay_connect', 'start_connect_process' ) ) . '" href="#" class="payment-start-connecting"><span>' . __( 'Continue Setup', 'latepoint' ) . '</span><i class="latepoint-icon latepoint-icon-arrow-right"></i></a>';
				$html .= '<div class="razorpay-connect-account-info">';
				$html .= '<div>' . esc_html( $razorpay_connect_account_id ) . '</div>';
				$html .= $disconnect_link;
				$html .= '</div>';
			}
		} else {
			$html .= '<a data-env="' . esc_attr( $env ) . '" data-route-name="' . esc_attr( OsRouterHelper::build_route_name( 'razorpay_connect', 'start_connect_process' ) ) . '" href="#" class="payment-start-connecting"><span>' . __( 'Start Connecting', 'latepoint' ) . '</span><i class="latepoint-icon latepoint-icon-arrow-right"></i></a>';
		}
		$html .= '</div>';
		return $html;
	}

	// -------------------------------------------------------------------------
	// Settings helpers
	// -------------------------------------------------------------------------

	public static function get_currency_iso_code(): string {
		return OsSettingsHelper::get_settings_value( 'razorpay_connect_currency_iso_code', self::$default_currency_iso_code );
	}

	public static function load_countries_list(): array {
		return [ 'IN' => 'India' ];
	}

	public static function load_currencies_list(): array {
		return [
			'AED' => 'United Arab Emirates Dirham',
			'ALL' => 'Albanian Lek',
			'AMD' => 'Armenian Dram',
			'ARS' => 'Argentine Peso',
			'AUD' => 'Australian Dollar',
			'AWG' => 'Aruban Florin',
			'BBD' => 'Barbadian Dollar',
			'BDT' => 'Bangladeshi Taka',
			'BMD' => 'Bermudian Dollar',
			'BND' => 'Brunei Dollar',
			'BOB' => 'Bolivian Boliviano',
			'BSD' => 'Bahamian Dollar',
			'BWP' => 'Botswana Pula',
			'BZD' => 'Belize Dollar',
			'CAD' => 'Canadian Dollar',
			'CHF' => 'Swiss Franc',
			'CNY' => 'Chinese Yuan',
			'COP' => 'Colombian Peso',
			'CRC' => 'Costa Rican Colón',
			'CUP' => 'Cuban Peso',
			'CZK' => 'Czech Koruna',
			'DKK' => 'Danish Krone',
			'DOP' => 'Dominican Peso',
			'DZD' => 'Algerian Dinar',
			'EGP' => 'Egyptian Pound',
			'ETB' => 'Ethiopian Birr',
			'EUR' => 'Euro',
			'FJD' => 'Fijian Dollar',
			'GBP' => 'Pound Sterling',
			'GHS' => 'Ghanaian Cedi',
			'GIP' => 'Gibraltar Pound',
			'GMD' => 'Gambian Dalasi',
			'GTQ' => 'Guatemalan Quetzal',
			'GYD' => 'Guyanese Dollar',
			'HKD' => 'Hong Kong Dollar',
			'HNL' => 'Honduran Lempira',
			'HRK' => 'Croatian Kuna',
			'HTG' => 'Haitian Gourde',
			'HUF' => 'Hungarian Forint',
			'IDR' => 'Indonesian Rupiah',
			'ILS' => 'Israeli New Shekel',
			'INR' => 'Indian Rupee',
			'JMD' => 'Jamaican Dollar',
			'KES' => 'Kenyan Shilling',
			'KGS' => 'Kyrgyzstani Som',
			'KHR' => 'Cambodian Riel',
			'KYD' => 'Cayman Islands Dollar',
			'KZT' => 'Kazakhstani Tenge',
			'LAK' => 'Lao Kip',
			'LBP' => 'Lebanese Pound',
			'LKR' => 'Sri Lankan Rupee',
			'LRD' => 'Liberian Dollar',
			'LSL' => 'Lesotho Loti',
			'MAD' => 'Moroccan Dirham',
			'MDL' => 'Moldovan Leu',
			'MKD' => 'Macedonian Denar',
			'MMK' => 'Myanmar Kyat',
			'MNT' => 'Mongolian Tögrög',
			'MOP' => 'Macanese Pataca',
			'MUR' => 'Mauritian Rupee',
			'MVR' => 'Maldivian Rufiyaa',
			'MWK' => 'Malawian Kwacha',
			'MXN' => 'Mexican Peso',
			'MYR' => 'Malaysian Ringgit',
			'NAD' => 'Namibian Dollar',
			'NGN' => 'Nigerian Naira',
			'NIO' => 'Nicaraguan Córdoba',
			'NOK' => 'Norwegian Krone',
			'NPR' => 'Nepalese Rupee',
			'NZD' => 'New Zealand Dollar',
			'PEN' => 'Peruvian Sol',
			'PGK' => 'Papua New Guinean Kina',
			'PHP' => 'Philippine Peso',
			'PKR' => 'Pakistani Rupee',
			'QAR' => 'Qatari Riyal',
			'RUB' => 'Russian Ruble',
			'SAR' => 'Saudi Riyal',
			'SCR' => 'Seychellois Rupee',
			'SEK' => 'Swedish Krona',
			'SGD' => 'Singapore Dollar',
			'SLL' => 'Sierra Leonean Leone',
			'SOS' => 'Somali Shilling',
			'SSP' => 'South Sudanese Pound',
			'SVC' => 'Salvadoran Colón',
			'SZL' => 'Swazi Lilangeni',
			'THB' => 'Thai Baht',
			'TTD' => 'Trinidad and Tobago Dollar',
			'TZS' => 'Tanzanian Shilling',
			'USD' => 'United States Dollar',
			'UYU' => 'Uruguayan Peso',
			'UZS' => 'Uzbekistani Som',
			'YER' => 'Yemeni Rial',
			'ZAR' => 'South African Rand',
		];
	}
}
