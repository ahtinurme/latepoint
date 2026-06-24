<?php
if ( ! defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly.
}


if ( ! class_exists( 'OsEverypayApiHelper' ) ) :


  class OsEverypayApiHelper {

    const LIVE_BASE_URL = 'https://pay.every-pay.eu/api/v4';
    const TEST_BASE_URL = 'https://igw-demo.every-pay.com/api/v4';

    public static function get_base_url(): string {
      return OsSettingsHelper::is_env_payments_dev() ? self::TEST_BASE_URL : self::LIVE_BASE_URL;
    }

    public static function get_api_username(): string {
      $key = OsSettingsHelper::is_env_payments_dev() ? 'everypay_test_api_username' : 'everypay_api_username';

      return (string) OsSettingsHelper::get_settings_value( $key, '' );
    }

    public static function get_api_secret(): string {
      $key = OsSettingsHelper::is_env_payments_dev() ? 'everypay_test_api_secret' : 'everypay_api_secret';

      return (string) OsSettingsHelper::get_settings_value( $key, '' );
    }

    public static function get_account_name(): string {
      $key = OsSettingsHelper::is_env_payments_dev() ? 'everypay_test_account_name' : 'everypay_account_name';

      return (string) OsSettingsHelper::get_settings_value( $key, '' );
    }

    public static function request( string $method, string $path, array $body = [] ) {
      $url = self::get_base_url() . $path;

      $args = [
        'method'    => $method,
        'timeout'   => 20,
        'sslverify' => true,
        'headers'   => [
          'Authorization' => 'Basic ' . base64_encode( self::get_api_username() . ':' . self::get_api_secret() ),
          'Content-Type'  => 'application/json',
          'Accept'        => 'application/json',
        ],
      ];

      if ( ! empty( $body ) ) {
        $args['body'] = wp_json_encode( $body );
      }

      $response = wp_remote_request( $url, $args );

      if ( is_wp_error( $response ) ) {
        OsDebugHelper::log( 'EveryPay API request failed: ' . $response->get_error_message(), 'everypay_api_error', [
          'method' => $method,
          'path'   => $path,
        ] );

        return false;
      }

      $status_code   = wp_remote_retrieve_response_code( $response );
      $decoded_body  = json_decode( wp_remote_retrieve_body( $response ), true );

      if ( $status_code < 200 || $status_code >= 300 ) {
        OsDebugHelper::log( 'EveryPay API returned an error response', 'everypay_api_error', [
          'method' => $method,
          'path'   => $path,
          'status' => $status_code,
          'body'   => $decoded_body,
        ] );

        return false;
      }

      return $decoded_body;
    }

    public static function create_oneoff_payment( array $data ) {
      $body = [
        'api_username'        => self::get_api_username(),
        'account_name'        => self::get_account_name(),
        'amount'              => number_format( (float) ( $data['amount'] ?? 0 ), 2, '.', '' ),
        'order_reference'     => $data['order_reference'] ?? '',
        'nonce'               => self::generate_nonce(),
        'timestamp'           => gmdate( 'c' ),
        'email'               => $data['email'] ?? '',
        'customer_ip'         => $data['customer_ip'] ?? '',
        'locale'              => $data['locale'] ?? '',
        'customer_url'        => $data['customer_url'] ?? '',
        'callback_url'        => $data['callback_url'] ?? '',
        'integration_details' => $data['integration_details'] ?? [
          'software' => 'LatePoint Addon - EveryPay',
          'version'  => '1.0',
        ],
        'mobile_payment'      => $data['mobile_payment'] ?? false,
      ];

      return self::request( 'POST', '/payments/oneoff', $body );
    }

    public static function get_payment_status( string $payment_reference ) {
      $path = '/payments/' . rawurlencode( $payment_reference )
        . '?api_username=' . rawurlencode( self::get_api_username() );

      return self::request( 'GET', $path );
    }

    public static function refund_payment( string $payment_reference, $amount ) {
      $body = [
        'api_username'      => self::get_api_username(),
        'payment_reference' => $payment_reference,
        'amount'            => number_format( (float) $amount, 2, '.', '' ),
        'nonce'             => self::generate_nonce(),
        'timestamp'         => gmdate( 'c' ),
      ];

      return self::request( 'POST', '/payments/refund', $body );
    }

    private static function generate_nonce(): string {
      try {
        $random = bin2hex( random_bytes( 32 ) );
      } catch ( \Exception $e ) {
        $random = uniqid( '', true ) . mt_rand();
      }

      return hash( 'sha512', $random . microtime( true ) );
    }

  }


endif;
