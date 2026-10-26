<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Helpers;

use MHMRentiva\Admin\Booking\Helpers\BookingConflicts;
use MHMRentiva\Admin\Booking\Helpers\NextFreeWindow;
use MHMRentiva\Admin\Booking\Helpers\SiteTime;
use MHMRentiva\Admin\Frontend\Shortcodes\Card\RvsCardView;
use MHMRentiva\Admin\Settings\Core\SettingsCore;
use MHMRentiva\Tests\Support\SiteClock;
use WP_UnitTestCase;

/**
 * PR S -- "Earliest free: %s" for a car that is booked on the searched dates.
 *
 * The answer is a start instant whose window has the vehicle page's shape:
 * the same number of local days and the return at the pickup's own time, so
 * the date on the card is a window the vehicle page then shows as free.
 *
 * @covers \MHMRentiva\Admin\Booking\Helpers\NextFreeWindow
 * @covers \MHMRentiva\Admin\Booking\Helpers\BookingConflicts::booking_intervals
 */
final class NextFreeWindowTest extends WP_UnitTestCase
{
	use SiteClock;

	/**
	 * Calls of the blocked-days filter in test_failed_verification_returns_null.
	 */
	private int $filter_calls = 0;

	public function setUp(): void
	{
		parent::setUp();

		// The buffer and horizon the cases are written for, whatever the test site stores.
		add_filter(
			'option_' . SettingsCore::OPTION_NAME,
			static function ($settings): array {
				$settings = is_array($settings) ? $settings : array();
				$settings['mhmrentiva_booking_buffer_time']          = '60';
				$settings['mhmrentiva_vehicle_advance_booking_days'] = 365;

				return $settings;
			}
		);
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
	 * @return array<string, array{0: string}>
	 */
	public static function zones(): array
	{
		return array(
			'Istanbul'     => array( 'Europe/Istanbul' ),
			'New York'     => array( 'America/New_York' ),
			'Auckland'     => array( 'Pacific/Auckland' ),
			'fixed +03:00' => array( '+03:00' ),
		);
	}

	/**
	 * @param list<string> $blocked
	 */
	private function vehicle(array $blocked = array()): int
	{
		$id = (int) self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_vehicle',
				'post_status' => 'publish',
			)
		);
		update_post_meta($id, '_mhmrentiva_vehicle_status', 'active');
		if (array() !== $blocked) {
			update_post_meta($id, '_mhmrentiva_blocked_dates', wp_json_encode($blocked));
		}

		return $id;
	}

	private function booking(int $vehicle_id, int $start_ts, int $end_ts, string $status = 'confirmed'): int
	{
		$id = (int) self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_booking',
				'post_status' => 'publish',
			)
		);
		update_post_meta($id, '_mhmrentiva_vehicle_id', $vehicle_id);
		update_post_meta($id, '_mhmrentiva_status', $status);
		update_post_meta($id, '_mhmrentiva_pickup_date', wp_date('Y-m-d', $start_ts));
		update_post_meta($id, '_mhmrentiva_pickup_time', wp_date('H:i', $start_ts));
		update_post_meta($id, '_mhmrentiva_dropoff_date', wp_date('Y-m-d', $end_ts));
		update_post_meta($id, '_mhmrentiva_dropoff_time', wp_date('H:i', $end_ts));
		update_post_meta($id, '_mhmrentiva_start_ts', $start_ts);
		update_post_meta($id, '_mhmrentiva_end_ts', $end_ts);

		return $id;
	}

	private static function local(string $date, string $time): int
	{
		return (int) SiteTime::instant($date, $time);
	}

	/**
	 * The local date $days after today.
	 */
	private static function day_from_today(int $days): string
	{
		return ( new \DateTimeImmutable('@' . time()) )->setTimezone(wp_timezone())->setTime(12, 0)->modify("+{$days} day")->format('Y-m-d');
	}

	private function earliest(int $vehicle_id, string $pickup_date, string $time, string $return_date): ?int
	{
		$answer = NextFreeWindow::earliest(array( $vehicle_id ), self::local($pickup_date, $time), self::local($return_date, $time));

		$this->assertArrayHasKey($vehicle_id, $answer);

		return $answer[ $vehicle_id ];
	}

	private function assert_local(string $expected, ?int $actual, string $label = ''): void
	{
		$this->assertNotNull($actual, $label . ' -- no window found');
		$this->assertSame($expected, wp_date('Y-m-d H:i', (int) $actual), $label);
	}

	/**
	 * @dataProvider zones
	 */
	public function test_free_request_is_its_own_answer(string $zone): void
	{
		$this->pin_zone($zone);
		$vehicle = $this->vehicle();

		$this->assert_local('2027-02-03 10:00', $this->earliest($vehicle, '2027-02-03', '10:00', '2027-02-06'));
	}

	/**
	 * @dataProvider zones
	 */
	public function test_after_one_booking_respects_buffer(string $zone): void
	{
		$this->pin_zone($zone);
		$vehicle = $this->vehicle();
		$this->booking($vehicle, self::local('2027-02-01', '10:00'), self::local('2027-02-04', '10:00'));

		$this->assert_local('2027-02-04 11:00', $this->earliest($vehicle, '2027-02-03', '10:00', '2027-02-06'));
	}

	/**
	 * @dataProvider zones
	 */
	public function test_rounds_up_to_full_hour(string $zone): void
	{
		$this->pin_zone($zone);
		$vehicle = $this->vehicle();
		$this->booking($vehicle, self::local('2027-02-01', '10:00'), self::local('2027-02-04', '10:10'));

		$this->assert_local('2027-02-04 12:00', $this->earliest($vehicle, '2027-02-03', '10:00', '2027-02-06'));
	}

	/**
	 * One second past the hour is the next hour.
	 *
	 * @dataProvider zones
	 */
	public function test_one_second_past_the_hour_rounds_to_the_next(string $zone): void
	{
		$this->pin_zone($zone);
		$vehicle = $this->vehicle();
		// Ends 10:00:01, so the buffer frees it at 11:00:01.
		$this->booking($vehicle, self::local('2027-02-01', '10:00'), self::local('2027-02-04', '10:00') + 1);

		$this->assert_local('2027-02-04 12:00', $this->earliest($vehicle, '2027-02-03', '10:00', '2027-02-06'));
	}

	/**
	 * A frees the car at 11:10. The 11:10 window would end before B's
	 * buffer, the rounded 12:00 window does not -- rounding happens inside the
	 * loop, so the rounded window is checked again and moves past B.
	 *
	 * @dataProvider zones
	 */
	public function test_rounding_rechecks_next_booking(string $zone): void
	{
		$this->pin_zone($zone);
		$vehicle = $this->vehicle();
		$this->booking($vehicle, self::local('2027-02-01', '10:00'), self::local('2027-02-04', '10:10'));
		// B's buffered start is 11:15 on the 7th: after 11:10, before 12:00.
		$this->booking($vehicle, self::local('2027-02-07', '12:15'), self::local('2027-02-08', '09:00'));

		$found = $this->earliest($vehicle, '2027-02-03', '10:00', '2027-02-06');

		$this->assert_local('2027-02-08 10:00', $found);
	}

	/**
	 * @dataProvider zones
	 */
	public function test_chained_bookings(string $zone): void
	{
		$this->pin_zone($zone);
		$vehicle = $this->vehicle();
		$this->booking($vehicle, self::local('2027-02-01', '10:00'), self::local('2027-02-04', '10:00'));
		$this->booking($vehicle, self::local('2027-02-05', '10:00'), self::local('2027-02-06', '10:00'));
		$this->booking($vehicle, self::local('2027-02-08', '09:00'), self::local('2027-02-09', '10:00'));

		// Three days: 4th 11:00 meets the second, 6th 11:00 the third, 9th 11:00 is free.
		$this->assert_local('2027-02-09 11:00', $this->earliest($vehicle, '2027-02-02', '10:00', '2027-02-05'));
	}

	/**
	 * A blocked day moves the candidate to the next day at the
	 * requested pickup time -- not midnight, and not the time a booking
	 * had pushed the candidate to.
	 *
	 * @dataProvider zones
	 */
	public function test_blocked_day_moves_to_next_day_same_time(string $zone): void
	{
		$this->pin_zone($zone);

		$plain = $this->vehicle(array( '2027-02-05' ));
		$this->assert_local('2027-02-06 10:00', $this->earliest($plain, '2027-02-04', '10:00', '2027-02-06'), 'blocked day inside the window');

		$after_booking = $this->vehicle(array( '2027-02-06' ));
		$this->booking($after_booking, self::local('2027-02-01', '10:00'), self::local('2027-02-04', '15:30'));
		// 16:30 -> 17:00, whose window reaches the 6th; the next day is the 7th at 10:00, not 17:00.
		$this->assert_local('2027-02-07 10:00', $this->earliest($after_booking, '2027-02-03', '10:00', '2027-02-05'), 'blocked day after a booking');
	}

	/**
	 * A window ending exactly at local midnight does not touch the
	 * day it ends on; a blocked day inside it still moves it.
	 *
	 * @dataProvider zones
	 */
	public function test_window_ending_at_midnight(string $zone): void
	{
		$this->pin_zone($zone);

		$end_day = $this->vehicle(array( '2027-02-03' ));
		$this->assert_local('2027-02-01 00:00', $this->earliest($end_day, '2027-02-01', '00:00', '2027-02-03'), 'the end day is not in the window');

		$inside = $this->vehicle(array( '2027-02-02', '2027-02-03' ));
		// 1st..2nd hits the 2nd -> 3rd 00:00, whose window hits the 3rd -> 4th 00:00.
		$this->assert_local('2027-02-04 00:00', $this->earliest($inside, '2027-02-01', '00:00', '2027-02-03'), 'blocked days inside the window');
	}

	/**
	 * The horizon is today + advance days, not the requested start + advance days.
	 *
	 * @dataProvider zones
	 */
	public function test_beyond_horizon_from_today_returns_null(string $zone): void
	{
		$this->pin_zone($zone);

		// Booked from day 200 to day 366: from the start, 365 days would reach past it;
		// from today, the last pickup day is 365 and its window is still booked.
		$far = $this->vehicle();
		$this->booking($far, self::local(self::day_from_today(200), '09:00'), self::local(self::day_from_today(366), '10:00'));
		$this->assertNull($this->earliest($far, self::day_from_today(200), '10:00', self::day_from_today(203)));

		// Booked until day 364: the first free pickup is on day 364, inside the horizon.
		$near = $this->vehicle();
		$this->booking($near, self::local(self::day_from_today(200), '09:00'), self::local(self::day_from_today(364), '10:00'));
		$this->assert_local(self::day_from_today(364) . ' 11:00', $this->earliest($near, self::day_from_today(200), '10:00', self::day_from_today(203)));

		// A request already past the horizon has nothing to offer.
		$free = $this->vehicle();
		$this->assertNull($this->earliest($free, self::day_from_today(366), '10:00', self::day_from_today(368)));
	}

	/**
	 * The answer's window is the vehicle page's window -- the same
	 * number of local days, return at the pickup's own time -- across DST.
	 *
	 * @dataProvider zones
	 */
	public function test_window_matches_vehicle_page(string $zone): void
	{
		$this->pin_zone($zone);
		$vehicle = $this->vehicle();
		// 12 -> 15 March 2027 crosses New York's spring change (14 March).
		$this->booking($vehicle, self::local('2027-03-10', '10:00'), self::local('2027-03-12', '15:20'));

		$found = $this->earliest($vehicle, '2027-03-12', '10:00', '2027-03-15');
		$this->assert_local('2027-03-12 17:00', $found);

		$window = NextFreeWindow::window((int) $found, 3);
		$page   = RvsCardView::window(
			array(
				'pickup_date' => wp_date('Y-m-d', (int) $found),
				'pickup_time' => wp_date('H:i', (int) $found),
				'return_date' => '2027-03-15',
			)
		);

		$this->assertSame($page, $window);
		$this->assertSame('2027-03-15 17:00', wp_date('Y-m-d H:i', (int) $window[1]));
		$this->assertSame(3, SiteTime::rental_days((int) $window[0], (int) $window[1]));
	}

	/**
	 * New York, 8 March 2026 -- 02:30 does not exist. The candidate
	 * lands on the first instant after the gap, 03:00, not 03:30 -> 04:00.
	 */
	public function test_spring_gap_candidate_moves_to_gap_end(): void
	{
		$this->pin_site_timezone('America/New_York');
		$vehicle = $this->vehicle(array( '2026-03-07' ));

		$found = $this->earliest($vehicle, '2026-03-06', '02:30', '2026-03-07');

		$this->assertSame(gmmktime(7, 0, 0, 3, 8, 2026), $found);
		$this->assert_local('2026-03-08 03:00', $found);
	}

	/**
	 * New York, 1 November 2026 -- 01:00-02:00 happens twice. The
	 * wall time on the card is read back as its first occurrence, so the
	 * answer is that one, or a later hour when that one is too early.
	 */
	public function test_repeated_autumn_hour_takes_first_occurrence(): void
	{
		$this->pin_site_timezone('America/New_York');

		// Blocked day -> 1 Nov 01:00: the first 01:00 (EDT, 05:00 UTC).
		$jump = $this->vehicle(array( '2026-10-31' ));
		$this->assertSame(gmmktime(5, 0, 0, 11, 1, 2026), $this->earliest($jump, '2026-10-30', '01:00', '2026-10-31'), 'first 01:00');

		// Blocked day -> 1 Nov 01:30, the first one (EDT); rounded: 02:00 EST.
		$half = $this->vehicle(array( '2026-10-31' ));
		$this->assertSame(gmmktime(7, 0, 0, 11, 1, 2026), $this->earliest($half, '2026-10-30', '01:30', '2026-10-31'), 'first 01:30, rounded');

		// Freed at the second 01:00 (EST, 06:00 UTC). "01:00" would read back as
		// the first one, an hour earlier and still booked; the answer is 02:00 EST.
		$second = $this->vehicle();
		$this->booking($second, gmmktime(5, 0, 0, 10, 30, 2026), gmmktime(5, 0, 0, 11, 1, 2026));
		$found = $this->earliest($second, '2026-10-31', '10:00', '2026-11-02');
		$this->assertSame(gmmktime(7, 0, 0, 11, 1, 2026), $found, 'second 01:00 is not a readable answer');
		$this->assertSame($found, SiteTime::instant((string) wp_date('Y-m-d', (int) $found), (string) wp_date('H:i', (int) $found)));
	}

	/**
	 * The answer is checked with the live rule; nothing is cached.
	 *
	 * @dataProvider zones
	 */
	public function test_result_is_not_booked(string $zone): void
	{
		$this->pin_zone($zone);
		$vehicle = $this->vehicle(array( '2027-02-10' ));
		$this->booking($vehicle, self::local('2027-02-01', '10:00'), self::local('2027-02-04', '10:10'));
		$this->booking($vehicle, self::local('2027-02-07', '12:15'), self::local('2027-02-08', '09:00'));

		$found = $this->earliest($vehicle, '2027-02-03', '10:00', '2027-02-06');

		// Past both bookings (8th 10:00), whose window reaches the blocked 10th -> 11th 10:00.
		$this->assert_local('2027-02-11 10:00', $found);
		$window = NextFreeWindow::window((int) $found, 3);
		$this->assertFalse(BookingConflicts::is_booked($vehicle, (int) $window[0], (int) $window[1]));
		$earlier = NextFreeWindow::window(self::local('2027-02-10', '10:00'), 3);
		$this->assertTrue(BookingConflicts::is_booked($vehicle, (int) $earlier[0], (int) $earlier[1]), 'a day earlier is still booked');
	}

	/**
	 * When the live check disagrees with the scan, no date is shown.
	 */
	public function test_failed_verification_returns_null(): void
	{
		$this->pin_site_timezone('Europe/Istanbul');
		$vehicle = $this->vehicle();

		// The first read (the scan) sees nothing; later reads (the check) see the day blocked.
		$this->filter_calls = 0;
		add_filter(
			'mhmrentiva_vehicle_blocked_days',
			function (array $days): array {
				++$this->filter_calls;

				return $this->filter_calls > 1 ? array_merge($days, array( '2027-02-03' )) : $days;
			}
		);

		$this->assertNull($this->earliest($vehicle, '2027-02-03', '10:00', '2027-02-06'));
	}

	/**
	 * Every vehicle answers, the order kept; the queries do not grow with the
	 * bookings or the days scanned.
	 */
	public function test_batch_answers_every_vehicle_with_flat_queries(): void
	{
		global $wpdb;

		$this->pin_site_timezone('Europe/Istanbul');

		$chained = $this->vehicle(array( '2027-03-20' ));
		for ($i = 0; $i < 10; $i++) {
			$this->booking($chained, self::local('2027-02-01', '10:00') + $i * 2 * DAY_IN_SECONDS, self::local('2027-02-02', '10:00') + $i * 2 * DAY_IN_SECONDS + 3 * HOUR_IN_SECONDS);
		}
		$free  = $this->vehicle();
		$never = $this->vehicle();
		$this->booking($never, self::local('2027-02-01', '10:00'), self::local(self::day_from_today(370), '10:00'));

		wp_cache_flush();
		$before = $wpdb->num_queries;
		$answer = NextFreeWindow::earliest(array( $chained, $free, $never ), self::local('2027-02-03', '10:00'), self::local('2027-02-06', '10:00'));
		$used   = $wpdb->num_queries - $before;

		$this->assertSame(array( $chained, $free, $never ), array_keys($answer));
		$this->assertNotNull($answer[ $chained ]);
		$this->assertSame(self::local('2027-02-03', '10:00'), $answer[ $free ]);
		$this->assertNull($answer[ $never ]);
		// One booking query, one meta prime, the settings option, one live check per found vehicle.
		$this->assertLessThanOrEqual(5, $used, 'queries used: ' . $used);
	}
}
