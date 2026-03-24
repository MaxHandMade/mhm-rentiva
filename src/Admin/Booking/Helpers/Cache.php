<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Booking\Helpers;

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query,WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value,WordPress.DB.SlowDBQuery.slow_db_query_tax_query,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Availability cache lifecycle intentionally performs direct cache-backed consistency checks.



if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Cache {

	/**
	 * Generate cache key
	 */
	private static function generateKey( int $vehicle_id, int $start_ts, int $end_ts ): string {
		return sprintf( 'mhm_avail_%d_%d_%d', $vehicle_id, $start_ts, $end_ts );
	}

	/**
	 * Get availability data from cache
	 */
	public static function getAvailability( int $vehicle_id, int $start_ts, int $end_ts ): ?array {
		$key  = self::generateKey( $vehicle_id, $start_ts, $end_ts );
		$data = get_transient( $key );

		return $data ? $data : null;
	}

	/**
	 * Save availability data to cache
	 */
	public static function setAvailability( int $vehicle_id, int $start_ts, int $end_ts, array $data ): bool {
		$key = self::generateKey( $vehicle_id, $start_ts, $end_ts );
		// ✅ Use SettingsCore::get() instead of removed BookingSettings method
		$ttl_minutes = (int) \MHMRentiva\Admin\Settings\Core\SettingsCore::get( 'mhm_rentiva_booking_cache_ttl', 60 );
		return set_transient( $key, $data, $ttl_minutes * MINUTE_IN_SECONDS );
	}

	/**
	 * Clear all cache for vehicle
	 */
	public static function invalidateVehicle( int $vehicle_id ): void {
		global $wpdb;

		$pattern = '%' . $wpdb->esc_like( 'mhm_avail_' . $vehicle_id . '_' ) . '%';

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$pattern
			)
		);
	}

	/**
	 * Clear cache for specific date range
	 */
	public static function invalidateDateRange( int $start_ts, int $end_ts ): void {
		global $wpdb;

		// Find and delete all keys containing this date range
		$pattern = '%mhm_avail_%';

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
				$pattern
			)
		);

		foreach ( $results as $result ) {
			$key   = $result->option_name;
			$parts = explode( '_', $key );

			if ( count( $parts ) >= 4 ) {
				$key_start = (int) $parts[2];
				$key_end   = (int) $parts[3];

				// Clear cache if date ranges overlap
				if ( $key_end > $start_ts && $key_start < $end_ts ) {
					delete_transient( $key );
				}
			}
		}
	}

	/**
	 * Clear all availability cache
	 */
	public static function clearAll(): void {
		global $wpdb;

		$pattern = '%' . $wpdb->esc_like( 'mhm_avail_' ) . '%';

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$pattern
			)
		);
	}

	/**
	 * Get cache statistics
	 */
	public static function getStats(): array {
		global $wpdb;

		$pattern = '%' . $wpdb->esc_like( 'mhm_avail_' ) . '%';

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s",
				$pattern
			)
		);

		return array(
			'cached_entries' => (int) $count,
			// ✅ Use SettingsCore::get() instead of removed BookingSettings method
			'cache_ttl'      => (int) \MHMRentiva\Admin\Settings\Core\SettingsCore::get( 'mhm_rentiva_booking_cache_ttl', 60 ) * MINUTE_IN_SECONDS,
		);
	}
}
