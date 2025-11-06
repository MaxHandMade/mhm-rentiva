<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Payment\Gateways\PayTR\API;

use MHMRentiva\Admin\Payment\Gateways\PayTR\Config;
use MHMRentiva\Admin\PostTypes\Logs\Logger;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

final class Token
{
    private const API_URL = 'https://www.paytr.com/odeme/api/get-token';

    /**
     * PayTR token oluşturur
     */
    public static function createToken(array $payload): array|WP_Error
    {
        // Gerekli alanları kontrol et
        $requiredFields = [
            'merchant_id', 'merchant_key', 'merchant_salt', 'user_ip', 
            'merchant_oid', 'email', 'payment_amount', 'user_basket', 
            'no_installment', 'max_installment', 'currency', 'test_mode'
        ];

        foreach ($requiredFields as $field) {
            if (!isset($payload[$field]) || $payload[$field] === '') {
                return new WP_Error('paytr_missing', sprintf('Eksik alan: %s', $field));
            }
        }

        // Hash oluştur (PayTR dokümantasyonuna göre)
        $hashStr = $payload['merchant_id']
            . $payload['user_ip']
            . $payload['merchant_oid']
            . $payload['email']
            . $payload['payment_amount']
            . $payload['user_basket']
            . $payload['no_installment']
            . $payload['max_installment']
            . $payload['currency']
            . $payload['test_mode'];

        $paytrToken = base64_encode(hash_hmac('sha256', $hashStr . $payload['merchant_salt'], $payload['merchant_key'], true));
        
        // Request body hazırla
        $body = $payload;
        $body['paytr_token'] = $paytrToken;
        unset($body['merchant_key'], $body['merchant_salt']);

        // API'ye istek gönder
        $response = wp_remote_post(self::API_URL, [
            'timeout' => 20,
            'body' => $body,
        ]);

        if (is_wp_error($response)) {
            Logger::error('PayTR token oluşturma hatası: ' . $response->get_error_message());
            return $response;
        }

        $raw = wp_remote_retrieve_body($response);
        $json = json_decode((string) $raw, true);

        if (is_array($json) && ($json['status'] ?? '') === 'success' && !empty($json['token'])) {
            Logger::info('PayTR token oluşturuldu: ' . $json['token']);
            return ['token' => (string) $json['token']];
        }

        $reason = is_array($json) ? (string) ($json['reason'] ?? 'paytr_hatası') : 'paytr_hatası';
        Logger::error('PayTR token hatası: ' . $reason);
        return new WP_Error('paytr_hatası', $reason, ['body' => $raw]);
    }

    /**
     * PayTR token için payload hazırlar
     */
    public static function preparePayload(array $bookingData): array
    {
        $merchantId = Config::merchantId();
        $merchantKey = Config::merchantKey();
        $merchantSalt = Config::merchantSalt();

        return [
            'merchant_id' => $merchantId,
            'merchant_key' => $merchantKey,
            'merchant_salt' => $merchantSalt,
            'user_ip' => self::getUserIp(),
            'merchant_oid' => $bookingData['merchant_oid'] ?? '',
            'email' => $bookingData['email'] ?? '',
            'payment_amount' => $bookingData['payment_amount'] ?? '',
            'user_basket' => $bookingData['user_basket'] ?? '',
            'no_installment' => Config::noInstallment() ? '1' : '0',
            'max_installment' => (string) Config::maxInstallment(),
            'currency' => 'TL',
            'test_mode' => Config::testMode() ? '1' : '0',
        ];
    }

    /**
     * Kullanıcı IP adresini alır
     */
    private static function getUserIp(): string
    {
        $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    /**
     * Token geçerliliğini kontrol eder
     */
    public static function validateToken(string $token): bool
    {
        if (empty($token)) {
            return false;
        }

        // PayTR token format kontrolü (örnek: 32 karakter hex)
        return strlen($token) >= 32 && ctype_alnum($token);
    }
}
