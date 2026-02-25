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
 * Auth:    None — public endpoint for uptime monitors and load balancers.
 *
 * Response tiers:
 *
 * PUBLIC (unauthenticated):
 *   HTTP 200: {"status": "ok"}
 *   HTTP 503: {"status": "degraded"}
 *
 * ADMIN (manage_options):
 *   HTTP 200/503: full detail including db, ledger_table, engine, version, timestamp.
 *
 * Tiering rationale: public endpoints that expose DB/version info are a fingerprint
 * risk. Uptime monitors only need the status code + minimal body.
 * Admin users get the full diagnostic payload for debugging.
 *
 * @since 4.21.0 (v1.1: tiered response, InnoDB engine probe)
 */
final class HealthController
{
    private const REST_NAMESPACE = 'mhm-rentiva/v1';
    private const ROUTE          = '/health';

    /**
     * Register REST route.
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
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => array(self::class, 'handle'),
                'permission_callback' => '__return_true', // Public — tiered in callback.
            )
        );
    }

    /**
     * Handle the health check.
     *
     * @return \WP_REST_Response
     */
    public static function handle(): \WP_REST_Response
    {
        global $wpdb;

        // ── Run probes ───────────────────────────────────────────────────────────
        $db_status     = 'ok';
        $table_status  = 'ok';
        $engine_status = 'ok';

        // Probe 1: Basic DB connectivity.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        if ($wpdb->get_var('SELECT 1') !== '1') {
            $db_status = 'error';
        }

        $ledger_table = $wpdb->prefix . 'mhm_rentiva_ledger';

        // Probe 2: Table existence.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $table_result = $wpdb->get_var(
            $wpdb->prepare('SHOW TABLES LIKE %s', $ledger_table)
        );
        if ($table_result !== $ledger_table) {
            $table_status = 'error';
        }

        // Probe 3: InnoDB engine check (transactions rely on this).
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $engine = (string) $wpdb->get_var(
            $wpdb->prepare(
                'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s',
                $ledger_table
            )
        );
        if (strtolower($engine) !== 'innodb') {
            $engine_status = 'error';
        }

        $overall = ($db_status === 'ok' && $table_status === 'ok' && $engine_status === 'ok')
            ? 'ok'
            : 'degraded';

        $http_status = $overall === 'ok' ? 200 : 503;

        // ── Tiered response ──────────────────────────────────────────────────────
        // Public monitors: minimal fingerprint surface — status only.
        if (! current_user_can('manage_options')) {
            return new \WP_REST_Response(
                array('status' => $overall),
                $http_status
            );
        }

        // Admin: full diagnostic payload.
        return new \WP_REST_Response(
            array(
                'status'        => $overall,
                'db'            => $db_status,
                'ledger_table'  => $table_status,
                'engine'        => $engine_status,
                'engine_name'   => $engine !== '' ? strtolower($engine) : 'unknown',
                'version'       => defined('MHM_RENTIVA_VERSION') ? MHM_RENTIVA_VERSION : 'unknown',
                'timestamp'     => gmdate('Y-m-d\TH:i:s\Z'),
            ),
            $http_status
        );
    }
}
