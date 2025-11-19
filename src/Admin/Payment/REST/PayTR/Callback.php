<?php declare(strict_types=1);

namespace MHMRentiva\REST\PayTR;

use MHMRentiva\Admin\Payment\PayTR\Config as PayTRConfig;
use MHMRentiva\Admin\Payment\PayTR\Client as PayTRClient;
use MHMRentiva\Admin\Booking\Core\Status;
use MHMRentiva\Admin\PostTypes\Logs\Logger;
use MHMRentiva\REST\PayTR\Helpers\BookingQuery;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;

if (!defined('ABSPATH')) {
    exit;
}

final class Callback
{
    public static function handle(WP_REST_Request $request): WP_REST_Response
    {
        $data = $request->get_body_params();
        $status         = (string) ($data['status'] ?? '');
        $hash           = (string) ($data['hash'] ?? '');
        $merchant_oid   = (string) ($data['merchant_oid'] ?? '');
        $total_amount   = (int) ($data['total_amount'] ?? 0);
        $payment_amount = (int) ($data['payment_amount'] ?? 0);
        $installment    = (int) ($data['installment_count'] ?? 0);
        $err_code       = sanitize_text_field((string) ($data['failed_reason_code'] ?? ''));
        $err_msg        = sanitize_text_field((string) ($data['failed_reason_msg'] ?? ''));

        $mkey  = PayTRConfig::merchantKey();
        $msalt = PayTRConfig::merchantSalt();
        if ($mkey === '' || $msalt === '') {
            return self::callback_plain('ERROR');
        }
        
        // Verify hash etc. (assuming PayTRClient::verifyCallback does this)
        $client = new PayTRClient();
        $verified = $client->verifyCallback($data);
        if (!$verified) {
            return self::callback_plain('ERROR');
        }

        // Find booking by OID
        $booking_id = BookingQuery::findBookingByOid($merchant_oid);

        if ($status === 'success' && $booking_id > 0) {
            update_post_meta($booking_id, '_mhm_payment_status', 'paid');
            update_post_meta($booking_id, '_mhm_payment_amount', (int) $payment_amount);
            update_post_meta($booking_id, '_mhm_payment_currency', 'TRY'); // PayTR returns TL, unify to TRY in our meta
            update_post_meta($booking_id, '_mhm_payment_gateway', 'paytr');
            $old = (string) get_post_meta($booking_id, '_mhm_status', true);
            if ($old !== 'confirmed') {
                Status::update_status($booking_id, 'confirmed', 0);
            }

            Logger::add([
                'gateway'      => 'paytr',
                'action'       => 'callback',
                'status'       => 'success',
                'booking_id'   => $booking_id,
                'amount_kurus' => (int) $payment_amount,
                'currency'     => 'TRY',
                'oid'          => $merchant_oid,
                /* translators: %d placeholder. */
                'message'      => sprintf(__('PayTR callback successful (installment: %d)', 'mhm-rentiva'), (int) $installment),
                'context'      => [
                    'raw'         => [
                        'total_amount'   => (int) $total_amount,
                        'payment_amount' => (int) $payment_amount,
                        'installment'    => (int) $installment,
                    ],
                ],
            ]);
        } else {
            Logger::add([
                'gateway'    => 'paytr',
                'action'     => 'callback',
                'status'     => 'error',
                'booking_id' => $booking_id ?: 0,
                'oid'        => $merchant_oid,
                'code'       => $err_code,
                'message'    => $err_msg !== '' ? $err_msg : __('PayTR callback failed', 'mhm-rentiva'),
                'context'    => [
                    'raw_status' => $status,
                ],
            ]);
        }

        return self::callback_plain('OK');
    }

    private static function callback_plain(string $text): WP_REST_Response
    {
        $res = new WP_REST_Response($text, 200);
        $res->header('Content-Type', 'text/plain; charset=UTF-8');
        return $res;
    }
}
