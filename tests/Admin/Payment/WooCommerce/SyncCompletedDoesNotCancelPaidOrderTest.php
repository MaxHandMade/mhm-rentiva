<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Payment\WooCommerce;

use MHMRentiva\Admin\Booking\Core\Status;
use MHMRentiva\Tests\Support\WooCommerceFixtures;
use WP_UnitTestCase;

/**
 * Measured before this task: sync_completed_to_wc() is the fourth
 * order-cancelling caller in WooCommerceBridge, and unlike the other three
 * (handle_order_status_change()'s cancelled/failed, on-hold and refunded
 * branches) it carried no "was this order ever paid" guard at all. It fires
 * on `mhmrentiva_booking_status_changed` for ANY surface's cancellation
 * (WooCommerceBridge::register() :67) and, before this task, unconditionally
 * cancelled whatever order resolve_wc_order_id() found -- undoing Task 6's
 * fix one hop later: cancel the unpaid remainder -> Task 6 leaves the
 * booking CONFIRMED -> but the booking-level status write that DOES survive
 * (or any other cancellation route) still reached sync_completed_to_wc, which
 * cancelled the deposit order Task 6 had just protected, with no refund
 * anywhere.
 *
 * The question this method asks is order-level, not booking-level:
 * resolve_wc_order_id() resolves exactly ONE order (`_mhmrentiva_woocommerce_order_id`),
 * and the question is "has THIS order ever been paid", not "does some
 * sibling still hold money" (that is Task 6's has_paid_sibling_order(),
 * which this task does not call, change, or copy). Both questions are
 * expressed against the same authority: PaymentState::forBooking()->orders(),
 * the set of a booking's orders whose money actually arrived
 * (get_date_paid() !== null -- PaymentState.php :153-155).
 *
 * Driven through Status::update_status() directly (not
 * CancellationHandler::cancel_booking()): the latter also calls
 * process_refund() procedurally, which is not what this task is about and
 * would make failures here ambiguous between "the guard is missing" and "a
 * refund side effect changed the order". process_refund() is never invoked
 * by the mhmrentiva_booking_status_changed action itself -- it is a plain
 * method call inside cancel_booking(), not a hook listener -- so driving the
 * hook directly does not touch it.
 */
final class SyncCompletedDoesNotCancelPaidOrderTest extends WP_UnitTestCase
{
    use WooCommerceFixtures;

    public function setUp(): void
    {
        parent::setUp();

        $this->require_woocommerce();
    }

    public function test_cancelling_the_booking_does_not_cancel_a_paid_order(): void
    {
        $booking_id = self::factory()->post->create( array( 'post_type' => 'mhmrentiva_booking' ) );
        update_post_meta( $booking_id, '_mhmrentiva_payment_type', 'full' );
        update_post_meta( $booking_id, '_mhmrentiva_payment_status', 'paid' );
        update_post_meta( $booking_id, '_mhmrentiva_status', Status::CONFIRMED );

        // First (and only) call for this booking id -- wires
        // _mhmrentiva_woocommerce_order_id, which is the sole key
        // resolve_wc_order_id() needs; sync_completed_to_wc() never reads
        // item-level meta the way handle_order_status_change() does.
        $order = $this->create_paid_order_for_booking( $booking_id, '300' );

        Status::update_status( $booking_id, Status::CANCELLED, 1 );

        $fresh = wc_get_order( $order->get_id() );

        $this->assertSame(
            'processing',
            $fresh->get_status(),
            'A paid order must not be cancelled just because the booking was cancelled from some other surface -- that undoes Task 6 one hop later, with no refund anywhere.'
        );

        $this->assertSame(
            Status::CANCELLED,
            Status::get( $booking_id ),
            'The booking-level transition itself must still succeed; only the WC order write is guarded.'
        );
    }

    /**
     * Negative control: an order that was NEVER paid must still be cancelled
     * when the booking is. Without this, a predicate that always answers
     * "this order was paid" would make the positive assertion above pass for
     * the wrong reason.
     *
     * This also ASSERTS the feedback loop the controller correction flagged,
     * rather than merely tracing it by hand: $order->update_status('cancelled')
     * fires WooCommerce's own woocommerce_order_status_changed, reaching Task
     * 6's cancelled/failed branch in handle_order_status_change(). That
     * branch reads the booking id from LINE ITEM meta (WooCommerceBridge.php
     * :1301), wired here (unlike the positive test above, which never needs
     * it) precisely so the re-entrant call resolves a real booking id instead
     * of no-oping on one it can never see. has_paid_sibling_order() is false
     * for this single, unpaid order, so that branch calls
     * Status::update_status($booking_id, 'cancelled', ...) again -- and that
     * call self-terminates via Status::can_transition('cancelled','cancelled')
     * returning false (the same loop-safety guard sync_completed_to_wc()'s own
     * docblock notes for its 'completed' arm), rather than fighting the status
     * this test already set. The final assertion below is what proves that:
     * if the composition of the two guards ever stopped self-terminating, the
     * booking would come out of this call in something other than CANCELLED.
     */
    public function test_cancelling_the_booking_still_cancels_an_unpaid_order(): void
    {
        $booking_id = self::factory()->post->create( array( 'post_type' => 'mhmrentiva_booking' ) );
        update_post_meta( $booking_id, '_mhmrentiva_payment_type', 'full' );
        update_post_meta( $booking_id, '_mhmrentiva_status', Status::PENDING );

        $product = $this->ensure_booking_product( '300' );

        $order = wc_create_order( array( 'status' => 'pending' ) );
        $item  = new \WC_Order_Item_Product();
        $item->set_product( $product );
        $item->set_quantity( 1 );
        $item->set_subtotal( 300.0 );
        $item->set_total( 300.0 );
        $order->add_item( $item );
        $order->calculate_totals();
        $order->save();

        update_post_meta( $booking_id, '_mhmrentiva_woocommerce_order_id', $order->get_id() );
        $this->wire_line_item_booking_id( $order, $booking_id );

        Status::update_status( $booking_id, Status::CANCELLED, 1 );

        $fresh = wc_get_order( $order->get_id() );

        $this->assertSame(
            'cancelled',
            $fresh->get_status(),
            'The negative control: an order that was never paid must still be cancelled when the booking is.'
        );

        $this->assertSame(
            Status::CANCELLED,
            Status::get( $booking_id ),
            'The re-entrant call from handle_order_status_change() must self-terminate, not fight the status this test already set.'
        );
    }

    /**
     * Same shape as CancelledOrderDoesNotCancelPaidBookingTest's private
     * method of the same name -- kept local rather than added to the shared
     * trait, for the same reason that test class gives: only a test that
     * specifically needs handle_order_status_change() to resolve a booking id
     * from an order it built by hand needs this at all.
     */
    private function wire_line_item_booking_id( \WC_Order $order, int $booking_id ): void
    {
        foreach ( $order->get_items() as $item ) {
            $item->add_meta_data( '_mhmrentiva_booking_id', $booking_id, true );
            $item->save();
        }
    }
}
