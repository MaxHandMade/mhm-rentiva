<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Core\Orchestration;

if (!defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Core\Orchestration\TenantProvisioner;
use MHMRentiva\Core\Orchestration\ControlPlaneGuard;
use MHMRentiva\Core\Tenancy\TenantResolver;
use MHMRentiva\Core\Tenancy\TenantContext;
use MHMRentiva\Core\Financial\Ledger;
use MHMRentiva\Core\Financial\LedgerEntry;
use MHMRentiva\Core\Orchestration\Exceptions\QuotaExceededException;

/**
 * Test C: Quota Race & Enforcement.
 */
class QuotaRaceTest extends \WP_UnitTestCase
{
    private $tenant_id = 200;

    public function setUp(): void
    {
        parent::setUp();
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->prefix}mhm_rentiva_tenants WHERE tenant_id = {$this->tenant_id}");
        $wpdb->query("DELETE FROM {$wpdb->prefix}mhm_rentiva_usage_metrics WHERE tenant_id = {$this->tenant_id}");

        TenantProvisioner::provision($this->tenant_id, 'test.com', '/', ['ledger_entries' => 2]);

        $context = new TenantContext($this->tenant_id, 'test', 'tr_TR', 'pro');
        TenantResolver::set_context($context);
    }

    /**
     * Test that quota of 2 allows 2 entries and blocks the 3rd.
     */
    public function test_quota_blocks_at_limit()
    {
        $entry1 = new LedgerEntry('tx_1', 1, null, null, 'test', 10.0, null, null, null, 'TRY', 'test', 'cleared');
        $entry2 = new LedgerEntry('tx_2', 1, null, null, 'test', 10.0, null, null, null, 'TRY', 'test', 'cleared');
        $entry3 = new LedgerEntry('tx_3', 1, null, null, 'test', 10.0, null, null, null, 'TRY', 'test', 'cleared');

        Ledger::add_entry($entry1);
        Ledger::add_entry($entry2);

        $this->expectException(QuotaExceededException::class);
        Ledger::add_entry($entry3);
    }
}
