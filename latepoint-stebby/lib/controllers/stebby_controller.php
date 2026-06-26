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
      ] );
    }

    /*
     * --------------------------------------------------------------------
     * v3 voucher-code redemption (no redirect)
     *
     * On "Confirm booking" the customer's voucher code is submitted with the form.
     * We create the order intent, store the code, and convert it - the charge
     * (useTicket) runs server-side during conversion. The front-end then continues
     * to the booking confirmation.
     * --------------------------------------------------------------------
     */

    public function request_token() {
      try {
        OsStepsHelper::set_required_objects( $this->params );
        $cart = OsStepsHelper::$cart_object;

        $voucher_code = $this->sanitize_voucher_code();

        $booking_form_page_url = $this->params['booking_form_page_url'] ?? OsUtilHelper::get_referrer();
        $order_intent          = OsOrderIntentHelper::create_or_update_order_intent( $cart, OsStepsHelper::$restrictions, OsStepsHelper::$presets, $booking_form_page_url, OsStepsHelper::get_customer_object_id() );

        if ( ! $order_intent->is_bookable() ) {
          throw new Exception( empty( $order_intent->get_error_messages() ) ? __( 'Booking slot is not available anymore.', 'latepoint-addon-stebby' ) : implode( ', ', $order_intent->get_error_messages() ) );
        }

        $order_intent->set_payment_data_value( OsStebbyHelper::PAYMENT_DATA_VOUCHER_CODE, $voucher_code );

        if ( ! $order_intent->convert_to_order() ) {
          throw new Exception( empty( $order_intent->get_error_messages() ) ? __( 'The Stebby ticket could not be redeemed.', 'latepoint-addon-stebby' ) : implode( ', ', $order_intent->get_error_messages() ) );
        }

        $this->send_json( [ 'status' => LATEPOINT_STATUS_SUCCESS, 'redirect_url' => OsOrderIntentHelper::generate_continue_intent_url( $order_intent->intent_key ) ] );
      } catch ( Exception $e ) {
        $this->send_json( [ 'status' => LATEPOINT_STATUS_ERROR, 'message' => $e->getMessage() ] );
      }
    }

    public function request_token_for_transaction() {
      try {
        $transaction_intent = $this->load_transaction_intent();
        $voucher_code       = $this->sanitize_voucher_code();

        $transaction_intent->set_payment_data_value( OsStebbyHelper::PAYMENT_DATA_VOUCHER_CODE, $voucher_code );

        if ( ! $transaction_intent->convert_to_transaction() ) {
          throw new Exception( empty( $transaction_intent->get_error_messages() ) ? __( 'The Stebby ticket could not be redeemed.', 'latepoint-addon-stebby' ) : implode( ', ', $transaction_intent->get_error_messages() ) );
        }

        $this->send_json( [ 'status' => LATEPOINT_STATUS_SUCCESS, 'redirect_url' => OsTransactionIntentHelper::generate_continue_intent_url( $transaction_intent->intent_key ) ] );
      } catch ( Exception $e ) {
        $this->send_json( [ 'status' => LATEPOINT_STATUS_ERROR, 'message' => $e->getMessage() ] );
      }
    }


    /*
     * --------------------------------------------------------------------
     * Shared helpers
     * --------------------------------------------------------------------
     */

    private function sanitize_voucher_code(): string {
      $code = OsStebbyHelper::normalize_voucher_code( (string) ( $this->params['stebby_voucher_code'] ?? '' ) );
      if ( ! OsStebbyHelper::is_valid_voucher_code( $code ) ) {
        throw new Exception( esc_html__( 'Please enter a valid Stebby voucher code.', 'latepoint-addon-stebby' ) );
      }

      return $code;
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

  }


endif;
