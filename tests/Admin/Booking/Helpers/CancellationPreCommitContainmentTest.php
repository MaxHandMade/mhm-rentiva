<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Helpers;

use MHMRentiva\Admin\Booking\Core\Status;
use MHMRentiva\Admin\Booking\Helpers\CancellationHandler;
use WP_UnitTestCase;

/**
 * Task 14b item 10 (slice 5): the PRE-commit phase's own \Exception-only catch.
 *
 * cancel_booking()'s transactional try/catch -- everything up to and
 * including COMMIT -- caught only \Exception, the same defect class 14a
 * closed one phase later, on the POST-commit try
 * (CancellationPostCommitContainmentTest). Status::update_status(), called
 * inside this PRE-commit try, fires the public mhmrentiva_booking_status_changed
 * action before COMMIT ever runs; a third-party listener on it (a Pro
 * listener among them) can throw an \Error, and a plain TypeError from a
 * misbehaving listener is exactly as reachable. Before this fix, that \Error
 * skipped catch(\Exception) entirely: $wpdb->query('ROLLBACK') never ran,
 * and the request fatalled with the transaction left open.
 *
 * catch(\Throwable) closes the gap. This file pins two things beyond "the
 * request survives": that the answer here is the OPPOSITE of 14a's --
 * nothing has committed yet, so this must read as "Cancellation failed",
 * never "cancelled, with problems" -- and that ROLLBACK is the query that
 * actually runs, not merely that some catch block exists. The second pin
 * reuses ManualBookingAtomicityTest::start_recording()'s technique: record
 * every statement and neutralise transaction control to a no-op SELECT, so
 * the outer transaction WP_UnitTestCase wraps this test in is never touched
 * by cancel_booking()'s own START TRANSACTION/COMMIT/ROLLBACK, while still
 * recording, in order, which of them our code actually issued.
 *
 * @covers \MHMRentiva\Admin\Booking\Helpers\CancellationHandler::cancel_booking
 */
final class CancellationPreCommitContainmentTest extends WP_UnitTestCase
{
    /** @var list<string> */
    private array $query_log = array();

    public function tearDown(): void
    {
        $this->query_log = array();
        parent::tearDown();
    }

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

    private function throw_error_on_status_changed_to_cancelled(): void
    {
        add_action(
            'mhmrentiva_booking_status_changed',
            static function ($booking_id, $old_status, $new_status): void {
                if (Status::CANCELLED === $new_status) {
                    throw new \Error('pre-commit listener exploded');
                }
            },
            10,
            3
        );
    }

    /**
     * Records every statement issued through $wpdb during the call and
     * neutralises transaction control to a no-op SELECT -- exactly
     * ManualBookingAtomicityTest::start_recording()'s technique -- so the
     * outer transaction WP_UnitTestCase wraps this test in is never
     * committed or rolled back by cancel_booking()'s own START
     * TRANSACTION/COMMIT/ROLLBACK, while still recording, in order, which
     * of them our code actually issued.
     */
    private function start_recording(): void
    {
        add_filter('query', function ($query) {
            $this->query_log[] = (string) $query;

            if (preg_match('/^\s*(START TRANSACTION|COMMIT|ROLLBACK)\b/i', (string) $query)) {
                return 'SELECT 1';
            }

            return $query;
        });
    }

    private function index_of(string $pattern): int
    {
        foreach ($this->query_log as $i => $sql) {
            if (preg_match($pattern, $sql)) {
                return $i;
            }
        }

        return -1;
    }

    /**
     * Proof the request survives at all: before this fix, the \Error above
     * propagated straight out of cancel_booking() uncaught (catch(\Exception)
     * does not see an \Error), and this test method would itself be reported
     * as errored by that uncaught \Error rather than reaching any assertion
     * below it.
     */
    public function test_a_pre_commit_error_does_not_fatal_the_request(): void
    {
        $booking_id = $this->make_unpaid_booking();
        $this->throw_error_on_status_changed_to_cancelled();

        $result = CancellationHandler::cancel_booking($booking_id, 0, '', true);

        $this->assertTrue(
            is_wp_error($result) || is_array($result),
            'cancel_booking() returning normally is the proof the \\Error did not fatal the request.'
        );
    }

    /**
     * Unlike the post-commit phase, nothing has committed yet when this
     * \Error is thrown -- Status::update_status() runs before COMMIT. The
     * honest answer here is therefore the opposite of 14a's: "Cancellation
     * failed", not "cancelled with problems" -- reporting anything else
     * would tell the caller a booking was cancelled when the transaction
     * that would have cancelled it never reached COMMIT.
     */
    public function test_a_pre_commit_error_reports_cancellation_failed_not_cancelled(): void
    {
        $booking_id = $this->make_unpaid_booking();
        $this->throw_error_on_status_changed_to_cancelled();

        $result = CancellationHandler::cancel_booking($booking_id, 0, '', true);

        $this->assertTrue(
            is_wp_error($result),
            'Nothing committed; this must read as a failure, not "cancelled, with problems".'
        );
        $this->assertStringContainsString(
            'pre-commit listener exploded',
            $result->get_error_message(),
            'The WP_Error must carry the actual \\Error message, the same way the existing \\Exception branch does.'
        );
        // Status::update_status()'s own update_post_meta() call refreshed
        // WordPress's request-local post_meta cache as a side effect before
        // ROLLBACK ever ran; a raw ROLLBACK reverts the database row, not
        // that cache. Same fix, same reasoning, as every other place in
        // this codebase that reads a value after a lock/transaction
        // boundary (RefundStatus::transition(), settle_refund()'s own
        // "freshness first" comment).
        wp_cache_delete($booking_id, 'post_meta');

        $this->assertNotSame(
            Status::CANCELLED,
            Status::get($booking_id),
            'ROLLBACK must have undone the status write Status::update_status() made before firing the hook that threw.'
        );
    }

    /**
     * The behavioural pin item 10 exists for: ROLLBACK is the query that
     * actually runs when an \Error escapes the pre-COMMIT phase, not merely
     * "some catch block exists". Before this fix there was no catch for
     * \Error at all, so neither ROLLBACK nor COMMIT was ever reached -- the
     * request fatalled with the transaction left open. Mirrors
     * ManualBookingAtomicityTest's transaction-wiring assertions: this
     * proves the WIRING (that our code issues ROLLBACK, in order, and never
     * issues COMMIT), not real cross-connection atomicity, which one
     * PHPUnit connection cannot exercise.
     */
    public function test_a_pre_commit_error_actually_issues_rollback_not_commit(): void
    {
        $booking_id = $this->make_unpaid_booking();
        $this->throw_error_on_status_changed_to_cancelled();
        $this->start_recording();

        CancellationHandler::cancel_booking($booking_id, 0, '', true);

        $start    = $this->index_of('/^\s*START TRANSACTION\b/i');
        $rollback = $this->index_of('/^\s*ROLLBACK\b/i');
        $commit   = $this->index_of('/^\s*COMMIT\b/i');

        $this->assertGreaterThanOrEqual(0, $start, 'cancel_booking() must open a transaction before writing.');
        $this->assertGreaterThan(
            $start,
            $rollback,
            'ROLLBACK must be the query cancel_booking() issues once the \\Error escapes its try.'
        );
        $this->assertSame(
            -1,
            $commit,
            'COMMIT must never be reached -- the \\Error was thrown before cancel_booking() got there.'
        );
    }
}
