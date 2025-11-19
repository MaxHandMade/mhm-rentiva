<?php declare(strict_types=1);

namespace MHMRentiva\REST\PayPal;

use MHMRentiva\Admin\Payment\Gateways\PayPal\Client as PayPalClient;
use MHMRentiva\Admin\Booking\Core\Status;
use MHMRentiva\Admin\PostTypes\Logs\Logger;
use WP_REST_Request;
use WP_REST_Response;

if (!defined('ABSPATH')) {
    exit;
}

final class Webhook
{
    /**
     * PayPal Webhook handler
     */
    public static function handle(WP_REST_Request $req): WP_REST_Response
    {
        // Rate limiting kontrolü
        $rate_limit_check = \MHMRentiva\Core\RateLimiter::middleware('webhook_processing');
        if (is_wp_error($rate_limit_check)) {
            return new WP_REST_Response([
                'error' => $rate_limit_check->get_error_message()
            ], 429);
        }

        $headers = [];
        foreach ($req->get_headers() as $key => $values) {
            $headers[strtolower($key)] = is_array($values) ? $values[0] : $values;
        }

        $body = $req->get_body();
        $data = json_decode($body, true);

        if (!is_array($data) || !isset($data['event_type'])) {
            return self::webhook_response('INVALID_REQUEST');
        }

        // ✅ GÜVENLİK: Timestamp validation
        if (!self::validateWebhookTimestamp($data)) {
            Logger::error('Webhook timestamp validation failed', ['data' => $data]);
            return self::webhook_response('INVALID_TIMESTAMP');
        }

        // ✅ GÜVENLİK: Event replay attack prevention
        if (!self::preventEventReplay($data['id'] ?? '')) {
            error_log('MHM Rentiva: Webhook event replay detected - Event ID: ' . ($data['id'] ?? ''));
            return self::webhook_response('DUPLICATE_EVENT');
        }

        // Webhook doğrulama
        $client = new PayPalClient();
        if (!$client->verifyWebhook($headers, $body)) {
            Logger::add([
                'gateway' => 'paypal',
                'action' => 'webhook_verification_failed',
                'status' => 'error',
                'message' => __('PayPal webhook signature verification failed', 'mhm-rentiva'),
                'context' => ['headers' => $headers, 'event_type' => $data['event_type']],
            ]);
            return self::webhook_response('VERIFICATION_FAILED');
        }

        $event_type = $data['event_type'];
        $resource = $data['resource'] ?? [];

        Logger::add([
            'gateway' => 'paypal',
            'action' => 'webhook_received',
            'status' => 'info',
            /* translators: %s placeholder. */
            'message' => sprintf(__('PayPal webhook received: %s', 'mhm-rentiva'), $event_type),
            'context' => [
                'event_type' => $event_type,
                'resource_id' => $resource['id'] ?? '',
            ],
        ]);

        // Event'e göre işlem yap
        switch ($event_type) {
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
                // Diğer event'ler için log tut
                Logger::add([
                    'gateway' => 'paypal',
                    'action' => 'webhook_unhandled_event',
                    'status' => 'info',
                    /* translators: %s placeholder. */
                    'message' => sprintf(__('Unhandled PayPal webhook event: %s', 'mhm-rentiva'), $event_type),
                ]);
                break;
        }

        return self::webhook_response('OK');
    }

    /**
     * Ödeme yakalama tamamlandı
     */
    private static function handlePaymentCaptureCompleted(array $resource): void
    {
        $capture_id = $resource['id'] ?? '';
        if (empty($capture_id)) {
            return;
        }

        // Capture ID ile rezervasyon bul
        $booking_id = self::findBookingByPayPalCaptureId($capture_id);
        if (!$booking_id) {
            Logger::add([
                'gateway' => 'paypal',
                'action' => 'webhook_capture_not_found',
                'status' => 'warning',
                'message' => __('Booking not found with PayPal capture ID', 'mhm-rentiva'),
                'context' => ['capture_id' => $capture_id],
            ]);
            return;
        }

        // Rezervasyon durumunu güncelle
        update_post_meta($booking_id, '_mhm_payment_status', 'paid');
        update_post_meta($booking_id, '_mhm_paypal_payment_id', $capture_id);
        update_post_meta($booking_id, '_mhm_paypal_status', 'captured');

        $old_status = (string) get_post_meta($booking_id, '_mhm_status', true);
        if ($old_status !== 'confirmed') {
            Status::update_status($booking_id, 'confirmed', 0);
        }

        Logger::add([
            'gateway' => 'paypal',
            'action' => 'webhook_capture_completed',
            'status' => 'success',
            'booking_id' => $booking_id,
            'message' => __('Payment confirmed via PayPal webhook', 'mhm-rentiva'),
            'context' => ['capture_id' => $capture_id],
        ]);
    }

    /**
     * Ödeme reddedildi
     */
    private static function handlePaymentCaptureDenied(array $resource): void
    {
        $capture_id = $resource['id'] ?? '';
        if (empty($capture_id)) {
            return;
        }

        $booking_id = self::findBookingByPayPalCaptureId($capture_id);
        if (!$booking_id) {
            return;
        }

        update_post_meta($booking_id, '_mhm_payment_status', 'failed');
        update_post_meta($booking_id, '_mhm_paypal_status', 'denied');

        Logger::add([
            'gateway' => 'paypal',
            'action' => 'webhook_capture_denied',
            'status' => 'error',
            'booking_id' => $booking_id,
            'message' => __('Payment declined via PayPal webhook', 'mhm-rentiva'),
            'context' => ['capture_id' => $capture_id],
        ]);
    }

    /**
     * Ödeme iade edildi
     */
    private static function handlePaymentCaptureRefunded(array $resource): void
    {
        $capture_id = $resource['id'] ?? '';
        if (empty($capture_id)) {
            return;
        }

        $booking_id = self::findBookingByPayPalCaptureId($capture_id);
        if (!$booking_id) {
            return;
        }

        update_post_meta($booking_id, '_mhm_payment_status', 'refunded');
        update_post_meta($booking_id, '_mhm_paypal_status', 'refunded');

        Logger::add([
            'gateway' => 'paypal',
            'action' => 'webhook_capture_refunded',
            'status' => 'info',
            'booking_id' => $booking_id,
            'message' => __('Payment refunded via PayPal webhook', 'mhm-rentiva'),
            'context' => ['capture_id' => $capture_id],
        ]);
    }

    /**
     * PayPal Capture ID ile rezervasyon bul
     */
    private static function findBookingByPayPalCaptureId(string $capture_id): ?int
    {
        if (empty($capture_id)) {
            return null;
        }

        $query = new \WP_Query([
            'post_type' => 'vehicle_booking',
            'post_status' => 'any',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'meta_query' => [
                [
                    'key' => '_mhm_paypal_payment_id',
                    'value' => $capture_id,
                    'compare' => '=',
                ]
            ]
        ]);

        if ($query->have_posts()) {
            return (int) $query->posts[0];
        }

        return null;
    }

    /**
     * Webhook yanıtı
     */
    private static function webhook_response(string $status): WP_REST_Response
    {
        $response = new WP_REST_Response($status, 200);
        $response->header('Content-Type', 'text/plain; charset=UTF-8');
        return $response;
    }

    /**
     * ✅ GÜVENLİK: Webhook timestamp validation
     * 
     * @param array $data Webhook data
     * @return bool Timestamp geçerli mi?
     */
    private static function validateWebhookTimestamp(array $data): bool
    {
        if (!isset($data['create_time'])) {
            return false;
        }
        
        $event_time = strtotime($data['create_time']);
        $current_time = time();
        
        // 10 dakika tolerans - çok eski event'leri reddet
        $tolerance = 10 * MINUTE_IN_SECONDS;
        
        if ($event_time < ($current_time - $tolerance)) {
            return false;
        }
        
        // Gelecekteki event'leri reddet (5 dakika tolerans)
        if ($event_time > ($current_time + (5 * MINUTE_IN_SECONDS))) {
            return false;
        }
        
        return true;
    }

    /**
     * ✅ GÜVENLİK: Event replay attack prevention
     * 
     * @param string $event_id Event ID
     * @return bool Event işlenebilir mi?
     */
    private static function preventEventReplay(string $event_id): bool
    {
        if (empty($event_id)) {
            return false;
        }
        
        $transient_key = "webhook_processed_{$event_id}";
        
        // Event zaten işlendi mi kontrol et
        if (get_transient($transient_key)) {
            return false; // Event zaten işlendi
        }
        
        // Event'i işlendi olarak işaretle (2 saat boyunca)
        set_transient($transient_key, true, 2 * HOUR_IN_SECONDS);
        
        return true;
    }
}
