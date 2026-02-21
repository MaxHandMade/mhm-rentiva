<?php

declare(strict_types=1);

namespace MHMRentiva\Layout\Observability;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Layout Audit Service
 *
 * Handles append-only logging for layout operations with a retention cap.
 *
 * @package MHMRentiva\Layout\Observability
 * @since 4.19.0
 */
class LayoutAuditService
{
    private const META_KEY      = '_mhm_layout_audit_log';
    private const RETENTION_CAP = 200;

    /**
     * Append an event to the audit log.
     *
     * @param int   $post_id Post ID.
     * @param array $event   Event data.
     * @return bool True on success.
     */
    public static function append_event(int $post_id, array $event): bool
    {
        $log = get_post_meta($post_id, self::META_KEY, true);
        if (! is_array($log)) {
            $log = [];
        }

        // Prepare event
        $event['timestamp'] = current_time('mysql', true);
        $event['actor']     = self::get_actor();
        $event['source']    = defined('WP_CLI') && WP_CLI ? 'CLI' : 'Web';

        // Append
        $log[] = $event;

        // Enforce retention cap
        if (count($log) > self::RETENTION_CAP) {
            $log = array_slice($log, -self::RETENTION_CAP);
            $log[count($log) - 1]['truncated'] = true;
        }

        return (bool) update_post_meta($post_id, self::META_KEY, $log);
    }

    /**
     * Log a successful import.
     */
    public static function log_import(int $post_id, string $prev_hash, string $new_hash, bool $dry_run = false): bool
    {
        return self::append_event($post_id, [
            'operation'     => 'import',
            'previous_hash' => $prev_hash,
            'new_hash'      => $new_hash,
            'dry_run'       => $dry_run
        ]);
    }

    /**
     * Log a successful rollback.
     */
    public static function log_rollback(int $post_id, string $prev_hash, string $new_hash, bool $dry_run = false): bool
    {
        return self::append_event($post_id, [
            'operation'     => 'rollback',
            'previous_hash' => $prev_hash,
            'new_hash'      => $new_hash,
            'dry_run'       => $dry_run
        ]);
    }

    /**
     * Get events for a post.
     */
    public static function get_events(int $post_id, int $limit = 0): array
    {
        $log = get_post_meta($post_id, self::META_KEY, true);
        if (! is_array($log)) {
            return [];
        }

        if ($limit > 0) {
            return array_slice($log, -$limit);
        }

        return $log;
    }

    /**
     * Get current actor name.
     */
    private static function get_actor(): string
    {
        if (defined('WP_CLI') && WP_CLI) {
            return 'WP-CLI User';
        }

        $user = wp_get_current_user();
        return $user->exists() ? $user->user_login : 'System';
    }
}
