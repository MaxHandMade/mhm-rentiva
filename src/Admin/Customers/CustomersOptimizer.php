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
	 * Get customer list in optimized way
	 *
	 * @param int    $page Page number
	 * @param int    $per_page Records per page
	 * @param string $search Search term
	 * @return array
	 */
	public static function get_customers_optimized( int $page = 1, int $per_page = 20, string $search = '', string $sort_by = 'last_booking', string $sort_dir = 'desc' ): array {
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

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $membership
		// is CustomerIdentity::sql_is_customer(): a single $wpdb->prepare() call with
		// every dynamic value bound. WordPress provides no placeholder for splicing a
		// composed SQL fragment into another statement, so the composition itself is
		// what the sniff sees. Scoped to this region and re-enabled straight after.
		$cache_key = self::CACHE_PREFIX . 'list_' . md5( $page . '_' . $per_page . '_' . $search . '_' . $sort_by . '_' . $sort_dir );

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
            LEFT JOIN {$wpdb->postmeta} email_meta ON u.user_email = email_meta.meta_value
                AND email_meta.meta_key = '_mhmrentiva_customer_email'
            LEFT JOIN {$wpdb->posts} p ON p.ID = email_meta.post_id
                AND p.post_type = 'mhmrentiva_booking'
                AND p.post_status IN ('publish', 'private', 'pending')
            LEFT JOIN {$wpdb->postmeta} price_meta ON p.ID = price_meta.post_id
                AND price_meta.meta_key = '_mhmrentiva_total_price'
            LEFT JOIN {$wpdb->usermeta} um_phone ON u.ID = um_phone.user_id
                AND um_phone.meta_key = 'mhmrentiva_phone'
            LEFT JOIN {$wpdb->usermeta} um_address ON u.ID = um_address.user_id
                AND um_address.meta_key = 'mhmrentiva_address'
            WHERE u.ID > 1
                AND u.user_login != 'admin'
                AND {$membership}
                AND (u.display_name LIKE %s OR u.user_email LIKE %s)
            GROUP BY u.ID, u.display_name, u.user_email, u.user_registered, um_phone.meta_value, um_address.meta_value
            ORDER BY %i ASC
            LIMIT %d OFFSET %d
            ", $search_like, $search_like, $order_col, (int) $per_page, (int) $offset ) )
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
            LEFT JOIN {$wpdb->postmeta} email_meta ON u.user_email = email_meta.meta_value
                AND email_meta.meta_key = '_mhmrentiva_customer_email'
            LEFT JOIN {$wpdb->posts} p ON p.ID = email_meta.post_id
                AND p.post_type = 'mhmrentiva_booking'
                AND p.post_status IN ('publish', 'private', 'pending')
            LEFT JOIN {$wpdb->postmeta} price_meta ON p.ID = price_meta.post_id
                AND price_meta.meta_key = '_mhmrentiva_total_price'
            LEFT JOIN {$wpdb->usermeta} um_phone ON u.ID = um_phone.user_id
                AND um_phone.meta_key = 'mhmrentiva_phone'
            LEFT JOIN {$wpdb->usermeta} um_address ON u.ID = um_address.user_id
                AND um_address.meta_key = 'mhmrentiva_address'
            WHERE u.ID > 1
                AND u.user_login != 'admin'
                AND {$membership}
                AND (u.display_name LIKE %s OR u.user_email LIKE %s)
            GROUP BY u.ID, u.display_name, u.user_email, u.user_registered, um_phone.meta_value, um_address.meta_value
            ORDER BY %i DESC
            LIMIT %d OFFSET %d
            ", $search_like, $search_like, $order_col, (int) $per_page, (int) $offset ) );

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
				"SELECT COUNT(DISTINCT u.ID)
            FROM {$wpdb->users} u
            WHERE u.ID > 1
                AND u.user_login != 'admin'
                AND {$membership}
                AND (u.display_name LIKE %s OR u.user_email LIKE %s)",
				$search_like,
				$search_like
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
				'total_spent'   => number_format( (float) $result->total_spent, 2, ',', '.' ),
				'last_booking'  => $result->last_booking ? gmdate( 'd.m.Y', strtotime( $result->last_booking ) ) : '-',
				'created_date'  => $result->created_date ? gmdate( 'd.m.Y', strtotime( $result->created_date ) ) : '-',
				'currency'      => $currency,
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
	 * Get customer statistics in optimized way
	 *
	 * @return array
	 */
	public static function get_customer_stats_optimized(): array {
		$cache_key = self::CACHE_PREFIX . 'stats';

		// Check cache
		$cached_data = CacheManager::get_cache( 'customers', $cache_key );
		if ( $cached_data !== false ) {
			return $cached_data;
		}

		global $wpdb;

		// Customer statistics from WordPress users.
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
                    WHEN u.user_registered >= DATE_SUB(NOW(), INTERVAL 30 DAY) 
                    THEN u.ID 
                END) as active_customers
            FROM {$wpdb->users} u
            INNER JOIN {$wpdb->postmeta} pm_email ON u.user_email = pm_email.meta_value
                AND pm_email.meta_key = '_mhmrentiva_customer_email'
            INNER JOIN {$wpdb->posts} p ON p.ID = pm_email.post_id
                AND p.post_type = 'mhmrentiva_booking'
                AND p.post_status IN ('publish', 'private', 'pending')
                AND p.post_status != 'trash'
            WHERE u.ID > 1 
                AND u.user_login != 'admin'
                AND u.user_email != '' 
                AND u.user_email IS NOT NULL
        ",
				gmdate( 'Y-m-01 00:00:00' )
			)
		);

		// Calculate monthly average (last 3 months)
		$monthly_avg = self::calculate_monthly_average();

		$stats = array(
			'total'         => (int) ( $result->total_customers ?? 0 ),
			'active'        => (int) ( $result->active_customers ?? 0 ),
			'new'           => (int) ( $result->new_customers ?? 0 ),
			'average'       => $monthly_avg,
			'average_trend' => self::calculate_trend(),
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
		$cache_key = self::CACHE_PREFIX . 'details_' . $customer_id;

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

		// Customer details and booking statistics in single query
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
            LEFT JOIN {$wpdb->postmeta} email_meta ON u.user_email = email_meta.meta_value
                AND email_meta.meta_key = '_mhmrentiva_customer_email'
            LEFT JOIN {$wpdb->posts} p ON p.ID = email_meta.post_id
                AND p.post_type = 'mhmrentiva_booking'
                AND p.post_status IN ('publish', 'private', 'pending')
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

		if ( ! $result ) {
			return null;
		}

		$currency = CurrencyHelper::get_currency_symbol();

		$customer_data = array(
			'id'            => (int) $result->ID,
			'name'          => $result->display_name,
			'email'         => $result->user_email,
			'phone'         => $result->phone ? $result->phone : '-',
			'address'       => $result->address ? $result->address : '-',
			'registered'    => gmdate( 'd.m.Y', strtotime( $result->user_registered ) ),
			'booking_count' => (int) $result->booking_count,
			'total_spent'   => number_format( (float) $result->total_spent, 2, ',', '.' ),
			'currency'      => $currency,
			'last_booking'  => $result->last_booking ? gmdate( 'd.m.Y H:i', strtotime( $result->last_booking ) ) : '-',
			'first_booking' => $result->first_booking ? gmdate( 'd.m.Y H:i', strtotime( $result->first_booking ) ) : '-',
		);

		// Cache save
		CacheManager::set_cache( 'customers', $cache_key, $customer_data, self::CACHE_TTL );

		return $customer_data;
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
			$cache_key = self::CACHE_PREFIX . 'details_' . $customer_id;
			return CacheManager::delete_cache( 'customers', $cache_key );
		}

		// Clear all customer cache
		return CacheManager::clear_cache_by_type( 'customers' );
	}

	/**
	 * Monthly average calculation
	 *
	 * @return float
	 */
	private static function calculate_monthly_average(): float {
		global $wpdb;

		// Get customer registration counts for last 3 months (WordPress user registration dates).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only aggregate report query; cached by the caller.
		$results = $wpdb->get_results(
			"
            SELECT 
                YEAR(u.user_registered) as year,
                MONTH(u.user_registered) as month,
                COUNT(DISTINCT u.ID) as customer_count
            FROM {$wpdb->users} u
            INNER JOIN {$wpdb->postmeta} pm_email ON u.user_email = pm_email.meta_value
                AND pm_email.meta_key = '_mhmrentiva_customer_email'
            INNER JOIN {$wpdb->posts} p ON p.ID = pm_email.post_id
                AND p.post_type = 'mhmrentiva_booking'
                AND p.post_status IN ('publish', 'private', 'pending')
                AND p.post_status != 'trash'
            WHERE u.ID > 1 
                AND u.user_login != 'admin'
                AND u.user_registered >= DATE_SUB(NOW(), INTERVAL 3 MONTH)
            GROUP BY YEAR(u.user_registered), MONTH(u.user_registered)
            ORDER BY year DESC, month DESC
        "
		);

		if ( empty( $results ) ) {
			return 0.0;
		}

		$total_customers = 0;
		foreach ( $results as $result ) {
			$total_customers += (int) $result->customer_count;
		}

		return round( $total_customers / count( $results ), 1 );
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
