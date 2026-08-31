<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\PostTypes\Maintenance;

use MHMRentiva\Admin\PostTypes\Maintenance\AutoComplete;
use MHMRentiva\Tests\Support\SiteClock;
use WP_UnitTestCase;

/**
 * Regression test: AutoComplete cron must use datetime (not date-only)
 * when deciding which confirmed/in_progress bookings to mark "completed".
 *
 * Bug (2026-05-21, booking #3810): cron's WP_Query meta_query compared
 * _mhmrentiva_dropoff_date < NOW() with type=DATETIME. The date meta has no time
 * portion, so MySQL cast '2026-05-21' to '2026-05-21 00:00:00'. Any
 * confirmed booking returning later TODAY (e.g., 16:00) was auto-completed
 * at gece 00:00 — long before the real return time. has_overlap() then
 * skipped the booking (completed not in its IN-list), allowing the same
 * vehicle to be double-booked for the remaining hours of the day.
 *
 * After fix: cron uses _mhmrentiva_end_ts (UNIX timestamp, primary) with
 * CONCAT(dropoff_date, dropoff_time) fallback when end_ts is missing.
 */
final class AutoCompleteCronDatetimeTest extends WP_UnitTestCase
{
	use SiteClock;

	private const VEHICLE_ID = 3008;

	/**
	 * Create a vehicle_booking with realistic meta. Override fields via $opts.
	 *
	 * @param array<string, mixed> $opts
	 */
	private function create_booking(array $opts = array()): int
	{
		$now      = (int) current_time('timestamp');
		$defaults = array(
			'status'           => 'confirmed',
			'start_ts'         => $now - 86400,
			'end_ts'           => $now + 3600,
			'dropoff_date'     => null,
			'dropoff_time'     => null,
			'set_end_ts'       => true,
			'set_dropoff_time' => true,
		);
		$opts = array_merge($defaults, $opts);

		$booking_id = (int) self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_booking',
			'post_status' => 'publish',
		));

		update_post_meta($booking_id, '_mhmrentiva_status', $opts['status']);
		update_post_meta($booking_id, '_mhmrentiva_vehicle_id', self::VEHICLE_ID);
		// Every timestamp handed to this helper is site-local (built from
		// current_time), so it is formatted as-is. wp_date() would apply the
		// offset a second time -- harmless while the test site sits at UTC and
		// wrong by the offset the moment a test pins the clock. CI caught the
		// first two of these; these four were the rest of the same shape.
		update_post_meta($booking_id, '_mhmrentiva_pickup_date', gmdate('Y-m-d', $opts['start_ts']));
		update_post_meta($booking_id, '_mhmrentiva_pickup_time', gmdate('H:i', $opts['start_ts']));
		update_post_meta($booking_id, '_mhmrentiva_start_ts', $opts['start_ts']);

		$dropoff_date = $opts['dropoff_date'] ?? gmdate('Y-m-d', $opts['end_ts']);
		update_post_meta($booking_id, '_mhmrentiva_dropoff_date', $dropoff_date);
		update_post_meta($booking_id, '_mhmrentiva_end_date', $dropoff_date);

		if ($opts['set_end_ts']) {
			update_post_meta($booking_id, '_mhmrentiva_end_ts', $opts['end_ts']);
		}
		if ($opts['set_dropoff_time']) {
			$dropoff_time = $opts['dropoff_time'] ?? gmdate('H:i', $opts['end_ts']);
			update_post_meta($booking_id, '_mhmrentiva_dropoff_time', $dropoff_time);
		}

		return $booking_id;
	}

	/**
	 * RED with current code: confirmed booking, end_ts 1h in future,
	 * dropoff_date forced to TODAY → buggy date-only meta_query triggers
	 * premature auto-complete. After fix: end_ts > NOW → not completed.
	 */
	public function test_cron_does_not_complete_booking_when_end_ts_is_in_future(): void
	{
		// Same reason as the sibling test below: a fixture that says "dropoff is
		// today, end_ts is an hour away" is incoherent when the run starts at 23:30.
		$this->pin_site_hour(9);
		$now       = (int) current_time('timestamp');
		// $now is already site-local (current_time), so it is formatted as-is.
		// wp_date($fmt, $now) would shift it a SECOND time -- invisible while the
		// test site sits at UTC, and 14 hours wrong the moment the clock is pinned.
		$today_str = gmdate('Y-m-d', $now);

		$booking_id = $this->create_booking(array(
			'status'       => 'confirmed',
			'start_ts'     => $now - 86400,
			'end_ts'       => $now + 3600,
			'dropoff_date' => $today_str,
		));

		AutoComplete::run();

		$this->assertSame(
			'confirmed',
			get_post_meta($booking_id, '_mhmrentiva_status', true),
			'Booking with end_ts in the future must not be auto-completed, even when dropoff_date is today.'
		);
	}

	/**
	 * Baseline: confirmed booking ended 1h ago → must be completed.
	 */
	public function test_cron_completes_booking_when_end_ts_is_in_past(): void
	{
		$now = (int) current_time('timestamp');
		$booking_id = $this->create_booking(array(
			'status'   => 'confirmed',
			'start_ts' => $now - 172800,
			'end_ts'   => $now - 3600,
		));

		AutoComplete::run();

		$this->assertSame(
			'completed',
			get_post_meta($booking_id, '_mhmrentiva_status', true),
			'Booking with end_ts in the past must be auto-completed.'
		);
	}

	/**
	 * Fallback: confirmed, no _mhmrentiva_end_ts, dropoff datetime in past → completed.
	 */
	public function test_cron_uses_dropoff_datetime_fallback_when_end_ts_missing(): void
	{
		$now     = (int) current_time('timestamp');
		$past_ts = $now - 3600;

		$booking_id = $this->create_booking(array(
			'status'     => 'confirmed',
			'start_ts'   => $now - 172800,
			'end_ts'     => $past_ts,
			'set_end_ts' => false,
		));

		AutoComplete::run();

		$this->assertSame(
			'completed',
			get_post_meta($booking_id, '_mhmrentiva_status', true),
			'Booking without end_ts but with past dropoff datetime must be completed via fallback.'
		);
	}

	/**
	 * RED with current code (fallback path): no end_ts, dropoff_date=TODAY,
	 * dropoff_time is later TODAY. Buggy date-only query → wrongly completed.
	 * After fix: CONCAT(today, future_time) > NOW → not completed.
	 */
	public function test_cron_does_not_complete_when_end_ts_missing_and_dropoff_time_in_future(): void
	{
		// "+2 hours" has to stay inside today. This used to skip after 22:00,
		// which meant the cron's date-only bug went unmeasured for two hours of
		// every day and on whichever CI runs started then.
		$this->pin_site_hour(9);
		$now          = (int) current_time('timestamp');

		// $now is already site-local (current_time), so it is formatted as-is.
		// wp_date($fmt, $now) would shift it a SECOND time -- invisible while the
		// test site sits at UTC, and 14 hours wrong the moment the clock is pinned.
		$today_str         = gmdate('Y-m-d', $now);
		$future_time_today = gmdate('H:i', $now + 7200);

		$booking_id = $this->create_booking(array(
			'status'       => 'confirmed',
			'start_ts'     => $now - 86400,
			'end_ts'       => $now + 7200,
			'dropoff_date' => $today_str,
			'dropoff_time' => $future_time_today,
			'set_end_ts'   => false,
		));

		AutoComplete::run();

		$this->assertSame(
			'confirmed',
			get_post_meta($booking_id, '_mhmrentiva_status', true),
			'Booking with today dropoff and future dropoff_time, no end_ts, must not be completed.'
		);
	}

	/**
	 * Regression: cron only touches confirmed/in_progress.
	 * pending → unchanged. cancelled → unchanged. confirmed (expired) → completed.
	 */
	public function test_cron_only_processes_confirmed_and_in_progress_statuses(): void
	{
		$now  = (int) current_time('timestamp');
		$past = $now - 7200;

		$pending = $this->create_booking(array(
			'status'   => 'pending',
			'start_ts' => $now - 172800,
			'end_ts'   => $past,
		));
		$cancelled = $this->create_booking(array(
			'status'   => 'cancelled',
			'start_ts' => $now - 172800,
			'end_ts'   => $past,
		));
		$confirmed = $this->create_booking(array(
			'status'   => 'confirmed',
			'start_ts' => $now - 172800,
			'end_ts'   => $past,
		));

		AutoComplete::run();

		$this->assertSame('pending', get_post_meta($pending, '_mhmrentiva_status', true),
			'pending booking must not be touched by AutoComplete cron.');
		$this->assertSame('cancelled', get_post_meta($cancelled, '_mhmrentiva_status', true),
			'cancelled booking must not be touched by AutoComplete cron.');
		$this->assertSame('completed', get_post_meta($confirmed, '_mhmrentiva_status', true),
			'confirmed expired booking must be auto-completed.');
	}
}
