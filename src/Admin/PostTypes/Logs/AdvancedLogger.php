<?php

declare(strict_types=1);
// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query,WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value,WordPress.DB.SlowDBQuery.slow_db_query_tax_query,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Advanced logger intentionally writes/reads bounded operational log data.

namespace MHMRentiva\Admin\PostTypes\Logs;

use MHMRentiva\Admin\Settings\Settings;
use MHMRentiva\Admin\PostTypes\Utilities\ClientUtilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Advanced Logging System.
 *
 * Handles detailed and categorized logging, including different log levels,
 * categories, and performance monitoring.
 */
final class AdvancedLogger {



	/**
	 * Log levels.
	 */
	public const LEVEL_DEBUG    = 'debug';
	public const LEVEL_INFO     = 'info';
	public const LEVEL_WARNING  = 'warning';
	public const LEVEL_ERROR    = 'error';
	public const LEVEL_CRITICAL = 'critical';

	/**
	 * Log categories.
	 */
	public const CATEGORY_PAYMENT     = 'payment';
	public const CATEGORY_BOOKING     = 'booking';
	public const CATEGORY_VEHICLE     = 'vehicle';
	public const CATEGORY_CUSTOMER    = 'customer';
	public const CATEGORY_SYSTEM      = 'system';
	public const CATEGORY_SECURITY    = 'security';
	public const CATEGORY_PERFORMANCE = 'performance';
	public const CATEGORY_API         = 'api';

	/**
	 * Performance metrics.
	 */
	private static array $performance_metrics = array();
	private static array $memory_usage        = array();

	/**
	 * Saves a detailed log entry.
	 *
	 * @param array{
	 *  level: string,
	 *  category: string,
	 *  message: string,
	 *  context?: array<string,mixed>,
	 *  user_id?: int,
	 *  ip_address?: string,
	 *  user_agent?: string,
	 *  booking_id?: int,
	 *  vehicle_id?: int,
	 *  customer_id?: int,
	 *  execution_time?: float,
	 *  memory_usage?: int,
	 *  stack_trace?: bool
	 * } $args
	 */
	public static function log( array $args ): int {
		$level    = strtolower( (string) ( $args['level'] ?? self::LEVEL_INFO ) );
		$category = strtolower( (string) ( $args['category'] ?? self::CATEGORY_SYSTEM ) );
		$message  = (string) ( $args['message'] ?? '' );

		if ( empty( $message ) ) {
			return 0;
		}

		// Check if the log level should be skipped (e.g., debug logs in production).
		if ( self::should_skip_log( $level ) ) {
			return 0;
		}

		$context             = (array) ( $args['context'] ?? array() );
		$user_id             = isset( $args['user_id'] ) ? (int) $args['user_id'] : get_current_user_id();
		$ip_address          = ClientUtilities::get_client_ip();
		$user_agent          = self::get_user_agent();
		$booking_id          = isset( $args['booking_id'] ) ? (int) $args['booking_id'] : 0;
		$vehicle_id          = isset( $args['vehicle_id'] ) ? (int) $args['vehicle_id'] : 0;
		$customer_id         = isset( $args['customer_id'] ) ? (int) $args['customer_id'] : 0;
		$execution_time      = isset( $args['execution_time'] ) ? (float) $args['execution_time'] : 0;
		$memory_usage        = isset( $args['memory_usage'] ) ? (int) $args['memory_usage'] : memory_get_usage( true );
		$include_stack_trace = (bool) ( $args['stack_trace'] ?? false );

		// Create post title.
		$title_parts = array(
			'[' . strtoupper( $level ) . ']',
			'[' . strtoupper( $category ) . ']',
			$message,
		);
		$title       = implode( ' ', $title_parts );

		// Create post content.
		$content_parts = array(
			'Message: ' . $message,
			'Timestamp: ' . current_time( 'Y-m-d H:i:s' ),
			'User ID: ' . $user_id,
			'IP Address: ' . $ip_address,
		);

		if ( $booking_id > 0 ) {
			$content_parts[] = 'Booking ID: ' . $booking_id;
		}
		if ( $vehicle_id > 0 ) {
			$content_parts[] = 'Vehicle ID: ' . $vehicle_id;
		}
		if ( $customer_id > 0 ) {
			$content_parts[] = 'Customer ID: ' . $customer_id;
		}
		if ( $execution_time > 0 ) {
			$content_parts[] = 'Execution Time: ' . $execution_time . 'ms';
		}
		if ( $memory_usage > 0 ) {
			$content_parts[] = 'Memory Usage: ' . size_format( $memory_usage );
		}

		if ( ! empty( $context ) ) {
			$content_parts[] = 'Context: ' . wp_json_encode( $context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
		}

		if ( $include_stack_trace && in_array( $level, array( self::LEVEL_ERROR, self::LEVEL_CRITICAL ), true ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace -- Intentional for error logging in production
			$content_parts[] = 'Stack Trace: ' . wp_json_encode( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
		}

		$content = implode( "\n", $content_parts );

		// Create the post array for insertion.
		$post_arr = array(
			'post_type'    => PostType::TYPE,
			'post_status'  => 'publish',
			'post_title'   => $title,
			'post_content' => $content,
		);

		$post_id = (int) wp_insert_post( $post_arr, true );
		if ( is_wp_error( $post_id ) || $post_id <= 0 ) {
			return 0;
		}

		// Save meta data.
		update_post_meta( $post_id, '_mhm_log_level', $level );
		update_post_meta( $post_id, '_mhm_log_category', $category );
		update_post_meta( $post_id, '_mhm_log_user_id', $user_id );
		update_post_meta( $post_id, '_mhm_log_ip_address', $ip_address );
		update_post_meta( $post_id, '_mhm_log_user_agent', $user_agent );

		if ( $booking_id > 0 ) {
			update_post_meta( $post_id, '_mhm_log_booking_id', $booking_id );
		}
		if ( $vehicle_id > 0 ) {
			update_post_meta( $post_id, '_mhm_log_vehicle_id', $vehicle_id );
		}
		if ( $customer_id > 0 ) {
			update_post_meta( $post_id, '_mhm_log_customer_id', $customer_id );
		}
		if ( $execution_time > 0 ) {
			update_post_meta( $post_id, '_mhm_log_execution_time', $execution_time );
		}
		if ( $memory_usage > 0 ) {
			update_post_meta( $post_id, '_mhm_log_memory_usage', $memory_usage );
		}

		// Update performance metrics.
		self::update_performance_metrics( $category, $execution_time, $memory_usage );

		// Send an email notification for critical errors.
		if ( self::LEVEL_CRITICAL === $level ) {
			self::send_critical_alert( $post_id, $title, $message, $context );
		}

		return $post_id;
	}

	/**
	 * Starts performance tracking for a specific operation.
	 */
	public static function start_performance_tracking( string $operation ): void {
		self::$performance_metrics[ $operation ] = array(
			'start_time'   => microtime( true ),
			'start_memory' => memory_get_usage( true ),
		);
	}

	/**
	 * Ends performance tracking for an operation and logs the result.
	 */
	public static function end_performance_tracking( string $operation, string $category = self::CATEGORY_PERFORMANCE ): int {
		if ( ! isset( self::$performance_metrics[ $operation ] ) ) {
			return 0;
		}

		$start_data = self::$performance_metrics[ $operation ];
		$end_time   = microtime( true );
		$end_memory = memory_get_usage( true );

		$execution_time = ( $end_time - $start_data['start_time'] ) * 1000; // Convert to milliseconds.
		$memory_usage   = $end_memory - $start_data['start_memory'];

		unset( self::$performance_metrics[ $operation ] );

		return self::log(
			array(
				'level'          => self::LEVEL_INFO,
				'category'       => $category,
				'message'        => "Performance tracking for operation: {$operation}",
				'context'        => array(
					'operation'   => $operation,
					'peak_memory' => memory_get_peak_usage( true ),
				),
				'execution_time' => $execution_time,
				'memory_usage'   => $memory_usage,
			)
		);
	}

	/**
	 * Logs an error message.
	 */
	public static function error( string $message, array $context = array(), string $category = self::CATEGORY_SYSTEM ): int {
		return self::log(
			array(
				'level'       => self::LEVEL_ERROR,
				'category'    => $category,
				'message'     => $message,
				'context'     => $context,
				'stack_trace' => true,
			)
		);
	}

	/**
	 * Logs a critical error message.
	 */
	public static function critical( string $message, array $context = array(), string $category = self::CATEGORY_SYSTEM ): int {
		return self::log(
			array(
				'level'       => self::LEVEL_CRITICAL,
				'category'    => $category,
				'message'     => $message,
				'context'     => $context,
				'stack_trace' => true,
			)
		);
	}

	/**
	 * Logs a warning message.
	 */
	public static function warning( string $message, array $context = array(), string $category = self::CATEGORY_SYSTEM ): int {
		return self::log(
			array(
				'level'    => self::LEVEL_WARNING,
				'category' => $category,
				'message'  => $message,
				'context'  => $context,
			)
		);
	}

	/**
	 * Logs an informational message.
	 */
	public static function info( string $message, array $context = array(), string $category = self::CATEGORY_SYSTEM ): int {
		return self::log(
			array(
				'level'    => self::LEVEL_INFO,
				'category' => $category,
				'message'  => $message,
				'context'  => $context,
			)
		);
	}

	/**
	 * Logs a debug message.
	 */
	public static function debug( string $message, array $context = array(), string $category = self::CATEGORY_SYSTEM ): int {
		return self::log(
			array(
				'level'    => self::LEVEL_DEBUG,
				'category' => $category,
				'message'  => $message,
				'context'  => $context,
			)
		);
	}

	/**
	 * Logs a security-related event.
	 */
	public static function security( string $message, array $context = array() ): int {
		return self::log(
			array(
				'level'      => self::LEVEL_WARNING,
				'category'   => self::CATEGORY_SECURITY,
				'message'    => $message,
				'context'    => $context,
				'ip_address' => ClientUtilities::get_client_ip(),
				'user_agent' => self::get_user_agent(),
			)
		);
	}

	/**
	 * Logs an API-related event.
	 */
	public static function api( string $message, array $context = array() ): int {
		return self::log(
			array(
				'level'    => self::LEVEL_INFO,
				'category' => self::CATEGORY_API,
				'message'  => $message,
				'context'  => $context,
			)
		);
	}

	/**
	 * Logs a payment-related event.
	 */
	public static function payment( string $message, array $context = array(), int $booking_id = 0 ): int {
		return self::log(
			array(
				'level'      => self::LEVEL_INFO,
				'category'   => self::CATEGORY_PAYMENT,
				'message'    => $message,
				'context'    => $context,
				'booking_id' => $booking_id,
			)
		);
	}

	/**
	 * Logs a booking-related event.
	 */
	public static function booking( string $message, array $context = array(), int $booking_id = 0 ): int {
		return self::log(
			array(
				'level'      => self::LEVEL_INFO,
				'category'   => self::CATEGORY_BOOKING,
				'message'    => $message,
				'context'    => $context,
				'booking_id' => $booking_id,
			)
		);
	}

	/**
	 * Logs a vehicle-related event.
	 */
	public static function vehicle( string $message, array $context = array(), int $vehicle_id = 0 ): int {
		return self::log(
			array(
				'level'      => self::LEVEL_INFO,
				'category'   => self::CATEGORY_VEHICLE,
				'message'    => $message,
				'context'    => $context,
				'vehicle_id' => $vehicle_id,
			)
		);
	}

	/**
	 * Logs a customer-related event.
	 */
	public static function customer( string $message, array $context = array(), int $customer_id = 0 ): int {
		return self::log(
			array(
				'level'       => self::LEVEL_INFO,
				'category'    => self::CATEGORY_CUSTOMER,
				'message'     => $message,
				'context'     => $context,
				'customer_id' => $customer_id,
			)
		);
	}

	/**
	 * Retrieves log statistics.
	 *
	 * @note This query can be slow on sites with a large number of logs due to meta_query JOINs.
	 * For high-traffic sites, a custom table for logs would be more performant.
	 *
	 * @param int $days The number of days to retrieve stats for.
	 * @return array
	 */
	public static function get_log_stats( int $days = 7 ): array {
		global $wpdb;

		$date_limit = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		$stats = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
                pm1.meta_value as level,
                pm2.meta_value as category,
                COUNT(*) as count
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_mhm_log_level'
             LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_mhm_log_category'
             WHERE p.post_type = %s
             AND p.post_date >= %s
             GROUP BY pm1.meta_value, pm2.meta_value
             ORDER BY count DESC",
				PostType::TYPE,
				$date_limit
			),
			ARRAY_A
		);

		return $stats ?: array();
	}

	/**
	 * Gets the collected performance metrics.
	 */
	public static function get_performance_metrics(): array {
		return self::$performance_metrics;
	}

	/**
	 * Cleans up old logs based on retention days.
	 *
	 * @param int $days Retention period in days.
	 * @return int Number of deleted logs.
	 */
	public static function cleanup_old_logs( int $days = 30 ): int {
		if ( $days <= 0 ) {
			return 0;
		}

		global $wpdb;

		$date_limit = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );
		$post_type  = PostType::TYPE;

		// Using direct SQL for performance on potentially large datasets
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE a, b, c FROM {$wpdb->posts} a LEFT JOIN {$wpdb->term_relationships} b ON (a.ID = b.object_id) LEFT JOIN {$wpdb->postmeta} c ON (a.ID = c.post_id) WHERE a.post_type = %s AND a.post_date < %s",
				$post_type,
				$date_limit
			)
		);

		if ( $deleted > 0 ) {
			self::info(
				/* translators: %d: number of deleted log entries */
				sprintf( __( 'Cleaned up %d old log entries.', 'mhm-rentiva' ), (int) $deleted ),
				array(
					'days'       => $days,
					'date_limit' => $date_limit,
				),
				self::CATEGORY_SYSTEM
			);
		}

		return (int) $deleted;
	}


	/**
	 * Checks if a log entry should be skipped based on its level.
	 */
	private static function should_skip_log( string $level ): bool {
		// 1. Always allow critical errors
		if ( self::LEVEL_CRITICAL === $level ) {
			return false;
		}

		// 2. Map levels to integer weights
		$levels = array(
			self::LEVEL_DEBUG    => 0,
			self::LEVEL_INFO     => 1,
			self::LEVEL_WARNING  => 2,
			self::LEVEL_ERROR    => 3,
			self::LEVEL_CRITICAL => 4,
		);

		// 3. Get configured level from settings (default: error)
		// Defaults to 'error' (3) to be safe/quiet in production
		$configured_level_slug = \MHMRentiva\Admin\Settings\Core\SettingsCore::get( 'mhm_rentiva_log_level', self::LEVEL_ERROR );

		// Ensure the configured level exists in our map, fallback to 'error'
		if ( ! isset( $levels[ $configured_level_slug ] ) ) {
			$configured_level_slug = self::LEVEL_ERROR;
		}

		$configured_weight = $levels[ $configured_level_slug ];
		$current_weight    = $levels[ $level ] ?? 1; // Default to INFO weight if unknown

		// 4. Compare: If current log's weight is less than configured, SKIP it.
		// Example: Config='error'(3). Log='info'(1). 1 < 3 -> TRUE (Skip).
		if ( $current_weight < $configured_weight ) {
			return true;
		}

		// 5. Special check for Debug: Must also have WP_DEBUG or Force Debug Mode
		if ( self::LEVEL_DEBUG === $level ) {
			$plugin_debug = \MHMRentiva\Admin\Settings\Core\SettingsCore::get( 'mhm_rentiva_debug_mode', '0' ) === '1';
			$wp_debug     = defined( 'WP_DEBUG' ) && WP_DEBUG;

			// If neither Plugin Debug Mode nor WP_DEBUG is on, skip debug logs
			if ( ! $plugin_debug && ! $wp_debug ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Updates performance metrics for a given category.
	 */
	private static function update_performance_metrics( string $category, float $execution_time, int $memory_usage ): void {
		if ( ! isset( self::$memory_usage[ $category ] ) ) {
			self::$memory_usage[ $category ] = array();
		}

		self::$memory_usage[ $category ][] = array(
			'time'      => $execution_time,
			'memory'    => $memory_usage,
			'timestamp' => current_time( 'timestamp' ),
		);

		// Keep the last 100 records.
		if ( count( self::$memory_usage[ $category ] ) > 100 ) {
			self::$memory_usage[ $category ] = array_slice( self::$memory_usage[ $category ], -100 );
		}
	}

	/**
	 * Sends a notification for a critical error.
	 */
	private static function send_critical_alert( int $log_id, string $title, string $message, array $context ): void {
		$admin_email = get_option( 'admin_email' );
		if ( ! filter_var( $admin_email, FILTER_VALIDATE_EMAIL ) ) {
			return;
		}

		$subject = sprintf(
			/* translators: %s: Log entry title */
			__( '[MHM Rentiva] Critical Error: %s', 'mhm-rentiva' ),
			$title
		);

		$body_parts = array(
			__( 'A critical error was detected on your site.', 'mhm-rentiva' ),
			'',
			/* translators: %d: log ID */
			sprintf( __( 'Log ID: %d', 'mhm-rentiva' ), (int) $log_id ),
			/* translators: %s: error message */
			sprintf( __( 'Message: %s', 'mhm-rentiva' ), $message ),
			/* translators: %s: current timestamp */
			sprintf( /* translators: %s: timestamp */__( 'Timestamp: %s', 'mhm-rentiva' ), current_time( 'Y-m-d H:i:s' ) ),
			/* translators: %s: site URL */
			sprintf( /* translators: %s: site url */__( 'Site URL: %s', 'mhm-rentiva' ), esc_url( home_url() ) ),
		);

		if ( ! empty( $context ) ) {
			$body_parts[] = '';
			$body_parts[] = __( 'Context:', 'mhm-rentiva' );
			$body_parts[] = wp_json_encode( $context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
		}

		$body = implode( "\n", $body_parts );

		wp_mail( $admin_email, $subject, $body );
	}

	/**
	 * Gets the current user agent.
	 */
	private static function get_user_agent(): string {
		if ( ! isset( $_SERVER['HTTP_USER_AGENT'] ) ) {
			return 'unknown';
		}
		return sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) );
	}

	/**
	 * Handles legacy log calls from the old Logger::add() method.
	 *
	 * @deprecated This method is for backward compatibility and will be removed in the future.
	 *             Use the new specific methods like `payment()`, `booking()`, or the generic `log()` method.
	 */
	public static function add( array $args ): int {
		$gateway = strtolower( (string) ( $args['gateway'] ?? '' ) );
		$action  = strtolower( (string) ( $args['action'] ?? '' ) );
		$status  = strtolower( (string) ( $args['status'] ?? '' ) );

		if ( empty( $gateway ) || empty( $action ) || empty( $status ) ) {
			return 0;
		}

		$booking_id = isset( $args['booking_id'] ) ? (int) $args['booking_id'] : 0;
		$message    = (string) ( $args['message'] ?? '' );

		// Determine level and category from legacy format.
		$level    = ( 'success' === $status ) ? self::LEVEL_INFO : self::LEVEL_ERROR;
		$category = ( 'payment' === $gateway ) ? self::CATEGORY_PAYMENT : self::CATEGORY_SYSTEM;

		// Normalize amount to the smallest currency unit (kurus/cents).
		$amount_kurus = 0;
		if ( isset( $args['amount_kurus'] ) ) {
			$amount_kurus = max( 0, (int) $args['amount_kurus'] );
		} elseif ( isset( $args['amount'] ) ) {
			$amount = $args['amount'];
			if ( is_float( $amount ) || is_int( $amount ) ) {
				$amount_kurus = (int) round( ( (float) $amount ) * 100 );
			}
		}

		// Build the context array for the new log format.
		$context = array_merge(
			( $args['context'] ?? array() ),
			array(
				'gateway'      => $gateway,
				'action'       => $action,
				'status'       => $status,
				'amount_kurus' => $amount_kurus,
				'currency'     => strtoupper( (string) ( $args['currency'] ?? '' ) ),
				'oid'          => (string) ( $args['oid'] ?? '' ),
				'code'         => (string) ( $args['code'] ?? '' ),
			)
		);

		// Call the new universal log method.
		return self::log(
			array(
				'level'      => $level,
				'category'   => $category,
				'message'    => $message ?: ucfirst( $action ),
				'context'    => $context,
				'booking_id' => $booking_id,
			)
		);
	}
}
