<?php
declare(strict_types=1);

namespace MHMRentiva\Core\Services\Metrics;

if (! defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Core\Financial\Ledger;



/**
 * Metric defining total non-accessible balance amounts historically constrained to credit injections and reversals natively.
 */
final class PendingBalanceMetric implements MetricInterface
{
    public function key(): string
    {
        return 'pending_balance';
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

        // Absolute pending constraint
        $total = Ledger::get_pending_balance($vendor_id);

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
}
