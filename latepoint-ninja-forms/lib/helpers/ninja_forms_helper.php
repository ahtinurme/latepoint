<?php
if ( ! defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly.
}

if ( ! class_exists( 'OsNinjaFormsHelper' ) ) :

/**
 * All Ninja Forms <-> LatePoint glue lives here. The main plugin class only wires hooks
 * to these static methods so the logic stays in one place and is unit-testable.
 *
 * Storage model: submissions ARE native Ninja Forms submissions (nf_sub). We link each to its
 * LatePoint order/customer by stamping postmeta FKs on the sub (LP_ORDER_FK / LP_CUSTOMER_FK),
 * then list them by querying nf_sub on those keys. No denormalised copy — Ninja Forms is the
 * single source of truth, so the subs are also visible under Ninja Forms → Submissions.
 */
class OsNinjaFormsHelper {

  const SERVICE_META_ENABLED  = 'ninja_form_enabled';

  // Postmeta on an nf_sub linking it back to LatePoint.
  const LP_ORDER_FK    = '_latepoint_order_id';
  const LP_CUSTOMER_FK = '_latepoint_customer_id';

  // Ninja Forms field keys the site admin adds to their form as Hidden fields. We populate
  // them automatically when the form is rendered through our shortcode, and read them back
  // on submission to link the entry to a LatePoint order.
  const HIDDEN_ORDER_KEY = 'lp_nf_order';
  const HIDDEN_TOKEN_KEY = 'lp_nf_token';

  // URL/query flag marking the "second person" (guest) link on a 2-person booking. On the guest
  // link the identity fields are NOT prefilled — the guest types their own, and on submission
  // they are matched to / created as their own LatePoint customer by email.
  const ROLE_KEY = 'lp_nf_role';

  // Set while our shortcode renders a form, consumed by populate_hidden_fields().
  protected static ?int    $current_order_id = null;
  protected static ?string $current_token    = null;
  protected static ?string $current_role     = null;
  protected static $current_customer         = null;

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
   * service. Defaults to DISABLED when never set — only services explicitly
   * toggled on (the first-time EMS ones) deliver the form link.
   * ========================================================================= */

  public static function service_link_enabled( $service_id ): bool {
    if ( empty( $service_id ) ) {
      return false;
    }
    return OsMetaHelper::get_service_meta_by_key( self::SERVICE_META_ENABLED, $service_id, 'off' ) == LATEPOINT_VALUE_ON;
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
    $enabled = ! $service || empty( $service->id ) ? false : self::service_link_enabled( $service->id );

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

  /** The consent is signed once per person — true when this customer already has a submission. */
  public static function customer_has_submission( $customer_id ): bool {
    if ( ! $customer_id ) {
      return false;
    }
    $ids = get_posts( [
      'post_type'   => 'nf_sub',
      'post_status' => 'publish',
      'numberposts' => 1,
      'fields'      => 'ids',
      'meta_key'    => self::LP_CUSTOMER_FK,
      'meta_value'  => (int) $customer_id,
    ] );
    return ! empty( $ids );
  }

  public static function form_url_for_order( $order, string $role = '' ): string {
    if ( ! self::has_form( $order ) ) {
      return '';
    }
    // Booker already submitted → no primary link. The guest link stays: the 2nd person on a
    // "kahekesi" booking is someone else, identified only at submit time.
    if ( $role !== 'guest' && self::customer_has_submission( $order->customer_id ) ) {
      return '';
    }
    $page_id = self::get_form_page_id();
    if ( ! $page_id ) {
      return '';
    }
    $args = [
      self::HIDDEN_ORDER_KEY => (int) $order->id,
      self::HIDDEN_TOKEN_KEY => self::token_for_order( $order->id ),
    ];
    if ( $role !== '' ) {
      $args[ self::ROLE_KEY ] = $role; // 'guest' → the 2nd person's blank, self-identifying form
    }
    return add_query_arg( $args, get_permalink( $page_id ) );
  }

  /* ===========================================================================
   * Requirement 1 — deliver the link
   * ========================================================================= */

  public static function replace_order_vars( string $text, $order ): string {
    if ( strpos( $text, '{{order_ninja_form_' ) === false ) {
      return $text;
    }
    $url        = self::form_url_for_order( $order );
    $guest_url  = self::form_url_for_order( $order, 'guest' );
    // Render the *_link vars as a button matching LatePoint's own email button (manage-booking CTA).
    $btn = fn( $u ) => '<a href="' . esc_url( $u ) . '" style="display:block;text-decoration:none;padding:10px;border-radius:6px;text-align:center;font-size:18px;color:#fff;background-color:#1e7bff;font-weight:700;">' . esc_html__( 'Open form', 'latepoint-ninja-forms' ) . '</a>';
    $link       = $url ? $btn( $url ) : '';
    $guest_link = $guest_url ? $btn( $guest_url ) : '';
    return str_replace(
      [ '{{order_ninja_form_url}}', '{{order_ninja_form_link}}', '{{order_ninja_form_guest_url}}', '{{order_ninja_form_guest_link}}' ],
      [ esc_url( $url ), $link, esc_url( $guest_url ), $guest_link ],
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
    return self::submissions_by_fk( self::LP_ORDER_FK, (int) $order_id );
  }

  /** @return array<int, array<string, mixed>> */
  public static function get_customer_submissions( $customer_id ): array {
    return self::submissions_by_fk( self::LP_CUSTOMER_FK, (int) $customer_id );
  }

  /** @return array<int, array<string, mixed>> */
  protected static function submissions_by_fk( string $meta_key, int $id ): array {
    if ( ! $id || ! function_exists( 'Ninja_Forms' ) ) {
      return [];
    }
    $sub_ids = get_posts( [
      'post_type'   => 'nf_sub',
      'post_status' => 'publish',
      'numberposts' => -1,
      'fields'      => 'ids',
      'orderby'     => 'date',
      'order'       => 'ASC',
      'meta_key'    => $meta_key,
      'meta_value'  => $id,
    ] );

    $entries = [];
    foreach ( $sub_ids as $sub_id ) {
      $entry = self::sub_to_entry( $sub_id );
      if ( $entry ) {
        $entries[] = $entry;
      }
    }
    return $entries;
  }

  /**
   * Build a render/notify entry from a native nf_sub: its form title, date, and visible
   * field label/value pairs (read straight from postmeta to avoid double-escaping).
   *
   * @return ?array<string, mixed>
   */
  protected static function sub_to_entry( $sub_id ): ?array {
    $sub_id = (int) $sub_id;
    if ( ! $sub_id || ! function_exists( 'Ninja_Forms' ) ) {
      return null;
    }
    $form_id = (int) get_post_meta( $sub_id, '_form_id', true );
    if ( ! $form_id ) {
      return null;
    }

    $form   = Ninja_Forms()->form( $form_id )->get();
    $fields = [];
    foreach ( Ninja_Forms()->form( $form_id )->get_fields() as $field ) {
      if ( in_array( $field->get_setting( 'type' ), [ 'submit', 'hidden', 'html', 'hr' ], true ) ) {
        continue;
      }
      // Paper-scan originals are private: render authenticated endpoint URLs, never the stored file names.
      $value = $field->get_setting( 'key' ) === 'originaaldokument'
        ? self::scan_urls_for_sub( $sub_id )
        : self::format_field_value( $field, maybe_unserialize( get_post_meta( $sub_id, '_field_' . $field->get_id(), true ) ) );
      if ( $value === '' ) {
        continue;
      }
      $fields[] = [ 'label' => (string) $field->get_setting( 'label' ), 'value' => $value ];
    }

    return [
      'form_title'   => $form ? (string) $form->get_setting( 'title' ) : '',
      'submitted_at' => get_post_field( 'post_date', $sub_id ),
      'fields'       => $fields,
    ];
  }

  /**
   * Stored list values are option *values* (slugs); map them back to their option labels for
   * display. Everything else renders as-is.
   *
   * @param mixed $raw
   */
  protected static function format_field_value( $field, $raw ): string {
    $list_types = [ 'listradio', 'listcheckbox', 'listselect', 'listmultiselect' ];
    if ( in_array( $field->get_setting( 'type' ), $list_types, true ) ) {
      $labels = [];
      foreach ( (array) $field->get_setting( 'options' ) as $o ) {
        if ( isset( $o['value'], $o['label'] ) ) {
          $labels[ (string) $o['value'] ] = (string) $o['label'];
        }
      }
      $selected = is_array( $raw ) ? $raw : ( ( $raw === '' || $raw === null ) ? [] : [ $raw ] );
      $out = array_map( fn( $v ) => $labels[ (string) $v ] ?? (string) $v, $selected );
      return implode( ', ', $out );
    }

    if ( $field->get_setting( 'type' ) === 'checkbox' ) {
      $checked = in_array( (string) $raw, [ '1', 'on', 'checked', 'true' ], true );
      return $checked ? __( 'Jah', 'latepoint-ninja-forms' ) : '';
    }

    if ( $field->get_setting( 'type' ) === 'signature' ) {
      $data = is_string( $raw ) ? json_decode( $raw, true ) : null;
      if ( ! is_array( $data ) ) {
        return '';
      }
      if ( ( $data['signature_type'] ?? '' ) === 'drawn' ) {
        return (string) ( $data['signature_data'] ?? '' ); // data: URL, rendered as <img> by render_value()
      }
      return (string) ( $data['typed_name'] ?? '' );
    }

    if ( is_array( $raw ) ) {
      return implode( ', ', $raw );
    }
    return (string) $raw;
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

  // Customer cabinet tab class, shared by the trigger (latepoint_customer_dashboard_after_tabs)
  // and the content panel (latepoint_customer_dashboard_after_tab_contents).
  const CABINET_TAB_CLASS = 'tab-content-customer-consent';

  /** The cabinet tab button — only when the customer has submissions, so the tab never shows empty. */
  public static function customer_cabinet_tab_trigger_html( $customer ): string {
    if ( ! $customer || empty( $customer->id ) || ! self::get_customer_submissions( $customer->id ) ) {
      return '';
    }
    $label = apply_filters( 'latepoint_ninja_forms_cabinet_tab_label', 'Nõusolek' ); // Estonian site; override via filter
    return '<a href="#" data-tab-target=".' . self::CABINET_TAB_CLASS . '" class="latepoint-tab-trigger">'
      . esc_html( $label ) . '</a>';
  }

  /** The cabinet tab panel holding the submissions (hidden until its trigger is clicked). */
  public static function customer_cabinet_tab_content_html( $customer ): string {
    $inner = self::customer_submissions_html( $customer );
    if ( ! $inner ) {
      return '';
    }
    return '<div class="latepoint-tab-content ' . self::CABINET_TAB_CLASS . '">' . $inner . '</div>';
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

  /** Render image URLs (an attached scan) and drawn-signature data URLs as images, everything else as escaped text. */
  protected static function render_value( string $value ): string {
    $img = 'style="max-width:220px;height:auto;margin:4px 0;border:1px solid #ddd;"';
    $out = [];
    foreach ( preg_split( '/\r\n|\r|\n/', $value ) as $line ) {
      $trimmed = trim( $line );
      if ( $trimmed !== '' && ( preg_match( '#^https?://\S+\.(jpe?g|png|gif|webp)$#i', $trimmed )
        || ( strpos( $trimmed, 'action=' . self::SCAN_ACTION ) !== false && preg_match( '#^https?://\S+$#', $trimmed ) ) ) ) {
        $out[] = '<a href="' . esc_url( $trimmed ) . '" target="_blank" rel="noopener"><img src="' . esc_url( $trimmed ) . '" alt="" ' . $img . ' /></a>';
      } elseif ( preg_match( '#^data:image/(png|jpe?g|gif|webp);base64,[A-Za-z0-9+/=]+$#', $trimmed ) ) {
        $out[] = '<img src="' . esc_attr( $trimmed ) . '" alt="" ' . $img . ' />'; // esc_url strips data: URLs, so esc_attr
      } else {
        $out[] = nl2br( esc_html( $line ) );
      }
    }
    return implode( '<br>', $out );
  }

  /* ===========================================================================
   * Protected original-scan serving — the paper consent scans live in a
   * deny-all uploads/lp-consent dir (see scripts/forms/protect-consent-scans.php)
   * and are streamed only to the submission's own logged-in LatePoint customer
   * or a logged-in admin/agent.
   * ========================================================================= */

  const SCAN_ACTION = 'latepoint_nf_scan';

  public static function scans_dir(): string {
    return wp_upload_dir()['basedir'] . '/lp-consent';
  }

  /**
   * Scan file basenames stored on the sub's `originaaldokument` field, filtered to files
   * that actually exist in the protected dir (so nothing renders as a broken image).
   *
   * @return array<int, string>
   */
  protected static function scan_files_for_sub( int $sub_id ): array {
    global $wpdb;
    $form_id = (int) get_post_meta( $sub_id, '_form_id', true );
    if ( ! $form_id ) {
      return [];
    }
    $field_id = (int) $wpdb->get_var( $wpdb->prepare(
      "SELECT id FROM {$wpdb->prefix}nf3_fields WHERE parent_id = %d AND `key` = 'originaaldokument'", $form_id
    ) );
    if ( ! $field_id ) {
      return [];
    }
    $files = [];
    foreach ( preg_split( '/\R+/', (string) get_post_meta( $sub_id, '_field_' . $field_id, true ) ) as $line ) {
      $name = basename( trim( $line ) ); // basename() also kills any traversal in a tampered value
      if ( $name !== '' && is_file( self::scans_dir() . '/' . $name ) ) {
        $files[] = $name;
      }
    }
    return $files;
  }

  /** Authenticated scan URLs, one per line — render_value() turns them into <img> tags. */
  protected static function scan_urls_for_sub( int $sub_id ): string {
    $urls = [];
    foreach ( array_keys( self::scan_files_for_sub( $sub_id ) ) as $i ) {
      $urls[] = admin_url( 'admin-ajax.php?action=' . self::SCAN_ACTION . '&sub_id=' . $sub_id . '&i=' . $i );
    }
    return implode( "\n", $urls );
  }

  /** admin-ajax handler (priv + nopriv): stream one scan after the ownership check. */
  public static function serve_scan(): void {
    $sub_id = isset( $_GET['sub_id'] ) ? (int) $_GET['sub_id'] : 0;
    $index  = isset( $_GET['i'] ) ? (int) $_GET['i'] : 0;

    $is_staff = OsAuthHelper::is_admin_logged_in() || OsAuthHelper::is_agent_logged_in();
    $owner_id = $sub_id ? (int) get_post_meta( $sub_id, self::LP_CUSTOMER_FK, true ) : 0;
    $is_owner = $owner_id && $owner_id === (int) OsAuthHelper::get_logged_in_customer_id();
    if ( ! $sub_id || ( ! $is_staff && ! $is_owner ) ) {
      status_header( 403 );
      exit;
    }

    $files = self::scan_files_for_sub( $sub_id );
    if ( ! isset( $files[ $index ] ) ) {
      status_header( 404 );
      exit;
    }
    $path = self::scans_dir() . '/' . $files[ $index ];

    nocache_headers(); // private document — keep it out of shared/page caches
    header( 'Content-Type: image/jpeg' );
    header( 'Content-Length: ' . filesize( $path ) );
    readfile( $path );
    exit;
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

    $role = isset( $_GET[ self::ROLE_KEY ] ) ? sanitize_key( wp_unslash( $_GET[ self::ROLE_KEY ] ) ) : '';

    // Old email links stay valid forever; once the booker has submitted, show a note instead of
    // the form so a conscientious customer doesn't file it again with every booking email.
    if ( $role !== 'guest' && self::customer_has_submission( $order->customer_id ) ) {
      return self::form_page_styles() . '<div class="latepoint-nf-page"><div class="nf-form-cont"><p>'
        . esc_html__( 'You have already submitted this form — no need to fill it in again. Thank you!', 'latepoint-ninja-forms' )
        . '</p></div></div>';
    }

    self::$current_order_id = $order_id;
    self::$current_token    = $token;
    self::$current_role     = $role;
    // Prefill the identity block from the booker on the primary link; the guest link starts blank.
    self::$current_customer = ( $role === 'guest' ) ? null : $order->get_customer();

    $output = do_shortcode( '[ninja_form id=' . (int) $form_id . ']' );
    self::localize_nf_js_strings(); // Estonian for the React signature field's JS strings (not in NF's .pot)

    self::$current_order_id = null;
    self::$current_token    = null;
    self::$current_role     = null;
    self::$current_customer = null;

    return self::form_page_styles() . '<div class="latepoint-nf-page">' . $output . '</div>' . self::form_page_script();
  }

  /**
   * Client-side multi-submit lock: first click on the submit button puts the page into a
   * "submitting" state (button inert + spinner) until NF responds or validation errors appear.
   * The lock lives on the page container because NF re-renders the button, wiping per-button state.
   */
  protected static function form_page_script(): string {
    static $printed = false;
    if ( $printed ) {
      return '';
    }
    $printed = true;

    return <<<'HTML'
<script id="latepoint-nf-guard">
(function(){
  var timer = null;
  function release(c){ c.classList.remove('lp-nf-submitting'); clearInterval(timer); timer = null; }
  document.addEventListener('click', function(e){
    var c = document.querySelector('.latepoint-nf-page');
    if (!c || !(e.target instanceof Element) || !c.contains(e.target)) return;
    if (!e.target.matches('input[type=button].ninja-forms-field, .nf-element[type=button]')) return;
    if (c.classList.contains('lp-nf-submitting')) { e.preventDefault(); e.stopImmediatePropagation(); return; }
    c.classList.add('lp-nf-submitting');
    var started = Date.now();
    timer = setInterval(function(){
      if (c.querySelector('.nf-error') || Date.now() - started > 20000) release(c);
    }, 400);
  }, true);
  if (window.jQuery) {
    jQuery(document).on('nfFormSubmitResponse nfFormError', function(){
      var c = document.querySelector('.latepoint-nf-page');
      if (c) release(c);
    });
  }
})();
</script>
HTML;
  }

  /**
   * The Ninja Forms Signature field's "Clear" button + helper text are JS strings (wp.i18n, domain
   * `ninja-forms`) that aren't in NF's .pot, so Loco can't translate them. Inject Estonian for just
   * those, attached to wp-i18n so it runs before the React field mounts.
   */
  protected static function localize_nf_js_strings(): void {
    if ( ! wp_script_is( 'wp-i18n', 'enqueued' ) && ! wp_script_is( 'wp-i18n', 'registered' ) ) {
      return;
    }
    $data = [
      ''                                                  => [ 'domain' => 'ninja-forms', 'lang' => 'et' ],
      'Clear'                                             => [ 'Tühjenda' ],
      'Use your mouse, finger, or stylus to sign above.'  => [ 'Allkirjastage ülal hiire, sõrme või pliiatsiga.' ],
    ];
    wp_add_inline_script( 'wp-i18n', 'wp.i18n.setLocaleData(' . wp_json_encode( $data ) . ', "ninja-forms");', 'after' );
  }

  /**
   * Scoped styling for the public consent form so it reads as a clean branded card rather than the
   * raw Ninja Forms default. Brand green #174c2f, theme font inherited. Kept inline + scoped under
   * `.latepoint-nf-page` so it touches nothing else on the site.
   */
  protected static function form_page_styles(): string {
    static $printed = false;
    if ( $printed ) {
      return '';
    }
    $printed = true;

    return <<<'CSS'
<style id="latepoint-nf-styles">
.latepoint-nf-page{--lpnf-green:#174c2f;--lpnf-green-dark:#0f3a23;--lpnf-line:#dbe3de;
  max-width:760px;margin:32px auto;background:#fff;border-radius:16px;
  box-shadow:0 6px 30px rgba(16,40,28,.08);border-top:4px solid var(--lpnf-green);overflow:hidden}
.latepoint-nf-page .nf-form-cont{padding:8px 38px 34px}
@media(max-width:600px){.latepoint-nf-page{margin:0;border-radius:0}.latepoint-nf-page .nf-form-cont{padding:8px 18px 26px}}
.latepoint-nf-page .nf-form-title h3{color:var(--lpnf-green);font-size:1.7rem;font-weight:700;
  margin:8px 0 4px;line-height:1.25}
.latepoint-nf-page .nf-field-container{margin-bottom:20px}
.latepoint-nf-page .nf-field-label label,.latepoint-nf-page .nf-field-label{font-weight:600;
  color:#21302a;font-size:.95rem;margin-bottom:7px;line-height:1.4}
.latepoint-nf-page .nf-field-description{color:#6a7872;font-size:.85rem}
.latepoint-nf-page .nf-error-msg{color:#c0392b}
.latepoint-nf-page input.ninja-forms-field,
.latepoint-nf-page textarea.ninja-forms-field,
.latepoint-nf-page select.ninja-forms-field,
.latepoint-nf-page .nf-element{font-family:inherit!important;font-size:1rem!important;color:#1c2622!important;
  background:#fff!important;border:1px solid var(--lpnf-line)!important;border-radius:10px!important;
  padding:11px 14px!important;box-shadow:none!important;transition:border-color .15s,box-shadow .15s;width:100%}
.latepoint-nf-page textarea.nf-element{min-height:96px;line-height:1.5}
.latepoint-nf-page .nf-element:focus{border-color:var(--lpnf-green)!important;
  box-shadow:0 0 0 3px rgba(23,76,47,.14)!important;outline:none!important}
.latepoint-nf-page .nf-field-element input[type=radio],
.latepoint-nf-page .nf-field-element input[type=checkbox]{accent-color:var(--lpnf-green);
  width:18px;height:18px;margin-right:9px;cursor:pointer}
.latepoint-nf-page .list-select-wrap .nf-field-element ul li,
.latepoint-nf-page .checkbox-container,
.latepoint-nf-page .nf-field-element li{list-style:none;margin:0 0 8px}
.latepoint-nf-page .list-select-wrap label,
.latepoint-nf-page .nf-field-element li label{display:flex;align-items:center;font-weight:400!important;
  color:#2b3a33;cursor:pointer;margin:0}
.latepoint-nf-page .nf-field-element li{padding:9px 13px;border:1px solid var(--lpnf-line);
  border-radius:10px;transition:border-color .15s,background .15s}
.latepoint-nf-page .nf-field-element li:hover{border-color:var(--lpnf-green);background:#f5f9f7}
.latepoint-nf-page .html-container,.latepoint-nf-page .nf-field .html-content{background:#f4f8f5;
  border-left:4px solid var(--lpnf-green);border-radius:10px;padding:14px 18px;color:#3c4c44;font-size:.9rem;line-height:1.55}
.latepoint-nf-page canvas{border:1px solid var(--lpnf-line)!important;border-radius:10px;background:#fff}
.latepoint-nf-page .nf-element[type=button],
.latepoint-nf-page input[type=button].ninja-forms-field{background:var(--lpnf-green)!important;color:#fff!important;
  border:none!important;border-radius:10px!important;padding:14px 34px!important;font-weight:600!important;
  font-size:1.02rem!important;width:auto!important;cursor:pointer;transition:background .15s,transform .05s;box-shadow:0 2px 10px rgba(23,76,47,.18)!important}
.latepoint-nf-page .nf-element[type=button]:hover,
.latepoint-nf-page input[type=button].ninja-forms-field:hover{background:var(--lpnf-green-dark)!important}
.latepoint-nf-page .nf-element[type=button]:active{transform:translateY(1px)}
.latepoint-nf-page .nf-after-form-content .extra-html,
.latepoint-nf-page .nf-response-msg{color:var(--lpnf-green)}
/* "Muu, palun täpsusta" text field (container_class lp-muu-detail): hidden until the preceding
   field's "Muu" option is selected. Pure CSS via :has() — reactive, no JS, survives NF re-renders. */
.latepoint-nf-page .lp-muu-detail{display:none}
.latepoint-nf-page nf-field:has(> .nf-field-container input[value="muu"]:checked) + nf-field .lp-muu-detail{display:block}
/* Multi-submit lock: while .lp-nf-submitting the button is inert (survives NF re-renders,
   unlike a disabled attribute) and a spinner shows next to it. */
.latepoint-nf-page.lp-nf-submitting .nf-element[type=button],
.latepoint-nf-page.lp-nf-submitting input[type=button].ninja-forms-field{pointer-events:none;opacity:.6}
.latepoint-nf-page.lp-nf-submitting .submit-container .nf-field-element::after{content:'';
  display:inline-block;width:20px;height:20px;margin-left:12px;vertical-align:middle;
  border:3px solid rgba(23,76,47,.25);border-top-color:var(--lpnf-green);border-radius:50%;
  animation:lpnf-spin .8s linear infinite}
@keyframes lpnf-spin{to{transform:rotate(360deg)}}
</style>
CSS;
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
    // Prefill the identity block from the booker (primary link only; guest link stays blank).
    $c = self::$current_customer;
    if ( $c && ! empty( $c->id ) ) {
      $prefill = [
        'eesnimi'       => $c->first_name,
        'perekonnanimi' => $c->last_name,
        'email'         => $c->email,
        'telefon'       => $c->phone,
      ];
      if ( isset( $prefill[ $key ] ) && $prefill[ $key ] !== '' && ( $default_value === '' || $default_value === null ) ) {
        return $prefill[ $key ];
      }
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

    // Ninja Forms has already saved the native sub by the time this fires; we just stamp the
    // LatePoint FKs onto it so it lists on the order/customer. sub_id also guards against the
    // action firing more than once.
    $sub_id = (int) ( $form_data['actions']['save']['sub_id'] ?? ( $form_data['id'] ?? 0 ) );
    if ( ! $sub_id ) {
      return;
    }
    $lock_key = 'lp_nf_processed_' . md5( $order_id . '_' . $sub_id );
    if ( get_transient( $lock_key ) ) {
      return;
    }
    set_transient( $lock_key, 1, HOUR_IN_SECONDS );

    // The submitter may not be the booker (2nd person on a "kahekesi" booking), so resolve who this
    // consent belongs to by email — find an existing customer or create one from the identity fields.
    $customer = self::resolve_submission_customer( $fields, $order );

    // Hard once-per-person guard: the link and form page already hide behind
    // customer_has_submission(), but nothing stops a re-posted form (old tab, stale page).
    // Drop the duplicate sub entirely — the new one isn't FK-stamped yet, so it doesn't count itself.
    if ( $customer && ! empty( $customer->id ) && self::customer_has_submission( $customer->id ) ) {
      wp_delete_post( $sub_id, true );
      return;
    }

    update_post_meta( $sub_id, self::LP_ORDER_FK, (int) $order->id );

    if ( $customer && ! empty( $customer->id ) ) {
      update_post_meta( $sub_id, self::LP_CUSTOMER_FK, (int) $customer->id );
    }

    $entry = self::sub_to_entry( $sub_id );
    if ( $entry ) {
      self::notify( $order, $entry, $customer );
    }
  }

  /**
   * Find (by email) or create the LatePoint customer a submission belongs to, from its identity
   * fields. Existing customers are never overwritten — only a newly created one gets the details.
   * Falls back to the order's booker when no usable email was submitted.
   *
   * @param array<int, array<string, mixed>> $fields
   * @return mixed OsCustomerModel or null
   */
  protected static function resolve_submission_customer( array $fields, $order ) {
    $vals = [];
    foreach ( $fields as $field ) {
      $key = $field['key'] ?? '';
      if ( in_array( $key, [ 'email', 'eesnimi', 'perekonnanimi', 'telefon' ], true ) ) {
        $v = $field['value'] ?? '';
        $vals[ $key ] = trim( is_array( $v ) ? implode( ' ', $v ) : (string) $v );
      }
    }

    $email = sanitize_email( $vals['email'] ?? '' );
    if ( ! $email || ! is_email( $email ) ) {
      return $order->get_customer();
    }

    $existing = ( new OsCustomerModel() )->where( [ 'email' => $email ] )->set_limit( 1 )->get_results_as_models();
    if ( $existing && ! empty( $existing->id ) ) {
      return $existing;
    }

    $customer             = new OsCustomerModel();
    $customer->first_name = $vals['eesnimi'] ?? '';
    $customer->last_name  = $vals['perekonnanimi'] ?? '';
    $customer->email      = $email;
    $customer->phone      = $vals['telefon'] ?? '';
    $customer->save();

    return ! empty( $customer->id ) ? $customer : $order->get_customer();
  }

  /* ===========================================================================
   * Requirement 3 — notifications
   * ========================================================================= */

  /** @param array<string, mixed> $entry */
  protected static function notify( $order, array $entry, $customer = null ): void {
    if ( ! $customer ) {
      $customer = $order->get_customer();
    }
    // No form data in emails (sensitive health info) — the submission is viewable in LatePoint.
    // The body is HTML; without this header wp_mail sends text/plain and clients show raw markup.
    $headers  = [ 'Content-Type: text/html; charset=UTF-8' ];

    if ( self::notify_customer_enabled() && $customer && ! empty( $customer->email ) ) {
      $subject = __( 'We received your form submission', 'latepoint-ninja-forms' );
      $content = '<p>' . sprintf( esc_html__( 'Hi %s,', 'latepoint-ninja-forms' ), esc_html( $customer->first_name ) ) . '</p>';
      $content .= '<p>' . esc_html__( 'Thank you, we have received your form submission.', 'latepoint-ninja-forms' ) . '</p>';
      $content .= '<p><a href="' . esc_url( OsSettingsHelper::get_customer_dashboard_url() ) . '">' . esc_html__( 'You can view it in your customer cabinet.', 'latepoint-ninja-forms' ) . '</a></p>';
      OsEmailHelper::send_email( $customer->email, $subject, $content, $headers );
    }

    if ( self::notify_admin_enabled() ) {
      $admin_email = self::get_admin_email();
      $customer_name = $customer ? $customer->full_name : '';
      $subject = sprintf( __( 'New form submission for order #%d', 'latepoint-ninja-forms' ), (int) $order->id );
      $content = '<p>' . sprintf( esc_html__( 'A form was submitted by %1$s for order #%2$d.', 'latepoint-ninja-forms' ), esc_html( $customer_name ), (int) $order->id ) . '</p>';
      OsEmailHelper::send_email( $admin_email, $subject, $content, $headers );
    }
  }
}

endif;
