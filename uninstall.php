<?php
/**
 * Uninstall Handler.
 *
 * Runs when the plugin is deleted. Purges database table and settings if option is active.
 *
 * @package Onu Exchange Rates and Converter for BNR
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Only purge if user explicitly configured cleanup on uninstall.
if ( get_option( 'onuexra_delete_on_uninstall' ) ) {
	global $wpdb;

	// Drop custom exchange rates table.
	$onuexra_table_name = $wpdb->prefix . 'onuexra_exchange_rates';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
	$wpdb->query( "DROP TABLE IF EXISTS " . esc_sql( $onuexra_table_name ) );

	// Delete all saved options.
	$onuexra_options_to_delete = array(
		'onuexra_db_version',
		'onuexra_fetch_time',
		'onuexra_default_currencies',
		'onuexra_decimals',
		'onuexra_decimal_sep',
		'onuexra_thousands_sep',
		'onuexra_keep_history_days',
		'onuexra_delete_on_uninstall',
		'onuexra_fetch_logs',
	);

	foreach ( $onuexra_options_to_delete as $onuexra_option ) {
		delete_option( $onuexra_option );
	}

	// Delete cached object metrics.
	wp_cache_delete( 'onuexra_today_rates', 'onuexra' );
}
