<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Payment\Refunds;

use MHMRentiva\Admin\Payment\Core\Money;
use MHMRentiva\Admin\Payment\Refunds\RefundValidator;
use MHMRentiva\Tests\Support\WooCommerceFixtures;
use WP_UnitTestCase;

/**
 * Spec addition A, measured on the dev site 2026-08-19.
 *
 * validateGateway() accepted only 'offline' and 'woocommerce', read from
 * _mhmrentiva_payment_gateway. That meta is written in exactly two production
 * places, both inside a wp_insert_post( meta_input ) array at creation time,
 * and ManualBookingMetaBox:864 writes a literal ''. Nothing writes it again
 * afterwards. So every admin-created booking -- and every booking that
 * predates the checkout path populating it, including one that HAS a
 * WooCommerce order -- was refused as "unsupported payment method". Six of the
 * twelve newest dev bookings were in that state.
 *
 * The channel is not an opinion stored at creation time; it is a fact about
 * where the money is. PaymentState already answers it, and answers it
 * correctly for rows written before any of this existed.
 */
final class RefundChannelTest extends WP_UnitTestCase
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

    public function test_an_empty_gateway_meta_no_longer_refuses_a_paid_order(): void
    {
        $this->create_paid_order_for_booking($this->booking_id, '120');
        update_post_meta($this->booking_id, '_mhmrentiva_payment_gateway', '');

        $result = RefundValidator::validateFullRefund($this->booking_id);

        $this->assertTrue(
            $result['valid'],
            'Booking 9313 on the dev site is exactly this shape: a real WooCommerce order '
                . 'behind an empty gateway meta. Got: ' . ( $result['message'] ?? '' )
        );
        $this->assertSame(RefundValidator::CHANNEL_WOOCOMMERCE, $result['channel']);
    }

    public function test_a_booking_with_no_orders_resolves_to_the_offline_channel(): void
    {
        // The offline shape PaymentState::resolveOfflineChannel() recognises:
        // a proof-of-payment status, a total, and a remaining of zero.
        update_post_meta($this->booking_id, '_mhmrentiva_total_price', '80');
        update_post_meta($this->booking_id, '_mhmrentiva_remaining_amount', '0');
        update_post_meta($this->booking_id, '_mhmrentiva_payment_gateway', '');

        $result = RefundValidator::validateFullRefund($this->booking_id);

        $this->assertTrue($result['valid'], (string) ( $result['message'] ?? '' ));
        $this->assertSame(RefundValidator::CHANNEL_OFFLINE, $result['channel']);
        $this->assertSame(Money::toMinor('80'), $result['amount']);
    }

    public function test_a_stale_gateway_meta_does_not_override_where_the_money_is(): void
    {
        // Meta says offline; a paid WooCommerce order says otherwise. The
        // order wins, because the order is the money.
        $this->create_paid_order_for_booking($this->booking_id, '120');
        update_post_meta($this->booking_id, '_mhmrentiva_payment_gateway', 'offline');

        $result = RefundValidator::validateFullRefund($this->booking_id);

        $this->assertSame(RefundValidator::CHANNEL_WOOCOMMERCE, $result['channel']);
    }

    public function test_the_manual_booking_creator_no_longer_writes_an_empty_gateway(): void
    {
        // The refund path stopped reading this meta in the previous task, but
        // four surfaces still display or group by it: the booking-list filter
        // dropdown, ReportRepository::get_payment_method_distribution() (which
        // COALESCEs an empty value to 'unknown'), AccountRenderer, and the
        // refund metabox. A manually created booking is an offline booking and
        // should say so.
        $source = file_get_contents(
            dirname(__DIR__, 4) . '/src/Admin/Booking/Meta/ManualBookingMetaBox.php'
        );

        $this->assertIsString($source);
        $this->assertStringNotContainsString(
            "'_mhmrentiva_payment_gateway'       => '',",
            $source,
            'ManualBookingMetaBox must not seed the gateway meta with an empty string.'
        );
        $this->assertStringContainsString(
            "'_mhmrentiva_payment_gateway'       => 'offline',",
            $source
        );
    }
}
