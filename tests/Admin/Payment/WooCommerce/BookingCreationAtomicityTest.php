<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Payment\WooCommerce;

use MHMRentiva\Admin\Booking\Helpers\Util;
use MHMRentiva\Admin\Payment\WooCommerce\WooCommerceBridge;
use ReflectionMethod;
use WP_UnitTestCase;

/**
 * Finding C-01 (independent review of the 6.0.2 package): the double-booking
 * guard on the live checkout path never held a lock.
 *
 * create_booking_from_data() calls Util::has_overlap_locked() and then
 * wp_insert_post(), but neither call ran inside a transaction. has_overlap_locked()
 * issues `SELECT ... FOR UPDATE` without opening one, and under autocommit MySQL
 * releases a FOR UPDATE row lock the moment the statement finishes. The lock was
 * therefore gone before the COUNT(*) conflict query even ran, and long gone before
 * the INSERT. Two concurrent checkouts could both read "free" and both insert.
 *
 * The plugin already shipped the correct primitive -- Locker::withLock() opens a
 * transaction, takes FOR UPDATE on the vehicle's rows, runs a callback and COMMITs
 * -- but nothing on a live path called it. Its only caller was
 * Util::check_availability_locked(), which itself had no callers anywhere in
 * either edition. The entire locking layer was dead code; that unused entry point
 * was removed in 6.0.3 and the live path locks for itself.
 *
 * WHAT THIS TEST CAN AND CANNOT PROVE
 *
 * It proves the wiring: that the conflict check and the INSERT are issued inside a
 * transaction, in that order, and that the row-lock pattern covers the meta keys it
 * has to cover. It does NOT prove mutual exclusion under real concurrency -- that
 * needs two simultaneous connections, which PHPUnit's single-connection harness
 * cannot stage. Read this as a regression fence around the wiring, not as proof
 * that double-booking is impossible.
 *
 * The transaction statements are neutralised into `SELECT 1` by the query filter:
 * WP_UnitTestCase wraps every test in its own transaction, and a nested
 * START TRANSACTION would implicitly COMMIT it and leak fixtures across tests.
 * The filter records the statement first, so ordering is still observed exactly.
 */
final class BookingCreationAtomicityTest extends WP_UnitTestCase
{
	private int $vehicle_id;

	/** @var list<string> */
	private array $query_log = array();

	public function setUp(): void
	{
		parent::setUp();

		$this->vehicle_id = (int) self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_vehicle',
			'post_status' => 'publish',
			'post_title'  => 'Atomicity Test Vehicle',
		));
		update_post_meta($this->vehicle_id, '_mhmrentiva_price_per_day', 100);

		$this->query_log = array();
	}

	/**
	 * Records every statement and neutralises transaction control so the
	 * surrounding WP_UnitTestCase transaction is not implicitly committed.
	 */
	private function start_recording(): void
	{
		add_filter('query', function ($query) {
			$this->query_log[] = (string) $query;

			if (preg_match('/^\s*(START TRANSACTION|COMMIT|ROLLBACK)\b/i', (string) $query)) {
				return 'SELECT 1';
			}

			return $query;
		});
	}

	/**
	 * Index of the first recorded statement matching $pattern, or -1.
	 */
	private function index_of(string $pattern): int
	{
		foreach ($this->query_log as $i => $sql) {
			if (preg_match($pattern, $sql)) {
				return $i;
			}
		}

		return -1;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function valid_booking_data(): array
	{
		return array(
			'vehicle_id'            => $this->vehicle_id,
			'pickup_date'           => gmdate('Y-m-d', strtotime('+1 day')),
			'pickup_time'           => '10:00',
			'dropoff_date'          => gmdate('Y-m-d', strtotime('+3 days')),
			'dropoff_time'          => '10:00',
			'guests'                => 2,
			'customer_user_id'      => 0,
			'customer_name'         => 'Jane Customer',
			'customer_first_name'   => 'Jane',
			'customer_last_name'    => 'Customer',
			'customer_email'        => 'jane@example.com',
			'customer_phone'        => '5551234567',
			'payment_type'          => 'deposit',
			'payment_method'        => 'woocommerce',
			'payment_gateway'       => '',
			'deposit_amount'        => 50,
			'remaining_amount'      => 150,
			'deposit_type'          => 'percentage',
			'payment_display'       => 'Deposit',
			'total_price'           => 200,
			'rental_days'           => 2,
			'selected_addons'       => array(),
			'cancellation_policy'   => 'flexible',
			'cancellation_deadline' => gmdate('Y-m-d H:i:s', strtotime('+24 hours')),
			'payment_deadline'      => gmdate('Y-m-d H:i:s', strtotime('+48 hours')),
		);
	}

	private function invoke_create_booking_from_data(array $booking_data, int $order_id): ?int
	{
		$method = new ReflectionMethod(WooCommerceBridge::class, 'create_booking_from_data');
		$method->setAccessible(true);

		return $method->invoke(null, $booking_data, $order_id);
	}

	/**
	 * RED before the fix: no START TRANSACTION is ever issued, so the index is -1
	 * and the very first assertion fails.
	 *
	 * GREEN after the fix: the conflict check and the INSERT both sit between
	 * START TRANSACTION and COMMIT, in that order.
	 */
	public function test_conflict_check_and_insert_run_inside_one_transaction(): void
	{
		$this->start_recording();

		$booking_id = $this->invoke_create_booking_from_data($this->valid_booking_data(), 999);

		$this->assertNotNull($booking_id, 'Sanity check: the happy path must still create a booking.');

		$begin  = $this->index_of('/^\s*START TRANSACTION\b/i');
		$lock   = $this->index_of('/FOR UPDATE/i');
		$insert = $this->index_of('/INSERT INTO\s+\S*posts\b/i');
		$commit = $this->index_of('/^\s*COMMIT\b/i');

		$this->assertGreaterThan(
			-1,
			$begin,
			'Booking creation must open a transaction; a bare SELECT ... FOR UPDATE under autocommit holds no lock past the statement.'
		);
		$this->assertGreaterThan(-1, $lock, 'The vehicle row lock must be taken.');
		$this->assertGreaterThan(-1, $insert, 'The booking post must be inserted.');
		$this->assertGreaterThan(-1, $commit, 'The transaction must be committed.');

		$this->assertGreaterThan($begin, $lock, 'The row lock must be taken AFTER the transaction opens, or it is released immediately.');
		$this->assertGreaterThan($lock, $insert, 'The booking must be inserted AFTER the conflict check holds its lock.');
		$this->assertGreaterThan($insert, $commit, 'The transaction must stay open until the booking row exists.');
	}

	/**
	 * The lock pattern has to cover both the pre- and post-6.0.0 meta key
	 * spellings. Locker::withLock() already documents why at length: on a site
	 * running 6.x code before the rename migration has run, every row still
	 * carries the pre-6.0.0 prefix, so a post-6.0.0-only pattern matches ZERO
	 * rows, FOR UPDATE locks nothing, and the caller proceeds believing it holds
	 * the lock. (Described in prose rather than by example on purpose: a literal
	 * old-prefix string here would be rewritten by bin/prefix-rename.php, which
	 * would both dirty that gate and destroy the sentence's meaning.)
	 *
	 * RED before the fix: has_overlap_locked() escaped '_mhmrentiva_', so the
	 * narrow pattern is present and the broad one is absent -- exactly the trap
	 * Locker's own comment warns about, in the function Locker was meant to guard.
	 */
	public function test_overlap_lock_pattern_covers_both_meta_key_spellings(): void
	{
		global $wpdb;

		$this->start_recording();

		Util::has_overlap_locked($this->vehicle_id, strtotime('+1 day'), strtotime('+3 days'));

		$for_update = null;
		foreach ($this->query_log as $sql) {
			if (preg_match('/FOR UPDATE/i', $sql)) {
				$for_update = $sql;
				break;
			}
		}

		$this->assertNotNull($for_update, 'has_overlap_locked() must issue a locking read.');

		$broad  = $wpdb->esc_like('_mhm') . '%';
		$narrow = $wpdb->esc_like('_mhmrentiva_') . '%';

		$this->assertStringContainsString(
			$broad,
			$for_update,
			'The lock pattern must cover both meta key families; a new-prefix-only pattern locks nothing on an un-migrated site.'
		);
		$this->assertStringNotContainsString(
			$narrow,
			$for_update,
			'The narrow post-6.0.0 pattern must not be what the lock relies on.'
		);
	}

	/**
	 * A second booking for the same vehicle and overlapping dates must be
	 * refused. This is the invariant the whole locking layer exists to protect,
	 * asserted here sequentially (one connection) so a regression that removes
	 * the conflict check entirely still turns this red.
	 */
	public function test_overlapping_second_booking_is_refused(): void
	{
		$first = $this->invoke_create_booking_from_data($this->valid_booking_data(), 999);
		$this->assertNotNull($first, 'The first booking must succeed.');

		$second = $this->invoke_create_booking_from_data($this->valid_booking_data(), 1000);

		$this->assertNull($second, 'An overlapping booking on the same vehicle must be refused.');
	}

	/**
	 * Negative control for the test above: a non-overlapping range on the same
	 * vehicle must still be accepted, so the guard cannot be satisfied by simply
	 * refusing everything.
	 */
	public function test_non_overlapping_second_booking_is_accepted(): void
	{
		$first = $this->invoke_create_booking_from_data($this->valid_booking_data(), 999);
		$this->assertNotNull($first, 'The first booking must succeed.');

		$later                 = $this->valid_booking_data();
		$later['pickup_date']  = gmdate('Y-m-d', strtotime('+40 days'));
		$later['dropoff_date'] = gmdate('Y-m-d', strtotime('+42 days'));

		$second = $this->invoke_create_booking_from_data($later, 1000);

		$this->assertNotNull($second, 'A non-overlapping booking on the same vehicle must still be accepted.');
	}
}
