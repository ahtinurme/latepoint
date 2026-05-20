<?php
/**
 * LatePoint Abilities — Calendar module factory.
 *
 * @package LatePoint\Abilities
 * @since   5.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LatePointAbilitiesCalendar {

	/**
	 * @return LatePointAbstractAbility[]
	 */
	public static function get_abilities(): array {
		$base = LATEPOINT_ABSPATH . 'lib/abilities/calendar/';

		require_once $base . 'abstract-calendar-ability.php';
		require_once $base . 'get-available-slots.php';
		require_once $base . 'check-slot-availability.php';
		require_once $base . 'get-work-schedule.php';
		require_once $base . 'list-off-periods.php';
		require_once $base . 'add-off-period.php';
		require_once $base . 'remove-off-period.php';

		return [
			new LatePointAbilityGetAvailableSlots(),
			new LatePointAbilityCheckSlotAvailability(),
			new LatePointAbilityGetWorkSchedule(),
			new LatePointAbilityListOffPeriods(),
			new LatePointAbilityAddOffPeriod(),
			new LatePointAbilityRemoveOffPeriod(),
		];
	}
}
