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
 * Vehicle Management Settings
 *
 * Vehicle pricing, display, and availability settings.
 * Refactored for SOLID principles and high performance.
 *
 * @since 4.0.0
 */
final class VehicleManagementSettings {


	public const SECTION_PRICING      = 'mhmrentiva_vehicle_pricing_section';
	public const SECTION_AVAILABILITY = 'mhmrentiva_vehicle_availability_section';
	public const SECTION_URLS         = 'mhmrentiva_vehicle_urls_section';

	/**
	 * Get default settings.
	 *
	 * @return array
	 */
	public static function get_default_settings(): array {
		return array(
			// URLs
			'mhmrentiva_vehicle_url_base'             => 'vehicle',

			// Pricing
			'mhmrentiva_vehicle_base_price'           => 1.0,
			'mhmrentiva_vehicle_weekend_multiplier'   => 1.2,
			'mhmrentiva_vehicle_tax_inclusive'        => '0',
			'mhmrentiva_vehicle_tax_rate'             => 18.0,

			// Availability
			'mhmrentiva_vehicle_min_rental_days'      => 1,
			'mhmrentiva_vehicle_max_rental_days'      => 30,
			'mhmrentiva_vehicle_advance_booking_days' => 365,
			'mhmrentiva_vehicle_allow_same_day'       => '1',
			'mhmrentiva_default_rental_location'      => '',
		);
	}

	/**
	 * Render the vehicle settings section.
	 */
	public static function render_settings_section(): void {
		if ( class_exists( '\MHMRentiva\Admin\Settings\View\SettingsViewHelper' ) ) {
			\MHMRentiva\Admin\Settings\View\SettingsViewHelper::render_section_cleanly( self::SECTION_URLS );
			\MHMRentiva\Admin\Settings\View\SettingsViewHelper::render_section_cleanly( self::SECTION_PRICING );
			\MHMRentiva\Admin\Settings\View\SettingsViewHelper::render_section_cleanly( self::SECTION_AVAILABILITY );
		}
	}

	/**
	 * Register settings.
	 */
	public static function register(): void {
		$page_slug = SettingsCore::PAGE;

		// 0. Vehicle URL Settings Section
		add_settings_section(
			self::SECTION_URLS,
			__( 'Vehicle URL Settings', 'mhm-rentiva' ),
			fn() => print( '<p>' . esc_html__( 'Configure the URL base for vehicle detail pages. After changing this, visit Settings → Permalinks and click Save to refresh rewrite rules.', 'mhm-rentiva' ) . '</p>' ),
			$page_slug
		);

		SettingsHelper::text_field(
			$page_slug,
			'mhmrentiva_vehicle_url_base',
			__( 'Vehicle URL Base', 'mhm-rentiva' ),
			self::SECTION_URLS,
			__( 'URL segment used for vehicle detail pages. Default: vehicle → example.com/vehicle/car-name/', 'mhm-rentiva' ),
			__( 'vehicle', 'mhm-rentiva' )
		);

		// 1. Vehicle Pricing Section
		add_settings_section(
			self::SECTION_PRICING,
			__( 'Vehicle Pricing Settings', 'mhm-rentiva' ),
			fn() => print( '<p>' . esc_html__( 'Configure vehicle pricing rules, multipliers, and tax settings.', 'mhm-rentiva' ) . '</p>' ),
			$page_slug
		);

		SettingsHelper::number_field(
			$page_slug,
			'mhmrentiva_vehicle_base_price',
			__( 'Base Price Multiplier', 'mhm-rentiva' ),
			0,
			100,
			__( 'Base price multiplier for all vehicles (1.0 = normal price)', 'mhm-rentiva' ),
			self::SECTION_PRICING
		);

		SettingsHelper::number_field(
			$page_slug,
			'mhmrentiva_vehicle_weekend_multiplier',
			__( 'Weekend Price Multiplier', 'mhm-rentiva' ),
			0,
			100,
			__( 'Weekend price multiplier (1.2 = 20% increase)', 'mhm-rentiva' ),
			self::SECTION_PRICING
		);

		// Custom Render for Tax (WooCommerce check)
		add_settings_field(
			'mhmrentiva_vehicle_tax_inclusive',
			__( 'Tax Inclusive Pricing', 'mhm-rentiva' ),
			array( self::class, 'render_tax_inclusive_field' ),
			$page_slug,
			self::SECTION_PRICING
		);

		// Custom Render for Tax Rate (WooCommerce check)
		add_settings_field(
			'mhmrentiva_vehicle_tax_rate',
			__( 'Tax Rate (%)', 'mhm-rentiva' ),
			array( self::class, 'render_tax_rate_field' ),
			$page_slug,
			self::SECTION_PRICING
		);

		// Seasonal Multipliers (Görev 9 / F19): moved from the orphaned
		// VehiclePricingSettings::render_settings_section() -- that method had
		// zero callers, so an admin could never reach these controls. Field
		// names are unchanged, so SettingsSanitizer::sanitize_vehicle_pricing_settings()
		// (already wired to accept vehicle_pricing[seasonal_multipliers][<season>][multiplier])
		// needed no changes.
		add_settings_field(
			'mhmrentiva_vehicle_seasonal_multipliers',
			__( 'Seasonal Pricing', 'mhm-rentiva' ),
			array( self::class, 'render_seasonal_multipliers_field' ),
			$page_slug,
			self::SECTION_PRICING
		);

		// 2. Vehicle Availability Section
		add_settings_section(
			self::SECTION_AVAILABILITY,
			__( 'Vehicle Availability Settings', 'mhm-rentiva' ),
			fn() => print( '<p>' . esc_html__( 'Configure vehicle availability rules and booking restrictions.', 'mhm-rentiva' ) . '</p>' ),
			$page_slug
		);

		SettingsHelper::number_field(
			$page_slug,
			'mhmrentiva_vehicle_min_rental_days',
			__( 'Minimum Rental Days', 'mhm-rentiva' ),
			1,
			365,
			__( 'Minimum number of rental days', 'mhm-rentiva' ),
			self::SECTION_AVAILABILITY
		);

		SettingsHelper::number_field(
			$page_slug,
			'mhmrentiva_vehicle_max_rental_days',
			__( 'Maximum Rental Days', 'mhm-rentiva' ),
			1,
			365,
			__( 'Maximum number of rental days', 'mhm-rentiva' ),
			self::SECTION_AVAILABILITY
		);

		SettingsHelper::number_field(
			$page_slug,
			'mhmrentiva_vehicle_advance_booking_days',
			__( 'Advance Booking Days', 'mhm-rentiva' ),
			1,
			365,
			__( 'How many days in advance can customers book', 'mhm-rentiva' ),
			self::SECTION_AVAILABILITY
		);

		SettingsHelper::checkbox_field(
			$page_slug,
			'mhmrentiva_vehicle_allow_same_day',
			__( 'Allow Same Day Booking', 'mhm-rentiva' ),
			__( 'Enable to allow customers to book for the same day.', 'mhm-rentiva' ),
			self::SECTION_AVAILABILITY
		);

		// Global Default Location. Locations come from an add-on via the filter:
		// without one the setting has no selectable value and nothing to fall back
		// to, so the field is withheld instead of offering only "Default (None)".
		$locations = apply_filters( 'mhmrentiva_locations', array(), 'rental' );
		if ( ! empty( $locations ) ) {
			$options = array( '' => __( 'Default (None)', 'mhm-rentiva' ) );
			foreach ( $locations as $loc ) {
				$options[ $loc->id ] = $loc->name;
			}

			SettingsHelper::select_field(
				$page_slug,
				'mhmrentiva_default_rental_location',
				__( 'Default Rental Location', 'mhm-rentiva' ),
				$options,
				__( 'This location will be used as a fallback if a vehicle has no specific location and its owner (vendor) has no default location set.', 'mhm-rentiva' ),
				self::SECTION_AVAILABILITY
			);
		}
	}

	/**
	 * Tax Inclusive Field (Custom)
	 */
	public static function render_tax_inclusive_field(): void {
		if ( class_exists( 'WooCommerce' ) ) {
			echo '<p class="description">' . esc_html__( 'Tax settings are managed by WooCommerce.', 'mhm-rentiva' ) . '</p>';
			return;
		}

		$value = SettingsCore::get( 'mhmrentiva_vehicle_tax_inclusive', '0' );
		echo '<input type="hidden" name="mhmrentiva_settings[mhmrentiva_vehicle_tax_inclusive]" value="0">';
		echo '<label><input type="checkbox" name="mhmrentiva_settings[mhmrentiva_vehicle_tax_inclusive]" value="1"' . checked( $value, '1', false ) . '> ' . esc_html__( 'Include tax in displayed prices', 'mhm-rentiva' ) . '</label>';
	}

	/**
	 * Tax Rate Field (Custom)
	 */
	public static function render_tax_rate_field(): void {
		if ( class_exists( 'WooCommerce' ) ) {
			echo '<p class="description">' . esc_html__( 'Tax rates are managed by WooCommerce settings.', 'mhm-rentiva' ) . '</p>';
			return;
		}

		$value = SettingsCore::get( 'mhmrentiva_vehicle_tax_rate', 18.0 );
		echo '<input type="number" name="mhmrentiva_settings[mhmrentiva_vehicle_tax_rate]" value="' . esc_attr( (string) $value ) . '" step="0.01" min="0" max="100" class="regular-text" />';
		echo '<p class="description">' . esc_html__( 'Tax rate percentage (e.g., 18 for 18%)', 'mhm-rentiva' ) . '</p>';
	}

	/**
	 * Seasonal Multipliers Field (Custom)
	 *
	 * Moved verbatim from the orphaned VehiclePricingSettings::render_settings_section()
	 * (Görev 9 / F19) -- same field names, same markup. Data still lives in and is read
	 * back through VehiclePricingSettings (get_seasonal_multipliers() / get_seasonal_multiplier_for_date(),
	 * the latter consumed by BookingForm.php's per-day price calculation).
	 *
	 * Discount controls (VehiclePricingSettings::discount_options / calculate_discounts())
	 * deliberately did NOT move here: calculate_discounts() had zero callers anywhere in the
	 * plugin (grep-verified), so unlike the seasonal multiplier there was no live logic for a
	 * rendered discount field to feed -- rendering it would only have recreated the same
	 * wired-but-unreachable shape one level down (a config screen with no reader). The whole
	 * discount trio (calculate_discounts()/get_enabled_discounts()/get_discount_options()) and
	 * its solely-owned remnants (get_default_settings()'s discount_options entry, the matching
	 * SettingsSanitizer arm) were deleted outright in T8 Görev 10c-A (K5-F1) rather than ever
	 * given a doorway -- same zero-caller evidence, re-confirmed at that HEAD. Currency,
	 * general and deposit fields from the orphan also stay out: each already has a live
	 * equivalent elsewhere (mhmrentiva_currency on the General tab; min/max rental days on
	 * this tab's Availability section; deposit is read from the separate mhmrentiva_enable_deposit
	 * option, not this array) -- moving them would double-render an already-covered field.
	 */
	public static function render_seasonal_multipliers_field(): void {
		$seasonal_multipliers = \MHMRentiva\Admin\Vehicle\Settings\VehiclePricingSettings::get_seasonal_multipliers();

		foreach ( $seasonal_multipliers as $key => $season ) {
			echo '<div style="margin-bottom: 15px; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">';
			echo '<h4>' . esc_html( $season['name'] ) . '</h4>';
			echo '<label for="season_' . esc_attr( $key ) . '_multiplier">' . esc_html__( 'Multiplier', 'mhm-rentiva' ) . '</label><br>';
			echo '<input type="number" id="season_' . esc_attr( $key ) . '_multiplier" name="mhmrentiva_settings[vehicle_pricing][seasonal_multipliers][' . esc_attr( $key ) . '][multiplier]" value="' . esc_attr( $season['multiplier'] ) . '" min="0.1" max="5.0" step="0.1" style="width: 100px;">';
			echo '<p class="description">' . esc_html( $season['description'] ) . '</p>';
			echo '</div>';
		}
	}
}
