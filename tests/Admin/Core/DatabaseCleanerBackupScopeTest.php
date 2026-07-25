<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Core;

use MHMRentiva\Admin\Core\Utilities\DatabaseCleaner;
use WP_UnitTestCase;

/**
 * WP.org T6 suppression audit, DatabaseCleaner.
 *
 * The three maintenance endpoints (download / restore / delete backup) take a
 * table name straight from $_POST and interpolate it into `SHOW CREATE TABLE`,
 * `SELECT *`, `INSERT INTO` and `DROP TABLE IF EXISTS`. Each carried a
 * phpcs:ignore justified as "table name is validated for existence via SHOW
 * TABLES LIKE" -- which is true and beside the point: every table in the
 * database exists, so the check admitted wp_users just as readily as one of our
 * own backups. delete_backup() additionally accepted anything with "backup"
 * anywhere in the name, which reaches other plugins' tables.
 *
 * The scope that was meant all along is the enumeration the UI itself lists:
 * tables this plugin created, matching {prefix}mhm_%_backup%.
 *
 * @covers \MHMRentiva\Admin\Core\Utilities\DatabaseCleaner::is_managed_backup_table
 * @covers \MHMRentiva\Admin\Core\Utilities\DatabaseCleaner::delete_backup
 * @covers \MHMRentiva\Admin\Core\Utilities\DatabaseCleaner::export_backup_to_sql
 * @covers \MHMRentiva\Admin\Core\Utilities\DatabaseCleaner::restore_backup
 */
final class DatabaseCleanerBackupScopeTest extends WP_UnitTestCase
{
	private string $ours;
	private string $foreign;

	public function setUp(): void
	{
		global $wpdb;
		parent::setUp();

		$this->ours    = $wpdb->prefix . 'mhm_postmeta_backup_20260101_120000';
		$this->foreign = $wpdb->prefix . 'someplugin_backup_data';

		// The test bootstrap filters every query to rewrite CREATE TABLE into
		// CREATE TEMPORARY TABLE, and temporary tables do not appear in SHOW
		// TABLES -- which is exactly what list_backups() and this whole scope
		// check are built on. Drop the filter so these fixtures are real tables.
		remove_filter( 'query', array( $this, '_create_temporary_tables' ) );
		remove_filter( 'query', array( $this, '_drop_temporary_tables' ) );

		foreach ( array( $this->ours, $this->foreign ) as $table ) {
			$wpdb->query( "CREATE TABLE IF NOT EXISTS `{$table}` ( id BIGINT UNSIGNED NOT NULL )" ); // phpcs:ignore WordPress.DB
		}
	}

	public function tearDown(): void
	{
		global $wpdb;

		foreach ( array( $this->ours, $this->foreign ) as $table ) {
			$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" ); // phpcs:ignore WordPress.DB
		}

		add_filter( 'query', array( $this, '_create_temporary_tables' ) );
		add_filter( 'query', array( $this, '_drop_temporary_tables' ) );

		parent::tearDown();
	}

	public function test_our_own_backup_table_is_in_scope(): void
	{
		$this->assertTrue( DatabaseCleaner::is_managed_backup_table( $this->ours ) );
	}

	public function test_a_core_table_is_never_in_scope(): void
	{
		global $wpdb;

		$this->assertFalse( DatabaseCleaner::is_managed_backup_table( $wpdb->users ) );
		$this->assertFalse( DatabaseCleaner::is_managed_backup_table( $wpdb->options ) );
	}

	/**
	 * "backup" appearing somewhere in the name is not ownership.
	 */
	public function test_another_plugins_backup_table_is_not_in_scope(): void
	{
		$this->assertFalse( DatabaseCleaner::is_managed_backup_table( $this->foreign ) );
	}

	public function test_delete_refuses_a_table_we_do_not_own(): void
	{
		global $wpdb;

		$result = DatabaseCleaner::delete_backup( $this->foreign );

		$this->assertFalse( $result['success'] );
		$this->assertNotEmpty(
			$wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $this->foreign ) ),
			'A table outside our backup scope must survive the call.'
		);
	}

	public function test_export_refuses_a_table_we_do_not_own(): void
	{
		$this->assertSame( '', DatabaseCleaner::export_backup_to_sql( $this->foreign ) );

		global $wpdb;
		$this->assertSame( '', DatabaseCleaner::export_backup_to_sql( $wpdb->users ) );
	}

	public function test_restore_refuses_a_table_we_do_not_own(): void
	{
		$result = DatabaseCleaner::restore_backup( $this->foreign );

		$this->assertFalse( $result['success'] );
	}

	public function test_export_still_works_for_our_own_backup(): void
	{
		$this->assertStringContainsString(
			$this->ours,
			DatabaseCleaner::export_backup_to_sql( $this->ours )
		);
	}
}
