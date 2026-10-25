<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Helpers;

use MHMRentiva\Admin\Booking\Helpers\SiteTime;
use MHMRentiva\Admin\Booking\Helpers\Util;
use MHMRentiva\Admin\Settings\Core\SettingsCore;
use MHMRentiva\Tests\Support\SiteClock;
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
	use SiteClock;

	private const VEHICLE_ID = 7777;

	/**
	 * Zero buffer, so "adjacent" means end == start and nothing else.
	 */
	private function without_buffer(): void
	{
		add_filter(
			'option_' . SettingsCore::OPTION_NAME,
			static function ($settings): array {
				$settings = is_array($settings) ? $settings : array();
				$settings['mhmrentiva_booking_buffer_time'] = '0';

				return $settings;
			}
		);
	}

	/**
	 * A booking that carries only its date and time strings -- no stored
	 * timestamps -- so the SQL fallback has to read it.
	 */
	private function create_dateless_booking(string $status, string $pickup_date, string $dropoff_date): int
	{
		$booking_id = (int) self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_booking',
			'post_status' => 'publish',
		));

		update_post_meta($booking_id, '_mhmrentiva_status', $status);
		update_post_meta($booking_id, '_mhmrentiva_vehicle_id', self::VEHICLE_ID);
		update_post_meta($booking_id, '_mhmrentiva_pickup_date', $pickup_date);
		update_post_meta($booking_id, '_mhmrentiva_dropoff_date', $dropoff_date);

		return $booking_id;
	}

	private function session_time_zone(): string
	{
		global $wpdb;

		return (string) $wpdb->get_var('SELECT @@session.time_zone');
	}

	private function set_session_time_zone(string $tz): void
	{
		global $wpdb;

		// Test-only: production code must never change the session zone.
		$wpdb->query($wpdb->prepare('SET SESSION time_zone = %s', $tz));
	}

	/**
	 * The overlap check reads stored instants and leaves the connection's
	 * session time zone exactly as it found it.
	 */
	public function test_overlap_without_session_time_zone(): void
	{
		$this->pin_site_timezone('Europe/Istanbul');
		$this->without_buffer();

		$start = (int) SiteTime::instant('2027-05-10', '10:00');
		$end   = (int) SiteTime::instant('2027-05-12', '10:00');
		$this->create_booking('confirmed', $start, $end);

		$before = $this->session_time_zone();

		$this->assertTrue(
			Util::has_overlap_locked(self::VEHICLE_ID, (int) SiteTime::instant('2027-05-11', '10:00'), (int) SiteTime::instant('2027-05-13', '10:00')),
			'A request inside the booked window must overlap.'
		);
		$this->assertFalse(
			Util::has_overlap_locked(self::VEHICLE_ID, $end, (int) SiteTime::instant('2027-05-14', '10:00')),
			'A request starting exactly at the booked end must not overlap.'
		);
		$this->assertTrue(Util::has_overlap(self::VEHICLE_ID, (int) SiteTime::instant('2027-05-11', '10:00'), (int) SiteTime::instant('2027-05-13', '10:00')));
		$this->assertFalse(Util::has_overlap(self::VEHICLE_ID, $end, (int) SiteTime::instant('2027-05-14', '10:00')));

		$this->assertSame($before, $this->session_time_zone(), 'The overlap queries must not change the session time zone.');

		$source = (string) file_get_contents(dirname(__DIR__, 4) . '/src/Admin/Booking/Helpers/Util.php');
		$this->assertDoesNotMatchRegularExpression('/SET\s+(SESSION\s+)?time_zone/i', $source);
	}

	/**
	 * A row without stored timestamps is read in the SITE zone (Istanbul,
	 * empty times defaulting to 10:00), whatever the session zone says.
	 */
	public function test_dateless_ts_fallback_uses_site_offset(): void
	{
		$this->pin_site_timezone('Europe/Istanbul');
		$this->without_buffer();
		$this->create_dateless_booking('confirmed', '2027-05-10', '2027-05-12');

		// Local 09:30 on the dropoff day: still inside the 10:00 window.
		$inside_start = (int) SiteTime::instant('2027-05-12', '09:30');
		// Local 11:00 on the dropoff day: an hour after it ended -- but inside
		// it if 10:00 were read as UTC or as New York time.
		$after_start = (int) SiteTime::instant('2027-05-12', '11:00');
		// Local 09:00 on the pickup day ending local 09:30: before it started --
		// but inside it if 10:00 were read as UTC+05:00 or more.
		$before_start = (int) SiteTime::instant('2027-05-10', '09:00');
		$before_end   = (int) SiteTime::instant('2027-05-10', '09:30');
		$day          = DAY_IN_SECONDS;

		$original = $this->session_time_zone();

		try {
			foreach (array( '+00:00', '+03:00', '-05:00' ) as $tz) {
				$this->set_session_time_zone($tz);

				$this->assertTrue(Util::has_overlap_locked(self::VEHICLE_ID, $inside_start, $inside_start + $day), "locked, inside, session {$tz}");
				$this->assertFalse(Util::has_overlap_locked(self::VEHICLE_ID, $after_start, $after_start + $day), "locked, after, session {$tz}");
				$this->assertFalse(Util::has_overlap_locked(self::VEHICLE_ID, $before_start, $before_end), "locked, before, session {$tz}");
				$this->assertTrue(Util::has_overlap(self::VEHICLE_ID, $inside_start, $inside_start + $day), "unlocked, inside, session {$tz}");
				$this->assertFalse(Util::has_overlap(self::VEHICLE_ID, $after_start, $after_start + $day), "unlocked, after, session {$tz}");
				$this->assertSame($tz, $this->session_time_zone(), "session {$tz} left untouched");
			}
		} finally {
			$this->set_session_time_zone($original);
		}
	}

	/**
	 * A completed row without stored timestamps whose local dropoff is still
	 * ahead keeps blocking, measured against the real "now".
	 */
	public function test_completed_dateless_row_compares_with_real_now(): void
	{
		$this->pin_site_timezone('Europe/Istanbul');
		$this->without_buffer();

		$now = time();
		$id  = $this->create_dateless_booking('completed', SiteTime::local_date($now - 2 * DAY_IN_SECONDS), SiteTime::local_date($now + 2 * DAY_IN_SECONDS));
		update_post_meta($id, '_mhmrentiva_pickup_time', '10:00');
		update_post_meta($id, '_mhmrentiva_dropoff_time', '10:00');

		$original = $this->session_time_zone();

		try {
			foreach (array( '+00:00', '-05:00' ) as $tz) {
				$this->set_session_time_zone($tz);
				$this->assertTrue(Util::has_overlap(self::VEHICLE_ID, $now, $now + 3600), "session {$tz}");
			}
		} finally {
			$this->set_session_time_zone($original);
		}
	}

	private function create_booking(string $status, int $start_ts, int $end_ts): int
	{
		$booking_id = (int) self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_booking',
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
		$now = time();
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
		$now = time();
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
		$now = time();
		$this->create_booking('confirmed', $now + 1000, $now + 5000);

		$overlap = Util::has_overlap(self::VEHICLE_ID, $now + 2000, $now + 4000);

		$this->assertTrue($overlap, 'Confirmed booking covering request window must be flagged as overlap.');
	}
}
