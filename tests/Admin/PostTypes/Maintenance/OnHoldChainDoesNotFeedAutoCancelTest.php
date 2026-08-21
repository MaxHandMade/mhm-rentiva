<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\PostTypes\Maintenance;

use MHMRentiva\Admin\Booking\Core\Status;
use MHMRentiva\Admin\PostTypes\Maintenance\AutoCancel;
use MHMRentiva\Admin\Settings\Core\SettingsCore;
use MHMRentiva\Tests\Support\WooCommerceFixtures;
use WP_UnitTestCase;

/**
 * The far end of the chain OnHoldDoesNotDemoteDepositBookingTest fixes at the
 * source: a deposit is paid by card (booking confirmed/paid), the customer
 * then opens the remaining-payment link and picks bank transfer (REMAINING
 * order pending -> on-hold). Before the ownership check, that demoted the
 * booking to pending/pending -- exactly the payment_status/status pair
 * AutoCancel::run()'s first sweep selects -- so the cron cancelled a healthy
 * booking and its paid deposit order with it.
 *
 * The booking is backdated past the payment-deadline window so sweep #1's
 * date_query is satisfied; this is load-bearing, not decoration. Without it,
 * a freshly-created booking would be excluded by the date filter alone and
 * this test would pass whether or not the on-hold fix regressed, which is
 * exactly the falsely-green shape this project has hit before.
 */
final class OnHoldChainDoesNotFeedAutoCancelTest extends WP_UnitTestCase
{
    use WooCommerceFixtures;

    public function setUp(): void
    {
        parent::setUp();

        $this->require_woocommerce();
    }

    public function test_a_paid_deposit_booking_survives_auto_cancel_after_its_remaining_order_goes_on_hold(): void
    {
        $booking_id = self::factory()->post->create( array(
            'post_type' => 'mhmrentiva_booking',
            // 2 hours ago: well past the 30-minute default payment deadline
            // sweep #1 uses, so the booking IS date-eligible for that sweep.
            // Only the payment_status/status meta_query is left to save it.
            'post_date' => gmdate( 'Y-m-d H:i:s', time() - 2 * HOUR_IN_SECONDS ),
        ) );
        update_post_meta( $booking_id, '_mhmrentiva_payment_type', 'deposit' );
        update_post_meta( $booking_id, '_mhmrentiva_payment_status', 'paid' );
        update_post_meta( $booking_id, '_mhmrentiva_status', Status::CONFIRMED );

        $deposit = $this->create_paid_order_for_booking( $booking_id, '300' );
        $this->wire_line_item_booking_id( $deposit, $booking_id );

        $remaining = $this->create_paid_order_for_booking( $booking_id, '700' );
        update_post_meta( $booking_id, '_mhmrentiva_remaining_order_id', $remaining->get_id() );
        $this->wire_line_item_booking_id( $remaining, $booking_id );
        $remaining->update_meta_data( '_mhmrentiva_is_remaining_payment', '1' );
        $remaining->save();

        $remaining->update_status( 'on-hold' );

        // Sanity check: if this fails, the rest of the assertions below prove
        // nothing about the chain -- they would just be re-testing AutoCancel's
        // own meta_query filters against data the on-hold branch never touched.
        self::assertSame(
            'paid',
            get_post_meta( $booking_id, '_mhmrentiva_payment_status', true ),
            'Setup sanity check: the on-hold fix must keep the booking paid before AutoCancel even runs.'
        );

        SettingsCore::set( 'mhmrentiva_booking_auto_cancel_enabled', '1' );

        AutoCancel::run();

        $this->assertNotSame(
            'cancelled',
            get_post_meta( $booking_id, '_mhmrentiva_status', true ),
            'AutoCancel sweep #1 selects payment_status/status "pending" pairs -- this booking must not be one.'
        );

        $deposit_after = wc_get_order( $deposit->get_id() );
        $this->assertSame( 'processing', $deposit_after->get_status() );
    }

    /**
     * See OnHoldDoesNotDemoteDepositBookingTest for the full rationale: the
     * shared fixture wires `_mhmrentiva_booking_id` onto the ORDER only, and
     * handle_order_status_change() reads ONLY the item-level copy.
     */
    private function wire_line_item_booking_id( \WC_Order $order, int $booking_id ): void
    {
        foreach ( $order->get_items() as $item ) {
            $item->add_meta_data( '_mhmrentiva_booking_id', $booking_id, true );
            $item->save();
        }
    }
}
