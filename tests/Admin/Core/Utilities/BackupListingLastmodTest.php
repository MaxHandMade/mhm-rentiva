<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Core\Utilities;

use MHMRentiva\Admin\Core\Utilities\DatabaseCleaner;
use WP_UnitTestCase;

/**
 * Listing a backup file that has no database record must not fatal.
 *
 * `WP_Filesystem_Direct::dirlist()` returns TWO modification-time fields, and
 * they are not interchangeable: `lastmodunix` is the Unix timestamp, while
 * `lastmod` is already formatted for display -- `gmdate('M j', ...)`, e.g.
 * "Jul 25". Passing the formatted one to `gmdate()` is a TypeError on PHP 8
 * ("Argument #2 ($timestamp) must be of type ?int, string given"), which takes
 * down the whole backups screen.
 *
 * The path is reachable in normal use: the directory scan exists precisely to
 * surface .sql files that are on disk but absent from `mhm_backup_records` --
 * a backup restored from another site, a file copied in by hand, or a record
 * lost while its file survived. Found by taking a real backup and listing it.
 *
 * @covers \MHMRentiva\Admin\Core\Utilities\DatabaseCleaner::list_full_backups
 */
final class BackupListingLastmodTest extends WP_UnitTestCase
{
	private string $file = '';

	public function tearDown(): void
	{
		if ( '' !== $this->file && is_file( $this->file ) ) {
			unlink( $this->file );
		}
		parent::tearDown();
	}

	public function test_a_backup_file_with_no_db_record_is_listed_without_fataling(): void
	{
		$dir = DatabaseCleaner::backup_dir();
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}

		$this->file = $dir . '/mhm_rentiva_full_backup_20260101_010101.sql';
		file_put_contents( $this->file, "-- test backup\n" );

		$backups = DatabaseCleaner::list_full_backups();

		$names = array_column( $backups, 'backup_name' );
		$this->assertContains(
			'mhm_rentiva_full_backup_20260101_010101',
			$names,
			'A .sql file on disk with no database record should still be listed.'
		);

		$entry = null;
		foreach ( $backups as $backup ) {
			if ( 'mhm_rentiva_full_backup_20260101_010101' === $backup['backup_name'] ) {
				$entry = $backup;
				break;
			}
		}

		$this->assertNotNull( $entry );
		$this->assertMatchesRegularExpression(
			'/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',
			$entry['created_at'],
			'The date came from the display-formatted dirlist field, not the timestamp.'
		);
	}
}
