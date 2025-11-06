<?php declare(strict_types=1);

namespace MHMRentiva\REST;

use MHMRentiva\Admin\Licensing\Mode;
use MHMRentiva\REST\StripeWebhook\Webhook;

if (!defined('ABSPATH')) {
    exit;
}

final class StripeWebhook
{
    public static function register(): void
    {
        if (!Mode::featureEnabled(Mode::FEATURE_GATEWAY_STRIPE)) {
            return;
        }
        
        add_action('rest_api_init', function () {
            register_rest_route('mhm-rentiva/v1', '/stripe/webhook', [
                'methods'  => ['POST'],
                'callback' => [Webhook::class, 'handle'],
                'permission_callback' => '__return_true',
            ]);
        });
    }
}
