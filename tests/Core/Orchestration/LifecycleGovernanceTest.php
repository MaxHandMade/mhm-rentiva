<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Core\Orchestration;


use MHMRentiva\Core\Orchestration\TenantProvisioner;
use MHMRentiva\Core\Tenancy\TenantResolver;
use MHMRentiva\Core\Tenancy\TenantContext;
use MHMRentiva\Core\Financial\Workers\MaturedPayoutJob;
use MHMRentiva\Core\Financial\Ledger;

/**
 * Test D: Lifecycle Governance & Suspension.
 */
class LifecycleGovernanceTest extends \WP_UnitTestCase
{
    private $tenant_id = 300;

    public function setUp(): void
    {
        parent::setUp();
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->prefix}mhm_rentiva_tenants WHERE tenant_id = {$this->tenant_id}");

        TenantProvisioner::provision($this->tenant_id, 'test.com', '/');

        $context = new TenantContext($this->tenant_id, 'test', 'tr_TR', 'pro');
        TenantResolver::set_context($context);
    }

    /**
     * Suspended-tenant lifecycle enforcement is performed by ControlPlaneGuard directly.
     * The financial ledger no longer invokes the gate (single-site simplification —
     * AtomicPayoutService/Ledger gate removal, 2026-06-21). The guard's contract is unchanged.
     */
    public function test_suspended_tenant_blocks_ledger()
    {
        global $wpdb;
        $wpdb->update($wpdb->prefix . 'mhm_rentiva_tenants', ['status' => 'suspended'], ['tenant_id' => $this->tenant_id]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Tenant is currently suspended');

        \MHMRentiva\Core\Orchestration\ControlPlaneGuard::assert_operational_and_quota($this->tenant_id, 'ledger_entries');
    }

}
