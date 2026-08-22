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

        // RefundCalculator (the _mhmrentiva_payment_amount reader) and
        // RefundValidator::validateGateway() are both gone now. What
        // Service::process() -> RefundValidator::decide() gates on today is
        // PaymentState, which derives refundability from the paid order(s)
        // create_paid_order_for_booking() above already created -- no
        // priming meta beyond payment status is needed to reach the
        // conversion this test measures.
        update_post_meta($booking_id, '_mhmrentiva_payment_status', 'paid');

        add_action(
            'woocommerce_create_refund',
            function ($refund, $args): void {
                $this->capturedAmount = (float) $args['amount'];
            },
            10,
            2
        );

        // 25.000 in a 3-decimal store is 25000 minor units. The actor is an
        // administrator: this test measures unit scaling, not authorization,
        // and the fixture sets no _mhmrentiva_customer_user_id at all.
        $admin_id = (int) self::factory()->user->create(array( 'role' => 'administrator' ));
        Service::process($booking_id, 25000, 'scale check', $admin_id);

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
