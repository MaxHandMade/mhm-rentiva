<?php

declare(strict_types=1);

namespace MHMRentiva\Core\Services\Metrics;

use MHMRentiva\Core\Financial\Ledger;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Validatable metric reflecting natively accessible ledger cleared limits dynamically independent of pending allocations.
 */
final class AvailableBalanceMetric implements MetricInterface
{
    public function key(): string
    {
        return 'available_balance';
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

        $total = Ledger::get_balance($vendor_id); // Internally restricts entirely to `status = 'cleared'` natively encompassing payouts and credits simultaneously.

        return array(
            'total'    => $total,
            'current'  => 0.0,
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
