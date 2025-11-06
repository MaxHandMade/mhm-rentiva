<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Payment\Gateways\PayPal\API;

use MHMRentiva\Admin\Payment\Gateways\PayPal\Config;
use MHMRentiva\Admin\PostTypes\Logs\Logger;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

final class Orders
{
    private const ORDERS_ENDPOINT = '/v2/checkout/orders';

    /**
     * PayPal sipariş oluşturur
     */
    public static function createOrder(array $orderData): array|WP_Error
    {
        $token = Auth::getAccessToken();
        if (empty($token)) {
            return new WP_Error('paypal_auth', 'PayPal token alınamadı');
        }

        $baseUrl = Config::apiUrl();
        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
            'PayPal-Request-Id' => wp_generate_uuid4(),
        ];

        $response = wp_remote_post($baseUrl . self::ORDERS_ENDPOINT, [
            'headers' => $headers,
            'body' => wp_json_encode($orderData),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            Logger::error('PayPal sipariş oluşturma hatası: ' . $response->get_error_message());
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($code !== 201) {
            Logger::error("PayPal sipariş oluşturma hatası: {$code} - {$body}");
            return new WP_Error('paypal_order_error', "PayPal sipariş hatası: {$code}");
        }

        Logger::info('PayPal sipariş oluşturuldu: ' . ($data['id'] ?? 'unknown'));
        return $data;
    }

    /**
     * PayPal sipariş detaylarını alır
     */
    public static function getOrder(string $orderId): array|WP_Error
    {
        $token = Auth::getAccessToken();
        if (empty($token)) {
            return new WP_Error('paypal_auth', 'PayPal token alınamadı');
        }

        $baseUrl = Config::apiUrl();
        $headers = [
            'Authorization' => 'Bearer ' . $token,
        ];

        $response = wp_remote_get($baseUrl . self::ORDERS_ENDPOINT . '/' . $orderId, [
            'headers' => $headers,
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            Logger::error('PayPal sipariş detay hatası: ' . $response->get_error_message());
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($code !== 200) {
            Logger::error("PayPal sipariş detay hatası: {$code} - {$body}");
            return new WP_Error('paypal_order_error', "PayPal sipariş detay hatası: {$code}");
        }

        return $data;
    }

    /**
     * PayPal siparişi onaylar (capture)
     */
    public static function captureOrder(string $orderId): array|WP_Error
    {
        $token = Auth::getAccessToken();
        if (empty($token)) {
            return new WP_Error('paypal_auth', 'PayPal token alınamadı');
        }

        $baseUrl = Config::apiUrl();
        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
            'PayPal-Request-Id' => wp_generate_uuid4(),
        ];

        $captureData = [
            'payment_source' => [
                'paypal' => [
                    'experience_context' => [
                        'payment_method_preference' => 'IMMEDIATE_PAYMENT_REQUIRED',
                        'brand_name' => get_bloginfo('name'),
                        'locale' => 'tr-TR',
                        'landing_page' => 'LOGIN',
                        'user_action' => 'PAY_NOW',
                    ],
                ],
            ],
        ];

        $response = wp_remote_post($baseUrl . self::ORDERS_ENDPOINT . '/' . $orderId . '/capture', [
            'headers' => $headers,
            'body' => wp_json_encode($captureData),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            Logger::error('PayPal sipariş onaylama hatası: ' . $response->get_error_message());
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($code !== 200) {
            Logger::error("PayPal sipariş onaylama hatası: {$code} - {$body}");
            return new WP_Error('paypal_capture_error', "PayPal sipariş onaylama hatası: {$code}");
        }

        Logger::info('PayPal sipariş onaylandı: ' . ($data['id'] ?? 'unknown'));
        return $data;
    }

    /**
     * PayPal siparişi iptal eder
     */
    public static function cancelOrder(string $orderId): array|WP_Error
    {
        $token = Auth::getAccessToken();
        if (empty($token)) {
            return new WP_Error('paypal_auth', 'PayPal token alınamadı');
        }

        $baseUrl = Config::apiUrl();
        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ];

        $response = wp_remote_post($baseUrl . self::ORDERS_ENDPOINT . '/' . $orderId . '/cancel', [
            'headers' => $headers,
            'body' => wp_json_encode([]),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            Logger::error('PayPal sipariş iptal hatası: ' . $response->get_error_message());
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($code !== 200) {
            Logger::error("PayPal sipariş iptal hatası: {$code} - {$body}");
            return new WP_Error('paypal_cancel_error', "PayPal sipariş iptal hatası: {$code}");
        }

        Logger::info('PayPal sipariş iptal edildi: ' . $orderId);
        return $data;
    }
}
