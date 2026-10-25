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
 * same whatever hour the suite runs at. New York at 00:30 on 9 March 2026 is
 * the morning after the spring-forward night: a 23-hour day sits inside the
 * window, and half an hour after local midnight any reading that shifts by
 * the offset a second time lands on the previous day.
 *
 * @covers \MHMRentiva\Admin\Utilities\Dashboard\DashboardService::get_revenue_data
 */
final class DashboardDayBucketsTest extends WP_UnitTestCase
{
	use SiteClock;

	public function test_last_seven_days_are_local_days(): void
	{
		$this->pin_site_timezone('America/New_York');
		$now = (int) SiteTime::instant('2026-03-09', '00:30');

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
