<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Core\Utilities;

use MHMRentiva\Admin\Core\Utilities\DatabaseCleaner;
use WP_UnitTestCase;

/**
 * Backups belong under the uploads directory, not in wp-content/.
 *
 * A plugin writing a directory straight into `WP_CONTENT_DIR` puts files where
 * the site owner has not chosen to allow writes, outside anything a host,
 * backup tool or security plugin treats as plugin-owned, and outside the one
 * location WordPress itself guarantees is writable and configurable
 * (`wp_upload_dir()`, which honours `UPLOADS` and the multisite per-blog path).
 * The WordPress.org preflight rule names this directly.
 *
 * The old location cannot simply be abandoned: an install that has already
 * taken a backup has real files there, and they must stay listable, restorable
 * and deletable. So the write target moves and the containment check accepts
 * both.
 *
 * @covers \MHMRentiva\Admin\Core\Utilities\DatabaseCleaner::backup_dir
 */
final class BackupDirectoryLocationTest extends WP_UnitTestCase
{
	/** @var list<string> */
	private array $made = array();

	public function tearDown(): void
	{
		foreach ( $this->made as $path ) {
			if ( is_file( $path ) ) {
				unlink( $path );
			}
		}
		parent::tearDown();
	}

	private function touch_file( string $dir, string $name ): string
	{
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}

		$path = $dir . '/' . $name;
		file_put_contents( $path, '-- test' );
		$this->made[] = $path;

		return $path;
	}

	public function test_the_backup_directory_lives_under_uploads(): void
	{
		$uploads = wp_upload_dir();

		$this->assertSame(
			$uploads['basedir'] . '/mhm-rentiva-backups',
			DatabaseCleaner::backup_dir(),
			'Backups must be written under the uploads directory.'
		);
	}

	public function test_the_backup_directory_is_not_in_wp_content_root(): void
	{
		$this->assertStringStartsNotWith(
			WP_CONTENT_DIR . '/mhm-rentiva-backups',
			DatabaseCleaner::backup_dir(),
			'The write target is still the wp-content root.'
		);
	}

	/**
	 * A file in the new location is in scope.
	 */
	public function test_a_file_in_the_new_directory_is_contained(): void
	{
		$file = $this->touch_file( DatabaseCleaner::backup_dir(), 'mhm_rentiva_full_backup_new.sql' );

		$this->assertTrue( DatabaseCleaner::is_backup_file( $file ) );
	}

	/**
	 * And so is one left behind by an older version, or the site owner loses
	 * access to backups they already took.
	 */
	public function test_a_file_in_the_legacy_directory_is_still_contained(): void
	{
		$file = $this->touch_file( WP_CONTENT_DIR . '/mhm-rentiva-backups', 'mhm_rentiva_full_backup_old.sql' );

		$this->assertTrue(
			DatabaseCleaner::is_backup_file( $file ),
			'A backup taken before the move must remain restorable and deletable.'
		);
	}

	/**
	 * The point of the check.
	 */
	public function test_a_file_outside_both_directories_is_rejected(): void
	{
		$file = $this->touch_file( WP_CONTENT_DIR, 'mhm_not_a_backup.sql' );

		$this->assertFalse( DatabaseCleaner::is_backup_file( $file ) );
	}

	public function test_a_traversal_out_of_the_backup_directory_is_rejected(): void
	{
		$this->touch_file( WP_CONTENT_DIR, 'mhm_traversal_target.sql' );

		$this->assertFalse(
			DatabaseCleaner::is_backup_file(
				DatabaseCleaner::backup_dir() . '/../../mhm_traversal_target.sql'
			)
		);
	}

	public function test_a_path_that_does_not_resolve_is_rejected(): void
	{
		$this->assertFalse(
			DatabaseCleaner::is_backup_file( DatabaseCleaner::backup_dir() . '/does-not-exist.sql' )
		);
	}
}
