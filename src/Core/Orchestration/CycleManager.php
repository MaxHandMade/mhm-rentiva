<?php

declare(strict_types=1);

namespace MHMRentiva\Core\Orchestration;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Manages Metering Cycles for SaaS tenants.
 *
 * Handles atomic UTC-based cycle resets every month.
 *
 * @since 4.23.0
 */
final class CycleManager {


    /**
     * Proactively ensures the current month's cycle records exist for a tenant.
     *
     * This is called during provisioning or manually triggered.
     */
    public static function ensure_cycle(int $tenant_id): void
    {
        $metrics = [ 'payouts', 'ledger_entries', 'risk_events' ];
        foreach ($metrics as $metric) {
            MeteredUsageTracker::increment($tenant_id, $metric, 0); // UPSERT with 0
        }
    }

    /**
     * Master Reset: Runs via cron to "close" the previous cycle.
     *
     * Hiyerarşi:
     * 1. Detect old cycles.
     * 2. Perform atomic reset by inserting new cycle records.
     *
     * @param int $batch_size Limits performance impact on large tenant bases.
     */
    public static function perform_monthly_reset(int $batch_size = 50): void
    {
        global $wpdb;
        $table      = $wpdb->prefix . 'mhm_rentiva_usage_metrics';
        $new_cycle  = MeteredUsageTracker::get_current_cycle_start();
        $last_month = gmdate('Y-m-d H:i:s', strtotime('first day of last month midnight'));

        // Identify tenants needing a new cycle record
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
        $tenants_to_reset = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT tenant_id FROM {$table} 
                 WHERE cycle_start <= %s 
                 AND tenant_id NOT IN (SELECT tenant_id FROM {$table} WHERE cycle_start = %s)
                 LIMIT %d",
                $last_month,
                $new_cycle,
                $batch_size
            )
        );

        foreach ($tenants_to_reset as $tenant_id) {
            self::ensure_cycle( (int) $tenant_id);
        }
    }
}
