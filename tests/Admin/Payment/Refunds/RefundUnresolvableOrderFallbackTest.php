<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Payment\Refunds;

use MHMRentiva\Admin\Payment\Core\Money;
use MHMRentiva\Admin\PostTypes\Logs\PostType;
use MHMRentiva\Tests\Support\WooCommerceFixtures;
use WP_UnitTestCase;

/**
 * The "Important" half of Task 8's fix round 1: an order
 * WooCommerceBridge::handle_order_refunded() can resolve a booking FROM, that
 * PaymentState::forBooking() cannot resolve BACK.
 *
 * get_booking_id_from_order() finds the booking through the order's own meta
 * OR its line item's -- either is enough. PaymentState::resolvePaidOrders()
 * only ever follows the BOOKING's own pointer keys
 * (BookingQueryHelper::resolve_wc_order_id()'s four, plus
 * _mhmrentiva_remaining_order_id). An order that reaches the booking by line
 * item alone, with none of those booking-side keys pointing back at it, is
 * therefore invisible to PaymentState::orders() while still being exactly the
 * order the refund hook fired for.
 *
 * Before the round-1 fix this mattered because $state->refunded() would then
 * read back whatever the booking's OTHER (zero, here) orders total -- so the
 * write that was supposed to record this refund would silently record 0,
 * losing the event outright while looking like a normal partially-refunded
 * write. The guard added in round 1 falls back to this order's own
 * get_total_refunded()/get_total() comparison instead. This test exists
 * because that guard had code and logging but nothing exercising it -- the
 * same shape, one level up, as the double-write bug Task 8 itself fixed.
 */
final class RefundUnresolvableOrderFallbackTest extends WP_UnitTestCase
{
    use WooCommerceFixtures;

    public function test_a_refund_on_an_order_paymentstate_cannot_resolve_is_not_recorded_as_zero(): void
    {
        $this->require_woocommerce();

        $booking_id = (int) self::factory()->post->create(array(
            'post_type'   => 'mhmrentiva_booking',
            'post_status' => 'publish',
        ));

        // In PaymentState's offline proof set on purpose: this is what would
        // make the offline gate open -- and refunded() read back the stored
        // meta, starting at 0 -- if the round-1 guard were not there.
        update_post_meta($booking_id, '_mhmrentiva_payment_status', 'paid');

        // Deliberately absent: _mhmrentiva_woocommerce_order_id,
        // _mhmrentiva_remaining_order_id, and the three other legacy keys
        // BookingQueryHelper::resolve_wc_order_id() checks. With none of them
        // set, PaymentState::resolvePaidOrders() returns an empty set no
        // matter what the order itself points back to -- confirmed below.

        $order = $this->create_order_linked_by_line_item_only($booking_id, '40');

        $this->assertSame(
            array(),
            \MHMRentiva\Admin\Payment\Core\PaymentState::forBooking($booking_id)->orders(),
            'Guard: PaymentState must not resolve this order, or the test is not measuring the fallback branch.'
        );

        $refund = wc_create_refund(array(
            'order_id' => $order->get_id(),
            'amount'   => '15',
        ));

        $this->assertFalse(is_wp_error($refund), 'Guard: the refund itself must succeed for this test to measure anything.');

        $this->assertSame(
            Money::toMinor('15'),
            (int) get_post_meta($booking_id, '_mhmrentiva_refunded_amount', true),
            "PaymentState::orders() is empty for this booking, so state->refunded() would read back the stored"
            . " meta (0) and silently erase this refund. The guard must fall back to the order's own figure."
        );

        $logs = get_posts(array(
            'post_type'      => PostType::TYPE,
            'posts_per_page' => 1,
            'orderby'        => 'ID',
            'order'          => 'DESC',
            'post_status'    => 'publish',
        ));

        $this->assertNotEmpty($logs, 'The fallback branch must log via AdvancedLogger::error() when it fires.');
        $this->assertStringContainsString(
            'not resolvable through PaymentState',
            $logs[0]->post_content,
            'The logged entry should be the one the fallback branch writes, not an unrelated log.'
        );
        $this->assertStringContainsString((string) $order->get_id(), $logs[0]->post_content);
        $this->assertStringContainsString((string) $booking_id, $logs[0]->post_content);
    }

    public function test_a_mixed_booking_keeps_the_resolvable_legs_refund_and_does_not_flip_status(): void
    {
        // The pure case above -- no resolvable orders at all -- is not the
        // only shape the fallback has to answer for. A booking can have ONE
        // leg PaymentState resolves and ANOTHER it does not. Production
        // always stamps a booking-side pointer for every order it creates,
        // so this is a data-link-gap edge rather than a live mainline path,
        // but the fallback still has to get it right without recreating the
        // two things Task 8 removed: an absolute write that discards
        // another order's refund (N-01), and a terminal status flip decided
        // from one order's own totals while money is still outstanding
        // elsewhere.
        $this->require_woocommerce();

        $booking_id = (int) self::factory()->post->create(array(
            'post_type'   => 'mhmrentiva_booking',
            'post_status' => 'publish',
        ));

        update_post_meta($booking_id, '_mhmrentiva_payment_status', 'paid');
        \MHMRentiva\Admin\Booking\Core\Status::update_status($booking_id, 'completed', 0);

        // The resolvable leg: wired through the shared fixture, which stamps
        // BOTH the order-level meta and the booking's own
        // _mhmrentiva_woocommerce_order_id pointer -- so PaymentState finds
        // it.
        $resolvable = $this->create_paid_order_for_booking($booking_id, '70');

        // The invisible leg: linked ONLY by its line item, exactly like the
        // pure case above -- and, critically, never registered as
        // _mhmrentiva_remaining_order_id or any other booking-side pointer.
        // Registering it there would make it resolvable and this would stop
        // being the mixed shape.
        $invisible = $this->create_order_linked_by_line_item_only($booking_id, '30');

        $this->assertSame(
            array( $resolvable->get_id() ),
            \MHMRentiva\Admin\Payment\Core\PaymentState::forBooking($booking_id)->orders(),
            'Guard: PaymentState must resolve the first order and NOT the second, or this is not the mixed shape.'
        );

        // Partially refund the RESOLVABLE leg first, so its contribution is
        // on the record before the invisible leg is touched at all.
        $firstRefund = wc_create_refund(array(
            'order_id' => $resolvable->get_id(),
            'amount'   => '20',
        ));
        $this->assertFalse(is_wp_error($firstRefund), "Guard: the resolvable leg's own refund must succeed.");

        $this->assertSame(
            Money::toMinor('20'),
            (int) get_post_meta($booking_id, '_mhmrentiva_refunded_amount', true),
            "Guard: before the invisible leg is touched, only the resolvable leg's refund should be on record."
        );

        // Now fully drain the INVISIBLE leg.
        $secondRefund = wc_create_refund(array(
            'order_id' => $invisible->get_id(),
            'amount'   => '30',
        ));
        $this->assertFalse(is_wp_error($secondRefund), "Guard: the invisible leg's own refund must succeed.");

        $this->assertSame(
            Money::toMinor('50'),
            (int) get_post_meta($booking_id, '_mhmrentiva_refunded_amount', true),
            "The resolvable leg already refunded 20; the invisible leg's own 30 must be ADDED to that, not"
            . " substituted for it. Recording 30 alone means the fallback discarded the resolvable leg's"
            . " refund -- N-01, recreated inside the branch meant to guard against it."
        );

        $this->assertSame(
            'completed',
            (string) get_post_meta($booking_id, '_mhmrentiva_status', true),
            'The invisible leg (30 of 30) is fully refunded on its OWN totals, but the booking as a whole'
            . ' (50 of 100) is not. Granting terminal-status authority to the invisible leg alone here is'
            . ' the exact mid-walk shape Task 8 removed, recreated one level down.'
        );
        $this->assertSame(
            'partially_refunded',
            (string) get_post_meta($booking_id, '_mhmrentiva_payment_status', true)
        );
    }

    /**
     * Builds a paid, processing WooCommerce order linked to the booking ONLY
     * through its line item's `_mhmrentiva_booking_id` meta -- never the
     * order's own meta, and never any of the booking's own pointer keys. This
     * is the one shape WooCommerceFixtures::create_paid_order_for_booking()
     * cannot produce: that method always writes the order-level key and (on a
     * booking's first call) the booking's own _mhmrentiva_woocommerce_order_id
     * pointer too, either of which would make this order resolvable through
     * PaymentState and defeat the scenario this test measures.
     */
    private function create_order_linked_by_line_item_only(int $booking_id, string $total): \WC_Order
    {
        $product = $this->ensure_booking_product($total);

        $order = wc_create_order(array( 'status' => 'pending' ));
        $item  = new \WC_Order_Item_Product();
        $item->set_product($product);
        $item->set_quantity(1);
        $item->set_subtotal((float) $total);
        $item->set_total((float) $total);
        $item->add_meta_data('_mhmrentiva_booking_id', $booking_id);
        $order->add_item($item);
        $order->calculate_totals();
        $order->save();
        $order->update_status('processing');

        return $order;
    }
}
