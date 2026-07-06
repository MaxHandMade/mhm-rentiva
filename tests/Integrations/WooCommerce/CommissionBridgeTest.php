<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integrations\WooCommerce;

use MHMRentiva\Integrations\WooCommerce\CommissionBridge;
use MHMRentiva\Core\Database\Migrations\LedgerMigration;
use MHMRentiva\Core\Financial\Automation\CommissionClearingJob;
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
        $order->update_meta_data('_mhm_booking_id', $this->booking_id);
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
        $order->update_meta_data('_mhm_booking_id', $this->booking_id);
        $order->save();

        $refund = \wc_create_refund(array(
            'order_id' => $order->get_id(),
            'amount'   => 50.0,
        ));

        // Fire 1
        CommissionBridge::on_order_refunded($order->get_id(), $refund->get_id());

        // Fire 2
        CommissionBridge::on_order_refunded($order->get_id(), $refund->get_id());

        $entries = Ledger::get_entries($this->vendor_id);

        $this->assertCount(1, $entries, 'Duplicate refund evaluations caused negative balance drifts over idempotent entries.');
        $this->assertEquals('cleared', $entries[0]->status);
        $this->assertEquals(-42.5, (float) $entries[0]->amount); // 50 * 0.15 = 7.5. 50 - 7.5 = 42.5. Cleared = -42.5
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

    public function test_refund_before_clearing_does_not_touch_the_pending_credit(): void
    {
        if (! class_exists('WC_Order')) {
            $this->markTestSkipped('WooCommerce not loaded.');
        }

        $order = \wc_create_order();
        $order->set_total('100.00');
        $order->update_meta_data('_mhm_booking_id', $this->booking_id);
        $order->save();

        CommissionBridge::on_payment_complete($order->get_id());

        $refund = \wc_create_refund(array(
            'order_id' => $order->get_id(),
            'amount'   => 50.0,
        ));

        CommissionBridge::on_order_refunded($order->get_id(), $refund->get_id());

        global $wpdb;
        $credit_uuid = 'pay_cmp_' . $order->get_id() . '_' . $this->booking_id;
        $status      = $wpdb->get_var($wpdb->prepare(
            "SELECT status FROM {$wpdb->prefix}mhm_rentiva_ledger WHERE transaction_uuid = %s",
            $credit_uuid
        ));

        $this->assertSame(
            'pending',
            $status,
            'The original credit must be left untouched (still pending, for its full original amount) so a PARTIAL refund does not destroy the commission owed on the retained portion.'
        );
    }

    public function test_refund_after_clearing_debits_the_available_balance(): void
    {
        if (! class_exists('WC_Order')) {
            $this->markTestSkipped('WooCommerce not loaded.');
        }

        $order = \wc_create_order();
        $order->set_total('100.00');
        $order->update_meta_data('_mhm_booking_id', $this->booking_id);
        $order->save();

        CommissionBridge::on_payment_complete($order->get_id());

        // Simulate the 7-day clearing job having already run on this credit.
        global $wpdb;
        $credit_uuid = 'pay_cmp_' . $order->get_id() . '_' . $this->booking_id;
        $wpdb->update(
            $wpdb->prefix . 'mhm_rentiva_ledger',
            array( 'status' => 'cleared' ),
            array( 'transaction_uuid' => $credit_uuid ),
            array( '%s' ),
            array( '%s' )
        );

        $balance_before = Ledger::get_balance($this->vendor_id);

        $refund = \wc_create_refund(array(
            'order_id' => $order->get_id(),
            'amount'   => 50.0,
        ));

        CommissionBridge::on_order_refunded($order->get_id(), $refund->get_id());

        $status_after = $wpdb->get_var($wpdb->prepare(
            "SELECT status FROM {$wpdb->prefix}mhm_rentiva_ledger WHERE transaction_uuid = %s",
            $credit_uuid
        ));
        $this->assertSame('cleared', $status_after, 'A late refund must not touch an already-cleared credit.');

        $balance_after = Ledger::get_balance($this->vendor_id);
        $this->assertEquals($balance_before - 42.5, $balance_after, 'A late refund must actually debit the cleared balance.');
    }

    public function test_partial_refund_before_clearing_nets_to_retained_amount_once_original_clears(): void
    {
        if (! class_exists('WC_Order')) {
            $this->markTestSkipped('WooCommerce not loaded.');
        }

        // €200 order, 15% commission => credit is pending for €170 net.
        $order = \wc_create_order();
        $order->set_total('200.00');
        $order->update_meta_data('_mhm_booking_id', $this->booking_id);
        $order->save();

        CommissionBridge::on_payment_complete($order->get_id());

        $balance_before_refund = Ledger::get_balance($this->vendor_id);

        // Customer keeps €150 of the booking; only €50 is refunded.
        $refund = \wc_create_refund(array(
            'order_id' => $order->get_id(),
            'amount'   => 50.0,
        ));

        CommissionBridge::on_order_refunded($order->get_id(), $refund->get_id());

        // The debit (-€42.50) lands immediately; the original €170 credit is still
        // pending (contributes nothing to balance yet), so the balance temporarily
        // dips by exactly the debit — this is expected, not a bug.
        $balance_immediately_after_refund = Ledger::get_balance($this->vendor_id);
        $this->assertEquals(
            $balance_before_refund - 42.5,
            $balance_immediately_after_refund,
            'A refund must debit the balance immediately, even if the original credit has not cleared yet.'
        );

        global $wpdb;
        $credit_uuid = 'pay_cmp_' . $order->get_id() . '_' . $this->booking_id;
        $wpdb->update(
            $wpdb->prefix . 'mhm_rentiva_ledger',
            array( 'created_at' => gmdate('Y-m-d H:i:s', time() - (8 * DAY_IN_SECONDS)) ),
            array( 'transaction_uuid' => $credit_uuid ),
            array( '%s' ),
            array( '%s' )
        );
        CommissionClearingJob::run();

        // Once the original (unmodified, full €200-order-based) credit clears, the
        // vendor ends up with exactly the commission owed on the €150 they actually
        // retained: €170 (full credit) - €42.50 (refund debit) = €127.50.
        $balance_final = Ledger::get_balance($this->vendor_id);
        $this->assertEquals(
            127.5,
            $balance_final,
            'Once the original credit clears, the vendor must receive commission on the retained amount, not zero.'
        );
    }
}
