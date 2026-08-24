<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Helpers;

use MHMRentiva\Admin\Booking\Core\Status;
use MHMRentiva\Admin\Booking\Helpers\CancellationHandler;
use MHMRentiva\Admin\PostTypes\Logs\PostType;
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
 * catch(\Throwable) closes the gap. This file pins three things beyond "the
 * request survives": that the answer here is the OPPOSITE of 14a's --
 * nothing has committed yet, so this must read as "Cancellation failed",
 * never "cancelled, with problems"; that ROLLBACK is the query that
 * actually runs, not merely that some catch block exists; and (fix round 1,
 * F4) that widening the catch to \Throwable does not leak an
 * engine-generated \Error/\TypeError message -- which can carry absolute
 * server paths -- to whichever caller asked, on an endpoint
 * (AccountController::ajax_cancel_booking()) a CUSTOMER reaches. Before
 * catch(\Throwable) existed, such an \Error fatalled to a generic WordPress
 * critical-error page instead of ever reaching a return value, so this
 * disclosure risk is new, not pre-existing. A plugin-authored \Exception's
 * message is still returned verbatim (unchanged, pre-existing behaviour);
 * only \Error/\TypeError gets a generic sentence. The full message, either
 * way, is logged via AdvancedLogger::error_linked() -- admin-only, safe.
 *
 * The second pin reuses ManualBookingAtomicityTest::start_recording()'s
 * technique: record every statement and neutralise transaction control to a
 * no-op SELECT, so the outer transaction WP_UnitTestCase wraps this test in
 * is never touched by cancel_booking()'s own START TRANSACTION/COMMIT/
 * ROLLBACK, while still recording, in order, which of them our code
 * actually issued.
 *
 * RECORDED, not fixed here (Task 14b review, "record, do not fix blind"):
 * the tests that do NOT call start_recording() (everything except
 * test_a_pre_commit_error_actually_issues_rollback_not_commit) let
 * cancel_booking() issue a REAL `START TRANSACTION`. MySQL has no true
 * nested transactions -- a second START TRANSACTION while one is already
 * open implicitly COMMITs whatever was pending, which on this project's own
 * record is the same class of defect Locker.php's COMMIT already carries
 * (wp-knowledge/laws feedback on this exact project). Concretely: the
 * booking post this test's own setUp-equivalent (make_unpaid_booking())
 * creates via self::factory()->post->create() is committed for real the
 * moment cancel_booking() opens its transaction, rather than being rolled
 * back by WP_UnitTestCase's own per-test transaction at tearDown. This is
 * not something a single test file can fix -- it is inherent to exercising
 * real ROLLBACK semantics against one MySQL connection shared with the test
 * harness's own transaction -- and test_a_pre_commit_error_reports_a_generic_message_not_the_raw_engine_text
 * specifically NEEDS the real ROLLBACK to prove the status write reverts, so
 * neutralising it there is not an option. tearDown() below tracks and
 * explicitly deletes every booking these tests create, so the leak this
 * causes does not accumulate across a full suite run even though the
 * underlying transaction-nesting hazard remains a known, tracked class.
 *
 * @covers \MHMRentiva\Admin\Booking\Helpers\CancellationHandler::cancel_booking
 */
final class CancellationPreCommitContainmentTest extends WP_UnitTestCase
{
    /** @var list<string> */
    private array $query_log = array();

    /** @var list<int> */
    private array $created_booking_ids = array();

    public function tearDown(): void
    {
        $this->query_log = array();

        // See the class docblock's "RECORDED, not fixed here" note: two of
        // these tests commit their fixture for real via a real nested
        // transaction. Explicit cleanup so that leak does not accumulate.
        foreach ($this->created_booking_ids as $booking_id) {
            wp_delete_post($booking_id, true);
        }
        $this->created_booking_ids = array();

        parent::tearDown();
    }

    private function make_unpaid_booking(): int
    {
        $booking_id = (int) self::factory()->post->create(array(
            'post_type'   => 'mhmrentiva_booking',
            'post_status' => 'publish',
        ));
        $this->created_booking_ids[] = $booking_id;

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
     * Fix round 1, F4's other half: a plugin-authored \Exception (the same
     * shape cancel_booking() itself throws for "Failed to update booking
     * status.") must keep returning its own message verbatim -- only
     * \Error/\TypeError gets the generic sentence. Mirrors
     * throw_error_on_status_changed_to_cancelled() exactly except for the
     * thrown type.
     */
    private function throw_exception_on_status_changed_to_cancelled(): void
    {
        add_action(
            'mhmrentiva_booking_status_changed',
            static function ($booking_id, $old_status, $new_status): void {
                if (Status::CANCELLED === $new_status) {
                    throw new \Exception('pre-commit listener threw a plugin exception');
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
     *
     * Fix round 1, F4: before this fix, the WP_Error message here was
     * `$e->getMessage()` verbatim -- for a plugin-authored \Exception that
     * is fine, but this test deliberately throws an \Error, which is
     * ENGINE-generated and can carry absolute server paths. The returned
     * message must now be the generic sentence, NOT the raw \Error text --
     * this is the customer-reachable half of the fix
     * (AccountController::ajax_cancel_booking() echoes this message to
     * whoever asked). test_a_pre_commit_error_is_still_logged_with_its_full_message
     * below pins the other half: the full text is not simply discarded, it
     * moves to the admin-only Logs.
     */
    public function test_a_pre_commit_error_reports_a_generic_message_not_the_raw_engine_text(): void
    {
        $booking_id = $this->make_unpaid_booking();
        $this->throw_error_on_status_changed_to_cancelled();

        $result = CancellationHandler::cancel_booking($booking_id, 0, '', true);

        $this->assertTrue(
            is_wp_error($result),
            'Nothing committed; this must read as a failure, not "cancelled, with problems".'
        );
        $this->assertStringNotContainsString(
            'pre-commit listener exploded',
            $result->get_error_message(),
            'An \\Error message is engine-generated and can carry absolute server paths -- it must not reach'
                . ' a customer-facing caller verbatim.'
        );
        $this->assertStringContainsString(
            'Cancellation failed',
            $result->get_error_message(),
            'Still an honest, generic failure sentence, not silence.'
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
     * Fix round 1, F4's other half, proven directly: the full \Error message
     * is not simply discarded when it stops being safe to return to the
     * caller -- it moves to the admin-only Logs, linked to the booking via
     * error_linked(), which is exactly the trace this whole task exists to
     * guarantee for every branch it touches. Before this fix round, this
     * catch was the one branch item 10 introduced that wrote nothing to the
     * Logs at all.
     */
    public function test_a_pre_commit_error_is_still_logged_with_its_full_message(): void
    {
        $booking_id = $this->make_unpaid_booking();
        $this->throw_error_on_status_changed_to_cancelled();

        CancellationHandler::cancel_booking($booking_id, 0, '', true);

        $found = false;
        foreach ($this->all_log_entries() as $log) {
            if (
                str_contains($log->post_content, 'pre-commit listener exploded')
                && $booking_id === (int) get_post_meta($log->ID, '_mhmrentiva_log_booking_id', true)
            ) {
                $found = true;
                break;
            }
        }

        $this->assertTrue(
            $found,
            'The full \\Error message must survive somewhere an operator can read it, linked to this booking'
                . ' -- even though it is no longer safe to return to whichever caller asked.'
        );
    }

    /**
     * Fix round 1, F4's split point: a plugin-authored \Exception (the same
     * shape cancel_booking() itself throws internally) is not engine text
     * and keeps returning its own message verbatim, exactly as before this
     * fix round -- only \Error/\TypeError changed behaviour.
     * DepositScreenCancellationTest::test_a_handler_error_is_reported_as_a_json_error
     * already pins this from the AJAX side for the pre-existing "Failed to
     * update booking status." \Exception; this pins it directly for a
     * throwable arriving from the same public hook the \Error tests above
     * use.
     */
    public function test_a_pre_commit_exception_still_returns_its_own_detailed_message(): void
    {
        $booking_id = $this->make_unpaid_booking();
        $this->throw_exception_on_status_changed_to_cancelled();

        $result = CancellationHandler::cancel_booking($booking_id, 0, '', true);

        $this->assertTrue(is_wp_error($result));
        $this->assertStringContainsString(
            'pre-commit listener threw a plugin exception',
            $result->get_error_message(),
            'A plugin-authored \\Exception is not engine text; its message is safe and must still be returned'
                . ' verbatim, exactly as before this fix round.'
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
