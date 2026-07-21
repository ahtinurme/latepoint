<?php
/**
 * LatePoint Admin Nudges Helper.
 *
 * Registers contextual, dismissible admin notices (nudges) on WordPress
 * admin screens. Each nudge is event-triggered and shown once per user.
 * Flag detection and analytics tracking live in OsAnalyticsHelper;
 * this class is a pure display concern.
 *
 * Add new nudges as additional BSF_Admin_Notices::add_notice() calls
 * inside maybe_register_notices().
 *
 * @since 5.3.0
 * @package LatePoint
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'OsAdminNudgesHelper' ) ) {
	/**
	 * OsAdminNudgesHelper
	 *
	 * Registers dismissible BSF_Admin_Notices banners on the WP Dashboard.
	 */
	class OsAdminNudgesHelper {

		/**
		 * WP option key that records the first booking (set by OsAnalyticsHelper).
		 *
		 * @var string
		 */
		const FIRST_BOOKING_OPTION = 'latepoint_first_booking_created';

		/**
		 * Notice ID for the first-booking payment-gateway nudge.
		 * Used by BSF_Admin_Notices as the per-user dismiss user-meta key.
		 *
		 * @var string
		 */
		const NOTICE_FIRST_BOOKING = 'latepoint_first_booking_payment_nudge';

		/**
		 * Instance.
		 *
		 * @var OsAdminNudgesHelper|null
		 */
		private static $instance = null;

		/**
		 * Singleton accessor.
		 *
		 * @return OsAdminNudgesHelper
		 */
		public static function get_instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Constructor — register the admin_notices hook.
		 */
		private function __construct() {
			// Priority 20: before the BSF kit renders at 30.
			add_action( 'admin_notices', array( $this, 'maybe_register_notices' ), 20 );
		}

		/**
		 * Register all nudges. Only runs on the WP Dashboard screen.
		 * Add future nudges as additional add_notice() calls here.
		 *
		 * @return void
		 */
		public function maybe_register_notices() {
			$screen = get_current_screen();
			if ( ! $screen || 'dashboard' !== $screen->id ) {
				return;
			}

			$this->register_first_booking_nudge();
		}

		// -------------------------------------------------------------------------
		// First-booking payment-gateway nudge
		// -------------------------------------------------------------------------

		/**
		 * Register the first-booking nudge: prompts the site owner to connect a
		 * payment gateway right after their first booking is received.
		 *
		 * @return void
		 */
		private function register_first_booking_nudge() {
			BSF_Admin_Notices::add_notice(
				array(
					'id'             => self::NOTICE_FIRST_BOOKING,
					'type'           => 'info',
					'is_dismissible' => true,
					'capability'     => 'manage_options',
					'show_if'        => $this->should_show_first_booking_nudge(),
					'message'        => $this->get_first_booking_nudge_message(),
				)
			);
		}

		/**
		 * Build the logo + heading + inline-CTA-message markup for a nudge notice,
		 * matching the SureForms multiline notice pattern.
		 *
		 * @param string $heading Escaped heading text.
		 * @param string $message Already-sanitised message HTML (may contain inline <a>).
		 * @return string
		 */
		private function build_nudge_markup( $heading, $message ) {
			$logo_url = esc_url( LATEPOINT_IMAGES_URL . 'logo.svg' );
			return sprintf(
				'<div class="notice-image" style="align-self:center">
					<img src="%1$s" alt="LatePoint" width="50" height="50" style="display:block">
				</div>
				<div class="notice-content">
					<div class="notice-heading">%2$s</div>
					%3$s
				</div>',
				$logo_url,
				$heading,
				$message
			);
		}

		/**
		 * Build the first-booking nudge message with a CTA link to payment settings.
		 *
		 * Migrated sites (option value prefixed with 'migrated_') receive a generic
		 * payment-collection prompt; new sites get the first-booking celebration copy.
		 *
		 * @return string
		 */
		private function get_first_booking_nudge_message() {
			$payments_url = OsRouterHelper::build_link( array( 'settings', 'payments' ) );
			$option_value = get_option( self::FIRST_BOOKING_OPTION, '' );
			$allowed_msg  = array( 'a' => array( 'href' => array() ) );

			if ( 0 === strpos( $option_value, 'migrated_' ) ) {
				/* translators: %s: URL to the LatePoint payment-settings screen. */
				$message = sprintf(
					wp_kses( __( 'Connect a payment gateway and start getting paid at the time of booking. <a href="%s">Set up payments →</a>', 'latepoint' ), $allowed_msg ),
					esc_url( $payments_url )
				);
				return $this->build_nudge_markup(
					esc_html__( 'Did you know you can collect payment upfront in LatePoint?', 'latepoint' ),
					$message
				);
			}

			/* translators: %s: URL to the LatePoint payment-settings screen. */
			$message = sprintf(
				wp_kses( __( 'Did you know you can collect payment upfront in LatePoint? <a href="%s">Connect Stripe →</a>', 'latepoint' ), $allowed_msg ),
				esc_url( $payments_url )
			);
			return $this->build_nudge_markup(
				esc_html__( '🎉 You just got your first booking!', 'latepoint' ),
				$message
			);
		}

		/**
		 * Whether to show the first-booking nudge.
		 *
		 * Returns true only when:
		 *  - The first-booking WP option is set (by OsAnalyticsHelper).
		 *  - No online payment processor is currently enabled.
		 *  - The current user can manage options.
		 *
		 * Per-user dismissal is handled automatically by BSF_Admin_Notices via
		 * user-meta keyed to the notice ID.
		 *
		 * @return bool
		 */
		private function should_show_first_booking_nudge() {
			if ( ! current_user_can( 'manage_options' ) ) {
				return false;
			}

			if ( ! get_option( self::FIRST_BOOKING_OPTION ) ) {
				return false;
			}

			if ( ! empty( OsPaymentsHelper::get_enabled_payment_processors() ) ) {
				return false;
			}

			return true;
		}
	}
}
