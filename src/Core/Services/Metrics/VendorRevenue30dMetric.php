<?php

declare(strict_types=1);

namespace MHMRentiva\Core\Services\Metrics;

use MHMRentiva\Core\Financial\AnalyticsService;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Vendor 30-day cleared net revenue metric for the financial dashboard.
 *
 * @since 4.21.0
 */
final class VendorRevenue30dMetric implements MetricInterface
{
    public function key(): string
    {
        return 'vendor_revenue_30d';
    }

    public function subjectKey(array $args): string
    {
        return isset($args['vendor_id']) ? (string) $args['vendor_id'] : 'global';
    }

    public function resolve(string $context, array $args, int $currentStart, int $now, int $previousStart): array
    {
        if ($context !== 'vendor' || empty($args['vendor_id'])) {
            return array('current' => 0.0, 'previous' => 0.0);
        }

        $vendor_id = (int) $args['vendor_id'];

        // Non-overlapping 30d windows:
        // current:  [now - 30d, now)
        // previous: [now - 60d, now - 30d)
        $thirty_days = 30 * DAY_IN_SECONDS;
        $current_start  = $now - $thirty_days;
        $previous_start = $current_start - $thirty_days;

        $current  = AnalyticsService::get_revenue_period($vendor_id, $current_start, $now);
        $previous = AnalyticsService::get_revenue_period($vendor_id, $previous_start, $current_start);

        return array(
            'current'  => $current,
            'previous' => $previous,
        );
    }
}
