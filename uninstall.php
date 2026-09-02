<?php
/**
 * Removes the plugin's data when it is deleted.
 *
 * @package SalesByStateReportForEventTickets
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

$sbset_options = array(
	'sbset_db_version',
	'sbset_backfill_cursor',
	'sbset_year_start',
);

foreach ( $sbset_options as $sbset_option ) {
	delete_option( $sbset_option );
}

if ( is_multisite() ) {
	foreach ( $sbset_options as $sbset_option ) {
		delete_site_option( $sbset_option );
	}
}

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}sbset_order_state" );

if ( function_exists( 'as_unschedule_all_actions' ) ) {
	as_unschedule_all_actions( 'sbset_backfill_batch', array(), 'sales-by-state-report-for-event-tickets' );
}
