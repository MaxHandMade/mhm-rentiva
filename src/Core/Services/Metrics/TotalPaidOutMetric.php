<?php

declare(strict_types=1);

namespace MHMRentiva\Core\Services\Metrics;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Metric aggregating lifetime absolute payout values strictly parsing explicit cleared withdrawals scaling positively natively protecting presentation rules dynamically.
 */
final class TotalPaidOutMetric implements MetricInterface
{
    public function key(): string
    {
        return 'total_paid_out';
    }

    public function resolve(string $context, array $args, int $currentStart, int $now, int $previousStart): array
    {
        if ($context !== 'vendor' || empty($args['vendor_id'])) {
            return array(
                'total'    => 0.0,
                'current'  => 0.0,
                'previous' => 0.0,
            );
        }

        $vendor_id = (int) $args['vendor_id'];
        $total     = $this->query_absolute_payout_sum($vendor_id);

        return array(
            'total'    => $total,
            'current'  => 0.0, // Lifetime bound only metric
            'previous' => 0.0,
        );
    }

    public function subjectKey(array $args): string
    {
        if (! empty($args['vendor_id'])) {
            return (string) $args['vendor_id'];
        }
        return 'global';
    }

    /**
     * Explicitly sums absolute negative payout_debits rendering mathematically reliable presentation metrics globally limiting string conversions safely evaluating strict status
     */
    private function query_absolute_payout_sum(int $vendor_id): float
    {
        global $wpdb;
        $table = $wpdb->prefix . 'mhm_rentiva_ledger';

        $sum = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT SUM(ABS(amount)) FROM {$table} WHERE vendor_id = %d AND type = %s AND status = %s",
                $vendor_id,
                'payout_debit',
                'cleared'
            )
        );

        return (float) $sum;
    }
}
