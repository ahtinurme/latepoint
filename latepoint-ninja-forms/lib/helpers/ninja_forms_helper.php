<?php
if ( ! defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly.
}

if ( ! class_exists( 'OsNinjaFormsHelper' ) ) :

/**
 * All Ninja Forms <-> LatePoint glue lives here. The main plugin class only wires hooks
 * to these static methods so the logic stays in one place and is unit-testable.
 *
 * Storage model: a submission is saved as JSON on BOTH the order meta (so the admin order
 * view and a single-order lookup are cheap) and appended to the customer meta (so the
 * customer dashboard can list everything without a join across orders).
 */
class OsNinjaFormsHelper {

  const ORDER_META_KEY        = 'ninja_form_submissions';
  const CUSTOMER_META_KEY     = 'ninja_form_submissions';
  const SERVICE_META_ENABLED  = 'ninja_form_enabled';

  // Ninja Forms field keys the site admin adds to their form as Hidden fields. We populate
  // them automatically when the form is rendered through our shortcode, and read them back
  // on submission to link the entry to a LatePoint order.
  const HIDDEN_ORDER_KEY = 'lp_nf_order';
  const HIDDEN_TOKEN_KEY = 'lp_nf_token';

  // Set while our shortcode renders a form, consumed by populate_hidden_fields().
  protected static ?int    $current_order_id = null;
  protected static ?string $current_token    = null;

  /* ===========================================================================
   * Global settings
   * ========================================================================= */

  /** The single Ninja Form used for all services, or null if not configured. */
  public static function get_form_id(): ?int {
    $form_id = OsSettingsHelper::get_settings_value( 'ninja_forms_form_id', '' );
    return ( is_numeric( $form_id ) && (int) $form_id > 0 ) ? (int) $form_id : null;
  }

  public static function get_form_page_id(): int {
    return (int) OsSettingsHelper::get_settings_value( 'ninja_forms_page_id', 0 );
  }

  public static function auto_append_email_enabled(): bool {
    return OsSettingsHelper::get_settings_value( 'ninja_forms_auto_append_email', LATEPOINT_VALUE_ON ) == LATEPOINT_VALUE_ON;
  }

  public static function get_admin_email(): string {
    $email = OsSettingsHelper::get_settings_value( 'ninja_forms_admin_email', '' );
    return $email ? $email : get_bloginfo( 'admin_email' );
  }

  public static function notify_customer_enabled(): bool {
    return OsSettingsHelper::get_settings_value( 'ninja_forms_notify_customer', LATEPOINT_VALUE_ON ) == LATEPOINT_VALUE_ON;
  }

  public static function notify_admin_enabled(): bool {
    return OsSettingsHelper::get_settings_value( 'ninja_forms_notify_admin', LATEPOINT_VALUE_ON ) == LATEPOINT_VALUE_ON;
  }

  /* ===========================================================================
   * Per-service link toggle — the form is shared, but whether booking a given
   * service delivers the form link (email + confirmation) is configurable per
   * service. Defaults to enabled when never set.
   * ========================================================================= */

  public static function service_link_enabled( $service_id ): bool {
    if ( empty( $service_id ) ) {
      return false;
    }
    return OsMetaHelper::get_service_meta_by_key( self::SERVICE_META_ENABLED, $service_id, LATEPOINT_VALUE_ON ) == LATEPOINT_VALUE_ON;
  }

  public static function save_service_link_enabled( $service_id, $enabled ): void {
    if ( empty( $service_id ) ) {
      return;
    }
    $value = ( $enabled == LATEPOINT_VALUE_ON ) ? LATEPOINT_VALUE_ON : 'off';
    OsMetaHelper::save_service_meta_by_key( self::SERVICE_META_ENABLED, $value, $service_id );
  }

  public static function order_has_enabled_service( $order ): bool {
    if ( ! $order || empty( $order->id ) ) {
      return false;
    }
    foreach ( $order->get_bookings_from_order_items() as $booking ) {
      if ( self::service_link_enabled( $booking->service_id ) ) {
        return true;
      }
    }
    return false;
  }

  public static function service_form_field_html( $service ): string {
    $enabled = ! $service || empty( $service->id ) ? true : self::service_link_enabled( $service->id );

    $html  = '<div class="white-box-section"><div class="white-box-header"><div class="os-form-sub-header"><h3>' . esc_html__( 'Ninja Forms', 'latepoint-ninja-forms' ) . '</h3></div></div>';
    $html .= '<div class="white-box-content">';
    $html .= OsFormHelper::toggler_field(
      'service[' . self::SERVICE_META_ENABLED . ']',
      __( 'Send Ninja Form link for this service', 'latepoint-ninja-forms' ),
      $enabled,
      false,
      false,
      [ 'sub_label' => __( 'When on, booking this service delivers the form link in the confirmation email and on screen.', 'latepoint-ninja-forms' ) ]
    );
    $html .= '</div></div>';
    return $html;
  }

  /* ===========================================================================
   * Token + URL building
   * ========================================================================= */

  public static function token_for_order( $order_id ): string {
    return substr( hash_hmac( 'sha256', 'lp_nf_' . (int) $order_id, wp_salt( 'auth' ) ), 0, 20 );
  }

  public static function verify_token( $order_id, $token ): bool {
    return ! empty( $token ) && hash_equals( self::token_for_order( $order_id ), (string) $token );
  }

  /**
   * The Ninja Form for an order. One global form is shared by all services, but it is only
   * delivered when at least one booked service has the link enabled (per-service toggle).
   */
  public static function form_id_for_order( $order ): ?int {
    if ( ! $order || empty( $order->id ) ) {
      return null;
    }
    if ( ! self::order_has_enabled_service( $order ) ) {
      return null;
    }
    return self::get_form_id();
  }

  public static function has_form( $order ): bool {
    return self::form_id_for_order( $order ) !== null;
  }

  public static function form_url_for_order( $order ): string {
    if ( ! self::has_form( $order ) ) {
      return '';
    }
    $page_id = self::get_form_page_id();
    if ( ! $page_id ) {
      return '';
    }
    return add_query_arg(
      [
        self::HIDDEN_ORDER_KEY => (int) $order->id,
        self::HIDDEN_TOKEN_KEY => self::token_for_order( $order->id ),
      ],
      get_permalink( $page_id )
    );
  }

  /* ===========================================================================
   * Requirement 1 — deliver the link
   * ========================================================================= */

  public static function replace_order_vars( string $text, $order ): string {
    if ( strpos( $text, '{{order_ninja_form_' ) === false ) {
      return $text;
    }
    $url = self::form_url_for_order( $order );
    $link = $url ? '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Open form', 'latepoint-ninja-forms' ) . '</a>' : '';
    return str_replace(
      [ '{{order_ninja_form_url}}', '{{order_ninja_form_link}}' ],
      [ esc_url( $url ), $link ],
      $text
    );
  }

  public static function confirmation_link_html( $order ): string {
    $url = self::form_url_for_order( $order );
    if ( ! $url ) {
      return '';
    }
    $html  = '<div class="latepoint-ninja-form-cta">';
    $html .= '<p>' . esc_html__( 'Please complete the form for your booking:', 'latepoint-ninja-forms' ) . '</p>';
    $html .= '<a class="latepoint-btn latepoint-btn-primary" href="' . esc_url( $url ) . '">' . esc_html__( 'Open form', 'latepoint-ninja-forms' ) . '</a>';
    $html .= '</div>';
    return $html;
  }

  /**
   * Auto-append the form link to the customer's notification email when a form exists.
   *
   * Hooked on `latepoint_process_prepare_data_for_run` (same hook the Mailchimp addon uses),
   * which fires after the email content + recipient have been resolved but before send. We
   * only touch send_email actions addressed to the customer, and skip when the link is already
   * in the content (so it never duplicates a manually-placed {{order_ninja_form_link}}).
   *
   * @param \LatePoint\Misc\ProcessAction $action
   * @return \LatePoint\Misc\ProcessAction
   */
  public static function maybe_append_link_to_email( $action ) {
    if ( ! self::auto_append_email_enabled() || $action->type !== 'send_email' ) {
      return $action;
    }

    $order = $action->replacement_vars['order'] ?? null;
    if ( ! ( $order instanceof OsOrderModel ) ) {
      return $action;
    }

    $customer = $action->replacement_vars['customer'] ?? null;
    $to       = $action->prepared_data_for_run['to'] ?? '';
    if ( ! $customer || empty( $customer->email ) || stripos( $to, $customer->email ) === false ) {
      return $action;
    }

    $url = self::form_url_for_order( $order );
    if ( ! $url ) {
      return $action;
    }

    $content = $action->prepared_data_for_run['content'] ?? '';
    if ( strpos( $content, $url ) !== false ) {
      return $action;
    }

    $action->prepared_data_for_run['content'] = $content . self::email_link_block( $url );
    return $action;
  }

  protected static function email_link_block( string $url ): string {
    $html  = '<div class="latepoint-ninja-form-email-cta" style="margin:20px 0;">';
    $html .= '<p>' . esc_html__( 'Please complete the form for your booking:', 'latepoint-ninja-forms' ) . '</p>';
    $html .= '<p><a href="' . esc_url( $url ) . '">' . esc_html__( 'Open form', 'latepoint-ninja-forms' ) . '</a></p>';
    $html .= '</div>';
    return $html;
  }

  /* ===========================================================================
   * Requirement 4 — display stored submissions
   * ========================================================================= */

  /** @return array<int, array<string, mixed>> */
  public static function get_order_submissions( $order_id ): array {
    $raw = OsMetaHelper::get_order_meta_by_key( self::ORDER_META_KEY, $order_id, '' );
    $decoded = $raw ? json_decode( $raw, true ) : [];
    return is_array( $decoded ) ? $decoded : [];
  }

  /** @return array<int, array<string, mixed>> */
  public static function get_customer_submissions( $customer_id ): array {
    $raw = OsMetaHelper::get_customer_meta_by_key( self::CUSTOMER_META_KEY, $customer_id, '' );
    $decoded = $raw ? json_decode( $raw, true ) : [];
    return is_array( $decoded ) ? $decoded : [];
  }

  public static function customer_submissions_html( $customer ): string {
    if ( ! $customer || empty( $customer->id ) ) {
      return '';
    }
    $submissions = self::get_customer_submissions( $customer->id );
    if ( ! $submissions ) {
      return '';
    }
    $html = '<div class="latepoint-ninja-forms-dashboard-section"><h3>' . esc_html__( 'Your Form Submissions', 'latepoint-ninja-forms' ) . '</h3>';
    foreach ( $submissions as $submission ) {
      $html .= self::render_submission( $submission );
    }
    $html .= '</div>';
    return $html;
  }

  public static function admin_order_submission_html( $order ): string {
    if ( ! $order || empty( $order->id ) ) {
      return '';
    }
    $submissions = self::get_order_submissions( $order->id );
    if ( ! $submissions ) {
      return '';
    }
    $html = '<div class="white-box-section latepoint-ninja-forms-admin-section"><div class="white-box-header"><div class="os-form-sub-header"><h3>' . esc_html__( 'Ninja Form Submissions', 'latepoint-ninja-forms' ) . '</h3></div></div><div class="white-box-content">';
    foreach ( $submissions as $submission ) {
      $html .= self::render_submission( $submission );
    }
    $html .= '</div></div>';
    return $html;
  }

  /** @param array<string, mixed> $submission */
  protected static function render_submission( array $submission ): string {
    $title = ! empty( $submission['form_title'] ) ? $submission['form_title'] : __( 'Form', 'latepoint-ninja-forms' );
    $when  = ! empty( $submission['submitted_at'] ) ? $submission['submitted_at'] : '';

    $html  = '<div class="latepoint-ninja-form-submission">';
    $html .= '<div class="latepoint-ninja-form-submission-head"><strong>' . esc_html( $title ) . '</strong>';
    if ( $when ) {
      $html .= ' <span class="latepoint-ninja-form-submission-date">' . esc_html( $when ) . '</span>';
    }
    $html .= '</div>';
    $html .= '<table class="latepoint-ninja-form-submission-table">';
    foreach ( ( $submission['fields'] ?? [] ) as $field ) {
      $label = isset( $field['label'] ) ? $field['label'] : '';
      $value = isset( $field['value'] ) ? $field['value'] : '';
      $html .= '<tr><th>' . esc_html( $label ) . '</th><td>' . self::render_value( (string) $value ) . '</td></tr>';
    }
    $html .= '</table></div>';
    return $html;
  }

  /** Render image URLs (e.g. an attached scan) as thumbnails, everything else as escaped text. */
  protected static function render_value( string $value ): string {
    $out = [];
    foreach ( preg_split( '/\r\n|\r|\n/', $value ) as $line ) {
      $trimmed = trim( $line );
      if ( $trimmed !== '' && preg_match( '#^https?://\S+\.(jpe?g|png|gif|webp)$#i', $trimmed ) ) {
        $out[] = '<a href="' . esc_url( $trimmed ) . '" target="_blank" rel="noopener"><img src="' . esc_url( $trimmed ) . '" alt="" style="max-width:220px;height:auto;margin:4px 0;border:1px solid #ddd;" /></a>';
      } else {
        $out[] = nl2br( esc_html( $line ) );
      }
    }
    return implode( '<br>', $out );
  }

  /* ===========================================================================
   * Requirements 2 & 3 — Ninja Forms bridge
   * ========================================================================= */

  /**
   * Shortcode [latepoint_ninja_form]. Lives on the page the admin selects in settings.
   * Validates the order token from the URL, resolves the configured form for that order's
   * service and renders it. Hidden order/token fields are auto-filled via populate_hidden_fields().
   */
  public static function render_form_shortcode( $atts ): string {
    if ( ! shortcode_exists( 'ninja_form' ) ) {
      return '<p>' . esc_html__( 'Ninja Forms is not active.', 'latepoint-ninja-forms' ) . '</p>';
    }

    $order_id = isset( $_GET[ self::HIDDEN_ORDER_KEY ] ) ? (int) $_GET[ self::HIDDEN_ORDER_KEY ] : 0;
    $token    = isset( $_GET[ self::HIDDEN_TOKEN_KEY ] ) ? sanitize_text_field( wp_unslash( $_GET[ self::HIDDEN_TOKEN_KEY ] ) ) : '';

    if ( ! $order_id || ! self::verify_token( $order_id, $token ) ) {
      return '<p>' . esc_html__( 'This form link is invalid or has expired.', 'latepoint-ninja-forms' ) . '</p>';
    }

    $order = new OsOrderModel( $order_id );
    if ( empty( $order->id ) ) {
      return '<p>' . esc_html__( 'Booking not found.', 'latepoint-ninja-forms' ) . '</p>';
    }

    $form_id = self::form_id_for_order( $order );
    if ( ! $form_id ) {
      return '<p>' . esc_html__( 'No form is configured for this booking.', 'latepoint-ninja-forms' ) . '</p>';
    }

    self::$current_order_id = $order_id;
    self::$current_token    = $token;
    $output = do_shortcode( '[ninja_form id=' . (int) $form_id . ']' );
    self::$current_order_id = null;
    self::$current_token    = null;

    return $output;
  }

  /**
   * Fill the hidden order/token fields while our shortcode renders the form.
   *
   * @param mixed $default_value
   * @param array<string, mixed> $field_settings
   * @return mixed
   */
  public static function populate_hidden_fields( $default_value, $field_settings ) {
    $key = $field_settings['key'] ?? '';
    if ( $key === self::HIDDEN_ORDER_KEY && self::$current_order_id ) {
      return self::$current_order_id;
    }
    if ( $key === self::HIDDEN_TOKEN_KEY && self::$current_token ) {
      return self::$current_token;
    }
    return $default_value;
  }

  /**
   * Handle a Ninja Forms submission: link it to the order, store it, and notify.
   *
   * @param array<string, mixed> $form_data
   */
  public static function handle_form_submission( $form_data ): void {
    $fields = $form_data['fields'] ?? [];
    if ( ! is_array( $fields ) ) {
      return;
    }

    $order_id = null;
    $token    = '';
    foreach ( $fields as $field ) {
      $key = $field['key'] ?? '';
      if ( $key === self::HIDDEN_ORDER_KEY ) {
        $order_id = (int) ( $field['value'] ?? 0 );
      }
      if ( $key === self::HIDDEN_TOKEN_KEY ) {
        $token = (string) ( $field['value'] ?? '' );
      }
    }

    if ( ! $order_id || ! self::verify_token( $order_id, $token ) ) {
      return;
    }

    $order = new OsOrderModel( $order_id );
    if ( empty( $order->id ) ) {
      return;
    }

    // Guard against Ninja Forms firing the action more than once for a submission.
    $sub_id = (string) ( $form_data['actions']['save']['sub_id'] ?? ( $form_data['id'] ?? '' ) );
    if ( $sub_id ) {
      $lock_key = 'lp_nf_processed_' . md5( $order_id . '_' . $sub_id );
      if ( get_transient( $lock_key ) ) {
        return;
      }
      set_transient( $lock_key, 1, HOUR_IN_SECONDS );
    }

    $entry = self::build_entry( $form_data, $fields );

    self::append_order_submission( $order_id, $entry );
    self::append_customer_submission( $order, $entry );
    self::notify( $order, $entry );
  }

  /**
   * @param array<string, mixed> $form_data
   * @param array<int, array<string, mixed>> $fields
   * @return array<string, mixed>
   */
  protected static function build_entry( array $form_data, array $fields ): array {
    $visible_fields = [];
    foreach ( $fields as $field ) {
      $key  = $field['key'] ?? '';
      $type = $field['type'] ?? '';
      if ( in_array( $key, [ self::HIDDEN_ORDER_KEY, self::HIDDEN_TOKEN_KEY ], true ) ) {
        continue;
      }
      if ( in_array( $type, [ 'submit', 'hidden', 'html', 'hr' ], true ) ) {
        continue;
      }
      $value = $field['value'] ?? '';
      if ( is_array( $value ) ) {
        $value = implode( ', ', $value );
      }
      $visible_fields[] = [
        'label' => $field['label'] ?? ( $field['key'] ?? '' ),
        'value' => (string) $value,
      ];
    }

    return [
      'form_id'      => (int) ( $form_data['form_id'] ?? ( $form_data['id'] ?? 0 ) ),
      'form_title'   => (string) ( $form_data['settings']['title'] ?? '' ),
      'sub_id'       => (string) ( $form_data['actions']['save']['sub_id'] ?? '' ),
      'submitted_at' => current_time( 'mysql' ),
      'fields'       => $visible_fields,
    ];
  }

  /** @param array<string, mixed> $entry */
  protected static function append_order_submission( $order_id, array $entry ): void {
    $submissions   = self::get_order_submissions( $order_id );
    $submissions[] = $entry;
    OsMetaHelper::save_order_meta_by_key( self::ORDER_META_KEY, wp_json_encode( $submissions ), $order_id );
  }

  /** @param array<string, mixed> $entry */
  protected static function append_customer_submission( $order, array $entry ): void {
    $customer = $order->get_customer();
    if ( ! $customer || empty( $customer->id ) ) {
      return;
    }
    $entry['order_id'] = (int) $order->id;
    $submissions       = self::get_customer_submissions( $customer->id );
    $submissions[]     = $entry;
    OsMetaHelper::save_customer_meta_by_key( self::CUSTOMER_META_KEY, wp_json_encode( $submissions ), $customer->id );
  }

  /* ===========================================================================
   * Requirement 3 — notifications
   * ========================================================================= */

  /** @param array<string, mixed> $entry */
  protected static function notify( $order, array $entry ): void {
    $customer = $order->get_customer();
    $summary  = self::email_summary_html( $entry );

    if ( self::notify_customer_enabled() && $customer && ! empty( $customer->email ) ) {
      $subject = __( 'We received your form submission', 'latepoint-ninja-forms' );
      $content = '<p>' . sprintf( esc_html__( 'Hi %s,', 'latepoint-ninja-forms' ), esc_html( $customer->first_name ) ) . '</p>';
      $content .= '<p>' . esc_html__( 'Thank you, we have received your form submission. A copy is below.', 'latepoint-ninja-forms' ) . '</p>';
      $content .= $summary;
      OsEmailHelper::send_email( $customer->email, $subject, $content );
    }

    if ( self::notify_admin_enabled() ) {
      $admin_email = self::get_admin_email();
      $customer_name = $customer ? $customer->full_name : '';
      $subject = sprintf( __( 'New form submission for order #%d', 'latepoint-ninja-forms' ), (int) $order->id );
      $content = '<p>' . sprintf( esc_html__( 'A form was submitted by %1$s for order #%2$d.', 'latepoint-ninja-forms' ), esc_html( $customer_name ), (int) $order->id ) . '</p>';
      $content .= $summary;
      OsEmailHelper::send_email( $admin_email, $subject, $content );
    }
  }

  /** @param array<string, mixed> $entry */
  protected static function email_summary_html( array $entry ): string {
    $html = '<table cellpadding="6" cellspacing="0" border="1" style="border-collapse:collapse;">';
    foreach ( ( $entry['fields'] ?? [] ) as $field ) {
      $html .= '<tr><th align="left">' . esc_html( $field['label'] ) . '</th><td>' . nl2br( esc_html( $field['value'] ) ) . '</td></tr>';
    }
    $html .= '</table>';
    return $html;
  }
}

endif;
