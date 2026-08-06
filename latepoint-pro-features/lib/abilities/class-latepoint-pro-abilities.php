<?php
/**
 * LatePoint Pro abilities.
 *
 * @package LatePoint\Pro\Abilities
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LatePointProAbilities {

	/**
	 * Boot the Pro abilities integration.
	 */
	public static function init(): void {
		add_filter( 'latepoint_ability_config_modules', array( __CLASS__, 'register_coupon_ability_module' ) );
	}

	/**
	 * Register the Pro coupons abilities module with core's Abilities registry.
	 *
	 * @param array $modules
	 * @return array
	 */
	public static function register_coupon_ability_module( array $modules ): array {
		$modules['coupons'] = array(
			'class'    => 'LatePointAbilitiesCoupons',
			'base_dir' => LATEPOINT_ADDON_PRO_LIB_ABSPATH . 'abilities/',
		);

		return $modules;
	}
}

LatePointProAbilities::init();
