<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Payment\Core;

use MHMRentiva\Admin\Payment\Core\PaymentState;
use MHMRentiva\Tests\Support\WooCommerceFixtures;
use WP_UnitTestCase;

/**
 * The writer half of M-02.
 *
 * Before this sweep WooCommerceBridge::handle_order_refunded() wrote
 * _mhmrentiva_refunded_amount with a fixed (int) round($x * 100) while
 * PaymentState scaled the offline channel with 10^decimals. In a 2-decimal
 * store the two agree by coincidence. This test runs the store at three
 * decimals so the coincidence is gone, and asserts the recorded refund comes
 * back out of the facade as the same money that went in.
 */
final class RefundedMetaScaleTest extends WP_UnitTestCase
{
    use WooCommerceFixtures;

    public function tearDown(): void
    {
        delete_option('woocommerce_price_num_decimals');
        parent::tearDown();
    }

    public function test_a_recorded_refund_survives_a_three_decimal_store(): void
    {
        $this->require_woocommerce();

        update_option('woocommerce_price_num_decimals', '3');

        $booking_id = $this->factory->post->create(array( 'post_type' => 'mhmrentiva_booking' ));

        $order = $this->create_paid_order_for_booking($booking_id, '100.000');
        wc_create_refund(array(
            'order_id' => $order->get_id(),
            'amount'   => '25.000',
        ));

        $this->assertSame(
            25000,
            (int) get_post_meta($booking_id, '_mhmrentiva_refunded_amount', true),
            'A fixed *100 records 2500 here, and the facade then reads a tenth of the refund.'
        );

        $this->assertSame(
            25000,
            PaymentState::forBooking($booking_id)->refunded(),
            'The write scale and the read scale have to be the same scale.'
        );
    }
}
