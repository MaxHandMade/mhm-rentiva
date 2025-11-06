<?php declare(strict_types=1);

namespace MHMRentiva\Admin\PostTypes\Maintenance;

use MHMRentiva\Admin\PostTypes\Logs\Logger;
use MHMRentiva\Admin\Booking\Core\Status;
use WP_Query;
use Exception;

if (!defined('ABSPATH')) {
    exit;
}

final class AutoCancel
{
    public const EVENT = 'mhm_rentiva_auto_cancel_event';
    public const SCHEDULE = 'mhm_rentiva_15min';

    public static function register(): void
    {
        // Add custom schedule (every 15 minutes) - Earliest hook
        add_filter('cron_schedules', [self::class, 'schedules'], 1);
        // Schedule event if not scheduled - use init hook
        add_action('init', [self::class, 'maybe_schedule'], 100);
        // Hook runner
        add_action(self::EVENT, [self::class, 'run']);
    }

    public static function schedules(array $schedules): array
    {
        if (!isset($schedules[self::SCHEDULE])) {
            $schedules[self::SCHEDULE] = [
                'interval' => 900, // 15 min
                'display'  => __('Every 15 Minutes (Rentiva)', 'mhm-rentiva'),
            ];
        }
        return $schedules;
    }

    public static function maybe_schedule(): void
    {
        // If already scheduled, don't create again
        if (wp_next_scheduled(self::EVENT)) {
            return;
        }

        // Manually add custom schedule (fallback)
        $schedules = wp_get_schedules();
        if (!isset($schedules[self::SCHEDULE])) {
            add_filter('cron_schedules', [self::class, 'schedules']);
            // Reload schedules
            wp_cache_flush();
            $schedules = wp_get_schedules();
        }

        if (!isset($schedules[self::SCHEDULE])) {
            // If still not defined, use hourly (fallback)
            wp_schedule_event(time() + 60, 'hourly', self::EVENT);
            error_log('AutoCancel: Using fallback hourly schedule - custom schedule not available');
            return;
        }

        // Create new recurring event
        $result = wp_schedule_event(time() + 60, self::SCHEDULE, self::EVENT);
        if ($result) {
            error_log('AutoCancel: Recurring event scheduled successfully for ' . self::SCHEDULE);
        } else {
            error_log('AutoCancel: Failed to schedule recurring event for ' . self::SCHEDULE);
        }
    }

    public static function run(): void
    {
        error_log('AutoCancel: run() function called');
        // Read from unified settings array instead of legacy standalone options
        $enabled = (string) \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_booking_auto_cancel_enabled', '0') === '1';
        if (!$enabled) {
            error_log('AutoCancel: Auto cancel is disabled');
            return;
        }

        // Use Booking Management setting: payment deadline minutes
        $minutes = (int) \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_booking_payment_deadline_minutes', 60);
        if ($minutes < 10) $minutes = 10;
        // Reasonable batch limit
        $limit = 50;
        if ($limit < 1) $limit = 1;
        if ($limit > 200) $limit = 200;

        // Threshold (Local time)
        $thresholdTs = time() - ($minutes * 60);
        $threshold   = date('Y-m-d H:i:s', $thresholdTs);

        // Find unpaid bookings with expired payment deadline
        $q = new WP_Query([
            'post_type'      => 'vehicle_booking',
            'post_status'    => 'any',
            'fields'         => 'ids',
            'posts_per_page' => $limit,
            'no_found_rows'  => true,
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'key'     => '_mhm_payment_status',
                    'value'   => 'pending',
                    'compare' => '=',
                ],
                [
                    'key'     => '_mhm_payment_deadline',
                    'value'   => $threshold,
                    'compare' => '<',
                    'type'    => 'DATETIME',
                ],
            ],
        ]);

        if (!$q->have_posts()) {
            error_log('🔍 AutoCancel: No expired bookings found. Threshold: ' . $threshold);
            return;
        }
        
        error_log('🔍 AutoCancel: Found ' . count($q->posts) . ' expired bookings');

        foreach ($q->posts as $bid) {
            $bid = (int) $bid;
            // Skip if already confirmed/cancelled or pending verification (offline)
            $bookingStatus = (string) get_post_meta($bid, '_mhm_status', true);
            if (in_array($bookingStatus, ['confirmed', 'cancelled', 'completed', 'expired'], true)) {
                continue;
            }
            $payStatus = (string) get_post_meta($bid, '_mhm_payment_status', true);
            if ($payStatus === 'pending_verification') {
                continue;
            }

            // Perform cancellation
            try {
                // Directly update meta (Status class may be problematic)
                $newStatus = 'cancelled';
                update_post_meta($bid, '_mhm_status', $newStatus);
                update_post_meta($bid, '_mhm_auto_cancelled', (int) time());
                update_post_meta($bid, '_mhm_auto_cancelled_reason', '30 minutes timeout');

                // Removed Logger::add() call (causing issues)
                error_log("MHM AutoCancel: Booking $bid cancelled after $minutes minutes");
                
                do_action('mhm_rentiva_booking_auto_cancelled', $bid, $newStatus);
            } catch (\Throwable $e) {
                error_log("MHM AutoCancel Error: " . $e->getMessage());
            }
        }
        wp_reset_postdata();
    }

    private static function supportsStatus(string $status): bool
    {
        // Best-effort: allow plugin to define supported statuses via filter
        $supported = apply_filters('mhm_rentiva_supported_booking_statuses', ['pending', 'confirmed', 'cancelled', 'expired', 'completed']);
        return in_array($status, (array) $supported, true);
    }
}
