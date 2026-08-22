<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Actions;

use MHMRentiva\Admin\Booking\Actions\DepositManagementAjax;
use MHMRentiva\Admin\Booking\Core\Status;
use MHMRentiva\Admin\Booking\Helpers\Cache;
use MHMRentiva\Admin\Booking\Meta\BookingDepositMetaBox;
use MHMRentiva\Admin\Booking\Meta\BookingRefundMetaBox;
use MHMRentiva\Admin\Payment\Core\RefundLock;
use MHMRentiva\Admin\Payment\Core\RefundStatus;
use MHMRentiva\Tests\Support\WooCommerceFixtures;
use WP_Ajax_UnitTestCase;

/**
 * Task 12 (slice 5): needs_review's two operator exits.
 *
 * needs_review is where AutoCancel parks a booking it refuses to touch
 * unattended -- a paid order sitting on a booking a sweep was about to
 * cancel (K6, Task 4). Slice 5 gave that state a writer, a notification and
 * a visible row (Tasks 1-10); nothing could get a booking OUT of it until
 * this task. Two exits, both gated on MoneyAuthorization::mayMoveMoney()
 * with $surface = 'review_action': hand the booking to the cancellation flow
 * (which owns the money step), or record that no refund is due and close the
 * obligation.
 *
 * review_dismiss() follows close_manual_refund()'s corrected lock shape from
 * the start (Task 12 correction #1, T12-R1): the plan this task was written
 * from put every terminating wp_send_json_*() call INSIDE the try/finally,
 * which is the exact CRITICAL defect Task 11 was caught making and fixed --
 * wp_send_json_*() calls wp_die(), a hard exit in production that a finally
 * block does not run across, so that shape leaks the booking's refund lock
 * for RefundLock::TTL_SECONDS on every successful dismissal. See
 * DepositManagementAjax::review_dismiss()'s own docblock for the measurement
 * against the running container, and test_the_lock_is_released_after_a_
 * successful_dismiss() below for what this suite can and cannot prove about
 * it.
 *
 * @covers \MHMRentiva\Admin\Booking\Actions\DepositManagementAjax::review_cancel_and_refund
 * @covers \MHMRentiva\Admin\Booking\Actions\DepositManagementAjax::review_dismiss
 */
final class RefundReviewActionsTest extends WP_Ajax_UnitTestCase
{
    use WooCommerceFixtures;

    private int $booking_id;
    private int $admin_id;

    public function setUp(): void
    {
        parent::setUp();

        $this->admin_id = (int) self::factory()->user->create(array( 'role' => 'administrator' ));

        $this->booking_id = (int) self::factory()->post->create(array(
            'post_type'   => 'mhmrentiva_booking',
            'post_status' => 'publish',
        ));

        // Deliberately NOT cancelled: a park writes only
        // _mhmrentiva_refund_status (AutoCancel::cancel_booking_with_orders()'s
        // own docblock), so a needs_review booking in production is never the
        // cancelled+paid shape BookingDepositMetaBox::
        // can_refund_from_deposit_screen() requires -- which is exactly what
        // assertion 1 needs to be measuring something real.
        update_post_meta($this->booking_id, '_mhmrentiva_status', 'confirmed');

        $this->park_for_review($this->booking_id);

        DepositManagementAjax::register();
    }

    public function tearDown(): void
    {
        $_POST = array();

        // RefundLock rows are written with a raw $wpdb->query(); see
        // AutoCancelSweepSelectionTest's tearDown() for why a planted or
        // leaked row can outlive this test's own rollback.
        global $wpdb;
        $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'mhmrentiva_refund_lock_%'" );

        parent::tearDown();
    }

    /**
     * Park the booking the way production does -- through the lock and the
     * transition matrix -- so the meta value under test is the one
     * RefundStatus actually writes (AutoCancelSweepSelectionTest's own
     * precedent).
     */
    private function park_for_review(int $booking_id): void
    {
        $this->assertTrue(RefundLock::acquire($booking_id));
        $this->assertTrue(
            RefundStatus::transition($booking_id, RefundStatus::NEEDS_REVIEW, array( 'surface' => 'test_fixture' ))
        );
        RefundLock::release($booking_id);
    }

    /**
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function call(string $action, array $extra = array()): array
    {
        wp_set_current_user($this->admin_id);

        $_POST = array_merge(
            array(
                'nonce'      => wp_create_nonce('mhmrentiva_deposit_management_action'),
                'booking_id' => $this->booking_id,
            ),
            $extra
        );

        // WP_Ajax_UnitTestCase::dieHandler() APPENDS to _last_response; see
        // ManualRefundCloseTest's own call_close() for why this matters the
        // moment a test calls this twice (the idempotency tests below do).
        $this->_last_response = '';

        try {
            $this->_handleAjax($action);
        } catch (\WPAjaxDieContinueException | \WPAjaxDieStopException $e) {
            // wp_send_json_* terminates.
        }

        $decoded = json_decode($this->_last_response, true);

        return is_array($decoded) ? $decoded : array();
    }

    /**
     * Plan assertion 1, exactly as corrected: both controls must render for a
     * needs_review booking that is NOT cancelled, independent of
     * can_refund_from_deposit_screen() -- which this fixture proves is false
     * by construction, not by coincidence.
     */
    public function test_both_needs_review_controls_render_even_though_can_refund_from_deposit_screen_is_false(): void
    {
        wp_set_current_user($this->admin_id);

        $this->assertFalse(
            BookingDepositMetaBox::can_refund_from_deposit_screen($this->booking_id),
            'Sanity check: this booking is confirmed, not cancelled, so the deposit-screen predicate must '
                . 'read false, or this test proves nothing about independence from it.'
        );

        ob_start();
        BookingRefundMetaBox::render(get_post($this->booking_id));
        $html = (string) ob_get_clean();

        $this->assertStringContainsString('id="review-cancel-and-refund"', $html);
        $this->assertStringContainsString('id="review-dismiss"', $html);
    }

    /**
     * Plan assertion 2: cancel-and-refund cancels the booking and moves
     * refund_status forward from pending. "Forward from pending" is measured
     * as "reached some state PENDING can move to", not one hard-coded
     * terminal value -- the real Refunds\Service run this fixture drives can
     * legitimately land on more than one of them, and pinning a single value
     * would coupling this test to WooCommerce/gateway specifics that are not
     * what this task changed.
     */
    public function test_cancel_and_refund_cancels_the_booking_and_advances_refund_status_past_pending(): void
    {
        $this->require_woocommerce();
        $this->create_paid_order_for_booking($this->booking_id, '120');

        $response = $this->call('mhmrentiva_review_cancel_and_refund');

        $this->assertTrue((bool) ( $response['success'] ?? false ), 'Raw: ' . $this->_last_response);
        $this->assertSame(Status::CANCELLED, Status::get($this->booking_id));

        $status = RefundStatus::get($this->booking_id);

        $this->assertNotSame(RefundStatus::NEEDS_REVIEW, $status, 'The booking must have left needs_review.');
        $this->assertNotSame(RefundStatus::PENDING, $status, 'settle_refund() runs the whole flow synchronously in this request; PENDING left standing means it never reached a terminal outcome.');
        $this->assertContains(
            $status,
            array(
                RefundStatus::COMPLETED,
                RefundStatus::MANUAL_PENDING,
                RefundStatus::PARTIAL_FAILURE,
                RefundStatus::FAILED,
                RefundStatus::NOT_REQUIRED,
                RefundStatus::COMPLETED_EXTERNALLY,
            ),
            'Every reachable destination from pending; anything outside this set is not one the matrix allows.'
        );
    }

    /**
     * Plan assertion 3: an empty reason (here, whitespace-only -- exercising
     * trim(), not just an absent key) is refused before the lock is even
     * taken.
     */
    public function test_dismiss_with_an_empty_reason_is_rejected(): void
    {
        $response = $this->call('mhmrentiva_review_dismiss', array( 'reason' => '   ' ));

        $this->assertFalse((bool) ( $response['success'] ?? true ), 'Raw: ' . $this->_last_response);
        $this->assertSame(
            RefundStatus::NEEDS_REVIEW,
            RefundStatus::get($this->booking_id),
            'A rejected dismiss must not move the refund status at all.'
        );
    }

    /**
     * Plan assertion 4: a genuine reason writes not_required and the audit
     * trio.
     */
    public function test_dismiss_with_a_reason_writes_not_required_and_the_audit_trio(): void
    {
        $response = $this->call('mhmrentiva_review_dismiss', array( 'reason' => 'Customer no-show; deposit forfeited per policy.' ));

        $this->assertTrue(
            (bool) ( $response['success'] ?? false ),
            'Sanity: the dismiss must actually succeed, or the assertions below prove nothing. Raw: ' . $this->_last_response
        );

        $this->assertSame(RefundStatus::NOT_REQUIRED, RefundStatus::get($this->booking_id));
        $this->assertSame(
            $this->admin_id,
            (int) get_post_meta($this->booking_id, '_mhmrentiva_refund_review_dismissed_by', true)
        );
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',
            (string) get_post_meta($this->booking_id, '_mhmrentiva_refund_review_dismissed_at', true),
            'The dismissal timestamp must be a real MySQL datetime, not left empty.'
        );
        $this->assertSame(
            'Customer no-show; deposit forfeited per policy.',
            (string) get_post_meta($this->booking_id, '_mhmrentiva_refund_review_dismissed_reason', true)
        );
    }

    /**
     * Plan assertion 5, corrected per #6: the plan's own assertion is green
     * even against a completely broken endpoint, because review_dismiss()
     * writes no booking-status or availability meta on success OR on
     * failure -- "unchanged" is true either way. The positive control below
     * (success must be true) is what makes this test measure something a
     * regression could actually break; the availability half is measured
     * directly (a planted cache entry surviving), not inferred from the
     * booking status alone.
     */
    public function test_dismiss_does_not_touch_booking_status_or_vehicle_availability(): void
    {
        $vehicle_id = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_vehicle' ));
        update_post_meta($this->booking_id, '_mhmrentiva_vehicle_id', $vehicle_id);
        update_post_meta($this->booking_id, '_mhmrentiva_status', 'confirmed');

        Cache::setAvailability($vehicle_id, 1000, 2000, array( 'available' => false ));

        $response = $this->call('mhmrentiva_review_dismiss', array( 'reason' => 'No refund owed.' ));

        $this->assertTrue(
            (bool) ( $response['success'] ?? false ),
            'Sanity: the dismiss must actually succeed, or the assertions below prove nothing. Raw: ' . $this->_last_response
        );
        $this->assertSame(RefundStatus::NOT_REQUIRED, RefundStatus::get($this->booking_id));

        $this->assertSame(
            'confirmed',
            get_post_meta($this->booking_id, '_mhmrentiva_status', true),
            'review_dismiss() closes a money obligation, not a booking-occupancy one.'
        );
        $this->assertNotNull(
            Cache::getAvailability($vehicle_id, 1000, 2000),
            'review_dismiss() must not invalidate the vehicle availability cache -- unlike a real '
                . 'cancellation (AutoCancel::cancel_booking_with_orders(), Status::update_status()), '
                . 'nothing about this booking\'s occupancy changed.'
        );
    }

    public function test_dismiss_reason_is_capped_at_191_characters(): void
    {
        $long = str_repeat('a', 500);

        $response = $this->call('mhmrentiva_review_dismiss', array( 'reason' => $long ));

        $this->assertTrue((bool) ( $response['success'] ?? false ), 'Sanity: the dismiss must succeed. Raw: ' . $this->_last_response);

        $stored = (string) get_post_meta($this->booking_id, '_mhmrentiva_refund_review_dismissed_reason', true);

        $this->assertSame(191, strlen($stored), 'The reason must be capped at 191 characters, not stored verbatim.');
        $this->assertSame(str_repeat('a', 191), $stored);
    }

    public function test_dismiss_writes_a_booking_log_entry(): void
    {
        $response = $this->call('mhmrentiva_review_dismiss', array( 'reason' => 'No refund owed.' ));
        $this->assertTrue((bool) ( $response['success'] ?? false ), 'Raw: ' . $this->_last_response);

        $logs = get_post_meta($this->booking_id, '_mhmrentiva_booking_logs', true);
        $this->assertIsArray($logs);

        $matching = array_values(array_filter(
            $logs,
            static fn ( $entry ): bool => is_array($entry) && ( $entry['action'] ?? null ) === 'refund_review_dismissed'
        ));

        $this->assertCount(1, $matching, 'Exactly one refund_review_dismissed log entry must be written.');
        $this->assertSame($this->admin_id, $matching[0]['data']['processed_by'] ?? null);
        $this->assertSame('No refund owed.', $matching[0]['data']['reason'] ?? null);
    }

    public function test_cancel_and_refund_writes_a_booking_log_entry(): void
    {
        $response = $this->call('mhmrentiva_review_cancel_and_refund');
        $this->assertTrue((bool) ( $response['success'] ?? false ), 'Raw: ' . $this->_last_response);

        $logs = get_post_meta($this->booking_id, '_mhmrentiva_booking_logs', true);
        $this->assertIsArray($logs);

        $matching = array_values(array_filter(
            $logs,
            static fn ( $entry ): bool => is_array($entry) && ( $entry['action'] ?? null ) === 'refund_review_cancelled'
        ));

        $this->assertCount(1, $matching, 'Exactly one refund_review_cancelled log entry must be written.');
        $this->assertSame($this->admin_id, $matching[0]['data']['cancelled_by'] ?? null);
    }

    /**
     * The same authorisation bar as close_manual_refund() and every other
     * money-adjacent endpoint in this file: an actor MoneyAuthorization
     * refuses must not reach either exit, regardless of how the entry guard
     * (BookingActionGuard::authorize(), an administrator here would pass
     * regardless) resolves.
     */
    public function test_cancel_and_refund_is_rejected_when_money_authorization_refuses(): void
    {
        add_filter('mhmrentiva_may_move_money', '__return_false');

        $response = $this->call('mhmrentiva_review_cancel_and_refund');

        $this->assertFalse((bool) ( $response['success'] ?? true ), 'Raw: ' . $this->_last_response);
        $this->assertSame(RefundStatus::NEEDS_REVIEW, RefundStatus::get($this->booking_id));
        $this->assertSame('confirmed', Status::get($this->booking_id), 'A refused actor must not cancel the booking either.');
    }

    public function test_dismiss_is_rejected_when_money_authorization_refuses(): void
    {
        add_filter('mhmrentiva_may_move_money', '__return_false');

        $response = $this->call('mhmrentiva_review_dismiss', array( 'reason' => 'No refund owed.' ));

        $this->assertFalse((bool) ( $response['success'] ?? true ), 'Raw: ' . $this->_last_response);
        $this->assertSame(RefundStatus::NEEDS_REVIEW, RefundStatus::get($this->booking_id));
        $this->assertSame(
            '',
            (string) get_post_meta($this->booking_id, '_mhmrentiva_refund_review_dismissed_by', true),
            'A refused actor must leave no audit trail behind, as if the request never happened.'
        );
    }

    /**
     * The matrix boundary: a booking that has already left needs_review (by
     * either exit, or by any other path) must refuse a second visit to
     * either endpoint rather than silently re-running.
     */
    public function test_cancel_and_refund_refuses_a_booking_no_longer_in_needs_review(): void
    {
        RefundLock::acquire($this->booking_id);
        RefundStatus::transition($this->booking_id, RefundStatus::NOT_REQUIRED);
        RefundLock::release($this->booking_id);

        $response = $this->call('mhmrentiva_review_cancel_and_refund');

        $this->assertFalse((bool) ( $response['success'] ?? true ), 'Raw: ' . $this->_last_response);
        $this->assertSame('confirmed', Status::get($this->booking_id), 'A booking no longer in needs_review must not be cancelled by this endpoint.');
    }

    public function test_dismiss_refuses_a_booking_no_longer_in_needs_review(): void
    {
        RefundLock::acquire($this->booking_id);
        RefundStatus::transition($this->booking_id, RefundStatus::NOT_REQUIRED);
        RefundLock::release($this->booking_id);

        $response = $this->call('mhmrentiva_review_dismiss', array( 'reason' => 'No refund owed.' ));

        $this->assertFalse((bool) ( $response['success'] ?? true ), 'Raw: ' . $this->_last_response);
        $this->assertSame(RefundStatus::NOT_REQUIRED, RefundStatus::get($this->booking_id), 'Status must stay exactly as it was, not regress.');
    }

    /**
     * Idempotency, measured on durable state (meta, the status-changed event
     * count), not only the second response's success flag -- a
     * response-only assertion goes green even if RefundStatus::transition()
     * were never called at all on the second request (ManualRefundCloseTest's
     * own precedent for this shape).
     */
    public function test_second_dismiss_request_produces_no_side_effects(): void
    {
        $before = did_action('mhmrentiva_refund_status_changed');

        $first = $this->call('mhmrentiva_review_dismiss', array( 'reason' => 'First reason.' ));
        $this->assertTrue(
            (bool) ( $first['success'] ?? false ),
            'The first dismiss must succeed, or nothing below is measuring a repeat. Raw: ' . $this->_last_response
        );

        $reason_after_first = get_post_meta($this->booking_id, '_mhmrentiva_refund_review_dismissed_reason', true);

        $second = $this->call('mhmrentiva_review_dismiss', array( 'reason' => 'second-attempt-should-be-ignored' ));

        $this->assertFalse((bool) ( $second['success'] ?? true ), 'A second dismiss on an already-closed review must not report success. Raw: ' . $this->_last_response);
        $this->assertSame(
            $reason_after_first,
            get_post_meta($this->booking_id, '_mhmrentiva_refund_review_dismissed_reason', true),
            'The second request must not overwrite the recorded reason.'
        );
        $this->assertSame(RefundStatus::NOT_REQUIRED, RefundStatus::get($this->booking_id));
        $this->assertSame(
            1,
            did_action('mhmrentiva_refund_status_changed') - $before,
            'RefundStatus::transition() must fire the status-changed event exactly once across both requests -- '
                . 'the matrix refuses the second (not_required -> not_required is not an edge) before any event fires again.'
        );
    }

    /**
     * T12-R1: review_dismiss() follows close_manual_refund()'s corrected
     * shape (every terminating wp_send_json_*() call OUTSIDE the
     * try/finally) from the moment it was written, so there is no "before
     * the fix" version of this endpoint to contrast against here.
     *
     * What this test DOES prove, verbatim from ManualRefundCloseTest's own
     * regression test: a successful call leaves neither the in-process lock
     * counter (RefundLock::isHeld(), a static array only acquire()/
     * release() touch) nor the persisted wp_options row behind.
     *
     * The honest limit, stated because ManualRefundCloseTest's own C1 test
     * states it and this endpoint shares the exact same blind spot: this
     * suite cannot reproduce the production defect the corrected shape
     * guards against. WP's own test bootstrap makes wp_die() THROW
     * (WPAjaxDieContinueException here) rather than terminate the process,
     * and PHP's finally DOES run while an exception unwinds the stack -- so
     * a version of review_dismiss() with every wp_send_json_*() call INSIDE
     * the try/finally would pass this exact test too, for the harness's own
     * reason, not because the lock was actually released in production. The
     * defect this shape avoids was verified independently against the
     * running container's own wp_die() (WP 7.1) and a standalone
     * finally-vs-exit probe; see task-11-report.md's fix-round-1 section and
     * Task 12 correction #1 for that measurement.
     */
    public function test_the_lock_is_released_after_a_successful_dismiss(): void
    {
        global $wpdb;

        $response = $this->call('mhmrentiva_review_dismiss', array( 'reason' => 'No refund owed.' ));

        $this->assertTrue((bool) ( $response['success'] ?? false ), 'Sanity: the dismiss must succeed. Raw: ' . $this->_last_response);

        $this->assertFalse(
            RefundLock::isHeld($this->booking_id),
            'This process must not still consider itself the lock holder after a completed dismiss.'
        );

        $row = $wpdb->get_var($wpdb->prepare(
            "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
            'mhmrentiva_refund_lock_' . $this->booking_id
        ));

        $this->assertNull(
            $row,
            'No mhmrentiva_refund_lock_<id> row may survive a successful dismiss -- a surviving row is '
                . 'exactly what the plan\'s original try/finally shape would have leaked in production.'
        );
    }

    /**
     * The same regression, on the refusal path: a lock acquired and then
     * refused (empty reason after acquire is impossible here since that
     * check runs before acquire(); the matrix refusal below is the one path
     * that acquires the lock and then declines to write) must still release
     * it.
     */
    public function test_the_lock_is_released_when_the_matrix_refuses_the_transition(): void
    {
        global $wpdb;

        RefundLock::acquire($this->booking_id);
        RefundStatus::transition($this->booking_id, RefundStatus::NOT_REQUIRED);
        RefundLock::release($this->booking_id);

        $response = $this->call('mhmrentiva_review_dismiss', array( 'reason' => 'No refund owed.' ));

        $this->assertFalse((bool) ( $response['success'] ?? true ), 'Raw: ' . $this->_last_response);

        $this->assertFalse(RefundLock::isHeld($this->booking_id));

        $row = $wpdb->get_var($wpdb->prepare(
            "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
            'mhmrentiva_refund_lock_' . $this->booking_id
        ));

        $this->assertNull($row, 'A matrix refusal must not leave the lock standing either.');
    }
}
