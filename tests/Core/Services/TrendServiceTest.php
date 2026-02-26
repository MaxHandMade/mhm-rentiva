<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Core\Services;

use MHMRentiva\Core\Services\TrendService;
use MHMRentiva\Core\Services\Metrics\MetricInterface;
use MHMRentiva\Core\Services\Metrics\MetricRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Ensures cache builders and structural orchestration works correctly.
 */
class TrendServiceTest extends TestCase
{


    /**
     * Mocks the handler to ensure that TrendService dispatches array orchestration over raw integer returns.
     */
    public function test_trend_service_dispatch_integration(): void
    {
        // Establish standard array shape that `TrendMath` would yield
        $shapeArray = array(
            'total'     => 10,
            'current'   => 5,
            'previous'  => 0,
            'trend'     => 100,
            'direction' => 'up',
        );

        // The provider fetches all metrics via get(). `MetricRegistry::get()` calls `new $class()`.
        // PHPUnit Dummies aren't strictly instantiable by string class names across isolated autoloaders easily.
        // We'll declare a fake class specifically for this.

        $fakeClassName = get_class(new class() implements MetricInterface {
            public function key(): string
            {
                return 'mocked_service_test';
            }

            public function subjectKey(array $args = array()): string
            {
                return 'fake_subject';
            }

            public function resolve(string $context, array $args, int $currentStart, int $now, int $previousStart): array
            {
                return array(
                    'total'    => 10,
                    'current'  => 5,
                    'previous' => 0, // 0 -> 5 is +100% trend
                );
            }
        });

        // Temporarily register our mock explicitly for the duration of this test logic
        // 'mocked_service_test'
        MetricRegistry::register('mocked_service_test', $fakeClassName);
        $result = TrendService::get_trend('mocked_service_test', 'customer');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('total', $result);
        $this->assertEquals(10, $result['total']);
        $this->assertEquals(100, $result['trend']);
    }
}
