<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Payment\Gateways\PayTR\API;

use MHMRentiva\Admin\Payment\Gateways\PayTR\Config;
use MHMRentiva\Admin\PostTypes\Logs\Logger;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

final class Inquiry
{
    private const INQUIRY_ENDPOINT = 'https://www.paytr.com/odeme/durumbilgisi';

    /**
     * PayTR ödeme durumu sorgular
     */
    public static function inquire(array $args): array
    {
        $merchantId = Config::merchantId();
        $merchantKey = Config::merchantKey();
        $merchantSalt = Config::merchantSalt();

        if ($merchantId === '' || $merchantKey === '' || $merchantSalt === '') {
            return ['ok' => false, 'message' => 'PayTR kimlik bilgileri eksik'];
        }

        $merchantOid = (string) ($args['merchant_oid'] ?? '');
        if ($merchantOid === '') {
            return ['ok' => false, 'message' => 'Merchant OID eksik'];
        }

        // Hash oluştur
        $hashStr = $merchantId . $merchantOid . $merchantSalt;
        $hash = base64_encode(hash_hmac('sha256', $hashStr, $merchantKey, true));

        $body = [
            'merchant_id' => $merchantId,
            'merchant_oid' => $merchantOid,
            'paytr_token' => $hash,
        ];

        $response = wp_remote_post(self::INQUIRY_ENDPOINT, [
            'timeout' => 30,
            'body' => $body,
        ]);

        if (is_wp_error($response)) {
            Logger::error('PayTR sorgu hatası: ' . $response->get_error_message());
            return ['ok' => false, 'message' => $response->get_error_message()];
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $raw = (string) wp_remote_retrieve_body($response);
        $json = json_decode($raw, true);

        if (!is_array($json)) {
            Logger::error('PayTR geçersiz yanıt: ' . $raw);
            return ['ok' => false, 'message' => __('Invalid PayTR response', 'mhm-rentiva'), 'code' => (string) $code];
        }

        if (isset($json['status']) && $json['status'] === 'success') {
            // Ödeme durumunu normalize et
            $paid = isset($json['payment_status']) ? ((string) $json['payment_status'] === 'paid') : false;
            $amount = isset($json['payment_amount']) ? (int) $json['payment_amount'] : 0;
            $installment = isset($json['installment_count']) ? (int) $json['installment_count'] : 0;

            Logger::info('PayTR ödeme durumu sorgulandı: ' . $merchantOid . ' - ' . ($paid ? 'Ödendi' : 'Ödenmedi'));

            return [
                'ok' => true,
                'status' => (string) ($json['payment_status'] ?? ''),
                'paid' => (bool) $paid,
                'payment_amount' => $amount,
                'installment' => $installment,
                'raw_response' => $json,
            ];
        }

        $message = (string) ($json['err_msg'] ?? 'PayTR sorgu hatası');
        $code = (string) ($json['err_no'] ?? '');
        
        Logger::error('PayTR sorgu hatası: ' . $message . ' (Kod: ' . $code . ')');
        
        return ['ok' => false, 'message' => $message, 'code' => $code];
    }

    /**
     * Ödeme durumunu kontrol eder
     */
    public static function checkPaymentStatus(string $merchantOid): array
    {
        return self::inquire(['merchant_oid' => $merchantOid]);
    }

    /**
     * Ödeme başarılı mı kontrol eder
     */
    public static function isPaymentSuccessful(array $inquiryResult): bool
    {
        return $inquiryResult['ok'] === true && $inquiryResult['paid'] === true;
    }

    /**
     * Ödeme tutarını alır
     */
    public static function getPaymentAmount(array $inquiryResult): int
    {
        return $inquiryResult['payment_amount'] ?? 0;
    }

    /**
     * Taksit sayısını alır
     */
    public static function getInstallmentCount(array $inquiryResult): int
    {
        return $inquiryResult['installment'] ?? 0;
    }
}
