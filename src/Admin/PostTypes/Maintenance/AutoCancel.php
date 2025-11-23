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
    public const SCHEDULE = 'mhm_rentiva_5min'; // Changed to 5min to match DatabaseInitialization

    public static function register(): void
    {
        // Add custom schedules
        add_filter('cron_schedules', [self::class, 'schedules'], 1);
        
        // Schedule event if not scheduled
        add_action('init', [self::class, 'maybe_schedule'], 100);
        
        // Hook runner
        add_action(self::EVENT, [self::class, 'run']);
    }

    public static function schedules(array $schedules): array
    {
        if (!isset($schedules['mhm_rentiva_5min'])) {
            $schedules['mhm_rentiva_5min'] = [
                'interval' => 300, // 5 min
                'display'  => __('Every 5 Minutes (Rentiva)', 'mhm-rentiva'),
            ];
        }
        
        if (!isset($schedules['mhm_rentiva_15min'])) {
            $schedules['mhm_rentiva_15min'] = [
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

        // Ensure schedule exists
        $schedules = wp_get_schedules();
        if (!isset($schedules[self::SCHEDULE])) {
            // Force add filter if not present
            add_filter('cron_schedules', [self::class, 'schedules']);
        }

        // Create new recurring event
        $result = wp_schedule_event(time(), self::SCHEDULE, self::EVENT);
        
        if ($result) {
            error_log('AutoCancel: Recurring event scheduled successfully for ' . self::SCHEDULE);
        } else {
            error_log('AutoCancel: Failed to schedule recurring event for ' . self::SCHEDULE);
            // Fallback to hourly if custom schedule fails
            wp_schedule_event(time(), 'hourly', self::EVENT);
        }
    }

    public static function run(): void
    {
        // Read from unified settings array
        $enabled = (string) \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_booking_auto_cancel_enabled', '0') === '1';
        
        if (!$enabled) {
            return;
        }

        // Use Booking Management setting: payment deadline minutes
        $minutes = (int) \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_booking_payment_deadline_minutes', 30); // Default 30 min
        if ($minutes < 5) $minutes = 5; // Minimum 5 minutes
        
        // Reasonable batch limit
        $limit = 50;

        // Threshold calculation using WordPress time (to match stored dates)
        // _mhm_payment_deadline is stored as 'Y-m-d H:i:s' in WP timezone
        $current_time = current_time('mysql');
        
        // Find unpaid bookings with expired payment deadline
        $q = new WP_Query([
            'post_type'      => 'vehicle_booking',
            'post_status'    => 'any', // Check all statuses to be safe, filter later
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
                    'value'   => $current_time,
                    'compare' => '<',
                    'type'    => 'DATETIME',
                ],
                [
                    'key'     => '_mhm_payment_deadline',
                    'compare' => 'EXISTS',
                ]
            ],
        ]);

        if (!$q->have_posts()) {
            return;
        }
        
        error_log('🔍 AutoCancel: Found ' . count($q->posts) . ' expired bookings. Current Time: ' . $current_time);

        foreach ($q->posts as $bid) {
            $bid = (int) $bid;
            
            // Double check status
            $bookingStatus = (string) get_post_meta($bid, '_mhm_status', true);
            
            // Only cancel pending bookings
            if ($bookingStatus !== 'pending') {
                continue;
            }
            
            $payStatus = (string) get_post_meta($bid, '_mhm_payment_status', true);
            
            // Skip if not pending payment
            if ($payStatus !== 'pending') {
                continue;
            }

            // Perform cancellation
            try {
                $newStatus = 'cancelled';
                update_post_meta($bid, '_mhm_status', $newStatus);
                update_post_meta($bid, '_mhm_auto_cancelled', current_time('timestamp'));
                update_post_meta($bid, '_mhm_auto_cancelled_reason', 'Payment deadline expired (' . $minutes . ' minutes)');
                
                // Log action
                if (class_exists(Logger::class)) {
                    Logger::info("Booking #$bid auto-cancelled due to payment deadline expiration.", [
                        'booking_id' => $bid,
                        'deadline_minutes' => $minutes
                    ], 'system');
                }
                
                error_log("MHM AutoCancel: Booking $bid cancelled. Deadline expired.");
                
                do_action('mhm_rentiva_booking_auto_cancelled', $bid, $newStatus);
                
            } catch (\Throwable $e) {
                error_log("MHM AutoCancel Error: " . $e->getMessage());
            }
        }
        wp_reset_postdata();
    }
}
