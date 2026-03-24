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
 * General Settings Group
 *
 * Manages currency, brand, and site-wide configurations.
 * Optimized for high performance and standardized rendering.
 *
 * @package MHMRentiva\Admin\Settings\Groups
 */
final class GeneralSettings {


	public const SECTION_GENERAL   = 'mhm_rentiva_general_section';
	public const SECTION_SITE_INFO = 'mhm_rentiva_site_info_section';

	/**
	 * Get default settings
	 *
	 * @return array
	 */
	public static function get_default_settings(): array {
		// Check for WooCommerce currency
		$currency = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'USD';

		return array(
			// General
			'mhm_rentiva_currency'          => $currency,
			'mhm_rentiva_currency_position' => 'right_space',
			'mhm_rentiva_dark_mode'         => 'auto',

			// Site Info
			'mhm_rentiva_brand_name'        => get_bloginfo( 'name' ),
			'mhm_rentiva_support_email'     => get_option( 'admin_email' ),
			'mhm_rentiva_contact_phone'     => '',
			'mhm_rentiva_contact_hours'     => '',
		);
	}

	/**
	 * Render the general settings section
	 */
	public static function render_settings_section(): void {
		if ( class_exists( '\MHMRentiva\Admin\Settings\View\SettingsViewHelper' ) ) {
			\MHMRentiva\Admin\Settings\View\SettingsViewHelper::render_section_cleanly( self::SECTION_GENERAL );
			\MHMRentiva\Admin\Settings\View\SettingsViewHelper::render_section_cleanly( self::SECTION_SITE_INFO );
		}
	}

	/**
	 * Register general settings
	 */
	public static function register(): void {
		$page_slug = SettingsCore::PAGE;

		// 1. General Section
		add_settings_section(
			self::SECTION_GENERAL,
			__( 'General Configuration', 'mhm-rentiva' ),
			fn() => print( '<p>' . esc_html__( 'Configure currency and display preferences.', 'mhm-rentiva' ) . '</p>' ),
			$page_slug
		);

		// Currency (Custom Render due to WooCommerce check)
		add_settings_field(
			'mhm_rentiva_currency',
			__( 'Currency', 'mhm-rentiva' ),
			array( self::class, 'render_currency_field' ),
			$page_slug,
			self::SECTION_GENERAL
		);

		// Currency Position (Custom Render due to WooCommerce check)
		add_settings_field(
			'mhm_rentiva_currency_position',
			__( 'Currency Position', 'mhm-rentiva' ),
			array( self::class, 'render_currency_position_field' ),
			$page_slug,
			self::SECTION_GENERAL
		);

		SettingsHelper::select_field(
			$page_slug,
			'mhm_rentiva_dark_mode',
			__( 'Dark Mode', 'mhm-rentiva' ),
			array(
				'auto'  => __( 'Auto (System)', 'mhm-rentiva' ),
				'light' => __( 'Light', 'mhm-rentiva' ),
				'dark'  => __( 'Dark', 'mhm-rentiva' ),
			),
			__( 'Select admin panel color scheme.', 'mhm-rentiva' ),
			self::SECTION_GENERAL
		);

		// 2. Site Info Section
		add_settings_section(
			self::SECTION_SITE_INFO,
			__( 'Brand & Contact Information', 'mhm-rentiva' ),
			fn() => print( '<p>' . esc_html__( 'Information used in emails, PDF documents, and contact forms.', 'mhm-rentiva' ) . '</p>' ),
			$page_slug
		);

		SettingsHelper::text_field(
			$page_slug,
			'mhm_rentiva_brand_name',
			__( 'Brand Name', 'mhm-rentiva' ),
			self::SECTION_SITE_INFO,
			__( 'Your company or brand name', 'mhm-rentiva' ),
			__( 'e.g., Otokira Rent a Car', 'mhm-rentiva' )
		);

		SettingsHelper::email_field(
			$page_slug,
			'mhm_rentiva_support_email',
			__( 'Support Email', 'mhm-rentiva' ),
			__( 'Email address to be used for customer support', 'mhm-rentiva' ),
			self::SECTION_SITE_INFO
		);

		SettingsHelper::text_field(
			$page_slug,
			'mhm_rentiva_contact_phone',
			__( 'Contact Phone', 'mhm-rentiva' ),
			self::SECTION_SITE_INFO,
			__( 'Customer service phone number', 'mhm-rentiva' ),
			__( '+90 555 123 45 67', 'mhm-rentiva' )
		);

		SettingsHelper::text_field(
			$page_slug,
			'mhm_rentiva_contact_hours',
			__( 'Support Hours', 'mhm-rentiva' ),
			self::SECTION_SITE_INFO,
			__( 'Business hours for customer support', 'mhm-rentiva' ),
			__( '09:00 - 18:00', 'mhm-rentiva' )
		);
	}

	/**
	 * Currency Field (Custom Render)
	 */
	public static function render_currency_field(): void {
		if ( class_exists( 'WooCommerce' ) && function_exists( 'get_woocommerce_currency' ) ) {
			echo '<p class="description"><strong>' . esc_html__( 'Managed by WooCommerce:', 'mhm-rentiva' ) . '</strong> ' . esc_html( (string) call_user_func( 'get_woocommerce_currency' ) ) . '</p>';
			return;
		}

		$currency   = SettingsCore::get( 'mhm_rentiva_currency', 'USD' );
		$currencies = class_exists( '\MHMRentiva\Admin\Core\CurrencyHelper' )
			? \MHMRentiva\Admin\Core\CurrencyHelper::get_currency_list_for_dropdown()
			: array(
				'USD' => 'US Dollar',
				'EUR' => 'Euro',
				'TRY' => 'Turkish Lira',
			);

		echo '<select name="mhm_rentiva_settings[mhm_rentiva_currency]">';
		foreach ( $currencies as $code => $name ) {
			echo '<option value="' . esc_attr( $code ) . '"' . selected( $currency, $code, false ) . '>' . esc_html( $name ) . '</option>';
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Currency to be used throughout the system.', 'mhm-rentiva' ) . '</p>';
	}

	/**
	 * Currency Position Field (Custom Render)
	 */
	public static function render_currency_position_field(): void {
		if ( class_exists( 'WooCommerce' ) ) {
			echo '<p class="description">' . esc_html__( 'Currency position is managed by WooCommerce settings.', 'mhm-rentiva' ) . '</p>';
			return;
		}

		$position  = SettingsCore::get( 'mhm_rentiva_currency_position', 'right_space' );
		$positions = array(
			'left'        => __( 'Left ($100)', 'mhm-rentiva' ),
			'left_space'  => __( 'Left Space ($ 100)', 'mhm-rentiva' ),
			'right'       => __( 'Right (100$)', 'mhm-rentiva' ),
			'right_space' => __( 'Right Space (100 $)', 'mhm-rentiva' ),
		);

		echo '<select name="mhm_rentiva_settings[mhm_rentiva_currency_position]">';
		foreach ( $positions as $pos => $name ) {
			echo '<option value="' . esc_attr( $pos ) . '"' . selected( $position, $pos, false ) . '>' . esc_html( $name ) . '</option>';
		}
		echo '</select>';
	}
}
