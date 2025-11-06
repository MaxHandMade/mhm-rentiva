<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Booking\Core;

use MHMRentiva\Admin\Licensing\Mode;
use MHMRentiva\Admin\Licensing\Restrictions;
use MHMRentiva\Admin\Booking\Helpers\Cache;

if (!defined('ABSPATH')) {
    exit;
}

final class Hooks
{
    public static function register(): void
    {
        // Cache invalidation hooks
        add_action('mhm_rentiva_booking_created', [self::class, 'invalidate_availability_cache'], 10, 1);
        add_action('mhm_rentiva_booking_status_changed', [self::class, 'invalidate_availability_cache'], 10, 1);
        
        // License limit checks
        add_action('mhm_rentiva_before_booking_create', [self::class, 'check_license_limits'], 10, 2);
        
        // Status automation hooks
        add_action('mhm_rentiva_booking_status_changed', [self::class, 'handle_status_automation'], 10, 3);
    }

    /**
     * Clear cache when booking is created or status changes
     */
    public static function invalidate_availability_cache(int $booking_id): void
    {
        $vehicle_id = (int) get_post_meta($booking_id, '_mhm_vehicle_id', true);
        if ($vehicle_id) {
            Cache::invalidateVehicle($vehicle_id);
        }
    }

    /**
     * Check license limits before creating a booking
     */
    public static function check_license_limits(int $vehicle_id, array $booking_data): void
    {
        if (class_exists('\MHMRentiva\Admin\Licensing\Mode') && class_exists('\MHMRentiva\Admin\Licensing\Restrictions')) {
            if (Mode::isLite() && Restrictions::bookingCount() >= Mode::maxBookings()) {
                // Return error with WordPress redirect
                $referer = wp_get_referer() ?: home_url();
                $url = add_query_arg([
                    'booking' => 'error',
                    'code' => 'license_limit',
                ], $referer);
                wp_redirect($url);
                exit;
            }
        }
    }

    /**
     * Automatic actions on status changes
     */
    public static function handle_status_automation(int $booking_id, string $old_status, string $new_status): void
    {
        // Send email when booking is confirmed
        if ($new_status === Status::CONFIRMED && $old_status === Status::PENDING) {
            do_action('mhm_rentiva_send_confirmation_email', $booking_id);
        }

        // Clear cache when booking is cancelled
        if ($new_status === Status::CANCELLED) {
            self::invalidate_availability_cache($booking_id);
        }

        // Log when booking is completed
        if ($new_status === Status::COMPLETED) {
            do_action('mhm_rentiva_booking_completed', $booking_id);
        }

        // Special hook for refund process
        if ($new_status === Status::REFUNDED) {
            do_action('mhm_rentiva_booking_refunded', $booking_id);
        }
    }
}
