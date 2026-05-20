<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * Removes all LatePoint database tables and plugin settings when
 * the "Remove all data on uninstall" option is enabled in Settings > General.
 *
 * @package LatePoint
 */

// Exit if uninstall is not called from WordPress.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$prefix = $wpdb->prefix;

// Read the setting directly from the custom settings table, since the plugin
// classes are not loaded during uninstall.
$settings_table = $prefix . 'latepoint_settings';

// Check whether the settings table still exists before querying it.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- We need to run a direct query here since the plugin's DB classes are not available during uninstall.
$table_exists = $wpdb->get_var(
	$wpdb->prepare( 'SHOW TABLES LIKE %s', $settings_table )
);

if ( $table_exists ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- We need to run a direct query here since the plugin's DB classes are not available during uninstall.
	$remove_data = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT value FROM %i WHERE name = %s LIMIT 1",
			$settings_table,
			'remove_data_on_plugin_delete'
		)
	);
} else {
	$remove_data = null;
}

// Only proceed with full data removal if the toggle is explicitly enabled ("on").
if ( 'on' !== $remove_data ) {
	return;
}

// -------------------------------------------------------------------------
// Drop all LatePoint tables.
// The list mirrors OsDatabaseHelper::get_all_latepoint_tables() plus the
// two tables that are defined as constants but were missing from that method.
// -------------------------------------------------------------------------
$tables = [
	$prefix . 'latepoint_bundles',
	$prefix . 'latepoint_bundles_services',
	$prefix . 'latepoint_bookings',
	$prefix . 'latepoint_sessions',
	$prefix . 'latepoint_services',
	$prefix . 'latepoint_settings',
	$prefix . 'latepoint_service_categories',
	$prefix . 'latepoint_work_periods',
	$prefix . 'latepoint_custom_prices',
	$prefix . 'latepoint_agents_services',
	$prefix . 'latepoint_activities',
	$prefix . 'latepoint_transactions',
	$prefix . 'latepoint_transaction_refunds',
	$prefix . 'latepoint_transaction_intents',
	$prefix . 'latepoint_agents',
	$prefix . 'latepoint_customers',
	$prefix . 'latepoint_customer_meta',
	$prefix . 'latepoint_service_meta',
	$prefix . 'latepoint_booking_meta',
	$prefix . 'latepoint_agent_meta',
	$prefix . 'latepoint_bundle_meta',
	$prefix . 'latepoint_steps',
	$prefix . 'latepoint_step_settings',
	$prefix . 'latepoint_locations',
	$prefix . 'latepoint_location_categories',
	$prefix . 'latepoint_processes',
	$prefix . 'latepoint_process_jobs',
	$prefix . 'latepoint_carts',
	$prefix . 'latepoint_cart_meta',
	$prefix . 'latepoint_cart_items',
	$prefix . 'latepoint_orders',
	$prefix . 'latepoint_order_meta',
	$prefix . 'latepoint_order_items',
	$prefix . 'latepoint_order_intents',
	$prefix . 'latepoint_order_intent_meta',
	$prefix . 'latepoint_order_invoices',
	$prefix . 'latepoint_payment_requests',
	$prefix . 'latepoint_recurrences',
	$prefix . 'latepoint_customer_otp_codes',
	$prefix . 'latepoint_blocked_periods',
];

foreach ( $tables as $table ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange -- We need to run a direct query here since the plugin's DB classes are not available during uninstall.
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table ) );
}

// -------------------------------------------------------------------------
// Delete WordPress options created by LatePoint.
// -------------------------------------------------------------------------
$options_to_delete = [
	'latepoint_db_version',
	'latepoint_wizard_visited',
	'latepoint_redirect_to_wizard',
];

foreach ( $options_to_delete as $option ) {
	delete_option( $option );
}

// Remove any transients prefixed with latepoint_.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- We need to run a direct query here since the plugin's DB classes are not available during uninstall.
$wpdb->query(
	"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_latepoint_%' OR option_name LIKE '_transient_timeout_latepoint_%'"
);
