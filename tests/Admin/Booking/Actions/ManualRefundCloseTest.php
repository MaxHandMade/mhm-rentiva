<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Actions;

use MHMRentiva\Admin\Booking\Actions\DepositManagementAjax;
use MHMRentiva\Admin\Payment\Core\RefundLock;
use MHMRentiva\Admin\Payment\Core\RefundStatus;
use MHMRentiva\Tests\Support\WooCommerceFixtures;
use WP_Ajax_UnitTestCase;

/**
 * Task 11 (slice 5): manual_pending -- "the refund is owed, no gateway can
 * move it, a human must hand the money over" -- had no exit. Slices 1-2 gave
 * the status a single writer, slice 4 made cancellation produce it, Task 10
 * made it visible; nothing in the plugin could say "the hand transfer
 * happened" until this endpoint.
 *
 * Attesting that money moved is held to the same authorisation bar as moving
 * it (MoneyAuthorization::mayMoveMoney(), $surface = 'manual_close') -- see
 * MoneyAuthorizationTest and RefundGateAgreementTest for the sibling
 * predicate this one is deliberately consistent with.
 *
 * @covers \MHMRentiva\Admin\Booking\Actions\DepositManagementAjax::close_manual_refund
 */
final class ManualRefundCloseTest extends WP_Ajax_UnitTestCase
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

        // Bypasses RefundStatus::transition() on purpose, matching
        // BookingRefundMetaBoxStatusRowTest's precedent: the matrix has no
        // single-hop edge into manual_pending from '', and this fixture is
        // about the close endpoint's own behaviour, not about the machine
        // that lands a booking here in production (Refunds\Service, Task 4).
        update_post_meta($this->booking_id, RefundStatus::META_KEY, RefundStatus::MANUAL_PENDING);

        DepositManagementAjax::register();
    }

    public function tearDown(): void
    {
        $_POST = array();
        parent::tearDown();
    }

    /**
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function call_close(int $booking_id, array $extra = array()): array
    {
        wp_set_current_user($this->admin_id);

        $_POST = array_merge(
            array(
                'nonce'      => wp_create_nonce('mhmrentiva_deposit_management_action'),
                'booking_id' => $booking_id,
            ),
            $extra
        );

        // WP_Ajax_UnitTestCase::dieHandler() APPENDS to _last_response
        // (`.=`, testcase-ajax.php) rather than overwriting it -- harmless
        // for a test that calls _handleAjax() once, but the idempotency test
        // below calls it twice in the same method, and the second call's
        // response would otherwise be the FIRST response's JSON with the
        // second one concatenated onto it (measured: json_decode() of that
        // string is null). Resetting here keeps each call's response
        // isolated to itself.
        $this->_last_response = '';

        try {
            $this->_handleAjax('mhmrentiva_close_manual_refund');
        } catch (\WPAjaxDieContinueException | \WPAjaxDieStopException $e) {
            // wp_send_json_* terminates.
        }

        $decoded = json_decode($this->_last_response, true);

        return is_array($decoded) ? $decoded : array();
    }

    /**
     * Plan assertion 1: manual_pending -> completed_manually.
     */
    public function test_manual_pending_booking_is_closed_to_completed_manually(): void
    {
        $response = $this->call_close($this->booking_id);

        $this->assertTrue(
            (bool) ( $response['success'] ?? false ),
            'Closing a manual_pending refund with an authorised actor must succeed. Raw: ' . $this->_last_response
        );
        $this->assertSame(
            RefundStatus::COMPLETED_MANUALLY,
            RefundStatus::get($this->booking_id),
            'The transition matrix allows exactly manual_pending -> completed_manually.'
        );
    }

    /**
     * Plan assertion 2: the audit trio is written.
     */
    public function test_close_records_actor_timestamp_and_reference(): void
    {
        $response = $this->call_close($this->booking_id, array( 'reference' => 'CASH-0001' ));

        $this->assertTrue((bool) ( $response['success'] ?? false ), 'Raw: ' . $this->_last_response);

        $this->assertSame(
            $this->admin_id,
            (int) get_post_meta($this->booking_id, '_mhmrentiva_refund_completed_by', true),
            'The actor who attested the transfer must be recorded.'
        );
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',
            (string) get_post_meta($this->booking_id, '_mhmrentiva_refund_completed_at', true),
            'The completion timestamp must be a real MySQL datetime, not left empty.'
        );
        $this->assertSame(
            'CASH-0001',
            (string) get_post_meta($this->booking_id, '_mhmrentiva_refund_completed_reference', true),
            'The operator-supplied reference must be stored verbatim (sanitised, not rewritten).'
        );
    }

    /**
     * Plan assertion 4, with its own sanity check: an assertion that the
     * amount did not change proves nothing if the close itself silently
     * failed, so success is asserted in the same test rather than assumed
     * from a sibling.
     */
    public function test_refunded_amount_is_unchanged(): void
    {
        update_post_meta($this->booking_id, '_mhmrentiva_refunded_amount', 5000);

        $response = $this->call_close($this->booking_id);

        $this->assertTrue(
            (bool) ( $response['success'] ?? false ),
            'Sanity check: the close must actually succeed, or the untouched-amount assertion below proves nothing. Raw: ' . $this->_last_response
        );
        $this->assertSame(
            5000,
            (int) get_post_meta($this->booking_id, '_mhmrentiva_refunded_amount', true),
            'This endpoint attests a transfer that already happened; it must not recompute or touch the refunded amount.'
        );
    }

    /**
     * Plan assertion 5: the offline channel -- no WooCommerce order behind
     * the money at all -- is exactly the case this endpoint exists for, and
     * the WC order note must be conditional rather than a hard requirement.
     * The default fixture already carries no _mhmrentiva_woocommerce_order_id
     * (or any of resolve_wc_order_id()'s three legacy aliases), so this test
     * needs no extra setup beyond setUp()'s own booking.
     */
    public function test_offline_booking_without_a_wc_order_closes_successfully(): void
    {
        $response = $this->call_close($this->booking_id);

        $this->assertTrue(
            (bool) ( $response['success'] ?? false ),
            'An offline booking with no WooCommerce order behind it must still be closeable -- '
                . 'this channel exists precisely because no such order exists. Raw: ' . $this->_last_response
        );
        $this->assertSame(RefundStatus::COMPLETED_MANUALLY, RefundStatus::get($this->booking_id));
    }

    /**
     * Plan assertion 6, with RefundGateAgreementTest/MoneyAuthorizationTest's
     * own pattern: force the money predicate to refuse via its filter, on an
     * actor who otherwise passes the entry guard (an administrator, who
     * would pass BookingActionGuard::authorize()'s edit_post check on this
     * CPT regardless). This proves close_manual_refund() asks
     * MoneyAuthorization itself, not merely the entry guard -- the two are
     * deliberately separate questions (see the class docblock).
     *
     * test_manual_pending_booking_is_closed_to_completed_manually() above is
     * this test's positive control: the same actor, same booking, same
     * request shape, no filter -- and it succeeds. Without that control, a
     * fixture that failed for some unrelated reason (a typo in the nonce
     * action, a booking shape the guard rejects) would make this refusal
     * pass for the wrong reason.
     */
    public function test_an_actor_money_authorization_refuses_is_rejected(): void
    {
        add_filter('mhmrentiva_may_move_money', '__return_false');

        $response = $this->call_close($this->booking_id);

        $this->assertFalse(
            (bool) ( $response['success'] ?? true ),
            'An actor MoneyAuthorization refuses must not be allowed to attest a transfer. Raw: ' . $this->_last_response
        );
        $this->assertSame(
            RefundStatus::MANUAL_PENDING,
            RefundStatus::get($this->booking_id),
            'A refused actor must not move the refund status at all.'
        );
        $this->assertSame(
            '',
            (string) get_post_meta($this->booking_id, '_mhmrentiva_refund_completed_by', true),
            'A refused actor must leave no audit trail behind, as if the request never happened.'
        );
    }

    /**
     * Plan assertion 3 + correction #8: idempotency must be measured on the
     * durable state (meta, order notes, the status-changed event count), not
     * only on the second response's success flag -- a response-only
     * assertion goes green even if RefundStatus::transition() were never
     * called at all on the second request, which is a real and distinct
     * regression from "transition() correctly refused a repeat".
     *
     * Needs a genuine WooCommerce order so the "no second order note"
     * half has something to measure; create_paid_order_for_booking() wires
     * _mhmrentiva_woocommerce_order_id, which is exactly what
     * BookingQueryHelper::resolve_wc_order_id() (the endpoint's own lookup)
     * reads.
     */
    public function test_second_request_produces_no_side_effects(): void
    {
        $this->require_woocommerce();

        $order = $this->create_paid_order_for_booking($this->booking_id, '500');

        $first = $this->call_close($this->booking_id, array( 'reference' => 'CASH-0001' ));
        $this->assertTrue(
            (bool) ( $first['success'] ?? false ),
            'The first close must succeed, or nothing below is measuring a repeat. Raw: ' . $this->_last_response
        );

        $notes_after_first = wc_get_order_notes(array( 'order_id' => $order->get_id() ));

        // Positive control for the note-count assertion below: without this,
        // an order-notes query that silently returns nothing (a wrong arg
        // name, a broken data store under HPOS) would make "no NEW note"
        // trivially true whether or not a second note was really added.
        $this->assertGreaterThan(
            0,
            count($notes_after_first),
            'Positive control: the first call must actually have written an order note, or the no-duplicate assertion below proves nothing.'
        );

        $completed_by_after_first = get_post_meta($this->booking_id, '_mhmrentiva_refund_completed_by', true);
        $completed_at_after_first = get_post_meta($this->booking_id, '_mhmrentiva_refund_completed_at', true);

        $second = $this->call_close($this->booking_id, array( 'reference' => 'second-attempt-should-be-ignored' ));

        $this->assertFalse(
            (bool) ( $second['success'] ?? true ),
            'A second close on an already-closed refund must not report success. Raw: ' . $this->_last_response
        );

        $this->assertCount(
            count($notes_after_first),
            wc_get_order_notes(array( 'order_id' => $order->get_id() )),
            'The second request must not add a second order note.'
        );
        $this->assertSame(
            $completed_by_after_first,
            get_post_meta($this->booking_id, '_mhmrentiva_refund_completed_by', true),
            'The second request must not overwrite the recorded actor.'
        );
        $this->assertSame(
            $completed_at_after_first,
            get_post_meta($this->booking_id, '_mhmrentiva_refund_completed_at', true),
            'The second request must not overwrite the recorded timestamp.'
        );
        $this->assertSame(
            'CASH-0001',
            (string) get_post_meta($this->booking_id, '_mhmrentiva_refund_completed_reference', true),
            'The second request\'s reference must not overwrite the first.'
        );
        $this->assertSame(
            RefundStatus::COMPLETED_MANUALLY,
            RefundStatus::get($this->booking_id),
            'Status must stay completed_manually, not regress or re-fire.'
        );
        $this->assertSame(
            1,
            did_action('mhmrentiva_refund_status_changed'),
            'RefundStatus::transition() must fire the status-changed event exactly once across both requests -- '
                . 'the matrix refuses the second (completed_manually -> completed_manually is not an edge) before any event fires again.'
        );
    }

    /**
     * C1 (fix round 1, CRITICAL): every terminating wp_send_json_*() call
     * used to sit INSIDE the try/finally, so RefundLock::release() never ran
     * in production -- wp_send_json_*() calls wp_die(), a hard exit, and PHP
     * does not run a finally block across an exit. This suite cannot
     * reproduce that exit: WP's own test bootstrap makes wp_die() THROW
     * (WPAjaxDieContinueException here, WPDieException elsewhere) rather
     * than terminate the process, and PHP's finally DOES run while an
     * exception unwinds the stack -- so this test would have been GREEN
     * against the pre-fix code too, for the harness's own reason, not
     * because the fix was in place. See task-11-report.md's fix-round-1
     * section for how the defect was actually verified (against the running
     * container's wp_die() implementation and a standalone `finally`-vs-exit
     * probe, neither of which goes through this suite).
     *
     * What this test DOES prove, and keeps proving for a different class of
     * future regression: a successful close leaves neither the in-process
     * lock counter (RefundLock::isHeld(), a static array only acquire()/
     * release() touch) nor the persisted wp_options row behind. The second
     * assertion is the one that would catch, for instance, a future
     * release() call made conditional on something that is not the case
     * here, or a codepath that calls acquire() twice without a matching
     * release() -- isHeld() alone cannot tell "released" apart from "never
     * really held" the way a direct row check can.
     */
    public function test_the_lock_is_released_after_a_successful_close(): void
    {
        global $wpdb;

        $response = $this->call_close($this->booking_id);

        $this->assertTrue((bool) ( $response['success'] ?? false ), 'Sanity: the close must succeed. Raw: ' . $this->_last_response);

        $this->assertFalse(
            RefundLock::isHeld($this->booking_id),
            'This process must not still consider itself the lock holder after a completed close.'
        );

        $row = $wpdb->get_var($wpdb->prepare(
            "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
            'mhmrentiva_refund_lock_' . $this->booking_id
        ));

        $this->assertNull(
            $row,
            'No mhmrentiva_refund_lock_<id> row may survive a successful close -- a surviving row is exactly '
                . 'what leaked in production before fix round 1 moved the terminating response outside the try.'
        );
    }

    /**
     * M3 (fix round 1): every other write path in this file calls
     * add_booking_log() on success; a hand-transfer attestation -- the most
     * human-vouched money event here -- left no trace in that log at all.
     */
    public function test_close_writes_a_booking_log_entry(): void
    {
        $response = $this->call_close($this->booking_id, array( 'reference' => 'CASH-0002' ));

        $this->assertTrue((bool) ( $response['success'] ?? false ), 'Sanity: the close must succeed. Raw: ' . $this->_last_response);

        $logs = get_post_meta($this->booking_id, '_mhmrentiva_booking_logs', true);

        $this->assertIsArray($logs);

        $matching = array_values(array_filter(
            $logs,
            static fn ( $entry ): bool => is_array($entry) && ( $entry['action'] ?? null ) === 'manual_refund_closed'
        ));

        $this->assertCount(1, $matching, 'Exactly one manual_refund_closed log entry must be written.');
        $this->assertSame($this->admin_id, $matching[0]['data']['processed_by'] ?? null);
        $this->assertSame('CASH-0002', $matching[0]['data']['reference'] ?? null);
    }

    /**
     * M4 (fix round 1): sanitize_text_field() normalises but does not
     * truncate, and the reference goes verbatim into a WC order note and
     * into post meta. An operator pasting an arbitrarily long string must
     * not write an arbitrarily long value to either place.
     */
    public function test_reference_is_capped_at_191_characters(): void
    {
        $long = str_repeat('a', 500);

        $response = $this->call_close($this->booking_id, array( 'reference' => $long ));

        $this->assertTrue((bool) ( $response['success'] ?? false ), 'Sanity: the close must succeed. Raw: ' . $this->_last_response);

        $stored = (string) get_post_meta($this->booking_id, '_mhmrentiva_refund_completed_reference', true);

        $this->assertSame(191, strlen($stored), 'The reference must be capped at 191 characters, not stored verbatim.');
        $this->assertSame(str_repeat('a', 191), $stored);
    }
}
