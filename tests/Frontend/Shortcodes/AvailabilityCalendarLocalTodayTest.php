<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use MHMRentiva\Admin\Frontend\Shortcodes\AvailabilityCalendar;
use MHMRentiva\Tests\Support\SiteClock;
use WP_UnitTestCase;

/**
 * The availability calendar's "today" and default month are the site's.
 *
 * They were gmdate( 'Y-m-d' ) and gmdate( 'Y-m' ): on an Istanbul site, from
 * 21:00 to midnight local the calendar highlighted yesterday, and on the last
 * evening of a month it opened on the month that had just ended.
 *
 * The moment is passed in: 22:30 UTC on 31 October 2026 is 01:30 on
 * 1 November in Istanbul.
 *
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\AvailabilityCalendar
 */
final class AvailabilityCalendarLocalTodayTest extends WP_UnitTestCase
{
	use SiteClock;

	/**
	 * @param array<int, mixed> $args
	 */
	private function call(string $method, array $args)
	{
		$ref = new \ReflectionMethod(AvailabilityCalendar::class, $method);
		$ref->setAccessible(true);

		return $ref->invokeArgs(null, $args);
	}

	public function test_calendar_today_and_month_are_local(): void
	{
		$this->pin_site_timezone('Europe/Istanbul');
		$now = gmmktime(22, 30, 0, 10, 31, 2026);

		$this->assertSame('2026-11', $this->call('current_month', array( $now )), 'The default month is November.');
		$this->assertSame('2026-11', $this->call('normalize_start_month', array( 'not-a-month', $now )), 'A malformed month falls back to the local month.');

		$vehicle_id = (int) self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_vehicle',
				'post_status' => 'publish',
			)
		);

		$data = $this->call('get_availability_data', array( $vehicle_id, '2026-11', 1, $now ));
		$days = $data['2026-11']['days'];

		$this->assertTrue($days['2026-11-01']['is_today'], 'Local today is 1 November.');
		$this->assertFalse($days['2026-11-01']['is_past']);
		$this->assertFalse($days['2026-11-02']['is_today']);
		$this->assertFalse($days['2026-11-02']['is_past']);
	}
}
