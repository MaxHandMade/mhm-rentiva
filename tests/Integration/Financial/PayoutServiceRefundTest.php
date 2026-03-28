<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Financial;

use MHMRentiva\Core\Financial\PayoutService;
use MHMRentiva\Core\Tenancy\TenantContext;
use MHMRentiva\Core\Tenancy\TenantResolver;

class PayoutServiceRefundTest extends \WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Ensure tenant_id=1 (the global test tenant) is ACTIVE in the Control Plane registry.
        // Some Orchestration tests wipe the tenants table or leave records in non-ACTIVE states.
        // We upsert here so Ledger::add_entry() can pass the ControlPlaneGuard.
        global $wpdb;
        $table = $wpdb->prefix . 'mhm_rentiva_tenants';
        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE tenant_id = %d", 1));
        if ($exists) {
            $wpdb->update($table, array('status' => 'ACTIVE'), array('tenant_id' => 1));
        } else {
            $wpdb->insert($table, array(
                'tenant_id'                  => 1,
                'status'                     => 'ACTIVE',
                'subscription_plan'          => 'pro',
                'quota_payouts_limit'        => 1000,
                'quota_ledger_entries_limit' => 10000,
                'quota_risk_events_limit'    => 500,
                'created_at'                 => current_time('mysql'),
            ));
        }

        // Pin the TenantResolver to tenant_id=1 so the resolved context is deterministic.
        TenantResolver::set_context(new TenantContext(1, 'tenant_1', get_locale(), 'pro'));
    }

    protected function tearDown(): void
    {
        TenantResolver::reset();
        parent::tearDown();
    }

    public function test_returns_error_for_zero_amount(): void
    {
        $result = PayoutService::create_refund_entry(1, 0, 0.0);
        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('invalid_amount', $result->get_error_code());
    }

    public function test_returns_error_for_negative_amount(): void
    {
        $result = PayoutService::create_refund_entry(1, 0, -50.0);
        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('invalid_amount', $result->get_error_code());
    }

    public function test_returns_true_for_valid_refund(): void
    {
        $result = PayoutService::create_refund_entry(1, 0, 150.0);
        $this->assertTrue($result);
    }
}
