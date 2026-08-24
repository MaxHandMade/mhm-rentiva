<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Addons;

use MHMRentiva\Admin\Booking\Addons\AddonBooking;
use WP_UnitTestCase;

/**
 * How often each add-on has actually been booked.
 *
 * THE METHOD EXISTED AND RETURNED NOTHING
 * ---------------------------------------
 * get_addon_revenue_report() was already counting add-on usage before this
 * screen needed it, and it could never have worked: its query filtered
 * `post_status IN ('confirmed','completed')`, but a booking's status is not its
 * post status. Status lives in the `_mhmrentiva_status` meta and every booking
 * row is `publish` -- measured on the dev database, where the distribution is
 * 29 publish and 2 auto-draft, with neither of those two values present at all.
 *
 * So the method returned an empty breakdown and 0.0 revenue on every call. It
 * was harmless only because nothing called it; the danger was the name, which
 * promised a revenue report to whoever wired it up next.
 *
 * The first test below pins the defect rather than the fix: it drives the query
 * shape the old code used and asserts it finds nothing, so if someone restores
 * that filter the suite says why it is wrong. The rest assert the repaired
 * behaviour.
 */
final class AddonUsageCountTest extends WP_UnitTestCase {

	private int $gps_id;
	private int $seat_id;

	protected function setUp(): void {
		parent::setUp();

		$this->gps_id  = self::factory()->post->create(
			array( 'post_type' => 'mhmrentiva_addon', 'post_title' => 'GPS', 'post_status' => 'publish' )
		);
		$this->seat_id = self::factory()->post->create(
			array( 'post_type' => 'mhmrentiva_addon', 'post_title' => 'Child seat', 'post_status' => 'publish' )
		);

		// Two confirmed bookings with GPS, one with both, one cancelled.
		$this->create_booking( 'confirmed', array( $this->gps_id => 150.0 ) );
		$this->create_booking( 'completed', array( $this->gps_id => 150.0, $this->seat_id => 100.0 ) );
		$this->create_booking( 'cancelled', array( $this->gps_id => 150.0 ) );
	}

	/**
	 * @param array<int,float> $addons Addon id => price.
	 */
	private function create_booking( string $status, array $addons ): int {
		$booking_id = self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_booking',
				'post_status' => 'publish',
				'post_date'   => '2026-08-01 10:00:00',
			)
		);

		update_post_meta( $booking_id, '_mhmrentiva_status', $status );

		$details = array();
		foreach ( $addons as $addon_id => $price ) {
			$details[] = array(
				'id'    => $addon_id,
				'title' => get_the_title( $addon_id ),
				'price' => $price,
			);
		}
		update_post_meta( $booking_id, '_mhmrentiva_addon_details', $details );

		return $booking_id;
	}

	private function report(): array {
		return AddonBooking::get_addon_revenue_report(
			new \DateTime( '2026-07-01 00:00:00' ),
			new \DateTime( '2026-09-01 00:00:00' )
		);
	}

	/**
	 * The defect, stated as a test. Bookings do not carry their status in
	 * post_status, so the old filter matched nothing -- and still matches
	 * nothing, which is why the repaired method may not go back to it.
	 */
	public function test_no_booking_carries_its_status_in_post_status(): void {
		global $wpdb;

		$rows = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status IN ( 'confirmed', 'completed' )",
				'mhmrentiva_booking'
			)
		);

		$this->assertSame(
			0,
			$rows,
			'If this ever passes non-zero, booking status moved to post_status and this feature needs revisiting.'
		);
	}

	public function test_it_counts_each_add_on_across_bookings(): void {
		$stats = $this->report()['addon_stats'];

		$this->assertSame( 2, $stats[ $this->gps_id ]['count'], 'GPS is on two countable bookings.' );
		$this->assertSame( 1, $stats[ $this->seat_id ]['count'] );
	}

	/**
	 * A cancelled booking is not a sale. The old query meant to exclude it and
	 * excluded everything instead; the repair has to keep the intent.
	 */
	public function test_a_cancelled_booking_does_not_count(): void {
		$stats = $this->report()['addon_stats'];

		$this->assertSame( 2, $stats[ $this->gps_id ]['count'], 'The third GPS booking is cancelled.' );
	}

	public function test_it_totals_the_revenue(): void {
		$report = $this->report();

		$this->assertEqualsWithDelta( 400.0, $report['total_revenue'], 0.01 );
	}

	public function test_a_booking_outside_the_window_is_excluded(): void {
		$this->create_booking( 'confirmed', array( $this->gps_id => 150.0 ) );
		$old = self::factory()->post->create(
			array( 'post_type' => 'mhmrentiva_booking', 'post_status' => 'publish', 'post_date' => '2020-01-01 10:00:00' )
		);
		update_post_meta( $old, '_mhmrentiva_status', 'confirmed' );
		update_post_meta(
			$old,
			'_mhmrentiva_addon_details',
			array( array( 'id' => $this->gps_id, 'title' => 'GPS', 'price' => 999.0 ) )
		);

		$this->assertSame( 3, $this->report()['addon_stats'][ $this->gps_id ]['count'] );
	}

	/** An add-on nobody booked simply does not appear. */
	public function test_an_unbooked_add_on_is_absent_rather_than_zero(): void {
		$unused = self::factory()->post->create(
			array( 'post_type' => 'mhmrentiva_addon', 'post_status' => 'publish' )
		);

		$this->assertArrayNotHasKey( $unused, $this->report()['addon_stats'] );
	}
}
