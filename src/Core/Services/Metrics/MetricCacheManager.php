<?php
declare(strict_types=1);

namespace MHMRentiva\Core\Services\Metrics;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Metric Cache Manager
 *
 * Handles granular, event-driven cache invalidation for dashboard analytics.
 * Protects runtime memory footprints by leveraging namespace segmentation and event debouncing.
 */
final class MetricCacheManager {

    /**
     * Prefix for all metric caching.
     */
    public const PREFIX = 'mhm_metric_';

    /**
     * 15-minute fallback TTL to ensure edge-cases do not persist anomalies infinitely.
     */
    public const FALLBACK_TTL = 15 * MINUTE_IN_SECONDS;

    /**
     * Request-scope ledger to prevent double-firing invalidations across consecutive WP triggers.
     *
     * @var array<string, bool>
     */
    private static array $flushed = array();

    /**
     * Generate a standardized deterministic cache key.
     *
     * @param string $context    e.g. customer, vendor
     * @param string $metric     e.g. total_bookings, revenue_7d
     * @param string $subjectKey e.g. 12 (user ID), guest
     */
    public static function build_key(string $context, string $metric, string $subjectKey): string
    {
        return self::PREFIX . sanitize_key($context) . '_' . sanitize_key($metric) . '_' . sanitize_key($subjectKey);
    }

    /**
     * Get metric from cache.
     *
     * @return array<string, mixed>|false
     */
    public static function get(string $context, string $metric, string $subjectKey)
    {
        return get_transient(self::build_key($context, $metric, $subjectKey));
    }

    /**
     * Set metric to cache.
     *
     * @param array<string, mixed> $data
     */
    public static function set(string $context, string $metric, string $subjectKey, array $data): bool
    {
        return set_transient(self::build_key($context, $metric, $subjectKey), $data, self::FALLBACK_TTL);
    }

    /**
     * Flush a specific metric for a subject in a given context.
     */
    public static function flush_subject_metric(string $context, string $metric, string $subjectKey): void
    {
        $key = self::build_key($context, $metric, $subjectKey);

        // Event Debounce Guard: Prevents duplicate flushes in same request
        if (isset(self::$flushed[ $key ])) {
            return;
        }

        delete_transient($key);
        self::$flushed[ $key ] = true;
    }

    /**
     * Flush ALL metrics belonging to a specific subject across all contexts via WPDB Wildcard prefix matching.
     */
    public static function flush_subject_all_metrics(string $subjectKey): void
    {
        $subjectKey = sanitize_key($subjectKey);

        // Event Debounce Guard for wildcard flush
        $flushId = 'all_metrics_' . $subjectKey;
        if (isset(self::$flushed[ $flushId ])) {
            return;
        }

        global $wpdb;

        // We strictly match transients starting with our exact namespace prefix, and ending with exactly _{subjectKey}
        // $wpdb->esc_like escapes special characters so they act as literals, allowing us to safely append real SQL '%' wildcards.
        $like_pattern = '_transient_' . $wpdb->esc_like(self::PREFIX) . '%' . $wpdb->esc_like('_' . $subjectKey);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Cache invalidation requires a live lookup of matching transient option rows.
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
                $like_pattern
            )
        );

        foreach ($results as $result) {
            $transient_name = str_replace('_transient_', '', $result->option_name);
            delete_transient($transient_name);
            self::$flushed[ $transient_name ] = true;
        }

        self::$flushed[ $flushId ] = true;
    }

    /**
     * Expose manually triggerable hook bindings on engine boot sequence.
     */
    public static function boot(): void
    {
        // Booking Entity Modifications affecting all contexts
        add_action('save_post_vehicle_booking', array( self::class, 'on_booking_saved' ), 10, 3);
        add_action('delete_post', array( self::class, 'on_booking_deleted' ), 10, 2);
        add_action('mhm_rentiva_booking_status_changed', array( self::class, 'on_booking_status_changed' ), 10, 3);

        // Profiles & Users (Favorites are generally mapped to customer namespace explicitly)
        add_action('updated_user_meta', array( self::class, 'on_user_meta_updated' ), 10, 4);
        add_action('added_user_meta', array( self::class, 'on_user_meta_updated' ), 10, 4);
        add_action('deleted_user_meta', array( self::class, 'on_user_meta_updated' ), 10, 4);

        // Internal Communication Message events affecting unread metrics on both role namespaces
        add_action('mhm_message_status_changed', array( self::class, 'on_message_updated' ), 10, 3);
        add_action('mhm_message_created', array( self::class, 'on_message_created' ), 10, 2);
    }

    // === Event Dispatches (Hook Callbacks) ===

    public static function on_booking_saved(int $post_id, \WP_Post $post, bool $update): void
    {
        if ($post->post_type !== 'vehicle_booking') {
            return;
        }
        self::flush_booking_cache_for_participants($post_id);
    }

    public static function on_booking_deleted(int $post_id, \WP_Post $post): void
    {
        if ($post->post_type !== 'vehicle_booking') {
            return;
        }
        self::flush_booking_cache_for_participants($post_id);
    }

    public static function on_booking_status_changed(int $booking_id, string $old_status, string $new_status): void
    {
        self::flush_booking_cache_for_participants($booking_id);
    }

    /**
     * Meta change callback listener to flag when a user updates their MHM favorites payload.
     */
    public static function on_user_meta_updated(int|array $meta_id, int $user_id, string $meta_key, $meta_value): void
    {
        if ($meta_key === 'mhm_rentiva_favorites' && $user_id > 0) {
            self::flush_subject_metric('customer', 'saved_favorites', (string) $user_id);
        }
    }

    /**
     * Flushes unread_messages whenever a message flips between read/unread explicitly.
     */
    public static function on_message_updated(int $message_id, string $old_status, string $new_status): void
    {
        // Since message status is agnostic of sender vs receiver in this generic hook, safest isolation is flushing entire wildcard
        // based upon exact parent mapping, but for MVP isolating just known recipients is preferred if easily obtainable
        $recipient_id = (int) get_post_meta($message_id, '_mhm_recipient_id', true);
        if ($recipient_id > 0) {
            self::flush_subject_metric('customer', 'unread_messages', (string) $recipient_id);
            self::flush_subject_metric('vendor', 'unread_messages', (string) $recipient_id);
        }
    }

    /**
     * Flushes unread_messages whenever an entity receives a brand new message node in unread state.
     */
    public static function on_message_created(int $message_id, array $data): void
    {
        // The `mhm_message_created` hook array payload contains 'recipient_id' OR we extract meta.
        $recipient_id = (int) ( $data['recipient_id'] ?? get_post_meta($message_id, '_mhm_recipient_id', true) );
        if ($recipient_id > 0) {
            self::flush_subject_metric('customer', 'unread_messages', (string) $recipient_id);
            self::flush_subject_metric('vendor', 'unread_messages', (string) $recipient_id);
        }
    }

    /**
     * Resolves User IDs associated with a specific Booking and forces wildcard flushes across all metric namespaces.
     */
    private static function flush_booking_cache_for_participants(int $booking_id): void
    {
        $customer_id = (int) get_post_meta($booking_id, '_mhm_customer_user_id', true);
        if ($customer_id > 0) {
            self::flush_subject_all_metrics( (string) $customer_id);
        }

        $vehicle_id = (int) get_post_meta($booking_id, '_mhm_vehicle_id', true);
        if ($vehicle_id > 0) {
            // Locate owner of the vehicle (Vendor)
            $vendor_id = (int) get_post_field('post_author', $vehicle_id);
            if ($vendor_id > 0) {
                self::flush_subject_all_metrics( (string) $vendor_id);
            }

            // Flush isolated vehicle performance cache
            self::flush_subject_metric('vehicle', 'perf', (string) $vehicle_id);
        }
    }
}
