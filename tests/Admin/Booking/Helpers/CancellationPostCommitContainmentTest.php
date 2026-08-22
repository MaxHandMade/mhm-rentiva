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

    /**
     * Fix round 1, F1: a throwable raised WHILE HANDLING a post-commit
     * problem must not fatal the request or turn this into a WP_Error
     * either -- there is no rollback left to perform. RefundStatus::
     * transition() fires the public mhmrentiva_refund_status_changed
     * action, reachable from third-party code exactly like
     * mhmrentiva_booking_cancelled is, so a listener on THAT hook throwing
     * from inside cancel_booking()'s own recovery path is the scenario the
     * recovery block's extra try/catch exists for.
     */
    public function test_a_throwable_from_the_recovery_itself_still_returns_the_array_not_a_wp_error(): void
    {
        $booking_id = $this->make_unpaid_booking();
        update_post_meta($booking_id, '_mhmrentiva_refund_status', RefundStatus::PENDING);

        $this->throw_error_on_booking_cancelled();

        add_action(
            'mhmrentiva_refund_status_changed',
            static function (): void {
                throw new \RuntimeException('recovery listener exploded too');
            }
        );

        wp_set_current_user($this->admin_id);
        $result = CancellationHandler::cancel_booking($booking_id, $this->admin_id, '', true);

        $this->assertFalse(
            is_wp_error($result),
            'A throwable raised while HANDLING a post-commit problem must not turn this into a WP_Error either.'
        );
        $this->assertTrue($result['cancelled']);
        $this->assertGreaterThanOrEqual(
            2,
            count($result['problems']),
            'Both the original throwable and the one raised by the recovery itself must be recorded.'
        );
    }

    /**
     * Fix round 1, F5: an unrelated post-commit throwable on a booking with
     * nothing owed must not tell the operator a refund is missing when none
     * was ever due. customer_email is seeded so send_cancellation_email()'s
     * OWN, unrelated mail is also in play -- the assertion below targets the
     * refund-failure subject specifically, not "no mail was sent at all".
     */
    public function test_the_operator_email_does_not_fire_when_there_is_no_money_at_stake(): void
    {
        $booking_id = $this->make_unpaid_booking();
        update_post_meta($booking_id, '_mhmrentiva_customer_email', 'customer@example.com');
        $this->throw_error_on_booking_cancelled();

        $mails = array();
        add_filter(
            'wp_mail',
            static function (array $args) use (&$mails): array {
                $mails[] = $args;
                return $args;
            }
        );

        wp_set_current_user($this->admin_id);
        CancellationHandler::cancel_booking($booking_id, $this->admin_id, '', true);

        $failure_mails = array_filter(
            $mails,
            static fn (array $mail): bool => str_contains($mail['subject'], 'problem completing its refund')
        );

        $this->assertSame(
            array(),
            $failure_mails,
            'No money is at stake on this booking; the operator refund-failure email must not fire.'
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

    /**
     * Fix round 1, F6: both new AJAX branches key on
     * `! empty( $result['problems'] ) || RefundStatus::FAILED === RefundStatus::get(...)`.
     * Every test above drives the FIRST disjunct via a throwing listener,
     * which short-circuits before the SECOND ever runs -- and with it, the
     * `wp_cache_delete()` freshness read that disjunct depends on. This
     * drives the second one directly: a genuine validator refusal that
     * never throws (the shape RefundValidator's own refusal branch
     * produces -- see CancellationInitiatesRefundTest::
     * test_a_validator_refusal_after_the_hook_still_reaches_a_terminal_status),
     * which is the case real users hit far more often than a broken
     * third-party listener.
     */
    public function test_the_deposit_screen_ajax_surface_reports_a_failed_refund_that_never_threw(): void
    {
        $this->require_woocommerce();

        $booking_id = (int) self::factory()->post->create(array(
            'post_type'   => 'mhmrentiva_booking',
            'post_status' => 'publish',
        ));
        update_post_meta($booking_id, '_mhmrentiva_status', 'confirmed');
        update_post_meta($booking_id, '_mhmrentiva_pickup_date', gmdate('Y-m-d', strtotime('+10 days')));
        update_post_meta($booking_id, '_mhmrentiva_dropoff_date', gmdate('Y-m-d', strtotime('+12 days')));
        update_post_meta($booking_id, '_mhmrentiva_payment_status', 'pending');

        $this->create_paid_order_for_booking($booking_id, '120');

        $response = $this->call_deposit_cancel($booking_id);

        $this->assertTrue($response['success'] ?? false);
        $this->assertStringContainsString('could not be completed', $response['data']['message'] ?? '');
        $this->assertSame(
            RefundStatus::FAILED,
            RefundStatus::get($booking_id),
            'Sanity check: this must be the validator-refusal shape, or the test proves nothing about the'
                . ' second disjunct.'
        );
    }

    public function test_the_account_ajax_surface_reports_a_failed_refund_that_never_threw(): void
    {
        $this->require_woocommerce();

        $booking_id = (int) self::factory()->post->create(array(
            'post_type'   => 'mhmrentiva_booking',
            'post_status' => 'publish',
        ));
        update_post_meta($booking_id, '_mhmrentiva_status', 'confirmed');
        update_post_meta($booking_id, '_mhmrentiva_pickup_date', gmdate('Y-m-d', strtotime('+10 days')));
        update_post_meta($booking_id, '_mhmrentiva_dropoff_date', gmdate('Y-m-d', strtotime('+12 days')));
        update_post_meta($booking_id, '_mhmrentiva_payment_status', 'pending');
        update_post_meta($booking_id, '_mhmrentiva_customer_user_id', $this->customer_id);

        $this->create_paid_order_for_booking($booking_id, '120');

        $response = $this->call_account_cancel($booking_id);

        $this->assertTrue($response['success'] ?? false);
        $this->assertStringContainsString('could not be completed', $response['data']['message'] ?? '');
        $this->assertSame(RefundStatus::FAILED, RefundStatus::get($booking_id));
    }

    /**
     * F2's second half: review_cancel_and_refund() (Task 12) must not claim
     * "the refund started" when cancel_booking() reports a post-commit
     * problem. Correction #7's own named scenario -- a concurrent
     * review_dismiss() writing not_required WHILE this request is in
     * flight -- is awkward to manufacture directly in a single-process
     * PHPUnit run; a generic post-commit throwable exercises the SAME new
     * `! empty( $result['problems'] )` branch this endpoint now checks
     * before either of its two pre-existing messages, which is what this
     * test pins.
     */
    public function test_review_cancel_and_refund_reports_problems_instead_of_claiming_the_refund_started(): void
    {
        $booking_id = (int) self::factory()->post->create(array(
            'post_type'   => 'mhmrentiva_booking',
            'post_status' => 'publish',
        ));
        update_post_meta($booking_id, '_mhmrentiva_status', 'confirmed');

        $this->assertTrue(\MHMRentiva\Admin\Payment\Core\RefundLock::acquire($booking_id));
        $this->assertTrue(
            RefundStatus::transition($booking_id, RefundStatus::NEEDS_REVIEW, array( 'surface' => 'test_fixture' ))
        );
        \MHMRentiva\Admin\Payment\Core\RefundLock::release($booking_id);

        $this->throw_error_on_booking_cancelled();

        wp_set_current_user($this->admin_id);
        $_POST = array(
            'nonce'      => wp_create_nonce('mhmrentiva_deposit_management_action'),
            'booking_id' => $booking_id,
        );
        $this->_last_response = '';

        try {
            $this->_handleAjax('mhmrentiva_review_cancel_and_refund');
        } catch (\WPAjaxDieContinueException | \WPAjaxDieStopException $e) {
            // wp_send_json_* terminates.
        }

        $decoded  = json_decode($this->_last_response, true);
        $response = is_array($decoded) ? $decoded : array();

        $this->assertTrue($response['success'] ?? false);
        $this->assertStringContainsString(
            'could not be completed',
            $response['data']['message'] ?? '',
            'A post-commit problem must not be reported as "the refund started" -- that sentence would be'
                . ' false the moment $result[\'problems\'] is non-empty.'
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

        // Fix round 1, F3: create_paid_order_for_booking() does not write
        // this meta key itself, and its absence let assertion (a) below
        // pass even with the guard removed (PaymentState::forBooking()
        // already reads a balance from the real WC order alone, so
        // has_money was true regardless -- but RefundValidator's OWN
        // payment-status gate, further down the un-guarded code path, is
        // what actually stopped the money in that case, not the thing this
        // test means to pin). Every sibling test that intends money to
        // move seeds it explicitly (CancellationInitiatesRefundTest.php,
        // CancellationRefundAuthorizationTest.php, CancellationRefundGateTest.php).
        update_post_meta($booking_id, '_mhmrentiva_payment_status', 'paid');

        $order = $this->create_paid_order_for_booking($booking_id, '120');

        // The race this test stands in for: another request already closed
        // this booking's refund obligation as not_required before this
        // cancellation reaches settle_refund().
        update_post_meta($booking_id, '_mhmrentiva_refund_status', RefundStatus::NOT_REQUIRED);

        $hook_count_before = did_action('mhmrentiva_booking_cancelled');

        wp_set_current_user($this->admin_id);
        $result = CancellationHandler::cancel_booking($booking_id, $this->admin_id, 'customer changed plans', true);

        $this->assertFalse(is_wp_error($result), 'COMMIT already ran; a post-commit refusal must not read as a WP_Error.');
        $this->assertNotEmpty(
            $result['problems'],
            'Fix round 1, F2: the refusal must be reported upward (not just logged) so a caller like'
                . ' review_cancel_and_refund() can see it and stop claiming the refund started.'
        );

        // Fix round 2, G1's regression pin: fix round 1 REPORTED the refusal
        // by throwing, which also unwound past this action entirely --
        // skipping a live Pro consumer (VendorCancellationDateBlocker::
        // maybe_block_dates()) even though the cancellation itself had
        // already committed. Reporting the refusal must never cost the rest
        // of the post-commit sequence.
        $this->assertGreaterThan(
            $hook_count_before,
            did_action('mhmrentiva_booking_cancelled'),
            'A refused PENDING transition must not prevent mhmrentiva_booking_cancelled from firing --'
                . ' the cancellation itself succeeded regardless of what happened to the refund.'
        );

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

    /**
     * Task 14b item 13: settle_refund() attempts a FAILED write when the
     * validator refuses a refund; before this fix, if the matrix ALSO
     * refused that write (the booking's refund_status raced to a terminal
     * value in between), nothing happened at all -- no log, and $success's
     * own failure never reached 'problems' either, since the comment beside
     * that branch covers only the $recorded === true case. All three AJAX
     * surfaces kept reading "cancelled successfully" while the money never
     * moved and nothing anywhere recorded that a refund had even been
     * attempted here.
     *
     * Engineered via a mhmrentiva_process_refund listener that settles the
     * booking's refund_status to a terminal value (NOT_REQUIRED) WITHOUT
     * touching the actual balance -- a plausible integrator bug, and the
     * only way, within one PHPUnit process, to make refund_status disagree
     * with PaymentState between settle_refund()'s own PENDING write and its
     * later FAILED attempt (RefundLock is re-entrant per-process, so the
     * hook's transition() call succeeds while this request still holds the
     * lock). _mhmrentiva_payment_status is seeded 'pending' so
     * RefundValidator::validatePaymentStatus() refuses the refund outright
     * -- has_money in process_refund() is still true via
     * PaymentState::forBooking()->paid(), so settle_refund() is reached
     * regardless of that meta value -- the cheapest way to force
     * processFullRefund() to return before finish() ever runs.
     */
    public function test_a_refused_failed_transition_is_recorded_as_a_problem(): void
    {
        $this->require_woocommerce();

        $booking_id = (int) self::factory()->post->create(array(
            'post_type'   => 'mhmrentiva_booking',
            'post_status' => 'publish',
        ));
        update_post_meta($booking_id, '_mhmrentiva_status', 'confirmed');
        update_post_meta($booking_id, '_mhmrentiva_pickup_date', gmdate('Y-m-d', strtotime('+10 days')));
        update_post_meta($booking_id, '_mhmrentiva_dropoff_date', gmdate('Y-m-d', strtotime('+12 days')));
        update_post_meta($booking_id, '_mhmrentiva_payment_status', 'pending');

        $this->create_paid_order_for_booking($booking_id, '120');

        add_action(
            'mhmrentiva_process_refund',
            static function (int $bid) use ($booking_id): void {
                if ($bid === $booking_id) {
                    RefundStatus::transition($booking_id, RefundStatus::NOT_REQUIRED, array( 'surface' => 'test_fixture' ));
                }
            }
        );

        wp_set_current_user($this->admin_id);
        $result = CancellationHandler::cancel_booking($booking_id, $this->admin_id, 'customer changed plans', true);

        $this->assertFalse(is_wp_error($result), 'COMMIT already ran; a post-commit refusal must not read as a WP_Error.');
        $this->assertNotEmpty(
            $result['problems'],
            'Item 13: a FAILED write the matrix refuses must be reported upward, the same as the PENDING'
                . ' refusal above -- not total silence.'
        );

        $this->assertSame(
            RefundStatus::NOT_REQUIRED,
            RefundStatus::get($booking_id),
            'The refused FAILED write must not be silently forced through; the status the hook set stands.'
        );

        $found = false;
        foreach ($this->all_log_entries() as $log) {
            if (
                str_contains($log->post_content, 'FAILED')
                && $booking_id === (int) get_post_meta($log->ID, '_mhmrentiva_log_booking_id', true)
            ) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'The refusal must leave a trace an operator can find, linked to this booking.');
    }
}
