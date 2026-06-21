<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Core\Orchestration;


use MHMRentiva\Core\Orchestration\TenantProvisioner;
use MHMRentiva\Core\Orchestration\ControlPlaneGuard;
use MHMRentiva\Core\Orchestration\MeteredUsageTracker;
use MHMRentiva\Core\Tenancy\TenantResolver;
use MHMRentiva\Core\Tenancy\TenantContext;
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
     * Quota enforcement now lives in ControlPlaneGuard directly. The financial ledger no
     * longer invokes the gate (single-site simplification — AtomicPayoutService/Ledger gate
     * removal, 2026-06-21). The guard's own contract is unchanged: at the provisioned limit
     * (2) the next operation is blocked.
     */
    public function test_quota_blocks_at_limit()
    {
        // Drive recorded usage up to the provisioned limit of 2.
        MeteredUsageTracker::increment($this->tenant_id, 'ledger_entries', 2);

        $this->expectException(QuotaExceededException::class);
        ControlPlaneGuard::assert_operational_and_quota($this->tenant_id, 'ledger_entries');
    }
}
