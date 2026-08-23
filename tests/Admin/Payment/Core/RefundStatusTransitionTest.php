<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Payment\Core;

use MHMRentiva\Admin\Payment\Core\RefundLock;
use MHMRentiva\Admin\Payment\Core\RefundStatus;
use MHMRentiva\Admin\PostTypes\Logs\PostType;
use WP_UnitTestCase;

/**
 * Measured before this task: five sites wrote _mhmrentiva_refund_status with a
 * bare update_post_meta, four of them from an empty previous value. WordPress
 * core (wp-includes/meta.php:256, 279-281) returns early when $prev_value is
 * empty and only adds the meta_value where-clause when it is not, so those four
 * writes were never compare-and-swap. The lock is what makes a transition
 * atomic; this class is where that is enforced.
 */
final class RefundStatusTransitionTest extends WP_UnitTestCase
{
    private int $booking_id;

    public function setUp(): void
    {
        parent::setUp();
        $this->booking_id = self::factory()->post->create(
            array( 'post_type' => 'mhmrentiva_booking' )
        );
    }

    public function tearDown(): void
    {
        RefundLock::release( $this->booking_id );
        parent::tearDown();
    }

    public function test_it_refuses_to_write_when_the_lock_is_not_held(): void
    {
        $this->assertFalse( RefundLock::isHeld( $this->booking_id ) );

        $this->assertFalse(
            RefundStatus::transition( $this->booking_id, RefundStatus::PENDING ),
            'A transition without the booking lock must fail closed.'
        );
        $this->assertSame( '', RefundStatus::get( $this->booking_id ) );
    }

    public function test_it_writes_when_the_lock_is_held(): void
    {
        $this->assertTrue( RefundLock::acquire( $this->booking_id ) );

        $this->assertTrue( RefundStatus::transition( $this->booking_id, RefundStatus::PENDING ) );
        $this->assertSame( RefundStatus::PENDING, RefundStatus::get( $this->booking_id ) );
    }

    public function test_it_refuses_a_transition_the_matrix_does_not_allow(): void
    {
        RefundLock::acquire( $this->booking_id );
        RefundStatus::transition( $this->booking_id, RefundStatus::PENDING );
        RefundStatus::transition( $this->booking_id, RefundStatus::COMPLETED );

        $this->assertFalse(
            RefundStatus::transition( $this->booking_id, RefundStatus::PENDING ),
            'completed is terminal; it has no exit.'
        );
        $this->assertSame( RefundStatus::COMPLETED, RefundStatus::get( $this->booking_id ) );
    }

    public function test_a_transition_to_the_same_value_is_a_no_op_and_reports_false(): void
    {
        RefundLock::acquire( $this->booking_id );
        RefundStatus::transition( $this->booking_id, RefundStatus::NEEDS_REVIEW );

        $fired = 0;
        add_action(
            'mhmrentiva_refund_status_changed',
            static function () use ( &$fired ): void {
                ++$fired;
            }
        );

        $this->assertFalse(
            RefundStatus::transition( $this->booking_id, RefundStatus::NEEDS_REVIEW ),
            'X -> X must report "nothing changed" so callers do not re-notify.'
        );
        $this->assertSame( 0, $fired );
    }

    public function test_it_reads_the_current_value_fresh_after_the_lock(): void
    {
        RefundLock::acquire( $this->booking_id );
        RefundStatus::transition( $this->booking_id, RefundStatus::PENDING );

        // Prime the request-local cache, then change the row behind it the way
        // a concurrent request that already committed would have.
        get_post_meta( $this->booking_id, RefundStatus::META_KEY, true );
        global $wpdb;
        $wpdb->update(
            $wpdb->postmeta,
            array( 'meta_value' => RefundStatus::COMPLETED ),
            array(
                'post_id'  => $this->booking_id,
                'meta_key' => RefundStatus::META_KEY,
            )
        );

        $this->assertFalse(
            RefundStatus::transition( $this->booking_id, RefundStatus::MANUAL_PENDING ),
            'A stale read would allow pending -> manual_pending on a row that already says completed.'
        );
    }

    public function test_it_announces_the_change_with_both_values(): void
    {
        RefundLock::acquire( $this->booking_id );
        RefundStatus::transition( $this->booking_id, RefundStatus::PENDING );

        $seen = array();
        add_action(
            'mhmrentiva_refund_status_changed',
            static function ( $id, $new, $old, $context ) use ( &$seen ): void {
                $seen = compact( 'id', 'new', 'old', 'context' );
            },
            10,
            4
        );

        RefundStatus::transition(
            $this->booking_id,
            RefundStatus::FAILED,
            array( 'surface' => 'admin_deposit' )
        );

        $this->assertSame( $this->booking_id, $seen['id'] );
        $this->assertSame( RefundStatus::FAILED, $seen['new'] );
        $this->assertSame( RefundStatus::PENDING, $seen['old'] );
        $this->assertSame( 'admin_deposit', $seen['context']['surface'] );
    }

    /**
     * The write can be refused after every guard has passed: a plugin can
     * short-circuit update_post_metadata, the row can fail to write, and the
     * $prev_value compare-and-swap this class documents as "a second barrier"
     * can reject the update inside the 300s lease-stealing window. In all
     * three the status did not change, so the event must not claim it did --
     * spec v3 section 2.3: "the event and the status cannot diverge".
     */
    public function test_it_reports_false_and_stays_silent_when_the_meta_write_does_not_land(): void
    {
        RefundLock::acquire( $this->booking_id );

        add_filter(
            'update_post_metadata',
            static function ( $check, $object_id, $meta_key ) {
                return RefundStatus::META_KEY === $meta_key ? false : $check;
            },
            10,
            3
        );

        $fired = 0;
        add_action(
            'mhmrentiva_refund_status_changed',
            static function () use ( &$fired ): void {
                ++$fired;
            }
        );

        $this->assertFalse(
            RefundStatus::transition( $this->booking_id, RefundStatus::PENDING ),
            'A write that never landed must not report a successful transition.'
        );
        $this->assertSame( 0, $fired, 'No status changed, so no listener may be told one did.' );
        $this->assertSame( '', RefundStatus::get( $this->booking_id ) );
    }

    /**
     * A refused write and a refused matrix edge both leave transition()
     * returning false, and every caller reads that single bit. The matrix
     * refusal is ordinary -- callers already narrate it. A database that
     * refused the write is not ordinary, and without a trace of its own it
     * arrives at the operator wearing the matrix's clothes.
     */
    public function test_it_leaves_a_trace_when_the_meta_write_is_refused(): void
    {
        RefundLock::acquire( $this->booking_id );

        add_filter(
            'update_post_metadata',
            static function ( $check, $object_id, $meta_key ) {
                return RefundStatus::META_KEY === $meta_key ? false : $check;
            },
            10,
            3
        );

        RefundStatus::transition( $this->booking_id, RefundStatus::PENDING );

        $logs = get_posts(
            array(
                'post_type'      => PostType::TYPE,
                'posts_per_page' => 5,
                'orderby'        => 'ID',
                'order'          => 'DESC',
                'post_status'    => 'publish',
            )
        );

        $refusal_log = null;
        foreach ( $logs as $log ) {
            if ( false !== strpos( $log->post_content, 'refund_status write was refused' ) ) {
                $refusal_log = $log;
                break;
            }
        }

        $this->assertNotNull(
            $refusal_log,
            'A refused write must be distinguishable from a refused matrix edge.'
        );
        $this->assertStringContainsString( (string) $this->booking_id, $refusal_log->post_content );
        $this->assertStringContainsString( RefundStatus::PENDING, $refusal_log->post_content );
    }

    /**
     * Task 12 (slice 5), correction #3: AutoCancel::not_parked_for_review()
     * needs the terminal set without restating it, the same way
     * cancellable_statuses() derives Status's set instead of a hand-written
     * list beside it. This pins the derivation itself against the matrix's
     * own docblock claim ("The four terminal states ... are absent as
     * keys") rather than trusting the comment to stay true.
     */
    public function test_terminal_states_are_derived_from_the_matrix_and_match_the_four_named_in_its_docblock(): void
    {
        $this->assertEqualsCanonicalizing(
            array(
                RefundStatus::NOT_REQUIRED,
                RefundStatus::COMPLETED_EXTERNALLY,
                RefundStatus::COMPLETED,
                RefundStatus::COMPLETED_MANUALLY,
            ),
            RefundStatus::terminalStates()
        );
    }

    /**
     * needs_review itself must NOT be reported as terminal -- it has an edge
     * to PENDING and to NOT_REQUIRED. AutoCancel::parked_refund_statuses()
     * depends on this distinction: it adds NEEDS_REVIEW to terminalStates()
     * explicitly rather than relying on it being included already.
     */
    public function test_needs_review_is_not_a_terminal_state(): void
    {
        $this->assertNotContains( RefundStatus::NEEDS_REVIEW, RefundStatus::terminalStates() );
    }
}
