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
 * Auth:    None â€” public endpoint for uptime monitors and load balancers.
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
                'permission_callback' => '__return_true', // Public â€” tiered in callback.
            )
        );
    }

    /**
     * Handle the health check.
     *
     * @return \WP_REST_Response|\WP_Error
     */
    public static function handle(): \WP_REST_Response|\WP_Error
    {
        global $wpdb;

        // 1. Rate Limiting enforcement
        $rate_limit = \MHMRentiva\Admin\Core\Utilities\RateLimiter::middleware('general');
        if (is_wp_error($rate_limit)) {
            return $rate_limit;
        }

        // â”€â”€ Run probes â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        $db_status     = 'ok';
        $table_status  = 'ok';
        $engine_status = 'ok';

        // Probe 1: Basic DB connectivity.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        if ($wpdb->get_var('SELECT 1') !== '1') {
            $db_status = 'error';
        }

        $ledger_table = $wpdb->prefix . 'mhm_rentiva_ledger';
        $audit_table  = $wpdb->prefix . 'mhm_rentiva_payout_audit';

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

        // â”€â”€ Tiered response â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        // Public monitors: minimal fingerprint surface â€” status only.
        if (! current_user_can('manage_options')) {
            return new \WP_REST_Response(
                array('status' => $overall),
                $http_status
            );
        }

        // Admin: full diagnostic payload.
        $integrity_status = \MHMRentiva\Core\Financial\Audit\Verification\IntegrityVerificationService::get_system_status();
        $last_check       = get_option(\MHMRentiva\Core\Financial\Audit\Verification\IntegrityVerificationService::OPTION_LAST_CHECK, []);

        $active_key_payload = \MHMRentiva\Core\Financial\Audit\Crypto\KeyPairManager::get_active_keypair();
        $repo               = new \MHMRentiva\Core\Financial\Audit\Crypto\KeyRegistryRepository();

        // Governance metrics
        $pending_count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$audit_table} WHERE JSON_UNQUOTE(JSON_EXTRACT(metadata_json, '$.workflow_state')) NOT IN ('executed', 'rejected', 'failed')"
            )
        );
        $high_risk_pending = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$audit_table} WHERE JSON_UNQUOTE(JSON_EXTRACT(metadata_json, '$.risk_level')) = 'high' AND JSON_UNQUOTE(JSON_EXTRACT(metadata_json, '$.workflow_state')) NOT IN ('executed', 'rejected', 'failed')"
            )
        );

        return new \WP_REST_Response(
            array(
                'status'                        => $overall,
                'system_integrity_status'       => $integrity_status,
                'ledger_row_count'             => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$ledger_table}"),
                'active_key_id'                => $active_key_payload['key_id'],
                'revoked_key_count'            => $repo->get_revoked_key_count(),
                'last_export_hash'             => isset($last_check['tip_hash']) ? substr($last_check['tip_hash'], 0, 12) . '...' : 'none',
                'last_integrity_check'         => $last_check['timestamp'] ?? 'never',
                'workflow_pending_count'       => $pending_count,
                'high_risk_pending_count'      => $high_risk_pending,
                'db'                            => $db_status,
                'version'                       => defined('MHM_RENTIVA_VERSION') ? MHM_RENTIVA_VERSION : 'unknown',
                'timestamp'                     => gmdate('Y-m-d\TH:i:s\Z'),
            ),
            $http_status
        );
    }
}
