<?php

declare(strict_types=1);

namespace MHMRentiva\Core\Database\Migrations;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Migration schema for tenant-scoped usage billing feature flags (Sprint-19 L3).
 */
final class UsageBillingFeatureFlagMigration
{
    public static function create_table(): void
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'mhm_rentiva_usage_billing_feature_flags';
        $charset_collate = 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';

        $sql = "CREATE TABLE {$table_name} (
            tenant_id BIGINT UNSIGNED NOT NULL,
            is_enabled TINYINT(1) NOT NULL DEFAULT 0,
            updated_at_utc CHAR(20) NOT NULL,
            PRIMARY KEY  (tenant_id)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
}
