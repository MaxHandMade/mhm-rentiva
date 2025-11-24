<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Booking\Core;

use MHMRentiva\Admin\Booking\Helpers\Locker;
use MHMRentiva\Admin\Booking\Helpers\Util;
use MHMRentiva\Admin\Booking\Helpers\Cache;
use MHMRentiva\Admin\Core\Utilities\UXHelper;
use MHMRentiva\Admin\Vehicle\Deposit\DepositCalculator;

if (!defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\Core\Helpers\Sanitizer;

final class Handler
{
    public static function register(): void
    {
        add_action('admin_post_mhm_rentiva_booking', [self::class, 'handle']);
        add_action('admin_post_nopriv_mhm_rentiva_booking', [self::class, 'handle']);
    }

    public static function handle(): void
    {
        // Nonce verification
        if (!isset($_POST['mhm_rentiva_booking_nonce']) || 
            !wp_verify_nonce($_POST['mhm_rentiva_booking_nonce'], 'mhm_rentiva_booking_action')) {
            $error_message = UXHelper::get_user_friendly_error(
                UXHelper::ERROR_TYPE_PERMISSION,
                'access_denied',
                ['reason' => 'invalid_nonce']
            );
            self::redirect_error('invalid_nonce', $error_message);
            return;
        }

        // Get and sanitize fields
        $vehicle_id = isset($_POST['vehicle_id']) ? absint($_POST['vehicle_id']) : 0;
        $pickup_date = isset($_POST['pickup_date']) ? self::sanitize_text_field_safe($_POST['pickup_date']) : '';
        $pickup_time = isset($_POST['pickup_time']) ? self::sanitize_text_field_safe($_POST['pickup_time']) : '';
        $dropoff_date = isset($_POST['dropoff_date']) ? self::sanitize_text_field_safe($_POST['dropoff_date']) : '';
        $dropoff_time = isset($_POST['dropoff_time']) ? self::sanitize_text_field_safe($_POST['dropoff_time']) : '';
        $contact_name = isset($_POST['contact_name']) ? self::sanitize_text_field_safe($_POST['contact_name']) : '';
        $contact_email = isset($_POST['contact_email']) ? sanitize_email((string) ($_POST['contact_email'] ?: '')) : '';
        $contact_phone = isset($_POST['contact_phone']) ? self::sanitize_text_field_safe($_POST['contact_phone']) : '';
        $selected_addons = isset($_POST['selected_addons']) ? array_map('absint', (array) $_POST['selected_addons']) : [];
        
        // Deposit system fields
        $payment_type = isset($_POST['payment_type']) ? Sanitizer::text_field_safe($_POST['payment_type']) : 'deposit';
        $payment_method = isset($_POST['payment_method']) ? Sanitizer::text_field_safe($_POST['payment_method']) : 'online';

        // Basic validation
        if (!$vehicle_id || !$pickup_date || !$pickup_time || !$dropoff_date || !$dropoff_time || 
            !$contact_name || !$contact_email) {
            $error_message = UXHelper::get_user_friendly_error(
                UXHelper::ERROR_TYPE_VALIDATION,
                'required_field',
                ['field' => 'vehicle_id']
            );
            self::redirect_error('invalid_input', $error_message);
            return;
        }

        // Deposit system validation
        if (!DepositCalculator::validate_payment_type($payment_type)) {
            $error_message = UXHelper::get_user_friendly_error(
                UXHelper::ERROR_TYPE_VALIDATION,
                'invalid_payment_type',
                ['payment_type' => $payment_type]
            );
            self::redirect_error('invalid_payment_type', $error_message);
            return;
        }

        if (!DepositCalculator::validate_payment_method($payment_method)) {
            $error_message = UXHelper::get_user_friendly_error(
                UXHelper::ERROR_TYPE_VALIDATION,
                'invalid_payment_method',
                ['payment_method' => $payment_method]
            );
            self::redirect_error('invalid_payment_method', $error_message);
            return;
        }

        // Check vehicle existence
        if (get_post_type($vehicle_id) !== 'vehicle') {
            $error_message = UXHelper::get_user_friendly_error(
                UXHelper::ERROR_TYPE_VEHICLE,
                'vehicle_not_found',
                ['vehicle_id' => $vehicle_id]
            );
            self::redirect_error('vehicle_not_found', $error_message);
            return;
        }

        // License system check - with Hook
        do_action('mhm_rentiva_before_booking_create', $vehicle_id, [
            'pickup_date' => $pickup_date,
            'pickup_time' => $pickup_time,
            'dropoff_date' => $dropoff_date,
            'dropoff_time' => $dropoff_time,
            'contact_name' => $contact_name,
            'contact_email' => $contact_email,
            'contact_phone' => $contact_phone,
            'selected_addons' => $selected_addons,
        ]);

        try {
            // Atomic booking creation with database locking
            $booking_data = [
                'vehicle_id' => $vehicle_id,
                'pickup_date' => $pickup_date,
                'pickup_time' => $pickup_time,
                'dropoff_date' => $dropoff_date,
                'dropoff_time' => $dropoff_time,
                'contact_name' => $contact_name,
                'contact_email' => $contact_email,
                'contact_phone' => $contact_phone,
                'selected_addons' => $selected_addons,
                'payment_type' => $payment_type,
                'payment_method' => $payment_method,
                'client_ip' => self::get_client_ip(),
                'user_agent' => Sanitizer::text_field_safe($_SERVER['HTTP_USER_AGENT'] ?? ''),
            ];

            $booking_id = self::create_booking_atomic($booking_data);

            if (!$booking_id) {
                $error_message = UXHelper::get_user_friendly_error(
                    UXHelper::ERROR_TYPE_SYSTEM,
                    'database_error',
                    ['operation' => 'booking_creation']
                );
                self::redirect_error('creation_failed', $error_message);
                return;
            }

            // Success redirection
            if (class_exists('WooCommerce')) {
                // Get payment details from booking meta
                $deposit_amount = floatval(get_post_meta($booking_id, '_mhm_deposit_amount', true));
                $total_amount = floatval(get_post_meta($booking_id, '_mhm_total_price', true));
                $payment_type = get_post_meta($booking_id, '_mhm_payment_type', true);
                
                $amount_to_pay = $payment_type === 'deposit' ? $deposit_amount : $total_amount;
                
                if (\MHMRentiva\Admin\Payment\WooCommerce\WooCommerceBridge::add_booking_to_cart($booking_id, $amount_to_pay)) {
                    wp_redirect(wc_get_checkout_url());
                    exit;
                }
            }

            self::redirect_success($booking_id);

        } catch (\Exception $e) {
            // ✅ UX IMPROVEMENT - User-friendly error handling
            $error_message = UXHelper::get_user_friendly_error(
                UXHelper::ERROR_TYPE_BOOKING,
                'booking_failed',
                ['error_details' => $e->getMessage()]
            );
            
            
            // Redirect with user-friendly error
            self::redirect_error('booking_failed', $error_message);
        }
    }


    private static function get_client_ip(): string
    {
        $ip_keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? '';
    }

    private static function redirect_success(int $booking_id): void
    {
        $referer = wp_get_referer();
        if (!$referer) {
            $referer = home_url();
        }
        
        $url = add_query_arg([
            'booking' => 'ok',
            'bid' => $booking_id,
        ], $referer);
        
        wp_redirect($url);
        exit;
    }

    /**
     * Atomic booking creation with database locking
     */
    private static function create_booking_atomic(array $booking_data): ?int
    {
        return Locker::withLock($booking_data['vehicle_id'], function() use ($booking_data) {
            // Atomic availability check with lock held
            $availability_result = Util::check_availability_locked(
                $booking_data['vehicle_id'],
                $booking_data['pickup_date'],
                $booking_data['pickup_time'],
                $booking_data['dropoff_date'],
                $booking_data['dropoff_time']
            );

            if (!$availability_result['ok']) {
                // ✅ KULLANICI DENEYİMİ İYİLEŞTİRMESİ - User-friendly error message
                $error_message = UXHelper::get_user_friendly_error(
                    UXHelper::ERROR_TYPE_VEHICLE,
                    'vehicle_unavailable',
                    ['vehicle_id' => $booking_data['vehicle_id'], 'date' => $booking_data['pickup_date']]
                );
                throw new \Exception($error_message);
            }

            // Extract data
            $start_ts = $availability_result['start_ts'];
            $end_ts = $availability_result['end_ts'];
            $rental_days = $availability_result['days'];
            $total_price = $availability_result['total_price'];

            // Deposit calculation
            $deposit_result = DepositCalculator::calculate_booking_deposit(
                $booking_data['vehicle_id'],
                $rental_days,
                $booking_data['payment_type']
            );

            if (!$deposit_result['success']) {
                $error_message = UXHelper::get_user_friendly_error(
                    UXHelper::ERROR_TYPE_SYSTEM,
                    'deposit_calculation_failed',
                    ['error' => $deposit_result['error']]
                );
                throw new \Exception($error_message);
            }

            // Create booking post
            $vehicle_title = get_the_title($booking_data['vehicle_id']);
            $post_title = sprintf(
                /* translators: 1: Vehicle ID, 2: Pickup date, 3: Dropoff date */
                __('Vehicle #%1$d – %2$s → %3$s', 'mhm-rentiva'),
                $booking_data['vehicle_id'],
                $booking_data['pickup_date'],
                $booking_data['dropoff_date']
            );

            $post_data = [
                'post_title' => $post_title,
                'post_content' => sprintf(
                    /* translators: 1: Vehicle title, 2: Customer name, 3: Pickup date, 4: Dropoff date */
                    __('Booking Request: %1$s (%2$s) between %3$s - %4$s dates.', 'mhm-rentiva'),
                    $vehicle_title,
                    $booking_data['contact_name'],
                    $booking_data['pickup_date'],
                    $booking_data['dropoff_date']
                ),
                'post_status' => 'publish',
                'post_type' => 'vehicle_booking',
                'post_author' => 1, // Admin user
            ];

            $booking_id = wp_insert_post($post_data);

            if (is_wp_error($booking_id) || !$booking_id) {
                // ✅ KULLANICI DENEYİMİ İYİLEŞTİRMESİ - User-friendly error message
                $error_message = UXHelper::get_user_friendly_error(
                    UXHelper::ERROR_TYPE_SYSTEM,
                    'database_error',
                    ['operation' => 'booking_creation']
                );
                throw new \Exception($error_message);
            }

            // Save meta fields
            $meta_fields = [
                '_mhm_vehicle_id' => $booking_data['vehicle_id'],
                '_mhm_start_ts' => $start_ts,
                '_mhm_end_ts' => $end_ts,
                '_mhm_pickup_date' => $booking_data['pickup_date'],
                '_mhm_pickup_time' => $booking_data['pickup_time'],
                '_mhm_dropoff_date' => $booking_data['dropoff_date'],
                '_mhm_dropoff_time' => $booking_data['dropoff_time'],
                '_mhm_contact_name' => $booking_data['contact_name'],
                '_mhm_contact_email' => $booking_data['contact_email'],
                '_mhm_contact_phone' => $booking_data['contact_phone'],
                '_mhm_rental_days' => $rental_days,
                '_mhm_total_price' => $total_price,
                '_mhm_status' => 'pending',
                '_mhm_client_ip' => $booking_data['client_ip'],
                '_mhm_user_agent' => $booking_data['user_agent'],
                
                // Deposit system meta fields
                '_mhm_payment_type' => $booking_data['payment_type'],
                '_mhm_payment_method' => $booking_data['payment_method'],
                '_mhm_deposit_amount' => $deposit_result['deposit_amount'],
                '_mhm_remaining_amount' => $deposit_result['remaining_amount'],
                '_mhm_deposit_type' => $deposit_result['deposit_type'],
                '_mhm_payment_display' => $deposit_result['payment_display'],
                
                // Cancellation policy (24 hours)
                '_mhm_cancellation_policy' => '24_hours',
                '_mhm_cancellation_deadline' => date('Y-m-d H:i:s', strtotime('+24 hours')),
                
                // 30 minutes waiting time for offline payment
                '_mhm_payment_deadline' => $booking_data['payment_method'] === 'offline' ? 
                    date('Y-m-d H:i:s', strtotime('+30 minutes')) : null,
            ];

            foreach ($meta_fields as $key => $value) {
                update_post_meta($booking_id, $key, $value);
            }

            // ✅ CACHE OPTIMIZATION - Centralized cache clearing
            // Invalidate availability cache for this vehicle
            Cache::invalidateVehicle($booking_data['vehicle_id']);
            
            // Clear related caches
            \MHMRentiva\Admin\Core\Utilities\CacheManager::clear_booking_cache($booking_id);

            // Trigger booking created action
            do_action('mhm_rentiva_booking_created', $booking_id);

            return $booking_id;
        });
    }

    private static function redirect_error(string $code, string $message = ''): void
    {
        $referer = wp_get_referer();
        if (!$referer) {
            $referer = home_url();
        }

        $args = [
            'booking' => 'error',
            'code' => $code,
        ];
        
        // ✅ UX IMPROVEMENT - Add error message to URL
        if (!empty($message)) {
            $args['message'] = urlencode($message);
        }

        $url = add_query_arg($args, $referer);

        wp_redirect($url);
        exit;
    }
}
