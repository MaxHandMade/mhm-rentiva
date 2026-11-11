<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Helpers;

use MHMRentiva\Admin\Booking\Helpers\Locker;
use WP_UnitTestCase;

/**
 * Locker::withBookingLock() is a lock only when it holds one (PR-2 A5).
 *
 * Until PR-2 it checked none of its statements: a START TRANSACTION or a
 * FOR UPDATE that failed, or a booking row that was not there, still ran the
 * callback -- unlocked, believing it was locked. And neither lock method knew
 * about the other: MySQL has no nested transactions, so opening one inside the
 * other commits the outer one and drops its locks without an error.
 *
 * Transaction control statements are neutralised as elsewhere in this suite,
 * so the surrounding WP_UnitTestCase transaction is not committed.
 *
 * Plan: docs/superpowers/plans/2026-11-11-pr2-kalan-odeme-guvenligi.md (r4 A5, r5).
 *
 * @covers \MHMRentiva\Admin\Booking\Helpers\Locker::withBookingLock
 * @covers \MHMRentiva\Admin\Booking\Helpers\Locker::withLock
 */
final class LockerBookingLockTest extends WP_UnitTestCase
{
	/** @var callable */
	private $no_transactions;

	private int $booking_id;

	public function setUp(): void
	{
		parent::setUp();

		$this->no_transactions = static function ($query) {
			return preg_match('/^\s*(START TRANSACTION|COMMIT|ROLLBACK)\b/i', (string) $query) ? 'SELECT 1' : $query;
		};
		add_filter('query', $this->no_transactions);

		$this->booking_id = (int) self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_booking',
			'post_status' => 'publish',
		));
	}

	public function tearDown(): void
	{
		remove_filter('query', $this->no_transactions);
		parent::tearDown();
	}

	public function test_a_held_lock_runs_the_callback_and_returns_its_value(): void
	{
		$this->assertSame('ran', Locker::withBookingLock($this->booking_id, static fn() => 'ran'));
	}

	public function test_a_failed_lock_read_does_not_run_the_callback(): void
	{
		$ran    = false;
		$break  = static function ($query) {
			return preg_match('/FOR UPDATE/i', (string) $query) ? 'SELECT * FROM mhmrentiva_no_such_table_pr2' : $query;
		};
		add_filter('query', $break);
		$suppress = $GLOBALS['wpdb']->suppress_errors(true);

		try {
			$result = Locker::withBookingLock($this->booking_id, static function () use (&$ran) {
				$ran = true;

				return 'ran';
			});
		} finally {
			$GLOBALS['wpdb']->suppress_errors($suppress);
			remove_filter('query', $break);
		}

		$this->assertNull($result);
		$this->assertFalse($ran, 'a lock that failed is not a lock');
	}

	public function test_a_booking_that_does_not_exist_is_not_locked(): void
	{
		$ran = false;

		$result = Locker::withBookingLock(PHP_INT_MAX, static function () use (&$ran) {
			$ran = true;

			return 'ran';
		});

		$this->assertNull($result);
		$this->assertFalse($ran);
	}

	public function test_a_booking_lock_inside_a_vehicle_lock_is_refused(): void
	{
		$this->setExpectedIncorrectUsage('MHMRentiva\Admin\Booking\Helpers\Locker::may_open');
		$vehicle_id = (int) self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_vehicle',
			'post_status' => 'publish',
		));
		$inner_ran  = false;

		$inner = Locker::withLock($vehicle_id, function () use (&$inner_ran) {
			return Locker::withBookingLock($this->booking_id, static function () use (&$inner_ran) {
				$inner_ran = true;

				return 'inner';
			});
		});

		$this->assertNull($inner);
		$this->assertFalse($inner_ran, 'the nested START TRANSACTION would have committed the vehicle lock');
	}

	public function test_the_lock_can_be_taken_again_after_a_callback_threw(): void
	{
		try {
			Locker::withBookingLock($this->booking_id, static function (): void {
				throw new \RuntimeException('boom');
			});
		} catch (\RuntimeException $e) {
			// expected
		}

		$this->assertSame('again', Locker::withBookingLock($this->booking_id, static fn() => 'again'));
	}
}
