<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Helpers;

use MHMRentiva\Admin\Booking\Helpers\BookingConflicts;
use MHMRentiva\Admin\Booking\Helpers\SiteTime;
use MHMRentiva\Admin\Settings\Core\SettingsCore;
use MHMRentiva\Tests\Support\SiteClock;
use WP_UnitTestCase;

/**
 * Ç6 (approved 2026-11-11): a calendar day knows WHEN it is taken.
 *
 * calendar_days() painted the return day of a 10:00 -> 10:00 booking as
 * booked while the card offered that day at 11:00 (customer-eye round
 * 2026-11-10, #1/#15), and showed a day the operator closed by hand as
 * "Booked" (#2). calendar_day_states() keeps the same inputs -- the holding
 * bookings and the blocked days -- and says, per day, whether it is booked,
 * closed, or free from / until a time. The hold is the booking widened by
 * the buffer, exactly the conflict rule (start - buffer < end, end + buffer
 * > start), and the times sit on the half-hour grid the quote accepts:
 * "from" rounds up, "until" rounds down, so the calendar never offers a
 * slot the server would refuse.
 *
 * @covers \MHMRentiva\Admin\Booking\Helpers\BookingConflicts::calendar_day_states
 */
final class CalendarDayStatesTest extends WP_UnitTestCase
{
	use SiteClock;

	private string $buffer = '60';

	public function setUp(): void
	{
		parent::setUp();
		$this->pin_site_timezone('Europe/Istanbul');
		add_filter('option_' . SettingsCore::OPTION_NAME, array( $this, 'with_buffer' ));
	}

	public function tearDown(): void
	{
		remove_filter('option_' . SettingsCore::OPTION_NAME, array( $this, 'with_buffer' ));
		remove_all_filters('mhmrentiva_vehicle_blocked_days');
		parent::tearDown();
	}

	/**
	 * @param mixed $settings
	 * @return array<string, mixed>
	 */
	public function with_buffer($settings): array
	{
		$settings = is_array($settings) ? $settings : array();
		$settings['mhmrentiva_booking_buffer_time'] = $this->buffer;

		return $settings;
	}

	/** @param list<string> $blocked */
	private function vehicle(array $blocked = array()): int
	{
		$id = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_vehicle', 'post_status' => 'publish' ));
		if (array() !== $blocked) {
			update_post_meta($id, '_mhmrentiva_blocked_dates', wp_json_encode($blocked));
		}

		return $id;
	}

	private function booking(int $vehicle_id, string $from, string $from_time, string $to, string $to_time): void
	{
		$id = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_booking', 'post_status' => 'publish' ));
		update_post_meta($id, '_mhmrentiva_vehicle_id', $vehicle_id);
		update_post_meta($id, '_mhmrentiva_status', 'confirmed');
		update_post_meta($id, '_mhmrentiva_pickup_date', $from);
		update_post_meta($id, '_mhmrentiva_pickup_time', $from_time);
		update_post_meta($id, '_mhmrentiva_dropoff_date', $to);
		update_post_meta($id, '_mhmrentiva_dropoff_time', $to_time);
		update_post_meta($id, '_mhmrentiva_start_ts', (int) SiteTime::instant($from, $from_time));
		update_post_meta($id, '_mhmrentiva_end_ts', (int) SiteTime::instant($to, $to_time));
	}

	public function test_pickup_day_is_free_until_and_return_day_free_from(): void
	{
		$v = $this->vehicle();
		$this->booking($v, '2027-07-01', '10:00', '2027-07-03', '10:00');

		$this->assertSame(
			array(
				'2027-07-01' => array( 'state' => 'partial', 'until' => '09:00' ),
				'2027-07-02' => array( 'state' => 'booked' ),
				'2027-07-03' => array( 'state' => 'partial', 'from' => '11:00' ),
			),
			BookingConflicts::calendar_day_states($v, '2027-07-01', '2027-07-31')
		);
	}

	public function test_times_sit_on_the_half_hour_grid_from_up_until_down(): void
	{
		$v = $this->vehicle();
		$this->booking($v, '2027-07-01', '10:15', '2027-07-03', '10:15');

		$states = BookingConflicts::calendar_day_states($v, '2027-07-01', '2027-07-31');

		$this->assertSame(array( 'state' => 'partial', 'until' => '09:00' ), $states['2027-07-01'], '09:15 rounds down.');
		$this->assertSame(array( 'state' => 'partial', 'from' => '11:30' ), $states['2027-07-03'], '11:15 rounds up.');
	}

	public function test_a_day_holding_two_pieces_is_booked(): void
	{
		$v = $this->vehicle();
		$this->booking($v, '2027-07-01', '10:00', '2027-07-05', '10:00');
		$this->booking($v, '2027-07-05', '16:00', '2027-07-08', '10:00');

		$this->assertSame(array( 'state' => 'booked' ), BookingConflicts::calendar_day_states($v, '2027-07-01', '2027-07-31')['2027-07-05']);
	}

	public function test_a_short_hold_inside_one_day_is_booked(): void
	{
		$v = $this->vehicle();
		$this->booking($v, '2027-07-10', '12:00', '2027-07-10', '15:00');

		$this->assertSame(array( '2027-07-10' => array( 'state' => 'booked' ) ), BookingConflicts::calendar_day_states($v, '2027-07-01', '2027-07-31'));
	}

	public function test_a_blocked_day_is_closed_and_outranks_a_booking(): void
	{
		$v = $this->vehicle(array( '2027-07-03', '2027-07-20' ));
		$this->booking($v, '2027-07-01', '10:00', '2027-07-03', '10:00');

		$states = BookingConflicts::calendar_day_states($v, '2027-07-01', '2027-07-31');

		$this->assertSame(array( 'state' => 'closed' ), $states['2027-07-03']);
		$this->assertSame(array( 'state' => 'closed' ), $states['2027-07-20']);
		$this->assertSame(array( 'state' => 'partial', 'until' => '09:00' ), $states['2027-07-01']);
	}

	public function test_without_a_buffer_the_booking_times_are_the_edges(): void
	{
		$this->buffer = '0';
		$v            = $this->vehicle();
		$this->booking($v, '2027-07-01', '10:00', '2027-07-03', '10:00');

		$states = BookingConflicts::calendar_day_states($v, '2027-07-01', '2027-07-31');

		$this->assertSame(array( 'state' => 'partial', 'until' => '10:00' ), $states['2027-07-01']);
		$this->assertSame(array( 'state' => 'partial', 'from' => '10:00' ), $states['2027-07-03']);
	}

	public function test_a_midnight_return_without_a_buffer_leaves_the_day_free(): void
	{
		$this->buffer = '0';
		$v            = $this->vehicle();
		$this->booking($v, '2027-07-01', '10:00', '2027-07-03', '00:00');

		$states = BookingConflicts::calendar_day_states($v, '2027-07-01', '2027-07-31');

		$this->assertSame(array( 'state' => 'booked' ), $states['2027-07-02']);
		$this->assertArrayNotHasKey('2027-07-03', $states);
	}

	public function test_a_pickup_just_after_midnight_takes_the_evening_before(): void
	{
		$v = $this->vehicle();
		$this->booking($v, '2027-07-02', '00:30', '2027-07-04', '10:00');

		$states = BookingConflicts::calendar_day_states($v, '2027-07-01', '2027-07-31');

		$this->assertSame(array( 'state' => 'partial', 'until' => '23:30' ), $states['2027-07-01']);
		$this->assertSame(array( 'state' => 'booked' ), $states['2027-07-02']);
	}

	public function test_only_the_requested_window_is_returned(): void
	{
		$v = $this->vehicle();
		$this->booking($v, '2027-06-28', '10:00', '2027-07-02', '10:00');

		$this->assertSame(
			array(
				'2027-07-01' => array( 'state' => 'booked' ),
				'2027-07-02' => array( 'state' => 'partial', 'from' => '11:00' ),
			),
			BookingConflicts::calendar_day_states($v, '2027-07-01', '2027-07-31')
		);
	}

	public function test_calendar_days_keeps_its_meaning(): void
	{
		$v = $this->vehicle();
		$this->booking($v, '2027-07-01', '10:00', '2027-07-03', '10:00');

		$this->assertSame(array( '2027-07-01', '2027-07-02', '2027-07-03' ), BookingConflicts::calendar_days($v, '2027-07-01', '2027-07-31'));
	}
}
