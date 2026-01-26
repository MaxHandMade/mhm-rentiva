<?php

/**
 * Customers Performance Optimizer Class.
 *
 * @package MHMRentiva\Admin\Customers
 */

declare(strict_types=1);

namespace MHMRentiva\Admin\Customers;

use MHMRentiva\Admin\Core\Utilities\CacheManager;
use MHMRentiva\Admin\Settings\Core\SettingsCore;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Optimizes customer data performance.
 * - Cache system
 * - Optimized queries
 * - Batch data processing
 */
final class CustomersOptimizer
{


	private const CACHE_PREFIX = 'mhm_customers_';
	private const CACHE_TTL    = 900; // 15 minutes
	private const BATCH_SIZE   = 50;

	/**
	 * Safe sanitize text field that handles null values.
	 *
	 * @param mixed $value Input value.
	 * @return string Sanitized string.
	 */
	public static function sanitize_text_field_safe($value): string
	{
		if (null === $value || '' === $value) {
			return '';
		}
		return sanitize_text_field((string) $value);
	}

	/**
	 * Get customer list in optimized way
	 *
	 * @param int    $page Page number
	 * @param int    $per_page Records per page
	 * @param string $search Search term
	 * @return array
	 */
	public static function get_customers_optimized(int $page = 1, int $per_page = 20, string $search = ''): array
	{
		$cache_key = self::CACHE_PREFIX . 'list_' . md5($page . '_' . $per_page . '_' . $search);

		// Check cache
		$cached_data = CacheManager::get_cache('customers', $cache_key);
		if ($cached_data !== false) {
			return $cached_data;
		}

		global $wpdb;

		// Calculate offset
		$offset = ($page - 1) * $per_page;

		// Optimized query - all data at once.
		$where_clause = "WHERE u.ID > 1 AND u.user_login != 'admin'";
		$search_term  = '';

		if (! empty($search)) {
			$where_clause .= $wpdb->prepare(' AND (u.display_name LIKE %s OR u.user_email LIKE %s)', '%' . $wpdb->esc_like($search) . '%', '%' . $wpdb->esc_like($search) . '%');
		}

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"
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
                AND email_meta.meta_key = '_mhm_customer_email'
            LEFT JOIN {$wpdb->posts} p ON p.ID = email_meta.post_id
                AND p.post_type = 'vehicle_booking'
                AND p.post_status = 'publish'
            LEFT JOIN {$wpdb->postmeta} price_meta ON p.ID = price_meta.post_id
                AND price_meta.meta_key = '_mhm_total_price'
            LEFT JOIN {$wpdb->usermeta} um_phone ON u.ID = um_phone.user_id
                AND um_phone.meta_key = 'mhm_rentiva_phone'
            LEFT JOIN {$wpdb->usermeta} um_address ON u.ID = um_address.user_id
                AND um_address.meta_key = 'mhm_rentiva_address'
            {$where_clause}
            GROUP BY u.ID, u.display_name, u.user_email, u.user_registered, um_phone.meta_value, um_address.meta_value
            ORDER BY last_booking DESC
            LIMIT %d OFFSET %d

            ", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Where clause is built safely above.
				(int) $per_page,
				(int) $offset
			)
		);

		if (empty($results)) {
			$data = array(
				'customers' => array(),
				'total'     => 0,
			);
			CacheManager::set_cache('customers', $cache_key, $data, self::CACHE_TTL);
			return $data;
		}

		$total = (int) $wpdb->get_var(
			"SELECT COUNT(DISTINCT u.ID)
            FROM {$wpdb->users} u
            {$where_clause}" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Where clause is safe.
		);

		// Format data
		$currency  = SettingsCore::get('mhm_rentiva_currency', 'USD');
		$customers = array();

		foreach ($results as $result) {
			$customers[] = array(
				'id'            => (int) $result->user_id,
				'name'          => $result->customer_name ?: $result->customer_email,
				'email'         => $result->customer_email,
				'phone'         => $result->phone ?: '-',
				'address'       => $result->address ?: '-',
				'booking_count' => (int) $result->booking_count,
				'total_spent'   => number_format((float) $result->total_spent, 2, ',', '.'),
				'last_booking'  => $result->last_booking ? gmdate('d.m.Y', strtotime($result->last_booking)) : '-',
				'created_date'  => $result->created_date ? gmdate('d.m.Y', strtotime($result->created_date)) : '-',
				'currency'      => $currency,
			);
		}

		$data = array(
			'customers'   => $customers,
			'total'       => (int) $total,
			'page'        => $page,
			'per_page'    => $per_page,
			'total_pages' => ceil($total / $per_page),
		);

		// Cache save
		CacheManager::set_cache('customers', $cache_key, $data, self::CACHE_TTL);

		return $data;
	}

	/**
	 * Get customer statistics in optimized way
	 *
	 * @return array
	 */
	public static function get_customer_stats_optimized(): array
	{
		$cache_key = self::CACHE_PREFIX . 'stats';

		// Check cache
		$cached_data = CacheManager::get_cache('customers', $cache_key);
		if ($cached_data !== false) {
			return $cached_data;
		}

		global $wpdb;

		// Customer statistics from WordPress users.
		$query = $wpdb->prepare(
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
                AND pm_email.meta_key = '_mhm_customer_email'
            INNER JOIN {$wpdb->posts} p ON p.ID = pm_email.post_id
                AND p.post_type = 'vehicle_booking'
                AND p.post_status IN ('publish', 'private', 'pending')
                AND p.post_status != 'trash'
            WHERE u.ID > 1 
                AND u.user_login != 'admin'
                AND u.user_email != '' 
                AND u.user_email IS NOT NULL
        ",
			gmdate('Y-m-01 00:00:00')
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is prepared above with $wpdb->prepare().
		$result = $wpdb->get_row($query);

		// Calculate monthly average (last 3 months)
		$monthly_avg = self::calculate_monthly_average();

		$stats = array(
			'total'         => (int) ($result->total_customers ?? 0),
			'active'        => (int) ($result->active_customers ?? 0),
			'new'           => (int) ($result->new_customers ?? 0),
			'average'       => $monthly_avg,
			'average_trend' => self::calculate_trend(),
		);

		// Cache save
		CacheManager::set_cache('customers', $cache_key, $stats, self::CACHE_TTL);

		return $stats;
	}

	/**
	 * Get customer details in optimized way
	 *
	 * @param int $customer_id
	 * @return array|null
	 */
	public static function get_customer_details_optimized(int $customer_id): ?array
	{
		$cache_key = self::CACHE_PREFIX . 'details_' . $customer_id;

		// Check cache
		$cached_data = CacheManager::get_cache('customers', $cache_key);
		if ($cached_data !== false) {
			return $cached_data;
		}

		global $wpdb;

		$customer = get_user_by('id', $customer_id);
		if (! $customer) {
			return null;
		}

		// Customer details and booking statistics in single query
		$query = $wpdb->prepare(
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
                AND email_meta.meta_key = '_mhm_customer_email'
            LEFT JOIN {$wpdb->posts} p ON p.ID = email_meta.post_id
                AND p.post_type = 'vehicle_booking'
                AND p.post_status = 'publish'
            LEFT JOIN {$wpdb->postmeta} price_meta ON p.ID = price_meta.post_id
                AND price_meta.meta_key = '_mhm_total_price'
            LEFT JOIN {$wpdb->usermeta} um_phone ON u.ID = um_phone.user_id
                AND um_phone.meta_key = 'mhm_rentiva_phone'
            LEFT JOIN {$wpdb->usermeta} um_address ON u.ID = um_address.user_id
                AND um_address.meta_key = 'mhm_rentiva_address'
            WHERE u.ID = %d
            GROUP BY u.ID, u.display_name, u.user_email, u.user_registered, um_phone.meta_value, um_address.meta_value
        ",
			$customer_id
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is prepared above with $wpdb->prepare().
		$result = $wpdb->get_row($query);

		if (! $result) {
			return null;
		}

		$currency = SettingsCore::get('mhm_rentiva_currency', 'USD');

		$customer_data = array(
			'id'            => (int) $result->ID,
			'name'          => $result->display_name,
			'email'         => $result->user_email,
			'phone'         => $result->phone ?: '-',
			'address'       => $result->address ?: '-',
			'registered'    => gmdate('d.m.Y', strtotime($result->user_registered)),
			'booking_count' => (int) $result->booking_count,
			'total_spent'   => number_format((float) $result->total_spent, 2, ',', '.'),
			'currency'      => $currency,
			'last_booking'  => $result->last_booking ? gmdate('d.m.Y H:i', strtotime($result->last_booking)) : '-',
			'first_booking' => $result->first_booking ? gmdate('d.m.Y H:i', strtotime($result->first_booking)) : '-',
		);

		// Cache save
		CacheManager::set_cache('customers', $cache_key, $customer_data, self::CACHE_TTL);

		return $customer_data;
	}

	/**
	 * Get booking days in optimized way
	 *
	 * @param int $month
	 * @param int $year
	 * @return array
	 */
	public static function get_booking_days_optimized(int $month, int $year): array
	{
		$cache_key = self::CACHE_PREFIX . 'booking_days_' . $year . '_' . $month;

		// Check cache
		$cached_data = CacheManager::get_cache('customers', $cache_key);
		if ($cached_data !== false) {
			return $cached_data;
		}

		global $wpdb;

		$start_date = sprintf('%04d-%02d-01', $year, $month);
		$end_date   = sprintf('%04d-%02d-%02d', $year, $month, gmdate('t', (int) mktime(0, 0, 0, $month, 1, $year)));

		$query = $wpdb->prepare(
			"
            SELECT DISTINCT DAY(p.post_date) as day
            FROM {$wpdb->posts} p
            WHERE p.post_type = 'vehicle_booking'
                AND p.post_status = 'publish'
                AND p.post_date >= %s
                AND p.post_date <= %s
            ORDER BY day
        ",
			$start_date,
			$end_date . ' 23:59:59'
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is prepared above with $wpdb->prepare().
		$results = $wpdb->get_col($query);
		$days    = array_map('intval', $results);

		// Save to cache (1 hour)
		CacheManager::set_cache('customers', $cache_key, $days, 3600);

		return $days;
	}

	/**
	 * Clear cache
	 *
	 * @param int|null $customer_id Clear cache for specific customer
	 * @return bool
	 */
	public static function clear_cache(?int $customer_id = null): bool
	{
		if ($customer_id) {
			$cache_key = self::CACHE_PREFIX . 'details_' . $customer_id;
			return CacheManager::delete_cache('customers', $cache_key);
		}

		// Clear all customer cache
		return CacheManager::clear_cache_by_type('customers');
	}

	/**
	 * Batch customer update
	 *
	 * @param array $customer_ids
	 * @param array $updates
	 * @return bool
	 */
	public static function batch_update_customers(array $customer_ids, array $updates): bool
	{
		if (empty($customer_ids) || empty($updates)) {
			return false;
		}

		$success = true;
		$chunks  = array_chunk($customer_ids, self::BATCH_SIZE);

		foreach ($chunks as $chunk) {
			foreach ($chunk as $customer_id) {
				$result = self::update_customer_data($customer_id, $updates);
				if (! $result) {
					$success = false;
				}

				// Clear cache
				self::clear_cache($customer_id);
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
	private static function update_customer_data(int $customer_id, array $updates): bool
	{
		$user_data = array();

		if (isset($updates['name'])) {
			$user_data['display_name'] = self::sanitize_text_field_safe($updates['name']);
			$user_data['first_name']   = self::sanitize_text_field_safe($updates['name']);
		}

		if (isset($updates['email'])) {
			$user_data['user_email'] = sanitize_email((string) ($updates['email'] ?? ''));
		}

		if (! empty($user_data)) {
			$user_data['ID'] = $customer_id;
			$result          = wp_update_user($user_data);
			if (is_wp_error($result)) {
				return false;
			}
		}

		// Update meta data
		if (isset($updates['phone'])) {
			update_user_meta($customer_id, 'mhm_rentiva_phone', self::sanitize_text_field_safe($updates['phone']));
		}

		if (isset($updates['address'])) {
			update_user_meta($customer_id, 'mhm_rentiva_address', sanitize_textarea_field((string) ($updates['address'] ?? '')));
		}

		return true;
	}

	/**
	 * Monthly average calculation
	 *
	 * @return float
	 */
	private static function calculate_monthly_average(): float
	{
		global $wpdb;

		// Get customer registration counts for last 3 months (WordPress user registration dates).
		$query = $wpdb->prepare(
			"
            SELECT 
                YEAR(u.user_registered) as year,
                MONTH(u.user_registered) as month,
                COUNT(DISTINCT u.ID) as customer_count
            FROM {$wpdb->users} u
            INNER JOIN {$wpdb->postmeta} pm_email ON u.user_email = pm_email.meta_value
                AND pm_email.meta_key = '_mhm_customer_email'
            INNER JOIN {$wpdb->posts} p ON p.ID = pm_email.post_id
                AND p.post_type = 'vehicle_booking'
                AND p.post_status IN ('publish', 'private', 'pending')
                AND p.post_status != 'trash'
            WHERE u.ID > 1 
                AND u.user_login != 'admin'
                AND u.user_registered >= DATE_SUB(NOW(), INTERVAL 3 MONTH)
            GROUP BY YEAR(u.user_registered), MONTH(u.user_registered)
            ORDER BY year DESC, month DESC
        "
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is prepared above with $wpdb->prepare().
		$results = $wpdb->get_results($query);

		if (empty($results)) {
			return 0.0;
		}

		$total_customers = 0;
		foreach ($results as $result) {
			$total_customers += (int) $result->customer_count;
		}

		return round($total_customers / count($results), 1);
	}

	/**
	 * Trend calculation.
	 *
	 * @return string
	 */
	private static function calculate_trend(): string
	{
		global $wpdb;

		// Compare this month and last month customer registration counts.
		$current_month_query = $wpdb->prepare(
			"
            SELECT COUNT(DISTINCT u.ID)
            FROM {$wpdb->users} u
            INNER JOIN {$wpdb->postmeta} pm_email ON u.user_email = pm_email.meta_value
                AND pm_email.meta_key = '_mhm_customer_email'
            INNER JOIN {$wpdb->posts} p ON p.ID = pm_email.post_id
                AND p.post_type = 'vehicle_booking'
                AND p.post_status IN ('publish', 'private', 'pending')
                AND p.post_status != 'trash'
            WHERE u.ID > 1 
                AND u.user_login != 'admin'
                AND u.user_registered >= %s
        ",
			gmdate('Y-m-01 00:00:00')
		);
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is prepared above with $wpdb->prepare().
		$current_month       = $wpdb->get_var($current_month_query);

		$last_month_query = $wpdb->prepare(
			"
            SELECT COUNT(DISTINCT u.ID)
            FROM {$wpdb->users} u
            INNER JOIN {$wpdb->postmeta} pm_email ON u.user_email = pm_email.meta_value
                AND pm_email.meta_key = '_mhm_customer_email'
            INNER JOIN {$wpdb->posts} p ON p.ID = pm_email.post_id
                AND p.post_type = 'vehicle_booking'
                AND p.post_status IN ('publish', 'private', 'pending')
                AND p.post_status != 'trash'
            WHERE u.ID > 1 
                AND u.user_login != 'admin'
                AND u.user_registered >= DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 1 MONTH), '%%Y-%%m-01')
                AND u.user_registered < %s
        ",
			gmdate('Y-m-01 00:00:00')
		);
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is prepared above with $wpdb->prepare().
		$last_month       = $wpdb->get_var($last_month_query);

		if ($last_month > 0) {
			$trend = (($current_month - $last_month) / $last_month) * 100;
			$sign  = $trend >= 0 ? '+' : '';
			return $sign . round($trend, 1) . '%';
		}

		return $current_month > 0 ? '+100%' : '0%';
	}

	/**
	 * Create database indexes
	 *
	 * @return bool
	 */
	public static function create_database_indexes(): bool
	{
		global $wpdb;

		$indexes = array(
			"CREATE INDEX IF NOT EXISTS idx_postmeta_customer_email ON {$wpdb->postmeta} (meta_key, meta_value(50))",
			"CREATE INDEX IF NOT EXISTS idx_postmeta_booking_price ON {$wpdb->postmeta} (post_id, meta_key)",
			"CREATE INDEX IF NOT EXISTS idx_usermeta_customer_phone ON {$wpdb->usermeta} (user_id, meta_key)",
			"CREATE INDEX IF NOT EXISTS idx_posts_booking_date ON {$wpdb->posts} (post_type, post_status, post_date)",
		);

		$success = true;
		foreach ($indexes as $index_query) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- DDL statement (CREATE INDEX) cannot use prepare.
			$result = $wpdb->query($index_query);
			if ($result === false) {
				$success = false;
			}
		}

		return $success;
	}
}
