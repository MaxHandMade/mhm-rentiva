<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Helpers;

use MHMRentiva\Admin\Booking\Helpers\Locker;
use WP_UnitTestCase;

/**
 * Fable audit findings M-B and M-C on the 6.0.3 locking work.
 *
 * M-B: withLock()/withBookingLock() caught \Exception only. A \TypeError or any
 * other \Error thrown inside the callback escaped WITHOUT rollback, leaving the
 * transaction open on that connection. Anything that catches \Throwable further
 * up and keeps going (Action Scheduler does exactly this) would then run every
 * subsequent write inside a transaction nobody will ever commit -- silently lost
 * on disconnect, with the row lock held for the whole run.
 *
 * M-C: the lock read targets the vehicle's postmeta rows. If a vehicle has no
 * row matching '_mhm%', the statement matches nothing: under REPEATABLE READ it
 * leaves only a gap lock (and gap locks are compatible with each other), under
 * READ COMMITTED nothing at all. Two transactions could then both proceed. The
 * lock has to land on a row that is guaranteed to exist -- the post row itself.
 *
 * As elsewhere in this suite, transaction control statements are recorded and
 * neutralised so the surrounding WP_UnitTestCase transaction is not implicitly
 * committed by a nested START TRANSACTION.
 */
final class LockerRobustnessTest extends WP_UnitTestCase
{
	/** @var list<string> */
	private array $query_log = array();

	private int $vehicle_id;

	public function setUp(): void
	{
		parent::setUp();

		$this->vehicle_id = (int) self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_vehicle',
			'post_status' => 'publish',
		));

		$this->query_log = array();

		add_filter('query', function ($query) {
			$this->query_log[] = (string) $query;

			if (preg_match('/^\s*(START TRANSACTION|COMMIT|ROLLBACK)\b/i', (string) $query)) {
				return 'SELECT 1';
			}

			return $query;
		});
	}

	private function logged(string $pattern): bool
	{
		foreach ($this->query_log as $sql) {
			if (preg_match($pattern, $sql)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * RED before the fix: \Error is not caught, so ROLLBACK never runs.
	 */
	public function test_error_inside_the_callback_rolls_back(): void
	{
		try {
			Locker::withLock($this->vehicle_id, static function (): void {
				throw new \Error('simulated engine-level failure');
			});
			$this->fail('The Error must propagate to the caller.');
		} catch (\Error $e) {
			$this->assertSame('simulated engine-level failure', $e->getMessage());
		}

		$this->assertTrue(
			$this->logged('/^\s*ROLLBACK\b/i'),
			'An Error thrown inside the critical section must roll the transaction back, not leave it open on the connection.'
		);
		$this->assertFalse(
			$this->logged('/^\s*COMMIT\b/i'),
			'A failed critical section must not commit.'
		);
	}

	/**
	 * Regression: the pre-existing \Exception path must keep rolling back.
	 */
	public function test_exception_inside_the_callback_still_rolls_back(): void
	{
		try {
			Locker::withBookingLock($this->vehicle_id, static function (): void {
				throw new \RuntimeException('halted');
			});
			$this->fail('The exception must propagate to the caller.');
		} catch (\RuntimeException $e) {
			$this->assertSame('halted', $e->getMessage());
		}

		$this->assertTrue($this->logged('/^\s*ROLLBACK\b/i'));
	}

	/**
	 * Negative control: a callback that returns normally must still commit, so
	 * the rollback assertions above cannot be satisfied by never committing.
	 */
	public function test_successful_callback_commits_and_returns_its_value(): void
	{
		$result = Locker::withLock($this->vehicle_id, static fn() => 'done');

		$this->assertSame('done', $result);
		$this->assertTrue($this->logged('/^\s*COMMIT\b/i'));
		$this->assertFalse($this->logged('/^\s*ROLLBACK\b/i'));
	}

	/**
	 * M-C: the lock must land on a row that always exists. A vehicle with no
	 * '_mhm%' postmeta is the case where the postmeta-only lock degrades to a
	 * gap lock, which does not exclude a second transaction.
	 */
	public function test_lock_targets_the_post_row_not_only_postmeta(): void
	{
		global $wpdb;

		// A vehicle with no plugin meta at all -- the degenerate case.
		$bare_vehicle = (int) self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_vehicle',
			'post_status' => 'publish',
		));
		$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->postmeta} WHERE post_id = %d", $bare_vehicle));

		$this->query_log = array();

		Locker::withLock($bare_vehicle, static fn() => true);

		$locks = array_values(array_filter(
			$this->query_log,
			static fn( string $sql ): bool => (bool) preg_match('/FOR UPDATE/i', $sql)
		));

		$this->assertNotEmpty($locks, 'A locking read must be issued.');

		$locks_posts_table = false;
		foreach ($locks as $sql) {
			if (preg_match('/FROM\s+\S*posts\b/i', $sql) && str_contains($sql, (string) $bare_vehicle)) {
				$locks_posts_table = true;
			}
		}

		$this->assertTrue(
			$locks_posts_table,
			'The lock must include the post row itself; a postmeta-only lock on a vehicle with no matching meta rows locks nothing and lets a second transaction through.'
		);
	}
}
