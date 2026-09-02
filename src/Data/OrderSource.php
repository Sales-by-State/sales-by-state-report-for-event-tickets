<?php
/**
 * Locates Event Tickets Commerce orders.
 *
 * @package SalesByStateReportForEventTickets
 */

namespace SBSET\Data;

defined( 'ABSPATH' ) || exit;

/**
 * Reads order IDs from WordPress posts.
 *
 * Table names and the order post type are written as literals so every
 * identifier in the SQL is fixed.
 */
class OrderSource {

	/**
	 * Total number of Tickets Commerce orders on the site.
	 *
	 * @return int
	 */
	public static function count() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts}
			 WHERE post_type = 'tec_tc_order'
			   AND post_status NOT IN ( 'auto-draft', 'trash', 'inherit' )"
		);
	}

	/**
	 * Number of orders above a cursor.
	 *
	 * @param int $cursor Highest order ID already processed.
	 * @return int
	 */
	public static function count_after( $cursor ) {
		global $wpdb;

		$cursor = max( 0, (int) $cursor );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts}
				 WHERE post_type = 'tec_tc_order'
				   AND post_status NOT IN ( 'auto-draft', 'trash', 'inherit' )
				   AND ID > %d",
				$cursor
			)
		);
	}

	/**
	 * The next batch of order IDs after a cursor.
	 *
	 * @param int $cursor Highest order ID already processed.
	 * @param int $limit  Batch size.
	 * @return int[]
	 */
	public static function ids_after( $cursor, $limit ) {
		global $wpdb;

		$cursor = max( 0, (int) $cursor );
		$limit  = max( 1, (int) $limit );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts}
				 WHERE post_type = 'tec_tc_order'
				   AND post_status NOT IN ( 'auto-draft', 'trash', 'inherit' )
				   AND ID > %d
				 ORDER BY ID ASC
				 LIMIT %d",
				$cursor,
				$limit
			)
		);

		return array_map( 'intval', (array) $ids );
	}
}
