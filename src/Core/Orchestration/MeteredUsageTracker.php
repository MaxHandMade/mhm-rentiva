<?php

declare(strict_types=1);

namespace MHMRentiva\Core\Orchestration;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Service for atomic usage tracking (Metering).
 *
 * Captures payout counts, ledger entries, and risk events per tenant/cycle.
 * Ensures consistent billing and quota enforcement data.
 *
 * @since 4.23.0
 */
final class MeteredUsageTracker {


    /**
     * Increments a specific metric for a tenant in the current billing cycle.
     *
     * Uses atomic UPSERT logic to handle cycle resets or initial entries.
     *
     * @param int    $tenant_id
     * @param string $metric_type 'payouts'|'ledger_entries'|'exports'|'risk_events'
     * @param int    $increment   Amount to add (default 1)
     */
    public static function increment(int $tenant_id, string $metric_type, int $increment = 1): void
    {
        global $wpdb;
        $table       = $wpdb->prefix . 'mhm_rentiva_usage_metrics';
        $cycle_start = self::get_current_cycle_start();
        $cycle_end   = self::get_current_cycle_end();
        $now         = current_time('mysql', 1);

        /**
         * Atomic UPSERT for Metering.
         * If record exists for (tenant, metric, cycle), increment.
         * Else, create first entry for the cycle.
         */
        $query = $wpdb->prepare(
            "INSERT INTO {$table} (tenant_id, metric_type, metric_value, cycle_start, cycle_end, updated_at)
             VALUES (%d, %s, %d, %s, %s, %s)
             ON DUPLICATE KEY UPDATE 
             metric_value = metric_value + %d, 
             updated_at = VALUES(updated_at)",
            $tenant_id,
            $metric_type,
            $increment,
            $cycle_start,
            $cycle_end,
            $now,
            $increment
        );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query($query);
    }

    /**
     * Gets current cycle's ending timestamp (Monthly UTC).
     */
    public static function get_current_cycle_end(): string
    {
        return gmdate('Y-m-t 23:59:59');
    }

    /**
     * Gets current cycle's starting timestamp (Monthly UTC).
     */
    public static function get_current_cycle_start(): string
    {
        return gmdate('Y-m-01 00:00:00');
    }

    /**
     * Retrieves current usage value for a metric.
     */
    public static function get_usage(int $tenant_id, string $metric_type): int
    {
        global $wpdb;
        $table       = $wpdb->prefix . 'mhm_rentiva_usage_metrics';
        $cycle_start = self::get_current_cycle_start();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT metric_value FROM {$table} WHERE tenant_id = %d AND metric_type = %s AND cycle_start = %s",
                $tenant_id,
                $metric_type,
                $cycle_start
            )
        );
    }
}
