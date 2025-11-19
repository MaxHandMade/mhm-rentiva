<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Payment\Gateways\PayPal\API;

use MHMRentiva\Admin\Payment\Gateways\PayPal\Config;
use MHMRentiva\Admin\PostTypes\Logs\Logger;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

final class Refunds
{
    private const REFUND_ENDPOINT = '/v2/payments/captures/%s/refund';

    /**
     * Create a PayPal refund request.
     */
    public static function createRefund(string $captureId, array $refundData): array|WP_Error
    {
        $token = Auth::getAccessToken();
        if (empty($token)) {
            return new WP_Error('paypal_auth', __('Unable to obtain PayPal token.', 'mhm-rentiva'));
        }

        $baseUrl = Config::apiUrl();
        $endpoint = sprintf($baseUrl . self::REFUND_ENDPOINT, $captureId);
        
        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
            'PayPal-Request-Id' => wp_generate_uuid4(),
        ];

        $response = wp_remote_post($endpoint, [
            'headers' => $headers,
            'body' => wp_json_encode($refundData),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            Logger::error('PayPal refund error: ' . $response->get_error_message());
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($code !== 201) {
            Logger::error("PayPal refund error: {$code} - {$body}");
            return new WP_Error('paypal_refund_error', sprintf(__('PayPal refund error: %s', 'mhm-rentiva'), $code));
        }

        Logger::info('PayPal refund created: ' . ($data['id'] ?? 'unknown'));
        return $data;
    }

    /**
     * Retrieve PayPal refund details.
     */
    public static function getRefund(string $refundId): array|WP_Error
    {
        $token = Auth::getAccessToken();
        if (empty($token)) {
            return new WP_Error('paypal_auth', __('Unable to obtain PayPal token.', 'mhm-rentiva'));
        }

        $baseUrl = Config::apiUrl();
        $headers = [
            'Authorization' => 'Bearer ' . $token,
        ];

        $response = wp_remote_get($baseUrl . '/v2/payments/refunds/' . $refundId, [
            'headers' => $headers,
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            Logger::error('PayPal refund details error: ' . $response->get_error_message());
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($code !== 200) {
            Logger::error("PayPal refund details error: {$code} - {$body}");
            return new WP_Error('paypal_refund_error', sprintf(__('PayPal refund details error: %s', 'mhm-rentiva'), $code));
        }

        return $data;
    }

    /**
     * Perform a full refund.
     */
    public static function fullRefund(string $captureId, string $reason = ''): array|WP_Error
    {
        $refundData = [
            'amount' => [
                'currency_code' => 'USD',
                'value' => '0.00', // Amount not required for full refund
            ],
        ];

        if (!empty($reason)) {
            $refundData['note_to_payer'] = $reason;
        }

        return self::createRefund($captureId, $refundData);
    }

    /**
     * Perform a partial refund.
     */
    public static function partialRefund(string $captureId, float $amount, string $currency = 'USD', string $reason = ''): array|WP_Error
    {
        $refundData = [
            'amount' => [
                'currency_code' => $currency,
                'value' => number_format($amount, 2, '.', ''),
            ],
        ];

        if (!empty($reason)) {
            $refundData['note_to_payer'] = $reason;
        }

        return self::createRefund($captureId, $refundData);
    }

    /**
     * Check if refund completed.
     */
    public static function isRefundCompleted(array $refundData): bool
    {
        return isset($refundData['status']) && $refundData['status'] === 'COMPLETED';
    }

    /**
     * Get refund amount.
     */
    public static function getRefundAmount(array $refundData): float
    {
        if (!isset($refundData['amount']['value'])) {
            return 0.0;
        }

        return (float) $refundData['amount']['value'];
    }

    /**
     * Get refund currency.
     */
    public static function getRefundCurrency(array $refundData): string
    {
        return $refundData['amount']['currency_code'] ?? 'USD';
    }
}
