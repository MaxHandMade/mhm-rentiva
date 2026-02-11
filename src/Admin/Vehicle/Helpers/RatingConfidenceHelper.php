<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Vehicle\Helpers;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Rating Confidence Helper
 *
 * Computes a trust-signal label based on rating_count.
 * Cosmetic only — does NOT affect rating aggregation.
 *
 * Buckets (default thresholds [2, 9]):
 *   - 1–2   → "New"
 *   - 3–9   → "Reliable"
 *   - 10+   → "Highly Reliable"
 *
 * Override via filter: mhm_rentiva_rating_confidence_thresholds
 *
 * @since 1.3.1
 * @package MHMRentiva\Admin\Vehicle\Helpers
 */
final class RatingConfidenceHelper
{
    /**
     * Default thresholds: [upper_bound_new, upper_bound_reliable]
     * Meaning: <=2 → new, <=9 → reliable, >=10 → high
     */
    private const DEFAULT_THRESHOLDS = array(2, 9);

    /**
     * Compute confidence label from rating count.
     *
     * @since 1.3.1
     *
     * @param int $rating_count Number of approved reviews.
     * @return array{key: string, label: string, tooltip: string}
     */
    public static function from_count(int $rating_count): array
    {
        // Nothing to show for zero reviews
        if ($rating_count <= 0) {
            return array(
                'key'     => '',
                'label'   => '',
                'tooltip' => '',
            );
        }

        /**
         * Filter the confidence thresholds.
         *
         * @since 1.3.1
         *
         * @param array $thresholds [upper_bound_new, upper_bound_reliable]
         *   Default: [2, 9]
         *   - count <= thresholds[0] → "New"
         *   - count <= thresholds[1] → "Reliable"
         *   - count > thresholds[1]  → "Highly Reliable"
         */
        $thresholds = apply_filters(
            'mhm_rentiva_rating_confidence_thresholds',
            self::DEFAULT_THRESHOLDS
        );

        // Ensure valid thresholds
        $threshold_new      = (int) ($thresholds[0] ?? 2);
        $threshold_reliable = (int) ($thresholds[1] ?? 9);

        if ($rating_count <= $threshold_new) {
            return array(
                'key'     => 'new',
                'label'   => __('New', 'mhm-rentiva'),
                'tooltip' => __('This vehicle has just started receiving reviews.', 'mhm-rentiva'),
            );
        }

        if ($rating_count <= $threshold_reliable) {
            return array(
                'key'     => 'reliable',
                'label'   => __('Reliable', 'mhm-rentiva'),
                'tooltip' => __('This vehicle has a growing number of reviews.', 'mhm-rentiva'),
            );
        }

        return array(
            'key'     => 'high',
            'label'   => __('Highly Reliable', 'mhm-rentiva'),
            'tooltip' => __('This vehicle has been reviewed by many renters.', 'mhm-rentiva'),
        );
    }
}
