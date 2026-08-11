<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\ListTable;

use MHMRentiva\Admin\Booking\Core\Status;
use MHMRentiva\Admin\Booking\ListTable\BookingColumns;
use WP_UnitTestCase;

/**
 * "Approve" row action on the bookings list (Faz 2 Task 7) -- the row-level
 * link that fires the new mhmrentiva_approve_booking endpoint. Reuses the
 * native `post_row_actions` slot (the only per-row link location Faz 1a's
 * in-place transform left in place, alongside core's own Edit/Quick Edit/
 * Trash/View), rather than introducing a second link surface.
 */
final class BookingApproveRowActionTest extends WP_UnitTestCase {

	public function test_pending_row_gets_the_approve_action(): void {
		$booking_id = self::factory()->post->create( array( 'post_type' => 'mhmrentiva_booking' ) );
		update_post_meta( $booking_id, '_mhmrentiva_status', Status::PENDING );

		$actions = BookingColumns::add_approve_row_action(
			array( 'edit' => '<a href="#">Edit</a>' ),
			get_post( $booking_id )
		);

		$this->assertArrayHasKey( 'mhmrentiva_approve', $actions );
		$this->assertStringContainsString( 'rv-bkl-approve', $actions['mhmrentiva_approve'] );
		$this->assertStringContainsString( 'data-booking-id="' . $booking_id . '"', $actions['mhmrentiva_approve'] );
		$this->assertArrayHasKey( 'edit', $actions, 'Existing row actions must survive untouched.' );
	}

	public function test_confirmed_row_has_no_approve_action(): void {
		$booking_id = self::factory()->post->create( array( 'post_type' => 'mhmrentiva_booking' ) );
		update_post_meta( $booking_id, '_mhmrentiva_status', Status::CONFIRMED );

		$actions = BookingColumns::add_approve_row_action(
			array( 'edit' => '<a href="#">Edit</a>' ),
			get_post( $booking_id )
		);

		$this->assertArrayNotHasKey( 'mhmrentiva_approve', $actions );
	}

	public function test_status_less_row_resolves_to_pending_and_gets_the_action(): void {
		// Status::get() folds a missing meta value to PENDING (the same
		// canonical fold the chip counts and the occupancy map use) -- the
		// row action must agree with that fold rather than reading the raw
		// meta value itself.
		$booking_id = self::factory()->post->create( array( 'post_type' => 'mhmrentiva_booking' ) );

		$actions = BookingColumns::add_approve_row_action(
			array( 'edit' => '<a href="#">Edit</a>' ),
			get_post( $booking_id )
		);

		$this->assertArrayHasKey( 'mhmrentiva_approve', $actions );
	}

	public function test_non_booking_post_type_is_untouched(): void {
		$post_id = self::factory()->post->create( array( 'post_type' => 'post' ) );

		$actions = BookingColumns::add_approve_row_action(
			array( 'edit' => '<a href="#">Edit</a>' ),
			get_post( $post_id )
		);

		$this->assertArrayNotHasKey( 'mhmrentiva_approve', $actions );
	}
}
