<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\PostTypes\Maintenance;

use MHMRentiva\Admin\Booking\Core\Status;
use MHMRentiva\Admin\Payment\Core\RefundStatus;
use MHMRentiva\Admin\PostTypes\Maintenance\AutoCancel;
use MHMRentiva\Admin\Settings\Core\SettingsCore;
use MHMRentiva\Tests\Support\WooCommerceFixtures;
use WP_UnitTestCase;

/**
 * K6: no unattended path moves money. A paid order sitting inside
 * AutoCancel's candidate set is either a data inconsistency (booking meta
 * says pending/pending while WooCommerce says paid) or a real refund
 * obligation -- and the sweep's own trigger can itself be the bug, exactly
 * as OnHoldChainDoesNotFeedAutoCancelTest proved for a related path. So
 * instead of cancelling the booking and its order, the sweep parks the
 * booking in needs_review and returns, leaving the order and its money
 * untouched for a human to act on.
 */
final class AutoCancelLeavesPaidMoneyAloneTest extends WP_UnitTestCase
{
    use WooCommerceFixtures;

    public function setUp(): void
    {
        parent::setUp();

        $this->require_woocommerce();
    }

    public function test_a_paid_order_stops_the_sweep_and_is_announced_once(): void
    {
        $booking_id = $this->make_expired_unpaid_booking();
        $order      = $this->create_paid_order_for_booking( $booking_id, '1200' );

        $refunded_before = $order->get_total_refunded();

        SettingsCore::set( 'mhmrentiva_booking_auto_cancel_enabled', '1' );

        $announced = 0;
        add_action(
            'mhmrentiva_refund_status_changed',
            static function () use ( &$announced ): void {
                ++$announced;
            }
        );

        AutoCancel::run();

        $order_after = wc_get_order( $order->get_id() );
        $this->assertSame(
            'processing',
            $order_after->get_status(),
            'A paid order must not be cancelled by an unattended sweep.'
        );
        $this->assertSame(
            $refunded_before,
            $order_after->get_total_refunded(),
            'No unattended path may move money -- the refunded total must not change.'
        );
        $this->assertNotSame( Status::CANCELLED, Status::get( $booking_id ) );
        $this->assertSame( RefundStatus::NEEDS_REVIEW, RefundStatus::get( $booking_id ) );
        $this->assertSame( 1, $announced, 'A paid order found by the sweep must be announced exactly once.' );

        // Sanity check: the early return must not have touched the very meta
        // sweep #1's query selects on. If it had, the second run below would
        // pass whether or not RefundStatus::transition() correctly refuses a
        // no-op needs_review -> needs_review write -- it would just prove the
        // booking silently fell out of the query.
        $this->assertSame( 'pending', get_post_meta( $booking_id, '_mhmrentiva_payment_status', true ) );
        $this->assertSame( 'pending', get_post_meta( $booking_id, '_mhmrentiva_status', true ) );

        AutoCancel::run();

        $this->assertSame(
            1,
            $announced,
            'The second sweep must not re-announce a booking already parked in review.'
        );
    }

    /**
     * post_date is backdated two hours, past the default 30-minute payment
     * deadline, so AutoCancel::run()'s sweep #1 date_query selects it; the
     * payment_status/status pair is the pending/pending combination that same
     * sweep's meta_query selects on.
     */
    private function make_expired_unpaid_booking(): int
    {
        $booking_id = (int) self::factory()->post->create( array(
            'post_type' => 'mhmrentiva_booking',
            'post_date' => gmdate( 'Y-m-d H:i:s', time() - 2 * HOUR_IN_SECONDS ),
        ) );

        update_post_meta( $booking_id, '_mhmrentiva_payment_status', 'pending' );
        update_post_meta( $booking_id, '_mhmrentiva_status', 'pending' );

        return $booking_id;
    }
}
