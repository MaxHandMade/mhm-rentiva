<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Helpers;

use MHMRentiva\Admin\Booking\Actions\DepositManagementAjax;
use MHMRentiva\Admin\Booking\Core\Status;
use MHMRentiva\Admin\Booking\Helpers\CancellationHandler;
use MHMRentiva\Admin\Frontend\Account\AccountController;
use MHMRentiva\Admin\Payment\Core\Money;
use MHMRentiva\Admin\Payment\Core\RefundStatus;
use MHMRentiva\Admin\PostTypes\Logs\PostType;
use MHMRentiva\Tests\Support\WooCommerceFixtures;
use WP_Ajax_UnitTestCase;

/**
 * Task 14a (slice 5): the COMMIT / post-commit phase split.
 *
 * cancel_booking() used to wrap its whole body -- including the cancellation
 * e-mail, the refund, and the public mhmrentiva_booking_cancelled hook -- in
 * one transaction-shaped try/catch(\Exception). The COMMIT sits in the
 * middle of that body: past it, the booking IS cancelled and the customer
 * HAS been told, so ROLLBACK on anything that goes wrong afterward does
 * nothing real, and reporting a WP_Error for a cancellation that already
 * landed told the caller the opposite of the truth. Worse, catching only
 * \Exception meant an \Error thrown by a third-party listener on the public
 * hook was never caught at all -- it fataled the request outright, after the
 * database write had already committed.
 *
 * This file pins the fix: post-commit code runs in its OWN try/catch(\Throwable),
 * a throwable there is recorded as a "problem" rather than reported as
 * "cancellation failed", refund_status is moved to FAILED rather than left
 * at whatever it was, and both AJAX surfaces that call cancel_booking() say
 * so instead of claiming a bare success.
 *
 * @covers \MHMRentiva\Admin\Booking\Helpers\CancellationHandler::cancel_booking
 * @covers \MHMRentiva\Admin\Booking\Helpers\CancellationHandler::settle_refund
 */
final class CancellationPostCommitContainmentTest extends WP_Ajax_UnitTestCase
{
    use WooCommerceFixtures;

    private int $admin_id;
    private int $customer_id;

    public function setUp(): void
    {
        parent::setUp();

        $this->admin_id    = (int) self::factory()->user->create(array( 'role' => 'administrator' ));
        $this->customer_id = (int) self::factory()->user->create(array( 'role' => 'subscriber' ));

        DepositManagementAjax::register();
        // Registered directly rather than via AccountController::register():
        // that method also wires query vars, rewrite endpoints and an
        // enqueue_assets hook this test has no use for and no teardown for.
        add_action('wp_ajax_mhmrentiva_cancel_booking', array( AccountController::class, 'ajax_cancel_booking' ));
    }

    public function tearDown(): void
    {
        $_POST = array();

        // RefundLock rows are written with a raw $wpdb->query(); see
        // CancellationInitiatesRefundTest's tearDown() for why a row can
        // outlive this test's own rollback otherwise.
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'mhmrentiva_refund_lock_%'");

        parent::tearDown();
    }

    /**
     * A booking with nothing to refund, ready for an admin-forced cancel.
     * Deliberately no money and no WooCommerce order: it keeps the
     * throwable/phase-split tests focused on cancel_booking() itself rather
     * than on the refund machinery Tasks 6-13 already cover end to end.
     */
    private function make_unpaid_booking(): int
    {
        $booking_id = (int) self::factory()->post->create(array(
            'post_type'   => 'mhmrentiva_booking',
            'post_status' => 'publish',
        ));

        update_post_meta($booking_id, '_mhmrentiva_status', 'confirmed');
        update_post_meta($booking_id, '_mhmrentiva_pickup_date', gmdate('Y-m-d', strtotime('+10 days')));
        update_post_meta($booking_id, '_mhmrentiva_dropoff_date', gmdate('Y-m-d', strtotime('+12 days')));

        return $booking_id;
    }

    /**
     * @return array<int, \WP_Post>
     */
    private function all_log_entries(): array
    {
        return get_posts(array(
            'post_type'      => PostType::TYPE,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
        ));
    }

    private function throw_error_on_booking_cancelled(): void
    {
        add_action(
            'mhmrentiva_booking_cancelled',
            static function (): void {
                throw new \Error('listener exploded');
            }
        );
    }

    // -------------------------------------------------------------------
    // Plan assertions 1-3: direct calls to CancellationHandler::cancel_booking()
    // -------------------------------------------------------------------

    /**
     * Plan assertion 1. An \Error is not an \Exception, so catch(\Exception)
     * alone never sees it -- it propagates out of cancel_booking() uncaught
     * and fatals the request. If that regressed, THIS test method would
     * itself error out with the \Error, which is the proof: no try/catch
     * around the call below is needed for that to show up.
     */
    public function test_an_error_thrown_by_a_booking_cancelled_listener_does_not_fatal_the_request(): void
    {
        $booking_id = $this->make_unpaid_booking();
        $this->throw_error_on_booking_cancelled();

        wp_set_current_user($this->admin_id);
        $result = CancellationHandler::cancel_booking($booking_id, $this->admin_id, '', true);

        $this->assertIsArray(
            $result,
            'cancel_booking() returning normally (rather than PHPUnit reporting this test as errored by an'
                . ' uncaught \Error) is the proof that the listener\'s \Error did not fatal the request.'
        );
    }

    /**
     * Plan assertion 2. The booking really was cancelled before the
     * throwable ran (COMMIT already happened), so the return must say
     * "cancelled, with the problem recorded" -- not a WP_Error reading
     * "Cancellation failed", which would tell the caller the opposite of
     * what the database now holds.
     */
    public function test_a_post_commit_throwable_reports_cancelled_true_with_the_problem_recorded(): void
    {
        $booking_id = $this->make_unpaid_booking();
        $this->throw_error_on_booking_cancelled();

        wp_set_current_user($this->admin_id);
        $result = CancellationHandler::cancel_booking($booking_id, $this->admin_id, '', true);

        $this->assertFalse(
            is_wp_error($result),
            'A post-commit throwable must not read as "Cancellation failed" -- the cancellation already committed.'
        );
        $this->assertTrue($result['cancelled'], 'COMMIT already ran; the return must say the booking was cancelled.');
        $this->assertNotEmpty($result['problems'], 'The throwable must be recorded, not swallowed.');
        $this->assertStringContainsString('listener exploded', $result['problems'][0]);

        $this->assertSame(
            Status::CANCELLED,
            Status::get($booking_id),
            'COMMIT already ran; the booking must stay cancelled regardless of what ran after it.'
        );
    }

    /**
     * Plan assertion 3. refund_status must not be left at 'pending' by a
     * post-commit throwable -- it must move to 'failed'.
     *
     * Engineered on a no-money booking with refund_status PRE-SEEDED at
     * 'pending' (bypassing the lock, the same way CancellationInitiatesRefundTest
     * plants a foreign fact directly): on a real paid booking,
     * process_refund() always reaches a terminal status (completed/
     * not_required/completed_externally/failed) before mhmrentiva_booking_cancelled
     * even fires, because that hook is the LAST of the three post-commit
     * steps -- so a throwable there would find a status that is already
     * terminal, not 'pending'. The only way this hook's own throwable finds
     * refund_status still at 'pending' is if nothing after that point had
     * already moved it -- exactly the shape a genuinely interrupted
     * concurrent refund (a killed worker mid-flight) would leave behind.
     * Seeding it directly isolates that shape without needing a real gateway
     * call to throw.
     */
    public function test_a_post_commit_throwable_moves_refund_status_from_pending_to_failed(): void
    {
        $booking_id = $this->make_unpaid_booking();
        update_post_meta($booking_id, '_mhmrentiva_refund_status', RefundStatus::PENDING);

        $this->throw_error_on_booking_cancelled();

        wp_set_current_user($this->admin_id);
        CancellationHandler::cancel_booking($booking_id, $this->admin_id, '', true);

        $this->assertSame(
            RefundStatus::FAILED,
            RefundStatus::get($booking_id),
            "A post-commit throwable must not leave refund_status stuck at 'pending' -- nothing else would ever"
                . ' revisit it.'
        );
    }

    // -------------------------------------------------------------------
    // Plan assertion 4: both AJAX surfaces say so
    // -------------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    private function call_deposit_cancel(int $booking_id): array
    {
        wp_set_current_user($this->admin_id);

        $_POST = array(
            'nonce'      => wp_create_nonce('mhmrentiva_deposit_management_action'),
            'booking_id' => $booking_id,
        );

        $this->_last_response = '';

        try {
            $this->_handleAjax('mhmrentiva_deposit_cancel_booking');
        } catch (\WPAjaxDieContinueException | \WPAjaxDieStopException $e) {
            // wp_send_json_* terminates.
        }

        $decoded = json_decode($this->_last_response, true);

        return is_array($decoded) ? $decoded : array();
    }

    /**
     * @return array<string, mixed>
     */
    private function call_account_cancel(int $booking_id): array
    {
        wp_set_current_user($this->customer_id);

        $_POST = array(
            'nonce'      => wp_create_nonce('mhmrentiva_cancel_booking_nonce'),
            'booking_id' => $booking_id,
        );

        $this->_last_response = '';

        try {
            $this->_handleAjax('mhmrentiva_cancel_booking');
        } catch (\WPAjaxDieContinueException | \WPAjaxDieStopException $e) {
            // wp_send_json_* terminates.
        }

        $decoded = json_decode($this->_last_response, true);

        return is_array($decoded) ? $decoded : array();
    }

    public function test_the_deposit_screen_ajax_surface_reports_problems_instead_of_a_bare_success(): void
    {
        $booking_id = $this->make_unpaid_booking();
        $this->throw_error_on_booking_cancelled();

        $response = $this->call_deposit_cancel($booking_id);

        $this->assertTrue(
            $response['success'] ?? false,
            'The cancellation itself succeeded; wp_send_json_success is still the right envelope for it.'
        );
        $this->assertStringContainsString(
            'could not be completed',
            $response['data']['message'] ?? '',
            'A post-commit problem must be visible in the operator-facing response, not a bare "cancelled'
                . ' successfully".'
        );
    }

    public function test_the_account_ajax_surface_reports_problems_instead_of_a_bare_success(): void
    {
        $booking_id = $this->make_unpaid_booking();
        update_post_meta($booking_id, '_mhmrentiva_customer_user_id', $this->customer_id);
        $this->throw_error_on_booking_cancelled();

        $response = $this->call_account_cancel($booking_id);

        $this->assertTrue($response['success'] ?? false);
        $this->assertStringContainsString(
            'could not be completed',
            $response['data']['message'] ?? '',
            'A post-commit problem must be visible to the customer too, not a bare "cancelled successfully".'
        );
    }

    // -------------------------------------------------------------------
    // Correction #7: a refused PENDING transition must not move money
    // -------------------------------------------------------------------

    /**
     * review_dismiss() (Task 12) can transition a booking NEEDS_REVIEW ->
     * NOT_REQUIRED -- a terminal status with no outgoing edge -- at any
     * point. If that lands on a booking between "cancel_booking() decided
     * there is money to refund" and "settle_refund() takes the lock",
     * RefundStatus::transition(..., PENDING, ...) has no matrix edge FROM
     * 'not_required' and silently returns false without writing. Before
     * this correction settle_refund() ignored that return value and pressed
     * on into the money step regardless -- money would move while
     * _mhmrentiva_refund_status stayed at 'not_required', silently
     * disagreeing with what had just happened.
     *
     * Seeded directly rather than raced for real, the same way
     * CancellationInitiatesRefundTest plants a foreign lock row: the
     * property under test is what settle_refund() does with the refusal,
     * not whether two requests can genuinely interleave in single-process
     * PHPUnit.
     */
    public function test_a_refused_pending_transition_does_not_move_money(): void
    {
        $this->require_woocommerce();

        $booking_id = (int) self::factory()->post->create(array(
            'post_type'   => 'mhmrentiva_booking',
            'post_status' => 'publish',
        ));
        update_post_meta($booking_id, '_mhmrentiva_status', 'confirmed');
        update_post_meta($booking_id, '_mhmrentiva_pickup_date', gmdate('Y-m-d', strtotime('+10 days')));
        update_post_meta($booking_id, '_mhmrentiva_dropoff_date', gmdate('Y-m-d', strtotime('+12 days')));

        $order = $this->create_paid_order_for_booking($booking_id, '120');

        // The race this test stands in for: another request already closed
        // this booking's refund obligation as not_required before this
        // cancellation reaches settle_refund().
        update_post_meta($booking_id, '_mhmrentiva_refund_status', RefundStatus::NOT_REQUIRED);

        wp_set_current_user($this->admin_id);
        CancellationHandler::cancel_booking($booking_id, $this->admin_id, 'customer changed plans', true);

        $this->assertSame(
            Money::toMinor('0'),
            Money::toMinor((string) wc_get_order($order->get_id())->get_total_refunded()),
            "PENDING could not be recorded from 'not_required' -- the money step must never have run."
        );

        $this->assertSame(
            RefundStatus::NOT_REQUIRED,
            RefundStatus::get($booking_id),
            'A refused transition must not be silently overwritten by a later step either.'
        );

        $found = false;
        foreach ($this->all_log_entries() as $log) {
            if (str_contains($log->post_content, 'Refund not attempted')) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'The refusal must leave a trace an operator can find, not just a discarded bool.');
    }
}
