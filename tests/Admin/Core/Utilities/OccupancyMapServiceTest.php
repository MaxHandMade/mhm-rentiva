<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Core\Utilities;

use MHMRentiva\Admin\Core\Utilities\OccupancyMapService;
use MHMRentiva\Admin\Vehicle\ListTable\VehicleColumns;
use WP_UnitTestCase;

/**
 * OccupancyMapService is the single raw-data occupancy source Faz 2's view
 * engine and the fleet occupancy matrix will both consume. It generalizes
 * VehicleColumns::get_week_bookings_map() (window-agnostic instead of
 * hard-coded to "today .. today+6") and changes ONE piece of behavior on
 * purpose: an expired-deadline `pending` booking no longer counts as
 * occupied (see test_expired_pending_is_dropped_from_the_week_window).
 *
 * @covers \MHMRentiva\Admin\Core\Utilities\OccupancyMapService
 */
final class OccupancyMapServiceTest extends WP_UnitTestCase
{
	public function setUp(): void
	{
		parent::setUp();
		OccupancyMapService::reset_memo();
	}

	public function tearDown(): void
	{
		OccupancyMapService::reset_memo();
		OccupancyMapService::invalidate();
		parent::tearDown();
	}

	private function make_booking( int $vehicle_id, string $status, string $pickup, string $dropoff, ?string $deadline = null ): int
	{
		$booking = self::factory()->post->create( array( 'post_type' => 'mhmrentiva_booking' ) );
		update_post_meta( $booking, '_mhmrentiva_status', $status );
		update_post_meta( $booking, '_mhmrentiva_vehicle_id', $vehicle_id );
		update_post_meta( $booking, '_mhmrentiva_pickup_date', $pickup );
		update_post_meta( $booking, '_mhmrentiva_dropoff_date', $dropoff );
		if ( null !== $deadline ) {
			update_post_meta( $booking, '_mhmrentiva_payment_deadline', $deadline );
		}

		return $booking;
	}

	/**
	 * A day can hold several bookings with different statuses; get_map()
	 * must preserve all of them raw — precedence reduction is reduce()'s
	 * job, not get_map()'s.
	 */
	public function test_map_shape_holds_two_raw_entries_for_the_same_cell(): void
	{
		$vehicle = self::factory()->post->create( array( 'post_type' => 'mhmrentiva_vehicle' ) );
		$day     = gmdate( 'Y-m-d', strtotime( '+2 days' ) );

		$booking_a = $this->make_booking( $vehicle, 'pending', $day, $day );
		$booking_b = $this->make_booking( $vehicle, 'confirmed', $day, $day );

		$map     = OccupancyMapService::get_map( gmdate( 'Y-m-d' ), gmdate( 'Y-m-d', strtotime( '+6 days' ) ) );
		$entries = $map[ $vehicle ][ $day ] ?? array();

		$this->assertCount( 2, $entries );
		$ids = array_column( $entries, 'booking_id' );
		sort( $ids );
		$expected = array( $booking_a, $booking_b );
		sort( $expected );
		$this->assertSame( $expected, $ids );

		$statuses = array_column( $entries, 'status' );
		sort( $statuses );
		$this->assertSame( array( 'confirmed', 'pending' ), $statuses );
	}

	public function test_reduce_applies_the_completed_pending_confirmed_in_progress_precedence(): void
	{
		$this->assertSame(
			'in_progress',
			OccupancyMapService::reduce(
				array(
					array( 'booking_id' => 1, 'status' => 'completed' ),
					array( 'booking_id' => 2, 'status' => 'in_progress' ),
				)
			)
		);

		$this->assertSame(
			'pending',
			OccupancyMapService::reduce(
				array( array( 'booking_id' => 1, 'status' => 'pending' ) )
			)
		);

		$this->assertSame(
			'confirmed',
			OccupancyMapService::reduce(
				array(
					array( 'booking_id' => 1, 'status' => 'pending' ),
					array( 'booking_id' => 2, 'status' => 'confirmed' ),
				)
			)
		);
	}

	public function test_reduce_of_an_empty_list_is_the_empty_string(): void
	{
		$this->assertSame( '', OccupancyMapService::reduce( array() ) );
	}

	/**
	 * The deadline exemption is part of the "counts as occupied" definition
	 * itself, not a filter layered on top — an expired pending booking must
	 * be entirely absent from the raw map, in every window that includes it.
	 * This deliberately CHANGES the old week-map behavior, which had no
	 * exemption at all.
	 */
	public function test_expired_pending_is_dropped_from_the_week_window(): void
	{
		$vehicle = self::factory()->post->create( array( 'post_type' => 'mhmrentiva_vehicle' ) );
		$day     = gmdate( 'Y-m-d', strtotime( '+1 day' ) );

		$expired = $this->make_booking( $vehicle, 'pending', $day, $day, gmdate( 'Y-m-d H:i:s', strtotime( '-1 hour' ) ) );

		$map = OccupancyMapService::get_map( gmdate( 'Y-m-d' ), gmdate( 'Y-m-d', strtotime( '+6 days' ) ) );

		$this->assertArrayNotHasKey( $day, $map[ $vehicle ] ?? array() );
	}

	public function test_unexpired_pending_is_present_in_the_week_window(): void
	{
		$vehicle = self::factory()->post->create( array( 'post_type' => 'mhmrentiva_vehicle' ) );
		$day     = gmdate( 'Y-m-d', strtotime( '+1 day' ) );

		$booking = $this->make_booking( $vehicle, 'pending', $day, $day, gmdate( 'Y-m-d H:i:s', strtotime( '+1 hour' ) ) );

		$map = OccupancyMapService::get_map( gmdate( 'Y-m-d' ), gmdate( 'Y-m-d', strtotime( '+6 days' ) ) );

		$this->assertArrayHasKey( $day, $map[ $vehicle ] );
		$this->assertSame( $booking, $map[ $vehicle ][ $day ][0]['booking_id'] );
	}

	/**
	 * Fix round 1, Finding 1: a booking with NEITHER `_mhmrentiva_status`
	 * NOR the legacy `_mhmrentiva_booking_status` set to a non-empty value
	 * must resolve to 'pending' -- the same fold
	 * BookingColumns::apply_status_filter() and the canonical KPI
	 * enumeration already apply -- rather than being silently excluded from
	 * the map entirely (NULL status previously failed the `status IN (...)`
	 * restriction).
	 */
	public function test_status_less_booking_resolves_to_pending_and_is_present(): void
	{
		$vehicle = self::factory()->post->create( array( 'post_type' => 'mhmrentiva_vehicle' ) );
		$day     = gmdate( 'Y-m-d', strtotime( '+1 day' ) );

		$booking = self::factory()->post->create( array( 'post_type' => 'mhmrentiva_booking' ) );
		update_post_meta( $booking, '_mhmrentiva_vehicle_id', $vehicle );
		update_post_meta( $booking, '_mhmrentiva_pickup_date', $day );
		update_post_meta( $booking, '_mhmrentiva_dropoff_date', $day );
		// Deliberately no _mhmrentiva_status / _mhmrentiva_booking_status meta at all.

		$map = OccupancyMapService::get_map( gmdate( 'Y-m-d' ), gmdate( 'Y-m-d', strtotime( '+6 days' ) ) );

		$this->assertArrayHasKey( $day, $map[ $vehicle ] ?? array() );
		$this->assertSame( $booking, $map[ $vehicle ][ $day ][0]['booking_id'] );
		$this->assertSame( 'pending', $map[ $vehicle ][ $day ][0]['status'] );
	}

	/**
	 * Same fold, but with the status meta present and explicitly empty
	 * string rather than absent -- the other branch of the COALESCE chain.
	 */
	public function test_empty_string_status_resolves_to_pending_and_is_present(): void
	{
		$vehicle = self::factory()->post->create( array( 'post_type' => 'mhmrentiva_vehicle' ) );
		$day     = gmdate( 'Y-m-d', strtotime( '+1 day' ) );

		$booking = $this->make_booking( $vehicle, '', $day, $day );

		$map = OccupancyMapService::get_map( gmdate( 'Y-m-d' ), gmdate( 'Y-m-d', strtotime( '+6 days' ) ) );

		$this->assertArrayHasKey( $day, $map[ $vehicle ] ?? array() );
		$this->assertSame( 'pending', $map[ $vehicle ][ $day ][0]['status'] );
	}

	/**
	 * The deadline exemption applies to the RESOLVED status (which defaults
	 * to 'pending'), not the raw (absent) column -- a status-less booking
	 * with an expired deadline must still be dropped, exactly like an
	 * explicit 'pending' with an expired deadline already was.
	 */
	public function test_status_less_booking_with_expired_deadline_is_dropped(): void
	{
		$vehicle = self::factory()->post->create( array( 'post_type' => 'mhmrentiva_vehicle' ) );
		$day     = gmdate( 'Y-m-d', strtotime( '+1 day' ) );

		$booking = self::factory()->post->create( array( 'post_type' => 'mhmrentiva_booking' ) );
		update_post_meta( $booking, '_mhmrentiva_vehicle_id', $vehicle );
		update_post_meta( $booking, '_mhmrentiva_pickup_date', $day );
		update_post_meta( $booking, '_mhmrentiva_dropoff_date', $day );
		update_post_meta( $booking, '_mhmrentiva_payment_deadline', gmdate( 'Y-m-d H:i:s', strtotime( '-1 hour' ) ) );

		$map = OccupancyMapService::get_map( gmdate( 'Y-m-d' ), gmdate( 'Y-m-d', strtotime( '+6 days' ) ) );

		$this->assertArrayNotHasKey( $day, $map[ $vehicle ] ?? array() );
	}

	/**
	 * Canonical-change pin on the Vehicles side (the week strip):
	 * a status-less booking must paint as 'is-pending', matching the fold
	 * this test file's get_map()-level tests establish above.
	 */
	public function test_week_strip_marks_a_status_less_booking_as_pending(): void
	{
		$vehicle = self::factory()->post->create( array( 'post_type' => 'mhmrentiva_vehicle' ) );

		$today = current_time( 'Y-m-d' );

		$booking = self::factory()->post->create( array( 'post_type' => 'mhmrentiva_booking' ) );
		update_post_meta( $booking, '_mhmrentiva_vehicle_id', $vehicle );
		update_post_meta( $booking, '_mhmrentiva_pickup_date', $today );
		update_post_meta( $booking, '_mhmrentiva_dropoff_date', $today );

		$strip = VehicleColumns::get_week_strip( $vehicle );

		$this->assertSame( 'is-pending', $strip[0]['class'] );
	}

	/**
	 * Same canonical-change pin, negative case: a status-less booking whose
	 * deadline has already passed must NOT paint (the strip shows the day
	 * free), exactly like an explicit expired 'pending' already didn't.
	 */
	public function test_week_strip_does_not_mark_a_status_less_expired_deadline_booking(): void
	{
		$vehicle = self::factory()->post->create( array( 'post_type' => 'mhmrentiva_vehicle' ) );

		$today = current_time( 'Y-m-d' );

		$booking = self::factory()->post->create( array( 'post_type' => 'mhmrentiva_booking' ) );
		update_post_meta( $booking, '_mhmrentiva_vehicle_id', $vehicle );
		update_post_meta( $booking, '_mhmrentiva_pickup_date', $today );
		update_post_meta( $booking, '_mhmrentiva_dropoff_date', $today );
		update_post_meta( $booking, '_mhmrentiva_payment_deadline', gmdate( 'Y-m-d H:i:s', strtotime( '-1 hour' ) ) );

		$strip = VehicleColumns::get_week_strip( $vehicle );

		$this->assertSame( 'is-free', $strip[0]['class'] );
	}

	public function test_transient_key_matches_the_invalidation_pattern(): void
	{
		global $wpdb;

		$start = gmdate( 'Y-m-d' );
		$end   = gmdate( 'Y-m-d', strtotime( '+6 days' ) );

		OccupancyMapService::get_map( $start, $end );

		$row = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
				'_transient_mhmrentiva_vehicle_stats_occmap_' . $start . '_' . $end
			)
		);
		$this->assertNotNull( $row, 'get_map() must write a transient under the mhmrentiva_vehicle_stats_ prefix.' );

		OccupancyMapService::invalidate();

		$row_after = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
				'_transient_mhmrentiva_vehicle_stats_occmap_' . $start . '_' . $end
			)
		);
		$this->assertNull( $row_after, 'invalidate() must delete the occmap transient.' );
	}

	/**
	 * The old service memoized a single ?array with no window key, which
	 * poisons mixed windows in one request (the 7-day strip and a month
	 * matrix would otherwise trample each other's cached result).
	 */
	public function test_memo_does_not_cross_poison_between_windows(): void
	{
		$vehicle = self::factory()->post->create( array( 'post_type' => 'mhmrentiva_vehicle' ) );

		$day_a = gmdate( 'Y-m-d', strtotime( '+1 day' ) );
		$this->make_booking( $vehicle, 'confirmed', $day_a, $day_a );

		$map_a = OccupancyMapService::get_map( gmdate( 'Y-m-d' ), gmdate( 'Y-m-d', strtotime( '+6 days' ) ) );
		$this->assertArrayHasKey( $day_a, $map_a[ $vehicle ] );

		$day_b = gmdate( 'Y-m-d', strtotime( '+40 days' ) );
		$this->make_booking( $vehicle, 'confirmed', $day_b, $day_b );

		$map_b = OccupancyMapService::get_map(
			gmdate( 'Y-m-d', strtotime( '+30 days' ) ),
			gmdate( 'Y-m-d', strtotime( '+50 days' ) )
		);

		$this->assertArrayNotHasKey( $day_a, $map_b[ $vehicle ] ?? array(), 'Window B must not carry window A\'s days.' );
		$this->assertArrayHasKey( $day_b, $map_b[ $vehicle ] );

		// And window A's own memoized result must still be intact — a second
		// call must not have been re-poisoned by window B running in between.
		$map_a_again = OccupancyMapService::get_map( gmdate( 'Y-m-d' ), gmdate( 'Y-m-d', strtotime( '+6 days' ) ) );
		$this->assertArrayHasKey( $day_a, $map_a_again[ $vehicle ] );
		$this->assertArrayNotHasKey( $day_b, $map_a_again[ $vehicle ] );
	}

	public function test_week_strip_still_marks_booked_and_blocked_days(): void
	{
		$vehicle = self::factory()->post->create( array( 'post_type' => 'mhmrentiva_vehicle' ) );

		$today    = current_time( 'Y-m-d' );
		$tomorrow = gmdate( 'Y-m-d', strtotime( '+1 day', strtotime( $today ) ) );

		$this->make_booking( $vehicle, 'confirmed', $today, $tomorrow );

		$blocked_day = gmdate( 'Y-m-d', strtotime( '+3 days', strtotime( $today ) ) );
		update_post_meta( $vehicle, '_mhmrentiva_blocked_dates', wp_json_encode( array( $blocked_day ) ) );

		$strip = VehicleColumns::get_week_strip( $vehicle );

		$this->assertCount( 7, $strip );
		$this->assertSame( 'is-confirmed', $strip[0]['class'], 'Today is booked (confirmed)' );
		$this->assertSame( 'is-confirmed', $strip[1]['class'], 'Tomorrow is booked (confirmed)' );
		$this->assertSame( 'is-blocked', $strip[3]['class'], 'Blocked day wins' );
		$this->assertSame( 'is-free', $strip[6]['class'], 'Untouched day is free' );
	}
}
