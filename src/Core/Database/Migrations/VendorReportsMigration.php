<?php

declare(strict_types=1);

namespace MHMRentiva\Core\Database\Migrations;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Migration schema for the vendor reports / appeals table.
 *
 * Stores structured complaints, appeals, and "contact admin" messages from
 * vendors. A single shared infrastructure used across five contexts:
 *
 *   - booking         — issue with a customer booking (no-show, damage, etc.)
 *   - vehicle         — appeal a paused/withdrawn vehicle action
 *   - vehicle_action  — withdrawal/pause reason capture (Not 2 augment in v4.35.0)
 *                       triggers `mhm_rentiva_before_apply_penalty` filter and
 *                       suspends penalty until admin resolves the appeal
 *   - penalty         — appeal an already-applied penalty ledger entry
 *   - general         — "contact admin" channel
 *
 * `context_id` is intentionally VARCHAR(64) instead of BIGINT so it can carry:
 *   - integer IDs for booking/vehicle/vehicle_action contexts
 *   - the ledger transaction UUID (CHAR(36)) for penalty context
 *   - empty/null for general context
 *
 * @since 4.35.0
 */
final class VendorReportsMigration {


    /**
     * Create or update the vendor_reports table via dbDelta.
     */
    public static function create_table(): void
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'mhm_rentiva_vendor_reports';

        // Match the LedgerMigration enterprise convention (utf8mb4 + InnoDB).
        $charset_collate = 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';

        $sql = "CREATE TABLE {$table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            vendor_id BIGINT UNSIGNED NOT NULL,
            context_type VARCHAR(20) NOT NULL,
            context_id VARCHAR(64) NULL,
            title VARCHAR(255) NOT NULL,
            description LONGTEXT NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'open',
            admin_note LONGTEXT NULL,
            admin_user_id BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            resolved_at DATETIME NULL,
            PRIMARY KEY  (id),
            KEY vendor_id_idx (vendor_id),
            KEY context_type_idx (context_type),
            KEY context_id_idx (context_id),
            KEY status_idx (status),
            KEY vendor_status_idx (vendor_id, status),
            KEY context_open_idx (context_type, context_id, status),
            KEY created_at_idx (created_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta($sql);
    }
}
