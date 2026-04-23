<?php
declare(strict_types=1);

namespace MHMRentiva\Core\Services;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Pure mathematical calculations for metrics.
 * Extracted to allow fast, I/O-free Unit Testing without WordPress dependencies.
 */
final class TrendMath {

    /**
     * Calculates the percentage trend and determines direction from raw totals.
     *
     * @param int $current  Current period value.
     * @param int $previous Previous period value.
     * @return array{current:int,previous:int,trend:int,direction:string}
     */
    public static function calculate_trend_from_totals(int $current, int $previous): array
    {
        $trend     = 0.0;
        $direction = 'neutral';

        if ($previous > 0) {
            $trend = ( ( $current - $previous ) / $previous ) * 100;
        } elseif ($current > 0) {
            $trend = 100;
        }

        if ($trend > 0) {
            $direction = 'up';
        } elseif ($trend < 0) {
            $direction = 'down';
        }

        return array(
            'current'   => $current,
            'previous'  => $previous,
            'trend'     => (int) round($trend),
            'direction' => $direction,
        );
    }
}
