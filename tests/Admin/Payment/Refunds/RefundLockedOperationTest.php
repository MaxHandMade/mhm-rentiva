<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Payment\Refunds;

use MHMRentiva\Admin\Payment\Core\Money;
use MHMRentiva\Admin\Payment\Core\RefundLock;
use MHMRentiva\Admin\Payment\Refunds\Service;
use MHMRentiva\Tests\Support\WooCommerceFixtures;
use ReflectionMethod;
use WP_UnitTestCase;

/**
 * Spec §5.4: the refundable balance is read, then wc_create_refund() checks it
 * again at call time, and between those two moments a second request can read
 * the same balance. The lock closes that window -- but only if every entry
 * point takes it. Measured before this task: Service::process() has two
 * callers (the deposit screen and the booking action) and
 * processFullRefund() is about to gain a third from the cancellation flow, so
 * a lock taken only by the cancellation flow would exclude nothing.
 */
final class RefundLockedOperationTest extends WP_UnitTestCase
{
    use WooCommerceFixtures;

    private int $booking_id;
    private int $admin_id;

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

    public function tearDown(): void
    {
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'mhmrentiva_refund_lock_%'");

        parent::tearDown();
    }

    private function plant_foreign_lock(): void
    {
        global $wpdb;

        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')",
            'mhmrentiva_refund_lock_' . $this->booking_id,
            'someone-else:' . time()
        ));
    }

    public function test_a_refund_is_refused_while_another_request_holds_the_lock(): void
    {
        $order = $this->create_paid_order_for_booking($this->booking_id, '120');

        $this->plant_foreign_lock();

        $result = Service::process($this->booking_id, Money::toMinor('20'), 'locked out', $this->admin_id);

        $this->assertSame('0', $result['mhmrentiva_refund']);
        $this->assertSame(
            '0',
            wc_format_decimal(wc_get_order($order->get_id())->get_total_refunded(), 0),
            'The refusal must happen before any money moves, not after.'
        );
    }

    public function test_a_full_refund_is_refused_by_the_same_lock(): void
    {
        $this->create_paid_order_for_booking($this->booking_id, '120');

        $this->plant_foreign_lock();

        $result = Service::processFullRefund($this->booking_id, 'locked out', $this->admin_id);

        $this->assertSame('0', $result['mhmrentiva_refund']);
    }

    public function test_the_lock_is_released_when_the_operation_finishes(): void
    {
        $this->create_paid_order_for_booking($this->booking_id, '120');

        Service::processFullRefund($this->booking_id, 'released', $this->admin_id);

        $this->assertTrue(
            RefundLock::acquire($this->booking_id),
            'A lock still standing after a completed operation blocks the booking for TTL_SECONDS.'
        );

        RefundLock::release($this->booking_id);
    }

    /**
     * WooCommerce catches the exception thrown from the woocommerce_refund_created
     * action itself (wc-order-functions.php: the refund is created inside a
     * try { ... } catch ( Exception $e ) { return new WP_Error( ... ); } block),
     * so the throw never reaches Service -- runOperation() takes its ordinary
     * is_wp_error( $refund ) branch and returns an array like any other refused
     * refund. This test therefore proves the lock is released on that ordinary
     * *failure return*, not on exception unwinding; the latter is covered
     * separately below, directly against withLock(), where an exception really
     * does cross the boundary.
     */
    public function test_the_lock_is_released_after_a_refused_refund(): void
    {
        $this->create_paid_order_for_booking($this->booking_id, '120');

        add_action(
            'woocommerce_refund_created',
            static function (): void {
                throw new \RuntimeException('refused on purpose');
            },
            1,
            2
        );

        Service::processFullRefund($this->booking_id, 'failing', $this->admin_id);

        $this->assertTrue(
            RefundLock::acquire($this->booking_id),
            'A lock still standing after a refused refund blocks the booking for TTL_SECONDS.'
        );

        RefundLock::release($this->booking_id);
    }

    /**
     * The refusal-path test above cannot exercise withLock()'s finally block on
     * an actual exception -- WooCommerce swallows the one it is given before it
     * ever reaches Service. This test goes straight at withLock() (private,
     * reached via reflection) with a closure that really does throw, so the
     * exception-unwinding guarantee the brief leans on is proven against the
     * method that makes it, not against a WooCommerce catch block that happens
     * to produce the same observable lock state.
     */
    public function test_the_lock_is_released_when_withLock_throws(): void
    {
        $method = new ReflectionMethod(Service::class, 'withLock');
        $method->setAccessible(true);

        $thrown = null;

        try {
            $method->invoke(
                null,
                $this->booking_id,
                static function (): array {
                    throw new \RuntimeException('the operation itself threw');
                }
            );
        } catch (\RuntimeException $e) {
            $thrown = $e;
        }

        $this->assertNotNull(
            $thrown,
            'withLock() must let the operation\'s exception propagate, not swallow it.'
        );

        $this->assertTrue(
            RefundLock::acquire($this->booking_id),
            'withLock() must release the lock in a finally block even when the operation throws.'
        );

        RefundLock::release($this->booking_id);
    }
}
