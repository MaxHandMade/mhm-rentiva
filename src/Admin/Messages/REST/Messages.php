<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Messages\REST;

use MHMRentiva\Admin\Licensing\Mode;
use MHMRentiva\Admin\PostTypes\Message\Message;
use MHMRentiva\Admin\Messages\REST\Admin\GetMessages;
use MHMRentiva\Admin\Messages\REST\Admin\GetMessage;
use MHMRentiva\Admin\Messages\REST\Admin\UpdateStatus;
use MHMRentiva\Admin\Messages\REST\Admin\ReplyToMessage;
use MHMRentiva\Admin\Messages\REST\Customer\GetMessages as CustomerGetMessages;
use MHMRentiva\Admin\Messages\REST\Customer\SendMessage;
use MHMRentiva\Admin\Messages\REST\Customer\GetThread;
use MHMRentiva\Admin\Messages\REST\Customer\SendReply;
use MHMRentiva\Admin\Messages\REST\Customer\GetBookings;
use MHMRentiva\Admin\Messages\REST\Customer\CloseMessage;
use MHMRentiva\Admin\Messages\REST\Helpers\Auth;

if (!defined('ABSPATH')) {
    exit;
}

final class Messages
{
    public static function register(): void
    {
        if (!Mode::featureEnabled(Mode::FEATURE_MESSAGES)) {
            return;
        }

        add_action('rest_api_init', [self::class, 'register_routes']);
    }

    public static function register_routes(): void
    {
        // Admin endpoints
        register_rest_route('mhm-rentiva/v1', '/messages', [
            'methods' => 'GET',
            'callback' => [GetMessages::class, 'handle'],
            'permission_callback' => [Auth::class, 'adminPermissionsCheck'],
            'args' => [
                'status' => [
                    'type' => 'string',
                    'enum' => array_keys(Message::get_statuses()),
                    'required' => false,
                ],
                'category' => [
                    'type' => 'string',
                    'enum' => array_keys(Message::get_categories()),
                    'required' => false,
                ],
                'per_page' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'maximum' => 100,
                    'default' => 20,
                    'required' => false,
                ],
                'page' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'default' => 1,
                    'required' => false,
                ],
            ],
        ]);

        register_rest_route('mhm-rentiva/v1', '/messages/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [GetMessage::class, 'handle'],
            'permission_callback' => [Auth::class, 'adminPermissionsCheck'],
            'args' => [
                'id' => [
                    'type' => 'integer',
                    'required' => true,
                ],
            ],
        ]);

        register_rest_route('mhm-rentiva/v1', '/messages/(?P<id>\d+)/status', [
            'methods' => 'POST',
            'callback' => [UpdateStatus::class, 'handle'],
            'permission_callback' => [Auth::class, 'adminPermissionsCheck'],
            'args' => [
                'id' => [
                    'type' => 'integer',
                    'required' => true,
                ],
                'status' => [
                    'type' => 'string',
                    'enum' => array_keys(Message::get_statuses()),
                    'required' => true,
                ],
            ],
        ]);

        register_rest_route('mhm-rentiva/v1', '/messages/(?P<id>\d+)/reply', [
            'methods' => 'POST',
            'callback' => [ReplyToMessage::class, 'handle'],
            'permission_callback' => [Auth::class, 'adminPermissionsCheck'],
            'args' => [
                'id' => [
                    'type' => 'integer',
                    'required' => true,
                ],
                'message' => [
                    'type' => 'string',
                    'required' => true,
                    'sanitize_callback' => 'wp_kses_post',
                ],
                'close_thread' => [
                    'type' => 'boolean',
                    'default' => false,
                    'required' => false,
                ],
            ],
        ]);

        // Customer endpoints (WordPress User Auth)
        register_rest_route('mhm-rentiva/v1', '/customer/messages', [
            'methods' => 'GET',
            'callback' => [CustomerGetMessages::class, 'handle'],
            'permission_callback' => 'is_user_logged_in', // WordPress login kontrolü
        ]);

        register_rest_route('mhm-rentiva/v1', '/customer/messages', [
            'methods' => 'POST',
            'callback' => [SendMessage::class, 'handle'],
            'permission_callback' => 'is_user_logged_in', // WordPress login kontrolü
            'args' => [
                'category' => [
                    'type' => 'string',
                    'enum' => array_keys(Message::get_categories()),
                    'required' => true,
                ],
                'subject' => [
                    'type' => 'string',
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'message' => [
                    'type' => 'string',
                    'required' => true,
                    'sanitize_callback' => 'wp_kses_post',
                ],
                'booking_id' => [
                    'type' => 'integer',
                    'required' => false,
                ],
                'priority' => [
                    'type' => 'string',
                    'enum' => ['normal', 'high', 'urgent'],
                    'required' => false,
                    'default' => 'normal',
                ],
            ],
        ]);

        register_rest_route('mhm-rentiva/v1', '/customer/messages/thread/(?P<thread_id>[a-zA-Z0-9\-]+)', [
            'methods' => 'GET',
            'callback' => [GetThread::class, 'handle'],
            'permission_callback' => 'is_user_logged_in', // WordPress login kontrolü
            'args' => [
                'thread_id' => [
                    'type' => 'string',
                    'required' => true,
                ],
            ],
        ]);

        register_rest_route('mhm-rentiva/v1', '/customer/messages/reply', [
            'methods' => 'POST',
            'callback' => [SendReply::class, 'handle'],
            'permission_callback' => 'is_user_logged_in', // WordPress login kontrolü
            'args' => [
                'thread_id' => [
                    'type' => 'string',
                    'required' => true,
                    'validate_callback' => function($param) {
                        // Accept both integer (numeric string) and UUID
                        return is_numeric($param) || preg_match('/^[a-zA-Z0-9\-]+$/', $param);
                    },
                ],
                'message' => [
                    'type' => 'string',
                    'required' => true,
                    'sanitize_callback' => 'wp_kses_post',
                ],
            ],
        ]);

        // Customer bookings endpoint (for messages form)
        register_rest_route('mhm-rentiva/v1', '/customer/bookings', [
            'methods' => 'GET',
            'callback' => [GetBookings::class, 'handle'],
            'permission_callback' => 'is_user_logged_in', // WordPress login kontrolü
        ]);

        // Customer close message endpoint
        register_rest_route('mhm-rentiva/v1', '/customer/messages/close', [
            'methods' => 'POST',
            'callback' => [CloseMessage::class, 'handle'],
            'permission_callback' => 'is_user_logged_in', // WordPress login kontrolü
            'args' => [
                'thread_id' => [
                    'type' => 'string',
                    'required' => true,
                    'validate_callback' => function($param) {
                        // Accept both integer (numeric string) and UUID
                        return is_numeric($param) || preg_match('/^[a-zA-Z0-9\-]+$/', $param);
                    },
                ],
            ],
        ]);
    }
}
