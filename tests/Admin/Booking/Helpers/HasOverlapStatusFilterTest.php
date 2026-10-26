<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Helpers;

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

	private const OTHER_VEHICLE_ID = 7778;

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
	private function create_dateless_booking(string $status, string $pickup_date, string $dropoff_date, int $vehicle_id = self::VEHICLE_ID): int
	{
		$booking_id = (int) self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_booking',
			'post_status' => 'publish',
		));

		update_post_meta($booking_id, '_mhmrentiva_status', $status);
		update_post_meta($booking_id, '_mhmrentiva_vehicle_id', $vehicle_id);
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
	 * A UTC wall-clock literal as a Unix timestamp. Expected instants are
	 * written out per zone, never derived from the code under test.
	 */
	private static function utc(string $datetime): int
	{
		return (int) strtotime($datetime . ' UTC');
	}

	private function pin_zone(string $zone): void
	{
		if ('+03:00' === $zone) {
			$this->pin_site_offset(3);
			return;
		}
		$this->pin_site_timezone($zone);
	}

	/**
	 * The four site clocks, each with the UTC instants of local
	 * 2027-05-10 10:00 and 2027-05-12 10:00 -- the window a dateless row
	 * with empty times (default 10:00) names. In May New York is on EDT
	 * (-4) and Auckland on NZST (+12).
	 *
	 * @return array<string, array{0: string, 1: string, 2: string}>
	 */
	public static function zones(): array
	{
		return array(
			'Istanbul'     => array( 'Europe/Istanbul', '2027-05-10 07:00', '2027-05-12 07:00' ),
			'New York'     => array( 'America/New_York', '2027-05-10 14:00', '2027-05-12 14:00' ),
			'Auckland'     => array( 'Pacific/Auckland', '2027-05-09 22:00', '2027-05-11 22:00' ),
			'fixed +03:00' => array( '+03:00', '2027-05-10 07:00', '2027-05-12 07:00' ),
		);
	}

	/**
	 * The overlap check reads stored instants and leaves the connection's
	 * session time zone exactly as it found it.
	 *
	 * @dataProvider zones
	 */
	public function test_overlap_without_session_time_zone(string $zone, string $start_utc, string $end_utc): void
	{
		$this->pin_zone($zone);
		$this->without_buffer();

		$start = self::utc($start_utc);
		$end   = self::utc($end_utc);
		$this->create_booking('confirmed', $start, $end);

		$before = $this->session_time_zone();

		$this->assertTrue(
			Util::has_overlap_locked(self::VEHICLE_ID, $start + DAY_IN_SECONDS, $end + DAY_IN_SECONDS),
			'A request inside the booked window must overlap.'
		);
		$this->assertFalse(
			Util::has_overlap_locked(self::VEHICLE_ID, $end, $end + 2 * DAY_IN_SECONDS),
			'A request starting exactly at the booked end must not overlap.'
		);
		$this->assertTrue(Util::has_overlap(self::VEHICLE_ID, $start + DAY_IN_SECONDS, $end + DAY_IN_SECONDS));
		$this->assertFalse(Util::has_overlap(self::VEHICLE_ID, $end, $end + 2 * DAY_IN_SECONDS));

		$this->assertSame($before, $this->session_time_zone(), 'The overlap queries must not change the session time zone.');

		$source = (string) file_get_contents(dirname(__DIR__, 4) . '/src/Admin/Booking/Helpers/Util.php');
		$this->assertDoesNotMatchRegularExpression('/SET\s+(SESSION\s+)?time_zone/i', $source);
	}

	/**
	 * A row without stored timestamps is read in the SITE zone (empty times
	 * defaulting to 10:00), whatever the session zone says.
	 *
	 * Margins are two hours: wider than the one-hour DST error the fixed
	 * fallback offset may carry (it is the offset of "now", not of May), and
	 * narrower than the three or more hours any zone mix-up -- session zone,
	 * wrong sign -- would move the window by.
	 *
	 * @dataProvider zones
	 */
	public function test_dateless_ts_fallback_uses_site_offset(string $zone, string $start_utc, string $end_utc): void
	{
		$this->pin_zone($zone);
		$this->without_buffer();
		$this->create_dateless_booking('confirmed', '2027-05-10', '2027-05-12');

		$start = self::utc($start_utc);
		$end   = self::utc($end_utc);
		$hour  = HOUR_IN_SECONDS;
		$day   = DAY_IN_SECONDS;

		$original = $this->session_time_zone();

		try {
			foreach (array( '+00:00', '+03:00', '-05:00' ) as $tz) {
				$this->set_session_time_zone($tz);

				$this->assertTrue(Util::has_overlap_locked(self::VEHICLE_ID, $end - 2 * $hour, $end + $day), "locked, inside, session {$tz}");
				$this->assertFalse(Util::has_overlap_locked(self::VEHICLE_ID, $end + 2 * $hour, $end + $day), "locked, after, session {$tz}");
				$this->assertFalse(Util::has_overlap_locked(self::VEHICLE_ID, $start - 3 * $hour, $start - 2 * $hour), "locked, before, session {$tz}");
				$this->assertTrue(Util::has_overlap(self::VEHICLE_ID, $end - 2 * $hour, $end + $day), "unlocked, inside, session {$tz}");
				$this->assertFalse(Util::has_overlap(self::VEHICLE_ID, $end + 2 * $hour, $end + $day), "unlocked, after, session {$tz}");
				$this->assertFalse(Util::has_overlap(self::VEHICLE_ID, $start - 3 * $hour, $start - 2 * $hour), "unlocked, before, session {$tz}");
				$this->assertSame($tz, $this->session_time_zone(), "session {$tz} left untouched");
			}
		} finally {
			$this->set_session_time_zone($original);
		}
	}

	/**
	 * A completed row without stored timestamps keeps blocking until its
	 * local dropoff, measured against the real "now".
	 *
	 * The dropoffs sit one hour either side of now -- less than any of the
	 * four zones' offsets -- so reading the local dropoff with a wrong
	 * offset (none, the wrong sign, the session's) flips one of the two
	 * answers. The fixture's local strings come from wp_date(), the same
	 * reading a dateless row was written with.
	 *
	 * @dataProvider zones
	 */
	public function test_completed_dateless_row_compares_with_real_now(string $zone): void
	{
		$this->pin_zone($zone);
		$this->without_buffer();

		$now      = time();
		$original = $this->session_time_zone();

		$running = $this->create_dateless_booking('completed', wp_date('Y-m-d', $now - DAY_IN_SECONDS), wp_date('Y-m-d', $now + HOUR_IN_SECONDS));
		update_post_meta($running, '_mhmrentiva_pickup_time', wp_date('H:i', $now - DAY_IN_SECONDS));
		update_post_meta($running, '_mhmrentiva_dropoff_time', wp_date('H:i', $now + HOUR_IN_SECONDS));

		$ended = $this->create_dateless_booking('completed', wp_date('Y-m-d', $now - DAY_IN_SECONDS), wp_date('Y-m-d', $now - HOUR_IN_SECONDS), self::OTHER_VEHICLE_ID);
		update_post_meta($ended, '_mhmrentiva_pickup_time', wp_date('H:i', $now - DAY_IN_SECONDS));
		update_post_meta($ended, '_mhmrentiva_dropoff_time', wp_date('H:i', $now - HOUR_IN_SECONDS));

		try {
			foreach (array( '+00:00', '-05:00' ) as $tz) {
				$this->set_session_time_zone($tz);
				$this->assertTrue(Util::has_overlap(self::VEHICLE_ID, $now, $now + 1800), "returns in an hour: still blocking, session {$tz}");
				$this->assertFalse(Util::has_overlap(self::OTHER_VEHICLE_ID, $now, $now + 1800), "returned an hour ago: free, session {$tz}");
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
