<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Core\Utilities;

use MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger;
use MHMRentiva\Admin\Settings\Core\SettingsCore;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles scheduled log maintenance tasks.
 *
 * @since 4.6.0
 */
final class LogMaintenanceScheduler {

	/**
	 * Cron event hook name.
	 */
	public const CRON_HOOK = 'mhm_rentiva_daily_log_cleanup';

	/**
	 * Initialize the scheduler.
	 */
	public static function init(): void {
		add_action( self::CRON_HOOK, array( self::class, 'run_log_cleanup' ) );

		// Ensure the cron is scheduled if it's not already
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * Run the log cleanup task.
	 */
	public static function run_log_cleanup(): void {
		// Check if cleanup is enabled
		$enabled = SettingsCore::get( 'mhm_rentiva_log_cleanup_enabled', '0' );
		if ( $enabled !== '1' ) {
			return;
		}

		// Get retention days
		$retention_days = (int) SettingsCore::get( 'mhm_rentiva_log_retention_days', 30 );

		// Safety check: Don't delete logs newer than 1 day just in case configuration is wrong
		$retention_days = max( 1, $retention_days );

		if ( class_exists( AdvancedLogger::class ) ) {
			AdvancedLogger::cleanup_old_logs( $retention_days );
		}
	}

	/**
	 * Clear the scheduled event on deactivation.
	 */
	public static function deactivate(): void {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}
	}
}
