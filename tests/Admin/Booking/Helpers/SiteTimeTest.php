<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Helpers;

use MHMRentiva\Admin\Booking\Helpers\SiteTime;
use MHMRentiva\Admin\Booking\Helpers\Util;
use MHMRentiva\Tests\Support\SiteClock;
use WP_UnitTestCase;

/**
 * PR T -- every booking time is read in the site's configured timezone.
 *
 * The four zones cover the ways a "UTC is close enough" shortcut goes wrong:
 * east of UTC (Istanbul, +3), west of UTC with DST (New York), far east with
 * DST in the southern summer (Auckland, +12/+13), and a fixed offset with no
 * named zone (UTC+3, no DST).
 *
 * 2026-11-02 is a Monday; 2026-11-07 a Saturday. New York leaves DST on
 * 2026-11-01 and enters it on 2026-03-08.
 *
 * @covers \MHMRentiva\Admin\Booking\Helpers\SiteTime
 */
final class SiteTimeTest extends WP_UnitTestCase
{
	use SiteClock;

	private const FIXED_PLUS_3 = 'UTC+3';

	public function tearDown(): void
	{
		$this->release_site_clock();
		parent::tearDown();
	}

	private function zone(string $zone): void
	{
		if (self::FIXED_PLUS_3 === $zone) {
			$this->pin_site_offset(3);
			return;
		}
		$this->pin_site_timezone($zone);
	}

	/**
	 * Local 2026-11-02 01:00 as UTC, per zone.
	 *
	 * @return array<string, array{0: string, 1: int}>
	 */
	public static function instant_cases(): array
	{
		return array(
			'Istanbul'    => array( 'Europe/Istanbul', gmmktime(22, 0, 0, 11, 1, 2026) ),
			'New York'    => array( 'America/New_York', gmmktime(6, 0, 0, 11, 2, 2026) ),
			'Auckland'    => array( 'Pacific/Auckland', gmmktime(12, 0, 0, 11, 1, 2026) ),
			'fixed +3:00' => array( self::FIXED_PLUS_3, gmmktime(22, 0, 0, 11, 1, 2026) ),
		);
	}

	/**
	 * @dataProvider instant_cases
	 */
	public function test_instant_reads_local_wall_time(string $zone, int $expected): void
	{
		$this->zone($zone);

		$this->assertSame($expected, SiteTime::instant('2026-11-02', '01:00'));
		$this->assertSame($expected, SiteTime::instant('2026-11-02', '01:00:00'), 'H:i:s is the same instant.');
		$this->assertSame('2026-11-02', SiteTime::local_date($expected), 'And it reads back as the local date.');
		$this->assertNull(SiteTime::instant('2026-13-40', '01:00'), 'An impossible date is not normalised into another one.');
		$this->assertNull(SiteTime::instant('2026-11-02', '1 pm'));
		$this->assertNull(SiteTime::instant('02.11.2026', '01:00'));
	}

	/**
	 * Review Focus 2: the weekend boundary on both sides of UTC.
	 *
	 * @return array<string, array{0: string, 1: string, 2: string, 3: int}>
	 */
	public static function weekend_cases(): array
	{
		return array(
			// UTC: Sunday 22:00.
			'Istanbul Monday 01:00'      => array( 'Europe/Istanbul', '2026-11-02', '01:00', 1 ),
			// UTC: Sunday 03:00.
			'New York Saturday 22:00'    => array( 'America/New_York', '2026-11-07', '22:00', 6 ),
			// UTC: Saturday 03:00 -- the case UTC gets wrong west of Greenwich.
			'New York Friday 22:00'      => array( 'America/New_York', '2026-11-06', '22:00', 5 ),
			// UTC: Sunday 19:00.
			'Auckland Monday 08:00'      => array( 'Pacific/Auckland', '2026-11-02', '08:00', 1 ),
			'fixed +3:00 Monday 01:00'   => array( self::FIXED_PLUS_3, '2026-11-02', '01:00', 1 ),
		);
	}

	/**
	 * @dataProvider weekend_cases
	 */
	public function test_weekend_is_measured_in_site_timezone(string $zone, string $date, string $time, int $iso_weekday): void
	{
		$this->zone($zone);

		$ts = SiteTime::instant($date, $time);

		$this->assertIsInt($ts);
		$this->assertSame($iso_weekday, SiteTime::weekday($ts));
	}

	public function test_days_from_survives_dst(): void
	{
		$this->zone('America/New_York');

		$spring = SiteTime::instant('2026-03-08', '00:30');
		$autumn = SiteTime::instant('2026-11-01', '00:30');

		$this->assertSame(array( '2026-03-08', '2026-03-09', '2026-03-10' ), SiteTime::days_from((int) $spring, 3));
		// Adding 24 hours from 00:30 on the 25-hour day lands on 23:30 the same
		// day; counting by calendar does not.
		$this->assertSame(array( '2026-11-01', '2026-11-02', '2026-11-03' ), SiteTime::days_from((int) $autumn, 3));
		$this->assertSame(array(), SiteTime::days_from((int) $autumn, 0));
	}

	/**
	 * @return array<string, array{0: string, 1: string, 2: string, 3: string, 4: string, 5: int}>
	 */
	public static function rental_day_cases(): array
	{
		return array(
			// 71 elapsed hours; three calendar days.
			'New York across spring DST'  => array( 'America/New_York', '2026-03-07', '10:00', '2026-03-10', '10:00', 3 ),
			// 72.5 elapsed hours; 10:00 -> 09:30 is one day short, as everywhere.
			'New York across autumn DST'  => array( 'America/New_York', '2026-10-31', '10:00', '2026-11-03', '09:30', 2 ),
			'Istanbul (no DST)'           => array( 'Europe/Istanbul', '2026-11-01', '10:00', '2026-11-04', '10:00', 3 ),
			'Istanbul short return'       => array( 'Europe/Istanbul', '2026-11-01', '10:00', '2026-11-04', '09:30', 2 ),
			'same day is one day'         => array( 'Europe/Istanbul', '2026-11-01', '10:00', '2026-11-01', '18:00', 1 ),
			'fixed +3:00'                 => array( self::FIXED_PLUS_3, '2026-11-01', '10:00', '2026-11-04', '10:00', 3 ),
			'Auckland across spring DST'  => array( 'Pacific/Auckland', '2026-09-26', '10:00', '2026-09-29', '10:00', 3 ),
		);
	}

	/**
	 * @dataProvider rental_day_cases
	 */
	public function test_rental_days_counts_local_calendar_days(string $zone, string $from, string $from_time, string $to, string $to_time, int $days): void
	{
		$this->zone($zone);

		$start = (int) SiteTime::instant($from, $from_time);
		$end   = (int) SiteTime::instant($to, $to_time);

		$this->assertSame($days, SiteTime::rental_days($start, $end));
		$this->assertSame($days, Util::rental_days($start, $end), 'Util::rental_days() delegates.');
	}

	public function test_days_ago_and_month_start_are_local(): void
	{
		$this->zone('America/New_York');
		$now = (int) SiteTime::instant('2026-03-09', '00:30');

		$this->assertSame('2026-03-08', SiteTime::days_ago(1, $now));
		$this->assertSame('2026-03-02', SiteTime::days_ago(7, $now));
		$this->assertSame('2026-03-09', SiteTime::today($now));

		$this->zone('Europe/Istanbul');
		// 2026-10-31 22:00 UTC.
		$now = (int) SiteTime::instant('2026-11-01', '01:00');

		$this->assertSame('2026-11-01', SiteTime::month_start($now));
		$this->assertSame('2026-11-01', SiteTime::today($now));
		$this->assertSame('2026-10-31', SiteTime::days_ago(1, $now));

		$this->assertSame(wp_date('Y-m-d'), SiteTime::today(), 'Without $now it is the site-local today.');
	}

	/**
	 * @return array<string, array{0: string, 1: int, 2: string}>
	 */
	public static function offset_cases(): array
	{
		return array(
			'Istanbul'           => array( 'Europe/Istanbul', gmmktime(12, 0, 0, 1, 15, 2026), '+03:00' ),
			'New York winter'    => array( 'America/New_York', gmmktime(12, 0, 0, 1, 15, 2026), '-05:00' ),
			'New York summer'    => array( 'America/New_York', gmmktime(12, 0, 0, 7, 15, 2026), '-04:00' ),
			'Auckland summer'    => array( 'Pacific/Auckland', gmmktime(12, 0, 0, 1, 15, 2026), '+13:00' ),
			'Kathmandu'          => array( 'Asia/Kathmandu', gmmktime(12, 0, 0, 1, 15, 2026), '+05:45' ),
			'fixed +3:00'        => array( self::FIXED_PLUS_3, gmmktime(12, 0, 0, 7, 15, 2026), '+03:00' ),
		);
	}

	/**
	 * @dataProvider offset_cases
	 */
	public function test_offset_string_is_the_site_offset_at_that_instant(string $zone, int $at, string $expected): void
	{
		$this->zone($zone);

		$this->assertSame($expected, SiteTime::offset_string($at));
	}

	public function test_gmt_mysql_is_utc(): void
	{
		$this->zone('Europe/Istanbul');

		$this->assertSame('2026-11-01 22:00:00', SiteTime::gmt_mysql(gmmktime(22, 0, 0, 11, 1, 2026)));
	}
}
