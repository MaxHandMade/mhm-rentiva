<?php
declare(strict_types=1);

namespace MHMRentiva\Layout\Observability;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Layout Diff Service
 *
 * Provides deterministic manifest-level diffing.
 *
 * @package MHMRentiva\Layout\Observability
 * @since 4.19.0
 */
class LayoutDiffService
{
    /**
     * Compute diff between two manifests.
     *
     * @param array $current  Current manifest map.
     * @param array $previous Previous manifest map.
     * @return array Diff result summary.
     */
    public static function diff(array $current, array $previous): array
    {
        return [
            'tokens'     => self::diff_tokens($current['tokens'] ?? [], $previous['tokens'] ?? []),
            'components' => self::diff_components($current['components'] ?? [], $previous['components'] ?? []),
            'pages'      => self::diff_pages($current['pages'] ?? [], $previous['pages'] ?? []),
        ];
    }

    /**
     * Diff tokens.
     */
    private static function diff_tokens(array $current, array $previous): array
    {
        $added   = array_diff_key($current, $previous);
        $removed = array_diff_key($previous, $current);
        $changed = [];

        foreach (array_intersect_key($current, $previous) as $key => $val) {
            if ($val !== $previous[$key]) {
                $changed[$key] = ['from' => $previous[$key], 'to' => $val];
            }
        }

        return [
            'added'   => array_keys($added),
            'removed' => array_keys($removed),
            'changed' => $changed,
        ];
    }

    /**
     * Diff components.
     */
    private static function diff_components(array $current, array $previous): array
    {
        $added   = array_diff_key($current, $previous);
        $removed = array_diff_key($previous, $current);
        $changed = [];

        foreach (array_intersect_key($current, $previous) as $key => $comp) {
            if ($comp !== $previous[$key]) {
                $changed[$key] = [
                    'type_changed' => ($comp['type'] ?? '') !== ($previous[$key]['type'] ?? ''),
                    // Deep comparison could be added here if needed
                ];
            }
        }

        return [
            'added'   => array_keys($added),
            'removed' => array_keys($removed),
            'changed' => array_keys($changed),
        ];
    }

    /**
     * Diff pages structure (basic).
     */
    private static function diff_pages(array $current, array $previous): array
    {
        // Basic count and index check
        return [
            'count_changed' => count($current) !== count($previous),
            'current_count' => count($current),
            'prev_count'    => count($previous),
        ];
    }
}
