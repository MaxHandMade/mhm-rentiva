<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Payment\Core;

use MHMRentiva\Admin\Payment\Core\PaymentState;
use MHMRentiva\Tests\Support\WooCommerceFixtures;
use WP_UnitTestCase;

/**
 * C-1: the order set must contain only orders whose money actually arrived.
 *
 * A remaining-payment order is created as `pending` and linked to the booking
 * at creation time (RemainingPaymentHandler), and AutoCancel exists precisely
 * to cancel those unpaid orders -- so pending and cancelled remaining orders
 * are an ordinary state, not an edge case. Summing them would report money
 * nobody paid as refundable.
 *
 * The filter is get_date_paid(), not is_paid(): is_paid() is status-based and
 * a fully refunded order sits in `refunded`, so is_paid() would drop it from
 * the set and lose the refund history with it. date_paid is written once and
 * a refund does not clear it.
 */
final class PaymentStateOrdersTest extends WP_UnitTestCase
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
    }

    private function make_order(string $total, string $status): \WC_Order
    {
        $product = $this->ensure_booking_product($total);

        $order = wc_create_order(array( 'status' => 'pending' ));

        $item = new \WC_Order_Item_Product();
        $item->set_product($product);
        $item->set_quantity(1);
        $item->set_subtotal((float) $total);
        $item->set_total((float) $total);
        $order->add_item($item);
        $order->calculate_totals();
        $order->save();

        if ($status !== 'pending') {
            $order->update_status($status);
        }

        return $order;
    }

    public function test_an_unpaid_remaining_order_is_excluded(): void
    {
        $deposit   = $this->make_order('30', 'processing');
        $remaining = $this->make_order('70', 'pending');

        update_post_meta($this->booking_id, '_mhmrentiva_woocommerce_order_id', $deposit->get_id());
        update_post_meta($this->booking_id, '_mhmrentiva_remaining_order_id', $remaining->get_id());

        $state = PaymentState::forBooking($this->booking_id);

        $this->assertSame(
            array( $deposit->get_id() ),
            $state->orders(),
            'An unpaid pending order was counted as money in hand.'
        );
    }

    public function test_a_cancelled_remaining_order_is_excluded(): void
    {
        $deposit   = $this->make_order('30', 'processing');
        $remaining = $this->make_order('70', 'cancelled');

        update_post_meta($this->booking_id, '_mhmrentiva_woocommerce_order_id', $deposit->get_id());
        update_post_meta($this->booking_id, '_mhmrentiva_remaining_order_id', $remaining->get_id());

        $this->assertSame(array( $deposit->get_id() ), PaymentState::forBooking($this->booking_id)->orders());
    }

    public function test_both_paid_orders_are_included_in_payment_order(): void
    {
        $deposit   = $this->make_order('30', 'processing');
        $remaining = $this->make_order('70', 'completed');

        update_post_meta($this->booking_id, '_mhmrentiva_woocommerce_order_id', $deposit->get_id());
        update_post_meta($this->booking_id, '_mhmrentiva_remaining_order_id', $remaining->get_id());

        $this->assertSame(
            array( $deposit->get_id(), $remaining->get_id() ),
            PaymentState::forBooking($this->booking_id)->orders(),
            'Refunds walk this list in order; the original order must come first.'
        );
    }

    public function test_a_fully_refunded_order_stays_in_the_set(): void
    {
        $order = $this->make_order('30', 'processing');

        wc_create_refund(array(
            'order_id'       => $order->get_id(),
            'amount'         => 30,
            'refund_payment' => false,
        ));

        update_post_meta($this->booking_id, '_mhmrentiva_woocommerce_order_id', $order->get_id());

        $this->assertSame(
            array( $order->get_id() ),
            PaymentState::forBooking($this->booking_id)->orders(),
            'Dropping a refunded order loses the refund history with it.'
        );
    }

    public function test_a_booking_without_orders_has_an_empty_set(): void
    {
        $this->assertSame(array(), PaymentState::forBooking($this->booking_id)->orders());
    }

    public function test_paid_sums_both_paid_orders(): void
    {
        $deposit   = $this->make_order('30', 'processing');
        $remaining = $this->make_order('70', 'completed');

        update_post_meta($this->booking_id, '_mhmrentiva_woocommerce_order_id', $deposit->get_id());
        update_post_meta($this->booking_id, '_mhmrentiva_remaining_order_id', $remaining->get_id());

        $this->assertSame(10000, PaymentState::forBooking($this->booking_id)->paid());
    }

    public function test_refundable_auto_comes_from_woocommerce_not_from_paid(): void
    {
        $order = $this->make_order('30', 'processing');

        wc_create_refund(array(
            'order_id'       => $order->get_id(),
            'amount'         => 10,
            'refund_payment' => false,
        ));

        update_post_meta($this->booking_id, '_mhmrentiva_woocommerce_order_id', $order->get_id());

        $state = PaymentState::forBooking($this->booking_id);

        $this->assertSame(3000, $state->paid(), 'paid() is the money that arrived, refunds do not reduce it.');
        $this->assertSame(1000, $state->refunded());
        $this->assertSame(
            2000,
            $state->refundableAuto(),
            'The refund base must be WooCommerce\'s own remaining figure, never paid() minus refunded().'
        );
    }

    public function test_a_fully_refunded_order_has_nothing_left_to_refund(): void
    {
        $order = $this->make_order('30', 'processing');

        wc_create_refund(array(
            'order_id'       => $order->get_id(),
            'amount'         => 30,
            'refund_payment' => false,
        ));

        update_post_meta($this->booking_id, '_mhmrentiva_woocommerce_order_id', $order->get_id());

        $state = PaymentState::forBooking($this->booking_id);

        $this->assertSame(0, $state->refundableAuto());
        $this->assertTrue($state->isFullyRefunded());
    }
}
