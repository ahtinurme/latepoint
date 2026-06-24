<?php
if ( ! defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly.
}


if ( ! class_exists( 'OsStebbyApiHelper' ) ) :


  /**
   * Thin client for the Stebby Cashier API v3.
   *
   * Docs: https://app.stebby.eu/api
   * Requests are authenticated with an `Api-Key` header and `Api-Version: 3`
   * against the live API. v3 identifies the customer directly by their ID code
   * (no redirect/token flow), which is what ticket redemption needs.
   */
  class OsStebbyApiHelper {

    const LIVE_BASE_URL = 'https://api.stebby.eu';

    public static function get_base_url(): string {
      return self::LIVE_BASE_URL;
    }

    public static function get_api_key(): string {
      return (string) OsSettingsHelper::get_settings_value( 'stebby_api_key', '' );
    }

    /**
     * Performs an authenticated request against the Stebby v3 API.
     *
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>|false Decoded response body, or false on failure.
     */
    public static function request( string $method, string $path, array $body = [] ) {
      $api_key = self::get_api_key();
      if ( empty( $api_key ) ) {
        OsDebugHelper::log( 'Stebby API key is not configured', 'stebby_api_error', [ 'path' => $path ] );

        return false;
      }

      $args = [
        'method'    => $method,
        'timeout'   => 20,
        'sslverify' => true,
        'headers'   => [
          'Api-Key'      => $api_key,
          'Api-Version'  => '3',
          'Content-Type' => 'application/json',
          'Accept'       => 'application/json',
        ],
      ];

      if ( ! empty( $body ) ) {
        $args['body'] = wp_json_encode( $body );
      }

      $response = wp_remote_request( self::get_base_url() . $path, $args );

      if ( is_wp_error( $response ) ) {
        OsDebugHelper::log( 'Stebby API call (transport error)', 'stebby_api', [
          'method'   => $method,
          'path'     => $path,
          'request'  => $body,
          'wp_error' => $response->get_error_message(),
        ] );
        OsDebugHelper::log( 'Stebby API request failed: ' . $response->get_error_message(), 'stebby_api_error', [
          'method' => $method,
          'path'   => $path,
        ] );

        return false;
      }

      $status_code  = wp_remote_retrieve_response_code( $response );
      $raw_body     = wp_remote_retrieve_body( $response );
      $decoded_body = json_decode( $raw_body, true );
      $logged_body  = $decoded_body !== null ? $decoded_body : $raw_body;

      // Full trace of every Stebby request/response, for debugging.
      OsDebugHelper::log( 'Stebby API call', 'stebby_api', [
        'method'   => $method,
        'path'     => $path,
        'request'  => $body,
        'status'   => $status_code,
        'response' => $logged_body,
      ] );

      if ( $status_code < 200 || $status_code >= 300 ) {
        OsDebugHelper::log( 'Stebby API returned an error response', 'stebby_api_error', [
          'method' => $method,
          'path'   => $path,
          'status' => $status_code,
          'body'   => $logged_body,
        ] );

        return false;
      }

      return is_array( $decoded_body ) ? $decoded_body : [];
    }

    /**
     * Extracts a human readable error message out of a Stebby error response.
     */
    public static function get_error_message_from_response( $response ): string {
      if ( is_array( $response ) ) {
        if ( ! empty( $response['errors'][0]['message'] ) ) {
          return (string) $response['errors'][0]['message'];
        }
        if ( ! empty( $response['message'] ) ) {
          return (string) $response['message'];
        }
      }

      return __( 'Stebby request could not be completed.', 'latepoint-addon-stebby' );
    }

    /**
     * Lists a client's usable (non-expired, unclaimed) tickets by their ID code.
     *
     * @param array<string, mixed> $filters e.g. ['idcode' => '39014022745']
     *
     * @return array<string, mixed>|false
     */
    public static function get_tickets( array $filters ) {
      return self::request( 'POST', '/api/getTickets', $filters );
    }

    /**
     * Marks a ticket as used (claims a previously purchased service).
     *
     * @return array<string, mixed>|false
     */
    public static function use_ticket( string $ticket_code ) {
      return self::request( 'POST', '/api/useTicket', [ 'ticket_code' => $ticket_code ] );
    }

  }


endif;
