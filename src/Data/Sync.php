<?php
/**
 * Keeps the report table in step with Tickets Commerce orders.
 *
 * @package SalesByStateReportForEventTickets
 */

namespace SBSET\Data;

use SBSET\Install\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Writes one row per order.
 */
class Sync {

	/**
	 * Post meta keys that affect the report row.
	 *
	 * @var string[]
	 */
	const MONEY_META = array(
		'_tec_tc_order_billing_country',
		'_tec_tc_order_billing_state',
		'_tec_tc_order_total_value',
		'_tec_tc_order_currency',
	);

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'save_post_tec_tc_order', array( $this, 'on_save_post' ), 20, 1 );
		add_action( 'deleted_post', array( $this, 'on_deleted_post' ), 20, 1 );
		add_action( 'wp_trash_post', array( $this, 'on_order_id' ), 20, 1 );
		add_action( 'untrashed_post', array( $this, 'on_order_id' ), 20, 1 );
		add_action( 'updated_post_meta', array( $this, 'on_meta' ), 20, 4 );
		add_action( 'added_post_meta', array( $this, 'on_meta' ), 20, 4 );
	}

	/**
	 * Handle a hook that passes an order ID first.
	 *
	 * @param mixed $order_id Order ID.
	 * @return void
	 */
	public function on_order_id( $order_id ) {
		$order_id = (int) $order_id;

		if ( ! $order_id ) {
			return;
		}

		$type = get_post_type( $order_id );

		if ( $type && 'tec_tc_order' !== $type ) {
			return;
		}

		$this->upsert( $order_id );
	}

	/**
	 * Handle save_post for the Tickets Commerce order post type.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function on_save_post( $post_id ) {
		$this->upsert( (int) $post_id );
	}

	/**
	 * Remove the row when an order post is deleted.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function on_deleted_post( $post_id ) {
		$post_id = (int) $post_id;

		if ( ! $post_id ) {
			return;
		}

		if ( 'tec_tc_order' !== get_post_type( $post_id ) && ! $this->row_exists( $post_id ) ) {
			return;
		}

		$this->delete( $post_id );
	}

	/**
	 * Refresh the row when billing, total, or currency meta changes.
	 *
	 * @param int    $meta_id    Meta ID.
	 * @param int    $object_id  Object ID.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value Meta value.
	 * @return void
	 */
	public function on_meta( $meta_id, $object_id, $meta_key = '', $meta_value = null ) {
		unset( $meta_id, $meta_value );

		if ( ! in_array( (string) $meta_key, self::MONEY_META, true ) ) {
			return;
		}

		if ( 'tec_tc_order' !== get_post_type( (int) $object_id ) ) {
			return;
		}

		$this->upsert( (int) $object_id );
	}

	/**
	 * Insert or update the row for one order.
	 *
	 * @param int $order_id Order ID.
	 * @return bool
	 */
	public function upsert( $order_id ) {
		global $wpdb;

		$order_id = (int) $order_id;

		if ( ! $order_id ) {
			return false;
		}

		$row = self::build_row( $order_id );

		if ( ! $row ) {
			$this->delete( $order_id );
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return false !== $wpdb->replace(
			Schema::table(),
			$row,
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%f', '%f' )
		);
	}

	/**
	 * Remove the row for an order.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function delete( $order_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( Schema::table(), array( 'order_id' => (int) $order_id ), array( '%d' ) );
	}

	/**
	 * Build the row for a Tickets Commerce order.
	 *
	 * Tickets Commerce has no shipping address and no first-party tax engine.
	 * The report groups by billing country and state stored on the order.
	 * Sales is the order total; tax and shipping columns stay at zero so the
	 * table schema matches the other Sales by State reports.
	 *
	 * @param int $order_id Order ID.
	 * @return array<string,mixed>|false
	 */
	public static function build_row( $order_id ) {
		global $wpdb;

		$order_id = (int) $order_id;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$post = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT ID, post_status, post_date_gmt, post_type
				 FROM {$wpdb->posts}
				 WHERE ID = %d",
				$order_id
			)
		);

		if ( ! $post || 'tec_tc_order' !== $post->post_type ) {
			return false;
		}

		if ( in_array( $post->post_status, array( 'auto-draft', 'trash', 'inherit' ), true ) ) {
			return false;
		}

		$country  = self::country_code( self::meta( $order_id, '_tec_tc_order_billing_country' ) );
		$state    = self::state_code( self::meta( $order_id, '_tec_tc_order_billing_state' ), $country );
		$total    = self::order_amount( $order_id );
		$created  = self::normalize_datetime( $post->post_date_gmt );
		$paid     = self::is_paid_status( $post->post_status ) ? $created : null;
		$currency = self::order_currency( $order_id );

		return array(
			'order_id'         => (int) $post->ID,
			'status'           => substr( sanitize_key( (string) $post->post_status ), 0, 32 ),
			'date_created'     => $created ? $created : '0000-00-00 00:00:00',
			'date_paid'        => $paid,
			'billing_country'  => $country,
			'billing_state'    => substr( $state, 0, 50 ),
			'shipping_country' => $country,
			'shipping_state'   => substr( $state, 0, 50 ),
			'currency'         => $currency,
			'total_sales'      => $total,
			'tax_total'        => 0,
			'shipping_total'   => 0,
			'net_total'        => $total,
		);
	}

	/**
	 * Order total stored by Tickets Commerce.
	 *
	 * @param int $order_id Order ID.
	 * @return float
	 */
	private static function order_amount( $order_id ) {
		$value = self::meta( $order_id, '_tec_tc_order_total_value' );
		$value = maybe_unserialize( $value );

		if ( is_object( $value ) && method_exists( $value, 'get_decimal' ) ) {
			return round( (float) $value->get_decimal(), 2 );
		}

		if ( is_array( $value ) && isset( $value['decimal'] ) ) {
			return round( (float) $value['decimal'], 2 );
		}

		return round( (float) $value, 2 );
	}

	/**
	 * Currency stored on the order, else Tickets Commerce's site currency.
	 *
	 * @param int $order_id Order ID.
	 * @return string
	 */
	private static function order_currency( $order_id ) {
		$currency = strtoupper( substr( (string) self::meta( $order_id, '_tec_tc_order_currency' ), 0, 3 ) );

		if ( ! preg_match( '/^[A-Z]{3}$/', $currency ) && class_exists( \TEC\Tickets\Commerce\Utils\Currency::class ) ) {
			$currency = strtoupper( substr( (string) \TEC\Tickets\Commerce\Utils\Currency::get_currency_code(), 0, 3 ) );
		}

		return preg_match( '/^[A-Z]{3}$/', $currency ) ? $currency : 'USD';
	}

	/**
	 * One post meta value.
	 *
	 * @param int    $order_id Order ID.
	 * @param string $key      Meta key.
	 * @return mixed
	 */
	private static function meta( $order_id, $key ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s LIMIT 1",
				(int) $order_id,
				$key
			)
		);
	}

	/**
	 * Whether an order status counts as paid for date_paid.
	 *
	 * @param string $status Post status.
	 * @return bool
	 */
	private static function is_paid_status( $status ) {
		return in_array(
			(string) $status,
			array( 'tec-tc-completed', 'tec-tc-refunded', 'tec-tc-reversed' ),
			true
		);
	}

	/**
	 * Two-letter country code.
	 *
	 * @param mixed $country Country.
	 * @return string
	 */
	private static function country_code( $country ) {
		$country = trim( (string) $country );

		if ( preg_match( '/^[A-Za-z]{2}$/', $country ) ) {
			return strtoupper( $country );
		}

		$names = array(
			'united states'  => 'US',
			'usa'            => 'US',
			'canada'         => 'CA',
			'united kingdom' => 'GB',
			'great britain'  => 'GB',
			'england'        => 'GB',
		);

		$key = strtolower( $country );

		return isset( $names[ $key ] ) ? $names[ $key ] : strtoupper( substr( $country, 0, 2 ) );
	}

	/**
	 * State / county as Tickets Commerce stores it.
	 *
	 * US and Canada use two-letter codes. The UK stores the county name.
	 *
	 * @param mixed  $state   State.
	 * @param string $country Country code.
	 * @return string
	 */
	private static function state_code( $state, $country ) {
		$state = trim( (string) $state );

		if ( in_array( $country, array( 'US', 'CA' ), true ) ) {
			return strtoupper( $state );
		}

		return $state;
	}

	/**
	 * Whether a report row already exists.
	 *
	 * Used after delete, when get_post_type() may already return empty.
	 *
	 * @param int $order_id Order ID.
	 * @return bool
	 */
	private function row_exists( $order_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT order_id FROM {$wpdb->prefix}sbset_order_state WHERE order_id = %d LIMIT 1",
				(int) $order_id
			)
		);
	}

	/**
	 * Normalise a datetime string.
	 *
	 * @param mixed $value Datetime.
	 * @return string|null
	 */
	private static function normalize_datetime( $value ) {
		$value = (string) $value;

		if ( '' === $value || '0000-00-00 00:00:00' === $value ) {
			return null;
		}

		$ts = strtotime( $value );

		return $ts ? gmdate( 'Y-m-d H:i:s', $ts ) : null;
	}
}
