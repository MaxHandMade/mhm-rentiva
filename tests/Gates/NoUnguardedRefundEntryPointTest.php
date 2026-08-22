<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Gates;

use MHMRentiva\Admin\Booking\Meta\BookingDepositMetaBox;
use MHMRentiva\Admin\Booking\Meta\BookingRefundMetaBox;
use WP_UnitTestCase;

/**
 * Task 9 (slice 5): the admin_post refund endpoint read amount_kurus straight
 * off the request with no balance predicate. It was unreachable rather than
 * unprotected -- its nonce producer was deleted by a5c35a61 -- but re-arming
 * it is exactly what a future "retry refund" feature would do, so
 * Actions::refund_booking(), its add_action() registration, notices() (whose
 * only nonce producer was notice_url(), whose only caller was
 * refund_booking()) and the whole Actions class were deleted rather than left
 * dormant. See task-9-report.md for the full deletion chain.
 */
final class NoUnguardedRefundEntryPointTest extends WP_UnitTestCase
{
    /**
     * Positive control + the RED-phase gate, combined.
     *
     * The plan's original approach was `do_action('init')`, but
     * Actions::register() (like every Section-A registrar) was only ever
     * called from Plugin::initialize_admin_services(), an is_admin()-gated
     * block that runs once at muplugins_loaded, before any test can raise
     * is_admin() -- 'init' never touched it. Calling do_action('init') here
     * would make assertFalse() below pass whether or not the endpoint still
     * existed, which is exactly the vacuous-pass Task10bDeadEndpointsRemovedTest
     * ::setUp() already documents for this same registrar family.
     *
     * Since Actions::register() was deleted along with the rest of the class
     * (nothing survived to keep the file for), the positive control here is a
     * sibling registrar instead: BookingRefundMetaBox::register() sat right
     * next to Actions::register() in Plugin.php's admin-service bootstrap
     * (:344-346, immediately above the now-removed :347-349 block). Calling it
     * directly proves hook registration genuinely runs in this test process,
     * so the assertFalse() below cannot be true merely because nothing ran.
     */
    public function test_the_admin_post_refund_endpoint_is_no_longer_registered(): void
    {
        BookingRefundMetaBox::register();
        $this->assertNotFalse(
            has_action('add_meta_boxes', array( BookingRefundMetaBox::class, 'add' )),
            'Positive control: BookingRefundMetaBox::register() (the live sibling that sat next '
            . 'to Actions::register() in Plugin.php) must actually wire a hook in this process, '
            . 'or the assertion below would pass for the wrong reason.'
        );

        $this->assertFalse(
            has_action('admin_post_mhmrentiva_refund_booking'),
            'This endpoint read amount_kurus from the request with no balance predicate. '
            . 'It was unreachable only because its nonce producer was deleted (a5c35a61) -- '
            . 'unreachable rather than unprotected. Re-arming it is exactly what a future '
            . 'retry feature would do, so it goes.'
        );
    }

    /**
     * The UI half of Task 9: the deposit screen's two money buttons must not
     * offer an action MoneyAuthorization::mayMoveMoney() would refuse. Actor 0
     * (logged out / unattributed) always fails the hard floor in
     * MoneyAuthorization, regardless of the booking's own state -- so this is
     * a clean, filter-proof way to construct a refused actor.
     *
     * Two booking shapes are exercised because the cancel button's condition
     * (booking_status in {pending, confirmed}) and the refund button's
     * condition (can_refund_from_deposit_screen(), which requires
     * Status::CANCELLED -- the very same _mhmrentiva_status meta key) can
     * never both be true for one booking at once.
     */
    public function test_neither_deposit_screen_button_renders_for_an_actor_who_would_be_refused(): void
    {
        $booking_id = self::factory()->post->create(array( 'post_type' => 'mhmrentiva_booking' ));

        // Cancel-eligible shape.
        update_post_meta($booking_id, '_mhmrentiva_payment_type', 'full');
        update_post_meta($booking_id, '_mhmrentiva_status', 'confirmed');
        $html = $this->render_deposit_actions_as(0, $booking_id);
        $this->assertStringNotContainsString(
            'id="cancel-booking"',
            $html,
            'An unattributed actor fails the MoneyAuthorization hard floor; the cancel button must not render for them.'
        );

        // Refund-eligible shape (same booking, flipped to the state
        // can_refund_from_deposit_screen() requires).
        update_post_meta($booking_id, '_mhmrentiva_payment_status', 'paid');
        update_post_meta($booking_id, '_mhmrentiva_status', 'cancelled');
        update_post_meta($booking_id, '_mhmrentiva_total_price', '80');
        update_post_meta($booking_id, '_mhmrentiva_remaining_amount', '0');
        $html = $this->render_deposit_actions_as(0, $booking_id);
        $this->assertStringNotContainsString(
            'id="process-refund"',
            $html,
            'An unattributed actor fails the MoneyAuthorization hard floor; the refund button must not render for them.'
        );
    }

    /**
     * The positive control the button assertions above need: without it, a
     * fixture that stopped rendering either button for some unrelated reason
     * (a typo in a meta key, a shape that no longer qualifies) would make the
     * test above pass for the wrong reason -- this slice hit that exact trap
     * three times (see brief correction #6). An administrator satisfies
     * MoneyAuthorization::mayMoveMoney() via user_can(..., 'manage_options'),
     * with no filters registered on mhmrentiva_may_move_money in this suite
     * to complicate it.
     */
    public function test_both_deposit_screen_buttons_render_for_an_authorized_actor(): void
    {
        $admin_id = self::factory()->user->create(array( 'role' => 'administrator' ));

        $booking_id = self::factory()->post->create(array( 'post_type' => 'mhmrentiva_booking' ));

        update_post_meta($booking_id, '_mhmrentiva_payment_type', 'full');
        update_post_meta($booking_id, '_mhmrentiva_status', 'confirmed');
        $html = $this->render_deposit_actions_as($admin_id, $booking_id);
        $this->assertStringContainsString(
            'id="cancel-booking"',
            $html,
            'Positive control: an authorized administrator must still see the cancel button on a cancel-eligible booking.'
        );

        update_post_meta($booking_id, '_mhmrentiva_payment_status', 'paid');
        update_post_meta($booking_id, '_mhmrentiva_status', 'cancelled');
        update_post_meta($booking_id, '_mhmrentiva_total_price', '80');
        update_post_meta($booking_id, '_mhmrentiva_remaining_amount', '0');
        $html = $this->render_deposit_actions_as($admin_id, $booking_id);
        $this->assertStringContainsString(
            'id="process-refund"',
            $html,
            'Positive control: an authorized administrator must still see the refund button on a refund-eligible booking.'
        );
    }

    private function render_deposit_actions_as(int $actor_id, int $booking_id): string
    {
        wp_set_current_user($actor_id);

        ob_start();
        BookingDepositMetaBox::render_deposit_management(get_post($booking_id));
        return (string) ob_get_clean();
    }
}
