<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Meta;

use MHMRentiva\Admin\Booking\Meta\BookingRefundMetaBox;
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
}
