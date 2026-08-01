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
 * Core System Settings Group
 *
 * Manages cache, performance, and low-level system configurations.
 * Optimized for high performance and standardized rendering.
 */
final class CoreSettings {

	public const SECTION_ID = 'mhmrentiva_core_section';

	/**
	 * Get default settings for core system
	 *
	 * @return array
	 */
	public static function get_default_settings(): array {
		return array(
			// Cache Settings
			'mhmrentiva_cache_enabled'     => '1',
			'mhmrentiva_cache_default_ttl' => 1.0,
			'mhmrentiva_cache_lists_ttl'   => 5,
			'mhmrentiva_cache_reports_ttl' => 15,

			// Query Limits
		);
	}

	/**
	 * Register core settings
	 */
	public static function register(): void {
		$page_slug = SettingsCore::PAGE;

		add_settings_section(
			self::SECTION_ID,
			__( 'System & Performance', 'mhm-rentiva' ),
			array( self::class, 'render_section_description' ),
			$page_slug
		);

		SettingsHelper::checkbox_field( $page_slug, 'mhmrentiva_cache_enabled', __( 'Enable Object Cache', 'mhm-rentiva' ), __( 'Active object caching reduces database load significantly.', 'mhm-rentiva' ), self::SECTION_ID );
		SettingsHelper::number_field( $page_slug, 'mhmrentiva_cache_default_ttl', __( 'Default Cache TTL (Hours)', 'mhm-rentiva' ), 0.5, 24, __( 'How long general data remains cached.', 'mhm-rentiva' ), self::SECTION_ID );
		SettingsHelper::number_field( $page_slug, 'mhmrentiva_cache_lists_ttl', __( 'Lists Cache TTL (Minutes)', 'mhm-rentiva' ), 1, 60, __( 'Cache duration for vehicle and booking lists.', 'mhm-rentiva' ), self::SECTION_ID );
		SettingsHelper::number_field( $page_slug, 'mhmrentiva_cache_reports_ttl', __( 'Reports Cache TTL (Minutes)', 'mhm-rentiva' ), 1, 1440, __( 'Cache duration for report calculations.', 'mhm-rentiva' ), self::SECTION_ID );
	}

	public static function render_section_description(): void {
		echo '<p>' . esc_html__( 'Configure system optimizations and performance thresholds.', 'mhm-rentiva' ) . '</p>';
	}

	// Static Accessors
	public static function is_cache_enabled(): bool {
		return SettingsCore::get( 'mhmrentiva_cache_enabled', '1' ) === '1';
	}
	public static function get_cache_default_ttl(): int {
		return (int) ( floatval( SettingsCore::get( 'mhmrentiva_cache_default_ttl', 1.0 ) ) * HOUR_IN_SECONDS );
	}
	public static function get_cache_lists_ttl(): int {
		return (int) ( absint( SettingsCore::get( 'mhmrentiva_cache_lists_ttl', 5 ) ) * MINUTE_IN_SECONDS );
	}
	public static function get_cache_reports_ttl(): int {
		return (int) ( absint( SettingsCore::get( 'mhmrentiva_cache_reports_ttl', 15 ) ) * MINUTE_IN_SECONDS );
	}
}
