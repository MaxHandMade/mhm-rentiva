<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Settings\Groups;

if (!defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\Frontend\Shortcodes\Contact\RenderStamp;
use MHMRentiva\Admin\Frontend\Shortcodes\Contact\SpamCheck;
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


	public const SECTION_GENERAL   = 'mhmrentiva_general_section';
	public const SECTION_SITE_INFO = 'mhmrentiva_site_info_section';

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
			'mhmrentiva_currency'                => $currency,
			'mhmrentiva_currency_position'       => 'right_space',
			'mhmrentiva_dark_mode'               => 'auto',

			// Site Info
			'mhmrentiva_brand_name'              => get_bloginfo( 'name' ),
			'mhmrentiva_support_email'           => get_option( 'admin_email' ),
			'mhmrentiva_contact_phone'           => '',
			'mhmrentiva_contact_hours'           => '',
			'mhmrentiva_contact_address'         => '',
			'mhmrentiva_contact_phone_note'      => '',
			'mhmrentiva_contact_response_note'   => '',
			'mhmrentiva_contact_consent_enabled' => '0',
			'mhmrentiva_contact_min_seconds'     => RenderStamp::DEFAULT_MIN_SECONDS,
			'mhmrentiva_contact_max_links'       => SpamCheck::DEFAULT_MAX_LINKS,
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
			'mhmrentiva_currency',
			__( 'Currency', 'mhm-rentiva' ),
			array( self::class, 'render_currency_field' ),
			$page_slug,
			self::SECTION_GENERAL
		);

		// Currency Position (Custom Render due to WooCommerce check)
		add_settings_field(
			'mhmrentiva_currency_position',
			__( 'Currency Position', 'mhm-rentiva' ),
			array( self::class, 'render_currency_position_field' ),
			$page_slug,
			self::SECTION_GENERAL
		);

		SettingsHelper::select_field(
			$page_slug,
			'mhmrentiva_dark_mode',
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
			'mhmrentiva_brand_name',
			__( 'Brand Name', 'mhm-rentiva' ),
			self::SECTION_SITE_INFO,
			__( 'Your company or brand name', 'mhm-rentiva' ),
			__( 'e.g., Otokira Rent a Car', 'mhm-rentiva' )
		);

		SettingsHelper::email_field(
			$page_slug,
			'mhmrentiva_support_email',
			__( 'Support Email', 'mhm-rentiva' ),
			__( 'Email address to be used for customer support', 'mhm-rentiva' ),
			self::SECTION_SITE_INFO
		);

		SettingsHelper::text_field(
			$page_slug,
			'mhmrentiva_contact_phone',
			__( 'Contact Phone', 'mhm-rentiva' ),
			self::SECTION_SITE_INFO,
			__( 'Customer service phone number', 'mhm-rentiva' ),
			__( '+90 555 123 45 67', 'mhm-rentiva' )
		);

		SettingsHelper::text_field(
			$page_slug,
			'mhmrentiva_contact_hours',
			__( 'Support Hours', 'mhm-rentiva' ),
			self::SECTION_SITE_INFO,
			__( 'Business hours for customer support', 'mhm-rentiva' ),
			__( '09:00 - 18:00', 'mhm-rentiva' )
		);

		SettingsHelper::text_field(
			$page_slug,
			'mhmrentiva_contact_address',
			__( 'Office Address', 'mhm-rentiva' ),
			self::SECTION_SITE_INFO,
			__( 'Shown beside the contact form. Leave empty to hide it.', 'mhm-rentiva' )
		);

		SettingsHelper::text_field(
			$page_slug,
			'mhmrentiva_contact_phone_note',
			__( 'Phone Note', 'mhm-rentiva' ),
			self::SECTION_SITE_INFO,
			__( 'A short line under the phone number on the contact form.', 'mhm-rentiva' )
		);

		SettingsHelper::text_field(
			$page_slug,
			'mhmrentiva_contact_response_note',
			__( 'Response Time Note', 'mhm-rentiva' ),
			self::SECTION_SITE_INFO,
			__( 'Shown above the contact form, for example when you usually reply.', 'mhm-rentiva' )
		);

		$consent_help = __( 'Senders must tick a privacy notice box on the contact form.', 'mhm-rentiva' );
		if ( '' === get_privacy_policy_url() ) {
			$consent_help .= ' ' . __( 'No privacy policy page is published (Settings > Privacy), so the box shows plain text without a link.', 'mhm-rentiva' );
		}

		SettingsHelper::checkbox_field(
			$page_slug,
			'mhmrentiva_contact_consent_enabled',
			__( 'Privacy Notice Consent', 'mhm-rentiva' ),
			$consent_help,
			self::SECTION_SITE_INFO
		);

		SettingsHelper::number_field(
			$page_slug,
			'mhmrentiva_contact_min_seconds',
			__( 'Minimum Seconds Before Sending', 'mhm-rentiva' ),
			0,
			300,
			__( 'A contact form or new review sent sooner after the page opened counts as a bot. 0 turns this off; left empty, the default applies.', 'mhm-rentiva' ),
			self::SECTION_SITE_INFO,
			1
		);

		SettingsHelper::number_field(
			$page_slug,
			'mhmrentiva_contact_max_links',
			__( 'Maximum Links in a Message', 'mhm-rentiva' ),
			0,
			100,
			__( 'A contact message with more links than this goes to Spam. With 0, any link does; left empty, the default applies.', 'mhm-rentiva' ),
			self::SECTION_SITE_INFO,
			1
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

		$currency   = SettingsCore::get( 'mhmrentiva_currency', 'USD' );
		$currencies = class_exists( '\MHMRentiva\Admin\Core\CurrencyHelper' )
			? \MHMRentiva\Admin\Core\CurrencyHelper::get_currency_list_for_dropdown()
			: array(
				'USD' => 'US Dollar',
				'EUR' => 'Euro',
				'TRY' => 'Turkish Lira',
			);

		echo '<select name="mhmrentiva_settings[mhmrentiva_currency]">';
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
		// The same predicate `CurrencyHelper::get_currency_position()` obeys. Asking
		// it differently here is exactly how this screen came to offer a dropdown
		// whose value the plugin then ignored.
		if ( \MHMRentiva\Admin\Core\CurrencyHelper::woocommerce_is_active() ) {
			echo '<p class="description">' . esc_html__( 'Currency position is managed by WooCommerce settings.', 'mhm-rentiva' ) . '</p>';
			return;
		}

		$position  = SettingsCore::get( 'mhmrentiva_currency_position', 'right_space' );
		$positions = array(
			'left'        => __( 'Left ($100)', 'mhm-rentiva' ),
			'left_space'  => __( 'Left Space ($ 100)', 'mhm-rentiva' ),
			'right'       => __( 'Right (100$)', 'mhm-rentiva' ),
			'right_space' => __( 'Right Space (100 $)', 'mhm-rentiva' ),
		);

		echo '<select name="mhmrentiva_settings[mhmrentiva_currency_position]">';
		foreach ( $positions as $pos => $name ) {
			echo '<option value="' . esc_attr( $pos ) . '"' . selected( $position, $pos, false ) . '>' . esc_html( $name ) . '</option>';
		}
		echo '</select>';
	}
}
