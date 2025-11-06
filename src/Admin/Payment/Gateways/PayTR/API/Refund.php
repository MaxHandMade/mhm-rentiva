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
     * PayTR iade işlemi yapar
     */
    public static function processRefund(array $args): array
    {
        $merchantId = Config::merchantId();
        $merchantKey = Config::merchantKey();
        $merchantSalt = Config::merchantSalt();

        if ($merchantId === '' || $merchantKey === '' || $merchantSalt === '') {
            return ['ok' => false, 'message' => 'PayTR kimlik bilgileri eksik'];
        }

        $merchantOid = (string) ($args['merchant_oid'] ?? '');
        $amount = (int) ($args['amount_kurus'] ?? 0);
        $reason = isset($args['reason']) ? (string) $args['reason'] : '';

        if ($merchantOid === '' || $amount <= 0) {
            return ['ok' => false, 'message' => __('Invalid refund input', 'mhm-rentiva')];
        }

        // Hash oluştur (PayTR dokümantasyonuna göre)
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
            Logger::error('PayTR iade hatası: ' . $response->get_error_message());
            return ['ok' => false, 'message' => $response->get_error_message()];
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $raw = (string) wp_remote_retrieve_body($response);
        $json = json_decode($raw, true);

        if (!is_array($json)) {
            Logger::error('PayTR iade geçersiz yanıt: ' . $raw);
            return ['ok' => false, 'message' => __('Invalid PayTR response', 'mhm-rentiva'), 'code' => (string) $code];
        }

        if (isset($json['status']) && $json['status'] === 'success') {
            $refundId = (string) ($json['refund_id'] ?? ($json['merchant_oid'] ?? $merchantOid));
            
            Logger::info('PayTR iade başarılı: ' . $merchantOid . ' - Tutar: ' . $amount . ' kuruş');
            
            return [
                'ok' => true,
                'id' => $refundId,
                'amount' => $amount,
                'merchant_oid' => $merchantOid,
                'raw_response' => $json,
            ];
        }

        $message = (string) ($json['err_msg'] ?? 'PayTR iade hatası');
        $code = (string) ($json['err_no'] ?? '');
        
        Logger::error('PayTR iade hatası: ' . $message . ' (Kod: ' . $code . ')');
        
        return ['ok' => false, 'message' => $message, 'code' => $code];
    }

    /**
     * Tam iade yapar
     */
    public static function fullRefund(string $merchantOid, string $reason = ''): array
    {
        // Tam iade için önce ödeme tutarını sorgula
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
     * Kısmi iade yapar
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
     * İade durumunu kontrol eder
     */
    public static function isRefundSuccessful(array $refundResult): bool
    {
        return $refundResult['ok'] === true && !empty($refundResult['id']);
    }

    /**
     * İade tutarını alır
     */
    public static function getRefundAmount(array $refundResult): int
    {
        return $refundResult['amount'] ?? 0;
    }

    /**
     * İade ID'sini alır
     */
    public static function getRefundId(array $refundResult): string
    {
        return $refundResult['id'] ?? '';
    }
}
