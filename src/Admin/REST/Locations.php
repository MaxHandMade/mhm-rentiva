<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\REST;

use MHMRentiva\Admin\REST\Helpers\AuthHelper;
use MHMRentiva\Admin\Core\Utilities\RateLimiter;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * LOCATIONS API ENDPOINT
 * 
 * Provides filtered location list based on service type.
 */
final class Locations
{
    /**
     * Register REST Route
     */
    public static function register(): void
    {
        register_rest_route(
            'mhm-rentiva/v1',
            '/locations',
            array(
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => array(self::class, 'get_locations'),
                'permission_callback' => array(self::class, 'permission_check'),
                'args'                => array(
                    'service_type' => array(
                        'required'          => false,
                        'sanitize_callback' => 'sanitize_text_field',
                        'default'           => 'both',
                        'enum'              => array('rental', 'transfer', 'both'),
                    ),
                ),
            )
        );
    }

    /**
     * Permission check with rate limiting and Nonce verification
     */
    public static function permission_check(\WP_REST_Request $request): bool
    {
        // 1. Rate Limiting
        $client_ip = RateLimiter::getClientIP();
        if (! RateLimiter::check($client_ip, 'general')) {
            return false;
        }

        // 2. Nonce Verification (via Header)
        $nonce = $request->get_header('X-WP-Nonce');
        if ($nonce && wp_verify_nonce($nonce, 'wp_rest')) {
            return true;
        }

        // 3. Fallback for logged-in users with valid nonces (default WP behavior)
        if (is_user_logged_in() && current_user_can('manage_options')) {
            return true;
        }

        return false;
    }

    /**
     * Get Filtered Locations
     */
    public static function get_locations(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;
        $service_type = $request->get_param('service_type');

        $table_name = $wpdb->prefix . 'rentiva_transfer_locations';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            $table_name = $wpdb->prefix . 'mhm_rentiva_transfer_locations';
        }

        $query = "SELECT id, name, type FROM $table_name WHERE is_active = 1";

        if ($service_type === 'rental') {
            $query .= " AND allow_rental = 1";
        } elseif ($service_type === 'transfer') {
            $query .= " AND allow_transfer = 1";
        }

        $query .= " ORDER BY priority ASC, name ASC";

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
        $results = $wpdb->get_results($query);

        return rest_ensure_response($results);
    }
}
