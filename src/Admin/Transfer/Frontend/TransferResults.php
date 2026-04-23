<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Transfer\Frontend;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Transfer Search Results Shortcode
 *
 * @package MHMRentiva
 */


// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query,WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value,WordPress.DB.SlowDBQuery.slow_db_query_tax_query,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Transfer result rendering uses bounded search/filter queries tied to user input constraints.



use MHMRentiva\Admin\Frontend\Shortcodes\Core\AbstractShortcode;
use MHMRentiva\Admin\Transfer\Engine\TransferSearchEngine;



/**
 * Transfer Results Shortcode Handler
 *
 * [rentiva_transfer_results]
 */
final class TransferResults extends AbstractShortcode {


    /**
     * Returns shortcode tag
     */
    protected static function get_shortcode_tag(): string
    {
        return 'rentiva_transfer_results';
    }

    /**
     * Returns template file path
     */
    protected static function get_template_path(): string
    {
        return 'shortcodes/transfer-results';
    }

    /**
     * Returns default attributes
     */
    protected static function get_default_attributes(): array
    {
        return array(
            'layout'               => 'list',
            'columns'              => '2',
            'orderby'              => 'price',
            'order'                => 'asc',
            'limit'                => '10',
            'show_passenger_count' => '1',
            'show_luggage_info'    => '1',
            'show_price'           => '1',
            'show_booking_button'  => '1',
            'show_vehicle_details' => '1',
            'show_route_info'      => '1',
            'class'                => '',
            'show_favorite_button' => '1',
            'show_compare_button'  => '1',
        );
    }

    /**
     * Override cache key to include URL query parameters.
     * Transfer results vary by search criteria, so each unique search needs its own cache.
     *
     * @param array $atts Shortcode attributes.
     * @return string Cache key including URL parameters.
     */
    protected static function get_cache_key(array $atts): string
    {
        $tag = self::get_shortcode_tag();

        // Get version for cache invalidation
        $version = get_transient('mhm_rv_cache_v_' . $tag);
        if (false === $version) {
            $version = (string) time();
            set_transient('mhm_rv_cache_v_' . $tag, $version, DAY_IN_SECONDS * 30);
        }

        // Include URL query parameters in cache key for unique search results
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $query_params = isset($_GET) ? array_filter( (array) $_GET, function ($key) {
            return in_array( (string) $key, [ 'origin_id', 'destination_id', 'date', 'time', 'adults', 'children', 'luggage_big', 'luggage_small', 'layout' ], true);
        }, ARRAY_FILTER_USE_KEY) : [];

        $cache_factors = array(
            'atts'         => $atts,
            'page_id'      => get_the_ID(),
            'user_id'      => get_current_user_id(),
            'is_admin'     => is_admin(),
            'language'     => get_locale(),
            'query_params' => $query_params, // This is the key addition!
            'v'            => $version,
        );

        return 'mhm_shc_' . substr(md5($tag), 0, 8) . '_' . md5( (string) wp_json_encode($cache_factors));
    }

    /**
     * Prepares template data
     */
    protected static function prepare_template_data(array $atts): array
    {
        // Read-only frontend filters from querystring; this shortcode does not mutate state.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $request  = wp_unslash(isset($_GET) ? (array) $_GET : []);
        $criteria = array(
            'origin_id'      => isset($request['origin_id']) ? absint( (string) $request['origin_id']) : absint($atts['origin_id'] ?? 0),
            'destination_id' => isset($request['destination_id']) ? absint( (string) $request['destination_id']) : absint($atts['destination_id'] ?? 0),
            'date'           => isset($request['date']) ? sanitize_text_field( (string) $request['date']) : sanitize_text_field($atts['date'] ?? ''),
            'time'           => isset($request['time']) ? sanitize_text_field( (string) $request['time']) : sanitize_text_field($atts['time'] ?? ''),
            'adults'         => isset($request['adults']) ? absint( (string) $request['adults']) : absint($atts['adults'] ?? 1),
            'children'       => isset($request['children']) ? absint( (string) $request['children']) : absint($atts['children'] ?? 0),
            'luggage_big'    => isset($request['luggage_big']) ? absint( (string) $request['luggage_big']) : absint($atts['luggage_big'] ?? 0),
            'luggage_small'  => isset($request['luggage_small']) ? absint( (string) $request['luggage_small']) : absint($atts['luggage_small'] ?? 0),
        );

        $results = array();
        if (! empty($criteria['origin_id']) && ! empty($criteria['destination_id'])) {
            $results = TransferSearchEngine::search($criteria);

            // [Step 5 & 8] Server-side Sorting & Limiting (v4.21.x)
            $orderby = $atts['orderby'] ?? 'price';
            $order   = strtoupper($atts['order'] ?? 'ASC');

            if ('price' === $orderby) {
                usort($results, function ($a, $b) use ($order) {
                    $val_a = (float) ( $a['price'] ?? 0 );
                    $val_b = (float) ( $b['price'] ?? 0 );
                    if ($val_a === $val_b) {
                        return 0;
                    }
                    $cmp = ( $val_a < $val_b ) ? -1 : 1;
                    return ( 'ASC' === $order ) ? $cmp : -$cmp;
                });
            }

            // Apply Limit
            $limit = isset($atts['limit']) ? absint($atts['limit']) : 10;
            if ($limit > 0) {
                $results = array_slice($results, 0, $limit);
            }
        }

        // Initial names for display
        global $wpdb;
        $origin_name      = '';
        $destination_name = '';

        // Use same table name lookup pattern as TransferSearchEngine.
        $table_locations = $wpdb->prefix . 'rentiva_transfer_locations';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_locations)) !== $table_locations) {
            $table_locations = $wpdb->prefix . 'mhm_rentiva_transfer_locations';
        }
        $table_locations = preg_replace('/[^A-Za-z0-9_]/', '', $table_locations) ?? '';

        if (! empty($criteria['origin_id'])) {
            $origin_name = (string) $wpdb->get_var(
                $wpdb->prepare('SELECT name FROM %i WHERE id = %d', $table_locations, $criteria['origin_id'])
            );
        }
        if (! empty($criteria['destination_id'])) {
            $destination_name = (string) $wpdb->get_var(
                $wpdb->prepare('SELECT name FROM %i WHERE id = %d', $table_locations, $criteria['destination_id'])
            );
        }

        // Result context for template
        $layout  = $atts['layout'] ?? 'grid';
        $columns = isset($atts['columns']) ? absint($atts['columns']) : 2;

        // Allow URL override only if NOT coming from a canonical block instance
        if (empty($atts['_canonical'])) {
            if (isset($request['layout'])) {
                $layout = sanitize_text_field( (string) $request['layout']);
            }
            if (isset($request['columns'])) {
                $columns = absint($request['columns']);
            }
        }

        $final_data = array(
            'results'          => $results,
            'criteria'         => $criteria,
            'origin_name'      => $origin_name,
            'destination_name' => $destination_name,
            'atts'             => $atts,
            'layout'           => $layout,
            'columns'          => $columns,
        );

        return $final_data;
    }

    /**
     * Loads CSS and JS files
     */
    protected static function enqueue_assets(array $atts = []): void
    {
        // Reuse existing transfer assets
        TransferShortcodes::enqueue_assets($atts);

        // Premium UI Parity Asset (v4.20.x)
        wp_enqueue_style(
            'mhm-rentiva-transfer-results-premium',
            MHM_RENTIVA_PLUGIN_URL . 'assets/css/frontend/transfer-results-premium.css',
            [ 'mhm-rentiva-transfer-results-css', 'mhm-rentiva-google-fonts' ],
            MHM_RENTIVA_VERSION . '.' . time() // Cache busting for dev
        );
    }
}
