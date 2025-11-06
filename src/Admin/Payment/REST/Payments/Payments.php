<?php declare(strict_types=1);

namespace MHMRentiva\REST\Payments;

use MHMRentiva\REST\Payments\CreateIntent;
use MHMRentiva\REST\Payments\Refund;

if (!defined('ABSPATH')) {
    exit;
}

final class Payments
{
    public static function register(): void
    {
        add_action('rest_api_init', function () {
            register_rest_route('mhm-rentiva/v1', '/payments/create-intent', [
                [
                    'methods'  => ['POST'],
                    'callback' => [CreateIntent::class, 'handle'],
                    'permission_callback' => '__return_true',
                    'args' => [
                        'booking_id' => ['required' => true, 'type' => 'integer'],
                    ],
                ],
            ]);

            register_rest_route('mhm-rentiva/v1', '/payments/refund', [
                [
                    'methods'  => ['POST'],
                    'callback' => [Refund::class, 'handle'],
                    'permission_callback' => [Refund::class, 'permissionCheck'],
                    'args' => [
                        'booking_id' => ['required' => true, 'type' => 'integer'],
                        'amount'     => ['required' => false, 'type' => 'integer'],
                    ],
                ],
            ]);
        });
    }
}
