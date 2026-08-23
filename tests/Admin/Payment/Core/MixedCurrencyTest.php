<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Payment\Core;

use MHMRentiva\Admin\Booking\Helpers\CancellationHandler;
use MHMRentiva\Admin\Payment\Core\Money;
use MHMRentiva\Admin\Payment\Core\PaymentState;
use MHMRentiva\Admin\Payment\Core\RefundStatus;
use MHMRentiva\Admin\PostTypes\Logs\PostType;
use MHMRentiva\Helpers\NotificationHelper;
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
        $this->assertSame(
            Money::toMinor('100'),
            $state->paid(),
            'The single-currency sum must still work -- the guard must not over-fire.'
        );
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
        $this->assertNotEmpty($result['problems'] ?? array());
        $this->assertStringContainsString(
            'more than one currency',
            $result['problems'][0] ?? '',
            "settle_refund()'s ?string contract: a mixed-currency refusal is a problem the caller must see"
                . ' -- asserting its content, not just its presence, ties this to the actual requirement'
                . ' (review fix round 1: the text itself has no display path on any of the three AJAX'
                . ' surfaces today, but the behavioural half -- a non-null return -- still must be this'
                . " method's own refusal string, not some other branch's).",
        );

        // Review fix round 1, F1: send_refund_needs_review_email() is
        // AutoCancel's own e-mail and both of its factual claims are false
        // on this path (the booking IS cancelled here; auto-cancel was never
        // involved). This booking's own truthful e-mail must fire instead,
        // and it must print no amount at all -- the whole point of
        // isMixedCurrency() is that no summed figure is safe to quote.
        $review_mails = array_values(array_filter(
            $mails,
            static fn (array $mail): bool => str_contains($mail['subject'], 'manual refund across currencies')
        ));
        $this->assertNotEmpty(
            $review_mails,
            'The mixed-currency review notification must actually be sent, its bool checked, not discarded.'
        );
        $this->assertStringNotContainsString(
            'Amount held',
            $review_mails[0]['message'],
            'This e-mail must not quote a summed amount -- that is exactly the figure isMixedCurrency() exists'
                . ' to warn against.'
        );
        $this->assertStringNotContainsString(
            'Auto-cancel',
            $review_mails[0]['message'],
            "This booking was cancelled by this request, not by AutoCancel -- the e-mail must not tell the"
                . ' operator the wrong story.'
        );

        $autocancel_mails = array_filter(
            $mails,
            static fn (array $mail): bool => str_contains($mail['subject'], 'still holds paid money')
        );
        $this->assertEmpty(
            $autocancel_mails,
            "AutoCancel's own e-mail must not be reused for this path."
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

    // -------------------------------------------------------------------
    // Whole-branch review, F2: two operator e-mails print an amount from
    // accessors that return 0 exactly when the amount is unknowable.
    // -------------------------------------------------------------------

    /**
     * send_refund_failed_email() is routed to for a mixed-currency booking
     * deliberately (CancellationHandler.php:403's own
     * `$recovery_state->isMixedCurrency()` disjunct, proven firing by
     * test_the_post_commit_failure_email_still_fires_for_a_mixed_currency_booking
     * above) -- specifically BECAUSE money is owed and unknowable, not
     * despite it. Money::toMajor($state->refundable()) is 0 for exactly this
     * shape (Task 15), so the e-mail whose entire purpose is "money is still
     * owed" said "Amount still owed to the customer: 0.00 EUR" -- a comment
     * calling the isMixedCurrency() disjunct exists precisely so this case
     * is not silently dropped, then the body it produces said nothing was
     * owed. This calls the helper directly rather than driving the whole
     * post-commit throwable path again (that is
     * test_the_post_commit_failure_email_still_fires_for_a_mixed_currency_booking's
     * job) -- this test is only about the BODY this method itself renders
     * for that state.
     */
    public function test_the_failed_email_does_not_quote_a_zeroed_amount_for_a_mixed_currency_booking(): void
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

        $sent = NotificationHelper::send_refund_failed_email($this->booking_id);

        $this->assertTrue($sent, 'Sanity: the e-mail must actually send, or the body assertions below prove nothing.');
        $this->assertNotEmpty($mails);

        $this->assertStringNotContainsString(
            'Amount still owed to the customer: 0',
            $mails[0]['message'],
            'refundable() is zeroed for a mixed-currency booking -- printing it here states a figure that'
                . ' is false on its face for an e-mail whose whole point is that money IS still owed.'
        );
        $this->assertStringContainsString(
            'more than one currency',
            $mails[0]['message'],
            'The body must say what is true instead of a figure -- the same reason'
                . ' send_refund_mixed_currency_review_email() prints no amount at all.'
        );
    }

    /**
     * send_refund_needs_review_email() is AutoCancel's own e-mail
     * (park_paid_booking_for_review()), fired whenever a sweep finds a paid
     * WC order and refuses to touch the booking (K6) -- a decision made
     * per-order (self::is_paid($order)) with no currency-matching involved,
     * so a booking whose two paid orders sit in different currencies reaches
     * this exact e-mail too. Money::toMajor($state->paid()) is 0 for that
     * shape (Task 15), so this e-mail said "Amount held: 0.00" on a booking
     * a sweep just refused to touch specifically because it holds money.
     */
    public function test_the_needs_review_email_does_not_quote_a_zeroed_amount_for_a_mixed_currency_booking(): void
    {
        $this->make_mixed_currency_booking();

        $mails = array();
        add_filter(
            'wp_mail',
            static function (array $args) use (&$mails): array {
                $mails[] = $args;
                return $args;
            }
        );

        $sent = NotificationHelper::send_refund_needs_review_email($this->booking_id);

        $this->assertTrue($sent, 'Sanity: the e-mail must actually send, or the body assertions below prove nothing.');
        $this->assertNotEmpty($mails);

        $this->assertStringNotContainsString(
            'Amount held: 0',
            $mails[0]['message'],
            'paid() is zeroed for a mixed-currency booking -- printing it here states a figure that is'
                . ' false on its face for an e-mail whose whole point is that this booking holds paid money.'
        );
        $this->assertStringContainsString(
            'more than one currency',
            $mails[0]['message'],
            'The body must say what is true instead of a figure -- the same reason'
                . ' send_refund_mixed_currency_review_email() prints no amount at all.'
        );
    }

    // -------------------------------------------------------------------
    // Review fix round 1, F2: a refused NEEDS_REVIEW transition must not
    // be silent -- the defect this slice has already fixed twice.
    // -------------------------------------------------------------------

    /**
     * The race this test stands in for: another process already moved this
     * booking's refund_status to a value the matrix has no needs_review edge
     * from (matrix()['pending'] does not include needs_review) -- e.g. a
     * concurrent cancellation attempt that got there first. Before this fix,
     * the mixed-currency branch returned silently on a refused transition:
     * no status change, no e-mail (correctly, nothing was parked), but also
     * no log -- exactly the "states nothing about a refusal it cannot avoid
     * hitting" defect AutoCancel::park_paid_booking_for_review() and this
     * same method's own PENDING-not-recorded branch both already guard
     * against.
     */
    public function test_a_refused_needs_review_transition_for_a_mixed_currency_booking_is_logged(): void
    {
        $this->make_mixed_currency_booking();
        update_post_meta($this->booking_id, '_mhmrentiva_payment_status', 'paid');
        update_post_meta($this->booking_id, '_mhmrentiva_refund_status', RefundStatus::PENDING);

        $mails = array();
        add_filter(
            'wp_mail',
            static function (array $args) use (&$mails): array {
                $mails[] = $args;
                return $args;
            }
        );

        $this->cancel();

        $this->assertSame(
            RefundStatus::PENDING,
            RefundStatus::get($this->booking_id),
            'The matrix refused the write; the pre-existing status must stand untouched, not be silently'
                . ' overwritten.'
        );

        $review_mails = array_filter(
            $mails,
            static fn (array $mail): bool => str_contains($mail['subject'], 'manual refund across currencies')
        );
        $this->assertEmpty(
            $review_mails,
            'The transition was never recorded -- nothing was actually parked, so the review notification'
                . ' must not fire for a park that never happened.'
        );

        $logs = get_posts(array(
            'post_type'      => PostType::TYPE,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
        ));

        $found = false;
        foreach ($logs as $log) {
            if (str_contains($log->post_content, 'Mixed-currency NEEDS_REVIEW could not be recorded')) {
                $found = true;
                break;
            }
        }
        $this->assertTrue(
            $found,
            'A refused mixed-currency NEEDS_REVIEW transition must leave a trace an operator can find, not'
                . ' total silence.'
        );
    }
}
