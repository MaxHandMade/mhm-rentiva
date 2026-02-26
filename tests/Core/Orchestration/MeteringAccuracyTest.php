<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Core\Orchestration;

if (!defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Core\Orchestration\MeteredUsageTracker;
use MHMRentiva\Core\Tenancy\TenantResolver;
use MHMRentiva\Core\Tenancy\TenantContext;

/**
 * Test B: Metering Accuracy & Concurrency.
 */
class MeteringAccuracyTest extends \WP_UnitTestCase
{
    private $tenant_id = 100;

    public function setUp(): void
    {
        parent::setUp();
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->prefix}mhm_rentiva_usage_metrics WHERE tenant_id = {$this->tenant_id}");

        $context = new TenantContext($this->tenant_id, 'test', 'tr_TR', 'pro');
        TenantResolver::set_context($context);
    }

    /**
     * Test that multiple increments result in correct total.
     */
    public function test_metering_increments_accurately()
    {
        MeteredUsageTracker::increment($this->tenant_id, 'ledger_entries');
        MeteredUsageTracker::increment($this->tenant_id, 'ledger_entries');
        MeteredUsageTracker::increment($this->tenant_id, 'ledger_entries');

        $usage = MeteredUsageTracker::get_usage($this->tenant_id, 'ledger_entries');
        $this->assertEquals(3, $usage);
    }

    /**
     * Test that UNIQUE constraint prevents duplicate cycle records.
     */
    public function test_unique_constraint_prevents_duplicates()
    {
        global $wpdb;

        $cycle_start = MeteredUsageTracker::get_current_cycle_start();
        $cycle_end = MeteredUsageTracker::get_current_cycle_end();

        // First insert via tracker
        MeteredUsageTracker::increment($this->tenant_id, 'payouts');

        // Manual attempt to insert same cycle/type/tenant
        $table = $wpdb->prefix . 'mhm_rentiva_usage_metrics';
        $wpdb->suppress_errors();
        $wpdb->insert($table, [
            'tenant_id' => $this->tenant_id,
            'metric_type' => 'payouts',
            'metric_value' => 1,
            'cycle_start' => $cycle_start,
            'cycle_end' => $cycle_end
        ]);

        $this->assertNotEmpty($wpdb->last_error, 'Should have a database error for duplicate key');

        $count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE tenant_id = %d AND metric_type = 'payouts'", $this->tenant_id));
        $this->assertEquals(1, $count, 'Only one row should exist for this cycle');
    }
}
