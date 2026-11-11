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

	public function setUp(): void
	{
		parent::setUp();
		$this->vehicle_id = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_vehicle', 'post_status' => 'publish' ));
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
}
