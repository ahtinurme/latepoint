<?php
/*
 * Copyright (c) 2024 LatePoint LLC. All rights reserved.
 */

class OsFeatureQrcodeHelper {

	public static function generate_qr_code_for_booking( string $html, OsBookingModel $booking ): string {
		if ( $booking->is_new_record() ) {
			return $html;
		}
		$ical_string = OsBookingHelper::generate_ical_event_string( $booking );
		$html       .= '<div class="qr-code-on-full-summary">';
		$html       .= '<div class="qr-code-booking-info">';
		$html       .= '<img src="' . ( new chillerlan\QRCode\QRCode() )->render( $booking->booking_code ) . '" alt="QR Code">';
		$html       .= '<div class="qr-code-label">' . __( 'Point your smartphone camera at this QR code and you will be able to copy your booking confirmation code', 'latepoint-pro-features' ) . '</div>';
		$html       .= '</div>';
		$html       .= '<div class="qr-code-vevent">';
		$html       .= '<img src="' . ( new chillerlan\QRCode\QRCode() )->render( $ical_string ) . '" alt="QR Code">';
		$html       .= '<div class="qr-code-label">' . __( 'Point your smartphone camera at this QR code and it will automatically add this appointment to your calendar', 'latepoint-pro-features' ) . '</div>';
		$html       .= '</div>';
		$html       .= '</div>';
		return $html;
	}


	public static function generate_qr_code_link( string $html, OsBookingModel $booking ): string {
		if ( $booking->is_new_record() ) {
			return $html;
		}
		$html .= '<div class="qr-show-trigger">';
		$html .= '<div><i class="latepoint-icon latepoint-icon-qrcode"></i></div>';
		$html .= '<div class="qr-code-trigger-label">' . esc_html__( 'Show QR', 'latepoint-pro-features' ) . '</div>';
		$html .= '</div>';

		return $html;
	}
}
