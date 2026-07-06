<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Core\Dashboard;

use MHMRentiva\Core\Dashboard\DashboardDataProvider;
use MHMRentiva\Core\Database\Migrations\LedgerMigration;
use MHMRentiva\Core\Financial\Ledger;
use MHMRentiva\Core\Financial\LedgerEntry;
use WP_UnitTestCase;

/**
 * Regression: the vendor dashboard's "Financial Summary" KPI cards
 * (available_balance, pending_balance, total_paid_out) must reflect the
 * vendor's real ledger totals. Previously these always resolved to 0
 * because DashboardDataProvider::trend_args_for_metric() passed a
 * 'user_id' arg key, but the underlying metric classes only recognize
 * 'vendor_id' — and because none of the three were registered in
 * metric_resolvers(), resolve_metric() never even attempted to compute
 * them (trend => false in DashboardConfig short-circuits straight to a
 * hardcoded total => 0).
 */
class DashboardDataProviderVendorFinancialsTest extends WP_UnitTestCase
{
    private int $vendor_id;

    public function setUp(): void
    {
        parent::setUp();
        LedgerMigration::create_table();

        global $wpdb;
        $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}mhm_rentiva_ledger"); // phpcs:ignore WordPress.DB

        $this->vendor_id = self::factory()->user->create(array('role' => 'mhm_rentiva_vendor'));
    }

    public function test_available_balance_kpi_reflects_the_real_cleared_balance(): void
    {
        Ledger::add_entry(new LedgerEntry(
            'test_credit_1',
            $this->vendor_id,
            null,
            null,
            'commission_credit',
            150.0,
            176.0,
            26.0,
            15.0,
            'EUR',
            'vendor',
            'cleared'
        ));

        $data = DashboardDataProvider::build('vendor', $this->vendor_id, '');

        $this->assertSame(150.0, $data['kpi_data']['available_balance']['total']);
    }

    public function test_pending_balance_kpi_reflects_the_real_pending_balance(): void
    {
        Ledger::add_entry(new LedgerEntry(
            'test_credit_2',
            $this->vendor_id,
            null,
            null,
            'commission_credit',
            85.0,
            100.0,
            15.0,
            15.0,
            'EUR',
            'vendor',
            'pending'
        ));

        $data = DashboardDataProvider::build('vendor', $this->vendor_id, '');

        $this->assertSame(85.0, $data['kpi_data']['pending_balance']['total']);
    }

    public function test_total_paid_out_kpi_reflects_real_cleared_payout_debits(): void
    {
        Ledger::add_entry(new LedgerEntry(
            'test_payout_1',
            $this->vendor_id,
            null,
            null,
            'payout_debit',
            -200.0,
            null,
            null,
            null,
            'EUR',
            'payout',
            'cleared'
        ));

        $data = DashboardDataProvider::build('vendor', $this->vendor_id, '');

        $this->assertSame(200.0, $data['kpi_data']['total_paid_out']['total']);
    }

    public function test_revenue_7d_kpi_reflects_real_earnings_not_zero(): void
    {
        // revenue_7d is the one vendor KPI still routed through TrendService
        // (trend => true in DashboardConfig). It must receive a 'vendor_id' arg,
        // not 'user_id', or Revenue7dMetric::resolve() short-circuits to zero.
        Ledger::add_entry(new LedgerEntry(
            'test_revenue_1',
            $this->vendor_id,
            null,
            null,
            'commission_credit',
            150.0,
            176.0,
            26.0,
            15.0,
            'EUR',
            'vendor',
            'cleared'
        ));

        $data = DashboardDataProvider::build('vendor', $this->vendor_id, '');

        $this->assertSame(150, $data['kpi_data']['revenue_7d']['total']);
    }
}
