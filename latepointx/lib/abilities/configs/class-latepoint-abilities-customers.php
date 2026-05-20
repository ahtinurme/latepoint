<?php
/**
 * LatePoint Abilities — Customers module factory.
 *
 * @package LatePoint\Abilities
 * @since   5.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LatePointAbilitiesCustomers {

	/**
	 * @return LatePointAbstractAbility[]
	 */
	public static function get_abilities(): array {
		$base = LATEPOINT_ABSPATH . 'lib/abilities/customers/';

		require_once $base . 'abstract-customer-ability.php';
		require_once $base . 'list-customers.php';
		require_once $base . 'get-customer.php';
		require_once $base . 'search-customers.php';
		require_once $base . 'get-customer-by-email.php';
		require_once $base . 'create-customer.php';
		require_once $base . 'update-customer.php';
		require_once $base . 'delete-customer.php';
		require_once $base . 'connect-customer-to-wp-user.php';
		require_once $base . 'get-customer-bookings.php';
		require_once $base . 'get-customer-orders.php';
		require_once $base . 'get-total-customers-count.php';

		return [
			new LatePointAbilityListCustomers(),
			new LatePointAbilityGetCustomer(),
			new LatePointAbilitySearchCustomers(),
			new LatePointAbilityGetCustomerByEmail(),
			new LatePointAbilityCreateCustomer(),
			new LatePointAbilityUpdateCustomer(),
			new LatePointAbilityDeleteCustomer(),
			new LatePointAbilityConnectCustomerToWpUser(),
			new LatePointAbilityGetCustomerBookings(),
			new LatePointAbilityGetCustomerOrders(),
			new LatePointAbilityGetTotalCustomersCount(),
		];
	}
}
