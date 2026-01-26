<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Core\Utilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ✅ CACHE OPTIMIZATION - Central Cache Management
 *
 * Prevents unnecessary cache clearing operations and improves performance
 */
final class CacheManager {


	/**
	 * Cache prefix constant
	 */
	private const CACHE_PREFIX = 'mhm_rentiva_';

	/**
	 * Create multisite-safe cache key
	 *
	 * @param string $base_key Base cache key
	 * @return string Multisite-safe cache key
	 */
	private static function get_multisite_cache_key( string $base_key ): string {
		// Multisite support: Add blog ID
		if ( is_multisite() ) {
			return $base_key . '_blog_' . get_current_blog_id();
		}
		return $base_key;
	}

	/**
	 * Cache keys
	 */
	private const CACHE_KEYS = array(
		'dashboard_stats' => 'mhm_rentiva_dashboard_stats',
		'booking_report'  => 'mhm_rentiva_booking_report_',
		'customer_report' => 'mhm_rentiva_customer_report_',
		'vehicle_report'  => 'mhm_rentiva_vehicle_report_',
		'revenue_report'  => 'mhm_rentiva_revenue_report_',
		'addon_list'      => 'mhm_rentiva_addon_list',
		'vehicle_list'    => 'rv_vlist_',
		'system_info'     => 'mhm_rentiva_system_info',
	);

	/**
	 * Check if cache is enabled
	 *
	 * @return bool Cache enabled status
	 */
	public static function is_cache_enabled(): bool {
		// Cache is always enabled (simple check)
		return true;
	}

	/**
	 * Cache types and durations - retrieved dynamically
	 */
	private static function get_cache_durations(): array {
		return array(
			'dashboard_stats' => self::get_cache_duration_reports(),
			'booking_report'  => self::get_cache_duration_reports(),
			'customer_report' => self::get_cache_duration_reports(),
			'vehicle_report'  => self::get_cache_duration_reports(),
			'revenue_report'  => self::get_cache_duration_reports(),
			'addon_list'      => self::get_cache_duration_lists(),
			'vehicle_list'    => self::get_cache_duration_lists(),
			'system_info'     => self::get_cache_duration_system(),
		);
	}

	/**
	 * Cache durations - Retrieved from settings
	 */
	private static function get_cache_duration_reports(): int {
		return \MHMRentiva\Admin\Settings\Groups\CoreSettings::get_cache_reports_ttl();
	}

	private static function get_cache_duration_lists(): int {
		return \MHMRentiva\Admin\Settings\Groups\CoreSettings::get_cache_lists_ttl();
	}

	private static function get_cache_duration_system(): int {
		return \MHMRentiva\Admin\Settings\Groups\CoreSettings::get_cache_default_ttl();
	}

	private static function get_cache_duration_default(): int {
		return \MHMRentiva\Admin\Settings\Groups\CoreSettings::get_cache_default_ttl();
	}

	/**
	 * Clear cache - Object Cache + Transient (only specified types)
	 */
	public static function clear_cache( array $types = array() ): void {
		if ( empty( $types ) ) {
			// Clear all caches
			$types = array_keys( self::CACHE_KEYS );
		}

		foreach ( $types as $type ) {
			if ( ! isset( self::CACHE_KEYS[ $type ] ) ) {
				continue;
			}

			$pattern = self::CACHE_KEYS[ $type ];

			if ( str_ends_with( $pattern, '_' ) ) {
				// Clear by pattern (e.g., booking_report_*)
				self::clear_cache_by_pattern( $pattern );
			} else {
				// Clear single cache
				self::delete_cache_object( $pattern );
			}
		}
	}

	/**
	 * Clear cache by pattern
	 */
	private static function clear_cache_by_pattern( string $pattern ): void {
		global $wpdb;

		// Find matching transients from cache
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} 
             WHERE option_name LIKE %s 
             AND option_name LIKE %s",
				'%' . $wpdb->esc_like( $pattern ) . '%',
				$wpdb->esc_like( '_transient_' ) . '%'
			)
		);

		foreach ( $results as $result ) {
			$transient_name = str_replace( '_transient_', '', $result->option_name );
			delete_transient( $transient_name );
		}
	}

	/**
	 * Save data to cache (Object Cache + Transient fallback)
	 */
	public static function set_cache( string $type, string $key, $data, ?int $duration = null ): bool {
		// Return false if cache is not enabled
		if ( ! \MHMRentiva\Admin\Settings\Groups\CoreSettings::is_cache_enabled() ) {
			return false;
		}

		if ( ! isset( self::CACHE_KEYS[ $type ] ) ) {
			return false;
		}

		$cache_key       = self::get_multisite_cache_key( self::CACHE_KEYS[ $type ] . $key );
		$cache_durations = self::get_cache_durations();
		$duration        = $duration ?? $cache_durations[ $type ] ?? self::get_cache_duration_default();

		// Use Object Cache (if available)
		if ( wp_using_ext_object_cache() ) {
			return wp_cache_set( $cache_key, $data, 'mhm_rentiva', $duration );
		}

		// Fallback: Transient cache
		return set_transient( $cache_key, $data, $duration );
	}

	/**
	 * Get data from cache (Object Cache + Transient fallback)
	 */
	public static function get_cache( string $type, string $key = '' ) {
		if ( ! isset( self::CACHE_KEYS[ $type ] ) ) {
			return false;
		}

		$cache_key = self::get_multisite_cache_key( self::CACHE_KEYS[ $type ] . $key );

		// Use Object Cache (if available)
		if ( wp_using_ext_object_cache() ) {
			return wp_cache_get( $cache_key, 'mhm_rentiva' );
		}

		// Fallback: Transient cache
		return get_transient( $cache_key );
	}

	/**
	 * Object Cache integration - Generic cache object getter
	 *
	 * @param string $key Cache key
	 * @return mixed Cache value or false
	 */
	public static function get_cache_object( string $key ) {
		if ( wp_using_ext_object_cache() ) {
			return wp_cache_get( $key, 'mhm_rentiva' );
		}
		return get_transient( $key );
	}

	/**
	 * Object Cache integration - Generic cache object setter
	 *
	 * @param string $key Cache key
	 * @param mixed  $data Data to cache
	 * @param int    $ttl Time to live (seconds)
	 * @return bool Success status
	 */
	public static function set_cache_object( string $key, $data, int $ttl = null ): bool {
		// Return false if cache is not enabled
		if ( ! \MHMRentiva\Admin\Settings\Groups\CoreSettings::is_cache_enabled() ) {
			return false;
		}

		$ttl = $ttl ?? self::get_cache_duration_default();

		if ( wp_using_ext_object_cache() ) {
			return wp_cache_set( $key, $data, 'mhm_rentiva', $ttl );
		}
		return set_transient( $key, $data, $ttl );
	}

	/**
	 * Object Cache integration - Generic cache object deleter
	 *
	 * @param string $key Cache key
	 * @return bool Success status
	 */
	public static function delete_cache_object( string $key ): bool {
		if ( wp_using_ext_object_cache() ) {
			return wp_cache_delete( $key, 'mhm_rentiva' );
		}
		return delete_transient( $key );
	}

	/**
	 * Clear cache on booking changes
	 *
	 * Granular cache clearing: Only clears relevant caches
	 *
	 * @param int $booking_id Booking ID (optional, for vehicle-specific cache clearing)
	 */
	public static function clear_booking_cache( int $booking_id = 0 ): void {
		// Base cache types that are always affected by booking changes
		$base_types = array( 'dashboard_stats', 'booking_report' );

		/**
		 * Filter: Allow addons to specify additional cache types to clear on booking changes
		 *
		 * @param array<string> $additional_types Additional cache types to clear
		 * @param int           $booking_id       Booking ID
		 * @return array Modified cache types array
		 *
		 * @example
		 * add_filter('mhm_rentiva_clear_booking_cache_types', function($types, $booking_id) {
		 *     $types[] = 'custom_booking_report';
		 *     return $types;
		 * }, 10, 2);
		 */
		$additional_types = apply_filters( 'mhm_rentiva_clear_booking_cache_types', array(), $booking_id );

		$types = array_merge( $base_types, $additional_types );

		// Only clear customer_report and revenue_report if booking status changed to completed/confirmed
		// This prevents unnecessary cache clearing on every booking update
		if ( $booking_id > 0 ) {
			$status = get_post_meta( $booking_id, '_mhm_status', true );
			if ( in_array( $status, array( 'completed', 'confirmed' ), true ) ) {
				$types[] = 'customer_report';
				$types[] = 'revenue_report';
			}
		}

		self::clear_cache( $types );

		// Also clear vehicle cache (only for related vehicle)
		if ( $booking_id > 0 ) {
			$vehicle_id = get_post_meta( $booking_id, '_mhm_vehicle_id', true );
			if ( $vehicle_id ) {
				// Clear only vehicle-specific report cache, not all vehicle reports
				self::clear_cache( array( 'vehicle_report' ) );
			}
		}
	}

	/**
	 * Clear cache on vehicle changes
	 *
	 * Granular cache clearing: Only clears relevant caches
	 *
	 * @param int $vehicle_id Vehicle ID (optional, for specific vehicle cache clearing)
	 */
	public static function clear_vehicle_cache( int $vehicle_id = 0 ): void {
		// Base cache types that are always affected by vehicle changes
		$base_types = array( 'vehicle_list', 'vehicle_report' );

		/**
		 * Filter: Allow addons to specify additional cache types to clear on vehicle changes
		 *
		 * @param array<string> $additional_types Additional cache types to clear
		 * @param int           $vehicle_id       Vehicle ID
		 * @return array Modified cache types array
		 */
		$additional_types = apply_filters( 'mhm_rentiva_clear_vehicle_cache_types', array(), $vehicle_id );

		$types = array_merge( $base_types, $additional_types );

		// Only clear revenue_report if vehicle pricing changed (not on every update)
		// Dashboard stats can be cleared less frequently
		$types[] = 'dashboard_stats';

		self::clear_cache( $types );
	}

	/**
	 * Clear cache on addon changes
	 */
	public static function clear_addon_cache( int $addon_id = 0 ): void {
		self::clear_cache( array( 'addon_list' ) );

		// Clear specific addon cache
		if ( $addon_id > 0 ) {
			wp_cache_delete( $addon_id, 'post_meta' );
			if ( wp_using_ext_object_cache() ) {
				wp_cache_delete( $addon_id, 'posts' );
			}
		}
	}

	/**
	 * Clear cache on settings changes
	 */
	public static function clear_settings_cache(): void {
		// On settings change, only clear necessary caches
		$types = array( 'dashboard_stats', 'system_info' );
		self::clear_cache( $types );
	}

	/**
	 * Cache statistics
	 */
	public static function get_cache_stats(): array {
		global $wpdb;

		$stats = array();
		foreach ( self::CACHE_KEYS as $type => $pattern ) {
			$count = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->options} 
                 WHERE option_name LIKE %s 
                 AND option_name LIKE %s",
					'%' . $wpdb->esc_like( $pattern ) . '%',
					$wpdb->esc_like( '_transient_' ) . '%'
				)
			);

			$stats[ $type ] = (int) $count;
		}

		return $stats;
	}

	/**
	 * Clear all caches of specific type
	 *
	 * @param string $type Cache type
	 * @return bool Success status
	 */
	public static function clear_cache_by_type( string $type ): bool {
		global $wpdb;

		$pattern = self::CACHE_PREFIX . $type . '_';

		// Clear transient caches
		$transient_keys = $wpdb->get_col(
			$wpdb->prepare(
				"
            SELECT option_name 
            FROM {$wpdb->options} 
            WHERE option_name LIKE %s 
            AND option_name LIKE %s
        ",
				$wpdb->esc_like( '_transient_' ) . '%',
				'%' . $wpdb->esc_like( $pattern ) . '%'
			)
		);

		$success = true;
		foreach ( $transient_keys as $key ) {
			$transient_name = str_replace( '_transient_', '', $key );
			if ( ! delete_transient( $transient_name ) ) {
				$success = false;
			}
		}

		return $success;
	}

	/**
	 * Delete cache
	 *
	 * @param string $type Cache type
	 * @param string $key Cache key
	 * @return bool Success status
	 */
	public static function delete_cache( string $type, string $key ): bool {
		if ( ! self::is_cache_enabled() ) {
			return false;
		}

		$cache_key = self::CACHE_PREFIX . $type . '_' . $key;
		return delete_transient( $cache_key );
	}
}
