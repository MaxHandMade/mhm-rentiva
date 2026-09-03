<?php
declare(strict_types=1);

namespace MHMRentiva\Layout\Observability;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Layout History Service
 *
 * Provides a read-only view of current and previous layout states.
 *
 * @package MHMRentiva\Layout\Observability
 * @since 4.19.0
 */
class LayoutHistoryService {

    /**
     * Get current version info for a post.
     */
    public static function get_current(int $post_id): array
    {
        return [
            'hash'      => get_post_meta($post_id, '_mhmrentiva_layout_hash', true),
            'timestamp' => get_post_meta($post_id, '_mhmrentiva_layout_version_timestamp', true),
            'manifest'  => get_post_meta($post_id, '_mhmrentiva_layout_manifest', true),
        ];
    }

    /**
     * Get previous version info for a post.
     */
    public static function get_previous(int $post_id): array
    {
        return [
            'hash'      => get_post_meta($post_id, '_mhmrentiva_layout_hash_previous', true),
            'timestamp' => get_post_meta($post_id, '_mhmrentiva_layout_version_timestamp_previous', true),
            'manifest'  => get_post_meta($post_id, '_mhmrentiva_layout_manifest_previous', true),
        ];
    }

    /**
     * Get a summary for CLI display.
     */
    public static function get_summary(int $post_id): array
    {
        $current = self::get_current($post_id);
        $prev    = self::get_previous($post_id);
        $audit   = LayoutAuditService::get_events($post_id, 1);
        $last    = ! empty($audit) ? end($audit) : null;

        // Values, not sentences: "N/A", "Unknown" and the "OP (date by actor)"
        // wording are display decisions in one language. The caller renders them.
        return [
            'post_id'        => $post_id,
            'current_hash'   => (string) $current['hash'],
            'current_date'   => (string) $current['timestamp'],
            'previous_hash'  => (string) $prev['hash'],
            'previous_date'  => (string) $prev['timestamp'],
            'last_operation' => $last ? [
                'operation' => (string) ( $last['operation'] ?? '' ),
                'timestamp' => (string) ( $last['timestamp'] ?? '' ),
                'actor'     => (string) ( $last['actor'] ?? '' ),
            ] : null,
        ];
    }
}
