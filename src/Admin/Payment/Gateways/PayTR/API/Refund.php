<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Payment\Gateways\PayTR\API;

use MHMRentiva\Admin\Payment\Gateways\PayTR\Config;
use MHMRentiva\Admin\PostTypes\Logs\Logger;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

final class Refund
{
    private const REFUND_ENDPOINT = 'https://www.paytr.com/odeme/iade';

    /**
     * Execute a PayTR refund request.
     */
    public static function processRefund(array $args): array
    {
        $merchantId = Config::merchantId();
        $merchantKey = Config::merchantKey();
        $merchantSalt = Config::merchantSalt();

        if ($merchantId === '' || $merchantKey === '' || $merchantSalt === '') {
            return ['ok' => false, 'message' => __('PayTR credentials are missing.', 'mhm-rentiva')];
        }

        $merchantOid = (string) ($args['merchant_oid'] ?? '');
        $amount = (int) ($args['amount_kurus'] ?? 0);
        $reason = isset($args['reason']) ? (string) $args['reason'] : '';

        if ($merchantOid === '' || $amount <= 0) {
            return ['ok' => false, 'message' => __('Invalid refund input', 'mhm-rentiva')];
        }

        // Build signature hash (per PayTR documentation)
        $hashStr = $merchantId . $merchantOid . $amount . $merchantSalt;
        $hash = base64_encode(hash_hmac('sha256', $hashStr, $merchantKey, true));

        $body = [
            'merchant_id' => $merchantId,
            'merchant_oid' => $merchantOid,
            'return_amount' => $amount,
            'return_hash' => $hash,
        ];

        if ($reason !== '') {
            $body['return_reason'] = $reason;
        }

        $response = wp_remote_post(self::REFUND_ENDPOINT, [
            'timeout' => 30,
            'body' => $body,
        ]);

        if (is_wp_error($response)) {
            Logger::error('PayTR refund error: ' . $response->get_error_message());
            return ['ok' => false, 'message' => $response->get_error_message()];
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $raw = (string) wp_remote_retrieve_body($response);
        $json = json_decode($raw, true);

        if (!is_array($json)) {
            Logger::error('Invalid PayTR refund response: ' . $raw);
            return ['ok' => false, 'message' => __('Invalid PayTR response.', 'mhm-rentiva'), 'code' => (string) $code];
        }

        if (isset($json['status']) && $json['status'] === 'success') {
            $refundId = (string) ($json['refund_id'] ?? ($json['merchant_oid'] ?? $merchantOid));
            
            Logger::info('PayTR refund successful: ' . $merchantOid . ' - Amount: ' . $amount . ' kuruş');
            
            return [
                'ok' => true,
                'id' => $refundId,
                'amount' => $amount,
                'merchant_oid' => $merchantOid,
                'raw_response' => $json,
            ];
        }

        $message = (string) ($json['err_msg'] ?? __('PayTR refund error.', 'mhm-rentiva'));
        $code = (string) ($json['err_no'] ?? '');
        
        Logger::error('PayTR refund error: ' . $message . ' (Code: ' . $code . ')');
        
        return ['ok' => false, 'message' => $message, 'code' => $code];
    }

    /**
     * Perform a full refund.
     */
    public static function fullRefund(string $merchantOid, string $reason = ''): array
    {
        // Query payment amount before attempting a full refund
        $inquiry = Inquiry::checkPaymentStatus($merchantOid);
        
        if (!$inquiry['ok']) {
            return ['ok' => false, 'message' => __('Payment status could not be queried:', 'mhm-rentiva') . ' ' . $inquiry['message']];
        }

        $amount = $inquiry['payment_amount'] ?? 0;
        if ($amount <= 0) {
            return ['ok' => false, 'message' => __('No amount to refund', 'mhm-rentiva')];
        }

        return self::processRefund([
            'merchant_oid' => $merchantOid,
            'amount_kurus' => $amount,
            'reason' => $reason,
        ]);
    }

    /**
     * Perform a partial refund.
     */
    public static function partialRefund(string $merchantOid, int $amount, string $reason = ''): array
    {
        if ($amount <= 0) {
            return ['ok' => false, 'message' => __('Invalid refund amount', 'mhm-rentiva')];
        }

        return self::processRefund([
            'merchant_oid' => $merchantOid,
            'amount_kurus' => $amount,
            'reason' => $reason,
        ]);
    }

    /**
     * Determine if refund succeeded.
     */
    public static function isRefundSuccessful(array $refundResult): bool
    {
        return $refundResult['ok'] === true && !empty($refundResult['id']);
    }

    /**
     * Retrieve refunded amount (in kuruş).
     */
    public static function getRefundAmount(array $refundResult): int
    {
        return $refundResult['amount'] ?? 0;
    }

    /**
     * Retrieve refund identifier.
     */
    public static function getRefundId(array $refundResult): string
    {
        return $refundResult['id'] ?? '';
    }
}
