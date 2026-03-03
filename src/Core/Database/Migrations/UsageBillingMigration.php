<?php

declare(strict_types=1);

namespace MHMRentiva\Core\Database\Migrations;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Migration schema for usage-based billing idempotency records (Sprint-19 L2).
 */
final class UsageBillingMigration
{
    public static function create_table(): void
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'mhm_rentiva_usage_billing';
        $charset_collate = 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';

        $sql = "CREATE TABLE {$table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id BIGINT UNSIGNED NOT NULL,
            subscription_id BIGINT UNSIGNED NOT NULL,
            period_start_utc DATETIME NOT NULL,
            period_end_utc DATETIME NOT NULL,
            idempotency_key VARCHAR(191) NOT NULL,
            amount_cents BIGINT NOT NULL,
            computation_hash CHAR(64) NOT NULL,
            ledger_transaction_uuid VARCHAR(64) NULL DEFAULT NULL,
            status VARCHAR(24) NOT NULL DEFAULT 'pending',
            created_at_utc DATETIME NOT NULL,
            updated_at_utc DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY uniq_usage_billing_idempotency (idempotency_key),
            UNIQUE KEY uniq_usage_billing_window (tenant_id, subscription_id, period_start_utc),
            KEY tenant_period_idx (tenant_id, period_start_utc),
            KEY status_idx (status)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
}
