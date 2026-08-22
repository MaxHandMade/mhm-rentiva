<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Payment\WooCommerce;

use MHMRentiva\Admin\Booking\Core\Status;
use MHMRentiva\Tests\Support\WooCommerceFixtures;
use WP_UnitTestCase;

/**
 * Measured before this task: the cancelled/failed branch writes booking-level
 * status from a single order, the same defect the on-hold branch had before
 * OnHoldDoesNotDemoteDepositBookingTest. A deposit booking is two WooCommerce
 * orders (deposit + remaining); cancelling the unpaid remainder cancelled a
 * booking whose deposit had already been paid, with no refund anywhere --
 * cancelling the collection instrument is not cancelling the debt.
 *
 * The booking id is read from ITEM meta (WooCommerceBridge:1301), never the
 * order's. WooCommerceFixtures::create_paid_order_for_booking() wires the
 * order-level copy only -- its own docblock says so -- so every test here
 * wires the item-level copy itself via wire_line_item_booking_id(), the same
 * way OnHoldDoesNotDemoteDepositBookingTest and RefundSingleWriterTest do.
 * Skipping that wiring does not fail these tests; it makes them pass for the
 * wrong reason, because handle_order_status_change() never resolves a
 * booking id at all and the cancelled/failed case never runs.
 */
final class CancelledOrderDoesNotCancelPaidBookingTest extends WP_UnitTestCase
{
    use WooCommerceFixtures;

    public function setUp(): void
    {
        parent::setUp();

        $this->require_woocommerce();
    }

    public function test_cancelling_the_remaining_order_does_not_cancel_a_paid_deposit_booking(): void
    {
        $booking_id = self::factory()->post->create( array( 'post_type' => 'mhmrentiva_booking' ) );
        update_post_meta( $booking_id, '_mhmrentiva_payment_type', 'deposit' );
        update_post_meta( $booking_id, '_mhmrentiva_payment_status', 'paid' );
        update_post_meta( $booking_id, '_mhmrentiva_status', Status::CONFIRMED );
        update_post_meta( $booking_id, '_mhmrentiva_remaining_amount', 70000 );

        // First call wires _mhmrentiva_woocommerce_order_id -- this is the
        // deposit order, already paid, and must stay untouched throughout.
        $deposit = $this->create_paid_order_for_booking( $booking_id, '300' );

        // Second call for the same booking id does NOT touch the primary
        // order-id key (fixture docblock); the remaining-order link is
        // production's RemainingPaymentHandler::create() job, wired here by
        // hand.
        $remaining = $this->create_paid_order_for_booking( $booking_id, '700' );
        $this->wire_line_item_booking_id( $remaining, $booking_id );
        $remaining->update_meta_data( '_mhmrentiva_is_remaining_payment', '1' );
        $remaining->save();
        update_post_meta( $booking_id, '_mhmrentiva_remaining_order_id', $remaining->get_id() );

        $remaining->update_status( 'cancelled' );

        $this->assertSame(
            Status::CONFIRMED,
            Status::get( $booking_id ),
            'Cancelling the unpaid remainder must not cancel a booking whose deposit was already paid.'
        );

        $this->assertSame(
            70000,
            (int) get_post_meta( $booking_id, '_mhmrentiva_remaining_amount', true ),
            'The remaining balance must not be touched by cancelling the collection instrument.'
        );

        $this->assertFalse(
            metadata_exists( 'post', $booking_id, '_mhmrentiva_remaining_order_id' ),
            'The dead order id must be cleared so the operator can issue a new payment link.'
        );
    }

    /**
     * Negative control: a single-order booking (no deposit/remaining split)
     * must still cancel when its only order is cancelled. Without this, a
     * predicate that always answers "a sibling is still holding money" would
     * make the first assertion pass for the wrong reason too.
     */
    public function test_cancelling_the_only_order_still_cancels_the_booking(): void
    {
        $booking_id = self::factory()->post->create( array( 'post_type' => 'mhmrentiva_booking' ) );
        update_post_meta( $booking_id, '_mhmrentiva_payment_type', 'full' );
        update_post_meta( $booking_id, '_mhmrentiva_status', Status::PENDING );

        $order = $this->create_paid_order_for_booking( $booking_id, '300' );
        $this->wire_line_item_booking_id( $order, $booking_id );

        $order->update_status( 'cancelled' );

        $this->assertSame(
            Status::CANCELLED,
            Status::get( $booking_id ),
            'The negative control: this branch must still work for a booking with only one order.'
        );
    }

    /**
     * WooCommerceFixtures::create_paid_order_for_booking() wires
     * `_mhmrentiva_booking_id` onto the ORDER only. Production also wires the
     * same key onto the order's LINE ITEM (WooCommerceBridge.php :911, :1026;
     * RemainingPaymentHandler.php :256), and handle_order_status_change()
     * reads ONLY the item copy (:1301). Wired locally to this test class
     * rather than in the shared fixture, mirroring
     * OnHoldDoesNotDemoteDepositBookingTest::wire_line_item_booking_id().
     */
    private function wire_line_item_booking_id( \WC_Order $order, int $booking_id ): void
    {
        foreach ( $order->get_items() as $item ) {
            $item->add_meta_data( '_mhmrentiva_booking_id', $booking_id, true );
            $item->save();
        }
    }
}
