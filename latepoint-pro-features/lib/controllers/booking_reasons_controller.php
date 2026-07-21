<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}


if ( ! class_exists( 'OsBookingReasonsController' ) ) :


	class OsBookingReasonsController extends OsController {


		public function __construct() {
			parent::__construct();

			$this->action_access['public'] = array_merge( $this->action_access['public'], [ 'cancellation_form' ] );
			$this->views_folder            = plugin_dir_path( __FILE__ ) . '../views/booking_reasons/';
		}


		/**
		 * Renders the cancellation reason lightbox. Confirming it posts to core's
		 * customer_cabinet__request_cancellation / manage_booking_by_key__request_cancellation,
		 * and the reason is stored by OsFeatureBookingReasonsHelper on latepoint_booking_updated.
		 */
		public function cancellation_form() {
			$key = ! empty( $this->params['key'] ) ? sanitize_text_field( $this->params['key'] ) : '';

			if ( $key ) {
				$data = OsBookingHelper::get_booking_id_and_manage_ability_by_key( $key );
				if ( empty( $data ) ) {
					$this->send_json(
						array(
							'status'  => LATEPOINT_STATUS_ERROR,
							'message' => __( 'Invalid request', 'latepoint-pro-features' ),
						)
					);
					return;
				}
				$booking = new OsBookingModel( $data['booking_id'] );
				$allowed = ( 'agent' === $data['for'] ) ? true : OsCustomerHelper::can_cancel_booking( $booking );
			} else {
				if ( ! filter_var( $this->params['id'] ?? null, FILTER_VALIDATE_INT ) ) {
					exit();
				}
				$booking = new OsBookingModel( $this->params['id'] );
				$allowed = ! empty( $booking->id ) && ( OsAuthHelper::get_logged_in_customer_id() == $booking->customer_id ) && OsCustomerHelper::can_cancel_booking( $booking );
			}

			if ( empty( $booking->id ) || ! $allowed ) {
				$this->send_json(
					array(
						'status'  => LATEPOINT_STATUS_ERROR,
						'message' => __( 'Not allowed to cancel', 'latepoint-pro-features' ),
					)
				);
				return;
			}

			$this->vars['booking'] = $booking;
			$this->vars['key']     = $key;
			$this->set_layout( 'none' );
			$this->format_render_return( 'cancellation_form' );
		}
	}


endif;
