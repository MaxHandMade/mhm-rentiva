<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Settings\Groups;

use MHMRentiva\Admin\Settings\Core\SettingsCore;
use MHMRentiva\Admin\Settings\Core\SettingsHelper;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Vehicle Management Settings
 *
 * Vehicle pricing, display, and availability settings.
 * Refactored for SOLID principles and high performance.
 *
 * @since 4.0.0
 */
final class VehicleManagementSettings
{

	public const SECTION_PRICING      = 'mhm_rentiva_vehicle_pricing_section';
	public const SECTION_AVAILABILITY = 'mhm_rentiva_vehicle_availability_section';

	/**
	 * Get default settings.
	 *
	 * @return array
	 */
	public static function get_default_settings(): array
	{
		return array(
			// Pricing
			'mhm_rentiva_vehicle_base_price'           => 1.0,
			'mhm_rentiva_vehicle_weekend_multiplier'   => 1.2,
			'mhm_rentiva_vehicle_tax_inclusive'        => '0',
			'mhm_rentiva_vehicle_tax_rate'             => 18.0,

			// Availability
			'mhm_rentiva_vehicle_min_rental_days'      => 1,
			'mhm_rentiva_vehicle_max_rental_days'      => 30,
			'mhm_rentiva_vehicle_advance_booking_days' => 365,
			'mhm_rentiva_vehicle_allow_same_day'       => '1',
		);
	}

	/**
	 * Render the vehicle settings section.
	 */
	public static function render_settings_section(): void
	{
		if (class_exists('\MHMRentiva\Admin\Settings\View\SettingsViewHelper')) {
			\MHMRentiva\Admin\Settings\View\SettingsViewHelper::render_section_cleanly(self::SECTION_PRICING);
			\MHMRentiva\Admin\Settings\View\SettingsViewHelper::render_section_cleanly(self::SECTION_AVAILABILITY);
		}
	}

	/**
	 * Register settings.
	 */
	public static function register(): void
	{
		$page_slug = SettingsCore::PAGE;

		// 1. Vehicle Pricing Section
		add_settings_section(
			self::SECTION_PRICING,
			__('Vehicle Pricing Settings', 'mhm-rentiva'),
			fn() => print('<p>' . esc_html__('Configure vehicle pricing rules, multipliers, and tax settings.', 'mhm-rentiva') . '</p>'),
			$page_slug
		);

		SettingsHelper::number_field(
			$page_slug,
			'mhm_rentiva_vehicle_base_price',
			__('Base Price Multiplier', 'mhm-rentiva'),
			0,
			100,
			__('Base price multiplier for all vehicles (1.0 = normal price)', 'mhm-rentiva'),
			self::SECTION_PRICING
		);

		SettingsHelper::number_field(
			$page_slug,
			'mhm_rentiva_vehicle_weekend_multiplier',
			__('Weekend Price Multiplier', 'mhm-rentiva'),
			0,
			100,
			__('Weekend price multiplier (1.2 = 20% increase)', 'mhm-rentiva'),
			self::SECTION_PRICING
		);

		// Custom Render for Tax (WooCommerce check)
		add_settings_field(
			'mhm_rentiva_vehicle_tax_inclusive',
			__('Tax Inclusive Pricing', 'mhm-rentiva'),
			array(self::class, 'render_tax_inclusive_field'),
			$page_slug,
			self::SECTION_PRICING
		);

		// Custom Render for Tax Rate (WooCommerce check)
		add_settings_field(
			'mhm_rentiva_vehicle_tax_rate',
			__('Tax Rate (%)', 'mhm-rentiva'),
			array(self::class, 'render_tax_rate_field'),
			$page_slug,
			self::SECTION_PRICING
		);

		// 2. Vehicle Availability Section
		add_settings_section(
			self::SECTION_AVAILABILITY,
			__('Vehicle Availability Settings', 'mhm-rentiva'),
			fn() => print('<p>' . esc_html__('Configure vehicle availability rules and booking restrictions.', 'mhm-rentiva') . '</p>'),
			$page_slug
		);

		SettingsHelper::number_field(
			$page_slug,
			'mhm_rentiva_vehicle_min_rental_days',
			__('Minimum Rental Days', 'mhm-rentiva'),
			1,
			365,
			__('Minimum number of rental days', 'mhm-rentiva'),
			self::SECTION_AVAILABILITY
		);

		SettingsHelper::number_field(
			$page_slug,
			'mhm_rentiva_vehicle_max_rental_days',
			__('Maximum Rental Days', 'mhm-rentiva'),
			1,
			365,
			__('Maximum number of rental days', 'mhm-rentiva'),
			self::SECTION_AVAILABILITY
		);

		SettingsHelper::number_field(
			$page_slug,
			'mhm_rentiva_vehicle_advance_booking_days',
			__('Advance Booking Days', 'mhm-rentiva'),
			1,
			365,
			__('How many days in advance can customers book', 'mhm-rentiva'),
			self::SECTION_AVAILABILITY
		);

		SettingsHelper::checkbox_field(
			$page_slug,
			'mhm_rentiva_vehicle_allow_same_day',
			__('Allow Same Day Booking', 'mhm-rentiva'),
			__('Enable to allow customers to book for the same day.', 'mhm-rentiva'),
			self::SECTION_AVAILABILITY
		);
	}

	/**
	 * Tax Inclusive Field (Custom)
	 */
	public static function render_tax_inclusive_field(): void
	{
		if (class_exists('WooCommerce')) {
			echo '<p class="description">' . esc_html__('Tax settings are managed by WooCommerce.', 'mhm-rentiva') . '</p>';
			return;
		}

		$value = SettingsCore::get('mhm_rentiva_vehicle_tax_inclusive', '0');
		echo '<input type="hidden" name="mhm_rentiva_settings[mhm_rentiva_vehicle_tax_inclusive]" value="0">';
		echo '<label><input type="checkbox" name="mhm_rentiva_settings[mhm_rentiva_vehicle_tax_inclusive]" value="1"' . checked($value, '1', false) . '> ' . esc_html__('Include tax in displayed prices', 'mhm-rentiva') . '</label>';
	}

	/**
	 * Tax Rate Field (Custom)
	 */
	public static function render_tax_rate_field(): void
	{
		if (class_exists('WooCommerce')) {
			echo '<p class="description">' . esc_html__('Tax rates are managed by WooCommerce settings.', 'mhm-rentiva') . '</p>';
			return;
		}

		$value = SettingsCore::get('mhm_rentiva_vehicle_tax_rate', 18.0);
		echo '<input type="number" name="mhm_rentiva_settings[mhm_rentiva_vehicle_tax_rate]" value="' . esc_attr((string) $value) . '" step="0.01" min="0" max="100" class="regular-text" />';
		echo '<p class="description">' . esc_html__('Tax rate percentage (e.g., 18 for 18%)', 'mhm-rentiva') . '</p>';
	}
}
