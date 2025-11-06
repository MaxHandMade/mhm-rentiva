<?php declare(strict_types=1);

namespace MHMRentiva\REST\PayPal;

use MHMRentiva\Admin\Payment\PayPal\Config as PayPalConfig;
use MHMRentiva\Admin\Payment\PayPal\Client as PayPalClient;
use MHMRentiva\Admin\Booking\Core\Status;
use MHMRentiva\Admin\PostTypes\Logs\Logger;
use MHMRentiva\REST\PayPal\Helpers\Auth;
use WP_REST_Request;
use WP_REST_Response;

if (!defined('ABSPATH')) {
    exit;
}

final class CapturePayment
{
    /**
     * Safe sanitize text field that handles null values
     */
    public static function sanitize_text_field_safe($value)
    {
        if ($value === null || $value === '') {
            return '';
        }
        return sanitize_text_field((string) $value);
    }

    /**
     * Capture payment
     */
    public static function handle(WP_REST_Request $req): WP_REST_Response
    {
        if (!PayPalConfig::enabled()) {
            return self::err('disabled', __('PayPal is disabled.', 'mhm-rentiva'), 400);
        }

        $order_id = self::sanitize_text_field_safe($req->get_param('order_id') ?? '');
        $booking_id = (int) ($req->get_param('booking_id') ?? 0);

        if (empty($order_id) || $booking_id <= 0) {
            return self::err('invalid_params', __('Invalid parameters.', 'mhm-rentiva'), 400);
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
        $saved_order_id = get_post_meta($booking_id, '_mhm_paypal_order_id', true);
        if ($saved_order_id !== $order_id) {
            return self::err('order_mismatch', __('Order ID mismatch.', 'mhm-rentiva'), 400);
        }

        // Capture with PayPal Client
        $client = new PayPalClient();
        $result = $client->captureOrder($order_id);

        if (!$result['ok']) {
            update_post_meta($booking_id, '_mhm_paypal_status', 'capture_failed');
            return self::err('capture_error', $result['message'], 500);
        }

        // Successful capture - update booking status
        update_post_meta($booking_id, '_mhm_payment_status', 'paid');
        update_post_meta($booking_id, '_mhm_payment_amount', $result['amount']['value'] ?? 0);
        update_post_meta($booking_id, '_mhm_payment_currency', $result['amount']['currency_code'] ?? PayPalConfig::currency());
        update_post_meta($booking_id, '_mhm_paypal_payment_id', $result['capture_id']);
        update_post_meta($booking_id, '_mhm_paypal_fee', $result['fee']);
        update_post_meta($booking_id, '_mhm_paypal_status', 'captured');

        // Confirm booking status
        $old_status = (string) get_post_meta($booking_id, '_mhm_status', true);
        if ($old_status !== 'confirmed') {
            Status::update_status($booking_id, 'confirmed', get_current_user_id());
        }

        Logger::add([
            'gateway' => 'paypal',
            'action' => 'payment_captured',
            'status' => 'success',
            'booking_id' => $booking_id,
            'message' => __('PayPal payment captured successfully', 'mhm-rentiva'),
            'context' => [
                'order_id' => $order_id,
                'capture_id' => $result['capture_id'],
                'amount' => $result['amount'],
                'fee' => $result['fee'],
            ],
        ]);

        return new WP_REST_Response([
            'ok' => true,
            'capture_id' => $result['capture_id'],
            'status' => $result['status'],
            'amount' => $result['amount'],
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
