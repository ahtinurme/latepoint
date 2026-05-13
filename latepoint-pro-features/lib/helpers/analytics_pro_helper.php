<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class OsAnalyticsProHelper {

	/**
	 * Initialize BSF Analytics for Pro plugin.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'bsf_core_stats', [ __CLASS__, 'add_latepoint_pro_analytics_data' ], 11 );
	}

	/**
	 * Track pro license activation event.
	 *
	 * @return void
	 */
	public static function on_license_activated() {
		if ( is_callable( [ 'OsAnalyticsHelper', 'events' ] ) ) {
			OsAnalyticsHelper::events()->track( 'pro_license_activated', LATEPOINT_ADDON_PRO_VERSION );
		}
	}

	/**
	 * Add LatePoint Pro specific analytics data.
	 *
	 * Merges pro data into the existing LatePoint stats payload at priority 11,
	 * after the free plugin has added its data at priority 10.
	 *
	 * @param array $stats_data Existing stats data.
	 * @return array
	 */
	public static function add_latepoint_pro_analytics_data( $stats_data ) {
		if ( empty( $stats_data['plugin_data']['latepoint'] ) || ! is_array( $stats_data['plugin_data']['latepoint'] ) ) {
			return $stats_data;
		}

		$latepoint = &$stats_data['plugin_data']['latepoint'];

		// Pro version.
		$latepoint['pro_version'] = LATEPOINT_ADDON_PRO_VERSION;

		return $stats_data;
	}
}
