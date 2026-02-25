<?php

declare(strict_types=1);

namespace MHMRentiva\Core\Services\Metrics;

use MHMRentiva\Core\Financial\AnalyticsService;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Vendor average per-booking earning metric (30d window).
 *
 * Uses COUNT(DISTINCT booking_id) on commission_credit entries only.
 * Refund rows are excluded from booking count.
 *
 * @since 4.21.0
 */
final class VendorAvgBookingValueMetric implements MetricInterface
{
    public function key(): string
    {
        return 'vendor_avg_booking_value';
    }

    public function subjectKey(array $args): string
    {
        return isset($args['vendor_id']) ? (string) $args['vendor_id'] : 'global';
    }

    public function resolve(string $context, array $args, int $currentStart, int $now, int $previousStart): array
    {
        if ($context !== 'vendor' || empty($args['vendor_id'])) {
            return array('avg' => 0.0);
        }

        $vendor_id = (int) $args['vendor_id'];

        $from_ts = $now - (30 * DAY_IN_SECONDS);
        $avg     = AnalyticsService::get_avg_booking_value($vendor_id, $from_ts, $now);

        return array('avg' => $avg);
    }
}
