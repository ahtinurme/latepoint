<?php
/**
 * LatePoint Abilities — Bookings module factory.
 *
 * Includes all individual booking ability files and returns instances.
 *
 * @package LatePoint\Abilities
 * @since   5.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LatePointAbilitiesBookings {

	/**
	 * @return LatePointAbstractAbility[]
	 */
	public static function get_abilities(): array {
		$base = LATEPOINT_ABSPATH . 'lib/abilities/bookings/';

		require_once $base . 'abstract-booking-ability.php';
		require_once $base . 'list-bookings.php';
		require_once $base . 'get-booking.php';
		require_once $base . 'get-bookings-for-date.php';
		require_once $base . 'get-upcoming-bookings.php';
		require_once $base . 'create-booking.php';
		require_once $base . 'update-booking.php';
		require_once $base . 'delete-booking.php';
		require_once $base . 'change-booking-status.php';
		require_once $base . 'approve-booking.php';
		require_once $base . 'cancel-booking.php';
		require_once $base . 'reschedule-booking.php';
		require_once $base . 'get-booking-stats.php';
		require_once $base . 'get-bookings-per-day.php';
		require_once $base . 'get-booking-statuses.php';

		return [
			new LatePointAbilityListBookings(),
			new LatePointAbilityGetBooking(),
			new LatePointAbilityGetBookingsForDate(),
			new LatePointAbilityGetUpcomingBookings(),
			new LatePointAbilityCreateBooking(),
			new LatePointAbilityUpdateBooking(),
			new LatePointAbilityDeleteBooking(),
			new LatePointAbilityChangeBookingStatus(),
			new LatePointAbilityApproveBooking(),
			new LatePointAbilityCancelBooking(),
			new LatePointAbilityRescheduleBooking(),
			new LatePointAbilityGetBookingStats(),
			new LatePointAbilityGetBookingsPerDay(),
			new LatePointAbilityGetBookingStatuses(),
		];
	}
}
