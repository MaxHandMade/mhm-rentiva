<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Settings\Groups;

if (!defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\Settings\Core\SettingsCore;

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

	public const SECTION_LOGS = 'mhmrentiva_logs_section';

	/**
	 * Get default settings for logs
	 *
	 * @return array
	 */
	public static function get_default_settings(): array {
		return array(
			'mhmrentiva_log_level'           => 'error',
			'mhmrentiva_log_cleanup_enabled' => '1',
			'mhmrentiva_log_retention_days'  => 30,
			'mhmrentiva_debug_mode'          => '0',
		);
	}

	/**
	 * Register settings.
	 *
	 * T8 Görev 10c-A (K5-F3): this used to call add_settings_section() +
	 * 4 SettingsHelper::*_field() calls for self::SECTION_LOGS, plus a
	 * render_settings_section()/render_section_description() pair to display
	 * them. That whole surface was deleted -- zero caller anywhere reached
	 * self::SECTION_LOGS: not a group_class on any TabRendererRegistry tab,
	 * not named in any tab's $sections list (unlike MaintenanceSettings'
	 * SECTION_ID, which the 'system' tab's $sections list DOES include --
	 * grep-verified, see task-10c-A-report.md), and no other class ever
	 * calls do_settings_fields() against this section id. No admin could
	 * ever reach these 4 fields by any path. Left as a no-op (not removed
	 * outright) because SettingsCore::register_sub_groups() calls
	 * LogsSettings::register() unconditionally whenever class_exists() +
	 * method_exists() hold -- same shape as AddCustomerPage::register()
	 * after Görev 10b's A12.
	 */
	public static function register(): void {
	}

	// Static Accessors
	public static function get_log_level(): string {
		return (string) SettingsCore::get( 'mhmrentiva_log_level', 'error' );
	}
	public static function is_log_cleanup_enabled(): bool {
		return SettingsCore::get( 'mhmrentiva_log_cleanup_enabled', '1' ) === '1';
	}
	public static function get_log_retention_days(): int {
		return (int) SettingsCore::get( 'mhmrentiva_log_retention_days', 30 );
	}
	public static function is_debug_mode_enabled(): bool {
		return SettingsCore::get( 'mhmrentiva_debug_mode', '0' ) === '1';
	}
}
