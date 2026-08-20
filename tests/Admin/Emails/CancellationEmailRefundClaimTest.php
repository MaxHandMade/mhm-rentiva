<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Emails;

use WP_UnitTestCase;

/**
 * wc-refunds.md, anti-pattern 2: telling the customer a manual refund was
 * "credited to the original payment method" is a false statement, because a
 * manual refund never touches the gateway. The cancellation e-mail is sent
 * BEFORE the money step and cannot know the mode, so the sentence cannot be
 * repaired in place -- it moves to the refund e-mail, which does know
 * (Slice 3's mode_text).
 *
 * The template is rendered directly rather than through wp_mail so the
 * assertion is about the text, not about delivery.
 */
final class CancellationEmailRefundClaimTest extends WP_UnitTestCase
{
    private function render(int $booking_id): string
    {
        $vehicle_name  = 'Test Vehicle';
        $pickup_date   = '2026-09-01';
        $dropoff_date  = '2026-09-03';
        $customer_name = 'Renter';
        $reason        = 'change of plans';

        ob_start();
        include MHMRENTIVA_PLUGIN_DIR . 'templates/emails/booking-cancelled.html.php';

        return (string) ob_get_clean();
    }

    public function test_the_cancellation_mail_does_not_promise_the_original_payment_method(): void
    {
        $booking_id = (int) self::factory()->post->create(array(
            'post_type'   => 'mhmrentiva_booking',
            'post_status' => 'publish',
        ));

        update_post_meta($booking_id, '_mhmrentiva_payment_status', 'paid');

        $html = $this->render($booking_id);

        $this->assertStringNotContainsString(
            'original payment method',
            $html,
            'A manual refund never reaches the original payment method; the promise cannot be made here.'
        );
        $this->assertStringNotContainsString(
            'has been initiated',
            $html,
            'At the moment this mail is sent, nothing has been initiated yet.'
        );
    }

    public function test_a_paid_booking_still_gets_told_a_refund_notice_may_follow(): void
    {
        $booking_id = (int) self::factory()->post->create(array(
            'post_type'   => 'mhmrentiva_booking',
            'post_status' => 'publish',
        ));

        update_post_meta($booking_id, '_mhmrentiva_payment_status', 'paid');

        $this->assertStringContainsString(
            'separate refund notice',
            $this->render($booking_id),
            'Dropping the panel entirely would leave a paying customer with no signal at all.'
        );
    }

    /**
     * Finding E: the template gated the panel on `$payment_status === 'paid'`
     * alone, a narrower question than the one CancellationHandler::process_refund()
     * asks before it moves money (spec §5.3, widened by this branch to also
     * cover a PARTIAL refund's remainder). A partially_refunded booking is
     * this branch's headline case -- it is exactly the booking that now gets
     * its remainder back -- and it got no panel at all before this fix.
     */
    public function test_a_partially_refunded_booking_still_gets_told_a_refund_notice_may_follow(): void
    {
        $booking_id = (int) self::factory()->post->create(array(
            'post_type'   => 'mhmrentiva_booking',
            'post_status' => 'publish',
        ));

        update_post_meta($booking_id, '_mhmrentiva_payment_status', 'partially_refunded');

        $this->assertStringContainsString(
            'separate refund notice',
            $this->render($booking_id),
            'The branch widened the money gate to cover partially_refunded; the template must ask the same question.'
        );
    }

    public function test_an_unpaid_booking_gets_no_refund_panel(): void
    {
        $booking_id = (int) self::factory()->post->create(array(
            'post_type'   => 'mhmrentiva_booking',
            'post_status' => 'publish',
        ));

        update_post_meta($booking_id, '_mhmrentiva_payment_status', 'pending');

        $this->assertStringNotContainsString('separate refund notice', $this->render($booking_id));
    }
}
