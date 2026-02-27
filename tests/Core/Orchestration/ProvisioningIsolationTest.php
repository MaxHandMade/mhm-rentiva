<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Core\Orchestration;


use MHMRentiva\Core\Orchestration\TenantProvisioner;
use MHMRentiva\Core\Orchestration\MeteredUsageTracker;
use MHMRentiva\Core\Tenancy\TenantResolver;
use MHMRentiva\Core\Tenancy\TenantContext;

/**
 * Test A: Provisioning Isolation & Rollback.
 */
class ProvisioningIsolationTest extends \WP_UnitTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->prefix}mhm_rentiva_tenants");
        $wpdb->query("DELETE FROM {$wpdb->prefix}mhm_rentiva_key_registry");
    }

    /**
     * Test that if site creation fails, tenant status is PROVISIONING_FAILED.
     */
    public function test_provisioning_failed_status_on_site_creation_failure()
    {
        global $wpdb;
        $tenant_id = 123456;

        // Force site creation failure via filter
        add_filter('mhm_rentiva_provisioning_site_id', function () {
            return null; // Simulate failure
        });

        $success = TenantProvisioner::provision($tenant_id, 'fail.com', '/');

        $this->assertFalse($success, 'Provisioning should return false on site failure');

        $status = $wpdb->get_var($wpdb->prepare("SELECT status FROM {$wpdb->prefix}mhm_rentiva_tenants WHERE tenant_id = %d", $tenant_id));
        $this->assertEquals('PROVISIONING_FAILED', $status);

        // Verify keys exist (Orphan key check: keys should exist even if provisioning failed because Step 1 committed)
        $key_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}mhm_rentiva_key_registry WHERE tenant_id = %d", $tenant_id));
        $this->assertGreaterThan(0, $key_count, 'Keys should exist for debugging/trace even if site failed');
    }
    public function tear_down()
    {
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->prefix}mhm_rentiva_tenants");
        $wpdb->query("DELETE FROM {$wpdb->prefix}mhm_rentiva_key_registry");
        parent::tear_down();
    }
}
