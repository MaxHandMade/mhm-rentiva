<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Helpers;

use MHMRentiva\Admin\Booking\Core\Status;
use MHMRentiva\Admin\Booking\Helpers\CancellationHandler;
use MHMRentiva\Admin\Payment\Core\Money;
use MHMRentiva\Admin\Payment\Core\PaymentState;
use MHMRentiva\Admin\PostTypes\Logs\PostType;
use MHMRentiva\Tests\Support\WooCommerceFixtures;
use WP_UnitTestCase;

/**
 * H-01: the whole point of the slice. Measured before this task,
 * mhmrentiva_process_refund had exactly one do_action and zero listeners, so a
 * cancellation wrote 'pending' and stopped -- on both surfaces, for every
 * booking, since the feature shipped.
 *
 * The hook ORDER is asserted, not assumed: it has to fire before the balance
 * is read, or an integrator's own refund would be made twice.
 */
final class CancellationInitiatesRefundTest extends WP_UnitTestCase
{
    use WooCommerceFixtures;

    private int $booking_id;
    private int $admin_id;

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
        update_post_meta($this->booking_id, '_mhmrentiva_payment_status', 'paid');
        update_post_meta($this->booking_id, '_mhmrentiva_vehicle_id', (int) self::factory()->post->create(array(
            'post_type' => 'mhmrentiva_vehicle',
        )));
        update_post_meta($this->booking_id, '_mhmrentiva_pickup_date', gmdate('Y-m-d', strtotime('+10 days')));
        update_post_meta($this->booking_id, '_mhmrentiva_dropoff_date', gmdate('Y-m-d', strtotime('+12 days')));
    }

    public function tearDown(): void
    {
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'mhmrentiva_refund_lock_%'");

        parent::tearDown();
    }

    private function cancel(): void
    {
        // The outer ownership guard reads the CURRENT user, not the actor
        // argument; see CancellationRefundAuthorizationTest for why.
        wp_set_current_user($this->admin_id);

        CancellationHandler::cancel_booking($this->booking_id, $this->admin_id, 'customer changed plans', true);
    }

    /**
     * The only end-to-end proof of re-entrancy in the whole slice.
     *
     * settle_refund() acquires RefundLock and then calls
     * Service::processFullRefund(), which acquires the same lock again in the
     * same request. If re-entrancy were broken, that inner acquire would
     * return false, the service would answer its refusal shape, and no money
     * would move -- so a passing assertion here is proof the nested acquire
     * actually succeeded, not just that a refund happened by some other path.
     */
    public function test_cancelling_a_paid_booking_refunds_the_whole_balance(): void
    {
        $order = $this->create_paid_order_for_booking($this->booking_id, '120');

        $this->cancel();

        $this->assertSame(
            Money::toMinor('120'),
            Money::toMinor(wc_get_order($order->get_id())->get_total_refunded()),
            'This is the assertion that was impossible before this slice.'
        );
    }

    /**
     * The `_mhmrentiva_refund_status` marker is written unconditionally,
     * immediately before do_action(), regardless of where settle_refund() (the
     * money step) is sequenced -- so asserting on that marker alone would stay
     * green even if the money step ran before the hook. What actually
     * discriminates that ordering is the balance itself: settle_refund() is
     * what moves it. If the hook fired after the money step instead of before,
     * this listener would see the balance already at zero.
     */
    public function test_the_hook_fires_before_the_balance_is_read(): void
    {
        $this->create_paid_order_for_booking($this->booking_id, '120');

        $seen = array();

        add_action(
            'mhmrentiva_process_refund',
            function () use (&$seen): void {
                $seen['refundable_at_hook_time'] = PaymentState::forBooking($this->booking_id)->refundable();
            }
        );

        $this->cancel();

        $this->assertSame(
            Money::toMinor('120'),
            $seen['refundable_at_hook_time'] ?? null,
            'If the money step ran before the hook fired, the balance would already be zero by the'
            . ' time this listener runs.'
        );
    }

    public function test_a_listener_that_refunds_externally_prevents_a_second_refund(): void
    {
        $order = $this->create_paid_order_for_booking($this->booking_id, '120');

        add_action(
            'mhmrentiva_process_refund',
            static function (int $booking_id) use ($order): void {
                wc_create_refund(array(
                    'order_id' => $order->get_id(),
                    'amount'   => '120',
                ));
            }
        );

        $this->cancel();

        $this->assertSame(
            Money::toMinor('120'),
            Money::toMinor(wc_get_order($order->get_id())->get_total_refunded()),
            'The integrator refunded 120; refunding again would return 240 to a customer who paid 120.'
        );
        $this->assertSame(
            'completed_externally',
            (string) get_post_meta($this->booking_id, '_mhmrentiva_refund_status', true),
            'Recording not_required here would erase a real money movement from the audit trail.'
        );
    }

    public function test_the_booking_stays_cancelled(): void
    {
        $this->create_paid_order_for_booking($this->booking_id, '120');

        $this->cancel();

        $this->assertSame(
            Status::CANCELLED,
            Status::get($this->booking_id),
            'Status::can_transition() has no cancelled -> refunded edge; the money state lives in payment_status.'
        );
    }

    public function test_the_lock_is_not_left_standing(): void
    {
        $this->create_paid_order_for_booking($this->booking_id, '120');

        $this->cancel();

        global $wpdb;

        $this->assertNull(
            $wpdb->get_var($wpdb->prepare(
                "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
                'mhmrentiva_refund_lock_' . $this->booking_id
            )),
            'A lock surviving the operation blocks every later refund on this booking for the TTL.'
        );
    }

    /**
     * Service::processFullRefund() returns EARLY -- before finish() -- when
     * RefundValidator refuses the request. That path never writes a terminal
     * status and never logs, so without a guard in settle_refund() itself, a
     * refusal here would leave the 'pending' marker standing forever with no
     * trace of why.
     *
     * This is not a contrived shape: a deposit paid by card (order
     * 'processing') with the remaining leg still an on-hold transfer order
     * carries payment_status = 'pending' (WooCommerceBridge:1349) -- a paid
     * WooCommerce order that passes the balance gate, sitting next to a
     * booking payment_status the validator refuses.
     */
    public function test_a_validator_refusal_after_the_hook_still_reaches_a_terminal_status(): void
    {
        $order = $this->create_paid_order_for_booking($this->booking_id, '120');

        update_post_meta($this->booking_id, '_mhmrentiva_payment_status', 'pending');

        $this->cancel();

        $this->assertSame(
            Money::toMinor('0'),
            Money::toMinor((string) wc_get_order($order->get_id())->get_total_refunded()),
            'The validator refused the refund; no money may have moved.'
        );

        $this->assertSame(
            'failed',
            (string) get_post_meta($this->booking_id, '_mhmrentiva_refund_status', true),
            "processFullRefund() returned before finish() could write a terminal status, so"
            . " settle_refund() itself must -- 'pending' left standing forever is a silent failure"
            . ' on the money path.'
        );

        $logs = get_posts(array(
            'post_type'      => PostType::TYPE,
            'posts_per_page' => 1,
            'orderby'        => 'ID',
            'order'          => 'DESC',
            'post_status'    => 'publish',
        ));

        $this->assertNotEmpty($logs, 'A validator refusal on the money path must leave a log row, not silence.');
        $this->assertStringContainsString(
            'Pending payments cannot be refunded',
            $logs[0]->post_content,
            "The logged message must be the validator's own refusal reason, not a generic failure."
        );
    }

    /**
     * Finding A: before this slice, mhmrentiva_process_refund had zero
     * listeners, so a broken one could never be reached. Now that it can fire
     * real integrator code, a listener that throws must not unwind the
     * cancellation that already committed -- the booking stays cancelled, the
     * refund still runs, and the failure is logged rather than silent.
     */
    public function test_a_throwing_process_refund_listener_does_not_abort_the_cancellation(): void
    {
        $order = $this->create_paid_order_for_booking($this->booking_id, '120');

        add_action(
            'mhmrentiva_process_refund',
            static function (): void {
                throw new \RuntimeException('listener exploded');
            }
        );

        $this->cancel();

        $this->assertSame(
            Status::CANCELLED,
            Status::get($this->booking_id),
            'A broken listener must not turn a committed cancellation into a WP_Error, nor undo work already committed.'
        );

        $this->assertSame(
            Money::toMinor('120'),
            Money::toMinor(wc_get_order($order->get_id())->get_total_refunded()),
            'PaymentState still decides the truth after the hook, regardless of what the listener did.'
        );

        $logs = get_posts(array(
            'post_type'      => PostType::TYPE,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
        ));

        $found = false;
        foreach ($logs as $log) {
            if (str_contains($log->post_content, 'mhmrentiva_process_refund listener failed')) {
                $found = true;
                break;
            }
        }

        $this->assertTrue($found, 'The listener failure must leave a trace rather than vanishing silently.');
    }

    /**
     * The failed-acquire branch at the top of settle_refund(): a lock held by
     * another request must fail this attempt closed, before any money moves,
     * and must not leave 'pending' standing.
     */
    public function test_the_lock_being_held_elsewhere_fails_closed_without_moving_money(): void
    {
        $order = $this->create_paid_order_for_booking($this->booking_id, '120');

        global $wpdb;
        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')",
            'mhmrentiva_refund_lock_' . $this->booking_id,
            'someone-else:' . time()
        ));

        $this->cancel();

        $this->assertSame(
            Money::toMinor('0'),
            Money::toMinor((string) wc_get_order($order->get_id())->get_total_refunded()),
            'The lock was already held; settle_refund() must not have reached the money step at all.'
        );

        $this->assertSame(
            'failed',
            (string) get_post_meta($this->booking_id, '_mhmrentiva_refund_status', true),
            'A refusal to acquire the lock is a terminal failure, not a state left as pending.'
        );
    }
}
