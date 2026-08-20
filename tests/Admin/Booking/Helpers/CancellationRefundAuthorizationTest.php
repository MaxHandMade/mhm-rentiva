<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Helpers;

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
            'An unattributed call must not reach the money step; that is what the $system flag is for.'
        );
    }

    public function test_an_explicit_system_call_may_move_money(): void
    {
        CancellationHandler::cancel_booking($this->booking_id, 0, 'cron', true, true);

        $this->assertNotSame(
            '',
            $this->refund_status(),
            'A declared system caller keeps the power the silent zero lost.'
        );
    }

    public function test_the_owner_may_move_money(): void
    {
        CancellationHandler::cancel_booking($this->booking_id, $this->owner_id, 'mine');

        $this->assertNotSame('', $this->refund_status());
    }

    public function test_an_administrator_may_move_money(): void
    {
        // cancel_booking()'s OUTER ownership guard (:87-95) asks
        // current_user_can( 'manage_options' ) -- the current user, not the
        // $user_id argument. Without this line the call is refused by a guard
        // this task never touched, and the test would be measuring the wrong
        // refusal.
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
}
