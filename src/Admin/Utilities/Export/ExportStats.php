<?php

declare(strict_types=1);
// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query,WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value,WordPress.DB.SlowDBQuery.slow_db_query_tax_query,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Export stats intentionally use aggregate queries and lightweight cache refresh logic.

namespace MHMRentiva\Admin\Utilities\Export;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Export Statistics Management
 */
final class ExportStats {

	private const OPTION_NAME  = 'mhm_rentiva_export_stats';
	private const CACHE_KEY    = 'mhm_rentiva_export_stats_cache';
	private const CACHE_EXPIRY = 3600; // 1 hour

	/**
	 * Get export statistics
	 */
	public static function get_stats(): array {
		// Try to get from cache first
		$cached = get_transient( self::CACHE_KEY );
		if ( $cached !== false ) {
			return $cached;
		}

		$stats = get_option( self::OPTION_NAME, array() );

		// Initialize default stats if empty
		if ( empty( $stats ) ) {
			$stats = self::get_default_stats();
			update_option( self::OPTION_NAME, $stats );
		}

		// Calculate real-time statistics
		$stats = array_merge( $stats, self::calculate_real_time_stats() );

		// Cache the results
		set_transient( self::CACHE_KEY, $stats, self::CACHE_EXPIRY );

		return $stats;
	}

	/**
	 * Get default statistics structure
	 */
	private static function get_default_stats(): array {
		return array(
			'total_exports'          => 0,
			'total_records_exported' => 0,
			'last_export_date'       => null,
			'last_export_type'       => null,
			'last_export_format'     => null,
			'export_types'           => array(
				'vehicle_booking' => 0,
				'mhm_payment_log' => 0,
				'reports'         => 0,
			),
			'export_formats'         => array(
				'csv'  => 0,
				'xls'  => 0,
				'json' => 0,
				'xml'  => 0,
			),
			'success_rate'           => 100.0,
			'created_at'             => current_time( 'mysql' ),
		);
	}

	/**
	 * Calculate real-time statistics
	 */
	private static function calculate_real_time_stats(): array {
		global $wpdb;

		// Count total vehicles - using prepare for post_type
		$vehicle_count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) 
            FROM {$wpdb->posts} 
            WHERE post_type = %s 
            AND post_status IN ('publish', 'pending', 'private')",
				'vehicle'
			)
		);

		// Count total bookings - using prepare for post_type
		$booking_count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) 
            FROM {$wpdb->posts} 
            WHERE post_type = %s 
            AND post_status IN ('publish', 'pending', 'private')",
				'vehicle_booking'
			)
		);

		// Count total payment logs - using prepare for post_type
		$payment_log_count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) 
            FROM {$wpdb->posts} 
            WHERE post_type = %s 
            AND post_status IN ('publish', 'pending', 'private')",
				'mhm_payment_log'
			)
		);

		return array(
			'current_data'            => array(
				'vehicles'     => (int) $vehicle_count,
				'bookings'     => (int) $booking_count,
				'payment_logs' => (int) $payment_log_count,
			),
			'total_available_records' => (int) ( $vehicle_count + $booking_count + $payment_log_count ),
		);
	}

	/**
	 * Update statistics after export
	 */
	public static function update_stats( string $export_type, string $format, int $record_count, bool $success = true ): void {
		$stats = self::get_stats();

		if ( $success ) {
			++$stats['total_exports'];
			$stats['total_records_exported'] += $record_count;
			$stats['last_export_date']        = current_time( 'mysql' );
			$stats['last_export_type']        = $export_type;
			$stats['last_export_format']      = $format;

			// Update export types count
			if ( isset( $stats['export_types'][ $export_type ] ) ) {
				++$stats['export_types'][ $export_type ];
			}

			// Update export formats count
			if ( isset( $stats['export_formats'][ $format ] ) ) {
				++$stats['export_formats'][ $format ];
			}

			// Calculate success rate
			$stats['success_rate'] = 100.0; // For now, assume all exports are successful
		}

		// Save updated stats
		update_option( self::OPTION_NAME, $stats );

		// Clear cache
		delete_transient( self::CACHE_KEY );
	}

	/**
	 * Get export statistics for display
	 */
	public static function get_display_stats(): array {
		$stats = self::get_stats();

		return array(
			'total_exports'     => number_format( $stats['total_exports'] ),
			'total_records'     => number_format( $stats['total_records_exported'] ),
			'last_export'       => $stats['last_export_date'] ?
				sprintf(
					/* translators: 1: %s; 2: %s. */
					__( '%1$s (%2$s format)', 'mhm-rentiva' ),
					date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $stats['last_export_date'] ) ),
					strtoupper( $stats['last_export_format'] ?? '' )
				) :
				__( 'Never', 'mhm-rentiva' ),
			'success_rate'      => number_format( $stats['success_rate'], 1 ) . '%',
			'current_data'      => $stats['current_data'],
			'available_records' => number_format( $stats['total_available_records'] ),
		);
	}

	/**
	 * Reset statistics
	 */
	public static function reset_stats(): void {
		delete_option( self::OPTION_NAME );
		delete_transient( self::CACHE_KEY );
	}

	/**
	 * Get export history (last 10 exports)
	 */
	public static function get_recent_exports( int $limit = 10 ): array {
		$history = get_option( 'mhm_rentiva_export_history', array() );

		// Sort by date (newest first)
		usort(
			$history,
			function ( $a, $b ) {
				return strtotime( $b['date'] ) - strtotime( $a['date'] );
			}
		);

		return array_slice( $history, 0, $limit );
	}

	/**
	 * Register hooks
	 */
	public static function register(): void {
		// Hook to update stats when export is completed
		add_action( 'mhm_rentiva_export_completed', array( self::class, 'update_stats' ), 10, 4 );

		// Clear cache when stats are updated
		add_action(
			'mhm_rentiva_export_stats_updated',
			function () {
				delete_transient( self::CACHE_KEY );
			}
		);
	}
}
