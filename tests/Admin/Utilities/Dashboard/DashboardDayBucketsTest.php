<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Utilities\Dashboard;

use MHMRentiva\Admin\Booking\Helpers\SiteTime;
use MHMRentiva\Admin\Utilities\Dashboard\DashboardService;
use MHMRentiva\Tests\Support\SiteClock;
use WP_UnitTestCase;

/**
 * The dashboard's "last seven days" are the site's last seven calendar days.
 *
 * They were built as wp_date( strtotime( "-N days", current_time( 'timestamp' ) ) ):
 * current_time() already shifts by the offset and wp_date() shifts again, so
 * a site west or east of UTC labelled the wrong day for part of every day.
 *
 * The moment is passed in rather than read from the clock, so the test is the
 * same whatever hour the suite runs at. It is 00:30 local on 9 March 2026 in
 * every zone -- half an hour after local midnight, where any reading that
 * shifts by the offset a second time (or not at all) lands on another day.
 * In New York it is the morning after the spring-forward night, so a 23-hour
 * day sits inside the window.
 *
 * @covers \MHMRentiva\Admin\Utilities\Dashboard\DashboardService::get_revenue_data
 */
final class DashboardDayBucketsTest extends WP_UnitTestCase
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
	 * Each zone with the UTC instant of its local 2026-03-09 00:30 (New York
	 * on EDT -4 since the 8th, Auckland on NZDT +13).
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function zones(): array
	{
		return array(
			'Istanbul'     => array( 'Europe/Istanbul', '2026-03-08 21:30' ),
			'New York'     => array( 'America/New_York', '2026-03-09 04:30' ),
			'Auckland'     => array( 'Pacific/Auckland', '2026-03-08 11:30' ),
			'fixed +03:00' => array( '+03:00', '2026-03-08 21:30' ),
		);
	}

	/**
	 * @dataProvider zones
	 */
	public function test_last_seven_days_are_local_days(string $zone, string $now_utc): void
	{
		$this->pin_zone($zone);
		$now = (int) strtotime($now_utc . ' UTC');

		$expected = array( '2026-03-03', '2026-03-04', '2026-03-05', '2026-03-06', '2026-03-07', '2026-03-08', '2026-03-09' );

		$from_site_time = array();
		for ($i = 6; $i >= 0; $i--) {
			$from_site_time[] = SiteTime::days_ago($i, $now);
		}
		$this->assertSame($expected, $from_site_time);

		$labels = array_column(DashboardService::get_revenue_data($now)['daily_data'], 'date');
		$this->assertSame($expected, $labels, 'Seven local days, oldest first, no repeat and no gap.');
	}
}
