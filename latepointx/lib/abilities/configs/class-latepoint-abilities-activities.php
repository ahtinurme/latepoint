<?php
/**
 * LatePoint Abilities — Activities module factory.
 *
 * @package LatePoint\Abilities
 * @since   5.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LatePointAbilitiesActivities {

	/**
	 * @return LatePointAbstractAbility[]
	 */
	public static function get_abilities(): array {
		$base = LATEPOINT_ABSPATH . 'lib/abilities/activities/';

		require_once $base . 'abstract-activity-ability.php';
		require_once $base . 'list-activities.php';
		require_once $base . 'get-activity.php';

		return [
			new LatePointAbilityListActivities(),
			new LatePointAbilityGetActivity(),
		];
	}
}
