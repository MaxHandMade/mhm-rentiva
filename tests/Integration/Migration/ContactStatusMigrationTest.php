<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Migration;

use MHMRentiva\Admin\ContactMessages\ContactStatus;
use MHMRentiva\Admin\Core\Utilities\DatabaseMigrator;
use MHMRentiva\Admin\PostTypes\Logs\PostType as LogPostType;
use MHMRentiva\Tests\Support\ForgetsMigrationLock;
use ReflectionMethod;
use WP_UnitTestCase;

final class ContactStatusMigrationTest extends WP_UnitTestCase
{
	use ForgetsMigrationLock;

	public function setUp(): void
	{
		parent::setUp();
		delete_option(DatabaseMigrator::CONTACT_STATUS_DONE_OPTION);
		delete_option(DatabaseMigrator::CONTACT_STATUS_CUTOFF_OPTION);
		self::forget_migration_lock();
	}

	public function tearDown(): void
	{
		parent::tearDown();
		self::forget_migration_lock();
	}

	private function step(): void
	{
		$m = new ReflectionMethod(DatabaseMigrator::class, 'migrate_contact_status_440');
		$m->setAccessible(true);
		$m->invoke(null);
	}

	private function contact(string $date, string $status): int
	{
		$id = (int) self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_contact',
			'post_status' => 'private',
			'post_date'   => $date,
		));
		update_post_meta($id, ContactStatus::META_KEY, $status);
		return $id;
	}

	public function test_existing_new_records_become_read(): void
	{
		$old = $this->contact('2026-08-01 10:00:00', 'new');
		$this->step();
		$this->assertSame('read', get_post_meta($old, ContactStatus::META_KEY, true));
		$this->assertSame('1', get_option(DatabaseMigrator::CONTACT_STATUS_DONE_OPTION));
	}

	public function test_a_booking_with_the_same_meta_key_is_untouched(): void
	{
		$booking = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_booking', 'post_date' => '2026-08-01 10:00:00' ));
		update_post_meta($booking, ContactStatus::META_KEY, 'new');
		$this->step();
		$this->assertSame('new', get_post_meta($booking, ContactStatus::META_KEY, true));
	}

	public function test_records_marked_unread_after_the_step_stay_new_on_a_second_call(): void
	{
		$old = $this->contact('2026-08-01 10:00:00', 'new');
		$this->step();
		update_post_meta($old, ContactStatus::META_KEY, 'new'); // admin pressed "Mark unread"
		$this->step();
		$this->assertSame('new', get_post_meta($old, ContactStatus::META_KEY, true));
	}

	public function test_a_full_rerun_from_an_older_stamp_keeps_unread_marks(): void
	{
		$old = $this->contact('2026-08-01 10:00:00', 'new');
		$this->step();
		update_post_meta($old, ContactStatus::META_KEY, 'new');
		update_option('mhmrentiva_db_version', '4.3.0');
		DatabaseMigrator::run_migrations();
		$this->assertSame('new', get_post_meta($old, ContactStatus::META_KEY, true));
	}

	public function test_the_update_is_visible_through_a_primed_object_cache(): void
	{
		$old = $this->contact('2026-08-01 10:00:00', 'new');
		wp_cache_set($old, array( ContactStatus::META_KEY => array( 'new' ) ), 'post_meta');
		$this->step();
		$this->assertSame('read', get_post_meta($old, ContactStatus::META_KEY, true));
	}

	/**
	 * Codex Important #1: the backfill ignored $wpdb->query()'s return value and
	 * set the done flag regardless. If the database accepts the SELECT but
	 * rejects the UPDATE (permissions, a broken table, a hostile `query`
	 * filter), the old records must stay `new` and the done flag must stay
	 * unset -- not be marked done while nothing actually changed.
	 *
	 * Residual round correction: leaving the done flag unset does NOT mean the
	 * step retries on the next admin_init/plugins_loaded request. run_migrations()
	 * gates this whole locked block on stored_db_version() < CURRENT_VERSION,
	 * and the steps that run after this one in run_migrations_locked() still
	 * stamp mhmrentiva_db_version to CURRENT_VERSION regardless of this step's
	 * own outcome -- so the very next request already reads the version as
	 * current and never calls migrate_contact_status_440() again. The step is
	 * retried on the NEXT migration run (the next CURRENT_VERSION bump), when
	 * its own done flag is checked again and found unset. Because that could be
	 * a long wait, a failed UPDATE is now also logged once via AdvancedLogger.
	 */
	public function test_a_failed_update_does_not_mark_the_step_done_and_leaves_records_new(): void
	{
		global $wpdb;
		$old = $this->contact('2026-08-01 10:00:00', 'new');

		$original_errors = $wpdb->suppress_errors(true);
		$break_update     = static function (string $query): string {
			if (str_starts_with(trim($query), 'UPDATE') && str_contains($query, 'SET pm.meta_value')) {
				return 'SELECT * FROM mhmrentiva_deliberately_missing_table';
			}
			return $query;
		};

		try {
			add_filter('query', $break_update, 9999);
			$this->step();
		} finally {
			remove_filter('query', $break_update, 9999);
			$wpdb->suppress_errors($original_errors);
		}

		$this->assertFalse(get_option(DatabaseMigrator::CONTACT_STATUS_DONE_OPTION), 'a failed UPDATE must not mark the backfill complete');
		$this->assertSame('new', get_post_meta($old, ContactStatus::META_KEY, true), 'the record must not be reported as migrated when the write never landed');

		$logs = get_posts(array(
			'post_type'      => LogPostType::TYPE,
			'posts_per_page' => 1,
			'orderby'        => 'ID',
			'order'          => 'DESC',
		));
		$this->assertNotEmpty($logs, 'a failed backfill UPDATE must be logged once, since it will not be retried until the next version bump');
		$this->assertStringContainsString('backfill', strtolower($logs[0]->post_title));
		$this->assertSame('error', get_post_meta($logs[0]->ID, '_mhmrentiva_log_level', true));
	}
}
