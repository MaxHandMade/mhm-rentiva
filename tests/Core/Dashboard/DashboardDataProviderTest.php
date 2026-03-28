<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Core\Dashboard;

use MHMRentiva\Core\Dashboard\DashboardDataProvider;
use MHMRentiva\Core\Services\Metrics\MetricInterface;
use MHMRentiva\Core\Services\Metrics\MetricRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Ensures DashboardDataProvider returns array structures perfectly matched against TrendService yields.
 */
class DashboardDataProviderTest extends TestCase
{
    /**
     * Verify data structure returned by the provider pipeline
     */
    public function test_data_provider_returns_deep_metric_data_structure(): void
    {
        // Set up a Fake metric
        $fakeClassName = get_class(new class() implements MetricInterface {
            public function key(): string
            {
                return 'fake_provider_metric';
            }

            public function subjectKey(array $args = array()): string
            {
                return 'fake_subject';
            }

            public function resolve(string $context, array $args, int $currentStart, int $now, int $previousStart): array
            {
                return array(
                    'total'    => 50,
                    'current'  => 10,
                    'previous' => 5,
                );
            }
        });

        MetricRegistry::register('fake_provider_metric', $fakeClassName);

        // Typically, the provider fetches *all* metrics via DashboardConfig.
        // We'll mock the configuration to explicitly yield our fake metric for processing.

        // We can hook to `mhm_rentiva_dashboard_kpis` if filter exists, or we use reflection/direct injection
        // Let's rely on standard WordPress filter injection to inject our metric to the provider
        add_filter('mhm_rentiva_dashboard_kpi_customer', static function ($kpis) {
            $kpis['fake_provider_metric'] = array(
                'label'  => 'Fake Test Label',
                'metric' => 'fake_provider_metric',
                'trend'  => true,
            );
            return $kpis;
        });

        // We'll call the build, and specifically inspect our fake_provider_metric within the resulting array.
        $data = DashboardDataProvider::build('customer', 123, 'test@example.com');

        // Assert base structure
        $this->assertIsArray($data);
        $this->assertArrayHasKey('kpi_data', $data);
        $this->assertArrayHasKey('fake_provider_metric', $data['kpi_data']);

        // Assert inner structure (Regression guard vs template UI mismatches)
        $fakeData = $data['kpi_data']['fake_provider_metric'];

        $this->assertIsArray($fakeData);
        $this->assertArrayHasKey('total', $fakeData);
        $this->assertArrayHasKey('trend', $fakeData);
        $this->assertArrayHasKey('direction', $fakeData);

        // Assert calculated values processed by TrendMath deeper down the chain
        $this->assertSame(50, $fakeData['total']);

        // 10 against 5 is a 100% gain, meaning trend is 100 and direction is 'up'
        $this->assertSame(100, $fakeData['trend']);
        $this->assertContains($fakeData['direction'], array('up', 'down', 'neutral'));
        $this->assertSame('up', $fakeData['direction']);
    }
}
