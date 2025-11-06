<?php declare(strict_types=1);

namespace MHMRentiva\REST\PayPal;

use MHMRentiva\Admin\Payment\PayPal\Config as PayPalConfig;
use MHMRentiva\Admin\Payment\PayPal\Client as PayPalClient;
use MHMRentiva\Admin\Booking\Helpers\Util;
use MHMRentiva\REST\PayPal\Helpers\Auth;
use MHMRentiva\REST\PayPal\Helpers\RateLimit;
use MHMRentiva\REST\PayPal\Helpers\Validation;
use WP_REST_Request;
use WP_REST_Response;

if (!defined('ABSPATH')) {
    exit;
}

final class CreateOrder
{
    /**
     * Create PayPal order
     */
    public static function handle(WP_REST_Request $req): WP_REST_Response
    {
        if (!PayPalConfig::enabled()) {
            return self::err('disabled', __('PayPal is disabled.', 'mhm-rentiva'), 400);
        }

        $booking_id = (int) ($req->get_param('booking_id') ?? 0);
        if ($booking_id <= 0) {
            return self::err('invalid_booking', __('Invalid booking.', 'mhm-rentiva'), 400);
        }

        // Authorization check
        $auth = Auth::verifyAuth($req, $booking_id);
        if (is_wp_error($auth)) {
            return new WP_REST_Response([
                'ok' => false,
                'code' => $auth->get_error_code(),
                'message' => $auth->get_error_message(),
            ], (int) $auth->get_error_data()['status'] ?: 403);
        }

        // Booking validation
        if (!Validation::validateBookingForPayment($booking_id)) {
            return self::err('invalid_state', __('This booking is not eligible for payment.', 'mhm-rentiva'), 400);
        }

        // Rate limiting
        $ip = Validation::getClientIp();
        $rl = RateLimit::checkRateLimit($ip, $booking_id);
        if (is_wp_error($rl)) {
            return new WP_REST_Response([
                'ok' => false,
                'code' => $rl->get_error_code(),
                'message' => $rl->get_error_message(),
            ], (int) $rl->get_error_data()['status'] ?: 429);
        }

        // Calculate amount and currency
        $total = get_post_meta($booking_id, '_mhm_total_price', true);
        $total = is_numeric($total) ? (float) $total : 0.0;

        if ($total <= 0) {
            $vid = (int) get_post_meta($booking_id, '_mhm_vehicle_id', true);
            $start = (int) get_post_meta($booking_id, '_mhm_start_ts', true);
            $end = (int) get_post_meta($booking_id, '_mhm_end_ts', true);
            $days = Util::rental_days($start, $end);
            $total = Util::total_price($vid, $days);
        }

        if ($total <= 0) {
            return self::err('invalid_amount', __('Invalid amount.', 'mhm-rentiva'), 400);
        }

        $currency = PayPalConfig::currency();
        $amount_kurus = (int) round($total * 100);

        // Create order with PayPal Client
        $client = new PayPalClient();
        $result = $client->createOrder($booking_id, $amount_kurus, $currency);

        if (!$result['ok']) {
            return self::err('paypal_error', $result['message'], 500);
        }

        // Save order ID
        update_post_meta($booking_id, '_mhm_paypal_order_id', $result['order_id']);
        update_post_meta($booking_id, '_mhm_payment_gateway', 'paypal');
        update_post_meta($booking_id, '_mhm_paypal_status', 'created');

        return new WP_REST_Response([
            'ok' => true,
            'order_id' => $result['order_id'],
            'status' => $result['status'],
            'links' => $result['links'],
        ], 200);
    }

    /**
     * Error response
     */
    private static function err(string $code, string $message, int $status = 400): WP_REST_Response
    {
        return new WP_REST_Response([
            'ok' => false,
            'code' => $code,
            'message' => $message,
        ], $status);
    }
}
