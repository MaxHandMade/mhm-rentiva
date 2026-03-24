<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Messages\Core;

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query,WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value,WordPress.DB.SlowDBQuery.slow_db_query_tax_query,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Message cache warmup/invalidation paths intentionally perform targeted DB queries.



if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cache management for messaging system
 */
final class MessageCache {


	const CACHE_GROUP  = 'mhm_messages';
	const CACHE_EXPIRY = 3600; // 1 saat

	/**
	 * Generate cache key
	 */
	private static function get_cache_key( string $key, array $params = array() ): string {
		if ( ! empty( $params ) ) {
			$key .= '_' . md5( serialize( $params ) );
		}
		return $key;
	}

	/**
	 * Get data from cache
	 */
	public static function get( string $key, array $params = array() ) {
		$cache_key = self::get_cache_key( $key, $params );
		return wp_cache_get( $cache_key, self::CACHE_GROUP );
	}

	/**
	 * Save data to cache
	 */
	public static function set( string $key, $data, array $params = array(), int $expiry = null ): bool {
		$cache_key = self::get_cache_key( $key, $params );
		$expiry    = $expiry ?? self::CACHE_EXPIRY;

		return wp_cache_set( $cache_key, $data, self::CACHE_GROUP, $expiry );
	}

	/**
	 * Delete data from cache
	 */
	public static function delete( string $key, array $params = array() ): bool {
		$cache_key = self::get_cache_key( $key, $params );
		return wp_cache_delete( $cache_key, self::CACHE_GROUP );
	}

	/**
	 * Clear cache
	 */
	public static function flush(): bool {
		return wp_cache_flush_group( self::CACHE_GROUP );
	}

	/**
	 * Cache message counts
	 */
	public static function get_message_counts( string $email = null ): array {
		$params    = $email ? array( 'email' => $email ) : array();
		$cache_key = 'message_counts';

		$counts = self::get( $cache_key, $params );

		if ( $counts === false ) {
			global $wpdb;

			if ( $email ) {
				// Counts for specific customer
				$counts = array(
					'total'   => (int) $wpdb->get_var(
						$wpdb->prepare(
							"SELECT COUNT(*) FROM {$wpdb->posts} p
                         INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
                         WHERE p.post_type = 'mhm_message' 
                         AND p.post_status = 'publish'
                         AND pm.meta_key = '_mhm_customer_email' 
                         AND pm.meta_value = %s",
							$email
						)
					),
					'pending' => (int) $wpdb->get_var(
						$wpdb->prepare(
							"SELECT COUNT(*) FROM {$wpdb->posts} p
                         INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
                         INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id 
                         WHERE p.post_type = 'mhm_message' 
                         AND p.post_status = 'publish'
                         AND pm.meta_key = '_mhm_customer_email' AND pm.meta_value = %s
                         AND pm2.meta_key = '_mhm_message_status' AND pm2.meta_value = 'pending'",
							$email
						)
					),
					'unread'  => (int) $wpdb->get_var(
						$wpdb->prepare(
							"SELECT COUNT(*) FROM {$wpdb->posts} p
                         INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
                         LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_mhm_is_read'
                         WHERE p.post_type = 'mhm_message' 
                         AND p.post_status = 'publish'
                         AND pm.meta_key = '_mhm_customer_email' AND pm.meta_value = %s
                         AND (pm2.meta_value IS NULL OR pm2.meta_value != '1')",
							$email
						)
					),
				);
			} else {
				// General counts
				$counts = array(
					'total'    => (int) $wpdb->get_var(
						"SELECT COUNT(*) FROM {$wpdb->posts} 
                         WHERE post_type = 'mhm_message' AND post_status = 'publish'"
					),
					'pending'  => (int) $wpdb->get_var(
						"SELECT COUNT(*) FROM {$wpdb->posts} p
                         INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
                         WHERE p.post_type = 'mhm_message' 
                         AND p.post_status = 'publish'
                         AND pm.meta_key = '_mhm_message_status' AND pm.meta_value = 'pending'"
					),
					'answered' => (int) $wpdb->get_var(
						"SELECT COUNT(*) FROM {$wpdb->posts} p
                         INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
                         WHERE p.post_type = 'mhm_message' 
                         AND p.post_status = 'publish'
                         AND pm.meta_key = '_mhm_message_status' AND pm.meta_value = 'answered'"
					),
					'unread'   => (int) $wpdb->get_var(
						"SELECT COUNT(*) FROM {$wpdb->posts} p
                         LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_mhm_is_read'
                         WHERE p.post_type = 'mhm_message' 
                         AND p.post_status = 'publish'
                         AND (pm.meta_value IS NULL OR pm.meta_value != '1')"
					),
				);
			}

			self::set( $cache_key, $counts, $params );
		}

		return $counts;
	}

	/**
	 * Cache thread messages
	 */
	public static function get_thread_messages( int $thread_id ): array {
		$cache_key = 'thread_messages';
		$params    = array( 'thread_id' => $thread_id );

		$messages = self::get( $cache_key, $params );

		if ( $messages === false ) {
			global $wpdb;

			$messages = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT p.*, 
                        pm_customer_name.meta_value as customer_name,
                        pm_customer_email.meta_value as customer_email,
                        pm_message_type.meta_value as message_type,
                        pm_status.meta_value as status,
                        pm_booking_id.meta_value as booking_id
                 FROM {$wpdb->posts} p
                 LEFT JOIN {$wpdb->postmeta} pm_customer_name ON p.ID = pm_customer_name.post_id AND pm_customer_name.meta_key = '_mhm_customer_name'
                 LEFT JOIN {$wpdb->postmeta} pm_customer_email ON p.ID = pm_customer_email.post_id AND pm_customer_email.meta_key = '_mhm_customer_email'
                 LEFT JOIN {$wpdb->postmeta} pm_message_type ON p.ID = pm_message_type.post_id AND pm_message_type.meta_key = '_mhm_message_type'
                 LEFT JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = '_mhm_message_status'
                 LEFT JOIN {$wpdb->postmeta} pm_booking_id ON p.ID = pm_booking_id.post_id AND pm_booking_id.meta_key = '_mhm_booking_id'
                 INNER JOIN {$wpdb->postmeta} pm_thread ON p.ID = pm_thread.post_id AND pm_thread.meta_key = '_mhm_thread_id'
                 WHERE pm_thread.meta_value = %d
                 AND p.post_type = 'mhm_message'
                 AND p.post_status = 'publish'
                 ORDER BY p.post_date ASC",
					$thread_id
				)
			);

			self::set( $cache_key, $messages, $params );
		}

		return $messages;
	}

	/**
	 * Cache customer messages
	 */
	public static function get_customer_messages( string $email, int $limit = 20, int $offset = 0 ): array {
		$cache_key = 'customer_messages';
		$params    = array(
			'email'  => $email,
			'limit'  => $limit,
			'offset' => $offset,
		);

		$messages = self::get( $cache_key, $params );

		if ( $messages === false ) {
			global $wpdb;

			$messages = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT p.*, 
                        pm_customer_name.meta_value as customer_name,
                        pm_customer_email.meta_value as customer_email,
                        pm_category.meta_value as category,
                        pm_status.meta_value as status,
                        pm_thread_id.meta_value as thread_id,
                        pm_booking_id.meta_value as booking_id,
                        pm_is_read.meta_value as is_read
                 FROM {$wpdb->posts} p
                 LEFT JOIN {$wpdb->postmeta} pm_customer_name ON p.ID = pm_customer_name.post_id AND pm_customer_name.meta_key = '_mhm_customer_name'
                 LEFT JOIN {$wpdb->postmeta} pm_customer_email ON p.ID = pm_customer_email.post_id AND pm_customer_email.meta_key = '_mhm_customer_email'
                 LEFT JOIN {$wpdb->postmeta} pm_category ON p.ID = pm_category.post_id AND pm_category.meta_key = '_mhm_message_category'
                 LEFT JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = '_mhm_message_status'
                 LEFT JOIN {$wpdb->postmeta} pm_thread_id ON p.ID = pm_thread_id.post_id AND pm_thread_id.meta_key = '_mhm_thread_id'
                 LEFT JOIN {$wpdb->postmeta} pm_booking_id ON p.ID = pm_booking_id.post_id AND pm_booking_id.meta_key = '_mhm_booking_id'
                 LEFT JOIN {$wpdb->postmeta} pm_is_read ON p.ID = pm_is_read.post_id AND pm_is_read.meta_key = '_mhm_is_read'
                 WHERE pm_customer_email.meta_value = %s
                 AND p.post_type = 'mhm_message'
                 AND p.post_status = 'publish'
                 ORDER BY p.post_date DESC
                 LIMIT %d OFFSET %d",
					$email,
					$limit,
					$offset
				)
			);

			self::set( $cache_key, $messages, $params, 1800 ); // 30 dakika cache
		}

		return $messages;
	}

	/**
	 * Cache dashboard widget data
	 */
	public static function get_dashboard_data(): array {
		$cache_key = 'dashboard_data';

		$data = self::get( $cache_key );

		if ( $data === false ) {
			global $wpdb;

			$max_messages = 5; // Default value, can be fetched from settings

			$data = array(
				'pending_count'   => (int) $wpdb->get_var(
					"SELECT COUNT(*) FROM {$wpdb->posts} p
                     INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                     WHERE p.post_type = 'mhm_message'
                     AND p.post_status = 'publish'
                     AND pm.meta_key = '_mhm_message_status'
                     AND pm.meta_value = 'pending'"
				),
				'recent_messages' => $wpdb->get_results(
					$wpdb->prepare(
						"SELECT p.ID, p.post_title, p.post_date, COALESCE(pm.meta_value, '') as customer_name
                     FROM {$wpdb->posts} p
                     INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_mhm_customer_name'
                     INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_mhm_message_status' AND pm2.meta_value = 'pending'
                     WHERE p.post_type = 'mhm_message'
                     AND p.post_status = 'publish'
                     ORDER BY p.post_date DESC
                     LIMIT %d",
						$max_messages
					)
				),
			);

			self::set( $cache_key, $data, array(), 1800 ); // 30 dakika cache
		}

		return $data;
	}

	/**
	 * Clear cache when message is created
	 *
	 * @param int|string|null $message_id Message ID (can be integer or string for UUID)
	 */
	public static function clear_message_cache( $message_id = null, string $email = null ): void {
		// Clear entire cache group (safest method)
		self::flush();

		// Clear general caches again (for certainty)
		self::delete( 'message_counts' );
		self::delete( 'dashboard_data' );
		self::delete( 'message_stats' );

		// Clear customer specific caches
		if ( $email ) {
			self::delete( 'customer_messages', array( 'email' => $email ) );
			self::delete( 'message_counts', array( 'email' => $email ) );
		}

		// Clear thread cache
		if ( $message_id ) {
			// Ensure message_id is integer (post ID is always integer)
			$message_id_int = is_numeric( $message_id ) ? (int) $message_id : null;
			if ( $message_id_int ) {
				$thread_id = get_post_meta( $message_id_int, '_mhm_thread_id', true );
				if ( $thread_id ) {
					self::delete( 'thread_messages', array( 'thread_id' => $thread_id ) );
				}
			}
		}

		// Also clear WordPress object cache (wp_cache_flush_group might not work in some cases)
		if ( function_exists( 'wp_cache_flush' ) ) {
			// This is aggressive but solves caching issues
			// Alternative: Use wp_cache_delete_group for message caches only
			// However wp_cache_delete_group is available in WordPress 6.1+
		}
	}

	/**
	 * Clear cache when message status changes
	 */
	public static function clear_status_cache( int $message_id ): void {
		$email = get_post_meta( $message_id, '_mhm_customer_email', true );
		self::clear_message_cache( $message_id, $email );
	}

	/**
	 * Cache statistics
	 */
	public static function get_cache_stats(): array {
		return array(
			'group'        => self::CACHE_GROUP,
			'expiry'       => self::CACHE_EXPIRY,
			'memory_usage' => memory_get_usage( true ),
			'peak_memory'  => memory_get_peak_usage( true ),
		);
	}
}
