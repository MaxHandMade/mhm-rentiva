<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Payment\Gateways\PayPal\API;

use MHMRentiva\Admin\Payment\Gateways\PayPal\Config;
use MHMRentiva\Admin\PostTypes\Logs\Logger;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

final class Auth
{
    private const TOKEN_ENDPOINT = '/v1/oauth2/token';

    /**
     * Access token alır ve cache'ler
     */
    public static function getAccessToken(): string
    {
        $cacheKey = Config::getAccessTokenCacheKey();
        $cached = get_transient($cacheKey);

        if ($cached !== false && is_array($cached) && isset($cached['token'])) {
            // Token hala geçerli mi kontrol et
            $expiresIn = $cached['expires_in'] ?? 0;
            $createdAt = $cached['created_at'] ?? 0;
            
            if (time() < ($createdAt + $expiresIn - 60)) { // 60 saniye buffer
                return $cached['token'];
            }
        }

        // Yeni token al
        $response = self::requestNewToken();
        
        if (is_wp_error($response)) {
            Logger::error('PayPal token alınamadı: ' . $response->get_error_message());
            return '';
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!isset($data['access_token'])) {
            Logger::error('PayPal token response geçersiz: ' . $body);
            return '';
        }

        // Token'ı cache'le
        $cacheData = [
            'token' => $data['access_token'],
            'expires_in' => $data['expires_in'] ?? 3600,
            'created_at' => time(),
        ];
        
        set_transient($cacheKey, $cacheData, $data['expires_in'] ?? 3600);
        
        return $data['access_token'];
    }

    /**
     * PayPal'dan yeni token ister
     */
    private static function requestNewToken(): array|WP_Error
    {
        $clientId = Config::clientId();
        $clientSecret = Config::clientSecret();
        $baseUrl = Config::apiUrl();

        if (empty($clientId) || empty($clientSecret)) {
            return new WP_Error('paypal_config', 'PayPal client ID veya secret eksik');
        }

        $headers = [
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Authorization' => 'Basic ' . base64_encode($clientId . ':' . $clientSecret),
        ];

        $body = 'grant_type=client_credentials';

        $response = wp_remote_post($baseUrl . self::TOKEN_ENDPOINT, [
            'headers' => $headers,
            'body' => $body,
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            $body = wp_remote_retrieve_body($response);
            return new WP_Error('paypal_token_error', "PayPal token hatası: {$code} - {$body}");
        }

        return $response;
    }

    /**
     * Token'ı temizler
     */
    public static function clearToken(): void
    {
        $cacheKey = Config::getAccessTokenCacheKey();
        delete_transient($cacheKey);
    }

    /**
     * Token geçerliliğini kontrol eder
     */
    public static function isTokenValid(): bool
    {
        $cacheKey = Config::getAccessTokenCacheKey();
        $cached = get_transient($cacheKey);

        if ($cached === false || !is_array($cached) || !isset($cached['token'])) {
            return false;
        }

        $expiresIn = $cached['expires_in'] ?? 0;
        $createdAt = $cached['created_at'] ?? 0;
        
        return time() < ($createdAt + $expiresIn - 60); // 60 saniye buffer
    }
}
