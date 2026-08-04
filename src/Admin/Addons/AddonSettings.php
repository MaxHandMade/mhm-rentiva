<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Addons;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Addon Settings Class.
 *
 * @package MHMRentiva\Admin\Addons
 */





use MHMRentiva\Admin\Settings\Core\SettingsSanitizer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles settings for additional services.
 */
final class AddonSettings {


	public const PAGE = 'mhmrentiva_addon_settings';

	/**
	 * Register actions.
	 */
	public static function register(): void {
		// WordPress Settings API registration.
		add_action( 'admin_init', array( self::class, 'register_settings' ) );
	}

	/**
	 * Register WordPress Settings API.
	 */
	public static function register_settings(): void {
		// Register setting group.
		register_setting(
			'mhmrentiva_addon_settings',
			'mhmrentiva_addon_settings',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( SettingsSanitizer::class, 'sanitize_addon_settings_option' ),
				'default'           => self::defaults(),
			)
		);
	}

	/**
	 * Get default settings.
	 *
	 * @return array Default settings.
	 */
	public static function defaults(): array {
		return array(
			'system_enabled' => '1',
			'show_prices'    => '1',
			'allow_multiple' => '1',
			'display_order'  => 'price_asc',
		);
	}
}
