<?php

/**
 * Transfer Search Results Shortcode
 * 
 * @package MHMRentiva
 */

declare(strict_types=1);

namespace MHMRentiva\Admin\Transfer\Frontend;

use MHMRentiva\Admin\Frontend\Shortcodes\Core\AbstractShortcode;
use MHMRentiva\Admin\Transfer\Engine\TransferSearchEngine;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Transfer Results Shortcode Handler
 *
 * [rentiva_transfer_results]
 */
final class TransferResults extends AbstractShortcode
{

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
        $tag = static::get_shortcode_tag();

        // Get version for cache invalidation
        $version = get_transient('mhm_rv_cache_v_' . $tag);
        if (false === $version) {
            $version = (string) time();
            set_transient('mhm_rv_cache_v_' . $tag, $version, DAY_IN_SECONDS * 30);
        }

        // Include URL query parameters in cache key for unique search results
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $query_params = isset($_GET) ? array_filter($_GET, function ($key) {
            return in_array($key, ['origin_id', 'destination_id', 'date', 'time', 'adults', 'children', 'luggage_big', 'luggage_small'], true);
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

        return 'mhm_shc_' . substr(md5($tag), 0, 8) . '_' . md5(serialize($cache_factors));
    }

    /**
     * Prepares template data
     */
    protected static function prepare_template_data(array $atts): array
    {
        // Read-only frontend filters from querystring; this shortcode does not mutate state.
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        $criteria = array(
            'origin_id'      => isset($_GET['origin_id']) ? absint($_GET['origin_id']) : 0,
            'destination_id' => isset($_GET['destination_id']) ? absint($_GET['destination_id']) : 0,
            'date'           => isset($_GET['date']) ? sanitize_text_field(wp_unslash($_GET['date'])) : '',
            'time'           => isset($_GET['time']) ? sanitize_text_field(wp_unslash($_GET['time'])) : '',
            'adults'         => isset($_GET['adults']) ? absint($_GET['adults']) : 1,
            'children'       => isset($_GET['children']) ? absint($_GET['children']) : 0,
            'luggage_big'    => isset($_GET['luggage_big']) ? absint($_GET['luggage_big']) : 0,
            'luggage_small'  => isset($_GET['luggage_small']) ? absint($_GET['luggage_small']) : 0,
        );
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        $results = array();
        if (! empty($criteria['origin_id']) && ! empty($criteria['destination_id'])) {
            $results = TransferSearchEngine::search($criteria);
        }

        // Initial names for display
        global $wpdb;
        $origin_name      = '';
        $destination_name = '';

        // Use same table name lookup pattern as TransferSearchEngine
        $table_locations = $wpdb->prefix . 'rentiva_transfer_locations';
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_locations'") !== $table_locations) {
            $table_locations = $wpdb->prefix . 'mhm_rentiva_transfer_locations';
        }

        if (! empty($criteria['origin_id'])) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $origin_name = (string) $wpdb->get_var($wpdb->prepare("SELECT name FROM {$table_locations} WHERE id = %d", $criteria['origin_id']));
        }
        if (! empty($criteria['destination_id'])) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $destination_name = (string) $wpdb->get_var($wpdb->prepare("SELECT name FROM {$table_locations} WHERE id = %d", $criteria['destination_id']));
        }

        return array(
            'results'          => $results,
            'criteria'         => $criteria,
            'origin_name'      => $origin_name,
            'destination_name' => $destination_name,
            'atts'             => $atts,
        );
    }

    /**
     * Loads CSS and JS files
     */
    protected static function enqueue_assets(array $atts = []): void
    {
        // Reuse existing transfer assets
        TransferShortcodes::enqueue_assets($atts);
    }
}
