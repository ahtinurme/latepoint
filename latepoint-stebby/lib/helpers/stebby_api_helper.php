<?php
if ( ! defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly.
}


if ( ! class_exists( 'OsStebbyApiHelper' ) ) :


  /**
   * Thin client for the Stebby Cashier API (v4).
   *
   * Docs: https://api.stebby.eu/docs
   * Authentication is done with an `Api-Key` header against the live API.
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
     * The client identification context. A default Stebby API key is restricted
     * to the TOKEN context, obtained through the redirect-based Identification
     * flow (request-token -> customer authorizes -> token).
     */
    public static function get_client_context(): string {
      return 'TOKEN';
    }

    /**
     * Performs an authenticated request against the Stebby API.
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
          'Content-Type' => 'application/json',
          'Accept'       => 'application/json',
        ],
      ];

      if ( ! empty( $body ) ) {
        $args['body'] = wp_json_encode( $body );
      }

      $response = wp_remote_request( self::get_base_url() . $path, $args );

      if ( is_wp_error( $response ) ) {
        OsDebugHelper::log( 'Stebby API request failed: ' . $response->get_error_message(), 'stebby_api_error', [
          'method' => $method,
          'path'   => $path,
        ] );

        return false;
      }

      $status_code  = wp_remote_retrieve_response_code( $response );
      $decoded_body = json_decode( wp_remote_retrieve_body( $response ), true );

      if ( $status_code < 200 || $status_code >= 300 ) {
        OsDebugHelper::log( 'Stebby API returned an error response', 'stebby_api_error', [
          'method' => $method,
          'path'   => $path,
          'status' => $status_code,
          'body'   => $decoded_body,
        ] );

        return false;
      }

      return is_array( $decoded_body ) ? $decoded_body : [];
    }

    /**
     * Extracts a human readable error message out of a Stebby error response.
     */
    public static function get_error_message_from_response( $response ): string {
      if ( is_array( $response ) && ! empty( $response['errors'][0]['message'] ) ) {
        return (string) $response['errors'][0]['message'];
      }

      return __( 'Stebby request could not be completed.', 'latepoint-addon-stebby' );
    }

    /**
     * Starts the Identification flow: asks Stebby for a one-time authorization
     * URL the customer is redirected to. After they authorize, Stebby sends them
     * to $success_redirect; we then exchange the returned reference for a token.
     *
     * @return array<string, mixed>|false
     */
    public static function request_token( string $success_redirect, string $cancel_redirect ) {
      return self::request( 'POST', '/api/v4/identify/request-token', [
        'successRedirect' => $success_redirect,
        'cancelRedirect'  => $cancel_redirect,
      ] );
    }

    /**
     * Exchanges a request-token reference for the customer's usable token, once
     * they have authorized it in Stebby. The token is used as client.value with
     * context TOKEN in ticket lookups.
     *
     * @return array<string, mixed>|false
     */
    public static function get_token( string $reference ) {
      return self::request( 'POST', '/api/v4/identify/token/' . rawurlencode( $reference ) );
    }

    /**
     * Looks up usable (non-expired, non-claimed) tickets, optionally filtered by
     * ticket code, client and purchasable code.
     *
     * @param array<string, mixed> $filters
     *
     * @return array<string, mixed>|false
     */
    public static function get_tickets( array $filters ) {
      return self::request( 'POST', '/api/v4/tickets', $filters );
    }

    /**
     * Marks a ticket as used (claims a previously purchased service).
     *
     * @return array<string, mixed>|false
     */
    public static function use_ticket( string $ticket_code ) {
      return self::request( 'POST', '/api/v4/tickets/use/' . rawurlencode( $ticket_code ) );
    }

  }


endif;
