<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Migration;

use MHMRentiva\Admin\Core\Utilities\DatabaseMigrator;
use WP_UnitTestCase;

/**
 * The Lite migration lane must not run from an anonymous admin-ajax request
 * (the contact form posts there as nopriv) and must not run twice at once.
 */
final class MigrationLaneGateTest extends WP_UnitTestCase
{
	public function setUp(): void
	{
		parent::setUp();
		self::forget_migration_lock();
	}

	public function tearDown(): void
	{
		remove_all_filters('wp_doing_ajax');
		delete_option(DatabaseMigrator::LOCK_OPTION);
		self::forget_migration_lock();
		set_current_screen('front');
		parent::tearDown();
	}

	/**
	 * Remove DatabaseMigrator::LOCK_OPTION by hand, not just via delete_option().
	 *
	 * A test here that opens the version gate runs the real migration body,
	 * including the 6.0.0 prefix rename's RENAME TABLE and
	 * RetiredIndexes::drop()'s DROP INDEX on core tables -- both DDL, both an
	 * implicit COMMIT. That ends WP_UnitTestCase's per-test transaction early:
	 * whatever acquired the lock before that point survives it, while a plain
	 * delete_option() afterward can land in a transaction the suite's own
	 * rollback then undoes, leaving the lock stuck for every later test in the
	 * process. Same fix as the add-on's ProMigrationLockTest::forget_lock().
	 */
	private static function forget_migration_lock(): void
	{
		global $wpdb;

		$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name = %s", DatabaseMigrator::LOCK_OPTION));
		wp_cache_delete(DatabaseMigrator::LOCK_OPTION, 'options');
		wp_cache_delete('notoptions', 'options');
		wp_cache_delete('alloptions', 'options');
	}

	public function test_context_allows_admin_page_cron_and_cli_but_not_ajax(): void
	{
		$this->assertTrue(DatabaseMigrator::context_allows(true, false, false, false));
		$this->assertFalse(DatabaseMigrator::context_allows(true, true, false, false));
		$this->assertFalse(DatabaseMigrator::context_allows(false, false, false, false));
		$this->assertTrue(DatabaseMigrator::context_allows(false, false, true, false));
		$this->assertTrue(DatabaseMigrator::context_allows(false, false, false, true));
	}

	public function test_hook_lane_does_not_migrate_during_an_ajax_request(): void
	{
		// An old stamp, so that without the gate the full migration WOULD run
		// and move it (the suite's own convention: DatabaseMigratorSeamTest).
		update_option('mhmrentiva_db_version', '1.0.0');
		set_current_screen('dashboard');
		add_filter('wp_doing_ajax', '__return_true');

		DatabaseMigrator::run_migrations_from_hook();

		$this->assertSame('1.0.0', get_option('mhmrentiva_db_version'));
	}

	public function test_a_held_lock_makes_a_second_run_back_off(): void
	{
		update_option('mhmrentiva_db_version', '1.0.0');
		add_option(DatabaseMigrator::LOCK_OPTION, (string) time(), '', false);

		$this->assertFalse(DatabaseMigrator::run_migrations());
		$this->assertSame('1.0.0', get_option('mhmrentiva_db_version'));
	}

	public function test_a_current_stamp_takes_no_lock(): void
	{
		update_option('mhmrentiva_db_version', '9.9.9');
		add_option(DatabaseMigrator::LOCK_OPTION, (string) time(), '', false);

		// Fast path: the stamp is read before the lock, so a held lock does not
		// turn an up-to-date request into a `false` -- and it does not touch the
		// lock row (no INSERT/DELETE on the up-to-date path).
		$held = (string) get_option(DatabaseMigrator::LOCK_OPTION);
		$this->assertTrue(DatabaseMigrator::run_migrations());
		wp_cache_delete('alloptions', 'options');
		$this->assertSame($held, (string) get_option(DatabaseMigrator::LOCK_OPTION));
	}

	public function test_an_expired_lock_is_taken_over_and_released(): void
	{
		update_option('mhmrentiva_db_version', '1.0.0');
		add_option(DatabaseMigrator::LOCK_OPTION, (string) ( time() - DatabaseMigrator::LOCK_TIMEOUT - 5 ), '', false);

		$this->assertTrue(DatabaseMigrator::run_migrations());
		wp_cache_delete('notoptions', 'options');
		$this->assertFalse(get_option(DatabaseMigrator::LOCK_OPTION));
	}

	public function test_the_plugins_loaded_drift_lane_does_not_migrate_during_an_ajax_request(): void
	{
		update_option('mhmrentiva_plugin_version', '0.0.1');
		update_option('mhmrentiva_db_version', '1.0.0');
		set_current_screen('dashboard');
		add_filter('wp_doing_ajax', '__return_true');

		mhmrentiva_run_version_drift_lane();

		$this->assertSame('1.0.0', get_option('mhmrentiva_db_version'));
		$this->assertSame('0.0.1', get_option('mhmrentiva_plugin_version'), 'the gate returns before the code-version stamp');
	}
}
