<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Payment\Gateways\PayPal;

use MHMRentiva\Admin\Licensing\Mode;
use MHMRentiva\Admin\Payment\Gateways\PayPal\API\Auth;
use MHMRentiva\Admin\Payment\Gateways\PayPal\API\Orders;
use MHMRentiva\Admin\Payment\Gateways\PayPal\API\Refunds;
use MHMRentiva\Admin\Payment\Gateways\PayPal\API\Webhook;
use MHMRentiva\Admin\PostTypes\Logs\Logger;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

final class Client
{
    /**
     * PayPal Client kayıt işlemleri
     */
    public static function register(): void
    {
        if (!Mode::featureEnabled(Mode::FEATURE_GATEWAY_PAYPAL)) {
            return;
        }

        add_action('init', [self::class, 'init']);
    }

    /**
     * Başlatma işlemleri
     */
    public static function init(): void
    {
        // Başlatma işlemleri
    }

    /**
     * PayPal Order oluşturur
     */
    public function createOrder(int $booking_id, int $amount, string $currency): array
    {
        // Booking bilgilerini al
        $booking = get_post($booking_id);
        if (!$booking || $booking->post_type !== 'vehicle_booking') {
            return [
                'ok' => false,
                'message' => __('Invalid booking.', 'mhm-rentiva'),
            ];
        }

        $vehicle_id = (int) get_post_meta($booking_id, '_mhm_vehicle_id', true);
        $vehicle = get_post($vehicle_id);
        $vehicle_title = $vehicle ? $vehicle->post_title : __('Vehicle Rental', 'mhm-rentiva');

        // Amount'u PayPal formatına çevir (cent/kuşur)
        $amountFormatted = number_format($amount / 100, 2, '.', '');

        $orderData = [
            'intent' => 'CAPTURE',
            'purchase_units' => [
                [
                    'reference_id' => 'booking_' . $booking_id,
                    /* translators: 1: Booking ID, 2: Vehicle title */
                    'description' => sprintf(__('Booking #%1$d - %2$s', 'mhm-rentiva'), $booking_id, $vehicle_title),
                    'amount' => [
                        'currency_code' => $currency,
                        'value' => $amountFormatted,
                    ],
                ],
            ],
            'application_context' => [
                'brand_name' => get_bloginfo('name'),
                'landing_page' => 'BILLING',
                'shipping_preference' => 'NO_SHIPPING',
                'user_action' => 'PAY_NOW',
                'return_url' => home_url('/'),
                'cancel_url' => home_url('/'),
            ],
        ];

        $result = Orders::createOrder($orderData);
        
        if (is_wp_error($result)) {
            Logger::add([
                'gateway' => 'paypal',
                'action' => 'create_order',
                'status' => 'error',
                'booking_id' => $booking_id,
                'message' => $result->get_error_message(),
            ]);

            return [
                'ok' => false,
                'message' => __('PayPal connection error.', 'mhm-rentiva'),
            ];
        }

        // Başarılı
        Logger::add([
            'gateway' => 'paypal',
            'action' => 'create_order',
            'status' => 'success',
            'booking_id' => $booking_id,
            'amount' => $amount,
            'currency' => $currency,
            'message' => __('PayPal order created', 'mhm-rentiva'),
            'context' => [
                'order_id' => $result['id'],
                'status' => $result['status'],
            ],
        ]);

        return [
            'ok' => true,
            'order_id' => $result['id'],
            'status' => $result['status'],
            'links' => $result['links'] ?? [],
        ];
    }

    /**
     * PayPal Order'ı yakalar (ödeme alır)
     */
    public function captureOrder(string $order_id): array
    {
        $result = Orders::captureOrder($order_id);
        
        if (is_wp_error($result)) {
            Logger::add([
                'gateway' => 'paypal',
                'action' => 'capture_order',
                'status' => 'error',
                'message' => $result->get_error_message(),
                'context' => ['order_id' => $order_id],
            ]);

            return [
                'ok' => false,
                'message' => __('PayPal connection error.', 'mhm-rentiva'),
            ];
        }

        // Başarılı capture
        $capture = $result['purchase_units'][0]['payments']['captures'][0] ?? [];

        Logger::add([
            'gateway' => 'paypal',
            'action' => 'capture_order',
            'status' => 'success',
            'message' => __('PayPal payment captured', 'mhm-rentiva'),
            'context' => [
                'order_id' => $order_id,
                'capture_id' => $capture['id'] ?? '',
                'amount' => $capture['amount'] ?? [],
                'status' => $capture['status'] ?? '',
            ],
        ]);

        return [
            'ok' => true,
            'capture_id' => $capture['id'] ?? '',
            'status' => $capture['status'] ?? '',
            'amount' => $capture['amount'] ?? [],
            'fee' => $capture['seller_receivable_breakdown']['paypal_fee']['value'] ?? '0.00',
        ];
    }

    /**
     * PayPal ödemesini iade eder
     */
    public function refundPayment(string $capture_id, int $amount): array
    {
        $amountFormatted = number_format($amount / 100, 2, '.', '');

        $refundData = [
            'amount' => [
                'value' => $amountFormatted,
                'currency_code' => Config::currency(),
            ],
        ];

        $result = Refunds::createRefund($capture_id, $refundData);
        
        if (is_wp_error($result)) {
            Logger::add([
                'gateway' => 'paypal',
                'action' => 'refund',
                'status' => 'error',
                'message' => $result->get_error_message(),
                'context' => ['capture_id' => $capture_id],
            ]);

            return [
                'ok' => false,
                'message' => __('PayPal connection error.', 'mhm-rentiva'),
            ];
        }

        // Başarılı iade
        Logger::add([
            'gateway' => 'paypal',
            'action' => 'refund',
            'status' => 'success',
            'message' => __('PayPal refund completed', 'mhm-rentiva'),
            'context' => [
                'capture_id' => $capture_id,
                'refund_id' => $result['id'],
                'amount' => $result['amount'],
                'status' => $result['status'],
            ],
        ]);

        return [
            'ok' => true,
            'refund_id' => $result['id'],
            'status' => $result['status'],
            'amount' => $result['amount'],
        ];
    }

    /**
     * PayPal webhook'unu doğrular
     */
    public function verifyWebhook(array $headers, string $body): bool
    {
        return Webhook::verifyWebhook($headers, $body);
    }

    /**
     * PayPal order durumunu sorgular
     */
    public function getOrder(string $order_id): array
    {
        $result = Orders::getOrder($order_id);
        
        if (is_wp_error($result)) {
            return [
                'ok' => false,
                'message' => $result->get_error_message(),
            ];
        }

        return [
            'ok' => true,
            'order' => $result,
        ];
    }
}
