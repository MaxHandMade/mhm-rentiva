<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Payment\Refunds;

use MHMRentiva\Admin\Payment\Refunds\Service;
use MHMRentiva\Tests\Support\WooCommerceFixtures;
use MHMRentiva\Tests\Support\WooCommerceOptionSandbox;
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
    use WooCommerceOptionSandbox;

    private ?float $capturedAmount = null;

    public function tearDown(): void
    {
        remove_all_actions('woocommerce_create_refund');
        $this->restore_sandboxed_options();
        parent::tearDown();
    }

    public function test_process_derives_the_major_unit_amount_at_the_stores_precision(): void
    {
        $this->require_woocommerce();

        $this->sandbox_option('woocommerce_price_num_decimals', '3');

        $booking_id = $this->factory->post->create(array( 'post_type' => 'mhmrentiva_booking' ));
        $this->create_paid_order_for_booking($booking_id, '1000.000');

        // M-01 (spec §5.5) is fixed as of slice 3 task 1: RefundValidator no
        // longer gates on WooCommerce's is_editable(), which was true only for
        // pending/on-hold/auto-draft and answered a question about the Edit
        // Order screen, not about refundability. The order stays "processing"
        // -- the ordinary status for a paid order, from create_paid_order_for_booking()
        // above -- and no longer needs walking back to an editable status by
        // hand to reach the conversion this test measures.

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
