<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Payment\Core;

use MHMRentiva\Admin\Booking\Helpers\CancellationHandler;
use MHMRentiva\Admin\Payment\Core\PaymentState;
use MHMRentiva\Admin\Payment\Core\RefundStatus;
use MHMRentiva\Tests\Support\WooCommerceFixtures;
use WP_UnitTestCase;

/**
 * Task 15 (slice 5): refuse a mixed-currency booking instead of quietly
 * adding two currencies together.
 *
 * This ecosystem ships a per-order currency switcher, so a booking's deposit
 * and remaining-payment orders can legitimately carry different currencies.
 * Summing them (PaymentState::paid()/refunded()/refundable*()) produces a
 * number with no meaning wearing whichever currency happened to arrive
 * first -- and the refund flow reading a resulting refundable() of 0 as
 * "nothing to refund" closes a real obligation as not_required, silently.
 *
 * @covers \MHMRentiva\Admin\Payment\Core\PaymentState::isMixedCurrency
 * @covers \MHMRentiva\Admin\Booking\Helpers\CancellationHandler::settle_refund
 */
final class MixedCurrencyTest extends WP_UnitTestCase
{
    use WooCommerceFixtures;

    private int $admin_id;
    private int $booking_id;

    public function setUp(): void
    {
        parent::setUp();

        $this->require_woocommerce();

        $this->admin_id   = (int) self::factory()->user->create(array( 'role' => 'administrator' ));
        $this->booking_id = (int) self::factory()->post->create(array(
            'post_type'   => 'mhmrentiva_booking',
            'post_status' => 'publish',
        ));

        update_post_meta($this->booking_id, '_mhmrentiva_status', 'confirmed');
        update_post_meta($this->booking_id, '_mhmrentiva_vehicle_id', (int) self::factory()->post->create(array(
            'post_type' => 'mhmrentiva_vehicle',
        )));
        update_post_meta($this->booking_id, '_mhmrentiva_pickup_date', gmdate('Y-m-d', strtotime('+10 days')));
        update_post_meta($this->booking_id, '_mhmrentiva_dropoff_date', gmdate('Y-m-d', strtotime('+12 days')));
    }

    public function tearDown(): void
    {
        // RefundLock rows are written with a raw $wpdb->query(); see
        // CancellationInitiatesRefundTest's tearDown() for why a row can
        // outlive this test's own rollback otherwise.
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'mhmrentiva_refund_lock_%'");

        parent::tearDown();
    }

    /**
     * Two orders on the same booking, deliberately in different currencies --
     * the per-order currency switcher shape this task exists for, not a
     * contrived one.
     */
    private function make_mixed_currency_booking(): void
    {
        $deposit   = $this->create_paid_order_for_booking($this->booking_id, '30');
        $remaining = $this->create_paid_order_for_booking($this->booking_id, '70');
        update_post_meta($this->booking_id, '_mhmrentiva_remaining_order_id', $remaining->get_id());

        $deposit->set_currency('TRY');
        $deposit->save();

        $remaining->set_currency('EUR');
        $remaining->save();
    }

    private function cancel(): array
    {
        wp_set_current_user($this->admin_id);

        $result = CancellationHandler::cancel_booking($this->booking_id, $this->admin_id, 'currency mismatch', true);

        return is_array($result) ? $result : array();
    }

    // -------------------------------------------------------------------
    // Plan Step 1, assertion 1
    // -------------------------------------------------------------------

    public function test_a_mixed_currency_booking_is_flagged_and_reports_nothing_refundable(): void
    {
        $this->make_mixed_currency_booking();

        $state = PaymentState::forBooking($this->booking_id);

        $this->assertTrue(
            $state->isMixedCurrency(),
            'Two paid orders in different currencies must be detected as mixed.'
        );
        $this->assertSame(
            0,
            $state->refundable(),
            'Summing across currencies has no meaning; refundable() must refuse to answer rather than'
                . ' report a number labelled with whichever currency happened to be first.'
        );
        $this->assertSame(0, $state->paid(), 'paid() is a reporting figure and must be zeroed the same way.');
        $this->assertSame(0, $state->refunded());
    }

    /**
     * The single-currency control: two paid orders that do NOT trigger the
     * guard. Without this, a bug that marked every multi-order booking mixed
     * (not just currency-mismatched ones) would pass the test above and go
     * unnoticed.
     */
    public function test_two_orders_in_the_same_currency_are_not_mixed(): void
    {
        $deposit   = $this->create_paid_order_for_booking($this->booking_id, '30');
        $remaining = $this->create_paid_order_for_booking($this->booking_id, '70');
        update_post_meta($this->booking_id, '_mhmrentiva_remaining_order_id', $remaining->get_id());

        $deposit->set_currency('TRY');
        $deposit->save();
        $remaining->set_currency('TRY');
        $remaining->save();

        $state = PaymentState::forBooking($this->booking_id);

        $this->assertFalse($state->isMixedCurrency());
        $this->assertSame(10000, $state->paid(), 'The single-currency sum must still work -- the guard must not over-fire.');
    }

    // -------------------------------------------------------------------
    // Plan Step 1, assertion 2 (end to end) + correction #2's two
    // payment_status shapes
    // -------------------------------------------------------------------

    /**
     * The shape a real per-order currency switcher checkout leaves behind:
     * payment_status claims 'paid'. Before this task, refundable() summed to
     * a meaningless total; if that total happened to net to <= 0 the
     * cancellation closed as not_required and the obligation vanished
     * silently.
     */
    public function test_cancelling_a_mixed_currency_booking_with_a_paid_status_reaches_needs_review(): void
    {
        $this->make_mixed_currency_booking();
        update_post_meta($this->booking_id, '_mhmrentiva_payment_status', 'paid');

        $mails = array();
        add_filter(
            'wp_mail',
            static function (array $args) use (&$mails): array {
                $mails[] = $args;
                return $args;
            }
        );

        $result = $this->cancel();

        $this->assertSame(
            RefundStatus::NEEDS_REVIEW,
            RefundStatus::get($this->booking_id),
            'A mixed-currency booking must be flagged for a human, never closed as not_required.'
        );
        $this->assertNotSame(RefundStatus::NOT_REQUIRED, RefundStatus::get($this->booking_id));
        $this->assertNotEmpty(
            $result['problems'] ?? array(),
            "settle_refund()'s ?string contract (correction #1): a mixed-currency refusal is a problem the"
                . ' caller must see, not a discarded false.'
        );

        $review_mails = array_filter(
            $mails,
            static fn (array $mail): bool => str_contains($mail['subject'], 'still holds paid money')
        );
        $this->assertNotEmpty(
            $review_mails,
            'The needs_review notification must actually be sent, its bool checked, not discarded.'
        );
    }

    /**
     * Correction #2 (T15-R1), the shape the plan's own design could have made
     * unreachable: payment_status is NOT 'paid'/'partially_refunded'. Before
     * widening process_refund()'s $has_money gate with isMixedCurrency(),
     * paid() > 0 was false (zeroed for mixed) AND the in_array() check was
     * false here, so process_refund() returned null before settle_refund()
     * ever ran -- the mixed-currency guard silently never fired.
     */
    public function test_cancelling_a_mixed_currency_booking_reaches_needs_review_even_without_a_paid_status_claim(): void
    {
        $this->make_mixed_currency_booking();
        // Deliberately NOT 'paid' or 'partially_refunded' -- e.g. a booking
        // whose payment_status meta was never (re)written to reflect the
        // second, differently-currencied order.
        update_post_meta($this->booking_id, '_mhmrentiva_payment_status', 'pending');

        $this->cancel();

        $this->assertSame(
            RefundStatus::NEEDS_REVIEW,
            RefundStatus::get($this->booking_id),
            "The mixed-currency guard must run regardless of the payment_status meta shape -- it is gated on"
                . ' isMixedCurrency(), not on a status string.'
        );
    }

    // -------------------------------------------------------------------
    // Correction #5: the post-commit operator e-mail gate
    // -------------------------------------------------------------------

    /**
     * cancel_booking()'s post-commit recovery block sends the operator a
     * failure e-mail when refundable() > 0. That gate was correct the day it
     * was written (14a) -- but Task 15 zeroes refundable() for exactly the
     * booking shape where a human genuinely is needed. Without the
     * isMixedCurrency() disjunct, a post-commit throwable on a mixed-currency
     * booking would silence this e-mail precisely when it matters most.
     */
    public function test_the_post_commit_failure_email_still_fires_for_a_mixed_currency_booking(): void
    {
        $this->make_mixed_currency_booking();
        update_post_meta($this->booking_id, '_mhmrentiva_payment_status', 'paid');
        update_post_meta($this->booking_id, '_mhmrentiva_customer_email', 'customer@example.com');

        add_action(
            'mhmrentiva_booking_cancelled',
            static function (): void {
                throw new \RuntimeException('listener exploded');
            }
        );

        $mails = array();
        add_filter(
            'wp_mail',
            static function (array $args) use (&$mails): array {
                $mails[] = $args;
                return $args;
            }
        );

        $this->cancel();

        $failure_mails = array_filter(
            $mails,
            static fn (array $mail): bool => str_contains($mail['subject'], 'problem completing its refund')
        );

        $this->assertNotEmpty(
            $failure_mails,
            'A mixed-currency booking has refundable() === 0 by design (Task 15), but it IS a booking with'
                . ' money at stake -- the operator failure e-mail must not be silenced by that zero.'
        );
    }
}
