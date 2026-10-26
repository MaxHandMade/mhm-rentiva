<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Helpers;

use MHMRentiva\Admin\Booking\Helpers\BookingConflicts;
use MHMRentiva\Admin\Booking\Helpers\SiteTime;
use MHMRentiva\Admin\Booking\Helpers\Util;
use MHMRentiva\Admin\Core\QueryHelper;
use MHMRentiva\Admin\REST\Availability;
use MHMRentiva\Admin\Settings\Core\SettingsCore;
use MHMRentiva\Admin\Vehicle\Meta\BlockedDatesMetaBox;
use MHMRentiva\Tests\Support\SiteClock;
use WP_UnitTestCase;

/**
 * PR S -- one "booked" rule.
 *
 * Search, the booking paths and the locked count used to answer the same
 * question three different ways: search looked at whole days with no buffer
 * and every status except cancelled/refunded, has_overlap() at the real
 * window with the buffer, the locked count at the window without future
 * `completed` rows. Manually blocked days were seen by none of them.
 *
 * Every edge below is asked of every reader; they must all give the one
 * answer the rule names.
 *
 * @covers \MHMRentiva\Admin\Booking\Helpers\BookingConflicts
 * @covers \MHMRentiva\Admin\Booking\Helpers\Util::has_overlap
 * @covers \MHMRentiva\Admin\Booking\Helpers\Util::count_overlaps_locked
 * @covers \MHMRentiva\Admin\Core\QueryHelper::get_availability_subquery
 */
final class BookingConflictsTest extends WP_UnitTestCase
{
	use SiteClock;

	public function setUp(): void
	{
		parent::setUp();

		// The buffer the edges are written for, whatever the test site stores.
		add_filter(
			'option_' . SettingsCore::OPTION_NAME,
			static function ($settings): array {
				$settings = is_array($settings) ? $settings : array();
				$settings['mhmrentiva_booking_buffer_time'] = '60';

				return $settings;
			}
		);
	}

	private function pin_zone(string $zone): void
	{
		if ('+03:00' === $zone) {
			$this->pin_site_offset(3);
			return;
		}
		$this->pin_site_timezone($zone);
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function zones(): array
	{
		return array(
			'Istanbul'     => array( 'Europe/Istanbul' ),
			'New York'     => array( 'America/New_York' ),
			'Auckland'     => array( 'Pacific/Auckland' ),
			'fixed +03:00' => array( '+03:00' ),
		);
	}

	private function vehicle(array $blocked = array()): int
	{
		$id = (int) self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_vehicle',
				'post_status' => 'publish',
			)
		);
		update_post_meta($id, '_mhmrentiva_vehicle_status', 'active');
		update_post_meta($id, '_mhmrentiva_price_per_day', '1000');
		if (array() !== $blocked) {
			update_post_meta($id, '_mhmrentiva_blocked_dates', wp_json_encode($blocked));
		}

		return $id;
	}

	/**
	 * A booking with dates, times and stored instants -- the shape every
	 * booking path writes today.
	 */
	private function booking(int $vehicle_id, string $status, int $start_ts, int $end_ts): int
	{
		$id = (int) self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_booking',
				'post_status' => 'publish',
			)
		);
		update_post_meta($id, '_mhmrentiva_vehicle_id', $vehicle_id);
		update_post_meta($id, '_mhmrentiva_status', $status);
		update_post_meta($id, '_mhmrentiva_pickup_date', wp_date('Y-m-d', $start_ts));
		update_post_meta($id, '_mhmrentiva_pickup_time', wp_date('H:i', $start_ts));
		update_post_meta($id, '_mhmrentiva_dropoff_date', wp_date('Y-m-d', $end_ts));
		update_post_meta($id, '_mhmrentiva_dropoff_time', wp_date('H:i', $end_ts));
		update_post_meta($id, '_mhmrentiva_start_ts', $start_ts);
		update_post_meta($id, '_mhmrentiva_end_ts', $end_ts);

		return $id;
	}

	private static function local(string $date, string $time): int
	{
		return (int) SiteTime::instant($date, $time);
	}

	/**
	 * Whether the search's availability fragment drops the vehicle.
	 */
	private function search_drops(int $vehicle_id, string $pickup_date, string $return_date, string $time): bool
	{
		global $wpdb;

		$fragment = QueryHelper::get_availability_subquery($pickup_date, $return_date, $time);
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- the fragment is built by QueryHelper.
		$found = $wpdb->get_var($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE ID = %d", $vehicle_id) . $fragment);

		return (int) $found !== $vehicle_id;
	}

	/**
	 * Every reader's answer to "is the vehicle booked for this window?".
	 *
	 * @return array<string, bool>
	 */
	private function answers(int $vehicle_id, string $pickup_date, string $time, string $return_date): array
	{
		$start = self::local($pickup_date, $time);
		$end   = self::local($return_date, $time);

		return array(
			'search fragment'     => $this->search_drops($vehicle_id, $pickup_date, $return_date, $time),
			'booked_vehicle_ids'  => in_array($vehicle_id, BookingConflicts::booked_vehicle_ids($start, $end, array( $vehicle_id )), true),
			'has_overlap'         => Util::has_overlap($vehicle_id, $start, $end),
			'count_overlaps_lock' => (int) Util::count_overlaps_locked($vehicle_id, $start, $end) > 0,
			'is_booked'           => BookingConflicts::is_booked($vehicle_id, $start, $end),
		);
	}

	private function assert_all(bool $expected, int $vehicle_id, string $pickup_date, string $time, string $return_date, string $label): void
	{
		$answers = $this->answers($vehicle_id, $pickup_date, $time, $return_date);

		$this->assertSame(
			array_fill_keys(array_keys($answers), $expected),
			$answers,
			"{$label}: {$pickup_date} {$time} -> {$return_date} {$time}"
		);
	}

	/**
	 * Every edge of the rule, in four zones.
	 *
	 * @dataProvider zones
	 */
	public function test_sql_and_php_agree_on_every_edge(string $zone): void
	{
		$this->pin_zone($zone);

		// Buffer edge: booked 10 May 10:00 -> 12 May 10:00, 60 minutes either side.
		$buffer = $this->vehicle();
		$this->booking($buffer, 'confirmed', self::local('2027-05-10', '10:00'), self::local('2027-05-12', '10:00'));
		$this->assert_all(true, $buffer, '2027-05-12', '10:59', '2027-05-14', 'end + 59 min is still inside the buffer');
		$this->assert_all(false, $buffer, '2027-05-12', '11:00', '2027-05-14', 'end + 60 min is free');
		$this->assert_all(true, $buffer, '2027-05-08', '09:01', '2027-05-10', 'ending 59 min before the start collides');
		$this->assert_all(false, $buffer, '2027-05-08', '09:00', '2027-05-10', 'ending 60 min before the start is free');

		// A blocked day, including the window's last, partial day.
		$blocked = $this->vehicle(array( '2027-12-09' ));
		$this->assert_all(true, $blocked, '2027-12-07', '10:00', '2027-12-09', 'the last partial day is blocked');
		$this->assert_all(false, $blocked, '2027-12-07', '00:00', '2027-12-09', 'a window ending at local midnight does not touch the day');
		$this->assert_all(true, $blocked, '2027-12-09', '00:00', '2027-12-10', 'the blocked day itself');
		$this->assert_all(false, $blocked, '2027-12-10', '00:00', '2027-12-12', 'the days after it');

		// A pending payment past its deadline holds nothing; one still in time does.
		$lapsed = $this->vehicle();
		$id     = $this->booking($lapsed, 'pending_payment', self::local('2027-06-10', '10:00'), self::local('2027-06-12', '10:00'));
		update_post_meta($id, '_mhmrentiva_payment_deadline', gmdate('Y-m-d H:i:s', time() - HOUR_IN_SECONDS));
		$this->assert_all(false, $lapsed, '2027-06-10', '10:00', '2027-06-12', 'an expired pending payment');

		$waiting = $this->vehicle();
		$id      = $this->booking($waiting, 'pending_payment', self::local('2027-06-10', '10:00'), self::local('2027-06-12', '10:00'));
		update_post_meta($id, '_mhmrentiva_payment_deadline', gmdate('Y-m-d H:i:s', time() + HOUR_IN_SECONDS));
		$this->assert_all(true, $waiting, '2027-06-10', '10:00', '2027-06-12', 'a pending payment still in time');

		// Completed but not yet returned holds the vehicle -- on the locked path too.
		$now      = time();
		$running  = $this->vehicle();
		$this->booking($running, 'completed', $now - DAY_IN_SECONDS, $now + 2 * DAY_IN_SECONDS);
		$from = $now + DAY_IN_SECONDS;
		$this->assert_all(true, $running, wp_date('Y-m-d', $from), wp_date('H:i', $from), wp_date('Y-m-d', $from + 2 * DAY_IN_SECONDS), 'completed, returns in the future');

		$returned = $this->vehicle();
		$this->booking($returned, 'completed', $now - 5 * DAY_IN_SECONDS, $now - 3 * DAY_IN_SECONDS);
		$from = $now - 4 * DAY_IN_SECONDS;
		$this->assert_all(false, $returned, wp_date('Y-m-d', $from), wp_date('H:i', $from), wp_date('Y-m-d', $from + 2 * DAY_IN_SECONDS), 'completed and returned');

		// A row without stored instants and with empty-string times: 20 May
		// 10:00 -> 22 May 10:00 by the default time. Margins of two hours,
		// wider than the fallback offset's possible one-hour DST error.
		$stampless = $this->vehicle();
		$id        = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_booking', 'post_status' => 'publish' ));
		update_post_meta($id, '_mhmrentiva_vehicle_id', $stampless);
		update_post_meta($id, '_mhmrentiva_status', 'confirmed');
		update_post_meta($id, '_mhmrentiva_pickup_date', '2027-05-20');
		update_post_meta($id, '_mhmrentiva_dropoff_date', '2027-05-22');
		update_post_meta($id, '_mhmrentiva_pickup_time', '');
		update_post_meta($id, '_mhmrentiva_dropoff_time', '');
		$this->assert_all(true, $stampless, '2027-05-22', '08:00', '2027-05-24', 'stamp-less, before its end');
		$this->assert_all(false, $stampless, '2027-05-22', '13:00', '2027-05-24', 'stamp-less, after its end and buffer');
		$this->assert_all(true, $stampless, '2027-05-18', '13:00', '2027-05-20', 'stamp-less, reaching its start');

		// A 120-day window whose 100th day is blocked.
		$long = $this->vehicle(array( '2027-04-10' ));
		$this->assert_all(true, $long, '2027-01-01', '10:00', '2027-05-01', 'day 100 of a 120-day window');
		$open = $this->vehicle();
		$this->assert_all(false, $open, '2027-01-01', '10:00', '2027-05-01', 'the same window, nothing blocked');

		// New York springs forward on 14 March 2027.
		$dst = $this->vehicle(array( '2027-03-14' ));
		$this->assert_all(true, $dst, '2027-03-13', '10:00', '2027-03-14', 'into the DST day');
		$this->assert_all(false, $dst, '2027-03-13', '00:00', '2027-03-14', 'ending at the DST day\'s midnight');
	}

	/**
	 * @dataProvider zones
	 */
	public function test_last_partial_day_counts(string $zone): void
	{
		$this->pin_zone($zone);
		$vehicle = $this->vehicle(array( '2026-12-09' ));

		$this->assertTrue(BookingConflicts::hits_blocked_day($vehicle, self::local('2026-12-07', '10:00'), self::local('2026-12-09', '10:00')));
		$this->assertFalse(BookingConflicts::hits_blocked_day($vehicle, self::local('2026-12-07', '10:00'), self::local('2026-12-09', '00:00')));
		$this->assertSame(array( '2026-12-07', '2026-12-08', '2026-12-09' ), BookingConflicts::window_days(self::local('2026-12-07', '10:00'), self::local('2026-12-09', '10:00')));
		$this->assertSame(array( '2026-12-07', '2026-12-08' ), BookingConflicts::window_days(self::local('2026-12-07', '10:00'), self::local('2026-12-09', '00:00')));
		$this->assertSame(array( '2026-12-09' ), BookingConflicts::blocked_days_in($vehicle, self::local('2026-12-07', '10:00'), self::local('2026-12-09', '10:00')));
		$this->assertSame(array(), BookingConflicts::window_days(self::local('2026-12-09', '10:00'), self::local('2026-12-09', '10:00')), 'An empty window has no days.');
	}

	public function test_blocked_days_read_is_defensive(): void
	{
		$as_array = $this->vehicle();
		update_post_meta($as_array, '_mhmrentiva_blocked_dates', array( '2027-01-02', '2027-01-01', 'junk', 42, '2027-1-3', '2027-01-01' ));
		$this->assertSame(array( '2027-01-01', '2027-01-02' ), BookingConflicts::blocked_days($as_array), 'An array is taken as it is, then cleaned.');

		$broken = $this->vehicle();
		update_post_meta($broken, '_mhmrentiva_blocked_dates', '["2027-01-01",');
		$this->assertSame(array(), BookingConflicts::blocked_days($broken), 'Broken JSON reads as nothing blocked.');

		$mixed = $this->vehicle();
		update_post_meta($mixed, '_mhmrentiva_blocked_dates', wp_json_encode(array( '2027-02-01', array( 'x' ), '2027-02-01x', null, ' 2027-02-02' )));
		$this->assertSame(array( '2027-02-01' ), BookingConflicts::blocked_days($mixed));

		$this->assertSame(array(), BookingConflicts::blocked_days($this->vehicle()));
		$this->assertSame(array( '2027-02-01' ), BlockedDatesMetaBox::get_blocked_dates($mixed), 'The meta box reads through the same rule.');

		// The filter sees the clean list and the vehicle, and its answer is cleaned too.
		$seen = array();
		add_filter(
			'mhmrentiva_vehicle_blocked_days',
			static function ($days, $vehicle_id) use (&$seen) {
				$seen[] = array( $days, $vehicle_id );
				return array_merge($days, array( '2027-03-03', 'nonsense' ));
			},
			10,
			2
		);
		$this->assertSame(array( '2027-02-01', '2027-03-03' ), BookingConflicts::blocked_days($mixed));
		$this->assertSame(array( array( array( '2027-02-01' ), $mixed ) ), $seen);

		add_filter('mhmrentiva_vehicle_blocked_days', static fn() => 'not a list', 20);
		$this->assertSame(array(), BookingConflicts::blocked_days($mixed), 'A filter answering garbage blocks nothing.');
	}

	public function test_a_filter_can_block_a_vehicle_without_meta(): void
	{
		$this->pin_zone('Europe/Istanbul');
		$plain = $this->vehicle();
		add_filter(
			'mhmrentiva_vehicle_blocked_days',
			static fn($days, $vehicle_id) => $vehicle_id === $plain ? array( '2027-08-01' ) : $days,
			10,
			2
		);

		$start = self::local('2027-07-31', '10:00');
		$end   = self::local('2027-08-02', '10:00');

		$this->assertSame(array( $plain ), BookingConflicts::blocked_vehicle_ids($start, $end, array( $plain )));
		$this->assertContains($plain, BookingConflicts::blocked_vehicle_ids($start, $end), 'null means every vehicle, not every vehicle with meta.');
		$this->assertSame(array(), BookingConflicts::blocked_vehicle_ids($start, $end, array()), 'An empty list means none.');
		$this->assertSame(array(), BookingConflicts::booked_vehicle_ids($start, $end, array()));
		$this->assertTrue($this->search_drops($plain, '2027-07-31', '2027-08-02', '10:00'));
	}

	/**
	 * A blocked-days filter that reads post meta (Pro's penalty records do)
	 * must not cost one query per candidate: the candidates' meta is primed
	 * once, so the query count does not grow with the candidate list.
	 */
	public function test_meta_reading_filter_does_not_add_a_query_per_candidate(): void
	{
		global $wpdb;

		$this->pin_zone('Europe/Istanbul');
		add_filter(
			'mhmrentiva_vehicle_blocked_days',
			static function ($days, $vehicle_id) {
				get_post_meta((int) $vehicle_id, '_mhmrentiva_test_penalty_probe', true);

				return $days;
			},
			10,
			2
		);

		$start = self::local('2027-07-31', '10:00');
		$end   = self::local('2027-08-02', '10:00');

		$count = function (array $ids) use ($wpdb, $start, $end): int {
			foreach ($ids as $id) {
				wp_cache_delete($id, 'post_meta');
			}
			$before = $wpdb->num_queries;
			BookingConflicts::blocked_vehicle_ids($start, $end, $ids);

			return $wpdb->num_queries - $before;
		};

		$few  = array();
		$many = array();
		for ($i = 0; $i < 2; $i++) {
			$few[] = $this->vehicle();
		}
		for ($i = 0; $i < 12; $i++) {
			$many[] = $this->vehicle();
		}

		$this->assertSame($count($few), $count($many), 'Twelve candidates cost the same queries as two.');
		$this->assertLessThanOrEqual(3, $count($many));
	}

	public function test_locked_count_includes_future_completed(): void
	{
		$vehicle = $this->vehicle();
		$now     = time();
		$this->booking($vehicle, 'completed', $now - DAY_IN_SECONDS, $now + DAY_IN_SECONDS);

		$this->assertSame(1, Util::count_overlaps_locked($vehicle, $now + HOUR_IN_SECONDS, $now + 2 * HOUR_IN_SECONDS));
		$this->assertSame(1, BookingConflicts::count_booking_overlaps_locked($vehicle, $now + HOUR_IN_SECONDS, $now + 2 * HOUR_IN_SECONDS));
		$this->assertTrue(Util::has_overlap_locked($vehicle, $now + HOUR_IN_SECONDS, $now + 2 * HOUR_IN_SECONDS));
	}

	public function test_locked_count_counts_a_blocked_day_but_the_booking_count_does_not(): void
	{
		$this->pin_zone('Europe/Istanbul');
		$vehicle = $this->vehicle(array( '2027-09-02' ));
		$start   = self::local('2027-09-01', '10:00');
		$end     = self::local('2027-09-03', '10:00');

		$this->assertGreaterThanOrEqual(1, Util::count_overlaps_locked($vehicle, $start, $end));
		$this->assertSame(0, BookingConflicts::count_booking_overlaps_locked($vehicle, $start, $end));
		$this->assertFalse(BookingConflicts::bookings_overlap($vehicle, $start, $end));
	}

	public function test_the_locked_path_reads_blocked_days_past_the_object_cache(): void
	{
		$this->pin_zone('Europe/Istanbul');
		$vehicle = $this->vehicle();
		$start   = self::local('2027-09-01', '10:00');
		$end     = self::local('2027-09-03', '10:00');

		// Fill the meta cache with "nothing blocked", then write behind its back.
		$this->assertSame(array(), BookingConflicts::blocked_days($vehicle));
		global $wpdb;
		$wpdb->insert($wpdb->postmeta, array( 'post_id' => $vehicle, 'meta_key' => '_mhmrentiva_blocked_dates', 'meta_value' => '["2027-09-02"]' ));
		$this->assertSame(array(), BookingConflicts::blocked_days($vehicle), 'Precondition: the cache still says nothing.');

		$this->assertGreaterThanOrEqual(1, Util::count_overlaps_locked($vehicle, $start, $end));
	}

	public function test_customer_paths_refuse_blocked_day(): void
	{
		$this->pin_zone('Europe/Istanbul');
		$vehicle = $this->vehicle(array( '2027-10-11' ));
		$start   = self::local('2027-10-10', '10:00');
		$end     = self::local('2027-10-12', '10:00');

		// Booking form submit, step 1 and the availability AJAX: the same call.
		$result = Util::check_availability_with_alternatives($vehicle, '2027-10-10', '10:00', '2027-10-12', '10:00');
		$this->assertFalse($result['ok']);
		$this->assertStringNotContainsStringIgnoringCase('closed', (string) $result['message'], 'The customer is not told why.');

		// Booking form submit, the locked step; WooCommerce create and checkout use it too.
		$this->assertTrue(Util::has_overlap_locked($vehicle, $start, $end));

		// REST.
		$request = new \WP_REST_Request('GET', '/mhm-rentiva/v1/availability');
		$request->set_param('vehicle_id', $vehicle);
		$request->set_param('pickup_date', '2027-10-10');
		$request->set_param('pickup_time', '10:00');
		$request->set_param('dropoff_date', '2027-10-12');
		$request->set_param('dropoff_time', '10:00');
		$data = Availability::check($request)->get_data();
		$this->assertFalse($data['ok']);
		$this->assertSame('unavailable', $data['code']);
		$this->assertSame(
			__('This vehicle is already booked for the selected dates. Please choose different dates or select another vehicle.', 'mhm-rentiva'),
			$data['message'],
			'Today\'s generic message.'
		);

		// Alternatives never offer a vehicle blocked on the window.
		$free     = $this->vehicle();
		$also_off = $this->vehicle(array( '2027-10-10' ));
		$offered  = wp_list_pluck(Util::get_alternative_vehicles($vehicle, '2027-10-10', '10:00', '2027-10-12', '10:00', 5), 'id');
		$this->assertContains($free, $offered);
		$this->assertNotContains($also_off, $offered);
	}

	public function test_removing_blocked_day_frees_immediately(): void
	{
		$this->pin_zone('Europe/Istanbul');
		$vehicle = $this->vehicle();
		BlockedDatesMetaBox::toggle_blocked_date($vehicle, '2027-11-11');

		$first = Util::check_availability($vehicle, '2027-11-10', '10:00', '2027-11-12', '10:00');
		$this->assertFalse($first['ok'], 'Precondition: "booked" is now cached.');

		BlockedDatesMetaBox::toggle_blocked_date($vehicle, '2027-11-11');

		// Same request, no cache flush: the answer cached a moment ago is not served.
		$this->assertTrue(Util::check_availability($vehicle, '2027-11-10', '10:00', '2027-11-12', '10:00')['ok']);
	}
}
