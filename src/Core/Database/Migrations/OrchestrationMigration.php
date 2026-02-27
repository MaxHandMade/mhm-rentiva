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
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            tenant_id bigint(20) unsigned NOT NULL,
            site_id bigint(20) unsigned DEFAULT NULL,
            status varchar(20) NOT NULL DEFAULT 'ACTIVE',
            subscription_plan varchar(50) DEFAULT 'basic',
            quota_payouts_limit int(11) DEFAULT 100,
            quota_ledger_entries_limit int(11) DEFAULT 1000,
            quota_risk_events_limit int(11) DEFAULT 50,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY tenant_id  (tenant_id),
            KEY site_idx  (site_id),
            KEY status_idx  (status)
        ) $charset_collate;";

        // 2. Usage Metrics Table
        $metrics_table = $wpdb->prefix . 'mhm_rentiva_usage_metrics';
        $sql2 = "CREATE TABLE $metrics_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            tenant_id bigint(20) unsigned NOT NULL,
            metric_type enum('payouts', 'ledger_entries', 'risk_events') NOT NULL,
            metric_value int(11) DEFAULT 0,
            cycle_start datetime NOT NULL,
            cycle_end datetime NOT NULL,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY tenant_metric_cycle  (tenant_id, metric_type, cycle_start),
            KEY tenant_metric_idx  (tenant_id, metric_type)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql1);
        dbDelta($sql2);
    }
}
