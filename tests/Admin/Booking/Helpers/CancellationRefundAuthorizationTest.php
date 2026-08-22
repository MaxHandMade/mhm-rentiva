<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Helpers;

use MHMRentiva\Admin\Booking\Core\Status;
use MHMRentiva\Admin\Booking\Helpers\CancellationHandler;
use MHMRentiva\Tests\Support\WooCommerceFixtures;
use WP_UnitTestCase;

/**
 * Spec §5.4: cancel_booking( $booking_id, $user_id = 0 ) skips its ownership
 * check entirely when the actor is 0, and step 6 hands that signature the
 * power to take money out of a gateway. The check therefore moves INTO the
 * money step and fails closed, and a genuine system caller has to say so with
 * a flag rather than with a silent zero.
 *
 * Task 8 (spec §5) went one step further: MoneyAuthorization::mayMoveMoney()
 * is now the single predicate BOTH cancel_booking()'s own outer ownership
 * guard and the money step ask, instead of the outer guard asking the ambient
 * current_user_can() and only the inner step asking the actor. There is also
 * no $system bypass any more -- since K6 no unattended path moves money, an
 * unattributed 0 actor is refused regardless of that flag.
 *
 * The observable used here is _mhmrentiva_refund_status: the money step writes
 * 'pending' as its first act, so its absence means the step never ran. Asserting
 * on the WooCommerce refund object instead would also pass for a booking that
 * simply had nothing to refund.
 */
final class CancellationRefundAuthorizationTest extends WP_UnitTestCase
{
    use WooCommerceFixtures;

    private int $booking_id;
    private int $owner_id;
    private int $stranger_id;
    private int $admin_id;

    public function setUp(): void
    {
        parent::setUp();

        $this->require_woocommerce();

        $this->owner_id    = (int) self::factory()->user->create(array( 'role' => 'customer' ));
        $this->stranger_id = (int) self::factory()->user->create(array( 'role' => 'customer' ));
        $this->admin_id    = (int) self::factory()->user->create(array( 'role' => 'administrator' ));

        $this->booking_id = (int) self::factory()->post->create(array(
            'post_type'   => 'mhmrentiva_booking',
            'post_status' => 'publish',
        ));

        update_post_meta($this->booking_id, '_mhmrentiva_status', 'confirmed');
        update_post_meta($this->booking_id, '_mhmrentiva_payment_status', 'paid');
        update_post_meta($this->booking_id, '_mhmrentiva_customer_user_id', $this->owner_id);
        update_post_meta($this->booking_id, '_mhmrentiva_vehicle_id', (int) self::factory()->post->create(array(
            'post_type' => 'mhmrentiva_vehicle',
        )));
        update_post_meta($this->booking_id, '_mhmrentiva_pickup_date', gmdate('Y-m-d', strtotime('+10 days')));
        update_post_meta($this->booking_id, '_mhmrentiva_dropoff_date', gmdate('Y-m-d', strtotime('+12 days')));

        $this->create_paid_order_for_booking($this->booking_id, '120');
    }

    private function refund_status(): string
    {
        return (string) get_post_meta($this->booking_id, '_mhmrentiva_refund_status', true);
    }

    public function test_a_silent_zero_actor_does_not_move_money(): void
    {
        CancellationHandler::cancel_booking($this->booking_id, 0, 'no actor');

        $this->assertSame(
            '',
            $this->refund_status(),
            'An unattributed call must not reach the money step -- MoneyAuthorization\'s hard floor'
                . ' refuses actor 0 regardless of any flag.'
        );
    }

    /**
     * Turned by Task 8 (spec §5): $system used to bypass may_move_money()'s
     * actor check entirely (`if ($system) return true;`). Measured before this
     * task: no production caller ever set it -- both real callers pass their
     * 4th argument as $force, not $system -- so the bypass was already dead in
     * practice; only this test exercised it. Since K6 no unattended path moves
     * money at all, MoneyAuthorization has no $system leg, and a declared
     * system caller with no attributed actor buys nothing at the money step
     * any more. $system remains on cancel_booking()'s signature as attribution
     * metadata only.
     *
     * Fix round 1, F1: the first version of this turn asserted only the empty
     * refund status, which would pass identically if cancel_booking() had
     * bailed at any earlier gate (or thrown) and never reached the money step
     * at all -- the exact failure shape this slice exists to close, and now a
     * near-duplicate of test_a_silent_zero_actor_does_not_move_money() above
     * in everything but $force/$system. Capturing the return and asserting
     * CANCELLED restores the control: it proves the cancellation itself ran
     * to completion, so the empty refund status is provably the money step's
     * own refusal, not some earlier exit.
     */
    public function test_an_explicit_system_call_may_not_move_money(): void
    {
        $result = CancellationHandler::cancel_booking($this->booking_id, 0, 'cron', true, true);

        $this->assertSame(
            Status::CANCELLED,
            Status::get($this->booking_id),
            'Guard: the cancellation itself must have gone through -- $system only ever bore on the'
                . ' MONEY step; otherwise this test would be measuring the wrong refusal.'
        );
        $this->assertFalse(is_wp_error($result));
        $this->assertSame(
            '',
            $this->refund_status(),
            'There is no $system bypass any more -- an unattributed actor is refused at the money'
                . ' step regardless of this flag, even though the cancellation itself succeeded.'
        );
    }

    public function test_the_owner_may_move_money(): void
    {
        CancellationHandler::cancel_booking($this->booking_id, $this->owner_id, 'mine');

        $this->assertNotSame('', $this->refund_status());
    }

    public function test_an_administrator_may_move_money(): void
    {
        // As of Task 8, cancel_booking()'s outer ownership guard asks
        // MoneyAuthorization::mayMoveMoney( ..., $user_id, 'cancel' ) --
        // the $user_id argument, not the ambient current user. This line is
        // still needed here because $user_id === $this->admin_id: the actor
        // being tested IS the current user in this scenario, so setting it
        // makes the fixture's own state consistent, not because the outer
        // guard reads it.
        wp_set_current_user($this->admin_id);

        CancellationHandler::cancel_booking($this->booking_id, $this->admin_id, 'operator', true);

        $this->assertNotSame('', $this->refund_status());
    }

    /**
     * Negative control: the outer guard already refuses a stranger, so this
     * test proves the refusal exists at all -- and that the money step is not
     * reached by a path that skirts it.
     */
    public function test_a_stranger_is_refused_and_no_money_step_runs(): void
    {
        $result = CancellationHandler::cancel_booking($this->booking_id, $this->stranger_id, 'not mine');

        $this->assertTrue(is_wp_error($result));
        $this->assertSame('', $this->refund_status());
    }

    /**
     * Turned by Task 8 (spec §5). Before this task, cancel_booking()'s OUTER
     * ownership guard asked current_user_can( 'manage_options' ) -- the
     * ambient current user, not the $user_id argument -- so an administrator
     * logged in could push a cancellation through on a stranger's behalf, and
     * only the INNER money step (may_move_money(), which already asked the
     * actor) refused. That split is exactly what let this scenario reach
     * CANCELLED while still recording no refund, and is what this test used
     * to assert.
     *
     * Task 8 ties the outer guard to the same MoneyAuthorization predicate the
     * money step uses, so both now ask about $user_id. An ambient
     * administrator can no longer walk a cancellation through at all when it
     * is attributed to someone else -- the whole call is refused before
     * Status::update_status() ever runs. This is the stronger property the
     * old split-refusal version was reaching for one layer too late; it is
     * still the only test in the file that would catch either guard being
     * swapped back to current_user_can().
     */
    public function test_the_gate_asks_about_the_actor_not_the_current_user(): void
    {
        wp_set_current_user($this->admin_id);

        $result = CancellationHandler::cancel_booking($this->booking_id, $this->stranger_id, 'operator override', true);

        $this->assertTrue(
            is_wp_error($result),
            'An ambient administrator must not push through a cancellation attributed to a stranger actor.'
        );
        $this->assertSame(
            Status::CONFIRMED,
            Status::get($this->booking_id),
            'The booking must be untouched -- the outer, actor-based guard must have refused before any status write.'
        );
        $this->assertSame(
            '',
            $this->refund_status(),
            'The money step must never even be reached once the outer guard refuses the actor.'
        );
    }
}
