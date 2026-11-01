<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use MHMRentiva\Admin\Frontend\Shortcodes\CalendarView;
use MHMRentiva\Tests\Support\SiteClock;
use WP_UnitTestCase;

/**
 * The availability calendar's "today" and default month are the site's.
 *
 * They were gmdate( 'Y-m-d' ) and gmdate( 'Y-m' ): on an Istanbul site, from
 * 21:00 to midnight local the calendar highlighted yesterday, and on the last
 * evening of a month it opened on the month that had just ended.
 *
 * The moment is passed in: 22:30 UTC on 31 October 2026. East of UTC that is
 * already 1 November; in New York (still EDT, -4) it is 18:30 on the 31st.
 *
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\CalendarView
 */
final class AvailabilityCalendarLocalTodayTest extends WP_UnitTestCase
{
	use SiteClock;

	private function pin_zone(string $zone): void
	{
		if ('+03:00' === $zone) {
			$this->pin_site_offset(3);
			return;
		}
		$this->pin_site_timezone($zone);
	}

	/**
	 * Zone, local month, yesterday, today, tomorrow at 2026-10-31 22:30 UTC.
	 *
	 * @return array<string, array{0: string, 1: string, 2: string, 3: string, 4: string}>
	 */
	public static function zones(): array
	{
		return array(
			'Istanbul'     => array( 'Europe/Istanbul', '2026-11', '2026-10-31', '2026-11-01', '2026-11-02' ),
			'New York'     => array( 'America/New_York', '2026-10', '2026-10-30', '2026-10-31', '2026-11-01' ),
			'Auckland'     => array( 'Pacific/Auckland', '2026-11', '2026-10-31', '2026-11-01', '2026-11-02' ),
			'fixed +03:00' => array( '+03:00', '2026-11', '2026-10-31', '2026-11-01', '2026-11-02' ),
		);
	}

	/**
	 * @dataProvider zones
	 */
	public function test_calendar_today_and_month_are_local(string $zone, string $month, string $yesterday, string $today, string $tomorrow): void
	{
		$this->pin_zone($zone);
		$now = gmmktime(22, 30, 0, 10, 31, 2026);

		$this->assertSame($month, CalendarView::current_month($now), 'The default month is the local one.');
		$this->assertSame($month, CalendarView::normalize_month('not-a-month', $now), 'A malformed month falls back to the local month.');
		$this->assertSame($month, CalendarView::opening_month('2026-01', $now), 'A past month opens on the local month.');

		$vehicle_id = (int) self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_vehicle',
				'post_status' => 'publish',
			)
		);

		// October and November, so yesterday, today and tomorrow are all in view.
		$cells = array();
		foreach (CalendarView::months($vehicle_id, '2026-10', 2, array( 'now' => $now )) as $calendar) {
			foreach ($calendar['weeks'] as $week) {
				foreach (array_filter($week) as $cell) {
					$cells[ $cell['date'] ] = $cell;
				}
			}
		}

		$this->assertTrue($cells[ $today ]['today'], "Local today is {$today}.");
		$this->assertSame('available', $cells[ $today ]['state']);
		$this->assertFalse($cells[ $yesterday ]['today']);
		$this->assertSame('past', $cells[ $yesterday ]['state']);
		$this->assertFalse($cells[ $tomorrow ]['today']);
		$this->assertSame('available', $cells[ $tomorrow ]['state']);
	}
}
