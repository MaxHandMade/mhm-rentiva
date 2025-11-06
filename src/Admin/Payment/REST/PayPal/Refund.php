<?php declare(strict_types=1);

namespace MHMRentiva\REST\PayPal;

use MHMRentiva\Admin\Payment\PayPal\Client as PayPalClient;
use MHMRentiva\Admin\PostTypes\Logs\Logger;
use MHMRentiva\REST\PayPal\Helpers\Auth;
use WP_REST_Request;
use WP_REST_Response;

if (!defined('ABSPATH')) {
    exit;
}

final class Refund
{
    /**
     * Refund operation
     */
    public static function handle(WP_REST_Request $req): WP_REST_Response
    {
        $booking_id = (int) ($req->get_param('booking_id') ?? 0);
        $amount = (int) ($req->get_param('amount') ?? 0);
        $reason = sanitize_text_field((string) ($req->get_param('reason') ?? ''));

        if ($booking_id <= 0 || $amount <= 0) {
            return self::err('invalid_params', __('Invalid parameters.', 'mhm-rentiva'), 400);
        }

        // Booking check
        $post = get_post($booking_id);
        if (!$post || $post->post_type !== 'vehicle_booking') {
            return self::err('invalid_booking', __('Booking not found.', 'mhm-rentiva'), 404);
        }

        // Payment status check
        $pay_status = get_post_meta($booking_id, '_mhm_payment_status', true);
        if ($pay_status !== 'paid') {
            return self::err('invalid_status', __('This booking cannot be refunded.', 'mhm-rentiva'), 400);
        }

        // PayPal payment ID check
        $capture_id = get_post_meta($booking_id, '_mhm_paypal_payment_id', true);
        if (empty($capture_id)) {
            return self::err('no_payment_id', __('PayPal payment ID not found.', 'mhm-rentiva'), 400);
        }

        // PayPal Client ile iade yap
        $client = new PayPalClient();
        $result = $client->refundPayment($capture_id, $amount);

        if (!$result['ok']) {
            return self::err('refund_error', $result['message'], 500);
        }

        // Update booking status
        update_post_meta($booking_id, '_mhm_payment_status', 'refunded');
        update_post_meta($booking_id, '_mhm_paypal_status', 'refunded');
        update_post_meta($booking_id, '_mhm_paypal_refund_id', $result['refund_id']);

        Logger::add([
            'gateway' => 'paypal',
            'action' => 'refund_completed',
            'status' => 'success',
            'booking_id' => $booking_id,
            'message' => __('PayPal refund completed', 'mhm-rentiva'),
            'context' => [
                'capture_id' => $capture_id,
                'refund_id' => $result['refund_id'],
                'amount' => $result['amount'],
                'reason' => $reason,
            ],
        ]);

        return new WP_REST_Response([
            'ok' => true,
            'refund_id' => $result['refund_id'],
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
