<?php
/**
 * LatePoint Abilities — Main loader & hook wiring.
 *
 * Registers the LatePoint category and all abilities with the WordPress
 * Abilities API (requires WordPress 6.9+). The guarded loader in latepoint.php
 * ensures this file is only included when the API is available.
 *
 * @package LatePoint\Abilities
 * @since   5.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LatePointAbilities {

	/** @var string[] Config class map: module slug => class name */
	private static array $config_modules = [
		'bookings'   => 'LatePointAbilitiesBookings',
		'customers'  => 'LatePointAbilitiesCustomers',
		'services'   => 'LatePointAbilitiesServices',
		'agents'     => 'LatePointAbilitiesAgents',
		'orders'     => 'LatePointAbilitiesOrders',
		'locations'  => 'LatePointAbilitiesLocations',
		'calendar'   => 'LatePointAbilitiesCalendar',
		'analytics'  => 'LatePointAbilitiesAnalytics',
		'activities' => 'LatePointAbilitiesActivities',
	];

	/**
	 * Boot: include module files and wire hooks.
	 */
	public static function init(): void {
		self::include_modules();

		// Reset LatePoint's current user on every request to ensure it reflects the latest WP user state.
		// This is crucial for accurate permission checks in abilities, especially when user roles or capabilities have changed during the request lifecycle.
		add_action( 'set_current_user', [ OsAuthHelper::class, 'reset_current_user' ], 1 );

		add_action( 'wp_abilities_api_categories_init', [ __CLASS__, 'register_category' ] );
		add_action( 'wp_abilities_api_init', [ __CLASS__, 'register_all' ] );
	}

	/**
	 * Include abstract base and all config module files.
	 */
	private static function include_modules(): void {
		$base = LATEPOINT_ABSPATH . 'lib/abilities/';
		require_once $base . 'abstract-ability.php';
		foreach ( array_keys( self::$config_modules ) as $slug ) {
			require_once $base . 'configs/class-latepoint-abilities-' . $slug . '.php';
		}
	}

	/**
	 * Register the top-level LatePoint category.
	 */
	public static function register_category(): void {
		wp_register_ability_category(
			'latepoint',
			[
				'label'       => __( 'LatePoint', 'latepoint' ),
				'description' => __( 'Appointment scheduling — bookings, customers, services, and staff.', 'latepoint' ),
			]
		);
	}

	/**
	 * Register all abilities from every module.
	 */
	public static function register_all(): void {
		foreach ( self::$config_modules as $class ) {
			foreach ( $class::get_abilities() as $ability ) {
				wp_register_ability( $ability->get_id(), $ability->to_definition() );
			}
		}
	}
}

LatePointAbilities::init();
