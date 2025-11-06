<?php declare(strict_types=1);

namespace MHMRentiva\REST\Payments;

use MHMRentiva\Admin\Payment\Stripe\Client as StripeClient;
use MHMRentiva\REST\Payments\Helpers\ResponseHelper;
use WP_REST_Request;
use WP_REST_Response;

if (!defined('ABSPATH')) {
    exit;
}

final class Refund
{
    public static function permissionCheck(WP_REST_Request $request): bool
    {
        if (!is_user_logged_in()) return false;
        $booking_id = (int) $request->get_param('booking_id');
        if ($booking_id <= 0) return false;
        if (!current_user_can('edit_post', $booking_id)) return false;

        // Nonce kontrolü (wp_rest)
        $nonce = $request->get_header('X-WP-Nonce');
        return $nonce && wp_verify_nonce($nonce, 'wp_rest');
    }

    public static function handle(WP_REST_Request $request): WP_REST_Response
    {
        $booking_id = (int) $request->get_param('booking_id');
        $amount     = $request->get_param('amount');
        $amount     = is_numeric($amount) ? (int) $amount : null;

        $post = get_post($booking_id);
        if (!$post || $post->post_type !== 'vehicle_booking') {
            return ResponseHelper::err('not_found', __('Booking not found.', 'mhm-rentiva'), 404);
        }

        $status = (string) get_post_meta($booking_id, '_mhm_payment_status', true);
        if ($status !== 'paid') {
            return ResponseHelper::err('not_paid', __('Booking not paid.', 'mhm-rentiva'), 400);
        }

        $pi = (string) get_post_meta($booking_id, '_mhm_stripe_payment_intent', true);
        if ($pi === '') {
            return ResponseHelper::err('no_pi', __('Payment Intent not found in booking.', 'mhm-rentiva'), 400);
        }

        $res = StripeClient::refund_payment_intent($pi, $amount);
        if (is_wp_error($res)) {
            return ResponseHelper::err('stripe_error', $res->get_error_message(), 500);
        }

        update_post_meta($booking_id, '_mhm_refund_id', (string) $res['id']);
        update_post_meta($booking_id, '_mhm_refund_status', (string) $res['status']);
        if (isset($res['amount'])) {
            update_post_meta($booking_id, '_mhm_refunded_amount', (int) $res['amount']);
        }
        // Basit akış: full refund varsayımıyla payment_status'u 'refunded' yapalım
        if ($amount === null) {
            update_post_meta($booking_id, '_mhm_payment_status', 'refunded');
        }

        return new WP_REST_Response([
            'ok'      => true,
            'refund'  => $res,
            'message' => __('Refund requested successfully.', 'mhm-rentiva'),
        ], 200);
    }
}
