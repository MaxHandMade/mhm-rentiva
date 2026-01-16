<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Transfer\Integration;

use MHMRentiva\Admin\Transfer\Engine\TransferSearchEngine;
use MHMRentiva\Admin\Payment\WooCommerce\WooCommerceBridge;
use MHMRentiva\Admin\Booking\Helpers\Util;

if (!defined('ABSPATH')) {
    exit;
}

final class TransferCartIntegration
{
    /**
     * Register hooks
     */
    public static function register(): void
    {
        add_action('wp_ajax_mhm_transfer_add_to_cart', [self::class, 'handle_add_to_cart_ajax']);
        add_action('wp_ajax_nopriv_mhm_transfer_add_to_cart', [self::class, 'handle_add_to_cart_ajax']);
    }

    /**
     * Handle Add to Cart AJAX
     */
    public static function handle_add_to_cart_ajax(): void
    {
        // 1. Validate Nonce (Optional but recommended, frontend needs to send it)
        // if (!check_ajax_referer('mhm_rentiva_nonce', 'security', false)) {
        //     wp_send_json_error(__('Security check failed', 'mhm-rentiva'));
        // }

        $vehicle_id = intval($_POST['vehicle_id']);
        $origin_id = intval($_POST['origin_id']);
        $destination_id = intval($_POST['destination_id']);
        $date = sanitize_text_field($_POST['date']);
        $time = sanitize_text_field($_POST['time']);
        $adults = intval($_POST['adults']);
        $children = intval($_POST['children']);
        $luggage_big = intval($_POST['luggage_big']);
        $luggage_small = intval($_POST['luggage_small']);
        $return_date = isset($_POST['return_date']) ? sanitize_text_field($_POST['return_date']) : '';
        $return_time = isset($_POST['return_time']) ? sanitize_text_field($_POST['return_time']) : '';

        // 2. Re-Validate and Calculate Price Structure
        // We act like a search to get the route and price verified
        $criteria = [
            'origin_id' => $origin_id,
            'destination_id' => $destination_id,
            'date' => $date,
            'time' => $time,
            'adults' => $adults,
            'children' => $children,
            'luggage_big' => $luggage_big,
            'luggage_small' => $luggage_small
        ];

        // We need to fetch the specific vehicle result from the engine logic
        // But the search engine returns ALL vehicles.
        // Optimization: We could add a method to SearchEngine to get single vehicle calculation or just reuse search filtering. 
        // For now, let's just reuse search and find our vehicle.

        $results = TransferSearchEngine::search($criteria);
        $selected_vehicle = null;

        foreach ($results as $vehicle) {
            if ($vehicle['id'] === $vehicle_id) {
                $selected_vehicle = $vehicle;
                break;
            }
        }

        if (!$selected_vehicle) {
            wp_send_json_error(__('Vehicle not available or price changed. Please search again.', 'mhm-rentiva'));
        }

        // 3. Financial Calculation (Deposit vs Full)
        $deposit_type = get_option('mhm_transfer_deposit_type', 'full_payment');
        $deposit_rate = intval(get_option('mhm_transfer_deposit_rate', '20'));
        $total_price = (float) $selected_vehicle['price'];

        $deposit_amount = 0.0;
        $remaining_amount = 0.0;
        $payment_type = 'full';

        if ($deposit_type === 'percentage') {
            $deposit_amount = ($total_price * $deposit_rate) / 100;
            $remaining_amount = $total_price - $deposit_amount;
            $payment_type = 'deposit';
        } else {
            // Full Payment
            $deposit_amount = $total_price;
            $remaining_amount = 0;
            $payment_type = 'full';
        }

        // 4. Prepare Booking Data
        $timezone = wp_timezone();
        try {
            // Need to set End Date/Time based on duration
            $start_datetime = new \DateTimeImmutable("$date $time", $timezone);
            $duration_min = $selected_vehicle['duration']; // from transfer search result
            $end_datetime = $start_datetime->modify("+{$duration_min} minutes");

            $dropoff_date = $end_datetime->format('Y-m-d');
            $dropoff_time = $end_datetime->format('H:i');
        } catch (\Exception $e) {
            wp_send_json_error(__('Invalid date time.', 'mhm-rentiva'));
        }

        $booking_data = [
            'vehicle_id' => $vehicle_id,
            'pickup_date' => $date,
            'pickup_time' => $time,
            'dropoff_date' => $dropoff_date,
            'dropoff_time' => $dropoff_time,
            'guests' => ($adults + $children),
            'customer_user_id' => get_current_user_id(),
            // Customer details will be filled by WooCommerce Checkout
            'customer_name' => '',
            'customer_first_name' => '',
            'customer_last_name' => '',
            'customer_email' => '',
            'customer_phone' => '',

            // Financials
            'total_price' => $total_price,
            'deposit_amount' => $deposit_amount,
            'remaining_amount' => $remaining_amount,
            'payment_type' => $payment_type, // 'deposit' or 'full'
            'payment_display' => ($payment_type === 'deposit') ?
                sprintf(__('Deposit: %s (%s%%)', 'mhm-rentiva'), wc_price($deposit_amount), $deposit_rate) :
                __('Full Payment', 'mhm-rentiva'),
            'pay_now_price' => $deposit_amount, // Important helper

            'rental_days' => 1, // Transfer is conceptually 1 unit
            'selected_addons' => [],
            'booking_type' => 'transfer', // Distinct from 'rental'

            // Extra Transfer Meta
            'transfer_origin_id' => $origin_id,
            'transfer_destination_id' => $destination_id,
            'transfer_adults' => $adults,
            'transfer_children' => $children,
            'transfer_luggage_big' => $luggage_big,
            'transfer_luggage_small' => $luggage_small,
            'transfer_distance_km' => $selected_vehicle['distance'],
            'transfer_duration_min' => $selected_vehicle['duration'],
        ];

        // 5. Add to Cart via Bridge
        if (WooCommerceBridge::add_booking_data_to_cart($booking_data, $deposit_amount)) {
            wp_send_json_success([
                'redirect_url' => function_exists('wc_get_cart_url') ? call_user_func('wc_get_cart_url') : '/',
                'message' => __('Transfer added to cart successfully.', 'mhm-rentiva')
            ]);
        } else {
            wp_send_json_error(__('Failed to add to cart.', 'mhm-rentiva'));
        }
    }
}
