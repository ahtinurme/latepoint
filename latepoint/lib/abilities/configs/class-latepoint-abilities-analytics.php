<?php
/**
 * LatePoint Abilities — Analytics module factory.
 *
 * @package LatePoint\Abilities
 * @since   5.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LatePointAbilitiesAnalytics {

	/**
	 * @return LatePointAbstractAbility[]
	 */
	public static function get_abilities(): array {
		$base = LATEPOINT_ABSPATH . 'lib/abilities/analytics/';

		require_once $base . 'abstract-analytics-ability.php';
		require_once $base . 'get-dashboard-stats.php';
		require_once $base . 'get-daily-chart-data.php';
		require_once $base . 'get-top-services.php';
		require_once $base . 'get-pending-bookings-count.php';

		return [
			new LatePointAbilityGetDashboardStats(),
			new LatePointAbilityGetDailyChartData(),
			new LatePointAbilityGetTopServices(),
			new LatePointAbilityGetPendingBookingsCount(),
		];
	}
}
