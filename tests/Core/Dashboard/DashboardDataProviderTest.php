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

        // We can hook to `mhmrentiva_dashboard_kpis` if filter exists, or we use reflection/direct injection
        // Let's rely on standard WordPress filter injection to inject our metric to the provider
        add_filter('mhmrentiva_dashboard_kpi_customer', static function ($kpis) {
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

    /**
     * Task A5a seam inversion: the ledger-backed operational metrics
     * (occupancy_rate, cancellation_rate) come from
     * `apply_filters('mhmrentiva_dashboard_vendor_metrics', [], ...)`. Lite's
     * own default is an empty array, which the `?? 0` fallback in
     * DashboardDataProvider::resolve_vendor_operational_metric() turns into
     * '0%' -- identical to the pre-inversion "AnalyticsService class absent" case.
     */
    public function test_vendor_operational_metrics_default_to_zero_percent_without_a_subscriber(): void
    {
        remove_all_filters('mhmrentiva_dashboard_vendor_metrics');

        $data = DashboardDataProvider::build('vendor', 999999, 'nobody@example.com');

        $this->assertSame('0%', $data['kpi_data']['occupancy_rate']['total'] ?? null);
        $this->assertSame('0%', $data['kpi_data']['cancellation_rate']['total'] ?? null);
    }

    /**
     * A subscriber (Pro) can supply the metrics map; the filter's return value
     * flows straight through to the KPI's 'total'.
     */
    public function test_a_subscriber_can_supply_vendor_operational_metrics(): void
    {
        remove_all_filters('mhmrentiva_dashboard_vendor_metrics');
        add_filter('mhmrentiva_dashboard_vendor_metrics', static function () {
            return array(
                'occupancy_rate'    => 77,
                'cancellation_rate' => 3,
            );
        });

        $data = DashboardDataProvider::build('vendor', 999999, 'nobody@example.com');

        $this->assertSame('77%', $data['kpi_data']['occupancy_rate']['total'] ?? null);
        $this->assertSame('3%', $data['kpi_data']['cancellation_rate']['total'] ?? null);

        remove_all_filters('mhmrentiva_dashboard_vendor_metrics');
    }
}
