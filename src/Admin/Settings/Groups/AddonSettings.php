<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Settings\Groups;

use MHMRentiva\Admin\Settings\Core\SettingsCore;
use MHMRentiva\Admin\Settings\Core\SettingsHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Addon Settings Group
 *
 * Configures additional services display, pricing, and behavior.
 * Refactored for modularity and high performance.
 *
 * @since 4.0.0
 */
final class AddonSettings {

	public const SECTION_ID = 'mhm_rentiva_addons_section';

	/**
	 * Get default settings for addons.
	 *
	 * @return array
	 */
	public static function get_default_settings(): array {
		return array(
			'mhm_rentiva_addon_require_confirmation'    => '0',
			'mhm_rentiva_addon_show_prices_in_calendar' => '1',
			'mhm_rentiva_addon_display_order'           => 'menu_order',
		);
	}

	/**
	 * Render the addon settings section
	 */
	public static function render_settings_section(): void {
		if ( class_exists( '\MHMRentiva\Admin\Settings\View\SettingsViewHelper' ) ) {
			\MHMRentiva\Admin\Settings\View\SettingsViewHelper::render_section_cleanly( self::SECTION_ID );
		}
	}

	/**
	 * Register settings.
	 */
	public static function register(): void {
		$page_slug = SettingsCore::PAGE;

		add_settings_section(
			self::SECTION_ID,
			__( 'Additional Services Settings', 'mhm-rentiva' ),
			array( self::class, 'render_section_description' ),
			$page_slug
		);

		SettingsHelper::checkbox_field(
			$page_slug,
			'mhm_rentiva_addon_require_confirmation',
			__( 'Require Confirmation', 'mhm-rentiva' ),
			__( 'Require manual confirmation when customers select additional services.', 'mhm-rentiva' ),
			self::SECTION_ID
		);

		SettingsHelper::checkbox_field(
			$page_slug,
			'mhm_rentiva_addon_show_prices_in_calendar',
			__( 'Show Prices in Calendar', 'mhm-rentiva' ),
			__( 'Display additional service prices in the booking calendar.', 'mhm-rentiva' ),
			self::SECTION_ID
		);

		SettingsHelper::select_field(
			$page_slug,
			'mhm_rentiva_addon_display_order',
			__( 'Display Order', 'mhm-rentiva' ),
			array(
				'menu_order'   => __( 'Menu Order (Default)', 'mhm-rentiva' ),
				'title'        => __( 'Title (A-Z)', 'mhm-rentiva' ),
				'price_asc'    => __( 'Price (Low to High)', 'mhm-rentiva' ),
				'price_desc'   => __( 'Price (High to Low)', 'mhm-rentiva' ),
				'date_created' => __( 'Creation Date', 'mhm-rentiva' ),
			),
			__( 'The order in which additional services are displayed.', 'mhm-rentiva' ),
			self::SECTION_ID
		);
	}

	public static function render_section_description(): void {
		printf( '<p>%s</p>', esc_html__( 'Configure general settings for additional services.', 'mhm-rentiva' ) );
		echo '<div class="notice notice-info inline"><p><strong>' . esc_html__( 'Note:', 'mhm-rentiva' ) . '</strong> ' . esc_html__( 'Use the "Additional Services" page from the left menu to manage specific service items.', 'mhm-rentiva' ) . '</p></div>';
	}

	// Static Accessors
	public static function require_confirmation(): bool {
		return SettingsCore::get( 'mhm_rentiva_addon_require_confirmation', '0' ) === '1';
	}
	public static function show_prices_in_calendar(): bool {
		return SettingsCore::get( 'mhm_rentiva_addon_show_prices_in_calendar', '1' ) === '1';
	}
	public static function get_display_order(): string {
		return (string) SettingsCore::get( 'mhm_rentiva_addon_display_order', 'menu_order' );
	}
}
