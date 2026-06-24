<?php
if ( ! defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly.
}


if ( ! class_exists( 'OsEverypayController' ) ) :


  class OsEverypayController extends OsController {


    function __construct(){
      parent::__construct();

      $this->action_access['public'] = array_merge( $this->action_access['public'], [
        'create_payment',
        'create_payment_for_transaction',
        'payment_return',
        'callback',
      ] );

      $this->views_folder = plugin_dir_path( __FILE__ ) . '../views/everypay/';
    }

    public function create_payment() {
      try {
        OsStepsHelper::set_required_objects( $this->params );

        $booking_form_page_url = $this->params['booking_form_page_url'] ?? OsUtilHelper::get_referrer();
        $order_intent          = OsOrderIntentHelper::create_or_update_order_intent( OsStepsHelper::$cart_object, OsStepsHelper::$restrictions, OsStepsHelper::$presets, $booking_form_page_url, OsStepsHelper::get_customer_object_id() );

        if ( ! $order_intent->is_bookable() ) {
          throw new Exception( empty( $order_intent->get_error_messages() ) ? __( 'Booking slot is not available anymore.', 'latepoint-addon-everypay' ) : implode( ', ', $order_intent->get_error_messages() ) );
        }

        $customer_url = OsRouterHelper::build_admin_post_link( [ 'everypay', 'payment_return' ], [ 'order_intent_key' => $order_intent->intent_key ] );
        $callback_url = OsRouterHelper::build_admin_post_link( [ 'everypay', 'callback' ] );

        $customer = $order_intent->get_customer();
        $payment  = OsEverypayApiHelper::create_oneoff_payment( [
          'amount'          => $order_intent->charge_amount,
          'order_reference' => $order_intent->intent_key,
          'email'           => $customer->email ?? '',
          'customer_ip'     => OsUtilHelper::get_user_ip(),
          'locale'          => substr( get_locale(), 0, 2 ),
          'customer_url'    => $customer_url,
          'callback_url'    => $callback_url,
        ] );

        if ( empty( $payment['payment_link'] ) || empty( $payment['payment_reference'] ) ) {
          throw new Exception( __( 'Unable to start EveryPay payment.', 'latepoint-addon-everypay' ) );
        }

        $payment_data                  = json_decode( $order_intent->payment_data, true ) ?: [];
        $payment_data['token']         = $payment['payment_reference'];
        $payment_data['charge_amount'] = (float) $order_intent->charge_amount;
        $order_intent->update_attributes( [ 'payment_data' => wp_json_encode( $payment_data ) ] );

        if ( $this->get_return_format() == 'json' ) {
          $this->send_json( [
            'status'           => LATEPOINT_STATUS_SUCCESS,
            'payment_link'     => $payment['payment_link'],
            'order_intent_key' => $order_intent->intent_key,
          ] );
        }
      } catch ( Exception $e ) {
        if ( $this->get_return_format() == 'json' ) {
          $this->send_json( [
            'status'  => LATEPOINT_STATUS_ERROR,
            'message' => $e->getMessage(),
          ] );
        }
      }
    }

    public function create_payment_for_transaction() {
      try {
        $invoice_access_key = sanitize_text_field( $this->params['key'] ?? '' );
        if ( empty( $invoice_access_key ) ) {
          throw new Exception( __( 'Invoice not found', 'latepoint-addon-everypay' ) );
        }

        $invoice = OsInvoicesHelper::get_invoice_by_key( $invoice_access_key );
        if ( ! ( $invoice instanceof OsInvoiceModel ) || $invoice->is_new_record() ) {
          throw new Exception( __( 'Invoice not found', 'latepoint-addon-everypay' ) );
        }

        $transaction_intent = OsTransactionIntentHelper::create_or_update_transaction_intent( $invoice, $this->params );

        $customer_url = OsRouterHelper::build_admin_post_link( [ 'everypay', 'payment_return' ], [ 'transaction_intent_key' => $transaction_intent->intent_key ] );
        $callback_url = OsRouterHelper::build_admin_post_link( [ 'everypay', 'callback' ] );

        $customer = $transaction_intent->get_customer();
        $payment  = OsEverypayApiHelper::create_oneoff_payment( [
          'amount'          => $transaction_intent->charge_amount,
          'order_reference' => $transaction_intent->intent_key,
          'email'           => $customer->email ?? '',
          'customer_ip'     => OsUtilHelper::get_user_ip(),
          'locale'          => substr( get_locale(), 0, 2 ),
          'customer_url'    => $customer_url,
          'callback_url'    => $callback_url,
        ] );

        if ( empty( $payment['payment_link'] ) || empty( $payment['payment_reference'] ) ) {
          throw new Exception( __( 'Unable to start EveryPay payment.', 'latepoint-addon-everypay' ) );
        }

        $payment_data                  = json_decode( $transaction_intent->payment_data, true ) ?: [];
        $payment_data['token']         = $payment['payment_reference'];
        $payment_data['charge_amount'] = (float) $transaction_intent->charge_amount;
        $transaction_intent->update_attributes( [ 'payment_data' => wp_json_encode( $payment_data ) ] );

        if ( $this->get_return_format() == 'json' ) {
          $this->send_json( [
            'status'                 => LATEPOINT_STATUS_SUCCESS,
            'payment_link'           => $payment['payment_link'],
            'transaction_intent_key' => $transaction_intent->intent_key,
          ] );
        }
      } catch ( Exception $e ) {
        if ( $this->get_return_format() == 'json' ) {
          $this->send_json( [
            'status'  => LATEPOINT_STATUS_ERROR,
            'message' => $e->getMessage(),
          ] );
        }
      }
    }

    public function payment_return() {
      $transaction_intent_key = sanitize_text_field( $this->params['transaction_intent_key'] ?? '' );

      if ( ! empty( $transaction_intent_key ) ) {
        $transaction_intent = OsTransactionIntentHelper::get_transaction_intent_by_intent_key( $transaction_intent_key );

        if ( ! $transaction_intent->is_new_record() && self::is_payment_successful( $transaction_intent->get_payment_data_value( 'token' ) ) ) {
          self::render_redirect_page( OsTransactionIntentHelper::generate_continue_intent_url( $transaction_intent->intent_key ), true );
          exit();
        }

        $error_url = ! empty( $transaction_intent->order_form_page_url ) ? $transaction_intent->order_form_page_url : home_url();
        self::render_redirect_page( add_query_arg( 'everypay_payment_error', '1', $error_url ), false );
        exit();
      }

      $order_intent_key = sanitize_text_field( $this->params['order_intent_key'] ?? '' );
      $order_intent     = OsOrderIntentHelper::get_order_intent_by_intent_key( $order_intent_key );

      if ( ! $order_intent->is_new_record() && self::is_payment_successful( $order_intent->get_payment_data_value( 'token' ) ) ) {
        self::render_redirect_page( OsOrderIntentHelper::generate_continue_intent_url( $order_intent->intent_key ), true );
        exit();
      }

      $error_url = ! empty( $order_intent->booking_form_page_url ) ? $order_intent->booking_form_page_url : home_url();
      self::render_redirect_page( add_query_arg( 'everypay_payment_error', '1', $error_url ), false );
      exit();
    }

    public function callback() {
      $order_reference   = sanitize_text_field( $this->params['order_reference'] ?? '' );
      $payment_reference = sanitize_text_field( $this->params['payment_reference'] ?? '' );

      if ( empty( $order_reference ) ) {
        OsDebugHelper::log( 'EveryPay callback received without an order reference', 'everypay_callback' );
        http_response_code( 400 );
        exit();
      }

      if ( ! self::is_payment_successful( $payment_reference ) ) {
        // Payment is not in a successful state - acknowledge the callback without converting.
        http_response_code( 200 );
        exit();
      }

      $order_intent = OsOrderIntentHelper::get_order_intent_by_intent_key( $order_reference );
      if ( ! $order_intent->is_new_record() ) {
        $order_intent->convert_to_order();
        http_response_code( 200 );
        exit();
      }

      $transaction_intent = OsTransactionIntentHelper::get_transaction_intent_by_intent_key( $order_reference );
      if ( ! $transaction_intent->is_new_record() ) {
        $transaction_intent->convert_to_transaction();
        http_response_code( 200 );
        exit();
      }

      OsDebugHelper::log( 'EveryPay callback could not match an intent', 'everypay_callback', [ 'order_reference' => $order_reference ] );
      http_response_code( 400 );
      exit();
    }

    private static function is_payment_successful( string $payment_reference ): bool {
      if ( empty( $payment_reference ) ) {
        return false;
      }

      $status = OsEverypayApiHelper::get_payment_status( $payment_reference );
      if ( empty( $status['payment_state'] ) ) {
        return false;
      }

      return in_array( $status['payment_state'], [ 'settled', 'authorized', 'authorised' ], true );
    }

    private static function render_redirect_page( string $url, bool $success ): void {
      nocache_headers();
      header( 'Content-Type: text/html; charset=utf-8' );

      $title        = $success
        ? __( 'Payment received', 'latepoint-addon-everypay' )
        : __( 'Payment was not completed', 'latepoint-addon-everypay' );
      $description  = $success
        ? __( 'Returning to your booking...', 'latepoint-addon-everypay' )
        : __( 'Returning to the booking form...', 'latepoint-addon-everypay' );
      $button_label = $success
        ? __( 'Continue to your booking', 'latepoint-addon-everypay' )
        : __( 'Return to the booking form', 'latepoint-addon-everypay' );

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
<style>
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background: #f6f6f6; margin: 0; padding: 0; color: #1c1f23; }
.lp-ep-w { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; box-sizing: border-box; }
.lp-ep-box { background: #fff; border-radius: 12px; padding: 32px 28px; max-width: 420px; width: 100%; text-align: center; box-shadow: 0 8px 24px rgba(0,0,0,0.06); }
.lp-ep-title { font-size: 20px; margin: 0 0 8px; font-weight: 600; }
.lp-ep-desc { color: #5a6068; margin: 0 0 24px; font-size: 14px; }
.lp-ep-btn { display: inline-block; background: #1c1f23; color: #fff; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 15px; }
.lp-ep-btn:hover { opacity: 0.9; }
.lp-ep-spinner { display: inline-block; width: 14px; height: 14px; border: 2px solid #d1d5db; border-top-color: #1c1f23; border-radius: 50%; animation: lp-ep-spin .8s linear infinite; vertical-align: middle; margin-right: 8px; }
@keyframes lp-ep-spin { to { transform: rotate(360deg); } }
</style>
</head>
<body>
<div class="lp-ep-w">
<div class="lp-ep-box">
<h1 class="lp-ep-title"><?php echo esc_html( $title ); ?></h1>
<p class="lp-ep-desc"><span class="lp-ep-spinner" aria-hidden="true"></span><?php echo esc_html( $description ); ?></p>
<a class="lp-ep-btn" href="<?php echo $url_attr; ?>"><?php echo esc_html( $button_label ); ?></a>
</div>
</div>
<script>setTimeout(function(){ window.location.replace(<?php echo $url_json; ?>); }, 1500);</script>
</body>
</html>
<?php
    }

  }

endif;
