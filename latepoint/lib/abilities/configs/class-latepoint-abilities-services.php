<?php
/**
 * LatePoint Abilities — Services module factory.
 *
 * @package LatePoint\Abilities
 * @since   5.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LatePointAbilitiesServices {

	/**
	 * @return LatePointAbstractAbility[]
	 */
	public static function get_abilities(): array {
		$base = LATEPOINT_ABSPATH . 'lib/abilities/services/';

		require_once $base . 'abstract-service-ability.php';
		require_once $base . 'get-services.php';
		require_once $base . 'get-service.php';
		require_once $base . 'list-service-categories.php';
		require_once $base . 'create-service.php';
		require_once $base . 'update-service.php';
		require_once $base . 'delete-service.php';
		require_once $base . 'enable-service.php';
		require_once $base . 'disable-service.php';
		require_once $base . 'duplicate-service.php';
		require_once $base . 'get-service-agents.php';
		require_once $base . 'get-service-bookings.php';

		return [
			new LatePointAbilityGetServices(),
			new LatePointAbilityGetService(),
			new LatePointAbilityListServiceCategories(),
			new LatePointAbilityCreateService(),
			new LatePointAbilityUpdateService(),
			new LatePointAbilityDeleteService(),
			new LatePointAbilityEnableService(),
			new LatePointAbilityDisableService(),
			new LatePointAbilityDuplicateService(),
			new LatePointAbilityGetServiceAgents(),
			new LatePointAbilityGetServiceBookings(),
		];
	}
}
