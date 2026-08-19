<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Payment\Refunds;

use MHMRentiva\Admin\Payment\Core\Money;
use MHMRentiva\Admin\Payment\Core\PaymentState;
use MHMRentiva\Admin\Payment\Refunds\Service;
use MHMRentiva\Tests\Support\WooCommerceFixtures;
use WP_UnitTestCase;

/**
 * N-01.
 *
 * A deposit booking has two paid orders: the original and the
 * remaining-payment order (_mhmrentiva_remaining_order_id). Service refunded
 * through BookingQueryHelper::resolve_wc_order_id(), which knows four legacy
 * keys and NOT the remaining-payment one -- so the second half of a deposit
 * booking's money was invisible to the refund subsystem. PaymentState::orders()
 * is the set that knows both, original first.
 */
final class RefundOperationMultiOrderTest extends WP_UnitTestCase
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

    private function seed_deposit_and_remaining(string $deposit, string $remaining): void
    {
        $this->create_paid_order_for_booking($this->booking_id, $deposit);

        $second = $this->create_paid_order_for_booking($this->booking_id, $remaining);
        update_post_meta($this->booking_id, '_mhmrentiva_remaining_order_id', $second->get_id());
    }

    public function test_a_full_refund_empties_both_orders(): void
    {
        $this->seed_deposit_and_remaining('30', '70');

        $result = Service::processFullRefund($this->booking_id, 'slice 3 multi-order');

        $this->assertSame('1', $result['mhmrentiva_refund'], (string) $result['mhmrentiva_refund_msg']);

        $state = PaymentState::forBooking($this->booking_id);
        $this->assertSame(
            0,
            $state->refundable(),
            'Refunding only the order resolve_wc_order_id() knows about leaves the '
                . 'remaining-payment order untouched -- that is the defect this test locks.'
        );
        $this->assertSame(Money::toMinor('100'), $state->refunded());
    }

    public function test_a_partial_refund_drains_the_first_order_before_touching_the_second(): void
    {
        $this->seed_deposit_and_remaining('30', '70');

        // 50 = all of the deposit order plus 20 of the remaining order.
        $result = Service::process($this->booking_id, Money::toMinor('50'), 'partial across orders');

        $this->assertSame('1', $result['mhmrentiva_refund'], (string) $result['mhmrentiva_refund_msg']);

        $state  = PaymentState::forBooking($this->booking_id);
        $orders = $state->orders();

        $first  = wc_get_order($orders[0]);
        $second = wc_get_order($orders[1]);

        $this->assertSame('30', wc_format_decimal($first->get_total_refunded(), 0));
        $this->assertSame('20', wc_format_decimal($second->get_total_refunded(), 0));
    }

    public function test_a_partial_refund_smaller_than_the_first_order_does_not_touch_the_second(): void
    {
        $this->seed_deposit_and_remaining('30', '70');

        Service::process($this->booking_id, Money::toMinor('10'), 'small partial');

        $orders = PaymentState::forBooking($this->booking_id)->orders();
        $second = wc_get_order($orders[1]);

        $this->assertSame(0.0, (float) $second->get_total_refunded());
    }
}
