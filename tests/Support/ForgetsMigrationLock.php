<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Support;

use MHMRentiva\Admin\Core\Utilities\DatabaseMigrator;

/**
 * Remove DatabaseMigrator::LOCK_OPTION by hand, not just via delete_option().
 *
 * A test that opens the version gate (an old `mhmrentiva_db_version` stamp,
 * then a real DatabaseMigrator::run_migrations() call) runs the real migration
 * body, including the 6.0.0 prefix rename's RENAME TABLE and
 * RetiredIndexes::drop()'s DROP INDEX on core tables -- both DDL, both an
 * implicit COMMIT. That ends WP_UnitTestCase's per-test transaction early:
 * whatever acquired the lock before that point survives it (permanently
 * committed), while a plain delete_option() afterward -- or a ROLLBACK TO
 * SAVEPOINT that the implicit commit already invalidated -- can land in a
 * transaction the suite's own rollback then undoes. A lock stranded that way
 * silently declines run_migrations() for every later test in the whole
 * PHPUnit process, not just this class's own -- not a local flake, a
 * process-wide one. Same fix as the add-on's
 * ProMigrationLockTest::forget_lock() / ProPrefixRenameMigrationTest.
 *
 * Call forget_migration_lock() in setUp() (to clear whatever an earlier test
 * or class may have stranded) AND in tearDown() AFTER parent::tearDown() --
 * calling it before would have this same DELETE undone by the suite's own
 * rollback in the exact scenario this trait exists to defend against.
 */
trait ForgetsMigrationLock
{
	private static function forget_migration_lock(): void
	{
		global $wpdb;

		$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name = %s", DatabaseMigrator::LOCK_OPTION));
		wp_cache_delete(DatabaseMigrator::LOCK_OPTION, 'options');
		wp_cache_delete('notoptions', 'options');
		wp_cache_delete('alloptions', 'options');
	}
}
