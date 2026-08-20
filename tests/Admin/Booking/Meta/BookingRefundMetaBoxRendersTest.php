<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Meta;

use MHMRentiva\Admin\Booking\Meta\BookingRefundMetaBox;
use MHMRentiva\Tests\Support\WooCommerceFixtures;
use WP_UnitTestCase;

/**
 * Spec addition B, measured 2026-08-19.
 *
 * render() returned "No refundable payment found for this booking." unless
 * $gateway === 'offline' AND $paidKurus > 0, and $paidKurus read
 * _mhmrentiva_payment_amount -- a key with zero writers. So the box has never
 * rendered its form, for any booking, on any site. Its form is the only
 * trigger for admin_post_mhmrentiva_refund_booking, which makes
 * Actions::refund_booking() and everything under it wired but unreachable.
 *
 * The box is the OFFLINE surface: a WooCommerce booking is refunded from
 * WooCommerce's own order screen or from the deposit-management screen, and
 * showing a third path for it would give the operator two buttons with
 * different rules.
 */
final class BookingRefundMetaBoxRendersTest extends WP_UnitTestCase
{
    use WooCommerceFixtures;

    /** @var int */
    private $booking_id;

    public function setUp(): void
    {
        parent::setUp();

        $this->booking_id = (int) self::factory()->post->create(array(
            'post_type'   => 'mhmrentiva_booking',
            'post_status' => 'publish',
        ));
    }

    private function render(): string
    {
        ob_start();
        BookingRefundMetaBox::render(get_post($this->booking_id));
        return (string) ob_get_clean();
    }

    public function test_an_offline_paid_booking_gets_a_refund_form(): void
    {
        update_post_meta($this->booking_id, '_mhmrentiva_payment_status', 'paid');
        update_post_meta($this->booking_id, '_mhmrentiva_total_price', '80');
        update_post_meta($this->booking_id, '_mhmrentiva_remaining_amount', '0');

        $html = $this->render();

        $this->assertStringContainsString('mhmrentiva_refund_booking', $html);
        $this->assertStringContainsString('name="amount_kurus"', $html);
        $this->assertStringNotContainsString('No refundable payment found', $html);
    }

    public function test_an_offline_booking_with_nothing_left_says_so_instead_of_showing_a_form(): void
    {
        update_post_meta($this->booking_id, '_mhmrentiva_payment_status', 'refunded');
        update_post_meta($this->booking_id, '_mhmrentiva_total_price', '80');
        update_post_meta($this->booking_id, '_mhmrentiva_remaining_amount', '0');
        update_post_meta($this->booking_id, '_mhmrentiva_refunded_amount', \MHMRentiva\Admin\Payment\Core\Money::toMinor('80'));

        $html = $this->render();

        $this->assertStringNotContainsString('name="amount_kurus"', $html);
    }

    public function test_a_booking_with_no_payment_at_all_shows_no_form(): void
    {
        $html = $this->render();

        $this->assertStringNotContainsString('name="amount_kurus"', $html);
    }

    /**
     * The safety-critical branch: BookingRefundMetaBox.php's
     * `array() === $state->orders() ? $state->refundableManual() : 0` forces
     * $remaining to 0 the moment a paid WooCommerce order exists, so this box
     * never opens a second refund path -- with different rules -- over money
     * that already has a WooCommerce-owned or deposit-management path.
     *
     * The booking also carries the same offline meta as
     * test_an_offline_paid_booking_gets_a_refund_form -- proof-of-payment
     * status, a total, a zero remaining -- so this proves the WooCommerce
     * order is what suppresses the form, not an absence of offline data. Drop
     * the order and the same meta produces a form (that is the first test in
     * this file). See the task report for the mutation-kill evidence: dropping
     * this box's own ternary in favour of a bare refundableManual() call did
     * NOT fail this test -- PaymentState::resolveOfflineChannel() already
     * zeroes the offline channel whenever order_ids is non-empty, so the box's
     * gate is defense-in-depth, not the only backstop. Replacing $remaining
     * with paid() - refunded() (both channels, not just the offline one) DID
     * fail it, rendering a full form for this WooCommerce-order booking.
     */
    public function test_a_paid_woocommerce_order_shows_no_form_even_with_offline_data_present(): void
    {
        $this->require_woocommerce();

        update_post_meta($this->booking_id, '_mhmrentiva_payment_status', 'paid');
        update_post_meta($this->booking_id, '_mhmrentiva_total_price', '80');
        update_post_meta($this->booking_id, '_mhmrentiva_remaining_amount', '0');

        $this->create_paid_order_for_booking($this->booking_id, '80');

        $html = $this->render();

        $this->assertStringNotContainsString('name="amount_kurus"', $html);
    }

    /**
     * Slice-3 phase-close audit, item 3. render() printed "No refundable
     * payment found for this booking." for the $remaining <= 0 branch
     * unconditionally -- including the shape this test builds, where the
     * booking's WooCommerce order is fully paid and nothing has been
     * refunded from it yet, i.e. WooCommerce money IS genuinely refundable.
     * The box staying offline-only is correct and unchanged (still no
     * form, still no amount_kurus field); only the sentence was false. This
     * pins the new sentence appearing and the old, false one NOT appearing
     * for exactly that shape.
     */
    public function test_a_paid_woocommerce_order_gets_the_woocommerce_sentence_not_the_false_one(): void
    {
        $this->require_woocommerce();

        update_post_meta($this->booking_id, '_mhmrentiva_payment_status', 'paid');
        update_post_meta($this->booking_id, '_mhmrentiva_total_price', '80');
        update_post_meta($this->booking_id, '_mhmrentiva_remaining_amount', '0');

        $this->create_paid_order_for_booking($this->booking_id, '80');

        $html = $this->render();

        $this->assertStringContainsString(
            'This booking has a refundable WooCommerce payment.',
            $html,
            'A booking whose WooCommerce order is still refundable must be told so.'
        );
        $this->assertStringNotContainsString(
            'No refundable payment found for this booking.',
            $html,
            'That sentence is false for a booking whose WooCommerce money is genuinely refundable.'
        );
        $this->assertStringNotContainsString('name="amount_kurus"', $html);
    }
}
