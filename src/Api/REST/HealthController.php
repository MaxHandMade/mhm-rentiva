<?php

declare(strict_types=1);

namespace MHMRentiva\Api\REST;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Health check REST endpoint.
 *
 * Route:   GET /mhm-rentiva/v1/health
 * Auth:    None (public — designed for uptime monitors, load balancers, CI pipelines).
 *
 * Response (HTTP 200 — healthy):
 * {
 *   "status":       "ok",
 *   "db":           "ok",
 *   "ledger_table": "ok",
 *   "version":      "4.20.3",
 *   "timestamp":    "2026-02-25T18:00:00Z"
 * }
 *
 * Response (HTTP 503 — degraded):
 * {
 *   "status":       "degraded",
 *   "db":           "error",
 *   "ledger_table": "ok",
 *   ...
 * }
 *
 * Probes:
 *   db           → SELECT 1 connectivity check
 *   ledger_table → SHOW TABLES LIKE confirms table exists
 *
 * Note: Response time is NOT included in the response body intentionally —
 * it would add latency to every health check and is better measured externally.
 *
 * @since 4.21.0
 */
final class HealthController
{
    private const REST_NAMESPACE = 'mhm-rentiva/v1';
    private const ROUTE          = '/health';

    /**
     * Register REST route. Called on rest_api_init.
     */
    public static function register(): void
    {
        add_action('rest_api_init', array(self::class, 'register_route'));
    }

    /**
     * Register the health route.
     */
    public static function register_route(): void
    {
        register_rest_route(
            self::REST_NAMESPACE,
            self::ROUTE,
            array(
                'methods'             => \WP_REST_Server::READABLE, // GET
                'callback'            => array(self::class, 'handle'),
                'permission_callback' => '__return_true', // Public — no auth.
            )
        );
    }

    /**
     * Handle the health check request.
     *
     * @return \WP_REST_Response
     */
    public static function handle(): \WP_REST_Response
    {
        global $wpdb;

        $db_status           = 'ok';
        $ledger_table_status = 'ok';

        // Probe 1: Basic DB connectivity.
        $db_result = $wpdb->get_var('SELECT 1'); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        if ($db_result !== '1') {
            $db_status = 'error';
        }

        // Probe 2: Ledger table existence.
        $ledger_table = $wpdb->prefix . 'mhm_rentiva_ledger';
        $table_result = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare('SHOW TABLES LIKE %s', $ledger_table)
        );
        if ($table_result !== $ledger_table) {
            $ledger_table_status = 'error';
        }

        $overall_status = ($db_status === 'ok' && $ledger_table_status === 'ok') ? 'ok' : 'degraded';
        $http_status    = $overall_status === 'ok' ? 200 : 503;

        return new \WP_REST_Response(
            array(
                'status'       => $overall_status,
                'db'           => $db_status,
                'ledger_table' => $ledger_table_status,
                'version'      => defined('MHM_RENTIVA_VERSION') ? MHM_RENTIVA_VERSION : 'unknown',
                'timestamp'    => gmdate('Y-m-d\TH:i:s\Z'),
            ),
            $http_status
        );
    }
}
