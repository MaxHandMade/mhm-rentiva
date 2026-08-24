<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Frontend\Account;

use MHMRentiva\Admin\Frontend\Account\AccountRenderer;
use WP_UnitTestCase;

/**
 * Phase-close review of Slice 2, item 1: HybridBookingButtonTest pinned the
 * admin metabox half of this defect (Task 8), but the customer-facing
 * "Pay Remaining Amount" button in templates/account/booking-detail.php
 * asked its own bare predicate (remaining_amount > 0 && status in
 * pending/confirmed) instead of RemainingPaymentHandler::is_hybrid_booking(),
 * the guard Task 7 widened. A customer on a hybrid booking (deposit taken
 * outside WooCommerce) got a live-looking button whose click always failed
 * with an alert() carrying a staff-oriented instruction the customer cannot
 * act on ("collect the remaining balance the same way").
 *
 * This test renders the actual template rather than only pinning the predicate,
 * so it also catches a future regression in the template's own conditional --
 * which HybridBookingButtonTest, being scoped to DepositManagementAjax,
 * structurally cannot. It drives AccountRenderer::render_booking_detail(), a
 * string-returning sibling of output_booking_detail() -- the method
 * WooCommerceIntegration's account tab (WooCommerceIntegration.php:227) actually
 * calls in production. render_booking_detail() itself has no production caller;
 * it exists here only because it returns markup a test can assert on, where
 * output_booking_detail() echoes. Both feed the same get_booking_detail_data()
 * into the same template file (templates/account/booking-detail.php), which is
 * what makes this coverage real rather than a detour: a reader who greps for
 * render_booking_detail() and finds only this test should not conclude the test
 * is dead, only that its production twin is the one carrying callers.
 *
 * @covers \MHMRentiva\Admin\Frontend\Account\AccountRenderer::render_booking_detail
 */
final class BookingDetailPayRemainingButtonTest extends WP_UnitTestCase
{
    private int $customer_id;

    public function setUp(): void
    {
        parent::setUp();
        $this->customer_id = self::factory()->user->create( array( 'role' => 'customer' ) );
        wp_set_current_user( $this->customer_id );
    }

    private function createBooking( string $payment_status ): int
    {
        $booking_id = self::factory()->post->create( array( 'post_type' => 'mhmrentiva_booking' ) );

        update_post_meta( $booking_id, '_mhmrentiva_customer_user_id', $this->customer_id );
        update_post_meta( $booking_id, '_mhmrentiva_status', 'confirmed' );
        update_post_meta( $booking_id, '_mhmrentiva_payment_type', 'deposit' );
        update_post_meta( $booking_id, '_mhmrentiva_deposit_amount', '100.00' );
        update_post_meta( $booking_id, '_mhmrentiva_total_price', '300.00' );
        update_post_meta( $booking_id, '_mhmrentiva_remaining_amount', '200.00' );
        update_post_meta( $booking_id, '_mhmrentiva_payment_status', $payment_status );

        return $booking_id;
    }

    /**
     * The exact shape this review found: a hybrid booking (deposit proven paid
     * outside WooCommerce, no WooCommerce order behind it) must not show the
     * customer a button that the server-side guard will always refuse.
     */
    public function test_hybrid_booking_shows_a_note_instead_of_a_button(): void
    {
        $booking_id = $this->createBooking( 'paid' );

        $html = AccountRenderer::render_booking_detail( $booking_id, true );

        $this->assertStringNotContainsString(
            'rv-pay-remaining-btn',
            $html,
            'A hybrid booking must not render a button the AJAX guard always refuses.'
        );
        $this->assertStringNotContainsString(
            'collect the remaining balance the same way',
            $html,
            'The staff-oriented admin string must never reach the customer template.'
        );
        $this->assertStringContainsString(
            'we will contact you directly to collect the remaining balance',
            $html,
            'The customer must see an explanation written for them, not a silently vanished row.'
        );
    }

    /**
     * An ordinary manual booking with no proven offline payment yet is the
     * ordinary "send a payment link" flow and must keep offering the button --
     * this is the negative control, so the fix does not over-hide it.
     */
    public function test_ordinary_unpaid_booking_still_shows_the_button(): void
    {
        $booking_id = $this->createBooking( 'pending' );

        $html = AccountRenderer::render_booking_detail( $booking_id, true );

        $this->assertStringContainsString(
            'rv-pay-remaining-btn',
            $html,
            'A booking with no proven offline payment must keep the working button.'
        );
    }
}
