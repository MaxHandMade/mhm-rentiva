<?php declare(strict_types=1);

namespace MHMRentiva\REST\Payments;

use MHMRentiva\Admin\Booking\Helpers\Util;
use MHMRentiva\Admin\Payment\Stripe\Client as StripeClient;
use MHMRentiva\Admin\Settings\Settings;
use MHMRentiva\REST\Payments\Helpers\Validation;
use MHMRentiva\REST\Payments\Helpers\ResponseHelper;
use WP_REST_Request;
use WP_REST_Response;

if (!defined('ABSPATH')) {
    exit;
}

final class CreateIntent
{
    public static function handle(WP_REST_Request $request): WP_REST_Response
    {
        $booking_id = (int) $request->get_param('booking_id');
        if ($booking_id <= 0) {
            return ResponseHelper::err('invalid_input', __('Invalid booking ID.', 'mhm-rentiva'), 400);
        }

        $post = get_post($booking_id);
        if (!$post || $post->post_type !== 'vehicle_booking') {
            return ResponseHelper::err('not_found', __('Booking not found.', 'mhm-rentiva'), 404);
        }

        // Check current status and payment
        $status = (string) get_post_meta($booking_id, '_mhm_status', true);
        $payStatus = (string) get_post_meta($booking_id, '_mhm_payment_status', true);
        if ($payStatus === 'paid' || $payStatus === 'refunded') {
            return ResponseHelper::err('already_paid', __('Booking already paid/refunded.', 'mhm-rentiva'), 400);
        }
        if (!in_array($status, ['pending','confirmed'], true)) {
            return ResponseHelper::err('invalid_state', __('Booking status is not payable.', 'mhm-rentiva'), 400);
        }

        // Amount/currency
        $total = get_post_meta($booking_id, '_mhm_total_price', true);
        $total = is_numeric($total) ? (float) $total : 0.0;
        if ($total <= 0) {
            // try compute
            $vehicle_id = (int) get_post_meta($booking_id, '_mhm_vehicle_id', true);
            $start = (int) get_post_meta($booking_id, '_mhm_start_ts', true);
            $end   = (int) get_post_meta($booking_id, '_mhm_end_ts', true);
            $days  = Util::rental_days($start, $end);
            $total = Util::total_price($vehicle_id, $days);
        }
        if ($total <= 0) {
            return ResponseHelper::err('invalid_amount', __('Total amount is zero.', 'mhm-rentiva'), 400);
        }

        $currency = is_callable([Settings::class, 'get']) ? (string) Settings::get('currency', 'TRY') : 'TRY';
        $amount_cents = (int) round($total * 100);

        $email = (string) get_post_meta($booking_id, '_mhm_contact_email', true);

        $res = StripeClient::create_payment_intent($booking_id, $amount_cents, $currency, $email);
        if (is_wp_error($res)) {
            return ResponseHelper::err('stripe_error', $res->get_error_message(), 500);
        }

        // Persist mapping
        update_post_meta($booking_id, '_mhm_stripe_payment_intent', (string) $res['id']);
        update_post_meta($booking_id, '_mhm_payment_amount', $amount_cents);
        update_post_meta($booking_id, '_mhm_payment_currency', strtoupper($currency));
        if (!get_post_meta($booking_id, '_mhm_payment_status', true)) {
            update_post_meta($booking_id, '_mhm_payment_status', 'unpaid');
        }

        return new WP_REST_Response([
            'ok'            => true,
            'booking_id'    => $booking_id,
            'payment_intent_id' => (string) $res['id'],
            'client_secret' => (string) $res['client_secret'],
            'amount'        => $amount_cents,
            'currency'      => strtoupper($currency),
        ], 200);
    }
}
