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
	 *
	 * The stored value is whatever is in `mhmrentiva_settings['vehicle_pricing']`,
	 * and that is not guaranteed to be an array: SettingsSanitizer's
	 * programmatic-update path deliberately passes array values through
	 * untouched, and nothing stops an older install or a third-party
	 * `update_option()` from leaving a scalar there. Without the shape check
	 * this method's own `: array` return type is the fatal.
	 */
	public static function get_settings(): array {
		$settings = SettingsCore::get( 'vehicle_pricing', self::get_default_settings() );
		return is_array( $settings ) ? $settings : self::get_default_settings();
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
	 *
	 * READ PATH OF THE PUBLIC BOOKING FORM. BookingForm.php calls
	 * get_seasonal_multiplier_for_date() once per rental day whenever the
	 * `mhmrentiva_vehicle_seasonal_pricing` master switch is on, so anything
	 * this method throws is a fatal on a page a visitor loads.
	 *
	 * It therefore trusts nothing about the stored shape. A season entry that
	 * is not an array, or that carries no usable `months` list, is skipped
	 * rather than evaluated -- previously `in_array( $month, $season['months'] )`
	 * received null/int/string as its haystack and raised
	 * `TypeError: in_array(): Argument #2 ($haystack) must be of type array`.
	 * A matching season with a missing or non-numeric `multiplier` falls back
	 * to the same neutral 1.0 this method already returns when nothing matches,
	 * because returning it raw would break the `: float` return type under
	 * this file's declare(strict_types=1).
	 *
	 * The comparison stays loose on purpose: months arrive from a form as
	 * numeric strings, and `in_array( 7, array( '6', '7', '8' ) )` must keep
	 * matching exactly as it did before.
	 */
	public static function get_seasonal_multiplier_for_month( int $month ): float {
		$seasonal_multipliers = self::get_seasonal_multipliers();

		foreach ( $seasonal_multipliers as $season ) {
			if ( ! is_array( $season ) || ! isset( $season['months'] ) || ! is_array( $season['months'] ) ) {
				continue;
			}

			if ( in_array( $month, $season['months'] ) ) {
				return isset( $season['multiplier'] ) && is_numeric( $season['multiplier'] )
					? (float) $season['multiplier']
					: 1.0;
			}
		}

		return 1.0;
	}

	/**
	 * Get seasonal multipliers
	 *
	 * Falls back to the defaults when the stored block is missing OR is not an
	 * array -- the `?? ` alone only covered the missing case, and this method's
	 * `: array` return type made the other one a fatal.
	 */
	public static function get_seasonal_multipliers(): array {
		$settings = self::get_settings();
		$seasonal = $settings['seasonal_multipliers'] ?? null;

		return is_array( $seasonal ) ? $seasonal : self::get_default_settings()['seasonal_multipliers'];
	}
}
