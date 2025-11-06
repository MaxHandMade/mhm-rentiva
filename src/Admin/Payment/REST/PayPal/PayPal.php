<?php declare(strict_types=1);

namespace MHMRentiva\REST\PayPal;

use MHMRentiva\Admin\Licensing\Mode;
use MHMRentiva\REST\PayPal\Helpers\Auth;

if (!defined('ABSPATH')) {
    exit;
}

final class PayPal
{
    public static function register(): void
    {
        if (!Mode::featureEnabled(Mode::FEATURE_GATEWAY_PAYPAL)) {
            return;
        }

        add_action('rest_api_init', function () {
            // Order oluşturma
            register_rest_route('mhm-rentiva/v1', '/paypal/create-order', [
                'methods' => 'POST',
                'callback' => [CreateOrder::class, 'handle'],
                'permission_callback' => '__return_true',
                'args' => [
                    'booking_id' => [
                        'required' => true,
                        'type' => 'integer',
                        'minimum' => 1,
                    ],
                ],
            ]);

            // Ödeme yakalama
            register_rest_route('mhm-rentiva/v1', '/paypal/capture-payment', [
                'methods' => 'POST',
                'callback' => [CapturePayment::class, 'handle'],
                'permission_callback' => '__return_true',
                'args' => [
                    'order_id' => [
                        'required' => true,
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'booking_id' => [
                        'required' => true,
                        'type' => 'integer',
                        'minimum' => 1,
                    ],
                ],
            ]);

            // Webhook
            register_rest_route('mhm-rentiva/v1', '/paypal/webhook', [
                'methods' => 'POST',
                'callback' => [Webhook::class, 'handle'],
                'permission_callback' => '__return_true',
            ]);

            // İade
            register_rest_route('mhm-rentiva/v1', '/paypal/refund', [
                'methods' => 'POST',
                'callback' => [Refund::class, 'handle'],
                'permission_callback' => [Auth::class, 'adminPermissionsCheck'],
                'args' => [
                    'booking_id' => [
                        'required' => true,
                        'type' => 'integer',
                        'minimum' => 1,
                    ],
                    'amount' => [
                        'required' => true,
                        'type' => 'integer',
                        'minimum' => 1,
                    ],
                    'reason' => [
                        'required' => false,
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                ],
            ]);
        });
    }
}
