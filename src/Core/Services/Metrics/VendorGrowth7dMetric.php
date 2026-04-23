<?php
declare(strict_types=1);

namespace MHMRentiva\Core\Services\Metrics;

if (! defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Core\Financial\AnalyticsService;



/**
 * Vendor 7-day revenue growth rate metric.
 *
 * Returns null when previous period has zero revenue (insufficient data).
 * Returns a positive or negative float representing percentage growth otherwise.
 *
 * @since 4.21.0
 */
final class VendorGrowth7dMetric implements MetricInterface {

    public function key(): string
    {
        return 'vendor_growth_7d';
    }

    public function subjectKey(array $args): string
    {
        return isset($args['vendor_id']) ? (string) $args['vendor_id'] : 'global';
    }

    public function resolve(string $context, array $args, int $currentStart, int $now, int $previousStart): array
    {
        if ($context !== 'vendor' || empty($args['vendor_id'])) {
            return array( 'growth' => null );
        }

        $vendor_id = (int) $args['vendor_id'];
        $growth    = AnalyticsService::get_growth_rate($vendor_id, 7, $now);

        return array( 'growth' => $growth );
    }
}
