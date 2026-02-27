<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Core\Financial;

use MHMRentiva\Core\Tenancy\TenantContext;
use MHMRentiva\Core\Tenancy\TenantResolver;
use MHMRentiva\Core\Financial\Ledger;
use MHMRentiva\Core\Financial\LedgerEntry;
use WP_UnitTestCase;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Tenant Isolation Tests
 *
 * Verifies that no ledger data can cross tenant boundaries.
 * Cross-tenant reads and mutations must be IMPOSSIBLE.
 *
 * @group multi-tenant
 * @group isolation
 * @since 4.23.0
 *
 * Higher-level integration test for Tenant Isolation.
 *
 * @method void set_up()
 * @method void tear_down()
 */
class TenantIsolationTest extends WP_UnitTestCase
{
    private const TENANT_A_ID = 1;
    private const TENANT_B_ID = 2;

    public function set_up()
    {
        parent::set_up();
        TenantResolver::reset();
    }

    public function tear_down()
    {
        TenantResolver::reset();
        parent::tear_down();
    }

    /**
     * @test
     * Tenant A's balance must be completely isolated from Tenant B.
     */
    public function test_ledger_balance_is_isolated_per_tenant(): void
    {
        $vendor_id = 999; // Shared vendor ID for the test

        // Write a ledger entry AS Tenant A
        TenantResolver::set_context(new TenantContext(self::TENANT_A_ID, 'tenant-a', 'en_US'));
        Ledger::add_entry(new LedgerEntry(
            transaction_uuid: 'test-tenant-a-credit-001',
            vendor_id: $vendor_id,
            booking_id: null,
            order_id: null,
            type: 'commission_credit',
            amount: 500.00,
            gross_amount: 500.00,
            commission_amount: 0.0,
            commission_rate: 0.0,
            currency: 'USD',
            context: 'commission_credit',
            status: 'cleared'
        ));

        // Query balance AS Tenant A — should see the credit.
        $balance_a = Ledger::get_balance($vendor_id, self::TENANT_A_ID);
        $this->assertEquals(500.00, $balance_a, 'Tenant A should see its own balance.');

        // Query balance AS Tenant B — should see NOTHING.
        $balance_b = Ledger::get_balance($vendor_id, self::TENANT_B_ID);
        $this->assertEquals(0.00, $balance_b, 'Tenant B MUST NOT see Tenant A ledger entries. Isolation failure!');
    }

    /**
     * @test
     * Tenant B cannot query entries that were written by Tenant A.
     */
    public function test_ledger_entries_are_isolated_per_tenant(): void
    {
        $vendor_id = 998;

        // Write AS Tenant A
        TenantResolver::set_context(new TenantContext(self::TENANT_A_ID, 'tenant-a', 'en_US'));
        Ledger::add_entry(new LedgerEntry(
            transaction_uuid: 'test-tenant-a-entry-001',
            vendor_id: $vendor_id,
            booking_id: null,
            order_id: null,
            type: 'commission_credit',
            amount: 200.00,
            gross_amount: 200.00,
            commission_amount: 0.0,
            commission_rate: 0.0,
            currency: 'USD',
            context: 'commission_credit',
            status: 'cleared'
        ));

        // Query AS Tenant B
        $entries_b = Ledger::get_entries($vendor_id, [], 20, 0, self::TENANT_B_ID);
        $this->assertEmpty($entries_b, 'Tenant B MUST NOT retrieve Tenant A entries. DATA LEAK DETECTED!');

        // But Tenant A should see it
        $entries_a = Ledger::get_entries($vendor_id, [], 20, 0, self::TENANT_A_ID);
        $this->assertNotEmpty($entries_a, 'Tenant A should see its own entries.');
    }
}
