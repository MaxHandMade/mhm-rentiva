<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Vehicle\Settings;

if (!defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\Settings\Core\SettingsCore;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ✅ VEHICLE PRICING SETTINGS - Configurable Pricing Settings
 *
 * Moves fixed pricing values to central settings
 */
final class VehiclePricingSettings {


	const OPTION_NAME = 'mhmrentiva_vehicle_pricing_settings';

	/**
	 * Default settings
	 */
	public static function get_default_settings(): array {
		return array(
			'seasonal_multipliers' => array(
				'spring' => array(
					'name'        => __( 'Spring', 'mhm-rentiva' ),
					'months'      => array( 3, 4, 5 ),
					'multiplier'  => 1.0,
					'description' => __( 'Standard pricing', 'mhm-rentiva' ),
				),
				'summer' => array(
					'name'        => __( 'Summer', 'mhm-rentiva' ),
					'months'      => array( 6, 7, 8 ),
					'multiplier'  => 1.3,
					'description' => __( 'High season pricing', 'mhm-rentiva' ),
				),
				'autumn' => array(
					'name'        => __( 'Autumn', 'mhm-rentiva' ),
					'months'      => array( 9, 10, 11 ),
					'multiplier'  => 1.1,
					'description' => __( 'Mid season pricing', 'mhm-rentiva' ),
				),
				'winter' => array(
					'name'        => __( 'Winter', 'mhm-rentiva' ),
					'months'      => array( 12, 1, 2 ),
					'multiplier'  => 0.8,
					'description' => __( 'Low season pricing', 'mhm-rentiva' ),
				),
			),

			'currency_settings'    => array(
				'default_currency' => 'USD',
			),

			'deposit_settings'     => array(
				'enable_deposit'          => true,
				'deposit_type'            => 'both', // 'fixed', 'percentage', 'both'
				'allow_no_deposit'        => true,
				'deposit_refund_policy'   => __( 'Deposit is non-refundable, deducted from total rental amount.', 'mhm-rentiva' ),
				'deposit_payment_methods' => array( 'credit_card', 'cash', 'bank_transfer' ),
				'show_deposit_in_listing' => true,
				'show_deposit_in_detail'  => true,
				'required_for_booking'    => false,
			),

			'general_settings'     => array(
				'min_rental_days'          => 1,
				'max_rental_days'          => 365,
				'default_rental_days'      => 3,
				'price_calculation_method' => 'daily', // daily, weekly, monthly
				'round_prices'             => true,
				'decimal_places'           => 2,
			),
		);
	}

	/**
	 * Get settings
	 */
	public static function get_settings(): array {
		return SettingsCore::get( 'vehicle_pricing', self::get_default_settings() );
	}

	/**
	 * Get seasonal multiplier for specific date
	 */
	public static function get_seasonal_multiplier_for_date( string $date ): float {
		$month = (int) gmdate( 'n', strtotime( $date ) );
		return self::get_seasonal_multiplier_for_month( $month );
	}

	/**
	 * Get seasonal multiplier for specific month
	 */
	public static function get_seasonal_multiplier_for_month( int $month ): float {
		$seasonal_multipliers = self::get_seasonal_multipliers();

		foreach ( $seasonal_multipliers as $season ) {
			if ( in_array( $month, $season['months'] ) ) {
				return $season['multiplier'];
			}
		}

		return 1.0;
	}

	/**
	 * Get season name for specific month
	 */
	public static function get_season_name_for_month( int $month ): string {
		$seasonal_multipliers = self::get_seasonal_multipliers();

		foreach ( $seasonal_multipliers as $key => $season ) {
			if ( in_array( $month, $season['months'] ) ) {
				return $key;
			}
		}

		return 'spring';
	}

	/**
	 * Additional service price calculation (no longer used - AddonManager is used)
	 */
	public static function calculate_addon_prices( array $addons, int $days ): float {
		return 0;
	}

	/**
	 * Get seasonal multipliers
	 */
	public static function get_seasonal_multipliers(): array {
		$settings = self::get_settings();
		return $settings['seasonal_multipliers'] ?? self::get_default_settings()['seasonal_multipliers'];
	}

	/**
	 * Get currency settings
	 */
	public static function get_currency_settings(): array {
		$settings = self::get_settings();
		return $settings['currency_settings'] ?? self::get_default_settings()['currency_settings'];
	}

	/**
	 * Get general settings
	 */
	public static function get_general_settings(): array {
		$settings = self::get_settings();
		return $settings['general_settings'] ?? self::get_default_settings()['general_settings'];
	}

	/**
	 * Get deposit settings
	 */
	public static function get_deposit_settings(): array {
		$settings = self::get_settings();
		return $settings['deposit_settings'] ?? self::get_default_settings()['deposit_settings'];
	}


	/**
	 * Save settings
	 */
	public static function save_settings( array $settings ): bool {
		return SettingsCore::set( 'vehicle_pricing', $settings );
	}

	/**
	 * Clear settings
	 */
	public static function clear_settings(): bool {
		return SettingsCore::delete( 'vehicle_pricing' );
	}

	/**
	 * Reset settings
	 */
	public static function reset_settings(): bool {
		return self::save_settings( self::get_default_settings() );
	}
}
