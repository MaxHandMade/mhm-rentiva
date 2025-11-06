<?php declare(strict_types=1);

namespace MHMRentiva\REST\PayTR;

use MHMRentiva\Admin\Settings\Settings;
use MHMRentiva\Admin\Payment\PayTR\Config as PayTRConfig;
use MHMRentiva\Admin\Payment\PayTR\Client as PayTRClient;
use MHMRentiva\Admin\Booking\Helpers\Util;
use MHMRentiva\Admin\PostTypes\Logs\Logger;
use MHMRentiva\REST\PayTR\Helpers\Auth;
use MHMRentiva\REST\PayTR\Helpers\RateLimit;
use MHMRentiva\REST\PayTR\Helpers\Validation;
use WP_REST_Request;
use WP_REST_Response;

if (!defined('ABSPATH')) {
    exit;
}

final class CreateToken
{
    public static function handle(WP_REST_Request $request): WP_REST_Response
    {
        if (!PayTRConfig::enabled()) {
            return self::err('disabled', __('PayTR disabled.', 'mhm-rentiva'), 400);
        }
        
        $mid = PayTRConfig::merchantId();
        $mkey = PayTRConfig::merchantKey();
        $msalt = PayTRConfig::merchantSalt();
        if ($mid === '' || $mkey === '' || $msalt === '') {
            return self::err('config', __('PayTR credentials missing.', 'mhm-rentiva'), 400);
        }

        $booking_id = (int) ($request->get_param('booking_id') ?? 0);
        if ($booking_id <= 0) {
            return self::err('invalid_booking', __('Invalid booking.', 'mhm-rentiva'), 400);
        }
        
        // Verify auth (REST nonce or booking-bound nonce)
        $auth = Auth::verifyAuth($request, $booking_id);
        if (is_wp_error($auth)) {
            return new WP_REST_Response([
                'ok'      => false,
                'code'    => $auth->get_error_code(),
                'message' => $auth->get_error_message(),
            ], (int) $auth->get_error_data()['status'] ?: 403);
        }
        
        // Validate booking state
        if (!Validation::validateBookingForToken($booking_id)) {
            return self::err('invalid_state', __('This booking is not eligible for payment token.', 'mhm-rentiva'), 400);
        }

        // Amount and currency
        $total = get_post_meta($booking_id, '_mhm_total_price', true);
        $total = is_numeric($total) ? (float) $total : 0.0;
        if ($total <= 0) {
            $vid   = (int) get_post_meta($booking_id, '_mhm_vehicle_id', true);
            $start = (int) get_post_meta($booking_id, '_mhm_start_ts', true);
            $end   = (int) get_post_meta($booking_id, '_mhm_end_ts', true);
            $days  = Util::rental_days($start, $end);
            $total = Util::total_price($vid, $days);
        }
        if ($total <= 0) {
            return self::err('invalid_amount', __('Total amount is zero.', 'mhm-rentiva'), 400);
        }
        
        $currency = is_callable([Settings::class, 'get']) ? (string) Settings::get('currency', 'TRY') : 'TRY';
        // PayTR expects TL code as "TL" (not "TRY")
        $currency = strtoupper($currency);
        if ($currency === 'TRY') {
            $currency = 'TL';
        }
        $amount_kurus = (int) round($total * 100);

        $ip = Util::client_ip();
        if (!$ip) {
            $ip = Validation::clientIp();
        }

        // Rate limit checks
        $rl = RateLimit::checkRateLimit($ip, $booking_id);
        if (is_wp_error($rl)) {
            return new WP_REST_Response([
                'ok'      => false,
                'code'    => $rl->get_error_code(),
                'message' => $rl->get_error_message(),
            ], (int) $rl->get_error_data()['status'] ?: 429);
        }

        // Customer
        $email = (string) get_post_meta($booking_id, '_mhm_contact_email', true);
        $name  = (string) get_post_meta($booking_id, '_mhm_contact_name', true);

        // Order id
        $oid = (string) get_post_meta($booking_id, '_mhm_paytr_merchant_oid', true);
        if ($oid === '') {
            $oid = 'RV' . $booking_id . '-' . wp_generate_password(8, false, false);
            update_post_meta($booking_id, '_mhm_paytr_merchant_oid', $oid);
        }

        // Basket (one line)
        $basket = [
            [
                'Vehicle Rental #' . $booking_id,
                number_format($total, 2, '.', ''), // unit price
                1,
            ],
        ];
        $user_basket = base64_encode(json_encode($basket, JSON_UNESCAPED_UNICODE));

        // Config options
        $noInst  = PayTRConfig::noInstallment();
        $maxInst = PayTRConfig::maxInstallment();
        if ($noInst) {
            $maxInst = 1;
        }
        $non3d   = PayTRConfig::non3d() ? 1 : 0;
        $timeout = PayTRConfig::timeoutLimit();
        $debugOn = PayTRConfig::debugOn() ? 1 : 0;

        $payload = [
            'merchant_id'     => $mid,
            'merchant_key'    => $mkey,
            'merchant_salt'   => $msalt,
            'merchant_oid'    => $oid,
            'email'           => $email !== '' ? $email : 'guest@example.com',
            'user_name'       => $name !== '' ? $name : 'Guest',
            'user_ip'         => $ip,
            'payment_amount'  => $amount_kurus,
            'currency'        => $currency,
            'test_mode'       => PayTRConfig::testMode() ? 1 : 0,
            'no_installment'  => $noInst ? 1 : 0,
            'max_installment' => max(1, (int) $maxInst),
            'user_basket'     => $user_basket,
            'debug_on'        => $debugOn,
            'timeout_limit'   => $timeout,
            'non_3d'          => $non3d,
            // Optional redirect urls (PayTR uses notify_url from panel by default)
            'merchant_ok_url'   => home_url('/'),
            'merchant_fail_url' => home_url('/'),
        ];

        // Prepare safe request context (no secrets)
        $reqContext = $payload;
        unset($reqContext['merchant_key'], $reqContext['merchant_salt']);

        $client = new PayTRClient();
        $res = $client->createToken($payload);

        if (!$res['ok']) {
            Logger::add([
                'gateway'       => 'paytr',
                'action'        => 'token_create',
                'status'        => 'error',
                'booking_id'    => $booking_id,
                'amount_kurus'  => $amount_kurus,
                'currency'      => $currency,
                'oid'           => $oid,
                'code'          => (string) ($res['code'] ?? ''),
                'message'       => (string) ($res['message'] ?? __('PayTR error', 'mhm-rentiva')),
                'context'       => [
                    'request'  => $reqContext,
                    'response' => $res,
                ],
            ]);
            return self::err('paytr_error', $res['message'] ?? __('PayTR error', 'mhm-rentiva'), 500);
        }

        // Save OID and gateway
        update_post_meta($booking_id, '_mhm_paytr_merchant_oid', (string) $oid);
        update_post_meta($booking_id, '_mhm_payment_gateway', 'paytr');

        Logger::add([
            'gateway'       => 'paytr',
            'action'        => 'token_create',
            'status'        => 'success',
            'booking_id'    => $booking_id,
            'amount_kurus'  => $amount_kurus,
            'currency'      => $currency,
            'oid'           => $oid,
            'message'       => __('PayTR token created', 'mhm-rentiva'),
            'context'       => [
                'request'    => $reqContext,
                'iframe_url' => 'https://www.paytr.com/odeme/guvenli/' . (string) $res['token'],
            ],
        ]);

        return new WP_REST_Response([
            'ok'         => true,
            'booking_id' => $booking_id,
            'token'      => (string) $res['token'],
            'iframe_url' => 'https://www.paytr.com/odeme/guvenli/' . (string) $res['token'],
        ], 200);
    }

    private static function err(string $code, string $msg, int $status): WP_REST_Response
    {
        return new WP_REST_Response(['ok' => false, 'code' => $code, 'message' => $msg], $status);
    }
}
