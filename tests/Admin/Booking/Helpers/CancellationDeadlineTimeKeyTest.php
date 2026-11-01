<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Helpers;

use MHMRentiva\Admin\Booking\Helpers\CancellationHandler;
use MHMRentiva\Admin\Settings\Core\SettingsCore;
use WP_UnitTestCase;

/**
 * PR D Task 4 (R10, m2, m9): the cancellation deadline counts from the time the
 * booking actually stores. The booking form and the manual booking screen
 * write `_mhmrentiva_start_time`; the deadline read only
 * `_mhmrentiva_pickup_time` and fell back to 10:00, so a hand-entered 14:00
 * pickup had its deadline four hours early -- and the screen and the refusal
 * could disagree.
 *
 * @covers \MHMRentiva\Admin\Booking\Helpers\CancellationHandler::pickup_time_for
 * @covers \MHMRentiva\Admin\Booking\Helpers\CancellationHandler::check_cancellation_deadline
 * @covers \MHMRentiva\Admin\Booking\Helpers\CancellationHandler::get_cancellation_info
 */
final class CancellationDeadlineTimeKeyTest extends WP_UnitTestCase
{
	public function setUp(): void
	{
		parent::setUp();
		update_option('timezone_string', 'Europe/Istanbul');
		$settings = (array) get_option(SettingsCore::OPTION_NAME, array());
		$settings['mhmrentiva_booking_cancellation_deadline_hours'] = 24;
		update_option(SettingsCore::OPTION_NAME, $settings);
	}

	public function tearDown(): void
	{
		update_option('timezone_string', '');
		parent::tearDown();
	}

	private function booking(array $meta): int
	{
		$id = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_booking' ));
		foreach ($meta as $key => $value) {
			update_post_meta($id, $key, $value);
		}
		return $id;
	}

	/**
	 * A booking whose pickup is `$offset` seconds from now, stored as the
	 * manual booking screen stores it: start_time only.
	 *
	 * @return array{0: int, 1: int} booking id, pickup timestamp (minute precision)
	 */
	private function start_time_booking(int $offset, array $extra = array()): array
	{
		$pickup = ( new \DateTimeImmutable('@' . ( time() + $offset )) )->setTimezone(wp_timezone());
		$id     = $this->booking(
			array(
				'_mhmrentiva_pickup_date' => $pickup->format('Y-m-d'),
				'_mhmrentiva_start_time'  => $pickup->format('H:i'),
			) + $extra
		);

		$ts = ( new \DateTimeImmutable($pickup->format('Y-m-d H:i'), wp_timezone()) )->getTimestamp();

		return array( $id, $ts );
	}

	public function test_start_time_wins(): void
	{
		// Pickup 24 h + 2 min ahead (minute rounding keeps it > 24 h): 1 min
		// before the deadline -> accepted, even with a pickup_time that would
		// put the deadline in the past.
		$early_time = ( new \DateTimeImmutable('@' . ( time() + 3600 )) )->setTimezone(wp_timezone())->format('H:i');
		list( $open, $open_pickup ) = $this->start_time_booking(DAY_IN_SECONDS + 120, array( '_mhmrentiva_pickup_time' => $early_time ));

		$this->assertTrue(CancellationHandler::check_cancellation_deadline($open), 'Before the deadline the cancellation is accepted.');
		$info = CancellationHandler::get_cancellation_info($open);
		$this->assertSame($open_pickup - DAY_IN_SECONDS, $info['deadline_ts'], 'The deadline is start_time - 24 h.');
		$this->assertTrue($info['can_cancel']);
		$this->assertSame(wp_date('Y-m-d H:i', $open_pickup - DAY_IN_SECONDS), $info['deadline']);

		// Pickup 24 h - 1 min ahead: the deadline passed a minute ago -> refused,
		// and the screen agrees.
		list( $closed, $closed_pickup ) = $this->start_time_booking(DAY_IN_SECONDS - 60);
		$check = CancellationHandler::check_cancellation_deadline($closed);
		$this->assertWPError($check);
		$this->assertSame('deadline_passed', $check->get_error_code());
		$info = CancellationHandler::get_cancellation_info($closed);
		$this->assertFalse($info['can_cancel']);
		$this->assertSame($closed_pickup - DAY_IN_SECONDS, $info['deadline_ts']);
	}

	public function test_pickup_time_fallback_and_default(): void
	{
		$this->assertSame('14:00', CancellationHandler::pickup_time_for($this->booking(array( '_mhmrentiva_start_time' => '14:00', '_mhmrentiva_pickup_time' => '09:00' ))));
		$this->assertSame('09:00', CancellationHandler::pickup_time_for($this->booking(array( '_mhmrentiva_start_time' => '', '_mhmrentiva_pickup_time' => '09:00' ))), 'An empty start_time counts as missing.');
		$this->assertSame('08:30', CancellationHandler::pickup_time_for($this->booking(array( '_mhmrentiva_booking_pickup_time' => '8:30' ))));
		$this->assertSame('16:45', CancellationHandler::pickup_time_for($this->booking(array( '_mhmrentiva_start_time' => '16:45:00' ))), 'Seconds are dropped.');
		$this->assertSame('10:00', CancellationHandler::pickup_time_for($this->booking(array( '_mhmrentiva_start_time' => 'soon' ))));
		$this->assertSame('10:00', CancellationHandler::pickup_time_for($this->booking(array())));

		// Both readers use the same instant.
		$id   = $this->booking(array( '_mhmrentiva_pickup_date' => '2031-03-10', '_mhmrentiva_pickup_time' => '09:00' ));
		$info = CancellationHandler::get_cancellation_info($id);
		$this->assertSame(( new \DateTimeImmutable('2031-03-09 09:00', wp_timezone()) )->getTimestamp(), $info['deadline_ts']);
		$this->assertTrue(CancellationHandler::check_cancellation_deadline($id));
	}

	public function test_missing_pickup_date_has_no_deadline(): void
	{
		$id   = $this->booking(array( '_mhmrentiva_start_time' => '10:00' ));
		$info = CancellationHandler::get_cancellation_info($id);

		$this->assertNull($info['deadline_ts']);
		$this->assertFalse($info['can_cancel']);
		$this->assertWPError(CancellationHandler::check_cancellation_deadline($id));
	}
}
