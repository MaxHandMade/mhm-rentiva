<?php

declare(strict_types=1);

namespace MHMRentiva\Core\Database\Migrations;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Migration for SaaS Orchestration Layer (v1.9).
 */
final class OrchestrationMigration
{
    /**
     * Create SaaS Orchestration tables.
     */
    public static function run(): void
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // 1. Tenants Registry Table
        $tenants_table = $wpdb->prefix . 'mhm_rentiva_tenants';
        $sql1 = "CREATE TABLE $tenants_table (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id BIGINT UNSIGNED NOT NULL UNIQUE,
            site_id BIGINT UNSIGNED DEFAULT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
            subscription_plan VARCHAR(50) DEFAULT 'basic',
            quota_payouts_limit INT DEFAULT 100,
            quota_ledger_entries_limit INT DEFAULT 1000,
            quota_risk_events_limit INT DEFAULT 50,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX site_idx (site_id),
            INDEX status_idx (status)
        ) $charset_collate;";

        // 2. Usage Metrics Table
        $metrics_table = $wpdb->prefix . 'mhm_rentiva_usage_metrics';
        $sql2 = "CREATE TABLE $metrics_table (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id BIGINT UNSIGNED NOT NULL,
            metric_type ENUM('payouts', 'ledger_entries', 'risk_events') NOT NULL,
            metric_value INT DEFAULT 0,
            cycle_start DATETIME NOT NULL,
            cycle_end DATETIME NOT NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY tenant_metric_cycle (tenant_id, metric_type, cycle_start),
            INDEX tenant_metric_idx (tenant_id, metric_type)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql1);
        dbDelta($sql2);
    }
}
