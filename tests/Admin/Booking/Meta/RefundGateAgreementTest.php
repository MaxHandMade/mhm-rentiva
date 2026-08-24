<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Meta;

use MHMRentiva\Admin\Booking\Meta\BookingDepositMetaBox;
use MHMRentiva\Admin\Booking\Meta\BookingRefundMetaBox;
use MHMRentiva\Admin\Payment\Core\Money;
use WP_UnitTestCase;

/**
 * "Can this booking be refunded from the deposit screen?" used to be written
 * three times -- BookingDepositMetaBox (the button), BookingRefundMetaBox (the
 * link that points at the button) and DepositManagementAjax::process_refund()
 * (the server-side gate) -- and the three copies disagreed in two measured
 * ways:
 *
 * 1. The trap. Refunds\Service writes payment_status = 'partially_refunded'
 *    whenever a refund does not clear the whole balance (Service.php:295),
 *    while every surface gate demanded exactly 'paid'. So a correct partial
 *    refund permanently stranded the rest of the money: no screen would offer
 *    the second refund, and the refund box still reported the balance as
 *    refundable while telling the operator to cancel a booking that was
 *    already cancelled.
 * 2. The legacy shape. BookingDepositMetaBox::render_deposit_management()
 *    returns early -- before any button -- when _mhmrentiva_payment_type is
 *    empty ("this booking was created with the old system"). The refund box's
 *    mirror of the gate did not know that and linked to a button that was not
 *    on the page.
 *
 * There is one predicate now, BookingDepositMetaBox::can_refund_from_deposit_
 * screen(), and this file's job is to prove the two surfaces cannot drift
 * apart again: every shape below asserts the button and the link TOGETHER, so
 * a change that moves one without the other fails here rather than in a
 * browser.
 *
 * Task 9 (slice 5) added a second, independent gate -- MoneyAuthorization::
 * mayMoveMoney(), asked with the ambient get_current_user_id() -- to BOTH
 * surfaces: the deposit box's button (BookingDepositMetaBox.php:414,424) and,
 * after fix round 1 closed a drift the first pass left open, the refund box's
 * link too (BookingRefundMetaBox.php:82). A first version of this fix gated
 * only the button, which made this very file's own invariant fail for the
 * wrong reason: an unauthorized ambient actor made the button disappear while
 * the link, still ungated, stayed -- disagreement on the actor dimension,
 * caught by test_the_deposit_boxs_button_and_the_refund_boxs_link_answer_the_
 * same_question_for_an_unauthorized_actor_too() below once it was added.
 *
 * This file is still deliberately about the STATE predicate for its five
 * original tests -- it is not the primary place actor authorization is
 * exercised (that is NoUnguardedRefundEntryPointTest and
 * MoneyAuthorizationTest) -- so setUp() sets an ambient administrator by
 * default. That pin is still load-bearing for those five tests: with both
 * surfaces now gated, an unauthenticated ambient actor (PHPUnit's default,
 * user 0) would force BOTH button and link to false regardless of booking
 * state, via MoneyAuthorization's hard floor -- which would make every
 * "true" shape below fail not because the state predicate disagrees, but
 * because neither surface is reachable at all. The one test that
 * deliberately wants that hard floor overrides the ambient user locally
 * instead of removing the class-wide pin.
 */
final class RefundGateAgreementTest extends WP_UnitTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        wp_set_current_user((int) self::factory()->user->create(array( 'role' => 'administrator' )));
    }

    /**
     * An offline-paid booking: no WooCommerce order, so PaymentState's offline
     * channel is live and total-minus-remaining is what was actually
     * collected.
     *
     * @param string $payment_status Written verbatim; the shapes under test differ in it.
     * @param string $payment_type   '' reproduces the legacy "old system" booking.
     */
    private function make_booking(
        string $payment_status,
        string $booking_status,
        string $payment_type,
        string $total = '1000',
        string $remaining = '0',
        int $refunded_minor = 0
    ): int {
        $booking_id = (int) self::factory()->post->create(array(
            'post_type'   => 'mhmrentiva_booking',
            'post_status' => 'publish',
        ));

        update_post_meta($booking_id, '_mhmrentiva_payment_status', $payment_status);
        update_post_meta($booking_id, '_mhmrentiva_status', $booking_status);
        update_post_meta($booking_id, '_mhmrentiva_total_price', $total);
        update_post_meta($booking_id, '_mhmrentiva_remaining_amount', $remaining);

        if ('' !== $payment_type) {
            update_post_meta($booking_id, '_mhmrentiva_payment_type', $payment_type);
        }

        if ($refunded_minor > 0) {
            update_post_meta($booking_id, '_mhmrentiva_refunded_amount', $refunded_minor);
        }

        return $booking_id;
    }

    private function deposit_box_has_button(int $booking_id): bool
    {
        ob_start();
        BookingDepositMetaBox::render_deposit_management(get_post($booking_id));
        $html = (string) ob_get_clean();

        return str_contains($html, 'id="process-refund"');
    }

    private function refund_box_has_link(int $booking_id): bool
    {
        ob_start();
        BookingRefundMetaBox::render(get_post($booking_id));
        $html = (string) ob_get_clean();

        return str_contains($html, 'Process this refund from the deposit-management screen.');
    }

    /**
     * @param bool $expected true when a refund route must genuinely exist.
     */
    private function assert_both_surfaces_agree(int $booking_id, bool $expected, string $shape): void
    {
        $button = $this->deposit_box_has_button($booking_id);
        $link   = $this->refund_box_has_link($booking_id);

        $this->assertSame(
            $expected,
            $button,
            sprintf('Deposit box button for the %s shape.', $shape)
        );
        $this->assertSame(
            $expected,
            $link,
            sprintf('Refund box link for the %s shape.', $shape)
        );
        $this->assertSame(
            $button,
            $link,
            sprintf(
                'The deposit box\'s button and the refund box\'s link answer the same question '
                    . 'and must never disagree; they do for the %s shape.',
                $shape
            )
        );
    }

    /**
     * The actor dimension, added by fix round 1 (F1). Uses the shape both
     * surfaces already agree is a genuine refund route for an authorized
     * actor (the control below) and asks the same question of an
     * unattributed one instead. Before F1, this failed: the button asked
     * MoneyAuthorization and the link did not, so an unauthorized actor made
     * the button disappear while the link stayed -- agreeing with neither
     * "both are false" nor the state-based expectation, just disagreeing
     * with the button. The local wp_set_current_user(0) below overrides the
     * class-wide administrator pin for this one test only; setUp() runs
     * again before every other test.
     */
    public function test_the_deposit_boxs_button_and_the_refund_boxs_link_answer_the_same_question_for_an_unauthorized_actor_too(): void
    {
        $booking_id = $this->make_booking('paid', 'cancelled', 'full');

        wp_set_current_user(0);

        $this->assert_both_surfaces_agree($booking_id, false, 'paid + cancelled, unauthorized actor');
    }

    /**
     * The control: the one shape both copies of the gate already agreed on.
     */
    public function test_a_paid_cancelled_booking_gets_both_the_button_and_the_link(): void
    {
        $booking_id = $this->make_booking('paid', 'cancelled', 'full');

        $this->assert_both_surfaces_agree($booking_id, true, 'paid + cancelled');
    }

    /**
     * THE TRAP. RED before the fix on both surfaces: each demanded exactly
     * 'paid', so this booking -- cancelled, 700,00 still genuinely refundable,
     * partially refunded once already -- lost its refund button and its link,
     * and the remaining balance could not be refunded from any screen.
     */
    public function test_a_partially_refunded_cancelled_booking_still_gets_both(): void
    {
        // 1000 collected offline, 300 already given back -> 700 refundable.
        $booking_id = $this->make_booking(
            'partially_refunded',
            'cancelled',
            'full',
            '1000',
            '0',
            (int) Money::toMinor('300')
        );

        $this->assert_both_surfaces_agree($booking_id, true, 'partially_refunded + cancelled');
    }

    /**
     * The fourth disagreement, found by the independent audit: the deposit box
     * prints the "old system" notice and returns before any button when
     * _mhmrentiva_payment_type is empty, while the refund box's mirror of the
     * gate did not know that and linked there anyway. RED before the fix on
     * the refund-box half.
     */
    public function test_a_legacy_booking_with_no_payment_type_gets_neither(): void
    {
        $booking_id = $this->make_booking('paid', 'cancelled', '');

        $this->assert_both_surfaces_agree($booking_id, false, 'empty payment_type (legacy)');
    }

    /**
     * Not cancelled: the deposit screen has no refund button for it, so the
     * refund box must not point at one.
     */
    public function test_a_paid_but_not_cancelled_booking_gets_neither(): void
    {
        $booking_id = $this->make_booking('paid', 'confirmed', 'full');

        $this->assert_both_surfaces_agree($booking_id, false, 'paid + confirmed');
    }

    /**
     * Nothing left to give back. The refund box takes its "no refundable
     * payment found" branch long before the gate, and the deposit box must not
     * offer a button that can only fail.
     */
    public function test_a_fully_refunded_cancelled_booking_gets_neither(): void
    {
        $booking_id = $this->make_booking(
            'refunded',
            'cancelled',
            'full',
            '1000',
            '0',
            (int) Money::toMinor('1000')
        );

        $this->assert_both_surfaces_agree($booking_id, false, 'fully refunded');
    }
}
