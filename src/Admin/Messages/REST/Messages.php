<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Messages\REST;

if (!defined('ABSPATH')) {
    exit;
}

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

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Messages {

	public static function register(): void {
		if ( ! Mode::canUseMessages() ) {
			return;
		}

		add_action( 'rest_api_init', array( self::class, 'register_routes' ) );
	}

	public static function register_routes(): void {
		// Admin endpoints
		register_rest_route(
			'mhm-rentiva/v1',
			'/messages',
			array(
				'methods'             => 'GET',
				'callback'            => array( GetMessages::class, 'handle' ),
				'permission_callback' => array( Auth::class, 'adminPermissionsCheck' ),
				'args'                => array(
					'status'   => array(
						'type'     => 'string',
						'enum'     => array_keys( Message::get_statuses() ),
						'required' => false,
					),
					'category' => array(
						'type'     => 'string',
						'enum'     => array_keys( Message::get_categories() ),
						'required' => false,
					),
					'per_page' => array(
						'type'     => 'integer',
						'minimum'  => 1,
						'maximum'  => 100,
						'default'  => 20,
						'required' => false,
					),
					'page'     => array(
						'type'     => 'integer',
						'minimum'  => 1,
						'default'  => 1,
						'required' => false,
					),
				),
			)
		);

		register_rest_route(
			'mhm-rentiva/v1',
			'/messages/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( GetMessage::class, 'handle' ),
				'permission_callback' => array( Auth::class, 'adminPermissionsCheck' ),
				'args'                => array(
					'id' => array(
						'type'     => 'integer',
						'required' => true,
					),
				),
			)
		);

		register_rest_route(
			'mhm-rentiva/v1',
			'/messages/(?P<id>\d+)/status',
			array(
				'methods'             => 'POST',
				'callback'            => array( UpdateStatus::class, 'handle' ),
				'permission_callback' => array( Auth::class, 'adminPermissionsCheck' ),
				'args'                => array(
					'id'     => array(
						'type'     => 'integer',
						'required' => true,
					),
					'status' => array(
						'type'     => 'string',
						'enum'     => array_keys( Message::get_statuses() ),
						'required' => true,
					),
				),
			)
		);

		register_rest_route(
			'mhm-rentiva/v1',
			'/messages/(?P<id>\d+)/reply',
			array(
				'methods'             => 'POST',
				'callback'            => array( ReplyToMessage::class, 'handle' ),
				'permission_callback' => array( Auth::class, 'adminPermissionsCheck' ),
				'args'                => array(
					'id'           => array(
						'type'     => 'integer',
						'required' => true,
					),
					'message'      => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'wp_kses_post',
					),
					'close_thread' => array(
						'type'     => 'boolean',
						'default'  => false,
						'required' => false,
					),
				),
			)
		);

		// Customer endpoints (WordPress User Auth)
		register_rest_route(
			'mhm-rentiva/v1',
			'/customer/messages',
			array(
				'methods'             => 'GET',
				'callback'            => array( CustomerGetMessages::class, 'handle' ),
				'permission_callback' => 'is_user_logged_in', // WordPress login check
			)
		);

		register_rest_route(
			'mhm-rentiva/v1',
			'/customer/messages',
			array(
				'methods'             => 'POST',
				'callback'            => array( SendMessage::class, 'handle' ),
				'permission_callback' => 'is_user_logged_in', // WordPress login check
				'args'                => array(
					'category'   => array(
						'type'     => 'string',
						'enum'     => array_keys( Message::get_categories() ),
						'required' => true,
					),
					'subject'    => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'message'    => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'wp_kses_post',
					),
					'booking_id' => array(
						'type'     => 'integer',
						'required' => false,
					),
					'priority'   => array(
						'type'     => 'string',
						'enum'     => array( 'normal', 'high', 'urgent' ),
						'required' => false,
						'default'  => 'normal',
					),
				),
			)
		);

		register_rest_route(
			'mhm-rentiva/v1',
			'/customer/messages/thread/(?P<thread_id>[a-zA-Z0-9\-]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( GetThread::class, 'handle' ),
				'permission_callback' => 'is_user_logged_in', // WordPress login check
				'args'                => array(
					'thread_id' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);

		register_rest_route(
			'mhm-rentiva/v1',
			'/customer/messages/reply',
			array(
				'methods'             => 'POST',
				'callback'            => array( SendReply::class, 'handle' ),
				'permission_callback' => 'is_user_logged_in', // WordPress login check
				'args'                => array(
					'thread_id' => array(
						'type'              => 'string',
						'required'          => true,
						'validate_callback' => function ( $param ) {
							// Accept both integer (numeric string) and UUID
							return is_numeric( $param ) || preg_match( '/^[a-zA-Z0-9\-]+$/', $param );
						},
					),
					'message'   => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'wp_kses_post',
					),
				),
			)
		);

		// Customer bookings endpoint (for messages form)
		register_rest_route(
			'mhm-rentiva/v1',
			'/customer/bookings',
			array(
				'methods'             => 'GET',
				'callback'            => array( GetBookings::class, 'handle' ),
				'permission_callback' => 'is_user_logged_in', // WordPress login check
			)
		);

		// Customer close message endpoint
		register_rest_route(
			'mhm-rentiva/v1',
			'/customer/messages/close',
			array(
				'methods'             => 'POST',
				'callback'            => array( CloseMessage::class, 'handle' ),
				'permission_callback' => 'is_user_logged_in', // WordPress login check
				'args'                => array(
					'thread_id' => array(
						'type'              => 'string',
						'required'          => true,
						'validate_callback' => function ( $param ) {
							// Accept both integer (numeric string) and UUID
							return is_numeric( $param ) || preg_match( '/^[a-zA-Z0-9\-]+$/', $param );
						},
					),
				),
			)
		);
	}
}
