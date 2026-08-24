<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integrations\WooCommerce;

use MHMRentiva\Admin\Payment\WooCommerce\WooCommerceBridge;
use MHMRentiva\Tests\Support\WooCommerceFixtures;
use WP_UnitTestCase;

/**
 * H-02: completing the FIRST (deposit) order used to wipe the unpaid remainder.
 *
 * The `completed` branch of handle_order_status_change() looked only at the
 * booking's payment_type and never at which order had just completed, so a
 * deposit booking with 70 still owed had that debt zeroed the moment the
 * 30-lira deposit order reached `completed`. The `processing` branch has
 * always carried the ownership check (_mhmrentiva_is_remaining_payment);
 * this file pins the same rule on `completed`.
 *
 * sync_completed_to_wc() drives the original order to completed by itself,
 * so the plugin could trigger this with nobody touching the order.
 */
final class DepositRemainingAmountTest extends WP_UnitTestCase
{
    use WooCommerceFixtures;

    /** @var int */
    private $booking_id;

    public function setUp(): void
    {
        parent::setUp();

        $this->require_woocommerce();

        // The plugin's own hooks are NOT registered in the PHPUnit environment
        // (measured 2026-08-18). Without this the status hook never fires and
        // the test goes green while proving nothing.
        WooCommerceBridge::register();

        $this->booking_id = (int) self::factory()->post->create(array(
            'post_type'   => 'mhmrentiva_booking',
            'post_status' => 'publish',
        ));

        // Booking meta is ordinary post meta -- get_post_meta is correct here.
        // Amounts are MAJOR units in these keys (measured: BookingMeta writes
        // $daily_price * $days raw).
        update_post_meta($this->booking_id, '_mhmrentiva_status', 'pending');
        update_post_meta($this->booking_id, '_mhmrentiva_payment_type', 'deposit');
        update_post_meta($this->booking_id, '_mhmrentiva_total_price', 100.0);
        update_post_meta($this->booking_id, '_mhmrentiva_deposit_amount', 30.0);
        update_post_meta($this->booking_id, '_mhmrentiva_remaining_amount', 70.0);

        // rental_has_ended() reads these; without them the completed branch
        // takes an unintended path while deciding the booking status.
        update_post_meta($this->booking_id, '_mhmrentiva_start_ts', time() - DAY_IN_SECONDS);
        update_post_meta($this->booking_id, '_mhmrentiva_end_ts', time() + DAY_IN_SECONDS);
    }

    /**
     * HPOS is enabled in this suite: order and item meta go through CRUD only.
     */
    private function make_order(bool $is_remaining_payment, string $total): \WC_Order
    {
        $product = $this->ensure_booking_product($total);

        $order = wc_create_order(array( 'status' => 'pending' ));

        $item = new \WC_Order_Item_Product();
        $item->set_product($product);
        $item->set_quantity(1);
        $item->set_subtotal((float) $total);
        $item->set_total((float) $total);
        $item->add_meta_data('_mhmrentiva_booking_id', $this->booking_id, true);

        $order->add_item($item);

        if ($is_remaining_payment) {
            $order->update_meta_data('_mhmrentiva_is_remaining_payment', '1');
        }

        $order->calculate_totals();
        $order->save();

        return $order;
    }

    private function remaining(): float
    {
        return (float) get_post_meta($this->booking_id, '_mhmrentiva_remaining_amount', true);
    }

    public function test_completing_the_deposit_order_keeps_the_unpaid_remainder(): void
    {
        $order = $this->make_order(false, '30');

        $order->update_status('completed');

        $this->assertSame(
            70.0,
            $this->remaining(),
            'Completing the deposit order wiped a 70-lira debt the customer never paid.'
        );
    }

    public function test_completing_the_remaining_payment_order_clears_the_remainder(): void
    {
        $order = $this->make_order(true, '70');

        $order->update_status('completed');

        $this->assertSame(0.0, $this->remaining(), 'The order that settles the debt must clear it.');
    }

    public function test_processing_the_remaining_payment_order_clears_the_remainder(): void
    {
        $order = $this->make_order(true, '70');

        $order->update_status('processing');

        $this->assertSame(0.0, $this->remaining(), 'The processing branch already had this rule; it must keep it.');
    }

    public function test_full_payment_booking_is_unaffected(): void
    {
        update_post_meta($this->booking_id, '_mhmrentiva_payment_type', 'full');
        update_post_meta($this->booking_id, '_mhmrentiva_remaining_amount', 0.0);

        $order = $this->make_order(false, '100');

        $order->update_status('completed');

        $this->assertSame(0.0, $this->remaining(), 'Full-payment behaviour must not change.');
    }
}
