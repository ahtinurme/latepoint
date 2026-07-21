<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/* @var $booking OsBookingModel */
/* @var $key string */

$confirm_route_name = empty( $key )
	? OsRouterHelper::build_route_name( 'customer_cabinet', 'request_cancellation' )
	: OsRouterHelper::build_route_name( 'manage_booking_by_key', 'request_cancellation' );
?>
<div class="latepoint-lightbox-heading">
    <h2><?php esc_html_e( 'Why are you cancelling this appointment?', 'latepoint-pro-features' ); ?></h2>
</div>
<div class="latepoint-lightbox-content">
    <div class="booking-reason-form cancellation-reason-form">
		<?php
		echo OsFormHelper::textarea_field( 'cancellation_reason', false, '', [ 'class' => 'latepoint-booking-reason', 'skip_id' => true, 'placeholder' => __( 'Enter your reason...', 'latepoint-pro-features' ) ] );
		echo OsFormHelper::hidden_field( 'id', $booking->id, [ 'class' => 'latepoint_booking_id', 'skip_id' => true ] );
		if ( ! empty( $key ) ) {
			echo OsFormHelper::hidden_field( 'key', $key, [ 'class' => 'latepoint_manage_booking_key', 'skip_id' => true ] );
		}
		// CSRF nonce consumed by core's request_cancellation.
		wp_nonce_field( 'cancel_booking_' . $booking->id, '_wpnonce', false );
		?>
    </div>
</div>
<div class="latepoint-lightbox-footer">
    <a href="#"
       data-route-name="<?php echo esc_attr( $confirm_route_name ); ?>"
       class="latepoint-btn latepoint-btn-danger latepoint-btn-block latepoint-confirm-cancellation-trigger"><?php esc_html_e( 'Confirm Cancellation', 'latepoint-pro-features' ); ?></a>
</div>
