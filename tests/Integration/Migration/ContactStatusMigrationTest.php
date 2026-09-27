<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Migration;

use MHMRentiva\Admin\ContactMessages\ContactStatus;
use MHMRentiva\Admin\Core\Utilities\DatabaseMigrator;
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
}
