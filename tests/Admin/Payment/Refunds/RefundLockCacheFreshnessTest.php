<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Payment\Refunds;

use MHMRentiva\Admin\Payment\Core\Money;
use MHMRentiva\Admin\Payment\Refunds\Service;
use WP_UnitTestCase;

/**
 * Fable audit, H-1: "the lock serialises, but the reads inside it are served
 * from a cache primed before it."
 *
 * get_post_meta() answers from the request-local post_meta cache.
 * update_meta_cache() pulls the booking's whole meta set into that cache at
 * the first read, and a concurrent request's writes do not invalidate THIS
 * request's copy (WP default: no persistent object cache). Service::withLock()
 * waits for RefundLock, but waiting alone does not make the validator's
 * PaymentState::forBooking() read (inside the callable it runs) fresh --
 * that is the exact lesson already pinned one directory over,
 * RemainingPaymentHandler::resolve_remaining_order(): "Serialisation without
 * freshness is not mutual exclusion."
 *
 * This is the offline channel deliberately: PaymentState's offline leg reads
 * booking meta directly (_mhmrentiva_total_price, _mhmrentiva_remaining_amount,
 * _mhmrentiva_refunded_amount), with no WooCommerce order involved, so the
 * staleness can be manufactured with a single postmeta row instead of a full
 * WC_Order fixture -- and it is exactly the variant the audit calls "the
 * expensive one": a stale read here does not just misreport an audit trail
 * entry, it lays down a second full manual-refund record for money a
 * concurrent request already recorded as returned.
 */
final class RefundLockCacheFreshnessTest extends WP_UnitTestCase
{
    private int $booking_id;
    private int $admin_id;

    public function setUp(): void
    {
        parent::setUp();

        $this->admin_id   = (int) self::factory()->user->create(array( 'role' => 'administrator' ));
        $this->booking_id = (int) self::factory()->post->create(array(
            'post_type'   => 'mhmrentiva_booking',
            'post_status' => 'publish',
        ));

        update_post_meta($this->booking_id, '_mhmrentiva_payment_status', 'paid');
        update_post_meta($this->booking_id, '_mhmrentiva_total_price', '200');
        update_post_meta($this->booking_id, '_mhmrentiva_remaining_amount', '0');
        update_post_meta($this->booking_id, '_mhmrentiva_refunded_amount', '0');
    }

    public function tearDown(): void
    {
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'mhmrentiva_refund_lock_%'");

        parent::tearDown();
    }

    /**
     * Simulates the audit's exact interleave in one process: prime the
     * request-local cache the way a mhmrentiva_process_refund listener would
     * (an ordinary meta read, before this request ever reaches the lock),
     * then have "another request" commit the fact that the booking is
     * already fully refunded -- written straight to wp_postmeta so THIS
     * request's object cache stays stale, the way a separate PHP process
     * would leave it.
     *
     * Without Service::withLock()'s wp_cache_delete(), the validator inside
     * the lock decides from the primed snapshot (refunded_amount still '0'),
     * refundable() reports 200, and the operation proceeds to lay down a
     * SECOND full manual-refund record on top of the 200 that already moved.
     */
    public function test_withLock_does_not_decide_on_a_stale_cache(): void
    {
        global $wpdb;

        // Prime the cache exactly as a mhmrentiva_process_refund listener
        // would, before this request ever reaches RefundLock::acquire().
        get_post_meta($this->booking_id, '_mhmrentiva_refunded_amount', true);
        $this->assertNotFalse(
            wp_cache_get($this->booking_id, 'post_meta'),
            'Sanity check: the priming read must populate the post_meta cache.'
        );

        // Another request finishes the whole refund and commits it. Written
        // directly (not via update_post_meta()) so this request's cache is
        // left exactly as stale as a separate process would leave it.
        // _mhmrentiva_refunded_amount is stored in MINOR units (Service.php
        // writes it as $previous + $operation['refunded']), unlike
        // _mhmrentiva_total_price/_mhmrentiva_remaining_amount, which are
        // major-unit meta PaymentState itself converts via Money::toMinor().
        $wpdb->update(
            $wpdb->postmeta,
            array( 'meta_value' => (string) Money::toMinor('200') ),
            array(
                'post_id'  => $this->booking_id,
                'meta_key' => '_mhmrentiva_refunded_amount',
            )
        );

        $result = Service::processFullRefund($this->booking_id, 'freshness check', $this->admin_id);

        $this->assertSame(
            '0',
            $result['mhmrentiva_refund'],
            'Fresh data says nothing is left to refund; deciding from the stale cache would refund the'
                . ' whole 200 a second time.'
        );
        $this->assertSame(
            __('No amount left to refund', 'mhm-rentiva'),
            $result['mhmrentiva_refund_msg'],
            'The validator must refuse on a freshly-read refundable() of 0, not on some other failure.'
        );
        $this->assertSame(
            Money::toMinor('200'),
            (int) get_post_meta($this->booking_id, '_mhmrentiva_refunded_amount', true),
            'The refunded_amount record must still be the 200 the other request wrote -- a second'
                . ' manual record here would double it to 400.'
        );
    }
}
