<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Payment\Gateways\Stripe\API;

use MHMRentiva\Admin\Payment\Gateways\Stripe\Config;
use MHMRentiva\Admin\PostTypes\Logs\Logger;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

final class Refunds
{
    private const API_BASE_URL = 'https://api.stripe.com/v1';

    /**
     * Stripe iade işlemi yapar
     */
    public static function createRefund(array $args): array
    {
        $secretKey = Config::secretKey();
        if (empty($secretKey)) {
            return [
                'ok' => false,
                'message' => __('Stripe secret key missing', 'mhm-rentiva')
            ];
        }

        $amount = (int) ($args['amount'] ?? 0);
        if ($amount <= 0) {
            return [
                'ok' => false,
                'message' => __('Invalid refund amount', 'mhm-rentiva')
            ];
        }

        $paymentIntentId = isset($args['payment_intent']) ? (string) $args['payment_intent'] : '';
        $chargeId = isset($args['charge']) ? (string) $args['charge'] : '';

        if (empty($paymentIntentId) && empty($chargeId)) {
            return [
                'ok' => false,
                'message' => __('Stripe payment identifier missing', 'mhm-rentiva')
            ];
        }

        $body = ['amount' => $amount];

        if (!empty($paymentIntentId)) {
            $body['payment_intent'] = $paymentIntentId;
        } elseif (!empty($chargeId)) {
            $body['charge'] = $chargeId;
        }

        // İade sebebi ekle
        if (!empty($args['reason'])) {
            $reason = self::mapRefundReason((string) $args['reason']);
            if (!empty($reason)) {
                $body['reason'] = $reason;
            }
        }

        $response = wp_remote_post(self::API_BASE_URL . '/refunds', [
            'headers' => [
                'Authorization' => 'Bearer ' . $secretKey,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body' => $body,
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            Logger::error('Stripe iade hatası: ' . $response->get_error_message());
            return [
                'ok' => false,
                'message' => $response->get_error_message()
            ];
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body = (string) wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($code >= 200 && $code < 300 && is_array($data) && isset($data['id'])) {
            Logger::info('Stripe iade oluşturuldu: ' . $data['id'] . ' - Tutar: ' . $amount);
            return [
                'ok' => true,
                'id' => (string) $data['id'],
                'refund' => $data
            ];
        }

        $message = is_array($data) && isset($data['error']['message']) 
            ? (string) $data['error']['message'] 
            : 'Stripe iade hatası';
        $errorCode = is_array($data) && isset($data['error']['code']) 
            ? (string) $data['error']['code'] 
            : (string) $code;

        Logger::error('Stripe iade hatası: ' . $message . ' (Kod: ' . $errorCode . ')');
        return [
            'ok' => false,
            'message' => $message,
            'code' => $errorCode
        ];
    }

    /**
     * İade detaylarını alır
     */
    public static function getRefund(string $refundId): array
    {
        $secretKey = Config::secretKey();
        if (empty($secretKey)) {
            return [
                'ok' => false,
                'message' => __('Stripe secret key missing', 'mhm-rentiva')
            ];
        }

        if (empty($refundId)) {
            return [
                'ok' => false,
                'message' => __('Refund ID missing', 'mhm-rentiva')
            ];
        }

        $response = wp_remote_get(self::API_BASE_URL . '/refunds/' . rawurlencode($refundId), [
            'headers' => [
                'Authorization' => 'Bearer ' . $secretKey,
            ],
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            Logger::error('Stripe iade detay hatası: ' . $response->get_error_message());
            return [
                'ok' => false,
                'message' => $response->get_error_message()
            ];
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body = (string) wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($code >= 200 && $code < 300 && is_array($data) && isset($data['id'])) {
            return [
                'ok' => true,
                'refund' => $data
            ];
        }

        $message = is_array($data) && isset($data['error']['message']) 
            ? (string) $data['error']['message'] 
            : 'Stripe iade detay hatası';

        Logger::error('Stripe iade detay hatası: ' . $message);
        return [
            'ok' => false,
            'message' => $message
        ];
    }

    /**
     * İade sebebini Stripe formatına çevirir
     */
    private static function mapRefundReason(string $reason): string
    {
        $reasonMap = [
            'duplicate' => 'duplicate',
            'fraud_suspected' => 'fraudulent',
            'customer_request' => 'requested_by_customer',
        ];

        return $reasonMap[$reason] ?? '';
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
    public static function getRefundAmount(array $refundData): int
    {
        if (isset($refundData['refund']['amount'])) {
            return (int) $refundData['refund']['amount'];
        }
        
        return 0;
    }

    /**
     * İade ID'sini alır
     */
    public static function getRefundId(array $refundData): string
    {
        if (isset($refundData['refund']['id'])) {
            return (string) $refundData['refund']['id'];
        }
        
        return $refundData['id'] ?? '';
    }

    /**
     * İade durumunu alır
     */
    public static function getRefundStatus(array $refundData): string
    {
        if (isset($refundData['refund']['status'])) {
            return (string) $refundData['refund']['status'];
        }
        
        return 'unknown';
    }

    /**
     * İade para birimini alır
     */
    public static function getRefundCurrency(array $refundData): string
    {
        if (isset($refundData['refund']['currency'])) {
            return (string) strtoupper($refundData['refund']['currency']);
        }
        
        return '';
    }

    /**
     * Tam iade yapar
     */
    public static function fullRefund(string $paymentIntentId, string $reason = ''): array
    {
        // Önce PaymentIntent'i sorgula
        $paymentIntent = PaymentIntents::retrievePaymentIntent($paymentIntentId);
        
        if (!$paymentIntent['ok']) {
            return [
                'ok' => false,
                'message' => __('PaymentIntent could not be queried:', 'mhm-rentiva') . ' ' . $paymentIntent['message']
            ];
        }

        $amount = $paymentIntent['amount'] ?? 0;
        if ($amount <= 0) {
            return [
                'ok' => false,
                'message' => __('No amount to refund', 'mhm-rentiva')
            ];
        }

        return self::createRefund([
            'payment_intent' => $paymentIntentId,
            'amount' => $amount,
            'reason' => $reason,
        ]);
    }

    /**
     * Kısmi iade yapar
     */
    public static function partialRefund(string $paymentIntentId, int $amount, string $reason = ''): array
    {
        if ($amount <= 0) {
            return [
                'ok' => false,
                'message' => __('Invalid refund amount', 'mhm-rentiva')
            ];
        }

        return self::createRefund([
            'payment_intent' => $paymentIntentId,
            'amount' => $amount,
            'reason' => $reason,
        ]);
    }
}
