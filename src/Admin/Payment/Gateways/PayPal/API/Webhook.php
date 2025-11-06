<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Payment\Gateways\PayPal\API;

use MHMRentiva\Admin\Payment\Gateways\PayPal\Config;
use MHMRentiva\Admin\PostTypes\Logs\Logger;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

final class Webhook
{
    /**
     * PayPal webhook'unu doğrular
     */
    public static function verifyWebhook(array $headers, string $body): bool
    {
        $webhookId = Config::webhookId();
        if (empty($webhookId)) {
            if (Config::debugMode()) {
                error_log('PayPal Webhook ID eksik');
            }
            return false;
        }

        $token = Auth::getAccessToken();
        if (empty($token)) {
            return false;
        }

        // PayPal webhook verification payload
        $verificationData = [
            'auth_algo' => $headers['paypal-auth-algo'] ?? '',
            'cert_url' => $headers['paypal-cert-url'] ?? '',
            'transmission_id' => $headers['paypal-transmission-id'] ?? '',
            'transmission_sig' => $headers['paypal-transmission-sig'] ?? '',
            'transmission_time' => $headers['paypal-transmission-time'] ?? '',
            'webhook_id' => $webhookId,
            'webhook_event' => json_decode($body, true),
        ];

        $response = wp_remote_post(Config::apiUrl() . '/v1/notifications/verify-webhook-signature', [
            'timeout' => Config::timeout(),
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $token,
            ],
            'body' => wp_json_encode($verificationData),
        ]);

        if (is_wp_error($response)) {
            if (Config::debugMode()) {
                error_log('PayPal Webhook Verification Error: ' . $response->get_error_message());
            }
            return false;
        }

        $code = wp_remote_retrieve_response_code($response);
        $responseBody = wp_remote_retrieve_body($response);
        $data = json_decode($responseBody, true);

        $isValid = ($code === 200 && isset($data['verification_status']) && $data['verification_status'] === 'SUCCESS');

        if (Config::debugMode()) {
            error_log('PayPal Webhook Verification: ' . ($isValid ? 'SUCCESS' : 'FAILED'));
        }

        return $isValid;
    }

    /**
     * Webhook event'ini işler
     */
    public static function processEvent(array $event): void
    {
        $eventType = $event['event_type'] ?? '';
        $resource = $event['resource'] ?? [];

        switch ($eventType) {
            case 'PAYMENT.CAPTURE.COMPLETED':
                self::handlePaymentCaptureCompleted($resource);
                break;
            case 'PAYMENT.CAPTURE.DENIED':
                self::handlePaymentCaptureDenied($resource);
                break;
            case 'PAYMENT.CAPTURE.REFUNDED':
                self::handlePaymentCaptureRefunded($resource);
                break;
            default:
                Logger::info('PayPal webhook event işlenmedi: ' . $eventType);
                break;
        }
    }

    /**
     * Ödeme yakalama tamamlandı
     */
    private static function handlePaymentCaptureCompleted(array $resource): void
    {
        $captureId = $resource['id'] ?? '';
        $amount = $resource['amount'] ?? [];
        
        if (empty($captureId)) {
            return;
        }

        // Booking'i bul ve güncelle
        $bookingId = self::findBookingByPayPalCaptureId($captureId);
        if ($bookingId) {
            update_post_meta($bookingId, '_mhm_payment_status', 'paid');
            update_post_meta($bookingId, '_mhm_paypal_payment_id', $captureId);
            update_post_meta($bookingId, '_mhm_paypal_status', 'captured');
            
            Logger::info('PayPal ödeme yakalandı: ' . $captureId);
        }
    }

    /**
     * Ödeme yakalama reddedildi
     */
    private static function handlePaymentCaptureDenied(array $resource): void
    {
        $captureId = $resource['id'] ?? '';
        
        if (empty($captureId)) {
            return;
        }

        // Booking'i bul ve güncelle
        $bookingId = self::findBookingByPayPalCaptureId($captureId);
        if ($bookingId) {
            update_post_meta($bookingId, '_mhm_payment_status', 'failed');
            update_post_meta($bookingId, '_mhm_paypal_status', 'denied');
            
            Logger::info('PayPal ödeme reddedildi: ' . $captureId);
        }
    }

    /**
     * Ödeme iade edildi
     */
    private static function handlePaymentCaptureRefunded(array $resource): void
    {
        $captureId = $resource['id'] ?? '';
        
        if (empty($captureId)) {
            return;
        }

        // Booking'i bul ve güncelle
        $bookingId = self::findBookingByPayPalCaptureId($captureId);
        if ($bookingId) {
            update_post_meta($bookingId, '_mhm_payment_status', 'refunded');
            update_post_meta($bookingId, '_mhm_paypal_status', 'refunded');
            
            Logger::info('PayPal ödeme iade edildi: ' . $captureId);
        }
    }

    /**
     * PayPal capture ID ile booking bulur
     */
    private static function findBookingByPayPalCaptureId(string $captureId): ?int
    {
        global $wpdb;
        
        $bookingId = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} 
             WHERE meta_key = '_mhm_paypal_payment_id' 
             AND meta_value = %s",
            $captureId
        ));
        
        return $bookingId ? (int) $bookingId : null;
    }
}
