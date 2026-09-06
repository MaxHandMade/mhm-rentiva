<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Customers;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Customers Performance Optimizer Class.
 *
 * @package MHMRentiva\Admin\Customers
 */





use MHMRentiva\Admin\Core\CurrencyHelper;
use MHMRentiva\Admin\Core\Utilities\CacheManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Optimizes customer data performance.
 * - Cache system
 * - Optimized queries
 *
 * Read-only by design: every method here queries or caches. Customer accounts are
 * written on the screens and routes that own them, and the check each one owes
 * depends on whether it acts on an existing account or makes a new one:
 *
 *   - CustomersPage::render_customer_edit() and CustomersRestController::bulk_delete()
 *     take a target from the request, so they ask the per-target question beside the
 *     write -- current_user_can( 'edit_user'/'delete_user', $id ) AND
 *     CustomerIdentity::is_customer( $id ). Neither implies the other: the capability
 *     is about the caller, CustomerIdentity is about the target.
 *   - AddCustomerPage::render() creates an account, so there is no existing target for
 *     a per-object check to be about. It is gated on create_users -- the capability
 *     WordPress itself requires to make a user -- before anything else runs, plus a
 *     nonce.
 *
 * bin/user-write-inventory.txt records both shapes for every write site in the plugin.
 */
final class CustomersOptimizer {



	private const CACHE_PREFIX = 'mhmrentiva_customers_';
	private const CACHE_TTL    = 900; // 15 minutes

	/**
	 * Bumped when the MEANING of a cached figure changes, so a deploy is not
	 * followed by 15 minutes of the old numbers. One constant, because writing
	 * the token in three places is how get_customer_details_optimized()'s cache
	 * key and clear_cache()'s copy of it drift apart.
	 */
	private const CACHE_SHAPE = 'v3';

	/**
	 * Fingerprint of everything that changes how money RENDERS.
	 *
	 * These payloads carry pre-formatted amounts (`total_spent`, `amount`), so a
	 * key that ignored the currency and its placement served yesterday's symbol
	 * for up to the full TTL after the WooCommerce setting was flipped. Part of
	 * the key, not part of the value.
	 *
	 * @return string
	 */
	private static function currency_fingerprint(): string {
		return substr(
			md5(
				CurrencyHelper::get_currency_code() . '|'
				. CurrencyHelper::get_currency_symbol() . '|'
				. CurrencyHelper::get_currency_position() . '|'
				. (string) CurrencyHelper::get_price_decimals()
			),
			0,
			8
		);
	}

	/**
	 * Get customer list in optimized way
	 *
	 * @param int    $page Page number
	 * @param int    $per_page Records per page
	 * @param string $search Search term
	 * @return array
	 */
	public static function get_customers_optimized( int $page = 1, int $per_page = 20, string $search = '', string $sort_by = 'last_booking', string $sort_dir = 'desc', string $status = 'all' ): array {
		// The sort key is mapped to a SELECT alias, never to a raw expression, so
		// the value bound to the %i below is always one of these six identifiers.
		$column_map = array(
			'name'         => 'customer_name',
			'email'        => 'customer_email',
			'bookings'     => 'booking_count',
			'total_spent'  => 'total_spent',
			'last_booking' => 'last_booking',
			'date'         => 'created_date',
		);
		$order_col  = $column_map[ $sort_by ] ?? 'last_booking';
		$order_asc  = 'asc' === strtolower( $sort_dir );

		// Restrict the list to accounts that are actually customers of this plugin.
		// Without this the query returns essentially every account on the site: it
		// starts FROM wp_users and only LEFT JOINs the bookings, so the booking is
		// optional and the sole account filters were `ID > 1` and
		// `user_login != 'admin'`. Administrators, editors and other plugins' users
		// were listed as customers, with their contact details, and counted in the
		// total and the pagination.
		//
		// The definition lives in CustomerIdentity, which the detail, delete and
		// export routes already use; expressing it in SQL rather than filtering
		// afterwards is what keeps LIMIT/OFFSET and the total honest.
		$membership = CustomerIdentity::sql_is_customer();

		// The one rule for "this booking belongs to this account" (CustomerIdentity),
		// correlated to `u` and `p` below. Computed once and spliced into all three
		// statements this method runs, so the SELECT, its counterpart total and the
		// other sort direction can never answer the ownership question differently.
		$owns = CustomerIdentity::sql_user_owns_booking();

		// Status filters are independent predicates (they may overlap): `new` means
		// registered in the last 30 days, `active` means a booking in the last 90
		// days (same window as the active_90d stat), `vip` means at least the
		// filterable minimum number of bookings. Anything unknown collapses to
		// 'all' so the bound toggles below never see a stray value.
		if ( ! in_array( $status, array( 'all', 'new', 'active', 'vip' ), true ) ) {
			$status = 'all';
		}
		$vip_min = self::get_vip_min_bookings();

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $membership
		// is CustomerIdentity::sql_is_customer() and $owns is
		// CustomerIdentity::sql_user_owns_booking(): each a single $wpdb->prepare() call
		// with every dynamic value bound. WordPress provides no placeholder for splicing
		// a composed SQL fragment into another statement, so the composition itself is
		// what the sniff sees. Scoped to this region and re-enabled straight after.
		//
		// The key carries the status, the VIP threshold and the currency fingerprint
		// as well as the paging/sort inputs: every one of them changes the rows or
		// the formatting this method returns, so leaving any of them out serves one
		// filter's result set under another filter's key.
		$cache_key = self::CACHE_PREFIX . 'list_' . md5( $page . '_' . $per_page . '_' . $search . '_' . $sort_by . '_' . $sort_dir . '_' . $status . '_' . $vip_min . '_' . self::currency_fingerprint() . '_' . self::CACHE_SHAPE );

		// Check cache
		$cached_data = CacheManager::get_cache( 'customers', $cache_key );
		if ( $cached_data !== false ) {
			return $cached_data;
		}

		global $wpdb;

		// Calculate offset
		$offset = ( $page - 1 ) * $per_page;

		// An empty search term is expressed as a value rather than as a missing
		// clause: `LIKE '%%'` matches every row of a NOT NULL column, which is
		// what the old unfiltered branch of this query returned. One statement
		// instead of two near-identical copies.
		$search_like = '%' . $wpdb->esc_like( $search ) . '%';

		// ASC and DESC cannot be placeholders, so the direction picks between two
		// literal statements; everything else, the ORDER BY column included, is
		// bound.
		$results = $order_asc
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Cross-table aggregate with no core API; the whole result is cached below.
			? $wpdb->get_results( $wpdb->prepare( "
            SELECT
                u.ID as user_id,
                u.display_name as customer_name,
                u.user_email as customer_email,
                u.user_registered as created_date,
                um_phone.meta_value as phone,
                um_address.meta_value as address,
                COUNT(DISTINCT p.ID) as booking_count,
                COALESCE(SUM(CAST(price_meta.meta_value AS DECIMAL(10,2))), 0) as total_spent,
                MAX(p.post_date) as last_booking
            FROM {$wpdb->users} u
            LEFT JOIN {$wpdb->posts} p ON p.post_type = 'mhmrentiva_booking'
                AND p.post_status IN ('publish', 'private', 'pending')
                AND {$owns}
            LEFT JOIN {$wpdb->postmeta} price_meta ON p.ID = price_meta.post_id
                AND price_meta.meta_key = '_mhmrentiva_total_price'
            LEFT JOIN {$wpdb->usermeta} um_phone ON u.ID = um_phone.user_id
                AND um_phone.meta_key = 'mhmrentiva_phone'
            LEFT JOIN {$wpdb->usermeta} um_address ON u.ID = um_address.user_id
                AND um_address.meta_key = 'mhmrentiva_address'
            WHERE u.ID > 1
                AND u.user_login != 'admin'
                AND {$membership}
                AND u.user_email != ''
                AND (u.display_name LIKE %s OR u.user_email LIKE %s)
                AND ( %s != 'new' OR u.user_registered >= DATE_SUB(NOW(), INTERVAL 30 DAY) )
            GROUP BY u.ID, u.display_name, u.user_email, u.user_registered, um_phone.meta_value, um_address.meta_value
            HAVING ( %s != 'active' OR MAX(p.post_date) >= DATE_SUB(NOW(), INTERVAL 90 DAY) )
                AND ( %s != 'vip' OR COUNT(DISTINCT p.ID) >= %d )
            ORDER BY %i ASC
            LIMIT %d OFFSET %d
            ", $search_like, $search_like, $status, $status, $status, $vip_min, $order_col, (int) $per_page, (int) $offset ) )
			: $wpdb->get_results( $wpdb->prepare( "
            SELECT
                u.ID as user_id,
                u.display_name as customer_name,
                u.user_email as customer_email,
                u.user_registered as created_date,
                um_phone.meta_value as phone,
                um_address.meta_value as address,
                COUNT(DISTINCT p.ID) as booking_count,
                COALESCE(SUM(CAST(price_meta.meta_value AS DECIMAL(10,2))), 0) as total_spent,
                MAX(p.post_date) as last_booking
            FROM {$wpdb->users} u
            LEFT JOIN {$wpdb->posts} p ON p.post_type = 'mhmrentiva_booking'
                AND p.post_status IN ('publish', 'private', 'pending')
                AND {$owns}
            LEFT JOIN {$wpdb->postmeta} price_meta ON p.ID = price_meta.post_id
                AND price_meta.meta_key = '_mhmrentiva_total_price'
            LEFT JOIN {$wpdb->usermeta} um_phone ON u.ID = um_phone.user_id
                AND um_phone.meta_key = 'mhmrentiva_phone'
            LEFT JOIN {$wpdb->usermeta} um_address ON u.ID = um_address.user_id
                AND um_address.meta_key = 'mhmrentiva_address'
            WHERE u.ID > 1
                AND u.user_login != 'admin'
                AND {$membership}
                AND u.user_email != ''
                AND (u.display_name LIKE %s OR u.user_email LIKE %s)
                AND ( %s != 'new' OR u.user_registered >= DATE_SUB(NOW(), INTERVAL 30 DAY) )
            GROUP BY u.ID, u.display_name, u.user_email, u.user_registered, um_phone.meta_value, um_address.meta_value
            HAVING ( %s != 'active' OR MAX(p.post_date) >= DATE_SUB(NOW(), INTERVAL 90 DAY) )
                AND ( %s != 'vip' OR COUNT(DISTINCT p.ID) >= %d )
            ORDER BY %i DESC
            LIMIT %d OFFSET %d
            ", $search_like, $search_like, $status, $status, $status, $vip_min, $order_col, (int) $per_page, (int) $offset ) );

		if ( empty( $results ) ) {
			$data = array(
				'customers' => array(),
				'total'     => 0,
			);
			CacheManager::set_cache( 'customers', $cache_key, $data, self::CACHE_TTL );
			return $data;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Counterpart of the SELECT above; cached with it.
		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				// Counts through the same derived table the SELECT above builds:
				// the status filters live in GROUP BY/HAVING, so a flat
				// COUNT(DISTINCT u.ID) would count rows the list itself does not
				// show and hand the pager a total that never matches the pages.
				// `$membership` is carried in here too -- a total that counts
				// non-customers is the same defect as a list that shows them.
				"SELECT COUNT(*) FROM (
                SELECT u.ID
                FROM {$wpdb->users} u
                LEFT JOIN {$wpdb->posts} p ON p.post_type = 'mhmrentiva_booking'
                    AND p.post_status IN ('publish', 'private', 'pending')
                    AND {$owns}
                WHERE u.ID > 1
                    AND u.user_login != 'admin'
                    AND {$membership}
                    AND u.user_email != ''
                    AND (u.display_name LIKE %s OR u.user_email LIKE %s)
                    AND ( %s != 'new' OR u.user_registered >= DATE_SUB(NOW(), INTERVAL 30 DAY) )
                GROUP BY u.ID
                HAVING ( %s != 'active' OR MAX(p.post_date) >= DATE_SUB(NOW(), INTERVAL 90 DAY) )
                    AND ( %s != 'vip' OR COUNT(DISTINCT p.ID) >= %d )
            ) filtered",
				$search_like,
				$search_like,
				$status,
				$status,
				$status,
				$vip_min
			)
		);

		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// Format data
		$currency  = CurrencyHelper::get_currency_symbol();
		$customers = array();

		foreach ( $results as $result ) {
			$customers[] = array(
				'id'            => (int) $result->user_id,
				'name'          => $result->customer_name ? $result->customer_name : $result->customer_email,
				'email'         => $result->customer_email,
				'phone'         => $result->phone ? $result->phone : '-',
				'address'       => $result->address ? $result->address : '-',
				'booking_count' => (int) $result->booking_count,
				// Canonical, symbol included. Clients must render this as-is; the
				// old shape was a bare number that every client concatenated the
				// symbol onto from the LEFT, contradicting a `right` position.
				'total_spent'   => CurrencyHelper::format_price( (float) $result->total_spent, 2 ),
				'last_booking'  => $result->last_booking ? gmdate( 'd.m.Y', strtotime( $result->last_booking ) ) : '-',
				'created_date'  => $result->created_date ? gmdate( 'd.m.Y', strtotime( $result->created_date ) ) : '-',
				'currency'      => $currency,
				'status'        => self::derive_status(
					(int) $result->booking_count,
					$result->created_date ? (int) strtotime( $result->created_date ) : 0,
					$result->last_booking ? (int) strtotime( $result->last_booking ) : 0
				),
			);
		}

		$data = array(
			'customers'   => $customers,
			'total'       => (int) $total,
			'page'        => $page,
			'per_page'    => $per_page,
			'total_pages' => ceil( $total / $per_page ),
		);

		// Cache save
		CacheManager::set_cache( 'customers', $cache_key, $data, self::CACHE_TTL );

		return $data;
	}

	/**
	 * Minimum booking count for the VIP tag (filterable, floor of 1).
	 *
	 * @return int
	 */
	public static function get_vip_min_bookings(): int {
		return max( 1, (int) apply_filters( 'mhmrentiva_customers_vip_min_bookings', 5 ) );
	}

	/**
	 * Derive the display status tag for a customer row.
	 *
	 * Display priority is VIP > new > active > none; the list *filters* use the
	 * underlying predicates independently, so a VIP row still matches the
	 * `active` filter when its last booking is inside the 90-day window.
	 *
	 * @param int $booking_count   Lifetime booking count.
	 * @param int $registered_ts   Registration timestamp (0 = unknown).
	 * @param int $last_booking_ts Latest booking timestamp (0 = none).
	 * @return string One of vip|new|active|none.
	 */
	public static function derive_status( int $booking_count, int $registered_ts, int $last_booking_ts ): string {
		if ( $booking_count >= self::get_vip_min_bookings() ) {
			return 'vip';
		}
		// The thresholds are positive epoch values, so the 0 sentinels for
		// "unknown" / "none" can never pass these comparisons.
		if ( $registered_ts >= strtotime( '-30 days' ) ) {
			return 'new';
		}
		if ( $last_booking_ts >= strtotime( '-90 days' ) ) {
			return 'active';
		}
		return 'none';
	}

	/**
	 * Get customer statistics in optimized way
	 *
	 * @return array
	 */
	public static function get_customer_stats_optimized(): array {
		// CACHE_SHAPE: the key changes when active_90d / avg_spend / the ownership
		// rule change the payload, so a stale pre-redesign cache entry can never
		// serve the old shape.
		$cache_key = self::CACHE_PREFIX . 'stats_' . self::CACHE_SHAPE;

		// Check cache
		$cached_data = CacheManager::get_cache( 'customers', $cache_key );
		if ( $cached_data !== false ) {
			return $cached_data;
		}

		global $wpdb;

		// The population is the one the list underneath these cards uses, and it
		// is expressed once, in CustomerIdentity. This query used to INNER JOIN
		// bookings through `_mhmrentiva_customer_email` instead, which made the
		// cards answer a narrower question than their own label: a customer added
		// by hand had no booking and was not counted, so the screen could read
		// "Total Customers 0" directly above a populated table. A booking linked
		// by `_mhmrentiva_customer_user_id` was invisible to it for the same
		// reason. CustomerStatsMatchTheListTest pins the equality rather than any
		// one symptom.
		//
		// The joins below are LEFT and mirror the list's exactly -- including the
		// list's own ownership rule (CustomerIdentity::sql_user_owns_booking()), so a
		// booking linked by user id counts here exactly as it does in the list. The
		// booking-derived figures keep their narrower meaning regardless: a customer
		// with no booking joins `total` and contributes nothing to `active_90d` or to
		// revenue.
		$membership = CustomerIdentity::sql_is_customer();
		$owns       = CustomerIdentity::sql_user_owns_booking();

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $membership
		// is CustomerIdentity::sql_is_customer() and $owns is
		// CustomerIdentity::sql_user_owns_booking(): each a single $wpdb->prepare() call
		// with every dynamic value bound, composed into this statement the same way
		// get_customers_optimized() composes it. Re-enabled straight after.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Cross-table aggregate with no core API; the result is cached below.
		$result = $wpdb->get_row(
			$wpdb->prepare(
				"
            SELECT
                COUNT(DISTINCT u.ID) as total_customers,
                COUNT(DISTINCT CASE
                    WHEN u.user_registered >= %s
                    THEN u.ID
                END) as new_customers,
                COUNT(DISTINCT CASE
                    WHEN p.post_date >= DATE_SUB(NOW(), INTERVAL 90 DAY)
                    THEN u.ID
                END) as active_90d,
                COALESCE(SUM(CAST(price_meta.meta_value AS DECIMAL(10,2))), 0) as total_revenue
            FROM {$wpdb->users} u
            LEFT JOIN {$wpdb->posts} p ON p.post_type = 'mhmrentiva_booking'
                AND p.post_status IN ('publish', 'private', 'pending')
                AND {$owns}
            LEFT JOIN {$wpdb->postmeta} price_meta ON p.ID = price_meta.post_id
                AND price_meta.meta_key = '_mhmrentiva_total_price'
            WHERE u.ID > 1
                AND u.user_login != 'admin'
                AND {$membership}
                AND u.user_email != ''
        ",
				gmdate( 'Y-m-01 00:00:00' )
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$total     = (int) ( $result->total_customers ?? 0 );
		$revenue   = (float) ( $result->total_revenue ?? 0 );
		$avg_spend = $total > 0 ? round( $revenue / $total, 2 ) : 0.0;

		$stats = array(
			'total'         => $total,
			'new'           => (int) ( $result->new_customers ?? 0 ),
			'average_trend' => self::calculate_trend(),
			// Customers whose latest booking activity falls in the last 90 days —
			// the same window the per-row "active" tag uses.
			'active_90d'    => (int) ( $result->active_90d ?? 0 ),
			// Lifetime spend divided by the customers counted above.
			'avg_spend'     => $avg_spend,
		);

		// Cache save
		CacheManager::set_cache( 'customers', $cache_key, $stats, self::CACHE_TTL );

		return $stats;
	}

	/**
	 * Get customer details in optimized way
	 *
	 * @param int $customer_id
	 * @return array|null
	 */
	public static function get_customer_details_optimized( int $customer_id ): ?array {
		// CACHE_SHAPE suffix: recent_bookings / favorites_count / status joined the
		// payload, so a stale pre-redesign cache entry can never serve the old shape.
		// clear_cache() below builds this same expression -- byte-identical, or the
		// detail cache for a customer can never be cleared.
		$cache_key = self::CACHE_PREFIX . 'details_' . self::CACHE_SHAPE . '_' . $customer_id . '_' . self::currency_fingerprint();

		// Check cache
		$cached_data = CacheManager::get_cache( 'customers', $cache_key );
		if ( $cached_data !== false ) {
			return $cached_data;
		}

		global $wpdb;

		$customer = get_user_by( 'id', $customer_id );
		if ( ! $customer ) {
			return null;
		}

		// The JOIN below mirrors the list's and the stat cards' ownership rule
		// (CustomerIdentity::sql_user_owns_booking()) instead of reaching bookings
		// through `_mhmrentiva_customer_email` alone, so a booking linked by
		// `_mhmrentiva_customer_user_id` counts here exactly as it does one screen up
		// -- see get_customer_stats_optimized() above for the fuller account of why.
		$owns = CustomerIdentity::sql_user_owns_booking();

		// Customer details and booking statistics in single query
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $owns is
		// CustomerIdentity::sql_user_owns_booking(), a single $wpdb->prepare() call
		// with every dynamic value bound, composed into this statement the same way
		// get_customer_stats_optimized() composes it. Re-enabled straight after.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Cross-table aggregate with no core API; the result is cached below.
		$result = $wpdb->get_row(
			$wpdb->prepare(
				"
            SELECT
                u.ID,
                u.display_name,
                u.user_email,
                u.user_registered,
                um_phone.meta_value as phone,
                um_address.meta_value as address,
                COUNT(DISTINCT p.ID) as booking_count,
                COALESCE(SUM(CAST(price_meta.meta_value AS DECIMAL(10,2))), 0) as total_spent,
                MAX(p.post_date) as last_booking,
                MIN(p.post_date) as first_booking
            FROM {$wpdb->users} u
            LEFT JOIN {$wpdb->posts} p ON p.post_type = 'mhmrentiva_booking'
                AND p.post_status IN ('publish', 'private', 'pending')
                AND {$owns}
            LEFT JOIN {$wpdb->postmeta} price_meta ON p.ID = price_meta.post_id
                AND price_meta.meta_key = '_mhmrentiva_total_price'
            LEFT JOIN {$wpdb->usermeta} um_phone ON u.ID = um_phone.user_id
                AND um_phone.meta_key = 'mhmrentiva_phone'
            LEFT JOIN {$wpdb->usermeta} um_address ON u.ID = um_address.user_id
                AND um_address.meta_key = 'mhmrentiva_address'
            WHERE u.ID = %d
            GROUP BY u.ID, u.display_name, u.user_email, u.user_registered, um_phone.meta_value, um_address.meta_value
        ",
				$customer_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( ! $result ) {
			return null;
		}

		$currency = CurrencyHelper::get_currency_symbol();

		$customer_data = array(
			'id'              => (int) $result->ID,
			'name'            => $result->display_name,
			'email'           => $result->user_email,
			'phone'           => $result->phone ? $result->phone : '-',
			'address'         => $result->address ? $result->address : '-',
			'registered'      => gmdate( 'd.m.Y', strtotime( $result->user_registered ) ),
			'booking_count'   => (int) $result->booking_count,
			// Canonical, symbol included — see get_customers_optimized().
			'total_spent'     => CurrencyHelper::format_price( (float) $result->total_spent, 2 ),
			'currency'        => $currency,
			'last_booking'    => $result->last_booking ? gmdate( 'd.m.Y H:i', strtotime( $result->last_booking ) ) : '-',
			'first_booking'   => $result->first_booking ? gmdate( 'd.m.Y H:i', strtotime( $result->first_booking ) ) : '-',
			'favorites_count' => count( \MHMRentiva\Admin\Services\FavoritesService::get_user_favorites( $customer_id ) ),
			'recent_bookings' => self::get_recent_bookings( $result->user_email, 5, 0, (int) $result->ID ),
			'status'          => self::derive_status(
				(int) $result->booking_count,
				(int) strtotime( $result->user_registered ),
				$result->last_booking ? (int) strtotime( $result->last_booking ) : 0
			),
		);

		// Cache save
		CacheManager::set_cache( 'customers', $cache_key, $customer_data, self::CACHE_TTL );

		return $customer_data;
	}

	/**
	 * Latest bookings for a customer, newest first.
	 *
	 * Feeds the detail panel's "recent bookings" list: vehicle title (falling
	 * back to the booking title when the vehicle link is missing), booking date
	 * and the formatted amount.
	 *
	 * @param string $email   Customer e-mail the bookings are keyed on.
	 * @param int    $limit   Maximum rows to return.
	 * @param int    $offset  Rows to skip (detail-page pagination).
	 * @param int    $user_id Customer account id, for a booking linked by
	 *                        `_mhmrentiva_customer_user_id` rather than by e-mail.
	 *                        Goes LAST with a default: this is a public static
	 *                        method in a strict_types file, so a leading required
	 *                        parameter would be a TypeError for any caller not
	 *                        updated here. 0 falls back to e-mail-only matching,
	 *                        which CustomerIdentity::sql_booking_owned_by()'s own
	 *                        guard makes safe -- see that method.
	 * @return array<int, array{id: int, vehicle: string, date: string, amount: string}>
	 */
	public static function get_recent_bookings( string $email, int $limit = 5, int $offset = 0, int $user_id = 0 ): array {
		global $wpdb;

		// Same ownership rule as the row above it and the detail panel's own
		// aggregate query: a booking linked by `_mhmrentiva_customer_user_id`
		// must show up in this list exactly as one linked by e-mail does.
		$owned = CustomerIdentity::sql_booking_owned_by( $user_id, $email );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $owned is
		// CustomerIdentity::sql_booking_owned_by(), a single $wpdb->prepare() call
		// with every dynamic value bound, composed into this statement the same way
		// get_customer_details_optimized() composes sql_user_owns_booking(). Re-enabled
		// straight after.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Small bounded lookup; cached by the caller inside the customer-details payload.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID as booking_id,
                    p.post_title as booking_title,
                    p.post_date,
                    v.post_title as vehicle_title,
                    CAST(COALESCE(price_meta.meta_value, 0) AS DECIMAL(10,2)) as amount
                FROM {$wpdb->posts} p
                LEFT JOIN {$wpdb->postmeta} price_meta ON p.ID = price_meta.post_id
                    AND price_meta.meta_key = '_mhmrentiva_total_price'
                LEFT JOIN {$wpdb->postmeta} vehicle_meta ON p.ID = vehicle_meta.post_id
                    AND vehicle_meta.meta_key = '_mhmrentiva_vehicle_id'
                LEFT JOIN {$wpdb->posts} v ON v.ID = CAST(vehicle_meta.meta_value AS UNSIGNED)
                WHERE p.post_type = 'mhmrentiva_booking'
                    AND p.post_status IN ('publish', 'private', 'pending')
                    AND {$owned}
                ORDER BY p.post_date DESC
                LIMIT %d OFFSET %d",
				$limit,
				$offset
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$bookings = array();
		foreach ( (array) $rows as $row ) {
			$booking_id = (int) $row->booking_id;
			$bookings[] = array(
				'id'        => $booking_id,
				// Same reference format the booking edit meta box shows
				// (BookingEditMetaBox: translated prefix + 6-digit display id).
				'reference' => __( 'BK-', 'mhm-rentiva' ) . str_pad( (string) mhmrentiva_get_display_id( $booking_id ), 6, '0', STR_PAD_LEFT ),
				'vehicle'   => $row->vehicle_title ? $row->vehicle_title : $row->booking_title,
				'date'      => gmdate( 'd.m.Y', strtotime( $row->post_date ) ),
				// Canonical, symbol included — see get_customers_optimized().
				'amount'    => CurrencyHelper::format_price( (float) $row->amount, 2 ),
			);
		}

		return $bookings;
	}

	/**
	 * Get booking days in optimized way
	 *
	 * @param int $month
	 * @param int $year
	 * @return array
	 */
	public static function get_booking_days_optimized( int $month, int $year ): array {
		$cache_key = self::CACHE_PREFIX . 'booking_days_' . $year . '_' . $month;

		// Check cache
		$cached_data = CacheManager::get_cache( 'customers', $cache_key );
		if ( $cached_data !== false ) {
			return $cached_data;
		}

		global $wpdb;

		$start_date = sprintf( '%04d-%02d-01', $year, $month );
		$end_date   = sprintf( '%04d-%02d-%02d', $year, $month, gmdate( 't', (int) mktime( 0, 0, 0, $month, 1, $year ) ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Date aggregate over booking posts; the result is cached below.
		$results = $wpdb->get_col(
			$wpdb->prepare(
				"
            SELECT DISTINCT DAY(p.post_date) as day
            FROM {$wpdb->posts} p
            WHERE p.post_type = 'mhmrentiva_booking'
                AND p.post_status = 'publish'
                AND p.post_date >= %s
                AND p.post_date <= %s
            ORDER BY day
        ",
				$start_date,
				$end_date . ' 23:59:59'
			)
		);
		$days    = array_map( 'intval', $results );

		// Save to cache (1 hour)
		CacheManager::set_cache( 'customers', $cache_key, $days, 3600 );

		return $days;
	}

	/**
	 * Clear cache
	 *
	 * @param int|null $customer_id Clear cache for specific customer
	 * @return bool
	 */
	public static function clear_cache( ?int $customer_id = null ): bool {
		if ( $customer_id ) {
			// Byte-identical to the expression in get_customer_details_optimized() --
			// see the comment there.
			$cache_key = self::CACHE_PREFIX . 'details_' . self::CACHE_SHAPE . '_' . $customer_id . '_' . self::currency_fingerprint();
			return CacheManager::delete_cache( 'customers', $cache_key );
		}

		// Clear all customer cache
		return CacheManager::clear_cache_by_type( 'customers' );
	}


	/**
	 * Trend calculation.
	 *
	 * @return string
	 */
	private static function calculate_trend(): string {
		global $wpdb;

		// Compare this month and last month customer registration counts.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only aggregate report query; cached by the caller.
		$current_month = $wpdb->get_var(
			$wpdb->prepare(
				"
            SELECT COUNT(DISTINCT u.ID)
            FROM {$wpdb->users} u
            INNER JOIN {$wpdb->postmeta} pm_email ON u.user_email = pm_email.meta_value
                AND pm_email.meta_key = '_mhmrentiva_customer_email'
            INNER JOIN {$wpdb->posts} p ON p.ID = pm_email.post_id
                AND p.post_type = 'mhmrentiva_booking'
                AND p.post_status IN ('publish', 'private', 'pending')
                AND p.post_status != 'trash'
            WHERE u.ID > 1 
                AND u.user_login != 'admin'
                AND u.user_registered >= %s
        ",
				gmdate( 'Y-m-01 00:00:00' )
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only aggregate report query; cached by the caller.
		$last_month = $wpdb->get_var(
			$wpdb->prepare(
				"
            SELECT COUNT(DISTINCT u.ID)
            FROM {$wpdb->users} u
            INNER JOIN {$wpdb->postmeta} pm_email ON u.user_email = pm_email.meta_value
                AND pm_email.meta_key = '_mhmrentiva_customer_email'
            INNER JOIN {$wpdb->posts} p ON p.ID = pm_email.post_id
                AND p.post_type = 'mhmrentiva_booking'
                AND p.post_status IN ('publish', 'private', 'pending')
                AND p.post_status != 'trash'
            WHERE u.ID > 1 
                AND u.user_login != 'admin'
                AND u.user_registered >= DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 1 MONTH), '%%Y-%%m-01')
                AND u.user_registered < %s
        ",
				gmdate( 'Y-m-01 00:00:00' )
			)
		);

		if ( $last_month > 0 ) {
			$trend = ( ( $current_month - $last_month ) / $last_month ) * 100;
			$sign  = $trend >= 0 ? '+' : '';
			return $sign . round( $trend, 1 ) . '%';
		}

		return $current_month > 0 ? '+100%' : '0%';
	}
}
