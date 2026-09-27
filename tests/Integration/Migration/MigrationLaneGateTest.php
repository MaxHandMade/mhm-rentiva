<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Migration;

use MHMRentiva\Admin\Core\Utilities\DatabaseMigrator;
use MHMRentiva\Tests\Support\ForgetsMigrationLock;
use WP_UnitTestCase;

/**
 * The Lite migration lane must not run from an anonymous admin-ajax or
 * admin-post request (the contact form posts to admin-ajax.php as nopriv) and
 * must not run twice at once.
 */
final class MigrationLaneGateTest extends WP_UnitTestCase
{
	use ForgetsMigrationLock;

	/** @var string|null */
	private $original_pagenow;

	public function setUp(): void
	{
		parent::setUp();
		$this->original_pagenow = $GLOBALS['pagenow'] ?? null;
		self::forget_migration_lock();
	}

	public function tearDown(): void
	{
		remove_all_filters('wp_doing_ajax');
		delete_option(DatabaseMigrator::LOCK_OPTION);
		set_current_screen('front');
		if (null === $this->original_pagenow) {
			unset($GLOBALS['pagenow']);
		} else {
			$GLOBALS['pagenow'] = $this->original_pagenow;
		}
		parent::tearDown();
		self::forget_migration_lock();
	}

	public function test_context_allows_admin_page_cron_and_cli_but_not_ajax(): void
	{
		$this->assertTrue(DatabaseMigrator::context_allows(true, false, false, false));
		$this->assertFalse(DatabaseMigrator::context_allows(true, true, false, false));
		$this->assertFalse(DatabaseMigrator::context_allows(false, false, false, false));
		$this->assertTrue(DatabaseMigrator::context_allows(false, false, true, false));
		$this->assertTrue(DatabaseMigrator::context_allows(false, false, false, true));
	}

	public function test_context_allows_rejects_admin_post(): void
	{
		$this->assertFalse(DatabaseMigrator::context_allows(true, false, false, false, true));
	}

	public function test_hook_lane_does_not_migrate_during_an_admin_post_request(): void
	{
		update_option('mhmrentiva_db_version', '1.0.0');
		set_current_screen('dashboard');
		$GLOBALS['pagenow'] = 'admin-post.php';

		DatabaseMigrator::run_migrations_from_hook();

		$this->assertSame('1.0.0', get_option('mhmrentiva_db_version'));
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

	/**
	 * A run that outlives LOCK_TIMEOUT can have its lock taken over by the
	 * next request. When the slow run finishes it must not delete the new
	 * owner's row -- that would let a third request migrate concurrently.
	 */
	public function test_release_keeps_a_lock_taken_over_during_the_run(): void
	{
		global $wpdb;
		update_option('mhmrentiva_db_version', '1.0.0');
		$new_owner = (string) ( time() + 1000 );

		$ran = DatabaseMigrator::run_migrations(null, null, static function () use ($wpdb, $new_owner): bool {
			// Simulate the takeover a later request performs on an expired lock.
			$wpdb->query($wpdb->prepare(
				"UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s",
				$new_owner,
				DatabaseMigrator::LOCK_OPTION
			));
			return true;
		});

		$this->assertTrue($ran);
		$held = $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", DatabaseMigrator::LOCK_OPTION));
		$this->assertSame($new_owner, $held, 'the slow run must release only the lock it took');
	}

	public function test_a_held_lock_is_reported_as_lock_busy_not_failure(): void
	{
		update_option('mhmrentiva_db_version', '1.0.0');
		add_option(DatabaseMigrator::LOCK_OPTION, (string) time(), '', false);

		$this->assertFalse(DatabaseMigrator::run_migrations());
		$this->assertTrue(
			DatabaseMigrator::last_run_was_lock_busy(),
			'A held lock is not a migration failure -- activation must not treat it as one.'
		);
	}

	public function test_a_normal_run_is_not_reported_as_lock_busy(): void
	{
		update_option('mhmrentiva_db_version', '1.0.0');

		$this->assertTrue(DatabaseMigrator::run_migrations());
		$this->assertFalse(DatabaseMigrator::last_run_was_lock_busy());
	}

	public function test_the_lock_busy_flag_resets_at_the_top_of_every_run(): void
	{
		// Prime the flag true with a held lock...
		update_option('mhmrentiva_db_version', '1.0.0');
		add_option(DatabaseMigrator::LOCK_OPTION, (string) time(), '', false);
		$this->assertFalse(DatabaseMigrator::run_migrations());
		$this->assertTrue(DatabaseMigrator::last_run_was_lock_busy());

		// ...then release it and run again: a stale `true` from the earlier
		// call must not leak into this unrelated one.
		self::forget_migration_lock();
		$this->assertTrue(DatabaseMigrator::run_migrations());
		$this->assertFalse(DatabaseMigrator::last_run_was_lock_busy());
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

	public function test_the_plugins_loaded_drift_lane_does_not_migrate_during_an_admin_post_request(): void
	{
		update_option('mhmrentiva_plugin_version', '0.0.1');
		update_option('mhmrentiva_db_version', '1.0.0');
		set_current_screen('dashboard');
		$GLOBALS['pagenow'] = 'admin-post.php';

		mhmrentiva_run_version_drift_lane();

		$this->assertSame('1.0.0', get_option('mhmrentiva_db_version'));
		$this->assertSame('0.0.1', get_option('mhmrentiva_plugin_version'), 'the gate returns before the code-version stamp');
	}
}
