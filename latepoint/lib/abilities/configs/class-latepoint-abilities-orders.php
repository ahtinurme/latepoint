<?php
/**
 * LatePoint Abilities — Orders & Payments module factory.
 *
 * @package LatePoint\Abilities
 * @since   5.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LatePointAbilitiesOrders {

	/**
	 * @return LatePointAbstractAbility[]
	 */
	public static function get_abilities(): array {
		$base = LATEPOINT_ABSPATH . 'lib/abilities/orders/';

		require_once $base . 'abstract-order-ability.php';
		require_once $base . 'list-orders.php';
		require_once $base . 'get-order.php';
		require_once $base . 'create-order.php';
		require_once $base . 'update-order.php';
		require_once $base . 'delete-order.php';
		require_once $base . 'change-order-status.php';
		require_once $base . 'get-order-price-breakdown.php';
		require_once $base . 'list-invoices.php';
		require_once $base . 'get-invoice.php';
		require_once $base . 'create-invoice.php';
		require_once $base . 'change-invoice-status.php';
		require_once $base . 'list-transactions.php';
		require_once $base . 'get-transaction.php';
		require_once $base . 'refund-transaction.php';

		return [
			new LatePointAbilityListOrders(),
			new LatePointAbilityGetOrder(),
			new LatePointAbilityCreateOrder(),
			new LatePointAbilityUpdateOrder(),
			new LatePointAbilityDeleteOrder(),
			new LatePointAbilityChangeOrderStatus(),
			new LatePointAbilityGetOrderPriceBreakdown(),
			new LatePointAbilityListInvoices(),
			new LatePointAbilityGetInvoice(),
			new LatePointAbilityCreateInvoice(),
			new LatePointAbilityChangeInvoiceStatus(),
			new LatePointAbilityListTransactions(),
			new LatePointAbilityGetTransaction(),
			new LatePointAbilityRefundTransaction(),
		];
	}
}
