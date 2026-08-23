<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Payment\Refunds;

use MHMRentiva\Admin\Payment\Core\RefundStatus;
use MHMRentiva\Admin\Payment\Refunds\Service;
use MHMRentiva\Tests\Support\WooCommerceFixtures;
use WP_UnitTestCase;

/**
 * withLock() writes pending before it runs the operation, so that a direct
 * caller's terminal write has the precondition the matrix requires. That
 * write is unconditional; the operation's own validation is not.
 *
 * When validation refuses, the closure returns before finish() is ever
 * reached and nothing writes a terminal status. The booking is left saying
 * "Refund in progress" -- on a path that just decided no refund will run.
 * CancellationHandler already closes exactly this shape one layer up
 * (CancellationHandler.php:1148 moves pending -> failed with
 * reason 'validator_refused'); the direct path did not inherit the
 * discipline.
 *
 * Spec v3 section 7.5 puts the rule on the write, not on the flow: a stuck
 * pending is taken to failed by compare-and-swap. That belongs to every path
 * that writes pending.
 */
final class ValidatorRefusalLeavesNoStuckPendingTest extends WP_UnitTestCase
{
    use WooCommerceFixtures;

    private int $booking_id;
    private int $admin_id;

    public function setUp(): void
    {
        parent::setUp();

        $this->require_woocommerce();

        $this->admin_id = (int) self::factory()->user->create(array( 'role' => 'administrator' ));

        $this->booking_id = (int) self::factory()->post->create(array(
            'post_type'   => 'mhmrentiva_booking',
            'post_status' => 'publish',
        ));

        update_post_meta($this->booking_id, '_mhmrentiva_payment_status', 'paid');
    }

    /**
     * No paid order means refundable() is 0, which is the validator's
     * "No amount left to refund" branch -- the same early return the race
     * between the deposit screen's lock-free pre-check and the lock itself
     * lands on when a concurrent WooCommerce refund empties the balance.
     */
    public function test_a_refused_partial_refund_does_not_leave_the_booking_pending(): void
    {
        $result = Service::process(
            $this->booking_id,
            5000,
            'Refund issued from the deposit management screen.',
            $this->admin_id
        );

        $this->assertSame(
            '0',
            $result['mhmrentiva_refund'] ?? '',
            'Precondition: the validator must refuse this refund.'
        );
        $this->assertNotSame(
            RefundStatus::PENDING,
            RefundStatus::get($this->booking_id),
            'A refund that was refused before it ran cannot leave the booking saying it is in progress.'
        );
    }

    public function test_a_refused_full_refund_does_not_leave_the_booking_pending(): void
    {
        $result = Service::processFullRefund(
            $this->booking_id,
            'Refund issued from the deposit management screen.',
            $this->admin_id
        );

        $this->assertSame(
            '0',
            $result['mhmrentiva_refund'] ?? '',
            'Precondition: the validator must refuse this refund.'
        );
        $this->assertNotSame(
            RefundStatus::PENDING,
            RefundStatus::get($this->booking_id),
            'A refund that was refused before it ran cannot leave the booking saying it is in progress.'
        );
    }

    /**
     * Not just "not pending": the booking has to carry the outcome. failed is
     * the value the matrix allows from pending and the one the sibling path
     * already writes for a validator refusal.
     */
    public function test_the_refusal_is_recorded_as_failed_rather_than_erased(): void
    {
        Service::process(
            $this->booking_id,
            5000,
            'Refund issued from the deposit management screen.',
            $this->admin_id
        );

        $this->assertSame(RefundStatus::FAILED, RefundStatus::get($this->booking_id));
    }
}
