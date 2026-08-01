<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Helpers;

use MHMRentiva\Admin\Booking\Helpers\Util;
use WP_UnitTestCase;

/**
 * Regression test: has_overlap() must include "completed but end_ts in future"
 * bookings as overlapping. This is a defense-in-depth layer against the cron
 * bug (or any future manual status mishap) that marks a booking completed
 * while the vehicle is still physically rented.
 *
 * Before fix: status filter IN ('pending_payment','pending','confirmed','in_progress')
 * silently excluded any completed booking → double-booking risk.
 * After fix: status IN (...) OR (status='completed' AND end_ts > NOW()).
 */
final class HasOverlapStatusFilterTest extends WP_UnitTestCase
{
	private const VEHICLE_ID = 7777;

	private function create_booking(string $status, int $start_ts, int $end_ts): int
	{
		$booking_id = (int) self::factory()->post->create(array(
			'post_type'   => 'vehicle_booking',
			'post_status' => 'publish',
		));

		update_post_meta($booking_id, '_mhmrentiva_status', $status);
		update_post_meta($booking_id, '_mhmrentiva_vehicle_id', self::VEHICLE_ID);
		update_post_meta($booking_id, '_mhmrentiva_pickup_date', wp_date('Y-m-d', $start_ts));
		update_post_meta($booking_id, '_mhmrentiva_pickup_time', wp_date('H:i', $start_ts));
		update_post_meta($booking_id, '_mhmrentiva_dropoff_date', wp_date('Y-m-d', $end_ts));
		update_post_meta($booking_id, '_mhmrentiva_dropoff_time', wp_date('H:i', $end_ts));
		update_post_meta($booking_id, '_mhmrentiva_start_ts', $start_ts);
		update_post_meta($booking_id, '_mhmrentiva_end_ts', $end_ts);

		return $booking_id;
	}

	/**
	 * RED with current code (smoking gun #3810): vehicle has a completed
	 * booking whose end_ts is still in the future. New request inside that
	 * window MUST be detected as overlap (defense-in-depth).
	 */
	public function test_overlap_detected_for_completed_with_future_end_ts(): void
	{
		$now = (int) current_time('timestamp');
		$this->create_booking('completed', $now - 3600, $now + 3600);

		$overlap = Util::has_overlap(self::VEHICLE_ID, $now + 1000, $now + 2000);

		$this->assertTrue(
			$overlap,
			'Completed booking with future end_ts must be flagged as overlap (defense-in-depth).'
		);
	}

	/**
	 * Baseline: completed booking that ended 2h ago must NOT trigger overlap.
	 * 60min buffer is also in the past.
	 */
	public function test_overlap_ignored_for_completed_with_past_end_ts(): void
	{
		$now = (int) current_time('timestamp');
		$this->create_booking('completed', $now - 86400, $now - 7200);

		$overlap = Util::has_overlap(self::VEHICLE_ID, $now, $now + 1800);

		$this->assertFalse(
			$overlap,
			'Genuinely-completed booking (end_ts well in past) must not be flagged as overlap.'
		);
	}

	/**
	 * Regression baseline: confirmed booking covering request window
	 * must be flagged as overlap (existing happy path).
	 */
	public function test_overlap_detected_for_confirmed_overlapping_window(): void
	{
		$now = (int) current_time('timestamp');
		$this->create_booking('confirmed', $now + 1000, $now + 5000);

		$overlap = Util::has_overlap(self::VEHICLE_ID, $now + 2000, $now + 4000);

		$this->assertTrue($overlap, 'Confirmed booking covering request window must be flagged as overlap.');
	}
}
