<?php
if ( ! defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly.
}


if ( ! class_exists( 'OsEverypayHelper' ) ) :


  class OsEverypayHelper {

    public static $processor_code = 'everypay';

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
        'name'       => __( 'EveryPay', 'latepoint-addon-everypay' ),
        'front_name' => __( 'EveryPay', 'latepoint-addon-everypay' ),
        'image_url'  => LatePointAddonEverypay::images_url() . 'processor-everypay.png',
      ];

      return $payment_processors;
    }

    public static function get_supported_payment_methods(): array {
      return [
        self::$processor_code => [
          'name'      => __( 'EveryPay', 'latepoint-addon-everypay' ),
          'label'     => __( 'Credit Card', 'latepoint-addon-everypay' ),
          'image_url' => LatePointAddonEverypay::images_url() . 'processor-everypay.png',
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
      $encrypted_settings[] = 'everypay_api_secret';
      $encrypted_settings[] = 'everypay_test_api_secret';

      return $encrypted_settings;
    }

    public static function process_payment( $result, OsOrderIntentModel $order_intent ) {
      if ( ! OsPaymentsHelper::should_processor_handle_payment_for_order_intent( self::$processor_code, $order_intent ) ) {
        return $result;
      }

      $payment_reference = $order_intent->get_payment_data_value( 'token' );
      $paid_amount       = $order_intent->get_payment_data_value( 'charge_amount' );
      $expected_amount   = ( $paid_amount === null || $paid_amount === '' ) ? $order_intent->charge_amount : $paid_amount;
      $verification      = self::verify_payment( $payment_reference, $expected_amount );

      if ( ! $verification['success'] ) {
        $order_intent->add_error( 'send_to_step', $verification['message'], 'payment' );

        $result['status']  = LATEPOINT_STATUS_ERROR;
        $result['message'] = $verification['message'];

        return $result;
      }

      $result['status']    = LATEPOINT_STATUS_SUCCESS;
      $result['processor'] = self::$processor_code;
      $result['charge_id'] = $payment_reference;
      $result['amount']    = $verification['amount'];
      $result['kind']      = $verification['kind'];

      return $result;
    }

    public static function process_payment_for_transaction_intent( $result, OsTransactionIntentModel $transaction_intent ) {
      if ( ! OsPaymentsHelper::should_processor_handle_payment_for_transaction_intent( self::$processor_code, $transaction_intent ) ) {
        return $result;
      }

      $payment_reference = $transaction_intent->get_payment_data_value( 'token' );
      $paid_amount       = $transaction_intent->get_payment_data_value( 'charge_amount' );
      $expected_amount   = ( $paid_amount === null || $paid_amount === '' ) ? $transaction_intent->charge_amount : $paid_amount;
      $verification      = self::verify_payment( $payment_reference, $expected_amount );

      if ( ! $verification['success'] ) {
        $transaction_intent->add_error( 'send_to_step', $verification['message'], 'payment' );

        $result['status']  = LATEPOINT_STATUS_ERROR;
        $result['message'] = $verification['message'];

        return $result;
      }

      $result['status']    = LATEPOINT_STATUS_SUCCESS;
      $result['processor'] = self::$processor_code;
      $result['charge_id'] = $payment_reference;
      $result['amount']    = $verification['amount'];
      $result['kind']      = $verification['kind'];

      return $result;
    }

    private static function verify_payment( $payment_reference, $charge_amount ): array {
      if ( empty( $payment_reference ) ) {
        OsDebugHelper::log( 'EveryPay payment verification failed: missing payment reference', 'everypay_payment_error' );

        return [ 'success' => false, 'message' => __( 'Payment could not be verified.', 'latepoint-addon-everypay' ) ];
      }

      $payment = OsEverypayApiHelper::get_payment_status( (string) $payment_reference );

      if ( empty( $payment['payment_state'] ) ) {
        OsDebugHelper::log( 'EveryPay payment verification failed: unable to retrieve payment', 'everypay_payment_error', [ 'payment_reference' => $payment_reference ] );

        return [ 'success' => false, 'message' => __( 'Unable to verify payment with EveryPay.', 'latepoint-addon-everypay' ) ];
      }

      $success_states = [
        'settled'    => LATEPOINT_TRANSACTION_KIND_CAPTURE,
        'authorised' => LATEPOINT_TRANSACTION_KIND_AUTHORIZATION,
      ];

      if ( ! isset( $success_states[ $payment['payment_state'] ] ) ) {
        OsDebugHelper::log( 'EveryPay payment is not in a successful state: ' . $payment['payment_state'], 'everypay_payment_error', [ 'payment_reference' => $payment_reference ] );

        return [ 'success' => false, 'message' => __( 'Payment was not completed.', 'latepoint-addon-everypay' ) ];
      }

      $expected_amount = number_format( (float) $charge_amount, 2, '.', '' );
      $returned_amount = number_format( (float) ( $payment['initial_amount'] ?? $payment['standing_amount'] ?? $payment['amount'] ?? 0 ), 2, '.', '' );

      if ( $expected_amount !== $returned_amount ) {
        OsDebugHelper::log( 'EveryPay payment amount mismatch', 'everypay_payment_error', [
          'payment_reference' => $payment_reference,
          'expected'          => $expected_amount,
          'returned'          => $returned_amount,
        ] );

        return [ 'success' => false, 'message' => __( 'Payment amount does not match the order total.', 'latepoint-addon-everypay' ) ];
      }

      $account_matches  = (string) ( $payment['account_name'] ?? '' ) === OsEverypayApiHelper::get_account_name();
      $username_matches = (string) ( $payment['api_username'] ?? '' ) === OsEverypayApiHelper::get_api_username();

      if ( ! $account_matches || ! $username_matches ) {
        OsDebugHelper::log( 'EveryPay payment account or credentials mismatch', 'everypay_payment_error', [ 'payment_reference' => $payment_reference ] );

        return [ 'success' => false, 'message' => __( 'Payment could not be verified for this account.', 'latepoint-addon-everypay' ) ];
      }

      return [
        'success' => true,
        'amount'  => $returned_amount,
        'kind'    => $success_states[ $payment['payment_state'] ],
      ];
    }


    public static function add_settings_fields( $processor_code ) {
      if ( $processor_code != self::$processor_code ) {
        return false;
      }

      $callback_url = OsRouterHelper::build_front_link( [ 'everypay', 'callback' ] );
      ?>
      <div class="sub-section-row">
        <div class="sub-section-label">
          <h3><?php esc_html_e( 'Live Credentials', 'latepoint-addon-everypay' ); ?></h3>
        </div>
        <div class="sub-section-content">
          <div class="os-row os-mb-2">
            <div class="os-col-6">
              <?php echo OsFormHelper::text_field( 'settings[everypay_api_username]', __( 'API Username', 'latepoint-addon-everypay' ), OsSettingsHelper::get_settings_value( 'everypay_api_username', '' ) ); ?>
            </div>
            <div class="os-col-6">
              <?php echo OsFormHelper::password_field( 'settings[everypay_api_secret]', __( 'API Secret', 'latepoint-addon-everypay' ), OsSettingsHelper::get_settings_value( 'everypay_api_secret', '' ) ); ?>
            </div>
          </div>
          <div class="os-row os-mb-2">
            <div class="os-col-6">
              <?php echo OsFormHelper::text_field( 'settings[everypay_account_name]', __( 'Processing Account Name', 'latepoint-addon-everypay' ), OsSettingsHelper::get_settings_value( 'everypay_account_name', '' ) ); ?>
            </div>
          </div>
        </div>
      </div>
      <div class="sub-section-row">
        <div class="sub-section-label">
          <h3><?php esc_html_e( 'Test Credentials', 'latepoint-addon-everypay' ); ?></h3>
        </div>
        <div class="sub-section-content">
          <div class="os-row os-mb-2">
            <div class="os-col-6">
              <?php echo OsFormHelper::text_field( 'settings[everypay_test_api_username]', __( 'Test API Username', 'latepoint-addon-everypay' ), OsSettingsHelper::get_settings_value( 'everypay_test_api_username', '' ) ); ?>
            </div>
            <div class="os-col-6">
              <?php echo OsFormHelper::password_field( 'settings[everypay_test_api_secret]', __( 'Test API Secret', 'latepoint-addon-everypay' ), OsSettingsHelper::get_settings_value( 'everypay_test_api_secret', '' ) ); ?>
            </div>
          </div>
          <div class="os-row os-mb-2">
            <div class="os-col-6">
              <?php echo OsFormHelper::text_field( 'settings[everypay_test_account_name]', __( 'Test Processing Account Name', 'latepoint-addon-everypay' ), OsSettingsHelper::get_settings_value( 'everypay_test_account_name', '' ) ); ?>
            </div>
          </div>
        </div>
      </div>
      <div class="sub-section-row">
        <div class="sub-section-label">
          <h3><?php esc_html_e( 'Callback Notification URL', 'latepoint-addon-everypay' ); ?></h3>
        </div>
        <div class="sub-section-content">
          <?php echo OsFormHelper::text_field( 'everypay_callback_url_display', __( 'Callback Notification URL', 'latepoint-addon-everypay' ), $callback_url, [ 'readonly' => 'readonly', 'onclick' => 'this.select();' ] ); ?>
          <p class="os-form-description"><?php esc_html_e( 'Provide this URL to EveryPay as the Callback / Notification URL for your processing account.', 'latepoint-addon-everypay' ); ?></p>
        </div>
      </div>
      <?php
    }

    public static function output_payment_step_contents( OsCartModel $cart ): void {
      if ( ! OsPaymentsHelper::should_processor_handle_payment_for_cart( self::$processor_code, $cart ) ) {
        return;
      }

      self::output_redirect_notice();
    }

    public static function output_order_payment_pay_contents( OsTransactionIntentModel $transaction_intent ): void {
      if ( ! OsPaymentsHelper::should_processor_handle_payment_for_transaction_intent( self::$processor_code, $transaction_intent ) ) {
        return;
      }

      self::output_redirect_notice();
    }

    private static function output_redirect_notice(): void {
      echo '<div class="lp-payment-method-content" data-payment-method="' . esc_attr( self::$processor_code ) . '">';
      echo '<div class="lp-payment-method-content-i">';
      echo '<div class="lp-everypay-redirect-notice">';
      echo '<span class="lp-everypay-redirect-notice-text">' . esc_html__( 'You\'ll be redirected to EveryPay to pay securely.', 'latepoint-addon-everypay' ) . '</span>';
      echo '</div>';
      echo '<a href="#" class="lp-everypay-manual-redirect" rel="nofollow" style="display:none;">' . esc_html__( 'If you are not redirected automatically, click here to continue to EveryPay.', 'latepoint-addon-everypay' ) . '</a>';
      echo '</div>';
      echo '</div>';
    }

  }

endif;
