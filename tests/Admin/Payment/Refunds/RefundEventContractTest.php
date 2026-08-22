<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Payment\Refunds;

use MHMRentiva\Admin\Booking\Actions\DepositManagementAjax;
use MHMRentiva\Admin\Booking\Helpers\CancellationHandler;
use MHMRentiva\Admin\Payment\Core\Money;
use MHMRentiva\Admin\Payment\Core\RefundStatus;
use MHMRentiva\Admin\Payment\Refunds\RefundValidator;
use MHMRentiva\Admin\Payment\Refunds\Service;
use MHMRentiva\Tests\Support\WooCommerceFixtures;
use MHMRentiva\Tests\Support\WooCommerceRefundGatewayDouble;
use MHMRentiva\Tests\Support\WooCommerceRefundGatewayRegistration;
use WP_Ajax_UnitTestCase;
use WP_Error;

/**
 * Task 13 (slice 5): mhmrentiva_refund_completed follows the money, not the
 * operation's final status.
 *
 * Before this task the event was bound to Service::finish() writing a
 * terminal status at all -- fired for every completed/manual_pending/
 * partial_failure/failed operation alike. A deposit booking's card leg can
 * genuinely come back through the gateway while its bank-transfer leg still
 * waits on a human: the operation's overall status is then manual_pending,
 * and nothing tracking money ever heard that real gateway money returned.
 * announceCompletion() now gates on auto_refunded > 0 -- the money moved,
 * not the label finish() attached to the operation as a whole.
 *
 * Two call sites reach announceCompletion() today (Service.php's failure
 * branch and its success branch), so the guard has to cut both: a 'failed'
 * operation that moved nothing must not announce, and a 'partial_failure'
 * operation that did move gateway money before the failing leg must still
 * announce.
 *
 * completed_manually (DepositManagementAjax::close_manual_refund(), Task 11)
 * and completed_externally (CancellationHandler::settle_refund()) write
 * their terminal status directly through RefundStatus::transition() -- they
 * never run through Service::finish() at all, so they were never capable of
 * firing this event even before this task. Pinned here as a regression
 * guard: announceCompletion() is private to Service, and only finish() may
 * call it.
 */
final class RefundEventContractTest extends WP_Ajax_UnitTestCase
{
    use WooCommerceFixtures;
    use WooCommerceRefundGatewayRegistration;

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
    }

    public function tearDown(): void
    {
        $_POST = array();

        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'mhmrentiva_refund_lock_%'");

        parent::tearDown();
    }

    /**
     * Same idiom as RefundMixedModeMessagingTest::wire_mixed_legs(): the
     * deposit (30) is auto-capable and walks first, the remainder (70) has
     * no payment method so modeForOrder() falls back to MODE_MANUAL. The
     * operation-level mode still collapses to 'manual' (RefundStatus lands
     * on manual_pending), but auto_refunded and manual_refunded are both
     * greater than zero -- exactly the shape this task's guard exists for.
     *
     * @return array{0: \WC_Order, 1: \WC_Order} auto order, manual order
     */
    private function wire_mixed_legs(): array
    {
        $this->require_woocommerce();
        $this->register_refund_gateway_double();

        $auto_order = $this->create_paid_order_for_booking($this->booking_id, '30');
        $auto_order->set_payment_method(WooCommerceRefundGatewayDouble::ID);
        $auto_order->save();

        $manual_order = $this->create_paid_order_for_booking($this->booking_id, '70');
        update_post_meta($this->booking_id, '_mhmrentiva_remaining_order_id', $manual_order->get_id());

        return array( $auto_order, $manual_order );
    }

    /**
     * @return array<int, array{ok: bool, refunded: int, auto_refunded: int, manual_refunded: int, mode: string, txn_ids: array<int, string>, channel: string, message: string, order_refunds: array<int, string>, refund_status: string, currency: string}>
     */
    private function capture_completion(callable $operation): array
    {
        $payloads = array();

        $listener = static function (int $booking_id, array $operation) use (&$payloads): void {
            $payloads[] = $operation;
        };

        add_action('mhmrentiva_refund_completed', $listener, 10, 2);

        try {
            $operation();
        } finally {
            remove_action('mhmrentiva_refund_completed', $listener, 10);
        }

        return $payloads;
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
     * Plan assertions 1 + 2: a manual_pending operation whose card leg
     * genuinely came back through the gateway must announce completion, and
     * the payload must carry enough for a listener to tell what actually
     * moved.
     */
    public function test_a_manual_pending_operation_that_moved_gateway_money_announces_completion(): void
    {
        list( $auto_order, $manual_order ) = $this->wire_mixed_legs();

        $fired = $this->capture_completion(function (): void {
            $result = Service::processFullRefund($this->booking_id, 'mixed completion', $this->admin_id);
            $this->assertSame('1', $result['mhmrentiva_refund'], $result['mhmrentiva_refund_msg']);
        });

        $this->assertCount(
            1,
            $fired,
            'A manual_pending operation that genuinely moved gateway money must announce completion exactly once -- the event follows the money, not the label finish() gave the operation.'
        );

        $payload = $fired[0];

        $this->assertSame(
            RefundStatus::MANUAL_PENDING,
            RefundStatus::get($this->booking_id),
            'Sanity: this operation must actually be the manual_pending shape the docblock describes.'
        );
        $this->assertGreaterThan(0, $payload['auto_refunded'], 'The card leg must be reflected in the payload.');
        $this->assertGreaterThan(0, $payload['manual_refunded'], 'The transfer leg must be reflected in the payload.');
        $this->assertSame(
            RefundValidator::CHANNEL_MIXED,
            $payload['channel'],
            'Both legs moved money through different channels; the payload must say so rather than picking one.'
        );
        $this->assertSame(RefundStatus::MANUAL_PENDING, $payload['refund_status']);
        $this->assertNotSame('', $payload['currency'], 'A listener cannot interpret the amounts without a currency.');

        $this->assertSame(
            array(
                $auto_order->get_id()   => (string) $payload['order_refunds'][ $auto_order->get_id() ],
                $manual_order->get_id() => (string) $payload['order_refunds'][ $manual_order->get_id() ],
            ),
            $payload['order_refunds'],
            'Every order that actually moved money must map to the refund record it produced, and no other order.'
        );
        $this->assertNotSame('', $payload['order_refunds'][ $auto_order->get_id() ]);
        $this->assertNotSame('', $payload['order_refunds'][ $manual_order->get_id() ]);

        // Correction #6: _mhmrentiva_refunded_amount is ALSO kept absolute
        // (not accumulated) by WooCommerceBridge::handle_order_refunded(),
        // a pre-existing, entirely separate write path hooked off
        // WooCommerce's own refund-creation event -- it fires for both legs
        // here and is expected to leave the correct total (100) behind. What
        // this proves is the absence of DOUBLE counting: Service::finish()'s
        // own accumulation only runs when CHANNEL_OFFLINE === channel
        // (:457), and 'mixed' is never that value, so this operation must
        // not ALSO add its 100 on top of the bridge's 100 (which would read
        // 200).
        $this->assertSame(
            Money::toMinor('100'),
            (int) get_post_meta($this->booking_id, '_mhmrentiva_refunded_amount', true),
            "The bridge's absolute write must stand alone -- Service::finish()'s CHANNEL_OFFLINE-only "
                . 'accumulation must not also fire for a mixed operation and double it.'
        );
    }

    /**
     * Plan assertion 3: DepositManagementAjax::close_manual_refund() (Task
     * 11) writes completed_manually directly through RefundStatus, never
     * through Service::finish() -- so it was never capable of firing this
     * event, before or after this task.
     */
    public function test_completed_manually_does_not_announce_completion(): void
    {
        update_post_meta($this->booking_id, RefundStatus::META_KEY, RefundStatus::MANUAL_PENDING);
        DepositManagementAjax::register();

        $fired = $this->capture_completion(function (): void {
            $response = $this->call_close($this->booking_id, array( 'reference' => 'CASH-0001' ));
            $this->assertTrue((bool) ( $response['success'] ?? false ), 'Raw: ' . $this->_last_response);
        });

        $this->assertSame(
            RefundStatus::COMPLETED_MANUALLY,
            RefundStatus::get($this->booking_id),
            'Sanity: the close must actually have landed on completed_manually.'
        );
        $this->assertCount(
            0,
            $fired,
            'Confirming a hand transfer never runs through Service::finish() and must not fire the money-following event.'
        );
    }

    /**
     * Plan assertion 4: CancellationHandler::settle_refund() writes
     * completed_externally directly through RefundStatus when an
     * integrator's mhmrentiva_process_refund listener already moved the
     * money -- also never through Service::finish().
     *
     * Built as a pure-offline booking already fully refunded (no WooCommerce
     * order at all): PaymentState::resolveOfflineChannel() reports
     * paid() > 0 and refundable() <= 0 without needing a gateway double or a
     * real external-refund listener, which is the minimal shape that reaches
     * this branch (settle_refund():598-616).
     */
    public function test_completed_externally_does_not_announce_completion(): void
    {
        update_post_meta($this->booking_id, '_mhmrentiva_status', 'confirmed');
        update_post_meta($this->booking_id, '_mhmrentiva_total_price', '100');
        update_post_meta($this->booking_id, '_mhmrentiva_remaining_amount', '0');
        update_post_meta($this->booking_id, '_mhmrentiva_refunded_amount', Money::toMinor('100'));

        $fired = $this->capture_completion(function (): void {
            $result = CancellationHandler::cancel_booking($this->booking_id, $this->admin_id, '', true);
            $this->assertNotInstanceOf(
                WP_Error::class,
                $result,
                $result instanceof WP_Error ? $result->get_error_message() : ''
            );
        });

        $this->assertSame(
            RefundStatus::COMPLETED_EXTERNALLY,
            RefundStatus::get($this->booking_id),
            'Sanity: this booking must actually be the already-fully-offline-refunded shape that lands here.'
        );
        $this->assertCount(
            0,
            $fired,
            'Confirming money that moved outside the plugin never runs through Service::finish() and must not fire the money-following event.'
        );
    }

    /**
     * Correction #1's first addition: nothing moved, so the operation is a
     * plain 'failed', and the event must not fire for it.
     *
     * Mirrors RefundPartialFailureTest::test_a_first_leg_failure_records_failed():
     * a single order with no payment method (MODE_MANUAL), refused on its
     * only leg.
     */
    public function test_a_failed_operation_that_moved_no_money_does_not_announce_completion(): void
    {
        $this->require_woocommerce();
        $this->create_paid_order_for_booking($this->booking_id, '120');

        add_action(
            'woocommerce_refund_created',
            static function (): void {
                throw new \RuntimeException('refused on purpose');
            },
            1,
            2
        );

        $fired = $this->capture_completion(function (): void {
            Service::processFullRefund($this->booking_id, 'pure failure', $this->admin_id);
        });

        $this->assertSame(
            RefundStatus::FAILED,
            RefundStatus::get($this->booking_id),
            'Sanity: nothing moved, so this must be a plain failure, not a partial one.'
        );
        $this->assertCount(
            0,
            $fired,
            "Correction #1: a 'failed' operation moved no money and must not announce completion."
        );
    }

    /**
     * Correction #1's second addition: the first leg genuinely moved
     * gateway money before the second leg failed, so the operation is
     * partial_failure -- and the event MUST fire for it, because real money
     * already came back.
     *
     * Mirrors RefundPartialFailureTest::
     * test_a_leg_that_fails_after_a_successful_one_records_partial_failure(),
     * with the first (successful) leg made auto-capable so auto_refunded is
     * what is greater than zero at the point of failure.
     */
    public function test_a_partial_failure_that_moved_gateway_money_announces_completion(): void
    {
        $this->require_woocommerce();
        $this->register_refund_gateway_double();

        $first = $this->create_paid_order_for_booking($this->booking_id, '30');
        $first->set_payment_method(WooCommerceRefundGatewayDouble::ID);
        $first->save();

        $second = $this->create_paid_order_for_booking($this->booking_id, '70');
        update_post_meta($this->booking_id, '_mhmrentiva_remaining_order_id', $second->get_id());

        add_action(
            'woocommerce_refund_created',
            static function (int $refund_id, array $args) use ($second): void {
                if ((int) $args['order_id'] === $second->get_id()) {
                    throw new \RuntimeException('refused on purpose');
                }
            },
            1,
            2
        );

        $fired = $this->capture_completion(function (): void {
            $result = Service::processFullRefund($this->booking_id, 'partial failure with auto money', $this->admin_id);
            $this->assertSame('0', $result['mhmrentiva_refund']);
        });

        $this->assertSame(
            RefundStatus::PARTIAL_FAILURE,
            RefundStatus::get($this->booking_id),
            'Sanity: the first leg succeeded, so this must be a partial failure, not a plain one.'
        );
        $this->assertCount(
            1,
            $fired,
            "Correction #1: a 'partial_failure' operation that genuinely moved gateway money before the failing "
                . 'leg must still announce completion.'
        );

        $payload = $fired[0];

        $this->assertGreaterThan(0, $payload['auto_refunded'], 'The successful first leg was auto-capable.');
        $this->assertSame(0, $payload['manual_refunded'], 'The second leg never succeeded, so nothing manual moved.');
        $this->assertSame(
            RefundValidator::CHANNEL_WOOCOMMERCE,
            $payload['channel'],
            "Only one channel actually moved money here -- 'mixed' would overstate what happened."
        );
    }
}
