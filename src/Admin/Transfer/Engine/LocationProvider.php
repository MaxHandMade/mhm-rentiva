<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Transfer\Engine;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Location Provider (Single Source of Truth)
 *
 * Standardizes location retrieval across classic shortcodes,
 * Gutenberg blocks, and REST API.
 */
final class LocationProvider {

    /**
     * Cache key prefix
     */
    private const CACHE_PREFIX = 'mhm_locations_';

    /**
     * Get filtered locations
     *
     * @param string $service_type rental|transfer|both
     * @param bool   $force_refresh Bypass cache
     * @return array
     */
    public static function get_locations(string $service_type = 'both', bool $force_refresh = false): array
    {
        $service_type = in_array($service_type, [ 'rental', 'transfer', 'both' ], true) ? $service_type : 'both';
        $cache_key    = self::CACHE_PREFIX . $service_type;

        if (!$force_refresh) {
            $cached = get_transient($cache_key);
            if (is_array($cached)) {
                return $cached;
            }
        }

        global $wpdb;
        $table_name = self::resolve_table_name();

        $query = "SELECT id, name, type, city, allow_rental, allow_transfer FROM {$table_name} WHERE is_active = 1";

        if ($service_type === 'rental') {
            $query .= ' AND allow_rental = 1';
        } elseif ($service_type === 'transfer') {
            $query .= ' AND allow_transfer = 1';
        }

        $query .= ' ORDER BY priority ASC, name ASC';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
        $results = $wpdb->get_results($query);
        $results = is_array($results) ? $results : [];

        // Cache for 1 hour (as per performance rules)
        set_transient($cache_key, $results, HOUR_IN_SECONDS);

        return $results;
    }

    /**
     * Get locations filtered by city
     *
     * @param string $city         City name to filter by.
     * @param string $service_type rental|transfer|both
     * @return array Array of location objects.
     */
    public static function get_by_city(string $city, string $service_type = 'both'): array
    {
        if ($city === '') {
            return array();
        }

        global $wpdb;
        $table_name = self::resolve_table_name();

        $query = $wpdb->prepare(
            'SELECT id, name, type, city FROM %i WHERE is_active = 1 AND LOWER(city) = LOWER(%s)',
            $table_name,
            $city
        );

        if ($service_type === 'rental') {
            $query .= ' AND allow_rental = 1';
        } elseif ($service_type === 'transfer') {
            $query .= ' AND allow_transfer = 1';
        }

        $query .= ' ORDER BY priority ASC, name ASC';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
        $results = $wpdb->get_results($query);

        return is_array($results) ? $results : array();
    }

    /**
     * Clear all location caches
     */
    public static function clear_cache(): void
    {
        delete_transient(self::CACHE_PREFIX . 'rental');
        delete_transient(self::CACHE_PREFIX . 'transfer');
        delete_transient(self::CACHE_PREFIX . 'both');
    }

    /**
     * Get a single location by ID
     *
     * @param int $location_id
     * @return object|null
     */
    public static function get_by_id(int $location_id): ?object
    {
        if ($location_id <= 0) {
            return null;
        }

        global $wpdb;
        $table_name = self::resolve_table_name();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $row = $wpdb->get_row(
            $wpdb->prepare('SELECT id, name, type, city FROM %i WHERE id = %d', $table_name, $location_id)
        );
        return is_object($row) ? $row : null;
    }

    /**
     * Resolve table name with fallback
     */
    private static function resolve_table_name(): string
    {
        global $wpdb;
        $new_table = $wpdb->prefix . 'rentiva_transfer_locations';

        // Use static cache for table existence check within request
        static $resolved_table = null;
        if ($resolved_table !== null) {
            return $resolved_table;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $new_table));

        if ($table_exists === $new_table) {
            $resolved_table = $new_table;
        } else {
            $resolved_table = $wpdb->prefix . 'mhm_rentiva_transfer_locations';
        }

        return $resolved_table;
    }
}
