<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Actions;

use MHMRentiva\Admin\Booking\Actions\DepositManagementAjax;
use MHMRentiva\Admin\Core\CurrencyHelper;
use MHMRentiva\Admin\Payment\Core\Money;
use WP_Ajax_UnitTestCase;

/**
 * Slice-3 surface round, defects 1 and 3, both measured live against booking
 * 9482 on 2026-08-20.
 *
 * 1. DepositManagementAjax::process_refund() computed the refund from
 *    _mhmrentiva_deposit_amount. On a full-payment booking that meta is 0, so
 *    a booking with a genuinely refundable balance always answered "Refund not
 *    processed due to cancellation policy" and refunded nothing -- while
 *    PaymentState already knew the right number.
 * 2. On that zero path the handler still wrote a `refund_processed` log entry
 *    with refund_amount: 0 and blamed the cancellation policy even when no
 *    _mhmrentiva_cancellation_deadline existed. The real reason was the zero
 *    deposit, and the audit trail claimed a refund that never happened.
 *
 * The amount now comes from PaymentState::refundable(), which is an int in
 * MINOR units -- the trap this file exists to hold shut. The old code held a
 * float in major units and converted at the call site with Money::toMinor();
 * taking refundable() directly and leaving that conversion in place multiplies
 * every refund by 100, and dropping the display conversion divides it. Both
 * are asserted below against the exact number, not against "something
 * happened".
 */
final class RefundAmountComesFromStateTest extends WP_Ajax_UnitTestCase
{
    private int $admin_id;

    public function setUp(): void
    {
        parent::setUp();

        $this->admin_id = (int) self::factory()->user->create(array( 'role' => 'administrator' ));

        DepositManagementAjax::register();
    }

    public function tearDown(): void
    {
        $_POST = array();
        parent::tearDown();
    }

    /**
     * A cancelled full-payment booking whose money was collected offline.
     *
     * _mhmrentiva_deposit_amount is deliberately 0: that is what a
     * full-payment booking actually stores, and it is the meta the old
     * calculation read.
     */
    private function make_full_payment_booking(string $total = '500'): int
    {
        $booking_id = (int) self::factory()->post->create(array(
            'post_type'   => 'mhmrentiva_booking',
            'post_status' => 'publish',
        ));

        update_post_meta($booking_id, '_mhmrentiva_payment_type', 'full');
        update_post_meta($booking_id, '_mhmrentiva_payment_status', 'paid');
        update_post_meta($booking_id, '_mhmrentiva_status', 'cancelled');
        update_post_meta($booking_id, '_mhmrentiva_deposit_amount', 0);
        update_post_meta($booking_id, '_mhmrentiva_total_price', $total);
        update_post_meta($booking_id, '_mhmrentiva_remaining_amount', '0');

        return $booking_id;
    }

    /**
     * @return array<string, mixed>
     */
    private function call_refund(int $booking_id): array
    {
        wp_set_current_user($this->admin_id);

        $_POST = array(
            'nonce'      => wp_create_nonce('mhmrentiva_deposit_management_action'),
            'booking_id' => $booking_id,
        );

        try {
            $this->_handleAjax('mhmrentiva_deposit_process_refund');
        } catch (\WPAjaxDieContinueException | \WPAjaxDieStopException $e) {
            // wp_send_json_* terminates.
        }

        $decoded = json_decode($this->_last_response, true);

        return is_array($decoded) ? $decoded : array();
    }

    /**
     * @return list<string> Every log action recorded on the booking, in order.
     */
    private function log_actions(int $booking_id): array
    {
        $logs = get_post_meta($booking_id, '_mhmrentiva_booking_logs', true);

        if (! is_array($logs)) {
            return array();
        }

        return array_map(
            static fn( $entry ) => (string) ( $entry['action'] ?? '' ),
            array_values($logs)
        );
    }

    /**
     * Defect 1. RED before the fix: deposit_amount is 0, so the handler
     * refunded nothing and reported a cancellation policy that does not exist
     * on this booking.
     */
    public function test_a_full_payment_booking_refunds_the_refundable_balance_not_the_deposit(): void
    {
        $booking_id = $this->make_full_payment_booking('500');

        $response = $this->call_refund($booking_id);

        $this->assertTrue(
            (bool) ( $response['success'] ?? false ),
            'A cancelled booking with 500,00 genuinely refundable must refund it. Raw: ' . $this->_last_response
        );

        // The unit assertion. Service writes _mhmrentiva_refunded_amount in
        // minor units for the offline channel, so this is the exact number the
        // handler passed it. A x100 error asks for more than refundable() and
        // is refused outright; a /100 error records 500 kuruş instead of 500
        // lira and lands here.
        $this->assertSame(
            (int) Money::toMinor('500'),
            (int) get_post_meta($booking_id, '_mhmrentiva_refunded_amount', true),
            'The amount handed to the refund service must be refundable() itself -- '
                . 'an int in minor units, neither multiplied nor divided by 100.'
        );
    }

    /**
     * The display half of the same trap: refundable() is minor units and
     * format_price() expects major, so the success message is where a missing
     * Money::toMajor() shows up as a 100x number in front of the operator.
     */
    public function test_the_success_message_states_the_amount_in_major_units(): void
    {
        $booking_id = $this->make_full_payment_booking('500');

        $response = $this->call_refund($booking_id);

        $message = (string) ( $response['data']['message'] ?? '' );

        $this->assertStringContainsString(
            CurrencyHelper::format_price(500.0, 2),
            $message,
            'The operator must be told 500,00 -- not 50.000,00 (minor units printed raw) '
                . 'and not 5,00. Raw: ' . $this->_last_response
        );
    }

    /**
     * Defect 3, first half. The cancellation-policy sentence is correct here
     * and stays -- but the log must stop claiming a refund was processed.
     * RED before the fix: the handler wrote `refund_processed` with
     * refund_amount: 0.
     */
    public function test_a_policy_denied_refund_is_logged_as_skipped_not_processed(): void
    {
        $booking_id = $this->make_full_payment_booking('500');
        update_post_meta(
            $booking_id,
            '_mhmrentiva_cancellation_deadline',
            gmdate('Y-m-d H:i:s', strtotime('-2 days'))
        );

        $response = $this->call_refund($booking_id);

        $this->assertTrue(
            (bool) ( $response['success'] ?? false ),
            'A policy decision of "no refund due" is a normal outcome, not a failure. '
                . 'Raw: ' . $this->_last_response
        );
        $this->assertStringContainsString(
            'cancellation policy',
            (string) ( $response['data']['message'] ?? '' ),
            'The deadline exists and has passed, so the cancellation-policy sentence is the true one.'
        );
        $this->assertNotContains(
            'refund_processed',
            $this->log_actions($booking_id),
            'Nothing was refunded; the audit trail must not say a refund was processed.'
        );
        $this->assertContains(
            'refund_skipped',
            $this->log_actions($booking_id),
            'The skip is still an event worth recording -- under its own name, with its reason.'
        );
        $this->assertSame(
            '',
            (string) get_post_meta($booking_id, '_mhmrentiva_refunded_amount', true),
            'A booking owed nothing back must not record a refunded amount.'
        );
    }

    /**
     * Defect 3, second half. RED before the fix: this booking has no
     * cancellation deadline at all and nothing left to refund, and the handler
     * answered "Refund not processed due to cancellation policy" -- naming a
     * policy that does not exist for a booking whose real problem is an empty
     * balance.
     */
    public function test_a_booking_with_nothing_refundable_is_not_blamed_on_a_policy(): void
    {
        $booking_id = $this->make_full_payment_booking('500');
        // No offline money on record at all: total - remaining is 0.
        update_post_meta($booking_id, '_mhmrentiva_total_price', '');
        update_post_meta($booking_id, '_mhmrentiva_remaining_amount', '');

        $response = $this->call_refund($booking_id);

        $message = (string) ( $response['data']['message'] ?? '' );

        $this->assertStringNotContainsString(
            'cancellation policy',
            $message,
            'There is no cancellation deadline on this booking; the real reason is an empty '
                . 'refundable balance and that is what the operator must be told. Raw: '
                . $this->_last_response
        );
        $this->assertNotContains(
            'refund_processed',
            $this->log_actions($booking_id),
            'Nothing was refunded; the audit trail must not say a refund was processed.'
        );
        $this->assertSame(
            '',
            (string) get_post_meta($booking_id, '_mhmrentiva_refunded_amount', true),
            'No refunded amount may be recorded for a refund that did not happen.'
        );
    }

    /**
     * The trap, from the server side. Refunds\Service writes
     * payment_status = 'partially_refunded' when a refund does not clear the
     * balance, and process_refund() demanded exactly 'paid' -- so the second
     * refund of a partially refunded booking was refused by the very handler
     * that had produced the status. RED before the fix.
     */
    public function test_a_partially_refunded_booking_can_still_be_refunded(): void
    {
        $booking_id = $this->make_full_payment_booking('500');
        update_post_meta($booking_id, '_mhmrentiva_payment_status', 'partially_refunded');
        update_post_meta($booking_id, '_mhmrentiva_refunded_amount', (int) Money::toMinor('200'));

        $response = $this->call_refund($booking_id);

        $this->assertTrue(
            (bool) ( $response['success'] ?? false ),
            'A partial refund must not strand the rest of the money. Raw: ' . $this->_last_response
        );
        $this->assertSame(
            (int) Money::toMinor('500'),
            (int) get_post_meta($booking_id, '_mhmrentiva_refunded_amount', true),
            'The second refund covers the remaining 300,00, bringing the recorded total to 500,00.'
        );
    }
}
