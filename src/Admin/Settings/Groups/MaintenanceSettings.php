<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Settings\Groups;

if (!defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\Settings\Core\SettingsCore;
use MHMRentiva\Admin\Settings\Core\SettingsHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maintenance & System Settings Group
 *
 * Provides a unified view for high-level system configurations,
 * performance settings, and maintenance tools.
 */
final class MaintenanceSettings {

	public const SECTION_ID = 'mhm_rentiva_maintenance_section';

	/**
	 * Get default settings for maintenance
	 *
	 * @return array
	 */
	public static function get_default_settings(): array {
		return array(
			'mhm_rentiva_clean_data_on_uninstall' => '0',
			'mhm_rentiva_log_max_size'            => 10,
		);
	}

	/**
	 * Register settings
	 */
	public static function register(): void {
		$page_slug = SettingsCore::PAGE;

		add_settings_section(
			self::SECTION_ID,
			__( 'System Maintenance', 'mhm-rentiva' ),
			array( self::class, 'render_section_description' ),
			$page_slug
		);

		// System Info (Read-Only Group)
		add_settings_field(
			'group_system_status',
			__( 'System Status', 'mhm-rentiva' ),
			array( self::class, 'render_group_system_status' ),
			$page_slug,
			self::SECTION_ID
		);

		// Performance Group (Accordioned)
		add_settings_field(
			'group_cache',
			__( 'Cache & Performance', 'mhm-rentiva' ),
			array( self::class, 'render_group_cache' ),
			$page_slug,
			self::SECTION_ID
		);

		// Database Cleanup
		add_settings_field(
			'group_db_cleanup',
			__( 'Data Retention', 'mhm-rentiva' ),
			array( self::class, 'render_group_db_cleanup' ),
			$page_slug,
			self::SECTION_ID
		);
	}

	/**
	 * Render the settings section
	 */
	public static function render_settings_section(): void {
		if ( class_exists( '\MHMRentiva\Admin\Settings\View\SettingsViewHelper' ) ) {
			\MHMRentiva\Admin\Settings\View\SettingsViewHelper::render_section_cleanly( self::SECTION_ID );
		}
	}

	public static function render_section_description(): void {
		echo '<p class="description">' . esc_html__( 'Overview of system status and maintenance tools.', 'mhm-rentiva' ) . '</p>';
	}

	/**
	 * Group: Cache & Performance
	 */
	public static function render_group_cache(): void {
		echo '<div class="mhm-accordion-group">';
		echo '<div class="mhm-accordion-header"><span>' . esc_html__( 'Cache Settings', 'mhm-rentiva' ) . '</span><span class="dashicons dashicons-arrow-down"></span></div>';
		echo '<div class="mhm-accordion-content">';

		if ( class_exists( CoreSettings::class ) ) {
			CoreSettings::register(); // Ensure fields are registered for rendering
			echo '<table class="form-table" role="presentation">';
			do_settings_fields( SettingsCore::PAGE, CoreSettings::SECTION_ID );
			echo '</table>';
		}

		echo '</div></div>';
	}

	/**
	 * Group: System Status
	 */
	public static function render_group_system_status(): void {
		echo '<div class="mhm-system-status-grid" style="display:grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap:15px; margin-bottom:20px;">';
		$server_software = sanitize_text_field( wp_unslash( (string) ( $_SERVER['SERVER_SOFTWARE'] ?? '' ) ) );
		$status          = array(
			'PHP'       => phpversion(),
			'WordPress' => get_bloginfo( 'version' ),
			'Server'    => '' !== $server_software ? $server_software : 'Unknown',
			'SQL Mode'  => 'High Performance',
		);

		foreach ( $status as $label => $val ) {
			printf(
				'<div class="status-card" style="background:#f9f9f9; padding:15px; border-radius:8px; border:1px solid #ddd;"><strong>%s</strong><br/>%s</div>',
				esc_html( $label ),
				esc_html( (string) $val )
			);
		}
		echo '</div>';
	}

	/**
	 * Group: Database Cleanup
	 */
	public static function render_group_db_cleanup(): void {
		echo '<div class="mhm-accordion-group">';
		echo '<div class="mhm-accordion-header"><span>' . esc_html__( 'Cleanup & Uninstall', 'mhm-rentiva' ) . '</span><span class="dashicons dashicons-arrow-down"></span></div>';
		echo '<div class="mhm-accordion-content">';

		$clean = SettingsCore::get( 'mhm_rentiva_clean_data_on_uninstall', '0' );

		echo '<div class="mhm-form-group">';
		echo '<input type="hidden" name="mhm_rentiva_settings[mhm_rentiva_clean_data_on_uninstall]" value="0">';
		echo '<label style="color:#d63638; font-weight:bold;"><input type="checkbox" name="mhm_rentiva_settings[mhm_rentiva_clean_data_on_uninstall]" value="1" style="width:auto;"' . checked( $clean, '1', false ) . '> ' . esc_html__( 'WIPE ALL DATA ON UNINSTALL', 'mhm-rentiva' ) . '</label>';
		echo '<p class="description">' . esc_html__( 'If enabled, all MHM Rentiva database tables and settings will be permanently deleted when the plugin is uninstalled.', 'mhm-rentiva' ) . '</p>';
		echo '</div>';

		echo '</div></div>';
	}
}
