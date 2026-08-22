<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Meta;

use MHMRentiva\Admin\Booking\Meta\BookingRefundMetaBox;
use MHMRentiva\Tests\Support\WooCommerceFixtures;
use WP_UnitTestCase;

/**
 * Spec addition B, measured 2026-08-19; nested-form regression fixed
 * 2026-08-20 (phase-close browser verification); the deposit-screen link
 * itself corrected the same day (fix round 1).
 *
 * render() returned "No refundable payment found for this booking." unless
 * $gateway === 'offline' AND $paidKurus > 0, and $paidKurus read
 * _mhmrentiva_payment_amount -- a key with zero writers. So the box had never
 * rendered its form, for any booking, on any site, until that was fixed --
 * at which point a browser (not a string assertion) showed the form itself
 * was broken: a metabox renders INSIDE WordPress's own #post form, and HTML
 * forbids nested forms. The browser's parser discarded this box's <form> tag
 * and adopted its hidden `action` field into WordPress's own form, so
 * clicking Refund submitted `editpost` and the booking's Update button
 * stopped saving. The box no longer prints a <form> at all; it links to the
 * deposit-management box, which has its own working, non-nested refund
 * trigger (wp_ajax_mhmrentiva_deposit_process_refund).
 *
 * Round 1: that link was itself unconditional, and BookingDepositMetaBox
 * only prints its "Process Refund" button when payment_status === 'paid'
 * AND booking_status === 'cancelled' -- measured live, a paid-but-not-
 * cancelled offline booking renders the deposit box with zero buttons.
 * render() now mirrors that same gate: the link only appears where the
 * button genuinely exists; every other shape in this branch gets a sentence
 * stating the cancellation precondition instead of a route that isn't there.
 *
 * The box is the OFFLINE surface: a WooCommerce booking is refunded from
 * WooCommerce's own order screen or from the deposit-management screen, and
 * showing a third path for it would give the operator two buttons with
 * different rules.
 *
 * Task 9 (slice 5) fix round 1 added a further condition to the link:
 * MoneyAuthorization::mayMoveMoney(), the same predicate the deposit box's
 * own button asks, so the two can never drift on the actor dimension after
 * already having drifted twice on the state one (see RefundGateAgreementTest,
 * whose whole job is proving they cannot). This test file's ambient user was
 * never set before Task 9 -- PHPUnit's default (0) always failed
 * MoneyAuthorization's hard floor -- so tests below that assert the link
 * renders (the 'link branch' shape) would now anchor the drift by failing to
 * notice the link no longer renders for anyone at all. setUp() below pins an
 * administrator for that reason; tests asserting the link does NOT render
 * are unaffected either way, since booking state alone already forces that.
 */
final class BookingRefundMetaBoxRendersTest extends WP_UnitTestCase
{
    use WooCommerceFixtures;

    /** @var int */
    private $booking_id;

    public function setUp(): void
    {
        parent::setUp();

        wp_set_current_user((int) self::factory()->user->create(array( 'role' => 'administrator' )));

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

    /**
     * BookingDepositMetaBox::can_refund_from_deposit_screen() is what decides
     * whether that screen prints a "Process Refund" button at all. This is a
     * shape it answers yes for -- the one case where a route genuinely exists
     * -- so the box must link to it, not merely describe the precondition.
     *
     * _mhmrentiva_payment_type is set here for a reason found by the surface
     * round: without it the deposit box returns early with the "old system"
     * notice and prints no buttons, so this fixture used to describe a booking
     * whose route did not exist while asserting that the box link to it. Both
     * copies of the gate were wrong in the same direction, so the test passed.
     */
    public function test_a_paid_cancelled_offline_booking_gets_the_summary_and_the_deposit_link(): void
    {
        update_post_meta($this->booking_id, '_mhmrentiva_payment_status', 'paid');
        update_post_meta($this->booking_id, '_mhmrentiva_status', 'cancelled');
        update_post_meta($this->booking_id, '_mhmrentiva_payment_type', 'full');
        update_post_meta($this->booking_id, '_mhmrentiva_total_price', '80');
        update_post_meta($this->booking_id, '_mhmrentiva_remaining_amount', '0');

        $html = $this->render();

        $this->assertStringContainsString('Remaining refundable', $html);
        $this->assertStringContainsString(
            'Process this refund from the deposit-management screen.',
            $html,
            'A paid, cancelled booking has a genuine route to the deposit-management screen; the box must link to it.'
        );
        $this->assertStringNotContainsString(
            'refunds for this booking are recorded from',
            $html,
            'The precondition sentence is for bookings with no route -- this one has one, and gets the link instead.'
        );
        $this->assertStringNotContainsString('No refundable payment found', $html);
    }

    /**
     * Task 9 (slice 5) fix round 1: same fixture as the test above -- a
     * genuine route exists -- but the ambient actor is unattributed
     * (wp_set_current_user(0) overrides this file's class-wide administrator
     * pin locally, for this test only). MoneyAuthorization::mayMoveMoney()'s
     * hard floor must suppress the link even though the state predicate
     * alone would show it, mirroring the button's own behaviour
     * (NoUnguardedRefundEntryPointTest) so the two surfaces cannot drift on
     * the actor dimension the way they already drifted twice on the state
     * one.
     */
    public function test_an_unauthorized_actor_gets_no_link_even_for_an_otherwise_routable_booking(): void
    {
        update_post_meta($this->booking_id, '_mhmrentiva_payment_status', 'paid');
        update_post_meta($this->booking_id, '_mhmrentiva_status', 'cancelled');
        update_post_meta($this->booking_id, '_mhmrentiva_payment_type', 'full');
        update_post_meta($this->booking_id, '_mhmrentiva_total_price', '80');
        update_post_meta($this->booking_id, '_mhmrentiva_remaining_amount', '0');

        wp_set_current_user(0);

        $html = $this->render();

        $this->assertStringNotContainsString(
            'Process this refund from the deposit-management screen.',
            $html,
            'An unattributed actor fails the MoneyAuthorization hard floor; the link must not render for them even though the state predicate alone would allow it.'
        );
    }

    /**
     * Fix round 1 (2026-08-20): render() used to point at the
     * deposit-management screen unconditionally. Measured live: for a paid
     * offline booking that is NOT cancelled -- the common case, e.g. a
     * completed rental with a partial refund owed -- that screen's
     * "Process Refund" button does not exist (BookingDepositMetaBox gates it
     * on booking_status === 'cancelled' too), so the link led nowhere. The
     * box must now say the amount is refundable and name the precondition
     * instead of implying a route that is not there.
     *
     * This is deliberately the SAME payment/amount meta as the cancelled
     * test above, with only booking_status different, so it proves
     * booking_status alone decides which sentence renders -- not an
     * absence of offline data.
     *
     * The sentence itself stopped naming cancellation in the surface round:
     * the predicate has four conditions and a cancelled-but-partially-refunded
     * booking also lands here, for which "once the booking is cancelled" was
     * simply false. It now states where refunds are recorded without claiming
     * why this particular booking does not qualify.
     */
    public function test_a_paid_but_not_cancelled_offline_booking_gets_the_precondition_sentence(): void
    {
        update_post_meta($this->booking_id, '_mhmrentiva_payment_status', 'paid');
        update_post_meta($this->booking_id, '_mhmrentiva_status', 'confirmed');
        update_post_meta($this->booking_id, '_mhmrentiva_payment_type', 'full');
        update_post_meta($this->booking_id, '_mhmrentiva_total_price', '80');
        update_post_meta($this->booking_id, '_mhmrentiva_remaining_amount', '0');

        $html = $this->render();

        $this->assertStringContainsString('Remaining refundable', $html);
        $this->assertStringContainsString(
            'This amount is refundable; refunds for this booking are recorded from the deposit-management screen.',
            $html,
            'No refund button exists for a paid, non-cancelled booking on the deposit-management screen; state the precondition, not a route.'
        );
        $this->assertStringNotContainsString(
            'once the booking is cancelled',
            $html,
            'That wording is false for the cancelled-but-partially-refunded shape that also reaches this branch.'
        );
        $this->assertStringNotContainsString(
            'Process this refund from the deposit-management screen.',
            $html,
            'That sentence claims a route (a clickable link to a working button) that does not exist for this booking; the two branches must not collapse to the same string.'
        );
        $this->assertStringNotContainsString('No refundable payment found', $html);
    }

    /**
     * The regression lock.
     *
     * A metabox is echoed into the middle of WordPress's own #post <form>
     * on the booking edit screen. HTML does not allow nested forms: a
     * browser's parser discards a <form> tag opened inside another form and
     * adopts that form's fields into the OUTER (WordPress) form instead.
     * Measured live: with the old markup, #post ended up with two
     * name="action" fields ("editpost" and "mhmrentiva_refund_booking"),
     * PHP took the last one, and clicking Update on the booking silently
     * stopped saving -- while the refund button submitted WordPress's own
     * form and never reached admin_post_mhmrentiva_refund_booking at all.
     * A PHPUnit assertion on the HTML *string* cannot see any of this --
     * strings don't get parsed -- which is exactly how the original form
     * shipped with green tests. This asserts on the string the only thing a
     * string assertion CAN prove: that no <form> and no name="action" field
     * is emitted by this box, ever, for a shape where money is offline and
     * refundable.
     *
     * Both branches, not one. Until the surface round this exercised only the
     * else branch (the precondition sentence), so a <form> reintroduced around
     * the LINK -- the branch that actually replaced the deleted form, and the
     * likelier place for one to come back -- would not have failed anything.
     * The two shapes below differ only in booking status, which is what
     * decides the branch.
     *
     * @dataProvider provide_both_gate_branches
     */
    public function test_the_box_emits_no_nested_form_and_no_action_field(string $booking_status, string $branch): void
    {
        update_post_meta($this->booking_id, '_mhmrentiva_payment_status', 'paid');
        update_post_meta($this->booking_id, '_mhmrentiva_status', $booking_status);
        update_post_meta($this->booking_id, '_mhmrentiva_payment_type', 'full');
        update_post_meta($this->booking_id, '_mhmrentiva_total_price', '80');
        update_post_meta($this->booking_id, '_mhmrentiva_remaining_amount', '0');

        $html = $this->render();

        // Prove the shape really did take the branch it claims to, so a gate
        // change that quietly collapses both shapes into one branch cannot
        // leave the other untested while this test still passes.
        $this->assertStringContainsString($branch, $html, 'This shape must render the branch under test.');

        $this->assertStringNotContainsString('<form', $html);
        $this->assertStringNotContainsString('name="action"', $html);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public function provide_both_gate_branches(): array
    {
        return array(
            'link branch (a route exists)'      => array(
                'cancelled',
                'Process this refund from the deposit-management screen.',
            ),
            'sentence branch (no route)'        => array(
                'confirmed',
                'This amount is refundable; refunds for this booking are recorded from the deposit-management screen.',
            ),
        );
    }

    public function test_an_offline_booking_with_nothing_left_says_so_instead_of_showing_a_link(): void
    {
        update_post_meta($this->booking_id, '_mhmrentiva_payment_status', 'refunded');
        update_post_meta($this->booking_id, '_mhmrentiva_total_price', '80');
        update_post_meta($this->booking_id, '_mhmrentiva_remaining_amount', '0');
        update_post_meta($this->booking_id, '_mhmrentiva_refunded_amount', \MHMRentiva\Admin\Payment\Core\Money::toMinor('80'));

        $html = $this->render();

        $this->assertStringContainsString('No refundable payment found', $html);
        $this->assertStringNotContainsString('deposit-management screen', $html);
    }

    public function test_a_booking_with_no_payment_at_all_shows_no_link(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('No refundable payment found', $html);
        $this->assertStringNotContainsString('deposit-management screen', $html);
    }

    /**
     * The safety-critical branch: BookingRefundMetaBox.php's
     * `array() === $state->orders() ? $state->refundableManual() : 0` forces
     * $remaining to 0 the moment a paid WooCommerce order exists, so this box
     * never opens a second refund path -- with different rules -- over money
     * that already has a WooCommerce-owned or deposit-management path.
     *
     * The booking also carries the same offline meta as
     * test_a_paid_cancelled_offline_booking_gets_the_summary_and_the_deposit_link --
     * proof-of-payment status, a total, a zero remaining -- so this proves
     * the WooCommerce order is what suppresses the summary/link, not an
     * absence of offline data. Drop the order and the same meta produces the
     * summary and link (that is the first test in this file). See the task
     * report for the mutation-kill evidence: dropping this box's own ternary
     * in favour of a bare refundableManual() call did NOT fail this test --
     * PaymentState::resolveOfflineChannel() already zeroes the offline
     * channel whenever order_ids is non-empty, so the box's gate is
     * defense-in-depth, not the only backstop. Replacing $remaining with
     * paid() - refunded() (both channels, not just the offline one) DID fail
     * it, rendering the offline summary and link for this WooCommerce-order
     * booking.
     */
    public function test_a_paid_woocommerce_order_shows_no_summary_even_with_offline_data_present(): void
    {
        $this->require_woocommerce();

        update_post_meta($this->booking_id, '_mhmrentiva_payment_status', 'paid');
        update_post_meta($this->booking_id, '_mhmrentiva_total_price', '80');
        update_post_meta($this->booking_id, '_mhmrentiva_remaining_amount', '0');

        $this->create_paid_order_for_booking($this->booking_id, '80');

        $html = $this->render();

        $this->assertStringNotContainsString('Remaining refundable', $html);
    }

    /**
     * Slice-3 phase-close audit, item 3. render() printed "No refundable
     * payment found for this booking." for the $remaining <= 0 branch
     * unconditionally -- including the shape this test builds, where the
     * booking's WooCommerce order is fully paid and nothing has been
     * refunded from it yet, i.e. WooCommerce money IS genuinely refundable.
     * The box staying offline-only is correct and unchanged (still no
     * offline summary, still no link to the deposit-management screen from
     * this branch); only the sentence was false. This pins the new sentence
     * appearing and the old, false one NOT appearing for exactly that shape.
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
        $this->assertStringNotContainsString('Remaining refundable', $html);
    }
}
