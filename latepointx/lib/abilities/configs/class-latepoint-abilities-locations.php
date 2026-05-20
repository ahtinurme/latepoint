<?php
/**
 * LatePoint Abilities — Locations module factory.
 *
 * @package LatePoint\Abilities
 * @since   5.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LatePointAbilitiesLocations {

	/**
	 * @return LatePointAbstractAbility[]
	 */
	public static function get_abilities(): array {
		$base = LATEPOINT_ABSPATH . 'lib/abilities/locations/';

		require_once $base . 'abstract-location-ability.php';
		require_once $base . 'list-locations.php';
		require_once $base . 'get-location.php';
		require_once $base . 'create-location.php';
		require_once $base . 'update-location.php';
		require_once $base . 'delete-location.php';
		require_once $base . 'enable-location.php';
		require_once $base . 'disable-location.php';
		require_once $base . 'list-location-categories.php';
		require_once $base . 'get-location-agents.php';
		require_once $base . 'get-location-services.php';

		return [
			new LatePointAbilityListLocations(),
			new LatePointAbilityGetLocation(),
			new LatePointAbilityCreateLocation(),
			new LatePointAbilityUpdateLocation(),
			new LatePointAbilityDeleteLocation(),
			new LatePointAbilityEnableLocation(),
			new LatePointAbilityDisableLocation(),
			new LatePointAbilityListLocationCategories(),
			new LatePointAbilityGetLocationAgents(),
			new LatePointAbilityGetLocationServices(),
		];
	}
}
