<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Core\Utilities;

if (!defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Debug Helper - Manages WordPress Debug Mode
 *
 * Enables debug mode in development environment and catches errors
 */
final class DebugHelper {


	public static function register(): void {
		// Enable debug mode (only in development)
		if ( self::is_development_environment() ) {
			self::enable_debug_mode();
			self::setup_error_handling();
		}
	}

	/**
	 * Development environment check
	 */
	private static function is_development_environment(): bool {
		$http_host = (string) filter_input( INPUT_SERVER, 'HTTP_HOST', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		// Localhost check
		if ( strpos( $http_host, 'localhost' ) !== false ) {
			return true;
		}

		// XAMPP check
		if ( strpos( $http_host, '127.0.0.1' ) !== false ) {
			return true;
		}

		// WP_DEBUG check
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			return true;
		}

		return false;
	}

	/**
	 * Enable debug mode
	 */
	private static function enable_debug_mode(): void {
		// Enable WordPress debug mode
		if ( ! defined( 'WP_DEBUG' ) ) {
			define( 'WP_DEBUG', true );
		}

		if ( ! defined( 'WP_DEBUG_LOG' ) ) {
			define( 'WP_DEBUG_LOG', true );
		}

		if ( ! defined( 'WP_DEBUG_DISPLAY' ) ) {
			define( 'WP_DEBUG_DISPLAY', false );
		}

		if ( ! defined( 'SCRIPT_DEBUG' ) ) {
			define( 'SCRIPT_DEBUG', true );
		}

		// REST API debug - PERFORMANCE OPTIMIZATION: Disabled
		// if (!defined('REST_REQUEST')) {
		// define('REST_REQUEST', true);
		// }
	}

	/**
	 * Error handling setup
	 */
	private static function setup_error_handling(): void {
		// PHP error reporting
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.prevent_path_disclosure_error_reporting -- Runtime error level is intentionally managed by plugin debug mode.
		error_reporting( E_ALL );

		// WordPress error handling
		add_action( 'wp_loaded', array( self::class, 'log_rest_api_errors' ) );
		add_action( 'rest_api_init', array( self::class, 'log_rest_api_init' ) );
	}

	/**
	 * Log REST API errors
	 */
	public static function log_rest_api_errors(): void {
		// Debug logging disabled for performance optimization
	}

	/**
	 * Log REST API init
	 */
	public static function log_rest_api_init(): void {
		// Debug logging disabled for performance optimization
	}

	/**
	 * Log REST API requests
	 */
	public static function log_rest_request( $response, $handler, $request ): mixed {
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			$route  = $request->get_route();
			$method = $request->get_method();

			AdvancedLogger::debug(
				'REST API Request',
				array(
					'method' => $method,
					'route'  => $route,
				),
				AdvancedLogger::CATEGORY_API
			);
			if ( is_wp_error( $response ) ) {
				AdvancedLogger::error( 'REST API Error', array( 'error' => $response->get_error_message() ), AdvancedLogger::CATEGORY_API );
			}
		}

		return $response;
	}

	/**
	 * Get debug information
	 */
	public static function get_debug_info(): array {
		return array(
			'wp_debug'                    => defined( 'WP_DEBUG' ) ? WP_DEBUG : false,
			'wp_debug_log'                => defined( 'WP_DEBUG_LOG' ) ? WP_DEBUG_LOG : false,
			'wp_debug_display'            => defined( 'WP_DEBUG_DISPLAY' ) ? WP_DEBUG_DISPLAY : false,
			'script_debug'                => defined( 'SCRIPT_DEBUG' ) ? SCRIPT_DEBUG : false,
			'vehicle_post_type_exists'    => post_type_exists( 'vehicle' ),
			'rest_api_available'          => function_exists( 'rest_url' ),
			'current_user_can_edit_posts' => current_user_can( 'edit_posts' ),
			'php_version'                 => PHP_VERSION,
			'wordpress_version'           => get_bloginfo( 'version' ),
			'theme'                       => get_template(),
			'plugins'                     => get_option( 'active_plugins', array() ),
		);
	}
}
