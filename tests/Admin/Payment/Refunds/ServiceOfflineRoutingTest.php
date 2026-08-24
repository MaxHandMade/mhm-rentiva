<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Payment\Refunds;

use MHMRentiva\Admin\Payment\Core\Money;
use MHMRentiva\Admin\Payment\Refunds\Service;
use WP_UnitTestCase;

/**
 * Fix round 1 on slice 3 task 2: Service::process() and
 * Service::processFullRefund() used to read RefundValidator::decide()'s
 * 'gateway' key, a key that no longer exists now that PaymentState supplies
 * the refundable amount. The first fix repointed both reads at 'channel' --
 * decide()'s hard-coded 'woocommerce' placeholder -- which stopped the
 * TypeError but silently misrouted every booking with no WooCommerce order at
 * all into the WooCommerce branch of runOperation() -- the single method that
 * replaced both processGatewayRefund() and processGatewayFullRefund() in a
 * later round -- which then answered "WooCommerce order not found for this
 * booking" for money that was never in WooCommerce.
 *
 * PaymentState::orders() is what resolveOfflineChannel() itself already uses
 * to decide whether the offline channel is live, so deriving the gateway from
 * it -- empty orders() means offline, a non-empty list means WooCommerce --
 * cannot disagree with the facade PaymentState already is. These tests pin
 * that an offline-only booking with a genuine, proven balance now reaches the
 * 'offline' branch and actually succeeds, rather than being refused by a
 * WooCommerce-shaped error message it has no business seeing.
 *
 * No WooCommerce order is created anywhere in this file on purpose -- that
 * absence, plus payment-status proof and total/remaining meta implying money
 * was actually collected, is exactly what PaymentState::orders() being empty
 * means.
 */
final class ServiceOfflineRoutingTest extends WP_UnitTestCase
{
    private function create_offline_paid_booking(string $total, string $remaining): int
    {
        $booking_id = (int) self::factory()->post->create(array(
            'post_type'   => 'mhmrentiva_booking',
            'post_status' => 'publish',
        ));

        update_post_meta($booking_id, '_mhmrentiva_payment_status', 'paid');
        update_post_meta($booking_id, '_mhmrentiva_total_price', $total);
        update_post_meta($booking_id, '_mhmrentiva_remaining_amount', $remaining);

        return $booking_id;
    }

    public function test_a_partial_refund_on_an_offline_only_booking_succeeds_as_a_manual_refund(): void
    {
        // 1000 total, 500 remaining -> 500 collected offline, all of it refundable.
        $booking_id = $this->create_offline_paid_booking('1000', '500');
        $admin_id   = (int) self::factory()->user->create(array( 'role' => 'administrator' ));

        $result = Service::process($booking_id, Money::toMinor('200'), 'offline routing check', $admin_id);

        $this->assertSame(
            '1',
            $result['mhmrentiva_refund'] ?? null,
            'A booking with a genuine offline balance and no WooCommerce order at all must not '
                . 'be refused with a WooCommerce-shaped error. Got: '
                . ( $result['mhmrentiva_refund_msg'] ?? '' )
        );
        $this->assertSame('', $result['mhmrentiva_refund_msg'] ?? null);

        // The 'offline' branch of runOperation() is the only one that
        // stamps a manual_* id; the 'woocommerce' branch would either fail
        // ("WooCommerce order not found for this booking", writing no id at
        // all) or, if it somehow found an order, stamp a numeric WooCommerce
        // refund id. This is what actually distinguishes the two branches,
        // rather than just checking that something succeeded.
        $txn_id = (string) get_post_meta($booking_id, '_mhmrentiva_refund_txn_id', true);
        $this->assertStringStartsWith(
            'manual_',
            $txn_id,
            'A refund routed through the offline branch stamps a manual_* transaction id.'
        );
    }

    public function test_a_full_refund_on_an_offline_only_booking_succeeds_as_a_manual_refund(): void
    {
        // 1000 total, 0 remaining -> the whole 1000 was collected offline.
        $booking_id = $this->create_offline_paid_booking('1000', '0');
        $admin_id   = (int) self::factory()->user->create(array( 'role' => 'administrator' ));

        $result = Service::processFullRefund(
            $booking_id,
            'offline routing check, full refund',
            $admin_id
        );

        $this->assertSame(
            '1',
            $result['mhmrentiva_refund'] ?? null,
            'Got: ' . ( $result['mhmrentiva_refund_msg'] ?? '' )
        );

        $txn_id = (string) get_post_meta($booking_id, '_mhmrentiva_refund_txn_id', true);
        $this->assertStringStartsWith('manual_', $txn_id);
    }
}
