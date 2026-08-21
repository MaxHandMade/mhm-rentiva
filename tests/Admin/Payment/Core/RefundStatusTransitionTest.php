<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Payment\Core;

use MHMRentiva\Admin\Payment\Core\RefundLock;
use MHMRentiva\Admin\Payment\Core\RefundStatus;
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
}
