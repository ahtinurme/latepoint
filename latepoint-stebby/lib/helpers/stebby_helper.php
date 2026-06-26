<?php
if ( ! defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly.
}


if ( ! class_exists( 'OsStebbyHelper' ) ) :


  /**
   * Registers Stebby as a LatePoint payment processor for both the booking
   * checkout (order intent) and the invoice payment (transaction intent) flows.
   *
   * Uses the Stebby v3 API: the customer enters their Stebby voucher code on the
   * payment step and we redeem it directly (useTicket). The redeemed voucher
   * covers the booked session; any remainder is left as a balance due.
   */
  class OsStebbyHelper {

    public static $processor_code = 'stebby';

    const PAYMENT_DATA_VOUCHER_CODE = 'stebby_voucher_code';

    // ponytail: matches observed Stebby codes (SB/VV prefix + 10 alphanumerics).
    // The useTicket API is the real authority; this just rejects obvious garbage
    // before the call. Add a prefix here if Stebby ever issues a new one.
    const VOUCHER_CODE_PATTERN = '/^(SB|VV)[A-Z0-9]{10}$/';

    const CONTEXT_BOOKING     = 'booking';
    const CONTEXT_TRANSACTION = 'transaction';

    public static function normalize_voucher_code( string $code ): string {
      return strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', $code ) );
    }

    public static function is_valid_voucher_code( string $code ): bool {
      return (bool) preg_match( self::VOUCHER_CODE_PATTERN, $code );
    }

    public static function init_hooks(): void {
      add_filter( 'latepoint_payment_processors', [ __CLASS__, 'register_payment_processor' ] );
      add_filter( 'latepoint_get_all_payment_times', [ __CLASS__, 'add_all_payment_methods_to_payment_times' ] );
      add_filter( 'latepoint_get_enabled_payment_times', [ __CLASS__, 'add_enabled_payment_methods_to_payment_times' ] );
      add_filter( 'latepoint_encrypted_settings', [ __CLASS__, 'encrypted_settings' ] );

      add_filter( 'latepoint_process_payment_for_order_intent', [ __CLASS__, 'process_payment' ], 10, 2 );
      add_filter( 'latepoint_process_payment_for_transaction_intent', [ __CLASS__, 'process_payment_for_transaction_intent' ], 10, 2 );

      add_action( 'latepoint_order_created', [ __CLASS__, 'save_voucher_code_to_comments' ], 10 );

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
     * Redeems a Stebby ticket when a transaction intent (invoice payment) is being
     * converted to a transaction.
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
     * Shared redemption logic for both order intents and transaction intents. Both
     * model types expose get_payment_data_value(), charge_amount and add_error()
     * with the same signatures.
     *
     * @param array<string, mixed>                        $result
     * @param OsOrderIntentModel|OsTransactionIntentModel $intent
     *
     * @return array<string, mixed>
     */
    private static function process_intent_payment( $result, $intent ) {
      $max_amount = (float) $intent->charge_amount;

      try {
        $charge = self::charge_voucher( $intent, $max_amount );
      } catch ( Exception $e ) {
        $intent->add_error( 'send_to_step', $e->getMessage(), 'payment' );

        $result['status']  = LATEPOINT_STATUS_ERROR;
        $result['message'] = $e->getMessage();

        return $result;
      }

      if ( $charge['covered'] <= 0 ) {
        return $result;
      }

      // Record only what the ticket covered. The total stays untouched, so any
      // remainder shows as an outstanding balance.
      $intent->charge_amount = $charge['covered'];

      $result['status']    = LATEPOINT_STATUS_SUCCESS;
      $result['processor'] = self::$processor_code;
      $result['charge_id'] = $charge['charge_id'];
      $result['amount']    = $charge['covered'];
      $result['kind']      = LATEPOINT_TRANSACTION_KIND_CAPTURE;

      return $result;
    }

    /**
     * Redeems the Stebby voucher code the customer entered. A voucher pays for the
     * booked session, so it covers the amount due on this booking.
     *
     * @param OsOrderIntentModel|OsTransactionIntentModel $intent
     *
     * @return array{covered: float, charge_id: string}
     */
    private static function charge_voucher( $intent, float $max_amount ): array {
      // The voucher code is submitted with the booking form; fall back to any value
      // already stored on the intent.
      $code = self::normalize_voucher_code( (string) $intent->get_payment_data_value( self::PAYMENT_DATA_VOUCHER_CODE ) );
      if ( $code === '' && class_exists( 'OsParamsHelper' ) ) {
        $code = self::normalize_voucher_code( (string) OsParamsHelper::get_param( 'stebby_voucher_code' ) );
      }
      if ( ! self::is_valid_voucher_code( $code ) ) {
        throw new Exception( esc_html__( 'Please enter a valid Stebby voucher code.', 'latepoint-addon-stebby' ) );
      }

      $use_response = OsStebbyApiHelper::use_ticket( $code );
      if ( $use_response === false ) {
        throw new Exception( esc_html__( 'The Stebby voucher could not be redeemed. It may already be used or expired.', 'latepoint-addon-stebby' ) );
      }

      return [
        'covered'   => round( $max_amount, 2 ),
        'charge_id' => 'stebby_ticket_' . $code,
      ];
    }

    /**
     * Records the redeemed voucher code on the order and its bookings so it shows
     * up in the booking comments. The code was stored on the order intent's payment
     * data, which is copied to the order's initial_payment_data on conversion.
     */
    public static function save_voucher_code_to_comments( OsOrderModel $order ): void {
      $payment_data = json_decode( (string) $order->initial_payment_data, true );
      $code         = is_array( $payment_data ) ? (string) ( $payment_data[ self::PAYMENT_DATA_VOUCHER_CODE ] ?? '' ) : '';
      if ( $code === '' ) {
        return;
      }

      $note = sprintf( __( 'Stebby voucher: %s', 'latepoint-addon-stebby' ), $code );

      $order->customer_comment = trim( $order->customer_comment . "\n" . $note );
      $order->save();

      foreach ( $order->get_bookings_from_order_items( true ) as $booking ) {
        $booking->customer_comment = trim( $booking->customer_comment . "\n" . $note );
        $booking->save();
      }
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
          <div class="lp-stebby-voucher-w os-form-group">
            <label for="lp-stebby-voucher-<?php echo esc_attr( $context ); ?>"><?php esc_html_e( 'Enter your Stebby voucher code', 'latepoint-addon-stebby' ); ?></label>
            <input type="text" id="lp-stebby-voucher-<?php echo esc_attr( $context ); ?>" class="lp-stebby-voucher-input" name="stebby_voucher_code" autocomplete="off" autocapitalize="characters" placeholder="<?php esc_attr_e( 'Stebby voucher code', 'latepoint-addon-stebby' ); ?>">
          </div>
          <div class="lp-stebby-error" style="display:none; color:#c0394b; margin-bottom:10px;"></div>
          <a href="#" class="latepoint-btn latepoint-btn-primary lp-stebby-pay-btn"><?php esc_html_e( 'Pay with Stebby voucher', 'latepoint-addon-stebby' ); ?></a>
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
