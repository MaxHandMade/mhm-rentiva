<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Performance Helper
 *
 * Performance optimizations for shortcodes
 */
final class PerformanceHelper {


	/**
	 * Cache key prefix
	 */
	private const CACHE_PREFIX = 'mhm_shortcode_';

	/**
	 * Default cache duration (1 hour)
	 */
	private const DEFAULT_CACHE_DURATION = 3600;

	/**
	 * Cache data with automatic invalidation
	 *
	 * @param string $key Cache key
	 * @param mixed  $data Data to cache
	 * @param int    $duration Cache duration in seconds
	 * @param array  $tags Cache tags for invalidation
	 * @return bool Cache success
	 */
	public static function cache_set( string $key, $data, ?int $duration = null, array $tags = array() ): bool {
		$duration  = $duration ?? self::DEFAULT_CACHE_DURATION;
		$cache_key = self::CACHE_PREFIX . $key;

		// Store data with tags
		$cache_data = array(
			'data'      => $data,
			'tags'      => $tags,
			'timestamp' => time(),
		);

		return set_transient( $cache_key, $cache_data, $duration );
	}

	/**
	 * Get cached data
	 *
	 * @param string $key Cache key
	 * @param mixed  $default Default value if cache miss
	 * @return mixed Cached data or default
	 */
	public static function cache_get( string $key, $fallback = null ) {
		$cache_key = self::CACHE_PREFIX . $key;
		$cached    = get_transient( $cache_key );

		if ( $cached === false ) {
			return $fallback;
		}

		return $cached['data'] ?? $fallback;
	}

	/**
	 * Cache multiple items efficiently
	 *
	 * @param array $items Items to cache [key => data]
	 * @param int   $duration Cache duration
	 * @param array $tags Cache tags
	 * @return bool Success
	 */
	public static function cache_set_multiple( array $items, ?int $duration = null, array $tags = array() ): bool {
		$success = true;
		foreach ( $items as $key => $data ) {
			if ( ! self::cache_set( $key, $data, $duration, $tags ) ) {
				$success = false;
			}
		}
		return $success;
	}

	/**
	 * Get multiple cached items
	 *
	 * @param array $keys Cache keys
	 * @param mixed $default Default value for missing keys
	 * @return array [key => data]
	 */
	public static function cache_get_multiple( array $keys, $fallback = null ): array {
		$results = array();
		foreach ( $keys as $key ) {
			$results[ $key ] = self::cache_get( $key, $fallback );
		}
		return $results;
	}

	/**
	 * Invalidate cache by tags
	 *
	 * @param array $tags Tags to invalidate
	 * @return int Number of items invalidated
	 */
	public static function cache_invalidate_tags( array $tags ): int {
		global $wpdb;

		if ( empty( $tags ) ) {
			return 0;
		}

		$tag_conditions = array();
		foreach ( $tags as $tag ) {
			$tag_conditions[] = $wpdb->prepare( 'option_value LIKE %s', '%"' . $tag . '"%' );
		}

		$prefix_like = $wpdb->esc_like( '_transient_' . self::CACHE_PREFIX ) . '%';
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Cache index invalidation requires direct transient row cleanup.
		return $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND (" . implode( ' OR ', $tag_conditions ) . ')', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$prefix_like
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Batch vehicle data loading to prevent N+1 queries
	 *
	 * @param array $vehicle_ids Vehicle IDs
	 * @return array [vehicle_id => vehicle_data]
	 */
	public static function batch_load_vehicle_data( array $vehicle_ids ): array {
		if ( empty( $vehicle_ids ) ) {
			return array();
		}

		// Check cache first
		$cache_keys = array_map(
			function ( $id ) {
				return "vehicle_data_{$id}";
			},
			$vehicle_ids
		);

		$cached_data = self::cache_get_multiple( $cache_keys );
		$missing_ids = array();
		$results     = array();

		// Separate cached and missing data
		foreach ( $vehicle_ids as $id ) {
			$cache_key = "vehicle_data_{$id}";
			if ( $cached_data[ $cache_key ] !== null ) {
				$results[ $id ] = $cached_data[ $cache_key ];
			} else {
				$missing_ids[] = $id;
			}
		}

		// Load missing data
		if ( ! empty( $missing_ids ) ) {
			$loaded_data = self::load_vehicle_data_batch( $missing_ids );
			$results     = array_merge( $results, $loaded_data );

			// Cache the loaded data
			$cache_items = array();
			foreach ( $loaded_data as $id => $data ) {
				$cache_items[ "vehicle_data_{$id}" ] = $data;
			}
			self::cache_set_multiple( $cache_items, self::DEFAULT_CACHE_DURATION, array( 'vehicles' ) );
		}

		return $results;
	}

	/**
	 * Load vehicle data in batch to prevent N+1 queries
	 *
	 * @param array $vehicle_ids Vehicle IDs
	 * @return array [vehicle_id => vehicle_data]
	 */
	private static function load_vehicle_data_batch( array $vehicle_ids ): array {
		global $wpdb;

		if ( empty( $vehicle_ids ) ) {
			return array();
		}

		$ids_placeholders = implode( ',', array_fill( 0, count( $vehicle_ids ), '%d' ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Placeholder list is generated from trusted integer ID array length.
		$posts = $wpdb->get_results(
			$wpdb->prepare(
				"
            SELECT ID, post_title, post_excerpt, post_name, post_status
            FROM {$wpdb->posts}
            WHERE ID IN ({$ids_placeholders})
            AND post_type = 'vehicle'
        ",
				...$vehicle_ids
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$posts_by_id = array();
		foreach ( $posts as $post ) {
			$posts_by_id[ $post['ID'] ] = $post;
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Placeholder list is generated from trusted integer ID array length.
		$meta_data = $wpdb->get_results(
			$wpdb->prepare(
				"
            SELECT post_id, meta_key, meta_value
            FROM {$wpdb->postmeta}
            WHERE post_id IN ({$ids_placeholders})
            AND meta_key IN (
                '_mhm_rentiva_daily_price',
                '_mhm_rentiva_price_per_day',
                '_mhm_rentiva_featured',
                '_mhm_rentiva_category',
                '_mhm_rentiva_brand',
                '_mhm_rentiva_model',
                '_mhm_rentiva_transmission',
                '_mhm_rentiva_fuel_type',
                '_mhm_rentiva_seats',
                '_mhm_rentiva_year',
                '_mhm_rentiva_engine_power',
                '_thumbnail_id'
            )
        ",
				...$vehicle_ids
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$meta_by_post_id = array();
		foreach ( $meta_data as $meta ) {
			$meta_by_post_id[ $meta['post_id'] ][ $meta['meta_key'] ] = $meta['meta_value'];
		}

		// Combine data
		$results = array();
		foreach ( $vehicle_ids as $id ) {
			$post_data = $posts_by_id[ $id ] ?? null;
			$meta      = $meta_by_post_id[ $id ] ?? array();

			if ( $post_data ) {
				$results[ $id ] = array(
					'id'      => $id,
					'title'   => $post_data['post_title'],
					'excerpt' => $post_data['post_excerpt'],
					'slug'    => $post_data['post_name'],
					'status'  => $post_data['post_status'],
					'meta'    => $meta,
				);
			}
		}

		return $results;
	}

	/**
	 * Batch load booking availability data
	 *
	 * @param array  $vehicle_ids Vehicle IDs
	 * @param string $start_date Start date (Y-m-d)
	 * @param string $end_date End date (Y-m-d)
	 * @return array [vehicle_id => availability_data]
	 */
	public static function batch_load_availability_data( array $vehicle_ids, string $start_date, string $end_date ): array {
		if ( empty( $vehicle_ids ) ) {
			return array();
		}

		global $wpdb;

		$ids_placeholders = implode( ',', array_fill( 0, count( $vehicle_ids ), '%d' ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Placeholder list is generated from trusted integer ID array length.
		$prepare_params = array_merge( $vehicle_ids, array( $end_date, $start_date ) );

		$bookings = $wpdb->get_results(
			$wpdb->prepare(
				"
            SELECT 
                pm_vehicle.meta_value as vehicle_id,
                pm_start.meta_value as start_date,
                pm_end.meta_value as end_date,
                pm_status.meta_value as status,
                pm_payment.meta_value as payment_status
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm_vehicle ON p.ID = pm_vehicle.post_id AND pm_vehicle.meta_key = '_mhm_vehicle_id'
            INNER JOIN {$wpdb->postmeta} pm_start ON p.ID = pm_start.post_id AND pm_start.meta_key = '_mhm_start_date'
            INNER JOIN {$wpdb->postmeta} pm_end ON p.ID = pm_end.post_id AND pm_end.meta_key = '_mhm_end_date'
            LEFT JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = '_mhm_status'
            LEFT JOIN {$wpdb->postmeta} pm_payment ON p.ID = pm_payment.post_id AND pm_payment.meta_key = '_mhm_payment_status'
            WHERE p.post_type = 'vehicle_booking'
            AND p.post_status IN ('publish', 'pending', 'confirmed')
            AND pm_vehicle.meta_value IN ({$ids_placeholders})
            AND pm_start.meta_value <= %s
            AND pm_end.meta_value >= %s
            ORDER BY pm_vehicle.meta_value, pm_start.meta_value ASC
        ",
				...$prepare_params
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

		// Group by vehicle ID
		$availability_by_vehicle = array();
		foreach ( $bookings as $booking ) {
			$vehicle_id = $booking['vehicle_id'];
			if ( ! isset( $availability_by_vehicle[ $vehicle_id ] ) ) {
				$availability_by_vehicle[ $vehicle_id ] = array();
			}
			$availability_by_vehicle[ $vehicle_id ][] = $booking;
		}

		return $availability_by_vehicle;
	}

	/**
	 * Optimize query by adding proper indexes hints
	 *
	 * @param string $sql SQL query
	 * @param array  $params Query parameters
	 * @return array [sql, params]
	 */
	public static function optimize_query( string $sql, array $params = array() ): array {
		// Add query optimization hints
		$optimized_sql = $sql;

		// Add index hints for common patterns
		if ( strpos( $sql, 'vehicle_booking' ) !== false && strpos( $sql, 'postmeta' ) !== false ) {
			// This is a booking query - ensure proper indexing
			$optimized_sql = str_replace(
				'FROM {$wpdb->posts} p',
				'FROM {$wpdb->posts} p USE INDEX (type_status_date)',
				$optimized_sql
			);
		}

		return array( $optimized_sql, $params );
	}

	/**
	 * Memory usage monitoring
	 *
	 * @param string $context Context name
	 * @return array [current, peak, context]
	 */
	public static function get_memory_usage( string $context = '' ): array {
		return array(
			'current'   => memory_get_usage( true ),
			'peak'      => memory_get_peak_usage( true ),
			'context'   => $context,
			'timestamp' => time(),
		);
	}

	/**
	 * Query count monitoring
	 *
	 * @return int Number of database queries executed
	 */
	public static function get_query_count(): int {
		global $wpdb;
		return $wpdb->num_queries;
	}

	/**
	 * Performance timing helper
	 *
	 * @param callable $callback Function to time
	 * @param string   $context Context name
	 * @return array [result, time_ms, memory_usage]
	 */
	public static function time_execution( callable $callback, string $context = '' ): array {
		$start_time    = microtime( true );
		$start_memory  = memory_get_usage( true );
		$start_queries = self::get_query_count();

		$result = $callback();

		$end_time    = microtime( true );
		$end_memory  = memory_get_usage( true );
		$end_queries = self::get_query_count();

		return array(
			'result'           => $result,
			'execution_time'   => round( ( $end_time - $start_time ) * 1000, 2 ), // milliseconds
			'memory_used'      => $end_memory - $start_memory,
			'queries_executed' => $end_queries - $start_queries,
			'context'          => $context,
		);
	}

	/**
	 * Lazy loading helper for large datasets
	 *
	 * @param callable $data_loader Data loading function
	 * @param int      $page Page number
	 * @param int      $per_page Items per page
	 * @param string   $cache_key Cache key
	 * @return array [items, total, has_more]
	 */
	public static function lazy_load_data( callable $data_loader, int $page = 1, int $per_page = 20, string $cache_key = '' ): array {
		$offset = ( $page - 1 ) * $per_page;

		// Try cache first
		if ( $cache_key ) {
			$cached = self::cache_get( "lazy_load_{$cache_key}_page_{$page}" );
			if ( $cached !== null ) {
				return $cached;
			}
		}

		// Load data
		$result = $data_loader( $per_page, $offset );

		// Cache result
		if ( $cache_key ) {
			self::cache_set( "lazy_load_{$cache_key}_page_{$page}", $result, 1800 ); // 30 minutes
		}

		return $result;
	}

	/**
	 * Clear all shortcode caches
	 *
	 * @return int Number of cache entries cleared
	 */
	public static function clear_all_caches(): int {
		global $wpdb;

		$prefix_like = $wpdb->esc_like( '_transient_' . self::CACHE_PREFIX ) . '%';
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Cache maintenance operation intentionally targets transient rows.
		return $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$prefix_like
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Get cache statistics
	 *
	 * @return array Cache statistics
	 */
	public static function get_cache_stats(): array {
		global $wpdb;

		$prefix_like = $wpdb->esc_like( '_transient_' . self::CACHE_PREFIX ) . '%';
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only aggregate statistics for plugin transient cache footprint.
		$stats = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) as total_entries, SUM(CHAR_LENGTH(option_value)) as total_size, AVG(CHAR_LENGTH(option_value)) as avg_size FROM {$wpdb->options} WHERE option_name LIKE %s",
				$prefix_like
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

		return array(
			'total_entries'    => (int) $stats['total_entries'],
			'total_size_bytes' => (int) $stats['total_size'],
			'avg_size_bytes'   => round( (float) $stats['avg_size'], 2 ),
			'total_size_mb'    => round( (int) $stats['total_size'] / 1024 / 1024, 2 ),
		);
	}
}
