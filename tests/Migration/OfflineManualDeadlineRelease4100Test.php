<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Migration;

use MHMRentiva\Admin\Booking\Helpers\BookingConflicts;
use MHMRentiva\Admin\Core\Utilities\DatabaseMigrator;
use WP_UnitTestCase;

/**
 * Upgrading to 4.10.0 deletes the payment deadline on bookings entered by
 * hand and paid outside the site. Before 6.4.0 each got a 30-minute deadline;
 * AutoCancel no longer sweeps them, but a pending booking past its deadline
 * holds nothing (BookingConflicts::PAYMENT_SQL), so the vehicle could be sold
 * twice. Without the deadline the booking holds its vehicle again. Bookings
 * paid online, and bookings made on the site, keep their deadline.
 *
 * @covers \MHMRentiva\Admin\Core\Utilities\DatabaseMigrator
 */
final class OfflineManualDeadlineRelease4100Test extends WP_UnitTestCase
{
	private int $vehicle_id;

	/** @var callable */
	private $no_transactions;

	public function setUp(): void
	{
		parent::setUp();
		// The 4.10.0 step checks each booking under Locker::withLock(), whose
		// START TRANSACTION would commit PHPUnit's own and leak this test's
		// rows into the next one (same guard as the other Locker tests).
		$this->no_transactions = static function ($query) {
			return preg_match('/^\s*(START TRANSACTION|COMMIT|ROLLBACK)\b/i', (string) $query) ? 'SELECT 1' : $query;
		};
		add_filter('query', $this->no_transactions);
		$this->vehicle_id = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_vehicle', 'post_status' => 'publish' ));
	}

	public function tearDown(): void
	{
		remove_filter('query', $this->no_transactions);
		parent::tearDown();
	}

	private function booking(?string $created_via, string $payment_method, int $start_ts): int
	{
		$booking_id = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_booking', 'post_status' => 'publish' ));
		foreach (array(
			'_mhmrentiva_vehicle_id'       => $this->vehicle_id,
			'_mhmrentiva_status'           => 'pending',
			'_mhmrentiva_payment_status'   => 'pending',
			'_mhmrentiva_payment_method'   => $payment_method,
			'_mhmrentiva_payment_deadline' => gmdate('Y-m-d H:i:s', time() - DAY_IN_SECONDS),
			'_mhmrentiva_start_ts'         => $start_ts,
			'_mhmrentiva_end_ts'           => $start_ts + 2 * DAY_IN_SECONDS,
		) as $key => $value) {
			update_post_meta($booking_id, $key, $value);
		}
		if (null !== $created_via) {
			update_post_meta($booking_id, '_mhmrentiva_created_via', $created_via);
		}

		return $booking_id;
	}

	private function deadline(int $booking_id): string
	{
		wp_cache_delete($booking_id, 'post_meta');

		return (string) get_post_meta($booking_id, '_mhmrentiva_payment_deadline', true);
	}

	public function test_4100_releases_only_the_offline_hand_entered_deadline(): void
	{
		$start          = time() + 10 * DAY_IN_SECONDS;
		$manual_offline = $this->booking('admin_manual', 'offline', $start);
		$manual_online  = $this->booking('admin_manual', 'online', $start + 20 * DAY_IN_SECONDS);
		$from_the_site  = $this->booking('woocommerce_checkout', 'woocommerce', $start + 40 * DAY_IN_SECONDS);
		$this->assertSame(0, (int) BookingConflicts::count_booking_overlaps_locked($this->vehicle_id, $start, $start + DAY_IN_SECONDS), 'Premise: the expired deadline frees the vehicle.');
		update_option('mhmrentiva_db_version', '4.9.0');

		DatabaseMigrator::run_migrations();

		$this->assertSame('4.10.0', get_option('mhmrentiva_db_version'));
		$this->assertSame('', $this->deadline($manual_offline));
		$this->assertNotSame('', $this->deadline($manual_online), 'Paid online: the deadline stays.');
		$this->assertNotSame('', $this->deadline($from_the_site), 'Made on the site: the deadline stays.');
		$this->assertSame(1, (int) BookingConflicts::count_booking_overlaps_locked($this->vehicle_id, $start, $start + DAY_IN_SECONDS), 'The hand-entered booking holds its vehicle again.');
	}

	public function test_4100_does_not_restore_a_hold_someone_else_has_taken_since(): void
	{
		// PR #196 review (P1): while the old deadline ran out the dates were
		// free, and another customer may have booked them. Restoring the hold
		// then would leave two active bookings on one vehicle.
		$start          = time() + 10 * DAY_IN_SECONDS;
		$manual_offline = $this->booking('admin_manual', 'offline', $start);
		$taken_since    = $this->booking('woocommerce_checkout', 'woocommerce', $start);
		update_post_meta($taken_since, '_mhmrentiva_status', 'confirmed');
		update_post_meta($taken_since, '_mhmrentiva_payment_status', 'paid');
		$this->assertSame(1, (int) BookingConflicts::count_booking_overlaps_locked($this->vehicle_id, $start, $start + DAY_IN_SECONDS), 'Premise: only the new booking holds the dates.');
		update_option('mhmrentiva_db_version', '4.9.0');
		$linked  = 0;
		$capture = static function ($meta_id, $object_id, $meta_key, $meta_value) use (&$linked, $manual_offline) {
			if ('_mhmrentiva_log_booking_id' === $meta_key && $manual_offline === (int) $meta_value) {
				++$linked;
			}
		};
		add_action('added_post_meta', $capture, 10, 4);

		DatabaseMigrator::run_migrations();
		remove_action('added_post_meta', $capture, 10);

		$this->assertNotSame('', $this->deadline($manual_offline), 'Left as it was: a human decides.');
		$this->assertSame(1, (int) BookingConflicts::count_booking_overlaps_locked($this->vehicle_id, $start, $start + DAY_IN_SECONDS), 'Never two holds on one vehicle.');
		$this->assertGreaterThan(0, $linked, 'The operator is told, on a log entry linked to the booking.');
	}
}
