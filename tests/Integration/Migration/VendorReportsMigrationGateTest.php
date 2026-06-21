<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Migration;

use MHMRentiva\Admin\Core\Utilities\DatabaseMigrator;
use WP_UnitTestCase;

/**
 * Regression guard for the schema-version gate.
 *
 * The vendor_reports (appeal) table was added to run_migrations() at v4.35.0 but the schema
 * CURRENT_VERSION was not bumped, so any install already at that schema version skipped the
 * whole migration block and never got the table — silently breaking the appeal/waiver flow.
 * Bumping CURRENT_VERSION must let run_migrations heal such installs.
 *
 * @group migration
 */
final class VendorReportsMigrationGateTest extends WP_UnitTestCase {

	public function test_run_migrations_heals_missing_vendor_reports_table(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'mhm_rentiva_vendor_reports';

		// Simulate an install that is "up to date" at the schema version that predates this fix
		// but is missing the table.
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB
		// An install behind the current schema version must get every table the block creates,
		// including vendor_reports (the appeal/waiver table that was silently missing).
		update_option( 'mhm_rentiva_db_version', '1.0.0' );
		wp_cache_delete( 'mhm_rentiva_db_version', 'options' );

		DatabaseMigrator::run_migrations();

		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ); // phpcs:ignore WordPress.DB
		$this->assertSame(
			$table,
			$exists,
			'run_migrations must create the vendor_reports table when the install is behind the schema version.'
		);
	}
}
