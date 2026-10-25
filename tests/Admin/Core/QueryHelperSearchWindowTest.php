<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Core;

use MHMRentiva\Admin\Booking\Helpers\SiteTime;
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

	public function test_search_window_uses_local_day_bounds(): void
	{
		$this->pin_site_timezone('Europe/Istanbul');

		$vehicle = $this->vehicle();
		$this->booking($vehicle, (int) SiteTime::instant('2027-06-10', '10:00'), (int) SiteTime::instant('2027-06-11', '10:00'));

		$this->assertFalse($this->is_listed($vehicle, '2027-06-10', '2027-06-10'), 'The booked day collides.');
		$this->assertTrue($this->is_listed($vehicle, '2027-06-12', '2027-06-12'), 'The day after the return is free.');

		// Picked up at 01:00 on the 13th, local: the 12th is free. Read as UTC
		// the 12th would run until 02:59 local on the 13th and swallow it.
		$early = $this->vehicle();
		$this->booking($early, (int) SiteTime::instant('2027-06-13', '01:00'), (int) SiteTime::instant('2027-06-14', '01:00'));

		$this->assertTrue($this->is_listed($early, '2027-06-12', '2027-06-12'), 'A pickup after local midnight is not on the searched day.');
		$this->assertFalse($this->is_listed($early, '2027-06-13', '2027-06-13'), 'It is on the next one.');
	}
}
