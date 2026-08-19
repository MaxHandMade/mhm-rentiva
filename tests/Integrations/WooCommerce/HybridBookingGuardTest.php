<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integrations\WooCommerce;

use MHMRentiva\Admin\Payment\WooCommerce\RemainingPaymentHandler;
use MHMRentiva\Tests\Support\WooCommerceFixtures;
use WP_UnitTestCase;

/**
 * A hybrid booking -- deposit taken offline, remainder collected through
 * WooCommerce -- cannot be represented honestly by either channel, so it is
 * refused rather than modelled.
 *
 * PaymentState reads the offline channel only when the booking has no paid WC
 * order; the moment a WC order appears the offline deposit would vanish from
 * paid(). Modelling it was tried in the spec's v2 and produced three defects
 * (tax bases that do not compare, ghost money from coupons, and an existing
 * offline refund record being ignored).
 *
 * The guard fires only when there is a proven offline payment to lose --
 * _mhmrentiva_payment_status already in one of PaymentState::resolveOfflineChannel()'s
 * three statuses. A deposit booking with no WooCommerce order and no proven
 * payment yet has nothing to lose: that is the ordinary "manual booking, send
 * the customer a payment link" flow, and it must keep working.
 */
final class HybridBookingGuardTest extends WP_UnitTestCase
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

        update_post_meta($this->booking_id, '_mhmrentiva_payment_type', 'deposit');
        update_post_meta($this->booking_id, '_mhmrentiva_total_price', 100.0);
        update_post_meta($this->booking_id, '_mhmrentiva_remaining_amount', 70.0);
        update_post_meta($this->booking_id, '_mhmrentiva_payment_status', 'paid');
    }

    public function test_an_offline_booking_is_refused_a_remaining_order(): void
    {
        $result = RemainingPaymentHandler::get_or_create_remaining_order($this->booking_id);

        $this->assertInstanceOf(
            \WP_Error::class,
            $result,
            'Building a WC order for an offline booking makes its deposit invisible to PaymentState.'
        );
        $this->assertSame('mhmrentiva_hybrid_booking_refused', $result->get_error_code());
    }

    public function test_a_woocommerce_booking_still_gets_its_remaining_order(): void
    {
        $product = $this->ensure_booking_product('30');

        $order = wc_create_order(array( 'status' => 'pending' ));
        $item  = new \WC_Order_Item_Product();
        $item->set_product($product);
        $item->set_quantity(1);
        $item->set_subtotal(30.0);
        $item->set_total(30.0);
        $order->add_item($item);
        $order->calculate_totals();
        $order->save();
        $order->update_status('processing');

        update_post_meta($this->booking_id, '_mhmrentiva_woocommerce_order_id', $order->get_id());

        $result = RemainingPaymentHandler::get_or_create_remaining_order($this->booking_id);

        $this->assertInstanceOf(\WC_Order::class, $result, 'The normal WooCommerce flow must not regress.');
    }

    public function test_a_manual_booking_with_no_proven_payment_still_gets_its_remaining_order(): void
    {
        $this->ensure_booking_product();

        // setUp() marks the booking 'paid' so the refusal test above has a
        // proven offline payment to lose. This test represents a booking that
        // has never proven any payment at all -- an admin created it manually
        // and is about to send the customer a WooCommerce link for the whole
        // remaining balance. There is nothing for a WC order to make disappear.
        delete_post_meta($this->booking_id, '_mhmrentiva_payment_status');

        $result = RemainingPaymentHandler::get_or_create_remaining_order($this->booking_id);

        $this->assertInstanceOf(
            \WC_Order::class,
            $result,
            'A booking with no proven offline payment has nothing to lose from a WooCommerce order -- the manual-booking-plus-payment-link flow must stay supported.'
        );
    }

    /**
     * The guard asked "is there a WooCommerce order id", the facade asks "is
     * there a PAID WooCommerce order". Checkout binds a pending order at
     * creation time, so on a booking whose deposit was taken in cash the guard
     * saw an id, stayed silent, and let the hybrid it exists to prevent form.
     */
    public function test_a_pending_order_does_not_disarm_the_guard(): void
    {
        $this->ensure_booking_product();

        update_post_meta($this->booking_id, '_mhmrentiva_payment_status', 'paid');
        update_post_meta($this->booking_id, '_mhmrentiva_total_price', '300.00');
        update_post_meta($this->booking_id, '_mhmrentiva_remaining_amount', '200.00');

        // A checkout order that exists but was never paid.
        $pending = wc_create_order();
        $pending->set_status('pending');
        $pending->save();
        update_post_meta($this->booking_id, '_mhmrentiva_woocommerce_order_id', $pending->get_id());

        $result = RemainingPaymentHandler::get_or_create_remaining_order($this->booking_id);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('mhmrentiva_hybrid_booking_refused', $result->get_error_code());
    }

    /**
     * The guard's amount-based rewrite (orders() === [] && paid() > 0)
     * narrows it as well as widens it. paid()'s offline leg is
     * max(0, total - remaining); when the vehicle carries no deposit value,
     * DepositCalculator::calculate_deposit() sets remaining_amount ===
     * total_amount (ManualBookingMetaBox writes both from that result), so
     * the offline leg is 0 even with a proof payment_status on record. The
     * old status-based guard (resolve_wc_order_id() <= 0 && payment_status
     * proven) would have refused this booking anyway, with no offline money
     * at stake. The new guard stays silent -- a deliberate change, pinned
     * here rather than left to be rediscovered as a regression. See the note
     * on this direction in RemainingPaymentHandler::is_hybrid_booking()'s
     * docblock.
     */
    public function test_a_no_deposit_booking_with_total_equal_to_remaining_leaves_the_guard_silent(): void
    {
        $this->ensure_booking_product();

        // setUp() already marks the booking 'paid' (a proof status); only
        // total/remaining need to collapse to the no-deposit shape.
        update_post_meta($this->booking_id, '_mhmrentiva_total_price', 100.0);
        update_post_meta($this->booking_id, '_mhmrentiva_remaining_amount', 100.0);

        $result = RemainingPaymentHandler::get_or_create_remaining_order($this->booking_id);

        $this->assertInstanceOf(
            \WC_Order::class,
            $result,
            'total === remaining means there is no offline deposit for a WC order to make disappear -- the guard must stay silent and let the remaining order build.'
        );
    }

    /**
     * Negative control. A booking with a genuinely PAID deposit order is the
     * ordinary deposit flow, and the guard must stay out of its way -- without
     * this, "always refuse" would pass the test above.
     */
    public function test_a_paid_deposit_order_leaves_the_guard_silent(): void
    {
        $this->ensure_booking_product();

        update_post_meta($this->booking_id, '_mhmrentiva_payment_status', 'paid');
        update_post_meta($this->booking_id, '_mhmrentiva_total_price', '300.00');
        update_post_meta($this->booking_id, '_mhmrentiva_remaining_amount', '200.00');

        $deposit = wc_create_order();
        $deposit->set_total('100.00');
        $deposit->set_date_paid(time());
        $deposit->save();
        update_post_meta($this->booking_id, '_mhmrentiva_woocommerce_order_id', $deposit->get_id());

        $result = RemainingPaymentHandler::get_or_create_remaining_order($this->booking_id);

        if ($result instanceof \WP_Error) {
            $this->fail(sprintf(
                'This is the ordinary deposit flow, not a hybrid -- expected a WC_Order but got WP_Error %s: %s',
                $result->get_error_code(),
                $result->get_error_message()
            ));
        }

        $this->assertInstanceOf(\WC_Order::class, $result);
    }
}
