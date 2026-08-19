<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Payment\Refunds;

use MHMRentiva\Admin\Payment\Core\Money;
use MHMRentiva\Admin\Payment\Core\PaymentState;
use MHMRentiva\Admin\Payment\Refunds\RefundValidator;
use MHMRentiva\Tests\Support\WooCommerceFixtures;
use WP_UnitTestCase;

/**
 * H-03 and H-04.
 *
 * H-03: the amount came from _mhmrentiva_payment_amount, a meta key with zero
 * writers in src/ (measured 2026-08-19 -- the only three writers in the tree
 * are test fixtures). Every real booking read 0 and was told "Paid amount not
 * found".
 *
 * H-04: validateFullRefund() asked validateAmount( $bookingId, 0 ) and used 0
 * as a "means full" sentinel -- but the very validator it called refuses
 * $amountKurus <= 0 with "Invalid refund amount". So even with a paid amount
 * present, a full refund could never validate. Two independent reasons, one
 * call.
 */
final class RefundAmountSourceTest extends WP_UnitTestCase
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

    public function test_a_paid_processing_order_finally_validates(): void
    {
        // The end-to-end verdict, held back from Task 1 so that task could end
        // green. This is the shape measured on the dev site as booking 9471:
        // order processing, date_paid set, a real balance still refundable,
        // and refused. Both blockers -- the is_editable() veto (Task 1) and the
        // zero-writer amount key (this task) -- have to be gone for it to pass.
        $order = $this->create_paid_order_for_booking($this->booking_id, '120');
        $order->update_status('processing');

        $result = RefundValidator::validatePartialRefund($this->booking_id, Money::toMinor('10'));

        $this->assertTrue(
            $result['valid'],
            'A paid processing order with a refundable balance must not be refused. Got: '
                . ( $result['message'] ?? '' )
        );
    }

    public function test_a_full_refund_validates_without_any_payment_amount_meta(): void
    {
        $this->create_paid_order_for_booking($this->booking_id, '120');

        $this->assertSame(
            '',
            (string) get_post_meta($this->booking_id, '_mhmrentiva_payment_amount', true),
            'Guard: this test is only meaningful while the retired key is unwritten.'
        );

        $result = RefundValidator::validateFullRefund($this->booking_id);

        $this->assertTrue($result['valid'], (string) ( $result['message'] ?? '' ));
        $this->assertSame(
            Money::toMinor('120'),
            $result['amount'],
            'The full-refund amount is the whole refundable balance, read from PaymentState.'
        );
    }

    public function test_the_amount_tracks_woocommerce_after_a_partial_refund(): void
    {
        $order = $this->create_paid_order_for_booking($this->booking_id, '120');

        wc_create_refund(array(
            'order_id'       => $order->get_id(),
            'amount'         => 20,
            'refund_payment' => false,
        ));

        $result = RefundValidator::validateFullRefund($this->booking_id);

        $this->assertTrue($result['valid'], (string) ( $result['message'] ?? '' ));
        $this->assertSame(
            Money::toMinor('100'),
            $result['amount'],
            'After refunding 20 of 120, the remaining balance is 100 -- not the original total.'
        );
    }

    public function test_a_partial_refund_over_the_remaining_balance_is_refused(): void
    {
        $this->create_paid_order_for_booking($this->booking_id, '120');

        $result = RefundValidator::validatePartialRefund(
            $this->booking_id,
            Money::toMinor('120') + 1
        );

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['message']);
    }

    public function test_a_fully_refunded_booking_is_refused_with_nothing_left(): void
    {
        $order = $this->create_paid_order_for_booking($this->booking_id, '120');

        wc_create_refund(array(
            'order_id'       => $order->get_id(),
            'amount'         => 120,
            'refund_payment' => false,
        ));

        $result = RefundValidator::validateFullRefund($this->booking_id);

        $this->assertFalse($result['valid']);
    }

    public function test_the_validated_state_is_handed_to_the_caller(): void
    {
        $this->create_paid_order_for_booking($this->booking_id, '120');

        $result = RefundValidator::validateFullRefund($this->booking_id);

        $this->assertInstanceOf(
            PaymentState::class,
            $result['state'],
            'Service must refund against the same snapshot validation was decided on, '
                . 'not a second resolve.'
        );
    }

    public function test_a_nonexistent_booking_is_told_it_does_not_exist(): void
    {
        // Order of checks, not an edge case. PaymentState::forBooking() on a
        // missing id resolves to an empty state, so an amount-first chain would
        // answer "refund amount exceeds remaining balance" -- true, useless,
        // and it sends the operator looking at the wrong thing.
        $result = RefundValidator::validatePartialRefund(999999, Money::toMinor('10'));

        $this->assertFalse($result['valid']);
        // Compared through __() on purpose: the messages are translated and the
        // dev site's WP-CLI answers in Turkish. A literal English assertion here
        // passes or fails on the locale, not on the behaviour.
        $this->assertSame(__('Invalid booking type', 'mhm-rentiva'), $result['message']);
    }

    public function test_a_booking_with_nothing_left_says_so_rather_than_naming_the_amount(): void
    {
        $order = $this->create_paid_order_for_booking($this->booking_id, '120');

        wc_create_refund(array(
            'order_id'       => $order->get_id(),
            'amount'         => 120,
            'refund_payment' => false,
        ));

        // Measured, not assumed: WooCommerceBridge::handle_order_refunded() is
        // wired to woocommerce_refund_created and syncs
        // _mhmrentiva_payment_status to 'refunded' the moment a WooCommerce
        // refund covers the whole paid total -- it fires for real here, ahead
        // of validatePartialRefund(). decide() checks payment status before the
        // balance, by design (see decide()'s docblock), so this booking is
        // refused there rather than by the refundable() <= 0 branch. Either way
        // the message names no amount, which is what this test is really
        // pinning.
        $result = RefundValidator::validatePartialRefund($this->booking_id, Money::toMinor('10'));

        $this->assertFalse($result['valid']);
        $this->assertSame(__('Already fully refunded', 'mhm-rentiva'), $result['message']);
    }
}
