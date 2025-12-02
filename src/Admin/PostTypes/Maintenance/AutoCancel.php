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
        // Add custom schedules - register immediately so it's available when wp_schedule_event is called
        // The filter is lazy-loaded, so it will be applied when wp_get_schedules() is called
        // Use priority 1 to ensure it's registered before other plugins
        add_filter('cron_schedules', [self::class, 'schedules'], 1);
        
        // Also ensure it's registered on plugins_loaded if not already
        if (!did_action('plugins_loaded')) {
            add_action('plugins_loaded', function() {
                add_filter('cron_schedules', [self::class, 'schedules'], 1);
            }, 1);
        }
        
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
        // Ensure schedule filter is applied before checking schedules
        add_filter('cron_schedules', [self::class, 'schedules'], 1);
        
        // Get schedules (this will trigger the filter)
        $schedules = wp_get_schedules();
        
        if (!isset($schedules[self::SCHEDULE])) {
            error_log('AutoCancel: Custom schedule ' . self::SCHEDULE . ' not found in available schedules. Available: ' . implode(', ', array_keys($schedules)));
            return;
        }

        // If already scheduled, check if it's using the correct schedule
        $next_scheduled = wp_next_scheduled(self::EVENT);
        if ($next_scheduled) {
            $current_schedule = wp_get_schedule(self::EVENT);
            // If schedule is wrong, unschedule and reschedule
            if ($current_schedule !== self::SCHEDULE) {
                wp_unschedule_event($next_scheduled, self::EVENT);
                $next_scheduled = false; // Force reschedule
            } else {
                // Verify the schedule is still valid
                $verify_schedule = wp_get_schedule(self::EVENT);
                if ($verify_schedule === self::SCHEDULE) {
                    return; // Already scheduled correctly
                }
                // Schedule is invalid, unschedule it
                wp_unschedule_event($next_scheduled, self::EVENT);
                $next_scheduled = false;
            }
        }

        // Double-check schedule exists before scheduling
        // Force filter application by calling wp_get_schedules() multiple times
        $schedules = wp_get_schedules();
        if (!isset($schedules[self::SCHEDULE])) {
            error_log('AutoCancel: Schedule ' . self::SCHEDULE . ' not available when attempting to schedule event. Available: ' . implode(', ', array_keys($schedules)));
            return;
        }

        // Verify schedule details
        $schedule_info = $schedules[self::SCHEDULE];
        if (!isset($schedule_info['interval']) || $schedule_info['interval'] !== 300) {
            error_log('AutoCancel: Schedule ' . self::SCHEDULE . ' has incorrect interval: ' . ($schedule_info['interval'] ?? 'missing'));
            return;
        }

        // Use direct cron array manipulation to avoid WordPress's schedule validation
        // This bypasses the invalid_schedule error that occurs when wp_schedule_event()
        // checks the schedule before the filter is applied
        self::direct_schedule_event();
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
            // Debug: Log when no expired bookings found
            error_log('🔍 AutoCancel: No expired bookings found. Current Time: ' . $current_time . ', Deadline Minutes: ' . $minutes);
            return;
        }
        
        error_log('🔍 AutoCancel: Found ' . count($q->posts) . ' expired bookings. Current Time: ' . $current_time . ', Booking IDs: ' . implode(', ', $q->posts));

        foreach ($q->posts as $bid) {
            $bid = (int) $bid;
            
            // Double check status
            $bookingStatus = (string) get_post_meta($bid, '_mhm_status', true);
            $payStatus = (string) get_post_meta($bid, '_mhm_payment_status', true);
            $payment_deadline = (string) get_post_meta($bid, '_mhm_payment_deadline', true);
            
            // Debug log
            error_log("🔍 AutoCancel: Checking booking #$bid - Status: $bookingStatus, Payment Status: $payStatus, Deadline: $payment_deadline");
            
            // Only cancel pending bookings
            if ($bookingStatus !== 'pending') {
                error_log("⏭️ AutoCancel: Skipping booking #$bid - Status is '$bookingStatus' (not 'pending')");
                continue;
            }
            
            // Skip if not pending payment
            if ($payStatus !== 'pending') {
                error_log("⏭️ AutoCancel: Skipping booking #$bid - Payment status is '$payStatus' (not 'pending')");
                continue;
            }

            // Perform cancellation
            try {
                $newStatus = 'cancelled';
                update_post_meta($bid, '_mhm_status', $newStatus);
                update_post_meta($bid, '_mhm_payment_status', 'cancelled');
                update_post_meta($bid, '_mhm_auto_cancelled', current_time('timestamp'));
                update_post_meta($bid, '_mhm_auto_cancelled_reason', 'Payment deadline expired (' . $minutes . ' minutes)');
                
                // Clear availability cache
                $vehicle_id = (int) get_post_meta($bid, '_mhm_vehicle_id', true);
                if ($vehicle_id && class_exists('MHMRentiva\Admin\Booking\Helpers\Cache')) {
                    \MHMRentiva\Admin\Booking\Helpers\Cache::invalidateVehicle($vehicle_id);
                }
                
                // Log action
                if (class_exists(Logger::class)) {
                    Logger::info("Booking #$bid auto-cancelled due to payment deadline expiration.", [
                        'booking_id' => $bid,
                        'deadline_minutes' => $minutes
                    ], 'system');
                }
                
                error_log("✅ AutoCancel: Booking #$bid cancelled. Deadline expired. Vehicle ID: $vehicle_id");
                
                do_action('mhm_rentiva_booking_auto_cancelled', $bid, $newStatus);
                
            } catch (\Throwable $e) {
                error_log("MHM AutoCancel Error: " . $e->getMessage());
            }
        }
        wp_reset_postdata();
    }

    /**
     * Direct schedule event - bypasses wp_schedule_event's schedule validation
     * This method directly manipulates the cron array to avoid the invalid_schedule error
     */
    private static function direct_schedule_event(): void
    {
        // Ensure schedule filter is applied
        add_filter('cron_schedules', [self::class, 'schedules'], 1);
        $schedules = wp_get_schedules();
        
        if (!isset($schedules[self::SCHEDULE])) {
            error_log('AutoCancel: Cannot schedule - schedule ' . self::SCHEDULE . ' not available');
            return;
        }

        // Get cron array
        $cron = _get_cron_array();
        if ($cron === false) {
            $cron = [];
        }

        // Remove any existing events for this hook
        foreach ($cron as $timestamp => $cronhooks) {
            if (isset($cronhooks[self::EVENT])) {
                unset($cron[$timestamp][self::EVENT]);
                // Clean up empty timestamps
                if (empty($cron[$timestamp])) {
                    unset($cron[$timestamp]);
                }
            }
        }

        // Calculate next run time (5 minutes from now)
        $next_run = time() + 300;
        
        // Add to cron array with proper structure
        $cron[$next_run][self::EVENT][md5(serialize([]))] = [
            'schedule' => self::SCHEDULE,
            'args' => [],
        ];

        // Sort by timestamp
        ksort($cron);
        
        // Save cron array
        _set_cron_array($cron);
        
        // Verify it was scheduled
        $verify_next = wp_next_scheduled(self::EVENT);
        $verify_schedule = wp_get_schedule(self::EVENT);
        
        if ($verify_next && $verify_schedule === self::SCHEDULE) {
            error_log('AutoCancel: Successfully scheduled recurring event for ' . self::SCHEDULE . ' (next run: ' . date('Y-m-d H:i:s', $verify_next) . ')');
        } else {
            error_log('AutoCancel: Direct schedule failed. Next: ' . ($verify_next ? date('Y-m-d H:i:s', $verify_next) : 'none') . ', Schedule: ' . ($verify_schedule ?: 'none'));
        }
    }
}
