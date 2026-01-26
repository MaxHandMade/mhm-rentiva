<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Settings\Groups;

use MHMRentiva\Admin\Settings\Core\SettingsCore;
use MHMRentiva\Admin\Settings\Core\SettingsHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Logs & Debugging Settings Group
 *
 * Manages system logging levels, retention policies, and debug modes.
 * Optimized for high performance and standardized rendering.
 */
final class LogsSettings {

	public const SECTION_LOGS = 'mhm_rentiva_logs_section';

	/**
	 * Get default settings for logs
	 *
	 * @return array
	 */
	public static function get_default_settings(): array {
		return array(
			'mhm_rentiva_log_level'           => 'error',
			'mhm_rentiva_log_cleanup_enabled' => '1',
			'mhm_rentiva_log_retention_days'  => 30,
			'mhm_rentiva_debug_mode'          => '0',
		);
	}

	/**
	 * Render the settings section
	 */
	public static function render_settings_section(): void {
		if ( class_exists( '\MHMRentiva\Admin\Settings\View\SettingsViewHelper' ) ) {
			\MHMRentiva\Admin\Settings\View\SettingsViewHelper::render_section_cleanly( self::SECTION_LOGS );
		}
	}

	/**
	 * Register settings
	 */
	public static function register(): void {
		$page_slug = SettingsCore::PAGE;

		add_settings_section(
			self::SECTION_LOGS,
			__( 'Logs & Debugging', 'mhm-rentiva' ),
			array( self::class, 'render_section_description' ),
			$page_slug
		);

		SettingsHelper::select_field(
			$page_slug,
			'mhm_rentiva_log_level',
			__( 'Log Level', 'mhm-rentiva' ),
			array(
				'error'   => __( 'Error (Recommended)', 'mhm-rentiva' ),
				'warning' => __( 'Warning', 'mhm-rentiva' ),
				'info'    => __( 'Info', 'mhm-rentiva' ),
				'debug'   => __( 'Debug (All Details)', 'mhm-rentiva' ),
			),
			__( 'Set the minimum severity level for logs to be recorded.', 'mhm-rentiva' ),
			self::SECTION_LOGS
		);

		SettingsHelper::checkbox_field(
			$page_slug,
			'mhm_rentiva_debug_mode',
			__( 'Debug Mode', 'mhm-rentiva' ),
			__( 'Displays additional technical details in error messages. Not recommended for live sites.', 'mhm-rentiva' ),
			self::SECTION_LOGS
		);

		SettingsHelper::checkbox_field(
			$page_slug,
			'mhm_rentiva_log_cleanup_enabled',
			__( 'Auto Cleanup Logs', 'mhm-rentiva' ),
			__( 'Automatically delete old logs based on retention period.', 'mhm-rentiva' ),
			self::SECTION_LOGS
		);

		SettingsHelper::number_field(
			$page_slug,
			'mhm_rentiva_log_retention_days',
			__( 'Log Retention (Days)', 'mhm-rentiva' ),
			1,
			365,
			__( 'How many days to keep logs before deleting them.', 'mhm-rentiva' ),
			self::SECTION_LOGS
		);
	}

	public static function render_section_description(): void {
		echo '<p>' . esc_html__( 'Configure system logging levels and maintenance policies.', 'mhm-rentiva' ) . '</p>';
	}

	// Static Accessors
	public static function get_log_level(): string {
		return (string) SettingsCore::get( 'mhm_rentiva_log_level', 'error' );
	}
	public static function is_log_cleanup_enabled(): bool {
		return SettingsCore::get( 'mhm_rentiva_log_cleanup_enabled', '1' ) === '1';
	}
	public static function get_log_retention_days(): int {
		return (int) SettingsCore::get( 'mhm_rentiva_log_retention_days', 30 );
	}
	public static function is_debug_mode_enabled(): bool {
		return SettingsCore::get( 'mhm_rentiva_debug_mode', '0' ) === '1';
	}
}
