<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integrations\WooCommerce;

use MHMRentiva\Admin\Booking\Actions\DepositManagementAjax;
use WP_UnitTestCase;

/**
 * Slice 2 widened the hybrid guard, so more bookings now refuse a remaining
 * payment link. The admin screen offered the button anyway: a live-looking
 * control whose only outcome is an error string.
 */
final class HybridBookingButtonTest extends WP_UnitTestCase
{
    public function test_the_screen_does_not_offer_a_link_it_will_refuse(): void
    {
        $booking_id = $this->factory->post->create(array( 'post_type' => 'mhmrentiva_booking' ));
        update_post_meta($booking_id, '_mhmrentiva_payment_status', 'paid');
        update_post_meta($booking_id, '_mhmrentiva_total_price', '300.00');
        update_post_meta($booking_id, '_mhmrentiva_remaining_amount', '200.00');

        $this->assertFalse(
            DepositManagementAjax::can_send_remaining_payment_link($booking_id),
            'The deposit was taken outside WooCommerce; the link would be refused.'
        );
    }

    public function test_an_ordinary_unpaid_manual_booking_still_offers_the_link(): void
    {
        $booking_id = $this->factory->post->create(array( 'post_type' => 'mhmrentiva_booking' ));
        update_post_meta($booking_id, '_mhmrentiva_total_price', '300.00');
        update_post_meta($booking_id, '_mhmrentiva_remaining_amount', '200.00');

        $this->assertTrue(
            DepositManagementAjax::can_send_remaining_payment_link($booking_id),
            'No proven payment yet is the ordinary manual-booking flow and stays supported.'
        );
    }
}
