<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Payment\Core;

use MHMRentiva\Admin\Payment\Core\PaymentState;
use MHMRentiva\Tests\Support\WooCommerceFixtures;
use WP_UnitTestCase;

/**
 * The offline channel, and the two rules that keep it honest.
 *
 * Payment proof: _mhmrentiva_remaining_amount is only written for deposit
 * bookings, so a full-payment offline booking has it empty. Without a proof
 * requirement "total - remaining" reads as "total paid" for a booking where
 * nobody has paid anything.
 *
 * Scale: _mhmrentiva_total_price and _mhmrentiva_remaining_amount are MAJOR
 * units (BookingMeta writes $daily_price * $days raw) while
 * _mhmrentiva_refunded_amount is MINOR units (writers use Money::toMinor()).
 * Subtracting one from the other without conversion is a 100x error.
 */
final class PaymentStateOfflineTest extends WP_UnitTestCase
{
    use WooCommerceFixtures;

    /** @var int */
    private $booking_id;

    public function setUp(): void
    {
        parent::setUp();

        update_option('woocommerce_price_num_decimals', '2');

        $this->booking_id = (int) self::factory()->post->create(array(
            'post_type'   => 'mhmrentiva_booking',
            'post_status' => 'publish',
        ));
    }

    public function test_an_unpaid_offline_booking_has_no_refundable_money(): void
    {
        update_post_meta($this->booking_id, '_mhmrentiva_total_price', 1000.0);
        update_post_meta($this->booking_id, '_mhmrentiva_payment_status', 'pending');

        $state = PaymentState::forBooking($this->booking_id);

        $this->assertSame(0, $state->paid(), 'A booking nobody paid must not report money.');
        $this->assertSame(0, $state->refundable());
    }

    public function test_a_paid_offline_booking_reports_its_money(): void
    {
        update_post_meta($this->booking_id, '_mhmrentiva_total_price', 1000.0);
        update_post_meta($this->booking_id, '_mhmrentiva_remaining_amount', 0.0);
        update_post_meta($this->booking_id, '_mhmrentiva_payment_status', 'paid');

        $state = PaymentState::forBooking($this->booking_id);

        $this->assertSame(100000, $state->paid());
        $this->assertSame(100000, $state->refundableManual());
        $this->assertSame(0, $state->refundableAuto(), 'Offline money cannot be refunded through a gateway.');
    }

    public function test_a_partly_paid_deposit_booking_reports_only_the_deposit(): void
    {
        update_post_meta($this->booking_id, '_mhmrentiva_total_price', 1000.0);
        update_post_meta($this->booking_id, '_mhmrentiva_remaining_amount', 700.0);
        update_post_meta($this->booking_id, '_mhmrentiva_payment_status', 'paid');

        $this->assertSame(30000, PaymentState::forBooking($this->booking_id)->paid());
    }

    public function test_the_two_meta_scales_are_not_subtracted_raw(): void
    {
        // 1000.00 paid (major) and 200.00 already refunded (stored as 20000 minor).
        update_post_meta($this->booking_id, '_mhmrentiva_total_price', 1000.0);
        update_post_meta($this->booking_id, '_mhmrentiva_remaining_amount', 0.0);
        update_post_meta($this->booking_id, '_mhmrentiva_payment_status', 'partially_refunded');
        update_post_meta($this->booking_id, '_mhmrentiva_refunded_amount', 20000);

        $state = PaymentState::forBooking($this->booking_id);

        $this->assertSame(20000, $state->refunded());
        $this->assertSame(
            80000,
            $state->refundableManual(),
            'A raw major-minus-minor subtraction gives -19000 here and reads as fully refunded.'
        );
    }

    public function test_a_cancelled_payment_status_is_not_proof_of_payment(): void
    {
        // AutoCancel writes 'cancelled' into payment_status.
        update_post_meta($this->booking_id, '_mhmrentiva_total_price', 1000.0);
        update_post_meta($this->booking_id, '_mhmrentiva_remaining_amount', 0.0);
        update_post_meta($this->booking_id, '_mhmrentiva_payment_status', 'cancelled');

        $this->assertSame(0, PaymentState::forBooking($this->booking_id)->paid());
    }

    public function test_a_paid_wc_order_suppresses_the_offline_channel(): void
    {
        // Same booking, both stories: a real paid WooCommerce order AND the meta
        // an offline booking would carry. The offline channel must stay inert --
        // this single rule is what stops the same money being counted twice.
        $this->require_woocommerce();

        $product = $this->ensure_booking_product('300');

        $order = wc_create_order(array( 'status' => 'pending' ));

        $item = new \WC_Order_Item_Product();
        $item->set_product($product);
        $item->set_quantity(1);
        $item->set_subtotal(300.0);
        $item->set_total(300.0);
        $order->add_item($item);
        $order->calculate_totals();
        $order->save();
        $order->update_status('processing');

        update_post_meta($this->booking_id, '_mhmrentiva_woocommerce_order_id', $order->get_id());

        // The offline-looking meta a fully-paid offline booking would also carry.
        update_post_meta($this->booking_id, '_mhmrentiva_total_price', 1000.0);
        update_post_meta($this->booking_id, '_mhmrentiva_remaining_amount', 0.0);
        update_post_meta($this->booking_id, '_mhmrentiva_payment_status', 'paid');

        $state = PaymentState::forBooking($this->booking_id);

        $this->assertSame(
            30000,
            $state->paid(),
            'The WC order must own paid(); the offline meta must not be added on top of it.'
        );
        $this->assertSame(0, $state->refunded());
        $this->assertSame(
            0,
            $state->refundableManual(),
            'The offline channel must stay inert while a paid WC order is present.'
        );
    }

    /**
     * The two offline legs used different gates: paid demanded proof of
     * payment, refunded only demanded the absence of a WooCommerce order. A
     * cancelled booking carrying a refund record therefore reported money
     * returned that it also reported never receiving.
     */
    public function test_a_cancelled_booking_cannot_report_a_refund_it_never_took(): void
    {
        update_post_meta($this->booking_id, '_mhmrentiva_payment_status', 'cancelled');
        update_post_meta($this->booking_id, '_mhmrentiva_total_price', '300.00');
        update_post_meta($this->booking_id, '_mhmrentiva_remaining_amount', '0');
        update_post_meta($this->booking_id, '_mhmrentiva_refunded_amount', 5000);

        $state = PaymentState::forBooking($this->booking_id);

        $this->assertSame(0, $state->paid(), 'cancelled is not proof of payment.');
        $this->assertSame(0, $state->refunded(), 'and it is not proof of a refund either.');
        $this->assertSame(0, $state->refundableManual());
    }

    /**
     * The negative control for the test above: with the same refund record and
     * a payment status that IS proof, both legs stay live. Without this, the
     * fix could be "always return 0" and the suite would applaud.
     */
    public function test_a_proven_offline_payment_still_reports_its_refund(): void
    {
        update_post_meta($this->booking_id, '_mhmrentiva_payment_status', 'partially_refunded');
        update_post_meta($this->booking_id, '_mhmrentiva_total_price', '300.00');
        update_post_meta($this->booking_id, '_mhmrentiva_remaining_amount', '0');
        update_post_meta($this->booking_id, '_mhmrentiva_refunded_amount', 5000);

        $state = PaymentState::forBooking($this->booking_id);

        $this->assertSame(30000, $state->paid());
        $this->assertSame(5000, $state->refunded());
        $this->assertSame(25000, $state->refundableManual());
    }
}
