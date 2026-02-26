<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Core\Orchestration;

if (!defined('ABSPATH')) {
    exit;
}

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
     * Test that suspended tenant blocks ledger operations.
     */
    public function test_suspended_tenant_blocks_ledger()
    {
        global $wpdb;
        $wpdb->update($wpdb->prefix . 'mhm_rentiva_tenants', ['status' => 'suspended'], ['tenant_id' => $this->tenant_id]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Tenant is currently suspended');

        $entry = new \MHMRentiva\Core\Financial\LedgerEntry('tx_4', 1, null, null, 'test', 10.0, null, null, null, 'TRY', 'test', 'cleared');
        Ledger::add_entry($entry);
    }

    /**
     * Test that worker job skips suspended tenants.
     */
    public function test_worker_job_skips_suspended_tenant()
    {
        global $wpdb;

        // 1. Setup a matured payout
        $payout_id = wp_insert_post([
            'post_title' => 'Matured Payout Test',
            'post_type' => 'mhm_payout',
            'post_status' => 'pending',
            'post_author' => 1
        ]);
        update_post_meta($payout_id, '_mhm_workflow_state', \MHMRentiva\Core\Financial\ApprovalStateMachine::STATE_TIME_LOCKED);
        update_post_meta($payout_id, '_mhm_release_after', gmdate('Y-m-d H:i:s', time() - 3600)); // 1 hour ago
        update_post_meta($payout_id, '_mhm_lock_status', 'LOCKED');

        // 2. Suspend tenant
        $wpdb->update($wpdb->prefix . 'mhm_rentiva_tenants', ['status' => 'suspended'], ['tenant_id' => $this->tenant_id]);

        // 3. Set context and filter to ensure Resolver works inside the loop
        $tid = $this->tenant_id;
        add_filter('mhm_rentiva_filter_tenant_id', function () use ($tid) {
            return $tid;
        });
        TenantResolver::set_context(new TenantContext($this->tenant_id, 'test', 'tr_TR', 'pro'));

        // 4. Run Job
        \MHMRentiva\Core\Financial\Automation\MaturedPayoutJob::run();

        // 5. Verify it's STILL in TIME_LOCKED state (skipped)
        $state = get_post_meta($payout_id, '_mhm_workflow_state', true);
        $this->assertEquals(\MHMRentiva\Core\Financial\ApprovalStateMachine::STATE_TIME_LOCKED, $state);
    }
}
