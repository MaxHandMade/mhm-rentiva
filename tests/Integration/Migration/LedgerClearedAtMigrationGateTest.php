<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Migration;

use MHMRentiva\Admin\Core\Utilities\DatabaseMigrator;
use WP_UnitTestCase;

/**
 * Regression guard for the schema-version gate.
 *
 * The `cleared_at` column was added to LedgerMigration::create_table() (v4.64.0, 7-day
 * revenue-window fix) but DatabaseMigrator::CURRENT_VERSION was not bumped alongside it — the
 * exact same class of bug already hit vendor_reports at v4.35.0 (see
 * VendorReportsMigrationGateTest). Any real install whose stored mhm_rentiva_db_version had
 * already reached the migrator's CURRENT_VERSION at the time (3.9.0) would skip the whole
 * migration block forever and never get the column, while AnalyticsService's new
 * COALESCE(cleared_at, created_at) queries would then fail with "Unknown column" on every
 * report/dashboard page load. Bumping CURRENT_VERSION must let run_migrations heal such installs.
 *
 * @group migration
 */
final class LedgerClearedAtMigrationGateTest extends WP_UnitTestCase {

	public function test_run_migrations_heals_a_ledger_table_missing_cleared_at(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'mhm_rentiva_ledger';

		// Simulate a real, already-migrated install: the ledger table exists in its
		// pre-cleared_at shape, and the stored schema version is already at the value
		// DatabaseMigrator::CURRENT_VERSION held before this fix (3.9.0) — i.e. an install
		// that is "up to date" by the old gate's standard, but still missing the column.
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB
		$charset_collate = 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';
		$wpdb->query(
			"CREATE TABLE {$table} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				tenant_id BIGINT UNSIGNED NOT NULL DEFAULT 1,
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
				PRIMARY KEY  (id)
			) {$charset_collate};" // phpcs:ignore WordPress.DB
		);

		update_option( 'mhm_rentiva_db_version', '3.9.0' );
		wp_cache_delete( 'mhm_rentiva_db_version', 'options' );

		DatabaseMigrator::run_migrations();

		$columns = $wpdb->get_col( "SHOW COLUMNS FROM {$table}" ); // phpcs:ignore WordPress.DB
		$this->assertContains(
			'cleared_at',
			$columns,
			'run_migrations must add the cleared_at column to an install stuck at the pre-fix schema version (3.9.0).'
		);
	}
}
