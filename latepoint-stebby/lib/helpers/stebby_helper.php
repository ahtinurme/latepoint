<?php
if ( ! defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly.
}


if ( ! class_exists( 'OsStebbyHelper' ) ) :


  /**
   * Registers Stebby as a LatePoint payment processor for both the booking
   * checkout (order intent) and the invoice payment (transaction intent) flows.
   *
   * Stebby is a redirect processor: the customer identifies themselves through
   * the Stebby Identification flow, then we list their redeemable tickets and
   * redeem one. The redeemed ticket's value is deducted from the total; any
   * remainder is left as a balance due on the order.
   */
  class OsStebbyHelper {

    public static $processor_code = 'stebby';

    const PAYMENT_DATA_VALUE     = 'stebby_value';
    const PAYMENT_DATA_REFERENCE = 'stebby_reference';

    const CONTEXT_BOOKING     = 'booking';
    const CONTEXT_TRANSACTION = 'transaction';

    public static function init_hooks(): void {
      add_filter( 'latepoint_payment_processors', [ __CLASS__, 'register_payment_processor' ] );
      add_filter( 'latepoint_get_all_payment_times', [ __CLASS__, 'add_all_payment_methods_to_payment_times' ] );
      add_filter( 'latepoint_get_enabled_payment_times', [ __CLASS__, 'add_enabled_payment_methods_to_payment_times' ] );
      add_filter( 'latepoint_encrypted_settings', [ __CLASS__, 'encrypted_settings' ] );

      add_filter( 'latepoint_process_payment_for_order_intent', [ __CLASS__, 'process_payment' ], 10, 2 );
      add_filter( 'latepoint_process_payment_for_transaction_intent', [ __CLASS__, 'process_payment_for_transaction_intent' ], 10, 2 );

      add_action( 'latepoint_payment_processor_settings', [ __CLASS__, 'add_settings_fields' ], 10 );
      add_action( 'latepoint_step_payment__pay_content', [ __CLASS__, 'output_payment_step_contents' ], 10 );
      add_action( 'latepoint_order_payment__pay_content_after', [ __CLASS__, 'output_order_payment_pay_contents' ], 10 );
    }

    public static function register_payment_processor( array $payment_processors ): array {
      $payment_processors[ self::$processor_code ] = [
        'code'       => self::$processor_code,
        'name'       => __( 'Stebby', 'latepoint-addon-stebby' ),
        'front_name' => __( 'Stebby', 'latepoint-addon-stebby' ),
        'image_url'  => LatePointAddonStebby::images_url() . 'processor-stebby.svg',
      ];

      return $payment_processors;
    }

    public static function get_supported_payment_methods(): array {
      return [
        self::$processor_code => [
          'name'      => __( 'Stebby', 'latepoint-addon-stebby' ),
          'label'     => __( 'Stebby', 'latepoint-addon-stebby' ),
          'image_url' => LatePointAddonStebby::images_url() . 'processor-stebby.svg',
        ],
      ];
    }

    public static function add_all_payment_methods_to_payment_times( array $payment_times ): array {
      foreach ( self::get_supported_payment_methods() as $payment_method_code => $payment_method_info ) {
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

    public static function encrypted_settings( array $encrypted_settings ): array {
      $encrypted_settings[] = 'stebby_api_key';

      return $encrypted_settings;
    }


    /*
     * --------------------------------------------------------------------
     * Identification (redirect auth)
     * --------------------------------------------------------------------
     */

    /**
     * Starts the Identification flow: asks Stebby for an authorization URL,
     * stores the polling reference on the intent and returns the URL the
     * customer is redirected to.
     *
     * @param OsOrderIntentModel|OsTransactionIntentModel $intent
     */
    public static function start_identification( $intent, string $intent_type ): string {
      $key_param        = $intent_type === 'transaction_intent' ? 'transaction_intent_key' : 'order_intent_key';
      $success_redirect = OsRouterHelper::build_admin_post_link( [ 'stebby', 'token_return' ], [ $key_param => $intent->intent_key ] );
      $cancel_redirect  = add_query_arg( 'stebby_payment_error', '1', self::intent_form_url( $intent, $intent_type ) );

      $response = OsStebbyApiHelper::request_token( $success_redirect, $cancel_redirect );
      if ( ! $response || empty( $response['redirectUrl'] ) || empty( $response['reference'] ) ) {
        throw new Exception( OsStebbyApiHelper::get_error_message_from_response( $response ) );
      }

      self::store_payment_data( $intent, [
        self::PAYMENT_DATA_VALUE     => '',
        self::PAYMENT_DATA_REFERENCE => (string) $response['reference'],
      ] );

      return (string) $response['redirectUrl'];
    }

    /**
     * Exchanges the stored request-token reference for the customer's authorized
     * token and persists it as the client value. Returns '' if not yet usable.
     *
     * @param OsOrderIntentModel|OsTransactionIntentModel $intent
     */
    public static function exchange_reference_for_token( $intent ): string {
      $reference = (string) $intent->get_payment_data_value( self::PAYMENT_DATA_REFERENCE );
      if ( empty( $reference ) ) {
        return '';
      }

      $response = OsStebbyApiHelper::get_token( $reference );
      $token    = is_array( $response ) ? (string) ( $response['token'] ?? '' ) : '';

      if ( ! empty( $token ) ) {
        self::store_payment_data( $intent, [ self::PAYMENT_DATA_VALUE => $token ] );
      }

      return $token;
    }

    /**
     * @param OsOrderIntentModel|OsTransactionIntentModel $intent
     * @param array<string, mixed>                        $data
     */
    private static function store_payment_data( $intent, array $data ): void {
      $payment_data = json_decode( $intent->payment_data, true ) ?: [];
      $intent->update_attributes( [ 'payment_data' => wp_json_encode( array_merge( $payment_data, $data ) ) ] );
    }

    /**
     * @param OsOrderIntentModel|OsTransactionIntentModel $intent
     */
    private static function intent_form_url( $intent, string $intent_type ): string {
      $url = $intent_type === 'transaction_intent' ? ( $intent->order_form_page_url ?? '' ) : ( $intent->booking_form_page_url ?? '' );

      return ! empty( $url ) ? $url : home_url();
    }

    /**
     * Interim page shown after returning from Stebby that bounces the customer
     * back into the booking - either to continue the booking or to the form with
     * an error flag.
     */
    public static function render_redirect_page( string $url, bool $success ): void {
      nocache_headers();
      header( 'Content-Type: text/html; charset=utf-8' );

      $title       = $success ? __( 'Stebby confirmed', 'latepoint-addon-stebby' ) : __( 'Stebby was not completed', 'latepoint-addon-stebby' );
      $description = $success ? __( 'Returning to your booking...', 'latepoint-addon-stebby' ) : __( 'Returning to the booking form...', 'latepoint-addon-stebby' );

      $lang     = substr( get_locale(), 0, 2 );
      $url_attr = esc_url( $url );
      $url_json = wp_json_encode( $url );
      ?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr( $lang ); ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="refresh" content="3;url=<?php echo $url_attr; ?>">
<title><?php echo esc_html( $title ); ?></title>
</head>
<body style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;text-align:center;padding:48px 24px;color:#1c1f23;">
<h1 style="font-size:20px;margin:0 0 8px;"><?php echo esc_html( $title ); ?></h1>
<p style="color:#5a6068;font-size:14px;"><?php echo esc_html( $description ); ?></p>
<a href="<?php echo $url_attr; ?>"><?php esc_html_e( 'Click here if you are not redirected automatically.', 'latepoint-addon-stebby' ); ?></a>
<script>setTimeout(function(){ window.location.replace(<?php echo $url_json; ?>); }, 1500);</script>
</body>
</html>
      <?php
    }


    /*
     * --------------------------------------------------------------------
     * Charging (ticket redemption)
     * --------------------------------------------------------------------
     */

    /**
     * Redeems a Stebby ticket when an order intent is being converted to an order.
     *
     * @param array<string, mixed> $result
     *
     * @return array<string, mixed>
     */
    public static function process_payment( $result, OsOrderIntentModel $order_intent ) {
      if ( ! OsPaymentsHelper::should_processor_handle_payment_for_order_intent( self::$processor_code, $order_intent ) ) {
        return $result;
      }

      return self::process_intent_payment( $result, $order_intent );
    }

    /**
     * Redeems a Stebby ticket when a transaction intent (invoice payment) is
     * being converted to a transaction.
     *
     * @param array<string, mixed> $result
     *
     * @return array<string, mixed>
     */
    public static function process_payment_for_transaction_intent( $result, OsTransactionIntentModel $transaction_intent ) {
      if ( ! OsPaymentsHelper::should_processor_handle_payment_for_transaction_intent( self::$processor_code, $transaction_intent ) ) {
        return $result;
      }

      return self::process_intent_payment( $result, $transaction_intent );
    }

    /**
     * Shared redemption logic for both order intents and transaction intents.
     * Both model types expose get_payment_data_value(), charge_amount and
     * add_error() with the same signatures.
     *
     * @param array<string, mixed>                        $result
     * @param OsOrderIntentModel|OsTransactionIntentModel $intent
     *
     * @return array<string, mixed>
     */
    private static function process_intent_payment( $result, $intent ) {
      $max_amount = (float) $intent->charge_amount;

      try {
        $charge = self::charge_voucher( $intent->get_payment_data_value( self::PAYMENT_DATA_VALUE ), $max_amount );
      } catch ( Exception $e ) {
        $intent->add_error( 'send_to_step', $e->getMessage(), 'payment' );

        $result['status']  = LATEPOINT_STATUS_ERROR;
        $result['message'] = $e->getMessage();

        return $result;
      }

      // Ticket covered nothing - let the booking proceed with the full balance
      // due. No LatePoint transaction is recorded for a zero charge.
      if ( $charge['covered'] <= 0 ) {
        return $result;
      }

      // Record only what the ticket actually covered. The total stays untouched,
      // so any remainder shows as an outstanding balance.
      $intent->charge_amount = $charge['covered'];

      $result['status']    = LATEPOINT_STATUS_SUCCESS;
      $result['processor'] = self::$processor_code;
      $result['charge_id'] = $charge['charge_id'];
      $result['amount']    = $charge['covered'];
      $result['kind']      = LATEPOINT_TRANSACTION_KIND_CAPTURE;

      return $result;
    }

    /**
     * Redeems the client's first still-redeemable Stebby ticket - it is not tied
     * to a particular booked service.
     *
     * @return array{covered: float, charge_id: string}
     */
    private static function charge_voucher( string $token, float $max_amount ): array {
      if ( empty( $token ) ) {
        throw new Exception( esc_html__( 'Your Stebby identification could not be verified.', 'latepoint-addon-stebby' ) );
      }

      $tickets     = self::get_client_tickets( $token );
      $ticket      = $tickets[0] ?? [];
      $ticket_code = (string) ( $ticket['code'] ?? '' );
      if ( empty( $ticket_code ) ) {
        throw new Exception( esc_html__( 'No redeemable Stebby ticket was found on your account.', 'latepoint-addon-stebby' ) );
      }

      $use_response = OsStebbyApiHelper::use_ticket( $ticket_code );
      if ( $use_response === false ) {
        throw new Exception( esc_html__( 'The Stebby ticket could not be redeemed. It may already be used or expired.', 'latepoint-addon-stebby' ) );
      }

      $ticket_value = round( (float) ( $ticket['purchasable']['price'] ?? 0 ), 2 );

      return [
        'covered'   => round( min( $ticket_value, $max_amount ), 2 ),
        'charge_id' => 'stebby_ticket_' . $ticket_code,
      ];
    }

    /**
     * Lists the identified client's usable (non-expired, unclaimed) Stebby
     * tickets, using the token obtained from the Identification flow.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function get_client_tickets( string $token ): array {
      $response = OsStebbyApiHelper::get_tickets( [
        'client' => [ 'context' => OsStebbyApiHelper::get_client_context(), 'value' => $token ],
      ] );

      if ( ! $response || empty( $response['tickets'] ) ) {
        OsDebugHelper::log( 'Stebby returned no usable tickets for the identified client', 'stebby_voucher', [ 'response' => $response ] );

        return [];
      }

      return $response['tickets'];
    }


    /*
     * --------------------------------------------------------------------
     * Front-end payment content
     * --------------------------------------------------------------------
     */

    public static function output_payment_step_contents( OsCartModel $cart ): void {
      if ( ! OsPaymentsHelper::should_processor_handle_payment_for_cart( self::$processor_code, $cart ) ) {
        return;
      }

      self::render_stebby_content( self::CONTEXT_BOOKING );
    }

    public static function output_order_payment_pay_contents( OsTransactionIntentModel $transaction_intent ): void {
      if ( ! OsPaymentsHelper::should_processor_handle_payment_for_transaction_intent( self::$processor_code, $transaction_intent ) ) {
        return;
      }

      self::render_stebby_content( self::CONTEXT_TRANSACTION );
    }

    private static function render_stebby_content( string $context ): void {
      ?>
      <div class="lp-payment-method-content lp-stebby-method-content" data-payment-method="<?php echo esc_attr( self::$processor_code ); ?>" data-stebby-context="<?php echo esc_attr( $context ); ?>" style="display: none;">
        <div class="lp-payment-method-content-i">
          <div class="lp-stebby-redirect-notice">
            <span class="lp-stebby-redirect-notice-text"><?php esc_html_e( "You'll be redirected to Stebby to log in and confirm.", 'latepoint-addon-stebby' ); ?></span>
          </div>
          <a href="#" class="lp-stebby-manual-redirect" rel="nofollow" style="display:none;"><?php esc_html_e( 'If you are not redirected automatically, click here to continue to Stebby.', 'latepoint-addon-stebby' ); ?></a>
        </div>
      </div>
      <?php
    }


    /*
     * --------------------------------------------------------------------
     * Admin settings
     * --------------------------------------------------------------------
     */

    public static function add_settings_fields( $processor_code ) {
      if ( $processor_code != self::$processor_code ) {
        return false;
      }
      ?>
      <div class="sub-section-row">
        <div class="sub-section-label">
          <h3><?php esc_html_e( 'API Key', 'latepoint-addon-stebby' ); ?></h3>
        </div>
        <div class="sub-section-content">
          <div class="os-row os-mb-2">
            <div class="os-col-6">
              <?php echo OsFormHelper::password_field( 'settings[stebby_api_key]', __( 'API Key', 'latepoint-addon-stebby' ), OsSettingsHelper::get_settings_value( 'stebby_api_key', '' ) ); ?>
            </div>
          </div>
        </div>
      </div>
      <?php
    }

  }


endif;
