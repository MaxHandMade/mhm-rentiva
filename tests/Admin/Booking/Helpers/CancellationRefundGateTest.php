<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Helpers;

use MHMRentiva\Admin\Booking\Helpers\CancellationHandler;
use MHMRentiva\Admin\Payment\Core\Money;
use MHMRentiva\Tests\Support\WooCommerceFixtures;
use WP_UnitTestCase;

/**
 * Measured before this task: the money step is gated on
 * _mhmrentiva_payment_status === 'paid' (CancellationHandler:408). A booking
 * that already took a PARTIAL refund reads 'partially_refunded', so cancelling
 * it gave back nothing and the remaining balance stayed with the store. The
 * question the gate has to ask is the balance, which is what PaymentState
 * exists to answer.
 *
 * The opposite direction is asserted too: a booking that never took money must
 * not acquire refund bookkeeping just because someone cancelled it.
 */
final class CancellationRefundGateTest extends WP_UnitTestCase
{
    use WooCommerceFixtures;

    private int $booking_id;
    private int $admin_id;

    public function setUp(): void
    {
        parent::setUp();

        $this->require_woocommerce();

        $this->admin_id   = (int) self::factory()->user->create(array( 'role' => 'administrator' ));
        $this->booking_id = (int) self::factory()->post->create(array(
            'post_type'   => 'mhmrentiva_booking',
            'post_status' => 'publish',
        ));

        update_post_meta($this->booking_id, '_mhmrentiva_status', 'confirmed');
        update_post_meta($this->booking_id, '_mhmrentiva_vehicle_id', (int) self::factory()->post->create(array(
            'post_type' => 'mhmrentiva_vehicle',
        )));
        update_post_meta($this->booking_id, '_mhmrentiva_pickup_date', gmdate('Y-m-d', strtotime('+10 days')));
        update_post_meta($this->booking_id, '_mhmrentiva_dropoff_date', gmdate('Y-m-d', strtotime('+12 days')));

        // cancel_booking()'s outer ownership guard asks current_user_can(),
        // not user_can( $user_id ). An administrator actor therefore has to be
        // the current user too, or every call below is refused by a guard this
        // slice does not touch.
        wp_set_current_user($this->admin_id);
    }

    public function test_a_partially_refunded_booking_still_gives_back_its_remainder(): void
    {
        $order = $this->create_paid_order_for_booking($this->booking_id, '120');

        wc_create_refund(array(
            'order_id' => $order->get_id(),
            'amount'   => '20',
        ));

        // The state the old gate refused outright.
        update_post_meta($this->booking_id, '_mhmrentiva_payment_status', 'partially_refunded');

        CancellationHandler::cancel_booking($this->booking_id, $this->admin_id, 'remainder', true);

        $this->assertSame(
            Money::toMinor('120'),
            Money::toMinor(wc_get_order($order->get_id())->get_total_refunded()),
            'The remaining 100 must be given back; the old gate gave back nothing.'
        );
    }

    public function test_a_booking_that_never_took_money_gets_no_refund_bookkeeping(): void
    {
        update_post_meta($this->booking_id, '_mhmrentiva_payment_status', 'pending');

        $fired = 0;
        add_action('mhmrentiva_process_refund', static function () use (&$fired): void {
            ++$fired;
        });

        CancellationHandler::cancel_booking($this->booking_id, $this->admin_id, 'nothing to give back', true);

        $this->assertSame(0, $fired, 'The extension point must not fire for a booking with no money.');
        $this->assertSame('', (string) get_post_meta($this->booking_id, '_mhmrentiva_refund_status', true));
    }

    /**
     * The legacy shape: payment_status claims 'paid' but no channel has any
     * money on record. The flow is entered (the claim is honoured, so the hook
     * still fires for integrators who relied on it) and closes as not_required
     * rather than pretending a refund happened.
     */
    public function test_a_paid_claim_with_no_money_on_record_closes_as_not_required(): void
    {
        update_post_meta($this->booking_id, '_mhmrentiva_payment_status', 'paid');

        $fired = 0;
        add_action('mhmrentiva_process_refund', static function () use (&$fired): void {
            ++$fired;
        });

        CancellationHandler::cancel_booking($this->booking_id, $this->admin_id, 'legacy shape', true);

        $this->assertSame(1, $fired);
        $this->assertSame(
            'not_required',
            (string) get_post_meta($this->booking_id, '_mhmrentiva_refund_status', true)
        );
    }
}
