<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Transfer\Engine;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Transfer Route Provider
 *
 * Single source of truth for popular-routes queries with JOIN-based
 * eligibility filtering (active locations + transfer-allowed) and a
 * 1-hour transient cache, mirroring {@see LocationProvider}.
 *
 * @since 4.34.0
 */
final class TransferRouteProvider {

    private const CACHE_PREFIX       = 'mhm_popular_routes_';
    private const CACHE_GROUP_OPTION = 'mhm_popular_routes_cache_keys';
    private const CACHE_TTL          = HOUR_IN_SECONDS;

    /**
     * Get popular routes with origin/destination location data joined.
     *
     * @param array{
     *     limit?: int,
     *     order?: string,
     *     featured_only?: bool,
     *     filter_origin_city?: string,
     *     filter_origin_type?: string,
     *     force_refresh?: bool,
     * } $args
     *
     * @return array<int,object> Each row carries: id, origin_id, destination_id, distance_km,
     *                           duration_min, pricing_method, base_price, min_price, max_price,
     *                           is_featured, origin_name, origin_city, origin_type,
     *                           destination_name, destination_city, destination_type.
     */
    public static function get_popular_routes(array $args = []): array
    {
        $defaults = [
            'limit'              => 6,
            'order'              => 'featured',
            'featured_only'      => false,
            'filter_origin_city' => '',
            'filter_origin_type' => '',
            'force_refresh'      => false,
        ];
        $args     = array_merge($defaults, $args);

        $args['limit']              = max(1, min(50, (int) $args['limit']));
        $args['order']              = self::sanitize_order( (string) $args['order']);
        $args['featured_only']      = (bool) $args['featured_only'];
        $args['filter_origin_city'] = sanitize_text_field( (string) $args['filter_origin_city']);
        $args['filter_origin_type'] = sanitize_key( (string) $args['filter_origin_type']);

        $cache_key = self::build_cache_key($args);

        if (! $args['force_refresh']) {
            $cached = get_transient($cache_key);
            if (is_array($cached)) {
                return $cached;
            }
        }

        global $wpdb;
        $routes_table    = self::resolve_routes_table();
        $locations_table = self::resolve_locations_table();

        $where  = [ 'o.is_active = 1', 'd.is_active = 1', 'o.allow_transfer = 1', 'd.allow_transfer = 1' ];
        $params = [];

        if ($args['featured_only']) {
            $where[] = 'r.is_featured = 1';
        }

        if ($args['filter_origin_city'] !== '') {
            $where[]  = 'LOWER(o.city) = LOWER(%s)';
            $params[] = $args['filter_origin_city'];
        }

        if ($args['filter_origin_type'] !== '') {
            $where[]  = 'o.type = %s';
            $params[] = $args['filter_origin_type'];
        }

        $hidden_routes = \MHMRentiva\Admin\Licensing\LiteOverflow\OverflowRegistry::get( 'route' );
        if ( ! empty( $hidden_routes ) ) {
            $placeholders = implode( ', ', array_fill( 0, count( $hidden_routes ), '%d' ) );
            $where[]      = "r.id NOT IN ({$placeholders})";
            foreach ( $hidden_routes as $hid ) {
                $params[] = (int) $hid;
            }
        }

        $where_sql = implode(' AND ', $where);
        $order_sql = self::build_order_sql($args['order']);

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Identifiers + composed WHERE/ORDER are sanitized; values bound via prepare().
        $sql      = "SELECT r.id, r.origin_id, r.destination_id, r.distance_km, r.duration_min,
                       r.pricing_method, r.base_price, r.min_price, r.max_price, r.is_featured,
                       o.name AS origin_name, o.city AS origin_city, o.type AS origin_type,
                       d.name AS destination_name, d.city AS destination_city, d.type AS destination_type
                FROM `{$routes_table}` r
                INNER JOIN `{$locations_table}` o ON r.origin_id = o.id
                INNER JOIN `{$locations_table}` d ON r.destination_id = d.id
                WHERE {$where_sql}
                ORDER BY {$order_sql}
                LIMIT %d";
        $params[] = $args['limit'];

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Composed query template is built from sanitized identifiers + %s/%d placeholders bound via wpdb->prepare().
        $prepared = $wpdb->prepare($sql, $params);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- $prepared comes from wpdb->prepare() above; static analyzer cannot follow the assignment. Custom table outside WP_Query; results cached via transient below.
        $rows = $wpdb->get_results($prepared);
        $rows = is_array($rows) ? $rows : [];

        set_transient($cache_key, $rows, self::CACHE_TTL);
        self::register_cache_key($cache_key);

        return $rows;
    }

    /**
     * Clear all popular-routes transient caches. Call after admin CRUD on routes/locations.
     */
    public static function clear_cache(): void
    {
        $keys = get_option(self::CACHE_GROUP_OPTION, []);
        if (is_array($keys)) {
            foreach ($keys as $key) {
                if (is_string($key) && $key !== '') {
                    delete_transient($key);
                }
            }
        }
        delete_option(self::CACHE_GROUP_OPTION);
    }

    /**
     * Total number of transfer routes, counted against the RESOLVED table
     * (new `rentiva_transfer_routes` or legacy `mhm_rentiva_transfer_routes`).
     *
     * Single source of truth for the Lite route limit — used by the limit
     * notice and the route-creation gate. Both previously hardcoded the legacy
     * table name, so on new-table installs (legacy empty/absent) the count
     * silently returned 0: the notice showed "0 used" and the creation gate
     * never enforced the Lite cap.
     */
    public static function route_count(): int
    {
        global $wpdb;
        $table = self::resolve_routes_table();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Bounded COUNT on the resolved routes table for admin Lite-limit checks.
        return (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i', $table));
    }

    private static function build_cache_key( array $args ): string
    {
        ksort( $args );
        unset( $args['force_refresh'] );
        $args['_hidden_routes'] = \MHMRentiva\Admin\Licensing\LiteOverflow\OverflowRegistry::get( 'route' );
        return self::CACHE_PREFIX . substr( md5( (string) wp_json_encode( $args ) ), 0, 12 );
    }

    private static function register_cache_key(string $key): void
    {
        $keys = get_option(self::CACHE_GROUP_OPTION, []);
        if (! is_array($keys)) {
            $keys = [];
        }
        if (! in_array($key, $keys, true)) {
            $keys[] = $key;
            update_option(self::CACHE_GROUP_OPTION, $keys, false);
        }
    }

    private static function sanitize_order(string $order): string
    {
        $allowed = [ 'featured', 'price_asc', 'price_desc', 'alphabetical', 'newest' ];
        return in_array($order, $allowed, true) ? $order : 'featured';
    }

    private static function build_order_sql(string $order): string
    {
        switch ($order) {
            case 'price_asc':
                return 'r.min_price ASC, r.id DESC';
            case 'price_desc':
                return 'r.min_price DESC, r.id DESC';
            case 'alphabetical':
                return 'o.name ASC, d.name ASC';
            case 'newest':
                return 'r.created_at DESC, r.id DESC';
            case 'featured':
            default:
                return 'r.is_featured DESC, r.created_at DESC, r.id DESC';
        }
    }

    private static function resolve_routes_table(): string
    {
        global $wpdb;
        $new = $wpdb->prefix . 'rentiva_transfer_routes';

        static $resolved = null;
        if ($resolved !== null) {
            return $resolved;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $exists   = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $new));
        $resolved = ( $exists === $new ) ? $new : $wpdb->prefix . 'mhm_rentiva_transfer_routes';
        return $resolved;
    }

    private static function resolve_locations_table(): string
    {
        global $wpdb;
        $new = $wpdb->prefix . 'rentiva_transfer_locations';

        static $resolved = null;
        if ($resolved !== null) {
            return $resolved;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $exists   = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $new));
        $resolved = ( $exists === $new ) ? $new : $wpdb->prefix . 'mhm_rentiva_transfer_locations';
        return $resolved;
    }
}
