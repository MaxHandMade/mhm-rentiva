<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Core\Services\Metrics;

use MHMRentiva\Core\Services\Metrics\MetricInterface;
use MHMRentiva\Core\Services\Metrics\MetricRegistry;
use WP_UnitTestCase;

/**
 * Isolated unit tests for metric mapping boundaries.
 */
class MetricRegistryTest extends WP_UnitTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        // Reset static map before each test using Reflection to ensure fresh boot sequences
        $reflection = new \ReflectionClass(MetricRegistry::class);
        $property = $reflection->getProperty('map');
        $property->setAccessible(true);
        $property->setValue(null, null);
    }

    /**
     * Unknown metric should gracefully null-island without throwing an exception.
     */
    public function test_unknown_metric_returns_null(): void
    {
        $metric = MetricRegistry::get('non_existent_fake_metric_x_1');

        $this->assertNull($metric);
    }

    /**
     * Test correct instantiation logic for known, standard metrics.
     */
    public function test_known_metrics_return_interface(): void
    {
        $metric = MetricRegistry::get('total_bookings');

        $this->assertInstanceOf(MetricInterface::class, $metric);
        $this->assertEquals('guest', $metric->subjectKey(array()));
    }

    public function test_dynamic_filter_injection(): void
    {
        // Define a fake external metric simulating a third-party pro add-on hooking into core
        $fakeClass = get_class(new class() implements MetricInterface {
            public function key(): string
            {
                return 'vendor_revenue_pro_test';
            }
            public function subjectKey(array $args = array()): string
            {
                return 'fake';
            }
            public function resolve(string $context, array $args, int $start, int $now, int $prev): array
            {
                return array();
            }
        });

        add_filter('mhm_rentiva_registered_metrics', function ($metrics) use ($fakeClass) {
            $metrics['vendor_revenue_pro'] = $fakeClass;
            return $metrics;
        });

        // Resolve map and verify the pro injection succeeded
        $this->assertArrayHasKey('vendor_revenue_pro', MetricRegistry::all());
        $this->assertInstanceOf(MetricInterface::class, MetricRegistry::get('vendor_revenue_pro'));
    }

    /**
     * Ensure dynamic registration enforces duplicate protections (Silent Override Risk).
     */
    public function test_duplicate_registration_throws_exception(): void
    {
        $fakeClass = get_class(new class() implements MetricInterface {
            public function key(): string
            {
                return 'test_duplicate_metric';
            }
            public function subjectKey(array $args = array()): string
            {
                return 'fake';
            }
            public function resolve(string $context, array $args, int $start, int $now, int $prev): array
            {
                return array();
            }
        });

        MetricRegistry::register('test_duplicate_metric', $fakeClass);

        $this->assertArrayHasKey('test_duplicate_metric', MetricRegistry::all());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Metric handler "test_duplicate_metric" is already registered.');

        MetricRegistry::register('test_duplicate_metric', $fakeClass);
    }
}
