<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Payment\Refunds;

use MHMRentiva\Admin\Payment\Core\Money;
use MHMRentiva\Admin\Payment\Refunds\Service;
use MHMRentiva\Tests\Support\WooCommerceFixtures;
use WP_UnitTestCase;

/**
 * N-02, second half: one writer per channel.
 *
 * Measured before this task: wc_create_refund() fires
 * woocommerce_refund_created, WooCommerceBridge::handle_order_refunded() writes
 * _mhmrentiva_refunded_amount ABSOLUTELY, control returns to Service, and
 * Service::updateBookingMeta() read that value back and wrote
 * previous + amount on top. A 20 refund on a 120 order therefore recorded 40.
 *
 * The same method then compared the doubled figure against
 * _mhmrentiva_payment_amount -- always 0 -- so it always wrote
 * payment_status = 'refunded', overwriting the correct 'partially_refunded'
 * the hook had just written one line earlier.
 *
 * The offline channel is the deliberate exception: no WC_Order_Refund object
 * exists there, no hook fires, and that meta IS the ledger, so Service
 * accumulates it. Both directions are asserted here so the exception cannot be
 * mistaken for the rule.
 */
final class RefundSingleWriterTest extends WP_UnitTestCase
{
    use WooCommerceFixtures;

    /** @var int */
    private $booking_id;

    /** @var int */
    private $admin_id;

    public function setUp(): void
    {
        parent::setUp();

        $this->require_woocommerce();

        $this->admin_id   = (int) self::factory()->user->create(array( 'role' => 'administrator' ));
        $this->booking_id = (int) self::factory()->post->create(array(
            'post_type'   => 'mhmrentiva_booking',
            'post_status' => 'publish',
        ));

        update_post_meta($this->booking_id, '_mhmrentiva_payment_status', 'paid');
    }

    public function test_a_woocommerce_refund_is_recorded_once(): void
    {
        $this->create_paid_order_for_booking($this->booking_id, '120');

        Service::process($this->booking_id, Money::toMinor('20'), 'single writer', $this->admin_id);

        $this->assertSame(
            Money::toMinor('20'),
            (int) get_post_meta($this->booking_id, '_mhmrentiva_refunded_amount', true),
            'Recording 40 for a 20 refund is the additive write this task removes.'
        );
    }

    public function test_a_partial_refund_leaves_the_booking_partially_refunded(): void
    {
        $this->create_paid_order_for_booking($this->booking_id, '120');

        Service::process($this->booking_id, Money::toMinor('20'), 'status after partial', $this->admin_id);

        $this->assertSame(
            'partially_refunded',
            (string) get_post_meta($this->booking_id, '_mhmrentiva_payment_status', true)
        );
    }

    public function test_a_full_refund_marks_the_booking_refunded(): void
    {
        $this->create_paid_order_for_booking($this->booking_id, '120');

        Service::processFullRefund($this->booking_id, 'status after full', $this->admin_id);

        $this->assertSame(
            'refunded',
            (string) get_post_meta($this->booking_id, '_mhmrentiva_payment_status', true)
        );
    }

    public function test_two_successive_partial_refunds_do_not_compound(): void
    {
        $this->create_paid_order_for_booking($this->booking_id, '120');

        Service::process($this->booking_id, Money::toMinor('20'), 'first', $this->admin_id);
        Service::process($this->booking_id, Money::toMinor('30'), 'second', $this->admin_id);

        $this->assertSame(
            Money::toMinor('50'),
            (int) get_post_meta($this->booking_id, '_mhmrentiva_refunded_amount', true)
        );
    }

    public function test_the_offline_channel_accumulates_because_nothing_else_records_it(): void
    {
        update_post_meta($this->booking_id, '_mhmrentiva_total_price', '80');
        update_post_meta($this->booking_id, '_mhmrentiva_remaining_amount', '0');

        Service::process($this->booking_id, Money::toMinor('30'), 'offline first', $this->admin_id);
        Service::process($this->booking_id, Money::toMinor('20'), 'offline second', $this->admin_id);

        $this->assertSame(
            Money::toMinor('50'),
            (int) get_post_meta($this->booking_id, '_mhmrentiva_refunded_amount', true),
            'No WooCommerce refund object exists offline, so Service is the only recorder.'
        );
    }

    private function seed_deposit_and_remaining(string $deposit, string $remaining): void
    {
        $first = $this->create_paid_order_for_booking($this->booking_id, $deposit);
        $this->wire_line_item_booking_id($first);

        $second = $this->create_paid_order_for_booking($this->booking_id, $remaining);
        update_post_meta($this->booking_id, '_mhmrentiva_remaining_order_id', $second->get_id());
        $this->wire_line_item_booking_id($second);
    }

    /**
     * WooCommerceFixtures::create_paid_order_for_booking() wires
     * `_mhmrentiva_booking_id` onto the ORDER only -- its own docblock says so.
     * Production always wires the same key onto the order's LINE ITEM too
     * (WooCommerceBridge.php :911 at checkout, :1026 on
     * booking-created-from-order, RemainingPaymentHandler.php :256 on the
     * remaining-payment order), and handle_order_status_change() reads ONLY
     * the item copy (:1300) -- never the order's. Without this, that
     * handler's `case 'refunded':` is structurally dead for every order this
     * fixture builds, which is exactly how the second path to the terminal
     * mid-walk defect stayed invisible to this file's own gate test.
     *
     * Wired here, locally to this test class, rather than in
     * WooCommerceFixtures itself: measured directly with a throwaway scratch
     * test that wiring it into the shared fixture flips a fresh booking's
     * _mhmrentiva_status to 'confirmed' (and _mhmrentiva_payment_status to
     * 'paid') on `create_paid_order_for_booking()` alone, silently, for every
     * one of the eighteen files that share that trait -- none of them assert
     * against it today, so all eighteen still pass, but that is a live
     * behavioural change to shared test infrastructure this task's scope did
     * not ask for. The docblock on the shared fixture should note the gap
     * this leaves (item meta is not wired, so `handle_order_status_change()`
     * cannot see orders it builds) so the next reader does not assume parity
     * with production it does not have.
     */
    private function wire_line_item_booking_id( \WC_Order $order ): void
    {
        foreach ( $order->get_items() as $item ) {
            $item->add_meta_data( '_mhmrentiva_booking_id', $this->booking_id, true );
            $item->save();
        }
    }

    public function test_a_multi_order_full_refund_records_the_whole_amount_not_the_last_leg(): void
    {
        // Before this task the hook wrote `Money::toMinor( $order->get_total_refunded() )` --
        // ONE order's figure. Refunding a 30+70 booking in full therefore ended with 70
        // recorded, because the second leg's absolute write erased the first leg's. That is
        // N-01, and this is the assertion that catches it coming back.
        $this->seed_deposit_and_remaining('30', '70');

        Service::processFullRefund($this->booking_id, 'multi-order absolute write', $this->admin_id);

        $this->assertSame(
            Money::toMinor('100'),
            (int) get_post_meta($this->booking_id, '_mhmrentiva_refunded_amount', true),
            'The booking refunded 100. Recording 70 means the last leg overwrote the first.'
        );
    }

    public function test_draining_the_first_order_does_not_mark_the_whole_booking_refunded(): void
    {
        // The defect this locks was introduced by Task 6 and is terminal: refunding 50 of a
        // 30+70 booking fully drains the FIRST order, so the hook's per-order comparison
        // (30 >= 30) fired Status::update_status( 'refunded' ) mid-walk, while 50 was still
        // outstanding. Status.php gives REFUNDED an empty transition list, so on a completed
        // rental that is irreversible -- and it fires the customer's status-change e-mail and
        // invalidates the availability cache on the way out.
        //
        // There are TWO paths to that same terminal write, and wc_create_refund() drives
        // BOTH from a single call: it flips order A's own status to 'refunded' (WC 11.0.1
        // wc-order-functions.php :731) -- which fires handle_order_status_change() -- before
        // it fires woocommerce_refund_created (:742) -- which fires handle_order_refunded(),
        // the one this task's Step 3 fixed. wire_line_item_booking_id() (see
        // seed_deposit_and_remaining()) makes handle_order_status_change() resolve a
        // booking id at all -- the shared fixture alone leaves that handler permanently
        // dead -- so this assertion now exercises both paths, not just the one that was
        // already fixed.
        //
        // The comparison is booking-level in both places now. A partial refund must leave
        // the booking's own status alone no matter how completely one of its orders was
        // drained.
        $this->seed_deposit_and_remaining('30', '70');

        \MHMRentiva\Admin\Booking\Core\Status::update_status($this->booking_id, 'completed', 0);
        $before = (string) get_post_meta($this->booking_id, '_mhmrentiva_status', true);

        $this->assertSame('completed', $before, 'Guard: the fixture must start completed.');

        Service::process($this->booking_id, Money::toMinor('50'), 'partial across orders', $this->admin_id);

        $this->assertSame(
            'completed',
            (string) get_post_meta($this->booking_id, '_mhmrentiva_status', true),
            'A partial refund must not terminally mark a completed booking refunded.'
        );
        $this->assertSame(
            'partially_refunded',
            (string) get_post_meta($this->booking_id, '_mhmrentiva_payment_status', true)
        );
    }
}
