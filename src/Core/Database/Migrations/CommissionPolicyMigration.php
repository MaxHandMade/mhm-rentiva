<?php

declare(strict_types=1);

namespace MHMRentiva\Core\Database\Migrations;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Migration schema for the commission policy versioning table.
 *
 * This table stores immutable snapshots of commission agreements at point-in-time.
 * PolicyService::resolve_policy_at() queries effective_from / effective_to ranges
 * to determine which policy was active at any given booking datetime.
 *
 * Design Decisions:
 * - Custom table (not CPT) to enable native SQL BETWEEN range queries without post_meta joins.
 * - version_hash stores SHA-256 of the full policy state fingerprint (vendor_id + global_rate
 *   + effective_from + effective_to) ensuring cryptographic audit-proof uniqueness.
 * - vendor_id = NULL denotes a platform-wide (global) policy applicable to all vendors.
 * - effective_to = NULL denotes an open-ended policy with no scheduled expiry.
 *
 * @since 4.21.0
 */
final class CommissionPolicyMigration
{
    /**
     * Create or update the commission policy table via dbDelta.
     */
    public static function create_table(): void
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'mhm_rentiva_commission_policy';

        // Enforce strict enterprise standards matching Ledger table charset/engine.
        $charset_collate = 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';

        $sql = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			label VARCHAR(200) NOT NULL DEFAULT '',
			global_rate DECIMAL(5,2) NOT NULL,
			vendor_id BIGINT UNSIGNED NULL DEFAULT NULL,
			effective_from DATETIME NOT NULL,
			effective_to DATETIME NULL DEFAULT NULL,
			version_hash CHAR(64) NOT NULL,
			created_by BIGINT UNSIGNED NOT NULL,
			created_at DATETIME NOT NULL,
			UNIQUE KEY version_hash_unique (version_hash),
			KEY vendor_effective_idx (vendor_id, effective_from, effective_to),
			KEY effective_from_idx (effective_from),
			KEY vendor_id_idx (vendor_id),
			PRIMARY KEY  (id)
		) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta($sql);
    }
}
