<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Payment\WooCommerce;

use MHMRentiva\Admin\Booking\Core\Status;
use MHMRentiva\Tests\Support\WooCommerceFixtures;
use WP_UnitTestCase;

/**
 * Measured before this task: the on-hold branch writes booking-level status
 * from a single order without the _mhmrentiva_is_remaining_payment ownership
 * check its completed/processing neighbours carry. A customer choosing bank
 * transfer for the REMAINING payment therefore demoted a deposit-paid booking
 * to pending/pending -- which is exactly the pair AutoCancel sweep #1 selects.
 *
 * The booking id is read from ITEM meta (WooCommerceBridge:1301), never the
 * order's. WooCommerceFixtures::create_paid_order_for_booking() wires the
 * order-level copy only -- its own docblock says so -- so every test here
 * wires the item-level copy itself via wire_line_item_booking_id(), the same
 * way RefundSingleWriterTest does. Skipping that wiring does not fail these
 * tests; it makes them pass for the wrong reason, because
 * handle_order_status_change() never resolves a booking id at all and the
 * on-hold case never runs.
 */
final class OnHoldDoesNotDemoteDepositBookingTest extends WP_UnitTestCase
{
    use WooCommerceFixtures;

    public function setUp(): void
    {
        parent::setUp();

        $this->require_woocommerce();
    }

    public function test_a_remaining_order_going_on_hold_leaves_a_paid_deposit_booking_alone(): void
    {
        $booking_id = self::factory()->post->create( array( 'post_type' => 'mhmrentiva_booking' ) );
        update_post_meta( $booking_id, '_mhmrentiva_payment_type', 'deposit' );
        update_post_meta( $booking_id, '_mhmrentiva_payment_status', 'paid' );
        update_post_meta( $booking_id, '_mhmrentiva_status', Status::CONFIRMED );

        $remaining = $this->create_paid_order_for_booking( $booking_id, '700' );
        $this->wire_line_item_booking_id( $remaining, $booking_id );
        $remaining->update_meta_data( '_mhmrentiva_is_remaining_payment', '1' );
        $remaining->save();

        $remaining->update_status( 'on-hold' );

        $this->assertSame(
            'paid',
            get_post_meta( $booking_id, '_mhmrentiva_payment_status', true ),
            'A REMAINING order going on-hold must not touch a deposit already paid.'
        );
        $this->assertSame( Status::CONFIRMED, Status::get( $booking_id ) );
    }

    public function test_the_deposit_order_going_on_hold_still_moves_the_booking(): void
    {
        $booking_id = self::factory()->post->create( array( 'post_type' => 'mhmrentiva_booking' ) );
        update_post_meta( $booking_id, '_mhmrentiva_payment_type', 'deposit' );
        update_post_meta( $booking_id, '_mhmrentiva_status', Status::PENDING );

        $deposit = $this->create_paid_order_for_booking( $booking_id, '300' );
        $this->wire_line_item_booking_id( $deposit, $booking_id );

        $deposit->update_status( 'on-hold' );

        $this->assertSame(
            'pending',
            get_post_meta( $booking_id, '_mhmrentiva_payment_status', true ),
            'The negative control: this branch must still work for the order that owns the booking.'
        );
    }

    /**
     * WooCommerceFixtures::create_paid_order_for_booking() wires
     * `_mhmrentiva_booking_id` onto the ORDER only. Production also wires the
     * same key onto the order's LINE ITEM (WooCommerceBridge.php :911, :1026;
     * RemainingPaymentHandler.php :256), and handle_order_status_change()
     * reads ONLY the item copy (:1301). Wired locally to this test class
     * rather than in the shared fixture, mirroring
     * RefundSingleWriterTest::wire_line_item_booking_id() -- adding it to the
     * shared trait changes behaviour for every one of its other callers.
     */
    private function wire_line_item_booking_id( \WC_Order $order, int $booking_id ): void
    {
        foreach ( $order->get_items() as $item ) {
            $item->add_meta_data( '_mhmrentiva_booking_id', $booking_id, true );
            $item->save();
        }
    }
}
