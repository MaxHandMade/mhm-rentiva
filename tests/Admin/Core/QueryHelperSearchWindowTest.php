<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Core;

use MHMRentiva\Admin\Core\QueryHelper;
use MHMRentiva\Tests\Support\SiteClock;
use WP_UnitTestCase;

/**
 * A searched day is the site's calendar day.
 *
 * The search filter compares stored instants (`_mhmrentiva_start_ts` /
 * `_end_ts`) with the bounds of the searched dates. Those bounds came from
 * strtotime( $date . ' 00:00:00' ), which reads the date in PHP's zone (UTC
 * under WordPress), so on an Istanbul site the searched day ran 03:00 to
 * 02:59 the next morning.
 *
 * @covers \MHMRentiva\Admin\Core\QueryHelper::get_availability_subquery
 */
final class QueryHelperSearchWindowTest extends WP_UnitTestCase
{
	use SiteClock;

	private function vehicle(): int
	{
		return (int) self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_vehicle',
				'post_status' => 'publish',
			)
		);
	}

	private function booking(int $vehicle_id, int $start_ts, int $end_ts): void
	{
		$id = (int) self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_booking',
				'post_status' => 'publish',
			)
		);
		update_post_meta($id, '_mhmrentiva_vehicle_id', $vehicle_id);
		update_post_meta($id, '_mhmrentiva_status', 'confirmed');
		update_post_meta($id, '_mhmrentiva_start_ts', $start_ts);
		update_post_meta($id, '_mhmrentiva_end_ts', $end_ts);
	}

	/**
	 * Whether the vehicle survives the availability filter for the dates.
	 */
	private function is_listed(int $vehicle_id, string $pickup_date, string $return_date): bool
	{
		global $wpdb;

		$fragment = QueryHelper::get_availability_subquery($pickup_date, $return_date);
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- the fragment is prepared by QueryHelper.
		$found = $wpdb->get_var($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE ID = %d", $vehicle_id) . $fragment);

		return (int) $found === $vehicle_id;
	}

	private function pin_zone(string $zone): void
	{
		if ('+03:00' === $zone) {
			$this->pin_site_offset(3);
			return;
		}
		$this->pin_site_timezone($zone);
	}

	private static function utc(string $datetime): int
	{
		return (int) strtotime($datetime . ' UTC');
	}

	/**
	 * The four site clocks, each with the UTC instants (June: New York on
	 * EDT -4, Auckland on NZST +12) of three local pickups, each a 24-hour
	 * booking:
	 *  - 2027-06-10 10:00 -- the ordinary case;
	 *  - 2027-06-13 01:00 -- just after local midnight; a UTC day east of
	 *    UTC would still be the 12th and swallow it;
	 *  - 2027-06-10 23:00 -- returning 23:00 on the 11th, local; a UTC day
	 *    west of UTC would already be the 12th.
	 *
	 * @return array<string, array{0: string, 1: string, 2: string, 3: string}>
	 */
	public static function zones(): array
	{
		return array(
			'Istanbul'     => array( 'Europe/Istanbul', '2027-06-10 07:00', '2027-06-12 22:00', '2027-06-10 20:00' ),
			'New York'     => array( 'America/New_York', '2027-06-10 14:00', '2027-06-13 05:00', '2027-06-11 03:00' ),
			'Auckland'     => array( 'Pacific/Auckland', '2027-06-09 22:00', '2027-06-12 13:00', '2027-06-10 11:00' ),
			'fixed +03:00' => array( '+03:00', '2027-06-10 07:00', '2027-06-12 22:00', '2027-06-10 20:00' ),
		);
	}

	/**
	 * @dataProvider zones
	 */
	public function test_search_window_uses_local_day_bounds(string $zone, string $ordinary, string $after_midnight, string $late_evening): void
	{
		$this->pin_zone($zone);

		$vehicle = $this->vehicle();
		$this->booking($vehicle, self::utc($ordinary), self::utc($ordinary) + DAY_IN_SECONDS);

		$this->assertFalse($this->is_listed($vehicle, '2027-06-10', '2027-06-10'), 'The booked day collides.');
		$this->assertTrue($this->is_listed($vehicle, '2027-06-12', '2027-06-12'), 'The day after the return is free.');

		$early = $this->vehicle();
		$this->booking($early, self::utc($after_midnight), self::utc($after_midnight) + DAY_IN_SECONDS);

		$this->assertTrue($this->is_listed($early, '2027-06-12', '2027-06-12'), 'A pickup after local midnight is not on the searched day.');
		$this->assertFalse($this->is_listed($early, '2027-06-13', '2027-06-13'), 'It is on the next one.');

		$late = $this->vehicle();
		$this->booking($late, self::utc($late_evening), self::utc($late_evening) + DAY_IN_SECONDS);

		$this->assertTrue($this->is_listed($late, '2027-06-12', '2027-06-12'), 'A return before local midnight is not on the next day.');
		$this->assertFalse($this->is_listed($late, '2027-06-11', '2027-06-11'), 'It is on the return day.');
	}
}
