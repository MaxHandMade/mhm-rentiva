<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Payment\Gateways\Stripe\API;

use MHMRentiva\Admin\Payment\Gateways\Stripe\Config;
use MHMRentiva\Admin\PostTypes\Logs\Logger;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

final class PaymentIntents
{
    private const API_BASE_URL = 'https://api.stripe.com/v1';

    /**
     * PaymentIntent durumunu sorgular
     */
    public static function retrievePaymentIntent(string $paymentIntentId): array
    {
        $secretKey = Config::secretKey();
        if (empty($secretKey)) {
            return [
                'ok' => false,
                'message' => __('Stripe secret key is missing', 'mhm-rentiva')
            ];
        }

        if (empty($paymentIntentId)) {
            return [
                'ok' => false,
                'message' => 'PaymentIntent ID eksik'
            ];
        }

        $response = wp_remote_get(self::API_BASE_URL . '/payment_intents/' . rawurlencode($paymentIntentId), [
            'headers' => [
                'Authorization' => 'Bearer ' . $secretKey,
            ],
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            Logger::error(__('Stripe PaymentIntent query error: ', 'mhm-rentiva') . $response->get_error_message());
            return [
                'ok' => false,
                'message' => $response->get_error_message()
            ];
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body = (string) wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($code >= 200 && $code < 300 && is_array($data) && isset($data['status'])) {
            $amount = isset($data['amount']) ? (int) $data['amount'] : 0;
            $currency = isset($data['currency']) ? (string) strtoupper($data['currency']) : '';

            Logger::info(__('Stripe PaymentIntent queried: ', 'mhm-rentiva') . $paymentIntentId . ' - ' . __('Status: ', 'mhm-rentiva') . $data['status']);

            return [
                'ok' => true,
                'status' => (string) $data['status'],
                'amount' => $amount,
                'currency' => $currency,
                'payment_intent' => $data
            ];
        }

        $message = is_array($data) && isset($data['error']['message']) 
            ? (string) $data['error']['message'] 
            : __('Stripe API error', 'mhm-rentiva');
        $errorCode = is_array($data) && isset($data['error']['code']) 
            ? (string) $data['error']['code'] 
            : (string) $code;

        Logger::error(__('Stripe PaymentIntent error: ', 'mhm-rentiva') . $message . ' (' . __('Code: ', 'mhm-rentiva') . $errorCode . ')');

        return [
            'ok' => false,
            'message' => $message,
            'code' => $errorCode
        ];
    }

    /**
     * PaymentIntent oluşturur
     */
    public static function createPaymentIntent(array $data): array
    {
        $secretKey = Config::secretKey();
        if (empty($secretKey)) {
            return [
                'ok' => false,
                'message' => __('Stripe secret key missing', 'mhm-rentiva')
            ];
        }

        $response = wp_remote_post(self::API_BASE_URL . '/payment_intents', [
            'headers' => [
                'Authorization' => 'Bearer ' . $secretKey,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body' => $data,
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            Logger::error(__('Stripe PaymentIntent creation error: ', 'mhm-rentiva') . $response->get_error_message());
            return [
                'ok' => false,
                'message' => $response->get_error_message()
            ];
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body = (string) wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($code >= 200 && $code < 300 && is_array($data) && isset($data['id'])) {
            Logger::info(__('Stripe PaymentIntent created: ', 'mhm-rentiva') . $data['id']);
            return [
                'ok' => true,
                'payment_intent' => $data
            ];
        }

        $message = is_array($data) && isset($data['error']['message']) 
            ? (string) $data['error']['message'] 
            : __('Stripe PaymentIntent creation error', 'mhm-rentiva');

        Logger::error(__('Stripe PaymentIntent creation error: ', 'mhm-rentiva') . $message);
        return [
            'ok' => false,
            'message' => $message
        ];
    }

    /**
     * PaymentIntent'i günceller
     */
    public static function updatePaymentIntent(string $paymentIntentId, array $data): array
    {
        $secretKey = Config::secretKey();
        if (empty($secretKey)) {
            return [
                'ok' => false,
                'message' => __('Stripe secret key missing', 'mhm-rentiva')
            ];
        }

        if (empty($paymentIntentId)) {
            return [
                'ok' => false,
                'message' => __('PaymentIntent ID missing', 'mhm-rentiva')
            ];
        }

        $response = wp_remote_post(self::API_BASE_URL . '/payment_intents/' . rawurlencode($paymentIntentId), [
            'headers' => [
                'Authorization' => 'Bearer ' . $secretKey,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body' => $data,
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            Logger::error(__('Stripe PaymentIntent update error: ', 'mhm-rentiva') . $response->get_error_message());
            return [
                'ok' => false,
                'message' => $response->get_error_message()
            ];
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body = (string) wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($code >= 200 && $code < 300 && is_array($data) && isset($data['id'])) {
            Logger::info(__('Stripe PaymentIntent updated: ', 'mhm-rentiva') . $paymentIntentId);
            return [
                'ok' => true,
                'payment_intent' => $data
            ];
        }

        $message = is_array($data) && isset($data['error']['message']) 
            ? (string) $data['error']['message'] 
            : __('Stripe PaymentIntent update error', 'mhm-rentiva');

        Logger::error(__('Stripe PaymentIntent update error: ', 'mhm-rentiva') . $message);
        return [
            'ok' => false,
            'message' => $message
        ];
    }

    /**
     * PaymentIntent'i iptal eder
     */
    public static function cancelPaymentIntent(string $paymentIntentId): array
    {
        $secretKey = Config::secretKey();
        if (empty($secretKey)) {
            return [
                'ok' => false,
                'message' => __('Stripe secret key missing', 'mhm-rentiva')
            ];
        }

        if (empty($paymentIntentId)) {
            return [
                'ok' => false,
                'message' => __('PaymentIntent ID missing', 'mhm-rentiva')
            ];
        }

        $response = wp_remote_post(self::API_BASE_URL . '/payment_intents/' . rawurlencode($paymentIntentId) . '/cancel', [
            'headers' => [
                'Authorization' => 'Bearer ' . $secretKey,
            ],
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            Logger::error(__('Stripe PaymentIntent cancellation error: ', 'mhm-rentiva') . $response->get_error_message());
            return [
                'ok' => false,
                'message' => $response->get_error_message()
            ];
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body = (string) wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($code >= 200 && $code < 300 && is_array($data) && isset($data['id'])) {
            Logger::info('Stripe PaymentIntent iptal edildi: ' . $paymentIntentId);
            return [
                'ok' => true,
                'payment_intent' => $data
            ];
        }

        $message = is_array($data) && isset($data['error']['message']) 
            ? (string) $data['error']['message'] 
            : __('Stripe PaymentIntent cancellation error', 'mhm-rentiva');

        Logger::error(__('Stripe PaymentIntent cancellation error: ', 'mhm-rentiva') . $message);
        return [
            'ok' => false,
            'message' => $message
        ];
    }

    /**
     * PaymentIntent durumunu kontrol eder
     */
    public static function isPaymentSuccessful(array $paymentIntent): bool
    {
        return isset($paymentIntent['status']) && $paymentIntent['status'] === 'succeeded';
    }

    /**
     * PaymentIntent tutarını alır
     */
    public static function getPaymentAmount(array $paymentIntent): int
    {
        return isset($paymentIntent['amount']) ? (int) $paymentIntent['amount'] : 0;
    }

    /**
     * PaymentIntent para birimini alır
     */
    public static function getPaymentCurrency(array $paymentIntent): string
    {
        return isset($paymentIntent['currency']) ? (string) strtoupper($paymentIntent['currency']) : '';
    }

    /**
     * PaymentIntent durumunu alır
     */
    public static function getPaymentStatus(array $paymentIntent): string
    {
        return isset($paymentIntent['status']) ? (string) $paymentIntent['status'] : 'unknown';
    }
}
