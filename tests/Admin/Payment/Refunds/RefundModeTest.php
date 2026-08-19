<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Payment\Refunds;

use MHMRentiva\Admin\Payment\Refunds\RefundValidator;
use MHMRentiva\Tests\Support\WooCommerceFixtures;
use WP_UnitTestCase;

/**
 * M-01, measured on the dev site 2026-08-19 before this task existed.
 *
 * validateGatewaySpecific() refused a refund when ! $order->is_editable().
 * WooCommerce defines is_editable() as true for pending, on-hold and
 * auto-draft only -- it answers "may the Edit Order screen change the line
 * items", not "is there money to give back". The result was exactly inverted:
 * booking 9471 (order processing, date_paid set, 1200.000 still refundable)
 * was refused, while booking 9474 (order on-hold, nothing collected) was the
 * one and only booking on the site that passed.
 *
 * The refundability question belongs to PaymentState; what is left here is a
 * classification, not a veto: can the gateway send this money back by itself,
 * or must a human do it.
 */
final class RefundModeTest extends WP_UnitTestCase
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
    }

    public function test_the_veto_is_gone_from_the_validator(): void
    {
        // The end-to-end assertion -- "a paid processing order validates" --
        // lives in Task 2's RefundAmountSourceTest, because until the amount
        // source moves off the zero-writer meta key the chain still refuses
        // this booking for a DIFFERENT reason. Asserting it here would commit a
        // deliberately red test and make this task's own gate meaningless.
        //
        // What this task can prove on its own: the shape that produced the
        // inverted verdict is no longer in the file.
        $source = file_get_contents(
            dirname(__DIR__, 4) . '/src/Admin/Payment/Refunds/RefundValidator.php'
        );

        $this->assertIsString($source);
        $this->assertStringNotContainsString(
            'is_editable()',
            $source,
            'is_editable() answers a question about the Edit Order screen, not about refundability.'
        );
        $this->assertStringNotContainsString(
            'function validateGatewaySpecific',
            $source
        );
    }

    public function test_an_order_with_no_resolvable_gateway_classifies_as_manual(): void
    {
        // A bank-transfer / cheque style order: the payment method never
        // resolves to a gateway object that supports refunds.
        $order = $this->create_paid_order_for_booking($this->booking_id, '120');
        $order->set_payment_method('mhm_no_such_gateway');
        $order->save();

        $this->assertSame(
            RefundValidator::MODE_MANUAL,
            RefundValidator::modeForOrder($order),
            'wc_get_payment_gateway_by_order() returns false for an unknown method; '
                . 'the fail-safe classification is manual, never auto.'
        );
    }

    public function test_the_mode_constants_are_the_two_values_the_service_branches_on(): void
    {
        $this->assertSame('auto', RefundValidator::MODE_AUTO);
        $this->assertSame('manual', RefundValidator::MODE_MANUAL);
    }
}
