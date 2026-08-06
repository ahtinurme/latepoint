<?php
/**
 * Coupons abilities module factory (Pro).
 *
 * @package LatePoint\Pro\Abilities
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LatePointAbilitiesCoupons {

	/**
	 * Get all coupon abilities.
	 *
	 * @return LatePointAbstractAbility[]
	 */
	public static function get_abilities(): array {
		// Defensive: the core abstract is loaded by the time register_all() calls this.
		if ( ! class_exists( 'LatePointAbstractAbility' ) ) {
			return [];
		}

		$base = LATEPOINT_ADDON_PRO_LIB_ABSPATH . 'abilities/coupons/';

		require_once $base . 'abstract-coupon-ability.php';
		require_once $base . 'create-coupon.php';
		require_once $base . 'update-coupon.php';
		require_once $base . 'get-coupon.php';
		require_once $base . 'list-coupons.php';

		return [
			new LatePointAbilityCreateCoupon(),
			new LatePointAbilityUpdateCoupon(),
			new LatePointAbilityGetCoupon(),
			new LatePointAbilityListCoupons(),
		];
	}
}