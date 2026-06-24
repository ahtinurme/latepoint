<?php
if ( ! defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly.
}


if ( ! class_exists( 'OsStebbyHelper' ) ) :


  /**
   * Registers Stebby as a LatePoint payment processor and wires up both the
   * booking checkout (order intent) and the invoice payment (transaction
   * intent) flows.
   *
   * Two customer-facing flows are supported, selected on the payment step:
   *
   *  - "account"  - The customer pays from their Stebby balance. They identify
   *                 themselves through the Stebby redirect (Identification flow)
   *                 and Stebby is charged for whatever portion it can cover.
   *  - "voucher"  - The customer already purchased the service on Stebby. After
   *                 the same identification we list their tickets and redeem one.
   *
   * The amount Stebby covers is deducted from the total; any remainder is left
   * as a balance due on the order. If the order/transaction fails to be created
   * after Stebby was charged, the account-flow purchase is reverted on shutdown.
   */
  class OsStebbyHelper {

    public static $processor_code = 'stebby';

    const META_REFERENCE_CODE = 'stebby_reference_code';

    const PAYMENT_DATA_FLOW         = 'stebby_flow';
    const PAYMENT_DATA_CONTEXT      = 'stebby_context';
    const PAYMENT_DATA_VALUE        = 'stebby_value';
    const PAYMENT_DATA_TICKET_CODE  = 'stebby_ticket_code';
    const PAYMENT_DATA_REFERENCE    = 'stebby_reference';
    const PAYMENT_DATA_PURCHASABLES = 'stebby_purchasables';

    const FLOW_ACCOUNT = 'account';
    const FLOW_VOUCHER = 'voucher';

    const SETTING_ACCOUNT_FLOW = 'enable_stebby_account_flow';

    /**
     * Whether the "pay from my Stebby balance" flow is offered alongside ticket
     * redemption. Off by default until an admin turns it on in
     * Settings → Payments → Stebby. Both flows identify the customer through the
     * Stebby redirect; this only controls whether paying from balance is offered.
     */
    public static function is_account_flow_enabled(): bool {
      return OsSettingsHelper::get_settings_value( self::SETTING_ACCOUNT_FLOW, 'off' ) === 'on';
    }

    const CONTEXT_BOOKING     = 'booking';
    const CONTEXT_TRANSACTION = 'transaction';

    /**
     * Stebby charges made during this request that must be reverted if their
     * intent does not convert. Each entry: [type, id, flow, purchase_log_ids].
     *
     * @var array<int, array<string, mixed>>
     */
    private static $pending_reverts = [];

    private static $shutdown_registered = false;

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

      // Per-service Stebby reference code (service -> Stebby purchasable mapping).
      add_action( 'latepoint_service_form_after', [ __CLASS__, 'add_service_reference_code_field' ], 10 );
      add_filter( 'latepoint_service_edit_form_sticky_section_items', [ __CLASS__, 'add_service_reference_code_sticky_menu_item' ], 10 );
      add_action( 'latepoint_service_saved', [ __CLASS__, 'save_service_reference_code' ], 10, 3 );

      // Per-bundle (package) Stebby reference code. Requires the Pro Features addon.
      add_action( 'latepoint_bundle_form_after', [ __CLASS__, 'add_bundle_reference_code_field' ], 10 );
      add_action( 'latepoint_bundle_saved', [ __CLASS__, 'save_bundle_reference_code' ], 10, 3 );
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
      $encrypted_settings[] = 'stebby_test_api_key';

      return $encrypted_settings;
    }

    /*
     * --------------------------------------------------------------------
     * Service <-> Stebby purchasable mapping
     * --------------------------------------------------------------------
     */

    public static function get_service_reference_code( $service_id ): string {
      if ( empty( $service_id ) ) {
        return '';
      }

      $service = new OsServiceModel( $service_id );
      if ( $service->is_new_record() ) {
        return '';
      }

      return (string) $service->get_meta_by_key( self::META_REFERENCE_CODE, '' );
    }

    public static function add_service_reference_code_field( OsServiceModel $service ): void {
      $reference_code = $service->is_new_record() ? '' : $service->get_meta_by_key( self::META_REFERENCE_CODE, '' );
      ?>
      <div class="white-box section-anchor lp-stebby-service-mapping" id="stickySectionStebby">
        <div class="white-box-header">
          <div class="os-form-sub-header">
            <h3><?php esc_html_e( 'Stebby', 'latepoint-addon-stebby' ); ?></h3>
            <div class="os-form-sub-description"><?php esc_html_e( 'Map this service to a Stebby purchasable so it can be paid for with Stebby. Enter the reference code configured for the matching service in your Stebby Point of Sale.', 'latepoint-addon-stebby' ); ?></div>
          </div>
        </div>
        <div class="white-box-content">
          <?php echo OsFormHelper::text_field( 'service[' . self::META_REFERENCE_CODE . ']', __( 'Stebby Reference Code', 'latepoint-addon-stebby' ), $reference_code ); ?>
        </div>
      </div>
      <?php
    }

    /**
     * Adds the Stebby box to the service edit form's sticky side navigation.
     *
     * @param array<int, array{href: string, label: string}> $items
     *
     * @return array<int, array{href: string, label: string}>
     */
    public static function add_service_reference_code_sticky_menu_item( array $items ): array {
      $items[] = [ 'href' => 'stickySectionStebby', 'label' => __( 'Stebby', 'latepoint-addon-stebby' ) ];

      return $items;
    }

    /**
     * @param array<string, mixed> $service_params
     */
    public static function save_service_reference_code( OsServiceModel $service, $is_new_record, $service_params ): void {
      if ( ! is_array( $service_params ) || ! array_key_exists( self::META_REFERENCE_CODE, $service_params ) ) {
        return;
      }

      $service->save_meta_by_key( self::META_REFERENCE_CODE, sanitize_text_field( $service_params[ self::META_REFERENCE_CODE ] ) );
    }

    /**
     * @param OsBundleModel $bundle
     */
    public static function add_bundle_reference_code_field( $bundle ): void {
      $reference_code = ( $bundle && ! $bundle->is_new_record() ) ? $bundle->get_meta_by_key( self::META_REFERENCE_CODE, '' ) : '';
      ?>
      <div class="white-box lp-stebby-service-mapping">
        <div class="white-box-header">
          <div class="os-form-sub-header">
            <h3><?php esc_html_e( 'Stebby', 'latepoint-addon-stebby' ); ?></h3>
            <div class="os-form-sub-description"><?php esc_html_e( 'Map this package to a Stebby purchasable so it can be paid for or redeemed with Stebby. Enter the reference code configured for the matching package in your Stebby Point of Sale.', 'latepoint-addon-stebby' ); ?></div>
          </div>
        </div>
        <div class="white-box-content">
          <?php echo OsFormHelper::text_field( 'bundle[' . self::META_REFERENCE_CODE . ']', __( 'Stebby Reference Code', 'latepoint-addon-stebby' ), $reference_code ); ?>
        </div>
      </div>
      <?php
    }

    /**
     * @param OsBundleModel        $bundle
     * @param array<string, mixed> $bundle_params
     */
    public static function save_bundle_reference_code( $bundle, $is_new_record, $bundle_params ): void {
      if ( ! is_array( $bundle_params ) || ! array_key_exists( self::META_REFERENCE_CODE, $bundle_params ) ) {
        return;
      }

      $bundle->save_meta_by_key( self::META_REFERENCE_CODE, sanitize_text_field( $bundle_params[ self::META_REFERENCE_CODE ] ) );
    }


    /*
     * --------------------------------------------------------------------
     * Building purchasables
     * --------------------------------------------------------------------
     */

    /**
     * Builds a Stebby purchasable for a service, or null if the service has no
     * Stebby reference code configured.
     *
     * @return array{code: string, name: string, price: float, amount: int, service_id: int}|null
     */
    private static function purchasable_from_service( $service_id, float $price, string $fallback_name = '' ) {
      if ( empty( $service_id ) ) {
        return null;
      }

      $service = new OsServiceModel( $service_id );
      if ( $service->is_new_record() ) {
        return null;
      }

      $reference_code = (string) $service->get_meta_by_key( self::META_REFERENCE_CODE, '' );
      if ( empty( $reference_code ) ) {
        return null;
      }

      return [
        'code'       => $reference_code,
        'name'       => $service->name ?: $fallback_name,
        'price'      => round( $price, 2 ),
        'amount'     => 1,
        'service_id' => (int) $service_id,
      ];
    }

    /**
     * Builds a Stebby purchasable for a bundle (package), or null if the bundle
     * has no Stebby reference code configured.
     *
     * @param OsBundleModel $bundle
     *
     * @return array{code: string, name: string, price: float, amount: int, bundle_id: int}|null
     */
    private static function purchasable_from_bundle( $bundle, float $price ) {
      if ( empty( $bundle ) || empty( $bundle->id ) ) {
        return null;
      }

      $reference_code = (string) $bundle->get_meta_by_key( self::META_REFERENCE_CODE, '' );
      if ( empty( $reference_code ) ) {
        return null;
      }

      return [
        'code'      => $reference_code,
        'name'      => $bundle->name ?: '',
        'price'     => round( $price, 2 ),
        'amount'    => 1,
        'bundle_id' => (int) $bundle->id,
      ];
    }

    /**
     * Builds the Stebby purchasables for the services currently in the cart.
     * Services without a Stebby reference code are paid for outside Stebby.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function build_purchasables_from_cart( OsCartModel $cart ): array {
      $purchasables = [];

      foreach ( $cart->get_items() as $cart_item ) {
        $purchasable = null;

        if ( $cart_item->is_booking() ) {
          $booking = $cart_item->build_original_object_from_item_data();
          if ( ! empty( $booking ) ) {
            $purchasable = self::purchasable_from_service( $booking->service_id, (float) $cart_item->get_subtotal(), $cart_item->get_item_display_name() );
          }
        } elseif ( $cart_item->is_bundle() ) {
          $bundle = $cart_item->build_original_object_from_item_data();
          if ( ! empty( $bundle ) ) {
            $purchasable = self::purchasable_from_bundle( $bundle, (float) $cart_item->get_subtotal() );
          }
        }

        if ( $purchasable ) {
          $purchasables[] = $purchasable;
        }
      }

      return $purchasables;
    }

    /**
     * Builds the Stebby purchasables for the bookings on an order (used by the
     * invoice payment flow).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function build_purchasables_from_order( OsOrderModel $order ): array {
      $purchasables = [];

      foreach ( $order->get_items() as $order_item ) {
        $purchasable = null;

        if ( $order_item->is_booking() ) {
          $booking = $order_item->build_original_object_from_item_data();
          if ( ! empty( $booking ) ) {
            $purchasable = self::purchasable_from_service( $booking->service_id, (float) $order_item->get_subtotal() );
          }
        } elseif ( $order_item->is_bundle() ) {
          $bundle = $order_item->build_original_object_from_item_data();
          if ( ! empty( $bundle ) ) {
            $purchasable = self::purchasable_from_bundle( $bundle, (float) $order_item->get_subtotal() );
          }
        }

        if ( $purchasable ) {
          $purchasables[] = $purchasable;
        }
      }

      return $purchasables;
    }

    /**
     * Maps stored purchasables down to the payload Stebby's calculate endpoint
     * expects (drops internal keys).
     *
     * @param array<int, array<string, mixed>> $purchasables
     *
     * @return array<int, array<string, mixed>>
     */
    public static function purchasables_for_api( array $purchasables ): array {
      return array_map( function ( $purchasable ) {
        return [
          'code'   => $purchasable['code'],
          'name'   => $purchasable['name'] ?? '',
          'price'  => round( (float) ( $purchasable['price'] ?? 0 ), 2 ),
          'amount' => (int) ( $purchasable['amount'] ?? 1 ),
        ];
      }, array_values( $purchasables ) );
    }


    /*
     * --------------------------------------------------------------------
     * Payment processing
     * --------------------------------------------------------------------
     */

    /**
     * Starts the redirect-based Identification flow for an intent: asks Stebby
     * for an authorization URL, stores the polling reference and the chosen
     * sub-flow on the intent, and returns the URL the customer is redirected to.
     *
     * @param OsOrderIntentModel|OsTransactionIntentModel $intent
     * @param array<int, array<string, mixed>>            $purchasables
     */
    public static function start_identification( $intent, string $intent_type, string $flow, array $purchasables ): string {
      $key_param       = $intent_type === 'transaction_intent' ? 'transaction_intent_key' : 'order_intent_key';
      $success_redirect = OsRouterHelper::build_admin_post_link( [ 'stebby', 'token_return' ], [ $key_param => $intent->intent_key ] );
      $cancel_redirect  = add_query_arg( 'stebby_payment_error', '1', self::intent_form_url( $intent, $intent_type ) );

      $response = OsStebbyApiHelper::request_token( $success_redirect, $cancel_redirect );
      if ( ! $response || empty( $response['redirectUrl'] ) || empty( $response['reference'] ) ) {
        throw new Exception( OsStebbyApiHelper::get_error_message_from_response( $response ) );
      }

      self::store_payment_data( $intent, [
        self::PAYMENT_DATA_FLOW         => $flow,
        self::PAYMENT_DATA_CONTEXT      => OsStebbyApiHelper::get_client_context(),
        self::PAYMENT_DATA_VALUE        => '',
        self::PAYMENT_DATA_REFERENCE    => (string) $response['reference'],
        self::PAYMENT_DATA_PURCHASABLES => wp_json_encode( $purchasables ),
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

    /**
     * Charges Stebby when an order intent is being converted to an order.
     *
     * @param array<string, mixed> $result
     *
     * @return array<string, mixed>
     */
    public static function process_payment( $result, OsOrderIntentModel $order_intent ) {
      if ( ! OsPaymentsHelper::should_processor_handle_payment_for_order_intent( self::$processor_code, $order_intent ) ) {
        return $result;
      }

      return self::process_intent_payment( $result, $order_intent, 'order_intent', (int) $order_intent->id );
    }

    /**
     * Charges Stebby when a transaction intent (invoice payment) is being
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

      return self::process_intent_payment( $result, $transaction_intent, 'transaction_intent', (int) $transaction_intent->id );
    }

    /**
     * Shared charge logic for both order intents and transaction intents. Both
     * model types expose get_payment_data_value(), charge_amount and
     * add_error() with the same signatures.
     *
     * @param array<string, mixed>                           $result
     * @param OsOrderIntentModel|OsTransactionIntentModel    $intent
     *
     * @return array<string, mixed>
     */
    private static function process_intent_payment( $result, $intent, string $intent_type, int $intent_id ) {
      $flow         = $intent->get_payment_data_value( self::PAYMENT_DATA_FLOW );
      $purchasables = self::get_stored_purchasables( $intent );
      $max_amount   = (float) $intent->charge_amount;

      try {
        if ( $flow === self::FLOW_VOUCHER || ! self::is_account_flow_enabled() ) {
          $charge = self::charge_voucher( $intent->get_payment_data_value( self::PAYMENT_DATA_VALUE ), $purchasables, $max_amount );
        } else {
          $charge = self::charge_account(
            $intent->get_payment_data_value( self::PAYMENT_DATA_CONTEXT ),
            $intent->get_payment_data_value( self::PAYMENT_DATA_VALUE ),
            $purchasables,
            $max_amount
          );
        }
      } catch ( Exception $e ) {
        $intent->add_error( 'send_to_step', $e->getMessage(), 'payment' );

        $result['status']  = LATEPOINT_STATUS_ERROR;
        $result['message'] = $e->getMessage();

        return $result;
      }

      // A charge was made on Stebby. Schedule a revert in case this intent fails
      // to convert later in the request.
      self::register_pending_revert( $intent_type, $intent_id, $flow, $charge['purchase_log_ids'] ?? [] );

      // Stebby covered nothing - let the booking proceed with the full balance
      // due. No LatePoint transaction is recorded for a zero charge.
      if ( $charge['covered'] <= 0 ) {
        return $result;
      }

      // Record only what Stebby actually paid. The total stays untouched, so any
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
     * "Pay with my Stebby account" flow: re-calculate against a fresh purchase
     * reference and execute the purchase.
     *
     * @param array<int, array<string, mixed>> $purchasables
     *
     * @return array{covered: float, charge_id: string, purchase_log_ids: array<int, string>}
     */
    private static function charge_account( string $context, string $value, array $purchasables, float $max_amount ): array {
      if ( empty( $context ) || empty( $value ) ) {
        throw new Exception( esc_html__( 'Please verify your Stebby account before continuing.', 'latepoint-addon-stebby' ) );
      }

      if ( empty( $purchasables ) ) {
        throw new Exception( esc_html__( 'None of the booked services are available on Stebby.', 'latepoint-addon-stebby' ) );
      }

      $calculation = OsStebbyApiHelper::calculate( [ 'context' => $context, 'value' => $value ], self::purchasables_for_api( $purchasables ) );
      if ( ! $calculation || empty( $calculation['purchaseReferenceId'] ) ) {
        throw new Exception( OsStebbyApiHelper::get_error_message_from_response( $calculation ) );
      }

      $purchase = OsStebbyApiHelper::purchase( (int) $calculation['purchaseReferenceId'] );
      if ( ! $purchase || empty( $purchase['purchases'] ) ) {
        throw new Exception( OsStebbyApiHelper::get_error_message_from_response( $purchase ) );
      }

      $covered          = 0.0;
      $purchase_log_ids = [];
      foreach ( $purchase['purchases'] as $line ) {
        $covered += (float) ( $line['processed'] ?? 0 );
        if ( ! empty( $line['id'] ) ) {
          $purchase_log_ids[] = (string) $line['id'];
        }
      }

      $first_id = $purchase_log_ids[0] ?? (string) $calculation['purchaseReferenceId'];

      return [
        'covered'          => round( min( $covered, $max_amount ), 2 ),
        'charge_id'        => 'stebby_' . $first_id,
        'purchase_log_ids' => $purchase_log_ids,
      ];
    }

    /**
     * "I already bought it on Stebby" flow: validate the ticket then mark it as
     * used. Ticket usage cannot be reverted, so no revert handles are returned.
     *
     * @param array<int, array<string, mixed>> $purchasables
     *
     * @return array{covered: float, charge_id: string, purchase_log_ids: array<int, string>}
     */
    private static function charge_voucher( string $token, array $purchasables, float $max_amount ): array {
      if ( empty( $token ) ) {
        throw new Exception( esc_html__( 'Your Stebby identification could not be verified.', 'latepoint-addon-stebby' ) );
      }

      $tickets = self::get_client_tickets( $token );
      $ticket  = self::match_ticket( $tickets, $purchasables );
      if ( empty( $ticket ) ) {
        throw new Exception( esc_html__( 'No usable Stebby ticket was found for the booked services.', 'latepoint-addon-stebby' ) );
      }

      $ticket_code = (string) ( $ticket['code'] ?? '' );

      $use_response = OsStebbyApiHelper::use_ticket( $ticket_code );
      if ( $use_response === false ) {
        throw new Exception( esc_html__( 'The Stebby ticket could not be redeemed. It may already be used or expired.', 'latepoint-addon-stebby' ) );
      }

      if ( ! empty( $ticket['_stebby_unmatched'] ) ) {
        self::log_unmatched_redemption( $ticket_code, $ticket );
      }

      $ticket_value = self::usable_ticket_value( $ticket, $purchasables );

      return [
        'covered'          => round( min( $ticket_value, $max_amount ), 2 ),
        'charge_id'        => 'stebby_ticket_' . $ticket_code,
        'purchase_log_ids' => [],
      ];
    }

    /**
     * Records a redeemed Stebby voucher that could not be matched to a booked
     * service or a package, so staff can reconcile it manually.
     *
     * @param array<string, mixed> $ticket
     */
    private static function log_unmatched_redemption( string $ticket_code, array $ticket ): void {
      $details = [
        'ticket_code'      => $ticket_code,
        'purchasable_code' => (string) ( $ticket['purchasable']['code'] ?? '' ),
        'purchasable_name' => (string) ( $ticket['purchasable']['name'] ?? '' ),
        'price'            => (float) ( $ticket['purchasable']['price'] ?? 0 ),
      ];

      if ( class_exists( 'OsDebugHelper' ) ) {
        OsDebugHelper::log( 'Stebby voucher redeemed without a matching service or package - needs manual reconciliation', 'stebby_unmatched_redemption', $details );
      }
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
        OsDebugHelper::log( 'Stebby returned no usable tickets for the identified client', 'stebby_voucher', [
          'is_dev'   => OsSettingsHelper::is_env_payments_dev(),
          'response' => $response,
        ] );

        return [];
      }

      return $response['tickets'];
    }

    /**
     * Picks the ticket to redeem from the client's usable tickets: the one whose
     * purchasable matches a booked service by Stebby reference code or price. If
     * none match but the client has a single usable ticket, that one is used and
     * flagged for manual reconciliation.
     *
     * @param array<int, array<string, mixed>> $tickets
     * @param array<int, array<string, mixed>> $purchasables
     *
     * @return array<string, mixed>|null
     */
    public static function match_ticket( array $tickets, array $purchasables ) {
      if ( empty( $tickets ) ) {
        return null;
      }

      $allowed_codes  = array_column( $purchasables, 'code' );
      $allowed_prices = array_map( static function ( $purchasable ) {
        return round( (float) ( $purchasable['price'] ?? 0 ), 2 );
      }, $purchasables );

      foreach ( $tickets as $ticket ) {
        $purchasable_code = (string) ( $ticket['purchasable']['code'] ?? '' );
        if ( $purchasable_code !== '' && in_array( $purchasable_code, $allowed_codes, true ) ) {
          return $ticket;
        }

        $purchasable_price = round( (float) ( $ticket['purchasable']['price'] ?? 0 ), 2 );
        if ( in_array( $purchasable_price, $allowed_prices, true ) ) {
          return $ticket;
        }
      }

      // ponytail: no code/price match - redeem the only usable ticket and flag it
      // for manual reconciliation. Map service reference codes to disambiguate
      // when a client holds several unrelated tickets.
      if ( count( $tickets ) === 1 ) {
        $ticket                       = $tickets[0];
        $ticket['_stebby_unmatched'] = true;

        return $ticket;
      }

      return null;
    }

    /**
     * The amount a usable ticket covers - the ticket's purchasable price. The
     * caller caps this at the amount actually due.
     *
     * @param array<string, mixed>             $ticket
     * @param array<int, array<string, mixed>> $purchasables
     */
    public static function usable_ticket_value( array $ticket, array $purchasables ): float {
      return round( (float) ( $ticket['purchasable']['price'] ?? 0 ), 2 );
    }

    /**
     * @param OsOrderIntentModel|OsTransactionIntentModel $intent
     *
     * @return array<int, array<string, mixed>>
     */
    public static function get_stored_purchasables( $intent ): array {
      $stored = $intent->get_payment_data_value( self::PAYMENT_DATA_PURCHASABLES );
      if ( empty( $stored ) ) {
        return [];
      }

      $decoded = json_decode( $stored, true );

      return is_array( $decoded ) ? $decoded : [];
    }


    /*
     * --------------------------------------------------------------------
     * Revert safety net
     * --------------------------------------------------------------------
     */

    /**
     * @param array<int, string> $purchase_log_ids
     */
    private static function register_pending_revert( string $intent_type, int $intent_id, string $flow, array $purchase_log_ids ): void {
      self::$pending_reverts[] = [
        'type'             => $intent_type,
        'id'               => $intent_id,
        'flow'             => $flow,
        'purchase_log_ids' => $purchase_log_ids,
      ];

      if ( ! self::$shutdown_registered ) {
        add_action( 'shutdown', [ __CLASS__, 'revert_uncommitted_charges' ] );
        self::$shutdown_registered = true;
      }
    }

    /**
     * Reverts Stebby charges whose intent did not convert into an order or
     * transaction during this request. Tickets cannot be un-used, so those are
     * only logged for manual follow-up.
     */
    public static function revert_uncommitted_charges(): void {
      foreach ( self::$pending_reverts as $pending ) {
        if ( self::intent_converted( $pending['type'], (int) $pending['id'] ) ) {
          continue;
        }

        if ( $pending['flow'] === self::FLOW_VOUCHER ) {
          OsDebugHelper::log( 'Stebby voucher was used but the order was not created. Manual revert required.', 'stebby_revert', [ 'intent' => $pending ] );
          continue;
        }

        foreach ( $pending['purchase_log_ids'] as $purchase_log_id ) {
          OsStebbyApiHelper::revert_purchase( (int) $purchase_log_id );
        }

        OsDebugHelper::log( 'Reverted Stebby purchase because the order was not created.', 'stebby_revert', [ 'intent' => $pending ] );
      }

      self::$pending_reverts = [];
    }

    private static function intent_converted( string $intent_type, int $intent_id ): bool {
      if ( $intent_type === 'transaction_intent' ) {
        $intent = new OsTransactionIntentModel( $intent_id );

        return ! empty( $intent->transaction_id );
      }

      $intent = new OsOrderIntentModel( $intent_id );

      return ! empty( $intent->order_id );
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
      $account_flow_enabled = self::is_account_flow_enabled();
      ?>
      <div class="lp-payment-method-content lp-stebby-method-content" data-payment-method="<?php echo esc_attr( self::$processor_code ); ?>" data-stebby-context="<?php echo esc_attr( $context ); ?>" style="display: none;">
        <div class="lp-payment-method-content-i">
          <?php if ( $account_flow_enabled ) { ?>
            <div class="lp-stebby-flow-selector">
              <label class="lp-stebby-flow-option">
                <input type="radio" name="lp_stebby_flow" value="<?php echo esc_attr( self::FLOW_ACCOUNT ); ?>" checked>
                <span><?php esc_html_e( 'Pay with my Stebby account', 'latepoint-addon-stebby' ); ?></span>
              </label>
              <label class="lp-stebby-flow-option">
                <input type="radio" name="lp_stebby_flow" value="<?php echo esc_attr( self::FLOW_VOUCHER ); ?>">
                <span><?php esc_html_e( 'Redeem a service I already bought on Stebby', 'latepoint-addon-stebby' ); ?></span>
              </label>
            </div>
          <?php } else { ?>
            <input type="hidden" name="lp_stebby_flow" value="<?php echo esc_attr( self::FLOW_VOUCHER ); ?>">
          <?php } ?>

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
          <h3><?php esc_html_e( 'API Keys', 'latepoint-addon-stebby' ); ?></h3>
          <div class="os-form-sub-description"><?php esc_html_e( 'The sandbox key is used while LatePoint payments are in test mode, the live key otherwise.', 'latepoint-addon-stebby' ); ?></div>
        </div>
        <div class="sub-section-content">
          <div class="os-row os-mb-2">
            <div class="os-col-6">
              <?php echo OsFormHelper::password_field( 'settings[stebby_api_key]', __( 'Live API Key', 'latepoint-addon-stebby' ), OsSettingsHelper::get_settings_value( 'stebby_api_key', '' ) ); ?>
            </div>
            <div class="os-col-6">
              <?php echo OsFormHelper::password_field( 'settings[stebby_test_api_key]', __( 'Sandbox API Key', 'latepoint-addon-stebby' ), OsSettingsHelper::get_settings_value( 'stebby_test_api_key', '' ) ); ?>
            </div>
          </div>
        </div>
      </div>
      <div class="sub-section-row">
        <div class="sub-section-label">
          <h3><?php esc_html_e( 'Payment Flows', 'latepoint-addon-stebby' ); ?></h3>
          <div class="os-form-sub-description"><?php esc_html_e( 'Redeeming an already-bought Stebby ticket is always available. The account flow additionally lets customers pay from their Stebby balance.', 'latepoint-addon-stebby' ); ?></div>
        </div>
        <div class="sub-section-content">
          <?php echo OsFormHelper::toggler_field( 'settings[' . self::SETTING_ACCOUNT_FLOW . ']', __( 'Allow paying from a Stebby account balance', 'latepoint-addon-stebby' ), self::is_account_flow_enabled(), false, false, [ 'sub_label' => __( 'Both flows identify the customer through Stebby. Leave off to offer ticket redemption only.', 'latepoint-addon-stebby' ) ] ); ?>
        </div>
      </div>
      <?php
      self::output_services_helper();
    }

    /**
     * Renders the list of services configured on the Stebby Point of Sale so
     * the admin knows which reference codes to enter on each LatePoint service.
     */
    private static function output_services_helper(): void {
      if ( empty( OsStebbyApiHelper::get_api_key() ) ) {
        return;
      }

      $info = OsStebbyApiHelper::get_info();
      if ( ! $info || empty( $info['services'] ) ) {
        return;
      }
      ?>
      <div class="sub-section-row">
        <div class="sub-section-label">
          <h3><?php esc_html_e( 'Stebby Services', 'latepoint-addon-stebby' ); ?></h3>
          <div class="os-form-sub-description"><?php esc_html_e( 'Reference codes available on your Stebby Point of Sale. Enter the matching code on each LatePoint service.', 'latepoint-addon-stebby' ); ?></div>
        </div>
        <div class="sub-section-content">
          <table class="lp-stebby-services-table">
            <thead>
              <tr>
                <th><?php esc_html_e( 'Reference Code', 'latepoint-addon-stebby' ); ?></th>
                <th><?php esc_html_e( 'Service', 'latepoint-addon-stebby' ); ?></th>
                <th><?php esc_html_e( 'Price', 'latepoint-addon-stebby' ); ?></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ( $info['services'] as $service ) { ?>
                <tr>
                  <td><code><?php echo esc_html( $service['referenceCode'] ?? '' ); ?></code></td>
                  <td><?php echo esc_html( trim( ( $service['serviceGroupName'] ?? '' ) . ' ' . ( $service['serviceName'] ?? '' ) ) ); ?></td>
                  <td><?php echo esc_html( OsMoneyHelper::format_price( $service['currentPrice'] ?? 0 ) ); ?></td>
                </tr>
              <?php } ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php
    }

  }


endif;
