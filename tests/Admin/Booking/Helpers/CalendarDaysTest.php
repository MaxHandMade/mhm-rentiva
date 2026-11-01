<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Helpers;

use MHMRentiva\Admin\Booking\Helpers\BookingConflicts;
use MHMRentiva\Admin\Booking\Helpers\SiteTime;
use MHMRentiva\Admin\Frontend\Shortcodes\VehicleDetails;
use MHMRentiva\Admin\Settings\Core\SettingsCore;
use MHMRentiva\Tests\Support\SiteClock;
use WP_UnitTestCase;

/**
 * PR E -- the one calendar rule.
 *
 * The vehicle page's grid and the availability calendar paint what
 * BookingConflicts::calendar_days() returns: the holding bookings of the
 * shared rule plus the manually blocked days. The buffer selects bookings,
 * it adds no day.
 *
 * @covers \MHMRentiva\Admin\Booking\Helpers\BookingConflicts::calendar_days
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\VehicleDetails::booked_dates
 */
final class CalendarDaysTest extends WP_UnitTestCase
{
	use SiteClock;

	public function setUp(): void
	{
		parent::setUp();
		$this->pin_site_timezone('Europe/Istanbul');
		add_filter(
			'option_' . SettingsCore::OPTION_NAME,
			static function ($settings): array {
				$settings = is_array($settings) ? $settings : array();
				$settings['mhmrentiva_booking_buffer_time'] = '60';

				return $settings;
			}
		);
	}

	public function tearDown(): void
	{
		remove_all_filters('mhmrentiva_vehicle_blocked_days');
		parent::tearDown();
	}

	private function vehicle(array $blocked = array()): int
	{
		$id = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_vehicle', 'post_status' => 'publish' ));
		if (array() !== $blocked) {
			update_post_meta($id, '_mhmrentiva_blocked_dates', wp_json_encode($blocked));
		}

		return $id;
	}

	private function booking(int $vehicle_id, string $status, string $from, string $from_time, string $to, string $to_time, array $extra = array()): int
	{
		$start = (int) SiteTime::instant($from, $from_time);
		$end   = (int) SiteTime::instant($to, $to_time);
		$id    = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_booking', 'post_status' => 'publish' ));
		update_post_meta($id, '_mhmrentiva_vehicle_id', $vehicle_id);
		update_post_meta($id, '_mhmrentiva_status', $status);
		update_post_meta($id, '_mhmrentiva_pickup_date', $from);
		update_post_meta($id, '_mhmrentiva_pickup_time', $from_time);
		update_post_meta($id, '_mhmrentiva_dropoff_date', $to);
		update_post_meta($id, '_mhmrentiva_dropoff_time', $to_time);
		update_post_meta($id, '_mhmrentiva_start_ts', $start);
		update_post_meta($id, '_mhmrentiva_end_ts', $end);
		foreach ($extra as $key => $value) {
			update_post_meta($id, $key, $value);
		}

		return $id;
	}

	public function test_confirmed_booking_paints_pickup_to_return_day(): void
	{
		$v = $this->vehicle();
		$this->booking($v, 'confirmed', '2027-07-01', '10:00', '2027-07-03', '10:00');

		$this->assertSame(array( '2027-07-01', '2027-07-02', '2027-07-03' ), BookingConflicts::calendar_days($v, '2027-07-01', '2027-07-31'));
		$this->assertSame(array(), BookingConflicts::calendar_days($v, '2027-08-01', '2027-08-31'));
	}

	public function test_booking_crossing_a_month_edge_paints_both_sides(): void
	{
		$v = $this->vehicle();
		$this->booking($v, 'confirmed', '2027-07-30', '10:00', '2027-08-02', '10:00');

		$this->assertSame(array( '2027-07-30', '2027-07-31' ), BookingConflicts::calendar_days($v, '2027-07-01', '2027-07-31'));
		$this->assertSame(array( '2027-08-01', '2027-08-02' ), BookingConflicts::calendar_days($v, '2027-08-01', '2027-08-31'));
	}

	public function test_midnight_return_day_is_included(): void
	{
		$v = $this->vehicle();
		$this->booking($v, 'confirmed', '2027-07-01', '10:00', '2027-07-03', '00:00');

		$this->assertSame(array( '2027-07-01', '2027-07-02', '2027-07-03' ), BookingConflicts::calendar_days($v, '2027-07-01', '2027-07-31'));
	}

	public function test_statuses_that_hold_nothing_paint_nothing(): void
	{
		$v = $this->vehicle();
		$this->booking($v, 'no_show', '2027-07-01', '10:00', '2027-07-03', '10:00');
		$this->booking($v, 'draft', '2027-07-05', '10:00', '2027-07-06', '10:00');
		$this->booking($v, 'cancelled', '2027-07-08', '10:00', '2027-07-09', '10:00');
		$this->booking($v, 'refunded', '2027-07-10', '10:00', '2027-07-11', '10:00');

		$this->assertSame(array(), BookingConflicts::calendar_days($v, '2027-07-01', '2027-07-31'));
	}

	public function test_lapsed_payment_deadline_paints_nothing_and_a_live_one_paints(): void
	{
		$v = $this->vehicle();
		$this->booking($v, 'pending_payment', '2027-07-01', '10:00', '2027-07-02', '10:00', array( '_mhmrentiva_payment_deadline' => gmdate('Y-m-d H:i:s', time() - HOUR_IN_SECONDS) ));
		$this->booking($v, 'pending_payment', '2027-07-10', '10:00', '2027-07-11', '10:00', array( '_mhmrentiva_payment_deadline' => gmdate('Y-m-d H:i:s', time() + HOUR_IN_SECONDS) ));

		$this->assertSame(array( '2027-07-10', '2027-07-11' ), BookingConflicts::calendar_days($v, '2027-07-01', '2027-07-31'));
	}

	/**
	 * Deliberate change: the old vehicle-page SQL painted every `completed`
	 * booking, past ones included. The shared rule keeps a completed booking
	 * holding only until its real return, so a finished rental is no longer
	 * "booked" (the availability calendar shows those days as "Past").
	 */
	public function test_completed_booking_holds_only_until_its_return(): void
	{
		$v = $this->vehicle();
		$this->booking($v, 'completed', '2020-07-01', '10:00', '2020-07-03', '10:00');
		$this->booking($v, 'completed', '2037-07-01', '10:00', '2037-07-03', '10:00');

		$this->assertSame(array(), BookingConflicts::calendar_days($v, '2020-07-01', '2020-07-31'));
		$this->assertSame(array( '2037-07-01', '2037-07-02', '2037-07-03' ), BookingConflicts::calendar_days($v, '2037-07-01', '2037-07-31'));
	}

	public function test_manually_blocked_days_paint_inside_the_range_only(): void
	{
		$v = $this->vehicle(array( '2027-07-15', '2027-08-02', '2027-06-30' ));

		$this->assertSame(array( '2027-07-15' ), BookingConflicts::calendar_days($v, '2027-07-01', '2027-07-31'));
	}

	public function test_blocked_day_filter_still_applies(): void
	{
		$v = $this->vehicle(array( '2027-07-15' ));
		add_filter(
			'mhmrentiva_vehicle_blocked_days',
			static function (array $days, int $vehicle_id) use ($v): array {
				return $vehicle_id === $v ? array( '2027-07-20' ) : $days;
			},
			10,
			2
		);

		$this->assertSame(array( '2027-07-20' ), BookingConflicts::calendar_days($v, '2027-07-01', '2027-07-31'));
	}

	public function test_booking_and_blocked_day_merge_sorted_and_unique(): void
	{
		$v = $this->vehicle(array( '2027-07-02', '2027-07-20' ));
		$this->booking($v, 'confirmed', '2027-07-01', '10:00', '2027-07-03', '10:00');

		$this->assertSame(array( '2027-07-01', '2027-07-02', '2027-07-03', '2027-07-20' ), BookingConflicts::calendar_days($v, '2027-07-01', '2027-07-31'));
	}

	public function test_buffer_selects_but_adds_no_day(): void
	{
		$v = $this->vehicle();
		// Returns 23:30 on the 4th: its 60-minute buffer reaches into the 5th,
		// which the search would call taken. The grid still paints days only.
		$this->booking($v, 'confirmed', '2027-07-03', '10:00', '2027-07-04', '23:30');

		$this->assertSame(array( '2027-07-03', '2027-07-04' ), BookingConflicts::calendar_days($v, '2027-07-01', '2027-07-31'));
		$this->assertSame(array(), BookingConflicts::calendar_days($v, '2027-07-05', '2027-07-31'));
	}

	public function test_bad_input_is_empty(): void
	{
		$v = $this->vehicle(array( '2027-07-15' ));

		$this->assertSame(array(), BookingConflicts::calendar_days($v, 'nonsense', '2027-07-31'));
		$this->assertSame(array(), BookingConflicts::calendar_days($v, '2027-07-31', '2027-07-01'));
		$this->assertSame(array(), BookingConflicts::calendar_days(0, '2027-07-01', '2027-07-31'));
	}

	public function test_vehicle_details_delegates(): void
	{
		$v = $this->vehicle(array( '2027-07-15' ));
		$this->booking($v, 'confirmed', '2027-07-01', '10:00', '2027-07-02', '10:00');

		$this->assertSame(BookingConflicts::calendar_days($v, '2027-07-01', '2027-07-31'), VehicleDetails::booked_dates($v, '2027-07-01', '2027-07-31'));
	}

	/**
	 * A painted day is never one the rule calls free for that whole local day
	 * (the day grid agrees with is_booked() on a whole-day window), except the
	 * return day, which is painted though the car is back by its return time.
	 */
	public function test_painted_days_agree_with_is_booked(): void
	{
		$v = $this->vehicle(array( '2027-07-15' ));
		$this->booking($v, 'confirmed', '2027-07-01', '10:00', '2027-07-03', '10:00');
		$this->booking($v, 'no_show', '2027-07-10', '10:00', '2027-07-12', '10:00');

		$painted = BookingConflicts::calendar_days($v, '2027-07-01', '2027-07-31');
		foreach (range(1, 31) as $n) {
			$date  = sprintf('2027-07-%02d', $n);
			$start = (int) SiteTime::instant($date, '00:00');
			$end   = $start + DAY_IN_SECONDS;
			if ('2027-07-03' === $date) {
				// Return day: busy until 10:00, so the whole day is "booked" too.
				$this->assertTrue(BookingConflicts::is_booked($v, $start, $end), $date);
				$this->assertContains($date, $painted);
				continue;
			}
			$this->assertSame(BookingConflicts::is_booked($v, $start, $end), in_array($date, $painted, true), $date);
		}
	}
}
