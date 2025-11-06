<?php declare(strict_types=1);

namespace MHMRentiva\REST;

use MHMRentiva\Admin\Licensing\Mode;
use MHMRentiva\REST\PayTR\CreateToken;
use MHMRentiva\REST\PayTR\Callback;

if (!defined('ABSPATH')) {
    exit;
}

final class PayTR
{
    public static function register(): void
    {
        if (!Mode::featureEnabled(Mode::FEATURE_GATEWAY_PAYTR)) {
            return;
        }
        
        add_action('rest_api_init', function () {
            register_rest_route('mhm-rentiva/v1', '/paytr/create-token', [
                'methods'             => ['POST'],
                'callback'            => [CreateToken::class, 'handle'],
                'permission_callback' => '__return_true',
                'args' => [
                    'booking_id' => ['required' => true, 'type' => 'integer'],
                ],
            ]);
            
            register_rest_route('mhm-rentiva/v1', '/paytr/callback', [
                'methods'             => ['POST'],
                'callback'            => [Callback::class, 'handle'],
                'permission_callback' => '__return_true',
            ]);
        });
    }
}
