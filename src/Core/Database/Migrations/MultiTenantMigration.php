<?php
declare(strict_types=1);

namespace MHMRentiva\Core\Database\Migrations;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Migration: Adds `tenant_id` column to all core financial tables
 * for Multi-Tenant Financial Isolation (Sprint 14).
 *
 * Safe to run multiple times (idempotent).
 * Existing rows default to `tenant_id = 1` (the primary/default tenant).
 *
 * @since 4.23.0
 */
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Controlled migration file intentionally performs schema changes.
final class MultiTenantMigration {

    /**
     * Executes the multi-tenant column additions.
     *
     * @since 4.23.0
     * @return bool True on success, false if any alteration fails.
     */
    public static function run(): bool
    {
        global $wpdb;

        $tables = [
            $wpdb->prefix . 'mhm_rentiva_ledger',
            $wpdb->prefix . 'mhm_rentiva_key_registry',
            $wpdb->prefix . 'mhm_rentiva_payout_audit',
        ];

        $success = true;

        foreach ($tables as $table) {
            if (! self::column_exists($wpdb, $table, 'tenant_id')) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange -- Migration intentionally alters schema during controlled upgrade.
                $result = $wpdb->query(
                    $wpdb->prepare(
                        'ALTER TABLE %i ADD COLUMN `tenant_id` BIGINT UNSIGNED NOT NULL DEFAULT 1 AFTER `id`',
                        $table
                    )
                );

                if (false === $result) {
                    $success = false;
                    continue;
                }

                // Add index for performance
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange -- Migration intentionally alters schema during controlled upgrade.
                $wpdb->query(
                    $wpdb->prepare(
                        'ALTER TABLE %i ADD INDEX `tenant_id_idx` (`tenant_id`)',
                        $table
                    )
                );
            }

            // ADD COMPOSITE INDEXES (V1.8 HARDENING)
            if ($table === $wpdb->prefix . 'mhm_rentiva_ledger' || $table === $wpdb->prefix . 'mhm_rentiva_payout_audit') {
                $index_name   = 'tenant_created_idx';
                $index_exists = (int) $wpdb->get_var(
                    $wpdb->prepare(
                        'SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_NAME = %s AND INDEX_NAME = %s AND TABLE_SCHEMA = DATABASE()',
                        $table,
                        $index_name
                    )
                );

                if ($index_exists === 0) {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange -- Migration intentionally alters schema during controlled upgrade.
                    $wpdb->query(
                        $wpdb->prepare(
                            'ALTER TABLE %i ADD INDEX `tenant_created_idx` (`tenant_id`, `created_at`)',
                            $table
                        )
                    );
                }
            }
        }

        // For KeyRegistry, enforce "One active key per tenant" UNIQUE constraint.
        self::enforce_key_registry_tenant_constraint($wpdb);

        return $success;
    }

    /**
     * Checks whether a column already exists in a table.
     *
     * @param \wpdb  $wpdb        WordPress DB object.
     * @param string $table       Full table name.
     * @param string $column_name Column name.
     * @return bool
     */
    private static function column_exists(\wpdb $wpdb, string $table, string $column_name): bool
    {
        $result = $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = %s AND COLUMN_NAME = %s AND TABLE_SCHEMA = DATABASE()',
                $table,
                $column_name
            )
        );

        return (int) $result > 0;
    }

    /**
     * Enforces "One Active Key Per Tenant" unique constraint on the key_registry table.
     * Drops legacy global unique index (if it exists) and creates a tenant-scoped one.
     *
     * @param \wpdb $wpdb
     */
    private static function enforce_key_registry_tenant_constraint(\wpdb $wpdb): void
    {
        $table = $wpdb->prefix . 'mhm_rentiva_key_registry';

        // Drop legacy single-tenant "one active key" index if it exists.
        $old_index_exists = $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_NAME = %s AND INDEX_NAME = %s AND TABLE_SCHEMA = DATABASE()',
                $table,
                'active_key_unique'
            )
        );

        if ( (int) $old_index_exists > 0) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange -- Migration intentionally alters schema during controlled upgrade.
            $wpdb->query(
                $wpdb->prepare(
                    'ALTER TABLE %i DROP INDEX `active_key_unique`',
                    $table
                )
            );
        }

        // Create the new per-tenant unique index if it doesn't already exist.
        $new_index_exists = $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_NAME = %s AND INDEX_NAME = %s AND TABLE_SCHEMA = DATABASE()',
                $table,
                'tenant_active_key_unique'
            )
        );

        if ( (int) $new_index_exists === 0) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange -- Migration intentionally alters schema during controlled upgrade.
            $wpdb->query(
                $wpdb->prepare(
                    'ALTER TABLE %i ADD UNIQUE INDEX `tenant_active_key_unique` (`tenant_id`, `active_key`)',
                    $table
                )
            );
        }
    }
}
