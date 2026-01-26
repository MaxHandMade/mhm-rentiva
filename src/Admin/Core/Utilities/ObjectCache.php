<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Core\Utilities;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * ✅ OBJECT CACHE INTEGRATION - Redis/Memcached Support
 *
 * Extends WordPress object cache API with Redis and Memcached support
 */
final class ObjectCache
{

	/**
	 * Cache backend types
	 */
	private const BACKEND_WP_CACHE  = 'wp_cache';
	private const BACKEND_REDIS     = 'redis';
	private const BACKEND_MEMCACHED = 'memcached';
	private const BACKEND_TRANSIENT = 'transient';

	/**
	 * Cache groups
	 */
	public const GROUP_BOOKINGS  = 'mhm_rentiva_bookings';
	public const GROUP_VEHICLES  = 'mhm_rentiva_vehicles';
	public const GROUP_CUSTOMERS = 'mhm_rentiva_customers';
	public const GROUP_REPORTS   = 'mhm_rentiva_reports';
	private const GROUP_SETTINGS = 'mhm_rentiva_settings';
	private const GROUP_QUERIES  = 'mhm_rentiva_queries';

	/**
	 * Cache durations - Retrieved from settings
	 */
	private static function get_default_expiration(): int
	{
		return \MHMRentiva\Admin\Settings\Groups\CoreSettings::get_cache_default_ttl();
	}

	private static function get_long_expiration(): int
	{
		return 24 * HOUR_IN_SECONDS; // 24 hours can remain constant
	}

	private static function get_short_expiration(): int
	{
		return \MHMRentiva\Admin\Settings\Groups\CoreSettings::get_cache_lists_ttl();
	}

	/**
	 * Current backend type
	 */
	private static ?string $current_backend = null;

	/**
	 * Cache statistics
	 */
	private static array $cache_stats = array(
		'hits'    => 0,
		'misses'  => 0,
		'sets'    => 0,
		'deletes' => 0,
	);

	/**
	 * Detect cache backend
	 */
	public static function detect_backend(): string
	{
		if (self::$current_backend !== null) {
			return self::$current_backend;
		}

		// Redis check
		if (class_exists('Redis') && function_exists('wp_cache_get') && wp_using_ext_object_cache()) {
			try {
				$test_key = 'mhm_rentiva_redis_test_' . time();
				wp_cache_set($test_key, 'test', self::GROUP_SETTINGS, 60);
				$result = wp_cache_get($test_key, self::GROUP_SETTINGS);
				wp_cache_delete($test_key, self::GROUP_SETTINGS);

				if ($result === 'test') {
					self::$current_backend = self::BACKEND_REDIS;
					return self::$current_backend;
				}
			} catch (\Exception $e) {
				// Redis not working
			}
		}

		// Memcached check
		if (class_exists('Memcached') && function_exists('wp_cache_get') && wp_using_ext_object_cache()) {
			try {
				$test_key = 'mhm_rentiva_memcached_test_' . time();
				wp_cache_set($test_key, 'test', self::GROUP_SETTINGS, 60);
				$result = wp_cache_get($test_key, self::GROUP_SETTINGS);
				wp_cache_delete($test_key, self::GROUP_SETTINGS);

				if ($result === 'test') {
					self::$current_backend = self::BACKEND_MEMCACHED;
					return self::$current_backend;
				}
			} catch (\Exception $e) {
				// Memcached not working
			}
		}

		// WordPress object cache check
		if (wp_using_ext_object_cache()) {
			self::$current_backend = self::BACKEND_WP_CACHE;
		} else {
			self::$current_backend = self::BACKEND_TRANSIENT;
		}

		return self::$current_backend;
	}

	/**
	 * Save data to cache
	 */
	public static function set(string $key, $data, string $group = self::GROUP_SETTINGS, ?int $expiration = null): bool
	{
		// Return false if cache is not enabled
		if (! \MHMRentiva\Admin\Settings\Groups\CoreSettings::is_cache_enabled()) {
			return false;
		}

		$expiration = $expiration ?? self::get_default_expiration();
		$backend    = self::detect_backend();
		++self::$cache_stats['sets'];

		switch ($backend) {
			case self::BACKEND_REDIS:
			case self::BACKEND_MEMCACHED:
			case self::BACKEND_WP_CACHE:
				return wp_cache_set($key, $data, $group, $expiration);

			case self::BACKEND_TRANSIENT:
				$transient_key = self::get_transient_key($key, $group);
				return set_transient($transient_key, $data, $expiration);

			default:
				return false;
		}
	}

	/**
	 * Get data from cache
	 */
	public static function get(string $key, string $group = self::GROUP_SETTINGS, bool $force = false)
	{
		$backend = self::detect_backend();

		switch ($backend) {
			case self::BACKEND_REDIS:
			case self::BACKEND_MEMCACHED:
			case self::BACKEND_WP_CACHE:
				$result = wp_cache_get($key, $group, $force);
				break;

			case self::BACKEND_TRANSIENT:
				$transient_key = self::get_transient_key($key, $group);
				$result        = get_transient($transient_key);
				break;

			default:
				$result = false;
		}

		if ($result !== false) {
			++self::$cache_stats['hits'];
		} else {
			++self::$cache_stats['misses'];
		}

		return $result;
	}

	/**
	 * Delete data from cache
	 */
	public static function delete(string $key, string $group = self::GROUP_SETTINGS): bool
	{
		$backend = self::detect_backend();
		++self::$cache_stats['deletes'];

		switch ($backend) {
			case self::BACKEND_REDIS:
			case self::BACKEND_MEMCACHED:
			case self::BACKEND_WP_CACHE:
				return wp_cache_delete($key, $group);

			case self::BACKEND_TRANSIENT:
				$transient_key = self::get_transient_key($key, $group);
				return delete_transient($transient_key);

			default:
				return false;
		}
	}

	/**
	 * Clear cache by group
	 */
	public static function flush_group(string $group): bool
	{
		$backend = self::detect_backend();

		switch ($backend) {
			case self::BACKEND_REDIS:
			case self::BACKEND_MEMCACHED:
			case self::BACKEND_WP_CACHE:
				if (function_exists('wp_cache_flush_group')) {
					return (bool) wp_cache_flush_group($group);
				}

				$fallback = apply_filters('mhm_rentiva_object_cache_flush_group', null, $group, $backend);
				if ($fallback !== null) {
					return (bool) $fallback;
				}

				self::log_cache_notice(
					'Object cache backend does not support group flush',
					array(
						'backend' => $backend,
						'group'   => $group,
					)
				);

				return false;

			case self::BACKEND_TRANSIENT:
				// Use pattern for group clearing in transients
				return self::flush_group_pattern($group);

			default:
				return false;
		}
	}

	/**
	 * Clear all cache
	 */
	public static function flush(): bool
	{
		$backend = self::detect_backend();

		switch ($backend) {
			case self::BACKEND_REDIS:
			case self::BACKEND_MEMCACHED:
			case self::BACKEND_WP_CACHE:
				$groups  = self::get_registered_groups();
				$flushed = 0;

				foreach ($groups as $group) {
					if (self::flush_group($group)) {
						++$flushed;
					}
				}

				if ($flushed > 0) {
					self::log_cache_notice(
						'Object cache groups flushed',
						array(
							'backend'        => $backend,
							'groups_flushed' => $flushed,
							'total_groups'   => count($groups),
						)
					);

					return true;
				}

				if (apply_filters('mhm_rentiva_object_cache_allow_global_flush', false, $backend)) {
					$result = function_exists('wp_cache_flush') ? wp_cache_flush() : false;

					self::log_cache_notice(
						'Global object cache flush executed',
						array(
							'backend' => $backend,
							'result'  => (bool) $result,
						)
					);

					return (bool) $result;
				}

				self::log_cache_notice(
					'Global object cache flush skipped',
					array(
						'backend' => $backend,
					)
				);

				return false;

			case self::BACKEND_TRANSIENT:
				// Only clear MHM Rentiva caches
				return self::flush_mhm_transients();

			default:
				return false;
		}
	}

	/**
	 * Check if cache exists
	 */
	public static function exists(string $key, string $group = self::GROUP_SETTINGS): bool
	{
		return self::get($key, $group) !== false;
	}

	/**
	 * Add data to cache (only if not exists)
	 */
	public static function add(string $key, $data, string $group = self::GROUP_SETTINGS, ?int $expiration = null): bool
	{
		// Return false if cache is not enabled
		if (! \MHMRentiva\Admin\Settings\Groups\CoreSettings::is_cache_enabled()) {
			return false;
		}

		if (self::exists($key, $group)) {
			return false;
		}

		$expiration = $expiration ?? self::get_default_expiration();
		return self::set($key, $data, $group, $expiration);
	}

	/**
	 * Update data in cache (only if exists)
	 */
	public static function replace(string $key, $data, string $group = self::GROUP_SETTINGS, ?int $expiration = null): bool
	{
		// Return false if cache is not enabled
		if (! \MHMRentiva\Admin\Settings\Groups\CoreSettings::is_cache_enabled()) {
			return false;
		}

		if (! self::exists($key, $group)) {
			return false;
		}

		$expiration = $expiration ?? self::get_default_expiration();
		return self::set($key, $data, $group, $expiration);
	}

	/**
	 * Cache increment
	 */
	public static function increment(string $key, int $offset = 1, string $group = self::GROUP_SETTINGS): int|false
	{
		$backend = self::detect_backend();

		switch ($backend) {
			case self::BACKEND_REDIS:
			case self::BACKEND_MEMCACHED:
			case self::BACKEND_WP_CACHE:
				return wp_cache_incr($key, $offset, $group);

			case self::BACKEND_TRANSIENT:
				$transient_key = self::get_transient_key($key, $group);
				$current       = get_transient($transient_key);
				if ($current === false) {
					$current = 0;
				}
				$new_value = $current + $offset;
				set_transient($transient_key, $new_value, self::get_default_expiration());
				return $new_value;

			default:
				return false;
		}
	}

	/**
	 * Cache decrement
	 */
	public static function decrement(string $key, int $offset = 1, string $group = self::GROUP_SETTINGS): int|false
	{
		$backend = self::detect_backend();

		switch ($backend) {
			case self::BACKEND_REDIS:
			case self::BACKEND_MEMCACHED:
			case self::BACKEND_WP_CACHE:
				return wp_cache_decr($key, $offset, $group);

			case self::BACKEND_TRANSIENT:
				$transient_key = self::get_transient_key($key, $group);
				$current       = get_transient($transient_key);
				if ($current === false) {
					$current = 0;
				}
				$new_value = max(0, $current - $offset);
				set_transient($transient_key, $new_value, self::get_default_expiration());
				return $new_value;

			default:
				return false;
		}
	}

	/**
	 * Cache statistics
	 */
	public static function get_stats(): array
	{
		return array(
			'backend'  => self::detect_backend(),
			'stats'    => self::$cache_stats,
			'hit_rate' => self::get_hit_rate(),
		);
	}

	/**
	 * Calculate cache hit rate
	 */
	public static function get_hit_rate(): float
	{
		$total = self::$cache_stats['hits'] + self::$cache_stats['misses'];
		if ($total === 0) {
			return 0.0;
		}

		return round((self::$cache_stats['hits'] / $total) * 100, 2);
	}

	/**
	 * Cache status
	 */
	public static function is_available(): bool
	{
		$backend = self::detect_backend();

		switch ($backend) {
			case self::BACKEND_REDIS:
			case self::BACKEND_MEMCACHED:
			case self::BACKEND_WP_CACHE:
				return wp_using_ext_object_cache();

			case self::BACKEND_TRANSIENT:
				return true;

			default:
				return false;
		}
	}

	/**
	 * Cache performance test
	 */
	public static function performance_test(int $iterations = 100): array
	{
		$test_key  = 'mhm_rentiva_perf_test_' . time();
		$test_data = array(
			'timestamp' => current_time('timestamp'),
			'data'      => str_repeat('test_data_', 100), // ~1KB data
		);

		$start_time = microtime(true);

		// Write test
		for ($i = 0; $i < $iterations; $i++) {
			self::set($test_key . '_' . $i, $test_data, self::GROUP_SETTINGS, 300);
		}

		$write_time = microtime(true) - $start_time;

		// Read test
		$start_time = microtime(true);
		$hits       = 0;

		for ($i = 0; $i < $iterations; $i++) {
			$result = self::get($test_key . '_' . $i, self::GROUP_SETTINGS);
			if ($result !== false) {
				++$hits;
			}
		}

		$read_time = microtime(true) - $start_time;

		// Cleanup
		for ($i = 0; $i < $iterations; $i++) {
			self::delete($test_key . '_' . $i, self::GROUP_SETTINGS);
		}

		return array(
			'backend'        => self::detect_backend(),
			'iterations'     => $iterations,
			'write_time'     => round($write_time * 1000, 2), // ms
			'read_time'      => round($read_time * 1000, 2), // ms
			'hits'           => $hits,
			'hit_rate'       => round(($hits / $iterations) * 100, 2),
			'avg_write_time' => round(($write_time / $iterations) * 1000, 2), // ms
			'avg_read_time'  => round(($read_time / $iterations) * 1000, 2), // ms
		);
	}

	/**
	 * Create transient key
	 */
	private static function get_transient_key(string $key, string $group): string
	{
		return 'mhm_rentiva_' . $group . '_' . md5($key);
	}

	/**
	 * Clear transient by group pattern
	 */
	private static function flush_group_pattern(string $group): bool
	{
		global $wpdb;

		$pattern = 'mhm_rentiva_' . $group . '_%';

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} 
             WHERE option_name LIKE %s 
             AND option_name LIKE %s",
				'%' . $wpdb->esc_like($pattern) . '%',
				$wpdb->esc_like('_transient_') . '%'
			)
		);

		$deleted = 0;
		foreach ($results as $result) {
			$transient_name = str_replace('_transient_', '', $result->option_name);
			if (delete_transient($transient_name)) {
				++$deleted;
			}
		}

		return $deleted > 0;
	}

	/**
	 * Clear MHM Rentiva transients
	 */
	private static function flush_mhm_transients(): bool
	{
		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} 
             WHERE option_name LIKE %s 
             AND option_name LIKE %s",
				'%' . $wpdb->esc_like('mhm_rentiva_') . '%',
				$wpdb->esc_like('_transient_') . '%'
			)
		);

		$deleted = 0;
		foreach ($results as $result) {
			$transient_name = str_replace('_transient_', '', $result->option_name);
			if (delete_transient($transient_name)) {
				++$deleted;
			}
		}

		return $deleted > 0;
	}

	/**
	 * Registered cache groups for plugin
	 */
	private static function get_registered_groups(): array
	{
		return array(
			self::GROUP_BOOKINGS,
			self::GROUP_VEHICLES,
			self::GROUP_CUSTOMERS,
			self::GROUP_REPORTS,
			self::GROUP_SETTINGS,
			self::GROUP_QUERIES,
		);
	}

	private static function log_cache_notice(string $message, array $context = array()): void
	{
		if (class_exists('\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger')) {
			\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::info(
				$message,
				$context,
				\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::CATEGORY_PERFORMANCE
			);
		}
	}

	/**
	 * Cache temperature (hot/cold cache detection)
	 */
	public static function get_cache_temperature(): array
	{
		$groups = array(
			self::GROUP_BOOKINGS,
			self::GROUP_VEHICLES,
			self::GROUP_CUSTOMERS,
			self::GROUP_REPORTS,
			self::GROUP_SETTINGS,
		);

		$temperatures = array();

		foreach ($groups as $group) {
			$test_key   = 'mhm_temp_test_' . time();
			$start_time = microtime(true);

			// Write to cache and read
			self::set($test_key, 'test', $group, 60);
			$result = self::get($test_key, $group);
			self::delete($test_key, $group);

			$response_time = (microtime(true) - $start_time) * 1000; // ms

			if ($response_time < 1) {
				$temperature = 'hot';
			} elseif ($response_time < 10) {
				$temperature = 'warm';
			} else {
				$temperature = 'cold';
			}

			$temperatures[$group] = array(
				'temperature'   => $temperature,
				'response_time' => round($response_time, 2),
			);
		}

		return $temperatures;
	}
}
