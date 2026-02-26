<?php

declare(strict_types=1);

namespace MHMRentiva\Core\Orchestration;

use MHMRentiva\Core\Tenancy\TenantResolver;
use MHMRentiva\Core\Orchestration\Exceptions\QuotaExceededException;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Authoritative Guard for the SaaS Control Plane.
 *
 * Enforces Tenant Existence, Operational Status (Lifecycle), and Quota Availability.
 * This guard stands ABOVE the Financial Kernel (v1.8).
 *
 * @since 4.23.0
 */
final class ControlPlaneGuard {



    /**
     * Asserts that the tenant exists, is operational, and has available quota.
     *
     * Hiyerarşi (Chief Engineer's Triple-Assert Pattern):
     * 1. Existence
     * 2. Operational Status (ACTIVE)
     * 3. Quota Availability
     *
     * @param int    $tenant_id
     * @param string $metric_type 'payouts'|'ledger_entries'|'risk_events'
     * @throws QuotaExceededException If quota is hit.
     * @throws \RuntimeException If tenant is non-existent or non-operational.
     */
    public static function assert_operational_and_quota(int $tenant_id, string $metric_type): void
    {
        $tenant = self::get_tenant_record($tenant_id);

        // 1. Existence & Provisioning Check
        if (! $tenant) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new \RuntimeException('Tenant does not exist in the Control Plane registry.');
        }

        if ($tenant->status === 'PROVISIONING_FAILED') {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new \RuntimeException('Tenant is in a failed provisioning state and is prohibited from operations.');
        }

        // 2. Operational Status (Lifecycle)
        if ($tenant->status !== 'ACTIVE') {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new \RuntimeException(sprintf('Tenant is currently %s. Operations are restricted.', esc_html($tenant->status)));
        }

        // 3. Quota Availability
        self::assert_quota_available($tenant_id, $metric_type, $tenant);
    }

    /**
     * Fetches the authoritative tenant record from the global registry.
     */
    public static function get_tenant_record(int $tenant_id): ?\stdClass
    {
        global $wpdb;
        $table = $wpdb->prefix . 'mhm_rentiva_tenants';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE tenant_id = %d", $tenant_id)
        );
    }

    /**
     * Checks if the tenant is active without throwing.
     */
    public static function is_operational(int $tenant_id): bool
    {
        $tenant = self::get_tenant_record($tenant_id);
        return $tenant && $tenant->status === 'ACTIVE';
    }

    /**
     * Strictly verifies quota before allowing an operation.
     */
    private static function assert_quota_available(int $tenant_id, string $metric_type, \stdClass $tenant): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'mhm_rentiva_usage_metrics';

        // Get current cycle's usage
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
        $usage = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT metric_value FROM {$table} WHERE tenant_id = %d AND metric_type = %s ORDER BY cycle_start DESC LIMIT 1",
                $tenant_id,
                $metric_type
            )
        );

        $limit_field = "quota_{$metric_type}_limit";
        $limit       = isset($tenant->$limit_field) ? (int) $tenant->$limit_field : 0;

        if ($usage >= $limit && $limit > 0) {
            throw new QuotaExceededException($tenant_id, $metric_type);
        }
    }
}
