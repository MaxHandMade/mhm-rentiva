<?php

declare(strict_types=1);

namespace MHMRentiva\Core\Database\Migrations;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Migration schema for the immutable financial ledger table.
 */
final class LedgerMigration
{
	/**
	 * Create or update the ledger table via dbDelta.
	 */
	public static function create_table(): void
	{
		global $wpdb;

		$table_name = $wpdb->prefix . 'mhm_rentiva_ledger';

		// Enforce strict enterprise standards (Overrides WP default fallback)
		$charset_collate = 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			transaction_uuid CHAR(36) NOT NULL,
			vendor_id BIGINT UNSIGNED NOT NULL,
			booking_id BIGINT UNSIGNED NULL,
			order_id BIGINT UNSIGNED NULL,
			type VARCHAR(30) NOT NULL,
			amount DECIMAL(12,2) NOT NULL,
			gross_amount DECIMAL(12,2) NULL,
			commission_amount DECIMAL(12,2) NULL,
			commission_rate DECIMAL(5,2) NULL,
			currency VARCHAR(10) NOT NULL,
			context VARCHAR(30) NOT NULL,
			status VARCHAR(30) NOT NULL,
			created_at DATETIME NOT NULL,
			policy_id BIGINT UNSIGNED NULL DEFAULT NULL,
			policy_version_hash CHAR(64) NULL DEFAULT NULL,
			UNIQUE KEY transaction_uuid_unique (transaction_uuid),
			KEY vendor_id_idx (vendor_id),
			KEY booking_id_idx (booking_id),
			KEY order_id_idx (order_id),
			KEY status_idx (status),
			KEY vendor_created_idx (vendor_id, created_at),
			KEY vendor_status_idx (vendor_id, status, created_at),
			KEY vendor_type_idx (vendor_id, type),
			KEY policy_id_idx (policy_id)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta($sql);
	}
}
