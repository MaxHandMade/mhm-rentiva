<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Core\Utilities;

use MHMRentiva\Admin\Core\Utilities\UXHelper;
use MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Error Handling Helper
 *
 * Centralizes inconsistent error-handling patterns.
 */
final class ErrorHandler {



	/**
	 * Error log prefix
	 */
	private const LOG_PREFIX = 'MHM Rentiva';

	/**
	 * Error types
	 */
	public const TYPE_SECURITY   = 'security';
	public const TYPE_VALIDATION = 'validation';
	public const TYPE_DATABASE   = 'database';
	public const TYPE_BUSINESS   = 'business';
	public const TYPE_SYSTEM     = 'system';

	/**
	 * Error levels
	 */
	public const LEVEL_LOW      = 'low';
	public const LEVEL_MEDIUM   = 'medium';
	public const LEVEL_HIGH     = 'high';
	public const LEVEL_CRITICAL = 'critical';

	/**
	 * Security error – terminate with wp_die.
	 */
	public static function security_error( string $message, string $title = '' ): void {
		$user_message = UXHelper::get_user_friendly_error(
			UXHelper::ERROR_TYPE_PERMISSION,
			'access_denied',
			array( 'reason' => 'security_violation' )
		);

		$title = $title ?: esc_html__( 'Security Error', 'mhm-rentiva' );
		self::log_error( $message, self::TYPE_SECURITY, self::LEVEL_CRITICAL );
		wp_die( esc_html( $user_message ), esc_html( $title ), array( 'response' => 403 ) );
	}

	/**
	 * Permission error – terminate with wp_die.
	 */
	public static function permission_error( string $message = '' ): void {
		$user_message = $message ?: UXHelper::get_user_friendly_error(
			UXHelper::ERROR_TYPE_PERMISSION,
			'access_denied'
		);

		self::log_error( $message, self::TYPE_SECURITY, self::LEVEL_HIGH );
		wp_die( esc_html( $user_message ), esc_html__( 'Permission Error', 'mhm-rentiva' ), array( 'response' => 403 ) );
	}

	/**
	 * Validation error – return WP_Error.
	 */
	public static function validation_error( string $message, string $code = 'validation_error' ): \WP_Error {
		self::log_error( $message, self::TYPE_VALIDATION, self::LEVEL_MEDIUM );
		return new \WP_Error( $code, $message );
	}

	/**
	 * Database error – throw exception.
	 */
	public static function database_error( string $message, ?\Exception $previous = null ): void {
		self::log_error( $message, self::TYPE_DATABASE, self::LEVEL_HIGH );
		throw new \Exception( esc_html( $message ), 0, $previous ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
	}

	/**
	 * Business logic error – throw exception.
	 */
	public static function business_error( string $message, string $code = '' ): void {
		self::log_error( $message, self::TYPE_BUSINESS, self::LEVEL_MEDIUM );
		throw new \Exception( esc_html( $message ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
	}

	/**
	 * System error – throw exception.
	 */
	public static function system_error( string $message, ?\Exception $previous = null ): void {
		self::log_error( $message, self::TYPE_SYSTEM, self::LEVEL_CRITICAL );
		throw new \Exception( esc_html( $message ), 0, $previous ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
	}

	/**
	 * REST API error – return WP_REST_Response.
	 */
	public static function rest_error( string $message, int $status_code = 400, string $code = 'error' ): \WP_REST_Response {
		self::log_error( $message, self::TYPE_SYSTEM, self::LEVEL_MEDIUM );
		return new \WP_REST_Response(
			array(
				'ok'      => false,
				'message' => $message,
				'code'    => $code,
			),
			$status_code
		);
	}

	/**
	 * AJAX error – wp_send_json_error.
	 */
	public static function ajax_error( string $message, int $status_code = 400 ): void {
		self::log_error( $message, self::TYPE_SYSTEM, self::LEVEL_MEDIUM );
		wp_send_json_error( array( 'message' => $message ), $status_code );
	}

	/**
	 * AJAX success – wp_send_json_success.
	 */
	public static function ajax_success( array $data = array(), string $message = '' ): void {
		$response = $data;
		if ( $message ) {
			$response['message'] = $message;
		}
		wp_send_json_success( $response );
	}

	/**
	 * Log error message.
	 */
	public static function log_error( string $message, string $type = self::TYPE_SYSTEM, string $level = self::LEVEL_MEDIUM ): void {
		if ( class_exists( '\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger' ) ) {
			$context = array( 'original_level' => $level );

			if ( $type === self::TYPE_SECURITY ) {
				\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::security( $message, $context );
				return;
			}

			switch ( $level ) {
				case self::LEVEL_CRITICAL:
					if ( method_exists( '\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger', 'critical' ) ) {
						\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::critical( $message, $context, $type );
					} else {
						\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::error( $message, $context, $type );
					}
					break;
				case self::LEVEL_HIGH:
					\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::error( $message, $context, $type );
					break;
				case self::LEVEL_MEDIUM:
					\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::warning( $message, $context, $type );
					break;
				case self::LEVEL_LOW:
				default:
					\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::info( $message, $context, $type );
					break;
			}
		} else {
			$log_message = sprintf(
				'[%s] [%s] [%s] %s',
				self::LOG_PREFIX,
				strtoupper( $type ),
				strtoupper( $level ),
				$message
			);

			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Fallback logger for fatal paths when custom logger is unavailable.
			error_log( $log_message );
		}
	}

	/**
	 * Catch exception and log.
	 */
	public static function catch_exception( \Exception $e, string $context = '' ): void {
		$message = $context ? "{$context}: {$e->getMessage()}" : $e->getMessage();
		self::log_error( $message, self::TYPE_SYSTEM, self::LEVEL_HIGH );
	}

	/**
	 * Display WordPress admin notice.
	 */
	public static function show_admin_notice( string $message, string $type = 'error' ): void {
		add_action(
			'admin_notices',
			function () use ( $message, $type ) {
				printf(
					'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
					esc_attr( $type ),
					esc_html( $message )
				);
			}
		);
	}

	/**
	 * Display frontend notice.
	 */
	public static function show_frontend_notice( string $message, string $type = 'error' ): string {
		$class = $type === 'error' ? 'notice-error' : 'notice-success';
		return sprintf(
			'<div class="notice %s"><p>%s</p></div>',
			esc_attr( $class ),
			esc_html( $message )
		);
	}

	/**
	 * Handle generic error result and normalize response.
	 */
	public static function handle_error( mixed $result, string $fallback_message = '' ): mixed {
		if ( is_wp_error( $result ) ) {
			$message = $result->get_error_message() ?: $fallback_message;
			self::log_error( $message, self::TYPE_SYSTEM, self::LEVEL_MEDIUM );
			return false;
		}

		if ( $result === false ) {
			$message = $fallback_message ?: esc_html__( 'Operation failed.', 'mhm-rentiva' );
			self::log_error( $message, self::TYPE_SYSTEM, self::LEVEL_MEDIUM );
			return false;
		}

		return $result;
	}
}

