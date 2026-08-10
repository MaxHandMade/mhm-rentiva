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
 * - Batch data processing
 */
final class CustomersOptimizer {



	private const CACHE_PREFIX = 'mhmrentiva_customers_';
	private const CACHE_TTL    = 900; // 15 minutes
	private const BATCH_SIZE   = 50;

	/**
	 * Safe sanitize text field that handles null values.
	 *
	 * @param mixed $value Input value.
	 * @return string Sanitized string.
	 */
	public static function sanitize_text_field_safe( $value ): string {
		if ( null === $value || '' === $value ) {
			return '';
		}
		return sanitize_text_field( (string) $value );
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

		// Status filters are independent predicates (they may overlap): `new` means
		// registered in the last 30 days, `active` means a booking in the last 90
		// days (same window as the active_90d stat), `vip` means at least the
		// filterable minimum number of bookings. Anything unknown collapses to
		// 'all' so the bound toggles below never see a stray value.
		if ( ! in_array( $status, array( 'all', 'new', 'active', 'vip' ), true ) ) {
			$status = 'all';
		}
		$vip_min   = self::get_vip_min_bookings();
		$cache_key = self::CACHE_PREFIX . 'list_' . md5( $page . '_' . $per_page . '_' . $search . '_' . $sort_by . '_' . $sort_dir . '_' . $status . '_' . $vip_min );

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
				"SELECT COUNT(*) FROM (
                SELECT u.ID
                FROM {$wpdb->users} u
                LEFT JOIN {$wpdb->postmeta} email_meta ON u.user_email = email_meta.meta_value
                    AND email_meta.meta_key = '_mhmrentiva_customer_email'
                LEFT JOIN {$wpdb->posts} p ON p.ID = email_meta.post_id
                    AND p.post_type = 'mhmrentiva_booking'
                    AND p.post_status IN ('publish', 'private', 'pending')
                WHERE u.ID > 1
                    AND u.user_login != 'admin'
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
		// v2: the key changed when active_90d / avg_spend joined the payload, so a
		// stale pre-redesign cache entry can never serve the old shape.
		$cache_key = self::CACHE_PREFIX . 'stats_v2';

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
                END) as active_customers,
                COUNT(DISTINCT CASE
                    WHEN p.post_date >= DATE_SUB(NOW(), INTERVAL 90 DAY)
                    THEN u.ID
                END) as active_90d,
                COALESCE(SUM(CAST(price_meta.meta_value AS DECIMAL(10,2))), 0) as total_revenue
            FROM {$wpdb->users} u
            INNER JOIN {$wpdb->postmeta} pm_email ON u.user_email = pm_email.meta_value
                AND pm_email.meta_key = '_mhmrentiva_customer_email'
            INNER JOIN {$wpdb->posts} p ON p.ID = pm_email.post_id
                AND p.post_type = 'mhmrentiva_booking'
                AND p.post_status IN ('publish', 'private', 'pending')
                AND p.post_status != 'trash'
            LEFT JOIN {$wpdb->postmeta} price_meta ON p.ID = price_meta.post_id
                AND price_meta.meta_key = '_mhmrentiva_total_price'
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

		$total     = (int) ( $result->total_customers ?? 0 );
		$revenue   = (float) ( $result->total_revenue ?? 0 );
		$avg_spend = $total > 0 ? round( $revenue / $total, 2 ) : 0.0;

		$stats = array(
			'total'         => $total,
			'active'        => (int) ( $result->active_customers ?? 0 ),
			'new'           => (int) ( $result->new_customers ?? 0 ),
			'average'       => $monthly_avg,
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
		// v2 suffix: recent_bookings / favorites_count / status joined the payload,
		// so a stale pre-redesign cache entry can never serve the old shape.
		// clear_cache() below uses the same key.
		$cache_key = self::CACHE_PREFIX . 'details_v2_' . $customer_id;

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
			'id'              => (int) $result->ID,
			'name'            => $result->display_name,
			'email'           => $result->user_email,
			'phone'           => $result->phone ? $result->phone : '-',
			'address'         => $result->address ? $result->address : '-',
			'registered'      => gmdate( 'd.m.Y', strtotime( $result->user_registered ) ),
			'booking_count'   => (int) $result->booking_count,
			'total_spent'     => number_format( (float) $result->total_spent, 2, ',', '.' ),
			'currency'        => $currency,
			'last_booking'    => $result->last_booking ? gmdate( 'd.m.Y H:i', strtotime( $result->last_booking ) ) : '-',
			'first_booking'   => $result->first_booking ? gmdate( 'd.m.Y H:i', strtotime( $result->first_booking ) ) : '-',
			'favorites_count' => count( \MHMRentiva\Admin\Services\FavoritesService::get_user_favorites( $customer_id ) ),
			'recent_bookings' => self::get_recent_bookings( $result->user_email, 3 ),
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
	 * @param string $email Customer e-mail the bookings are keyed on.
	 * @param int    $limit Maximum rows to return.
	 * @return array<int, array{vehicle: string, date: string, amount: string}>
	 */
	public static function get_recent_bookings( string $email, int $limit = 3 ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Small bounded lookup; cached by the caller inside the customer-details payload.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.post_title as booking_title,
                    p.post_date,
                    v.post_title as vehicle_title,
                    CAST(COALESCE(price_meta.meta_value, 0) AS DECIMAL(10,2)) as amount
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} email_meta ON p.ID = email_meta.post_id
                    AND email_meta.meta_key = '_mhmrentiva_customer_email'
                    AND email_meta.meta_value = %s
                LEFT JOIN {$wpdb->postmeta} price_meta ON p.ID = price_meta.post_id
                    AND price_meta.meta_key = '_mhmrentiva_total_price'
                LEFT JOIN {$wpdb->postmeta} vehicle_meta ON p.ID = vehicle_meta.post_id
                    AND vehicle_meta.meta_key = '_mhmrentiva_vehicle_id'
                LEFT JOIN {$wpdb->posts} v ON v.ID = CAST(vehicle_meta.meta_value AS UNSIGNED)
                WHERE p.post_type = 'mhmrentiva_booking'
                    AND p.post_status IN ('publish', 'private', 'pending')
                ORDER BY p.post_date DESC
                LIMIT %d",
				$email,
				$limit
			)
		);

		$bookings = array();
		foreach ( (array) $rows as $row ) {
			$bookings[] = array(
				'vehicle' => $row->vehicle_title ? $row->vehicle_title : $row->booking_title,
				'date'    => gmdate( 'd.m.Y', strtotime( $row->post_date ) ),
				'amount'  => number_format( (float) $row->amount, 2, ',', '.' ),
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
			$cache_key = self::CACHE_PREFIX . 'details_v2_' . $customer_id;
			return CacheManager::delete_cache( 'customers', $cache_key );
		}

		// Clear all customer cache
		return CacheManager::clear_cache_by_type( 'customers' );
	}

	/**
	 * Batch customer update
	 *
	 * @param array $customer_ids
	 * @param array $updates
	 * @return bool
	 */
	public static function batch_update_customers( array $customer_ids, array $updates ): bool {
		// Batch-updating customers updates real WordPress user accounts, so this
		// is gated on edit_users, not manage_options.
		if ( ! current_user_can( 'edit_users' ) ) {
			return false;
		}

		if ( empty( $customer_ids ) || empty( $updates ) ) {
			return false;
		}

		$success = true;
		$chunks  = array_chunk( $customer_ids, self::BATCH_SIZE );

		foreach ( $chunks as $chunk ) {
			foreach ( $chunk as $customer_id ) {
				$result = self::update_customer_data( $customer_id, $updates );
				if ( ! $result ) {
					$success = false;
				}

				// Clear cache
				self::clear_cache( $customer_id );
			}
		}

		return $success;
	}

	/**
	 * Single customer update
	 *
	 * @param int   $customer_id
	 * @param array $updates
	 * @return bool
	 */
	private static function update_customer_data( int $customer_id, array $updates ): bool {
		$user_data = array();

		if ( isset( $updates['name'] ) ) {
			$user_data['display_name'] = self::sanitize_text_field_safe( $updates['name'] );
			$user_data['first_name']   = self::sanitize_text_field_safe( $updates['name'] );
		}

		if ( isset( $updates['email'] ) ) {
			$user_data['user_email'] = sanitize_email( (string) ( $updates['email'] ?? '' ) );
		}

		if ( ! empty( $user_data ) ) {
			$user_data['ID'] = $customer_id;
			$result          = wp_update_user( $user_data );
			if ( is_wp_error( $result ) ) {
				return false;
			}
		}

		// Update meta data
		if ( isset( $updates['phone'] ) ) {
			update_user_meta( $customer_id, 'mhmrentiva_phone', self::sanitize_text_field_safe( $updates['phone'] ) );
		}

		if ( isset( $updates['address'] ) ) {
			update_user_meta( $customer_id, 'mhmrentiva_address', sanitize_textarea_field( (string) ( $updates['address'] ?? '' ) ) );
		}

		return true;
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
