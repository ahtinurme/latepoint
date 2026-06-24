<?php
if ( ! defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly.
}


if ( ! class_exists( 'OsStebbyController' ) ) :


  class OsStebbyController extends OsController {


    function __construct() {
      parent::__construct();

      $this->action_access['public'] = array_merge( $this->action_access['public'], [
        'request_token',
        'request_token_for_transaction',
        'token_return',
      ] );

      $this->views_folder = plugin_dir_path( __FILE__ ) . '../views/stebby/';
    }


    /*
     * --------------------------------------------------------------------
     * Identification (redirect) flow
     *
     * Stebby is a redirect processor: on "Confirm booking" we create the
     * intent, ask Stebby for an authorization URL and send the customer there.
     * They log in and authorize a token; Stebby returns them to token_return,
     * where we exchange the reference for the token and convert the intent. The
     * actual charge (calculate+purchase for the account flow, ticket lookup+use
     * for the voucher flow) runs server-side during that conversion.
     * --------------------------------------------------------------------
     */

    public function request_token() {
      try {
        OsStepsHelper::set_required_objects( $this->params );
        $cart = OsStepsHelper::$cart_object;

        $flow = $this->sanitize_flow( $this->params['lp_stebby_flow'] ?? '' );

        $booking_form_page_url = $this->params['booking_form_page_url'] ?? OsUtilHelper::get_referrer();
        $order_intent          = OsOrderIntentHelper::create_or_update_order_intent( $cart, OsStepsHelper::$restrictions, OsStepsHelper::$presets, $booking_form_page_url, OsStepsHelper::get_customer_object_id() );

        if ( ! $order_intent->is_bookable() ) {
          throw new Exception( empty( $order_intent->get_error_messages() ) ? __( 'Booking slot is not available anymore.', 'latepoint-addon-stebby' ) : implode( ', ', $order_intent->get_error_messages() ) );
        }

        $purchasables = OsStebbyHelper::build_purchasables_from_cart( $cart );
        $redirect_url = OsStebbyHelper::start_identification( $order_intent, 'order_intent', $flow, $purchasables );

        $this->send_json( [ 'status' => LATEPOINT_STATUS_SUCCESS, 'redirect_url' => $redirect_url ] );
      } catch ( Exception $e ) {
        $this->send_json( [ 'status' => LATEPOINT_STATUS_ERROR, 'message' => $e->getMessage() ] );
      }
    }

    public function request_token_for_transaction() {
      try {
        $transaction_intent = $this->load_transaction_intent();
        $purchasables       = $this->purchasables_for_transaction_intent( $transaction_intent );

        $flow         = $this->sanitize_flow( $this->params['lp_stebby_flow'] ?? '' );
        $redirect_url = OsStebbyHelper::start_identification( $transaction_intent, 'transaction_intent', $flow, $purchasables );

        $this->send_json( [ 'status' => LATEPOINT_STATUS_SUCCESS, 'redirect_url' => $redirect_url ] );
      } catch ( Exception $e ) {
        $this->send_json( [ 'status' => LATEPOINT_STATUS_ERROR, 'message' => $e->getMessage() ] );
      }
    }

    /**
     * Stebby returns the customer here after they authorize. We exchange the
     * stored reference for the token, convert the intent (which charges Stebby)
     * and bounce them back into the booking.
     */
    public function token_return() {
      $transaction_intent_key = sanitize_text_field( $this->params['transaction_intent_key'] ?? '' );
      if ( ! empty( $transaction_intent_key ) ) {
        $this->finalize_identification( OsTransactionIntentHelper::get_transaction_intent_by_intent_key( $transaction_intent_key ), 'transaction_intent' );

        return;
      }

      $order_intent_key = sanitize_text_field( $this->params['order_intent_key'] ?? '' );
      $this->finalize_identification( OsOrderIntentHelper::get_order_intent_by_intent_key( $order_intent_key ), 'order_intent' );
    }

    /**
     * @param OsOrderIntentModel|OsTransactionIntentModel $intent
     */
    private function finalize_identification( $intent, string $intent_type ): void {
      $form_url  = $intent_type === 'transaction_intent' ? ( $intent->order_form_page_url ?? '' ) : ( $intent->booking_form_page_url ?? '' );
      $error_url = add_query_arg( 'stebby_payment_error', '1', ! empty( $form_url ) ? $form_url : home_url() );

      if ( $intent->is_new_record() || empty( OsStebbyHelper::exchange_reference_for_token( $intent ) ) ) {
        OsStebbyHelper::render_redirect_page( $error_url, false );
        exit();
      }

      $converted = $intent_type === 'transaction_intent' ? $intent->convert_to_transaction() : $intent->convert_to_order();
      if ( ! $converted ) {
        OsStebbyHelper::render_redirect_page( $error_url, false );
        exit();
      }

      $continue_url = $intent_type === 'transaction_intent'
        ? OsTransactionIntentHelper::generate_continue_intent_url( $intent->intent_key )
        : OsOrderIntentHelper::generate_continue_intent_url( $intent->intent_key );

      OsStebbyHelper::render_redirect_page( $continue_url, true );
      exit();
    }


    /*
     * --------------------------------------------------------------------
     * Shared helpers
     * --------------------------------------------------------------------
     */

    private function sanitize_flow( string $flow ): string {
      $flow = sanitize_text_field( $flow );

      if ( $flow === OsStebbyHelper::FLOW_ACCOUNT ) {
        if ( ! OsStebbyHelper::is_account_flow_enabled() ) {
          throw new Exception( esc_html__( 'Paying from a Stebby balance is currently unavailable. Please redeem a ticket instead.', 'latepoint-addon-stebby' ) );
        }

        return OsStebbyHelper::FLOW_ACCOUNT;
      }

      return OsStebbyHelper::FLOW_VOUCHER;
    }

    private function load_transaction_intent(): OsTransactionIntentModel {
      $invoice_access_key = sanitize_text_field( $this->params['key'] ?? '' );
      if ( empty( $invoice_access_key ) ) {
        throw new Exception( __( 'Invoice not found.', 'latepoint-addon-stebby' ) );
      }

      $invoice = OsInvoicesHelper::get_invoice_by_key( $invoice_access_key );
      if ( ! ( $invoice instanceof OsInvoiceModel ) || $invoice->is_new_record() ) {
        throw new Exception( __( 'Invoice not found.', 'latepoint-addon-stebby' ) );
      }

      return OsTransactionIntentHelper::create_or_update_transaction_intent( $invoice, $this->params );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function purchasables_for_transaction_intent( OsTransactionIntentModel $transaction_intent ): array {
      $order = new OsOrderModel( $transaction_intent->order_id );
      if ( $order->is_new_record() ) {
        return [];
      }

      return OsStebbyHelper::build_purchasables_from_order( $order );
    }

  }


endif;
