<?php declare(strict_types=1);

namespace MHMRentiva\Admin\REST;

use MHMRentiva\Admin\Booking\Helpers\Util;
use MHMRentiva\Admin\Settings\Settings;

if (!defined('ABSPATH')) {
    exit;
}

final class Availability
{
    public static function register(): void
    {
        add_action('rest_api_init', [self::class, 'register_routes']);
    }

    /**
     * Permission callback - Security check with rate limiting
     */
    public static function permission_check(): bool
    {
        // Rate limiting check
        $client_ip = \MHMRentiva\Admin\Core\Utilities\RateLimiter::getClientIP();
        return \MHMRentiva\Admin\Core\Utilities\RateLimiter::check($client_ip, 'general');
    }

    public static function register_routes(): void
    {
        register_rest_route('mhm-rentiva/v1', '/availability', [
            'methods' => ['GET', 'POST'],
            'callback' => [self::class, 'check'],
            'permission_callback' => [self::class, 'permission_check'],
            'args' => [
                'vehicle_id' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'pickup_date' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'pickup_time' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'dropoff_date' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'dropoff_time' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        // Alternative vehicle suggestions endpoint
        register_rest_route('mhm-rentiva/v1', '/availability/with-alternatives', [
            'methods' => ['GET', 'POST'],
            'callback' => [self::class, 'check_with_alternatives'],
            'permission_callback' => [self::class, 'permission_check'],
            'args' => [
                'vehicle_id' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'pickup_date' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'pickup_time' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'dropoff_date' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'dropoff_time' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'limit' => [
                    'required' => false,
                    'type' => 'integer',
                    'default' => 5,
                    'minimum' => 1,
                    'maximum' => 10,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);
    }

    public static function check(\WP_REST_Request $request): \WP_REST_Response
    {
        $vehicle_id = $request->get_param('vehicle_id');
        $pickup_date = $request->get_param('pickup_date');
        $pickup_time = $request->get_param('pickup_time');
        $dropoff_date = $request->get_param('dropoff_date');
        $dropoff_time = $request->get_param('dropoff_time');

        // Availability check
        $result = Util::check_availability($vehicle_id, $pickup_date, $pickup_time, $dropoff_date, $dropoff_time);

        // Add currency information
        $currency = Settings::get('currency', 'TRY');
        $currency_position = Settings::get('currency_position', 'right_space');

        $response_data = [
            'ok' => $result['ok'],
            'code' => $result['code'],
            'message' => $result['message'],
            'currency' => $currency,
            'currency_position' => $currency_position,
        ];

        // Add additional information on success
        if ($result['ok']) {
            $response_data = array_merge($response_data, [
                'days' => $result['days'],
                'price_per_day' => $result['price_per_day'],
                'total_price' => $result['total_price'],
                'start_ts' => $result['start_ts'],
                'end_ts' => $result['end_ts'],
            ]);
        }

        return new \WP_REST_Response($response_data, $result['ok'] ? 200 : 400);
    }

    public static function check_with_alternatives(\WP_REST_Request $request): \WP_REST_Response
    {
        $vehicle_id = $request->get_param('vehicle_id');
        $pickup_date = $request->get_param('pickup_date');
        $pickup_time = $request->get_param('pickup_time');
        $dropoff_date = $request->get_param('dropoff_date');
        $dropoff_time = $request->get_param('dropoff_time');
        $limit = $request->get_param('limit') ?: 5;

        // Advanced availability check (with alternative suggestions)
        $result = Util::check_availability_with_alternatives($vehicle_id, $pickup_date, $pickup_time, $dropoff_date, $dropoff_time);

        // Add currency information
        $currency = Settings::get('currency', 'TRY');
        $currency_position = Settings::get('currency_position', 'right_space');

        $response_data = [
            'ok' => $result['ok'],
            'code' => $result['code'],
            'message' => $result['message'],
            'currency' => $currency,
            'currency_position' => $currency_position,
        ];

        // Add additional information on success
        if ($result['ok']) {
            $response_data = array_merge($response_data, [
                'days' => $result['days'],
                'price_per_day' => $result['price_per_day'],
                'total_price' => $result['total_price'],
                'start_ts' => $result['start_ts'],
                'end_ts' => $result['end_ts'],
            ]);
        }

        // Add alternative vehicles (if available)
        if (isset($result['alternatives']) && !empty($result['alternatives'])) {
            $response_data['alternatives'] = $result['alternatives'];
        }

        return new \WP_REST_Response($response_data, $result['ok'] ? 200 : 400);
    }
}
