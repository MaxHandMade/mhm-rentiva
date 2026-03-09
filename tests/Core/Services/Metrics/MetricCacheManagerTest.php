<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Core\Services\Metrics;

use MHMRentiva\Core\Services\Metrics\MetricCacheManager;
use WP_UnitTestCase;

class MetricCacheManagerTest extends WP_UnitTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        // Reset static flush ledger before each test using Reflection to ensure pristine request environments
        $reflection = new \ReflectionClass(MetricCacheManager::class);
        $property = $reflection->getProperty('flushed');
        $property->setAccessible(true);
        $property->setValue(null, array());
    }

    public function test_cache_key_generation(): void
    {
        $key = MetricCacheManager::build_key('customer', 'total_bookings', '15');
        $this->assertSame('mhm_metric_customer_total_bookings_15', $key);
    }

    public function test_get_set_operations(): void
    {
        $this->assertFalse(MetricCacheManager::get('customer', 'revenue_7d', '99'));

        $data = array('total' => 100);
        $this->assertTrue(MetricCacheManager::set('customer', 'revenue_7d', '99', $data));

        $this->assertSame($data, MetricCacheManager::get('customer', 'revenue_7d', '99'));
    }

    public function test_flush_specific_metric(): void
    {
        MetricCacheManager::set('customer', 'saved_favorites', '5', array('count' => 10));

        $this->assertNotEmpty(MetricCacheManager::get('customer', 'saved_favorites', '5'));

        MetricCacheManager::flush_subject_metric('customer', 'saved_favorites', '5');

        // Assert deletion processed successfully
        $this->assertFalse(MetricCacheManager::get('customer', 'saved_favorites', '5'));
    }

    public function test_flush_debounce_prevents_multiple_deletions(): void
    {
        MetricCacheManager::set('vendor', 'revenue_7d', '10', array('val' => 500));

        // Flush once
        MetricCacheManager::flush_subject_metric('vendor', 'revenue_7d', '10');
        $this->assertFalse(MetricCacheManager::get('vendor', 'revenue_7d', '10'));

        // Manually inject cache bypassing the `set()` flush block wrapper via direct core transient helper.
        set_transient('mhm_metric_vendor_revenue_7d_10', array('val' => 2), 300);

        // Demand flush again (simulating sequential hook firings in same request lifecycle)
        MetricCacheManager::flush_subject_metric('vendor', 'revenue_7d', '10');

        // Since ledger tracked the first flush, the second delete request is aborted. Transient must survive.
        $this->assertNotEmpty(get_transient('mhm_metric_vendor_revenue_7d_10'));
    }

    public function test_wildcard_subject_flush(): void
    {
        // Setup mapping array
        MetricCacheManager::set('vendor', 'revenue_7d', '55', array('total' => 100));
        MetricCacheManager::set('vendor', 'active_listings', '55', array('total' => 3));
        MetricCacheManager::set('customer', 'total_bookings', '55', array('total' => 1));

        // Inject secondary metric boundaries to ensure isolated blast zones
        MetricCacheManager::set('vendor', 'revenue_7d', '56', array('total' => 200));

        // Execute dynamic suffix target deletion
        MetricCacheManager::flush_subject_all_metrics('55');

        $this->assertFalse(MetricCacheManager::get('vendor', 'revenue_7d', '55'));
        $this->assertFalse(MetricCacheManager::get('vendor', 'active_listings', '55'));
        $this->assertFalse(MetricCacheManager::get('customer', 'total_bookings', '55'));

        // User 56 remained completely isolated and untouched
        $this->assertNotEmpty(MetricCacheManager::get('vendor', 'revenue_7d', '56'));
    }

    public function test_flush_vehicle_performance(): void
    {
        MetricCacheManager::set('vehicle', 'perf', '101', array('revenue' => 500));
        MetricCacheManager::set('vehicle', 'perf', '102', array('revenue' => 1000));

        $this->assertNotEmpty(MetricCacheManager::get('vehicle', 'perf', '101'));
        $this->assertNotEmpty(MetricCacheManager::get('vehicle', 'perf', '102'));

        MetricCacheManager::flush_subject_metric('vehicle', 'perf', '101');

        $this->assertFalse(MetricCacheManager::get('vehicle', 'perf', '101'));
        $this->assertNotEmpty(MetricCacheManager::get('vehicle', 'perf', '102'));
    }
}
