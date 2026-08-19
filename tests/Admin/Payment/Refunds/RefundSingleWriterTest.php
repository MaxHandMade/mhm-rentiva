<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Payment\Refunds;

use MHMRentiva\Admin\Payment\Core\Money;
use MHMRentiva\Admin\Payment\Refunds\Service;
use MHMRentiva\Tests\Support\WooCommerceFixtures;
use WP_UnitTestCase;

/**
 * N-02, second half: one writer per channel.
 *
 * Measured before this task: wc_create_refund() fires
 * woocommerce_refund_created, WooCommerceBridge::handle_order_refunded() writes
 * _mhmrentiva_refunded_amount ABSOLUTELY, control returns to Service, and
 * Service::updateBookingMeta() read that value back and wrote
 * previous + amount on top. A 20 refund on a 120 order therefore recorded 40.
 *
 * The same method then compared the doubled figure against
 * _mhmrentiva_payment_amount -- always 0 -- so it always wrote
 * payment_status = 'refunded', overwriting the correct 'partially_refunded'
 * the hook had just written one line earlier.
 *
 * The offline channel is the deliberate exception: no WC_Order_Refund object
 * exists there, no hook fires, and that meta IS the ledger, so Service
 * accumulates it. Both directions are asserted here so the exception cannot be
 * mistaken for the rule.
 */
final class RefundSingleWriterTest extends WP_UnitTestCase
{
    use WooCommerceFixtures;

    /** @var int */
    private $booking_id;

    public function setUp(): void
    {
        parent::setUp();

        $this->require_woocommerce();

        $this->booking_id = (int) self::factory()->post->create(array(
            'post_type'   => 'mhmrentiva_booking',
            'post_status' => 'publish',
        ));

        update_post_meta($this->booking_id, '_mhmrentiva_payment_status', 'paid');
    }

    public function test_a_woocommerce_refund_is_recorded_once(): void
    {
        $this->create_paid_order_for_booking($this->booking_id, '120');

        Service::process($this->booking_id, Money::toMinor('20'), 'single writer');

        $this->assertSame(
            Money::toMinor('20'),
            (int) get_post_meta($this->booking_id, '_mhmrentiva_refunded_amount', true),
            'Recording 40 for a 20 refund is the additive write this task removes.'
        );
    }

    public function test_a_partial_refund_leaves_the_booking_partially_refunded(): void
    {
        $this->create_paid_order_for_booking($this->booking_id, '120');

        Service::process($this->booking_id, Money::toMinor('20'), 'status after partial');

        $this->assertSame(
            'partially_refunded',
            (string) get_post_meta($this->booking_id, '_mhmrentiva_payment_status', true)
        );
    }

    public function test_a_full_refund_marks_the_booking_refunded(): void
    {
        $this->create_paid_order_for_booking($this->booking_id, '120');

        Service::processFullRefund($this->booking_id, 'status after full');

        $this->assertSame(
            'refunded',
            (string) get_post_meta($this->booking_id, '_mhmrentiva_payment_status', true)
        );
    }

    public function test_two_successive_partial_refunds_do_not_compound(): void
    {
        $this->create_paid_order_for_booking($this->booking_id, '120');

        Service::process($this->booking_id, Money::toMinor('20'), 'first');
        Service::process($this->booking_id, Money::toMinor('30'), 'second');

        $this->assertSame(
            Money::toMinor('50'),
            (int) get_post_meta($this->booking_id, '_mhmrentiva_refunded_amount', true)
        );
    }

    public function test_the_offline_channel_accumulates_because_nothing_else_records_it(): void
    {
        update_post_meta($this->booking_id, '_mhmrentiva_total_price', '80');
        update_post_meta($this->booking_id, '_mhmrentiva_remaining_amount', '0');

        Service::process($this->booking_id, Money::toMinor('30'), 'offline first');
        Service::process($this->booking_id, Money::toMinor('20'), 'offline second');

        $this->assertSame(
            Money::toMinor('50'),
            (int) get_post_meta($this->booking_id, '_mhmrentiva_refunded_amount', true),
            'No WooCommerce refund object exists offline, so Service is the only recorder.'
        );
    }

    private function seed_deposit_and_remaining(string $deposit, string $remaining): void
    {
        $this->create_paid_order_for_booking($this->booking_id, $deposit);

        $second = $this->create_paid_order_for_booking($this->booking_id, $remaining);
        update_post_meta($this->booking_id, '_mhmrentiva_remaining_order_id', $second->get_id());
    }

    public function test_a_multi_order_full_refund_records_the_whole_amount_not_the_last_leg(): void
    {
        // Before this task the hook wrote `Money::toMinor( $order->get_total_refunded() )` --
        // ONE order's figure. Refunding a 30+70 booking in full therefore ended with 70
        // recorded, because the second leg's absolute write erased the first leg's. That is
        // N-01, and this is the assertion that catches it coming back.
        $this->seed_deposit_and_remaining('30', '70');

        Service::processFullRefund($this->booking_id, 'multi-order absolute write');

        $this->assertSame(
            Money::toMinor('100'),
            (int) get_post_meta($this->booking_id, '_mhmrentiva_refunded_amount', true),
            'The booking refunded 100. Recording 70 means the last leg overwrote the first.'
        );
    }

    public function test_draining_the_first_order_does_not_mark_the_whole_booking_refunded(): void
    {
        // The defect this locks was introduced by Task 6 and is terminal: refunding 50 of a
        // 30+70 booking fully drains the FIRST order, so the hook's per-order comparison
        // (30 >= 30) fired Status::update_status( 'refunded' ) mid-walk, while 50 was still
        // outstanding. Status.php gives REFUNDED an empty transition list, so on a completed
        // rental that is irreversible -- and it fires the customer's status-change e-mail and
        // invalidates the availability cache on the way out.
        //
        // The comparison is booking-level now. A partial refund must leave the booking's own
        // status alone no matter how completely one of its orders was drained.
        $this->seed_deposit_and_remaining('30', '70');

        \MHMRentiva\Admin\Booking\Core\Status::update_status($this->booking_id, 'completed', 0);
        $before = (string) get_post_meta($this->booking_id, '_mhmrentiva_status', true);

        $this->assertSame('completed', $before, 'Guard: the fixture must start completed.');

        Service::process($this->booking_id, Money::toMinor('50'), 'partial across orders');

        $this->assertSame(
            'completed',
            (string) get_post_meta($this->booking_id, '_mhmrentiva_status', true),
            'A partial refund must not terminally mark a completed booking refunded.'
        );
        $this->assertSame(
            'partially_refunded',
            (string) get_post_meta($this->booking_id, '_mhmrentiva_payment_status', true)
        );
    }
}
