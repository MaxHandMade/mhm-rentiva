<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Booking\Helpers;

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Availability cache lifecycle intentionally performs direct cache-backed consistency checks.



if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Cache {

	/**
	 * Option-name prefix of the cache generations (see generation()).
	 */
	private const GENERATION_OPTION = 'mhmrentiva_cache_gen_';

	/**
	 * Generate cache key
	 *
	 * The cached answer carries a total price, so the key also carries a
	 * fingerprint of everything that price is made of: saving a new multiplier
	 * or a new daily price lands on a new key instead of serving the old total
	 * until the entry expires. The `mhmrentiva_avail_<vehicle>_` prefix is
	 * unchanged, so invalidateVehicle()'s cleanup DELETE still matches.
	 *
	 * It also carries the generations (the whole cache's and the vehicle's):
	 * invalidation moves a generation, so an old key is never asked for again
	 * -- whatever backs the transients. A raw DELETE alone cannot reach a
	 * persistent object cache (Redis, Memcached), nor the in-memory copy of
	 * the current request.
	 */
	private static function generateKey( int $vehicle_id, int $start_ts, int $end_ts ): string {
		$generation = substr( md5( self::generation( 'avail' ) . '|' . self::generation( 'avail_' . $vehicle_id ) ), 0, 8 );

		return sprintf( 'mhmrentiva_avail_%d_%d_%d_%s_%s', $vehicle_id, $start_ts, $end_ts, self::pricing_fingerprint( $vehicle_id ), $generation );
	}

	/**
	 * The current generation of a cache scope (`avail`, `avail_<vehicle>`,
	 * `vehicle_stats`), kept in a non-autoloaded option so update_option()
	 * keeps every object cache coherent.
	 *
	 * A missing generation is never assumed to be "the first one": a fresh
	 * value is made up, so if the option is lost (a cleanup tool, a restored
	 * table) the entries written under the old value are not read again.
	 */
	public static function generation( string $scope ): string {
		$name    = self::GENERATION_OPTION . $scope;
		$current = get_option( $name, '' );
		if ( is_string( $current ) && '' !== $current ) {
			return $current;
		}

		$fresh = self::fresh_generation();
		if ( add_option( $name, $fresh, '', false ) ) {
			return $fresh;
		}

		// Another request created it first: use theirs.
		$current = get_option( $name, '' );
		if ( is_string( $current ) && '' !== $current ) {
			return $current;
		}

		update_option( $name, $fresh, false );

		return $fresh;
	}

	/**
	 * Move a scope to a new generation: every entry keyed with the old one is
	 * orphaned at once and expires by its TTL.
	 */
	public static function bump_generation( string $scope ): void {
		update_option( self::GENERATION_OPTION . $scope, self::fresh_generation(), false );
	}

	/**
	 * A value no earlier generation had. Not wp_rand(): that is pluggable and
	 * not loaded yet when a migration clears the cache at muplugins_loaded.
	 */
	private static function fresh_generation(): string {
		try {
			$entropy = bin2hex( random_bytes( 8 ) );
		} catch ( \Exception $e ) {
			$entropy = (string) getmypid();
		}

		return substr( md5( uniqid( '', true ) . $entropy ), 0, 12 );
	}

	/**
	 * Short hash of the vehicle's daily price and every setting RentalPricing reads.
	 */
	private static function pricing_fingerprint( int $vehicle_id ): string {
		$pricing = array(
			\MHMRentiva\Admin\Vehicle\Helpers\VehicleDataHelper::get_price_per_day( $vehicle_id ),
			\MHMRentiva\Admin\Settings\Core\SettingsCore::get( 'mhmrentiva_vehicle_base_price', 1.0 ),
			\MHMRentiva\Admin\Settings\Core\SettingsCore::get( 'mhmrentiva_vehicle_weekend_multiplier', 1.2 ),
			\MHMRentiva\Admin\Settings\Core\SettingsCore::get( 'mhmrentiva_vehicle_seasonal_pricing', '0' ),
			\MHMRentiva\Admin\Vehicle\Settings\VehiclePricingSettings::get_seasonal_multipliers(),
		);

		return substr( md5( (string) wp_json_encode( $pricing ) ), 0, 8 );
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
		$ttl_minutes = (int) \MHMRentiva\Admin\Settings\Core\SettingsCore::get( 'mhmrentiva_booking_cache_ttl', 60 );
		return set_transient( $key, $data, $ttl_minutes * MINUTE_IN_SECONDS );
	}

	/**
	 * Clear all cache for vehicle
	 *
	 * Moves the vehicle's generation (what makes the old entries unreachable
	 * on every backend), then deletes the rows a database-backed site holds.
	 */
	public static function invalidateVehicle( int $vehicle_id ): void {
		global $wpdb;

		self::bump_generation( 'avail_' . $vehicle_id );

		$pattern = '%' . $wpdb->esc_like( 'mhmrentiva_avail_' . $vehicle_id . '_' ) . '%';

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$pattern
			)
		);
	}

	/**
	 * Clear all availability cache
	 */
	public static function clearAll(): void {
		global $wpdb;

		self::bump_generation( 'avail' );

		$pattern = '%' . $wpdb->esc_like( 'mhmrentiva_avail_' ) . '%';

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

		$pattern = '%' . $wpdb->esc_like( 'mhmrentiva_avail_' ) . '%';

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s",
				$pattern
			)
		);

		return array(
			'cached_entries' => (int) $count,
			// ✅ Use SettingsCore::get() instead of removed BookingSettings method
			'cache_ttl'      => (int) \MHMRentiva\Admin\Settings\Core\SettingsCore::get( 'mhmrentiva_booking_cache_ttl', 60 ) * MINUTE_IN_SECONDS,
		);
	}
}
