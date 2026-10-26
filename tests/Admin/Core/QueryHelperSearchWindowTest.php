<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Core;

use MHMRentiva\Admin\Core\QueryHelper;
use MHMRentiva\Admin\Settings\Core\SettingsCore;
use MHMRentiva\Tests\Support\SiteClock;
use WP_UnitTestCase;

/**
 * A searched window is read on the site's clock.
 *
 * The search filter compares stored instants (`_mhmrentiva_start_ts` /
 * `_end_ts`) with the searched window. Its bounds came from
 * strtotime( $date . ' 00:00:00' ), which reads the date in PHP's zone (UTC
 * under WordPress), so on an Istanbul site the searched day ran 03:00 to
 * 02:59 the next morning.
 *
 * PR S: the window is no longer whole days. It is the booking form's window --
 * pickup date and time to the return date at the same time -- and the search
 * applies the one booking rule (BookingConflicts), buffer included. The
 * fixtures are therefore asked about windows with a time, and the buffer is
 * pinned to 0 so each edge is the booking's own instant. The booking rows now
 * also carry their date and time meta, as every booking path writes them.
 *
 * @covers \MHMRentiva\Admin\Core\QueryHelper::get_availability_subquery
 */
final class QueryHelperSearchWindowTest extends WP_UnitTestCase
{
	use SiteClock;

	public function setUp(): void
	{
		parent::setUp();

		add_filter(
			'option_' . SettingsCore::OPTION_NAME,
			static function ($settings): array {
				$settings = is_array($settings) ? $settings : array();
				$settings['mhmrentiva_booking_buffer_time'] = '0';

				return $settings;
			}
		);
	}

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
		update_post_meta($id, '_mhmrentiva_pickup_date', wp_date('Y-m-d', $start_ts));
		update_post_meta($id, '_mhmrentiva_pickup_time', wp_date('H:i', $start_ts));
		update_post_meta($id, '_mhmrentiva_dropoff_date', wp_date('Y-m-d', $end_ts));
		update_post_meta($id, '_mhmrentiva_dropoff_time', wp_date('H:i', $end_ts));
	}

	/**
	 * Whether the vehicle survives the availability filter for the window.
	 */
	private function is_listed(int $vehicle_id, string $pickup_date, string $return_date, string $time): bool
	{
		global $wpdb;

		$fragment = QueryHelper::get_availability_subquery($pickup_date, $return_date, $time);
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
	 *  - 2027-06-13 01:00 -- just after local midnight;
	 *  - 2027-06-10 23:00 -- returning 23:00 on the 11th, local.
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
	 * Every window sits two hours from the booking's edge, so a zone mix-up
	 * (three hours or more for every zone here) flips one of the pair:
	 *  - ordinary, booked 10 Jun 10:00 -> 11 Jun 10:00 local: a window over
	 *    the 10th collides, one from the 12th is free (was: the 10th and the
	 *    12th as whole days -- same answers);
	 *  - after midnight, booked 13 Jun 01:00 -> 14 Jun 01:00: 11 Jun 23:00 ->
	 *    12 Jun 23:00 ends two hours before it, 12 Jun 03:00 -> 13 Jun 03:00
	 *    reaches it (was: whole day 12 free, day 13 booked -- the edge moved
	 *    onto the local pickup instant);
	 *  - late evening, booked 10 Jun 23:00 -> 11 Jun 23:00: a window from
	 *    12 Jun 01:00 is free, one from 11 Jun 21:00 collides (was: whole day
	 *    12 free, day 11 booked -- the edge moved onto the local return).
	 *
	 * @dataProvider zones
	 */
	public function test_search_window_uses_local_day_bounds(string $zone, string $ordinary, string $after_midnight, string $late_evening): void
	{
		$this->pin_zone($zone);

		$vehicle = $this->vehicle();
		$this->booking($vehicle, self::utc($ordinary), self::utc($ordinary) + DAY_IN_SECONDS);

		$this->assertFalse($this->is_listed($vehicle, '2027-06-09', '2027-06-10', '12:00'), 'A window over the booked day collides.');
		$this->assertTrue($this->is_listed($vehicle, '2027-06-12', '2027-06-13', '10:00'), 'The day after the return is free.');

		$early = $this->vehicle();
		$this->booking($early, self::utc($after_midnight), self::utc($after_midnight) + DAY_IN_SECONDS);

		$this->assertTrue($this->is_listed($early, '2027-06-11', '2027-06-12', '23:00'), 'A return before the local 01:00 pickup is free.');
		$this->assertFalse($this->is_listed($early, '2027-06-12', '2027-06-13', '03:00'), 'A return after it collides.');

		$late = $this->vehicle();
		$this->booking($late, self::utc($late_evening), self::utc($late_evening) + DAY_IN_SECONDS);

		$this->assertTrue($this->is_listed($late, '2027-06-12', '2027-06-13', '01:00'), 'A pickup after the local 23:00 return is free.');
		$this->assertFalse($this->is_listed($late, '2027-06-11', '2027-06-12', '21:00'), 'A pickup before it collides.');
	}

	/**
	 * Called without a time, the window uses the default pickup time at both
	 * ends (m5).
	 */
	public function test_a_dateless_call_uses_the_default_pickup_time(): void
	{
		$this->pin_zone('Europe/Istanbul');
		add_filter('mhmrentiva_default_pickup_time', static fn() => '15:00');

		$vehicle = $this->vehicle();
		// 12 Jun 12:00 -> 14 Jun 12:00, Istanbul.
		$this->booking($vehicle, self::utc('2027-06-12 09:00'), self::utc('2027-06-14 09:00'));

		$this->assertFalse($this->is_listed($vehicle, '2027-06-10', '2027-06-12', ''), '10 Jun 15:00 -> 12 Jun 15:00 reaches the 12:00 pickup.');
		$this->assertTrue($this->is_listed($vehicle, '2027-06-14', '2027-06-16', ''), 'From 14 Jun 15:00 the vehicle is back.');
		$this->assertSame('', QueryHelper::get_availability_subquery('2027-06-10', '2027-06-10'), 'An empty window filters nothing.');
	}

	/**
	 * Review m1: a malformed time must not open the filter -- it falls back to
	 * the default pickup time, as the booking rule's own defaults do.
	 */
	public function test_a_malformed_time_falls_back_to_the_default(): void
	{
		$this->pin_zone('Europe/Istanbul');

		$vehicle = $this->vehicle();
		// 10 Jun 10:00 -> 12 Jun 10:00, Istanbul.
		$this->booking($vehicle, self::utc('2027-06-10 07:00'), self::utc('2027-06-12 07:00'));

		$this->assertFalse($this->is_listed($vehicle, '2027-06-10', '2027-06-11', '25:99'));
		$this->assertFalse($this->is_listed($vehicle, '2027-06-10', '2027-06-11', 'noon'));

		add_filter('mhmrentiva_default_pickup_time', static fn() => 'garbage');
		$this->assertFalse($this->is_listed($vehicle, '2027-06-10', '2027-06-11', ''), 'A broken default falls back to 10:00.');
	}
}
