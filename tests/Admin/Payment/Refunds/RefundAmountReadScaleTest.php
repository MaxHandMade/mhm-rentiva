<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Payment\Refunds;

use MHMRentiva\Admin\Payment\Refunds\Service;
use MHMRentiva\Tests\Support\WooCommerceFixtures;
use WP_UnitTestCase;

/**
 * The reader half of M-02, measured where Service::process() converts a
 * minor-unit amount to the major-unit figure it hands WooCommerce.
 *
 * This does not measure a refund landing: wc_create_refund() is called with
 * refund_payment => true, which makes WooCommerce try the (nonexistent) test
 * gateway, fail, delete the refund, and return a WP_Error -- in both the old
 * and the new code. get_total_refunded() is 0 either way, so the assertion
 * here is on the amount Service derived, captured from the
 * woocommerce_create_refund action before that deletion happens. Task 2's
 * suite already covers a refund actually landing.
 */
final class RefundAmountReadScaleTest extends WP_UnitTestCase
{
    use WooCommerceFixtures;

    private ?float $capturedAmount = null;

    public function tearDown(): void
    {
        remove_all_actions('woocommerce_create_refund');
        delete_option('woocommerce_price_num_decimals');
        parent::tearDown();
    }

    public function test_process_derives_the_major_unit_amount_at_the_stores_precision(): void
    {
        $this->require_woocommerce();

        update_option('woocommerce_price_num_decimals', '3');

        $booking_id = $this->factory->post->create(array( 'post_type' => 'mhmrentiva_booking' ));
        $order      = $this->create_paid_order_for_booking($booking_id, '1000.000');

        // A third gate this task does not own, measured directly: WooCommerce's
        // is_editable() -- which RefundValidator::validateGatewaySpecific() uses
        // to decide whether an order "can be refunded" -- is true only for
        // pending/on-hold/auto-draft. A paid order is ordinarily "processing",
        // so on a real site today this gate refuses the ordinary case, not an
        // edge case. This is not a settled property of the system: spec §5.5
        // tracks it as M-01, a defect -- is_editable() is scoped to the Edit
        // Order screen, not a refundability test, and the correct questions
        // are get_remaining_refund_amount() / can_refund_order(). Spec §10
        // step 4 replaces this validator. Until then, a test that wants to
        // reach the conversion has to walk the order back to an editable
        // status by hand, same as the priming below.
        $order->update_status('on-hold');
        $order->save();

        // Priming a gate this task does not own. RefundCalculator reads
        // _mhmrentiva_payment_amount, a key with zero production writers, and refuses
        // every partial refund without it. Spec §10 step 4 deletes that validator; until
        // then a test that wants to reach the conversion has to satisfy it. Status and
        // gateway are primed for the same reason: validatePaymentStatus() rejects an
        // empty status and validateGateway() accepts only offline/woocommerce.
        update_post_meta($booking_id, '_mhmrentiva_payment_amount', 1000000);
        update_post_meta($booking_id, '_mhmrentiva_payment_status', 'paid');
        update_post_meta($booking_id, '_mhmrentiva_payment_gateway', 'woocommerce');

        add_action(
            'woocommerce_create_refund',
            function ($refund, $args): void {
                $this->capturedAmount = (float) $args['amount'];
            },
            10,
            2
        );

        // 25.000 in a 3-decimal store is 25000 minor units.
        Service::process($booking_id, 25000, 'scale check');

        $this->assertNotNull(
            $this->capturedAmount,
            'woocommerce_create_refund never fired -- Service::process() did not reach wc_create_refund().'
        );

        $this->assertSame(
            '25.000',
            wc_format_decimal($this->capturedAmount, 3),
            'A fixed /100 hands WooCommerce 250.0 for a 25.000 request in a 3-decimal store.'
        );
    }
}
