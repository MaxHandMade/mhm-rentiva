<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Meta;

use MHMRentiva\Admin\Booking\Meta\BookingRefundMetaBox;
use MHMRentiva\Admin\Payment\Core\RefundStatus;
use MHMRentiva\Tests\Support\WooCommerceFixtures;
use WP_UnitTestCase;

/**
 * Task 10 (slice 5). The refund state machine (RefundStatus, Tasks 1-2) and
 * Slice 4's cancellation flow can now leave a booking in manual_pending,
 * partial_failure or needs_review -- and every one of those is produced by
 * exactly the set of bookings for which render()'s own guard
 * (`array() === $state->orders() ? $state->refundableManual() : 0`) forces
 * $remaining to 0: a WooCommerce-order booking, or an offline booking whose
 * manual refund was already recorded. Rendered after that guard, the status
 * row would be invisible for every booking that needs it. render_status_row()
 * is called before $state is even resolved, so it never depends on the guard
 * that follows it.
 *
 * This row is information, not a money action -- Task 9's
 * MoneyAuthorization::mayMoveMoney() gate belongs to the deposit-screen LINK
 * only (see BookingRefundMetaBoxRendersTest); it is deliberately not asked
 * here. Anyone who can see this metabox (i.e. can edit the booking) sees the
 * status.
 */
final class BookingRefundMetaBoxStatusRowTest extends WP_UnitTestCase
{
    use WooCommerceFixtures;

    /** @var int */
    private $booking_id;

    public function setUp(): void
    {
        parent::setUp();

        wp_set_current_user((int) self::factory()->user->create(array( 'role' => 'administrator' )));

        $this->booking_id = (int) self::factory()->post->create(array(
            'post_type'   => 'mhmrentiva_booking',
            'post_status' => 'publish',
        ));
    }

    private function render(): string
    {
        ob_start();
        BookingRefundMetaBox::render(get_post($this->booking_id));
        return (string) ob_get_clean();
    }

    /**
     * The safety-critical assertion. A WooCommerce-order booking is exactly
     * the shape render()'s guard forces $remaining to 0 for
     * (BookingRefundMetaBoxRendersTest::
     * test_a_paid_woocommerce_order_shows_no_summary_even_with_offline_data_present
     * proves the offline summary disappears for it) -- so this is the one
     * case where a status row placed AFTER the early return would never be
     * seen at all. create_paid_order_for_booking() is what makes
     * PaymentState::forBooking()->orders() genuinely non-empty (via
     * resolvePaidOrders() reading _mhmrentiva_woocommerce_order_id and the
     * order's own get_date_paid()); a booking with no such order would leave
     * $remaining > 0 from an unrelated code path, the early return would
     * never run, and this test would pass whether or not the row sits before
     * it -- proving nothing about the guard this task exists to get behind.
     */
    public function test_a_woocommerce_order_booking_still_shows_the_status_row(): void
    {
        $this->require_woocommerce();

        update_post_meta($this->booking_id, '_mhmrentiva_payment_status', 'paid');
        update_post_meta($this->booking_id, '_mhmrentiva_total_price', '80');
        update_post_meta($this->booking_id, '_mhmrentiva_remaining_amount', '0');

        $this->create_paid_order_for_booking($this->booking_id, '80');

        $html = $this->render();

        // Confirms the fixture actually reaches the early-return branch this
        // task is about -- see the class-level PaymentState note above.
        $this->assertStringNotContainsString(
            'Remaining refundable',
            $html,
            'Sanity check: this fixture must exercise the early-return branch, or assertion 1 proves nothing about it.'
        );

        $this->assertStringContainsString('Refund status:', $html);
    }

    /**
     * Every entry RefundStatus::labels() maps a status to must reach the
     * screen. The provider reads labels() itself rather than mirroring its
     * values as a literal list, so this test grows automatically the day a
     * new status is added instead of silently missing it.
     *
     * @dataProvider provide_every_status_and_label
     */
    public function test_every_known_status_prints_its_label(string $status, string $expected_label): void
    {
        if ('' !== $status) {
            // Bypasses RefundStatus::transition() on purpose: the transition
            // matrix does not allow reaching every one of these states in a
            // single hop from '', and this test is about the label mapping
            // render_status_row() draws from RefundStatus::labels(), not
            // about the transition machine itself (that machine has its own
            // test, RefundStatusTransitionTest).
            update_post_meta($this->booking_id, RefundStatus::META_KEY, $status);
        }

        $html = $this->render();

        $this->assertStringContainsString($expected_label, $html);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public function provide_every_status_and_label(): array
    {
        $cases = array();

        foreach (RefundStatus::labels() as $status => $label) {
            $key = '' === $status ? '(empty)' : $status;
            $cases[$key] = array( $status, $label );
        }

        return $cases;
    }

    /**
     * The meta key has no writer discipline enforced at read time --
     * RefundStatus::transition() is the only legitimate writer, but a
     * pre-slice-5 site, a migration, or a foreign integration can still have
     * left something else in _mhmrentiva_refund_status. render_status_row()
     * must fall back to a label rather than echo that value straight into
     * the page.
     *
     * Writing 'legacy_value' directly bypasses RefundStatus::transition() on
     * purpose -- transition() validates against a fixed matrix and would
     * refuse this value outright. bin/check-refund-status-writers.php scans
     * src/ only, so this direct write in a test does not trip that gate; the
     * whole point of this test is what happens when something else already
     * bypassed it in production.
     */
    public function test_an_unrecognised_status_value_is_not_echoed_raw(): void
    {
        update_post_meta($this->booking_id, RefundStatus::META_KEY, 'legacy_value');

        $html = $this->render();

        $this->assertStringNotContainsString('legacy_value', $html);
        $this->assertStringContainsString('Unrecognised refund status', $html);
    }

    /**
     * A brand-new booking that has never gone through any refund flow at
     * all -- no meta row has ever been written -- must not look identical to
     * an unrecognised value; RefundStatus::get() returns '' for it, and
     * labels() maps '' to its own explanatory sentence.
     */
    public function test_a_booking_with_no_refund_meta_shows_the_default_label(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('No refund flow has run', $html);
    }
}
