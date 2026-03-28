<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integrations\WooCommerce;

use MHMRentiva\Integrations\WooCommerce\CommissionBridge;
use MHMRentiva\Core\Database\Migrations\LedgerMigration;
use MHMRentiva\Core\Financial\Ledger;
use WP_UnitTestCase;

class CommissionBridgeTest extends WP_UnitTestCase
{
    private int $vendor_id;
    private int $booking_id;

    public function setUp(): void
    {
        parent::setUp();
        LedgerMigration::create_table();

        global $wpdb;
        $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}mhm_rentiva_ledger"); // Clean state

        $this->vendor_id = self::factory()->user->create(array('role' => 'mhm_rentiva_vendor'));
        $this->booking_id = self::factory()->post->create(array(
            'post_type' => 'vehicle_booking',
            'post_author' => $this->vendor_id
        ));
    }

    public function test_payment_complete_firing_twice_yields_single_entry(): void
    {
        if (! class_exists('WC_Order')) {
            $this->markTestSkipped('WooCommerce not loaded.');
        }

        $order = \wc_create_order();
        $order->set_total('100.00');
        $order->update_meta_data('mhm_booking_id', $this->booking_id);
        $order->save();

        // Fire 1
        CommissionBridge::on_payment_complete($order->get_id());

        // Fire 2
        CommissionBridge::on_payment_complete($order->get_id());

        $entries = Ledger::get_entries($this->vendor_id);

        $this->assertCount(1, $entries, 'Double hook execution illegally violated DB uniqueness resulting in replicated balance transactions.');
    }

    public function test_refund_twice_yields_single_entry(): void
    {
        if (! class_exists('WC_Order')) {
            $this->markTestSkipped('WooCommerce not loaded.');
        }

        $order = \wc_create_order();
        $order->set_total('100.00');
        $order->update_meta_data('mhm_booking_id', $this->booking_id);
        $order->save();

        // Mocking a refund object since wc_create_refund is complex without valid line items
        $refund = \wc_create_order(array('type' => 'shop_order_refund'));
        $refund->set_total('50.0'); // Refunds are generated manually as order extensions wrapping native DB items
        $refund->save();

        // Fire 1
        CommissionBridge::on_order_refunded($order->get_id(), $refund->get_id());

        // Fire 2
        CommissionBridge::on_order_refunded($order->get_id(), $refund->get_id());

        $entries = Ledger::get_entries($this->vendor_id);

        $this->assertCount(1, $entries, 'Duplicate refund evaluations caused negative balance drifts over idempotent entries.');
        $this->assertEquals('reversed', $entries[0]->status);
        $this->assertEquals(-42.5, (float) $entries[0]->amount); // 50 * 0.15 = 7.5. 50 - 7.5 = 42.5. Reversed = -42.5
    }

    public function test_unrelated_order_ignored_safely(): void
    {
        if (! class_exists('WC_Order')) {
            $this->markTestSkipped('WooCommerce not loaded.');
        }

        // Vanilla order missing `mhm_booking_id`
        $order = \wc_create_order();
        $order->set_total('100.00');
        $order->save();

        CommissionBridge::on_payment_complete($order->get_id());

        $entries = Ledger::get_entries($this->vendor_id);
        $this->assertEmpty($entries, 'Independent vanilla e-commerce orders mistakenly flagged into marketplace ledgers.');
    }
}
