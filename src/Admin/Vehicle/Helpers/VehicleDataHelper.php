<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Vehicle\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Vehicle Data Helper
 *
 * Centralized helper for retrieving vehicle data, handling legacy meta keys,
 * and ensuring consistency across the plugin.
 */
class VehicleDataHelper {

	/**
	 * Get vehicle price per day
	 *
	 * Checks multiple meta keys for backward compatibility.
	 *
	 * @param int $vehicle_id Vehicle ID
	 * @return float Price per day
	 */
	public static function get_price_per_day( int $vehicle_id ): float {
		// List of meta keys to check, in order of preference
		$meta_keys = array(
			'_mhm_rentiva_price_per_day',
			'_mhm_rentiva_daily_price',
			'_mhm_rentiva_price',
			'daily_price',
			'price_per_day',
			'_price_per_day',
			'_mhm_price_per_day',
			'price',
		);

		foreach ( $meta_keys as $key ) {
			$price = get_post_meta( $vehicle_id, $key, true );
			if ( ! empty( $price ) && is_numeric( $price ) && floatval( $price ) > 0 ) {
				return floatval( $price );
			}
		}

		// Default fallback if no valid price found
		return 0.0;
	}

	/**
	 * Get vehicle year
	 *
	 * @param int $vehicle_id Vehicle ID
	 * @return string Vehicle year
	 */
	public static function get_year( int $vehicle_id ): string {
		$keys = array( '_mhm_rentiva_year', '_year', 'year' );
		foreach ( $keys as $key ) {
			$val = get_post_meta( $vehicle_id, $key, true );
			if ( ! empty( $val ) ) {
				return (string) $val;
			}
		}
		return '';
	}

	/**
	 * Get vehicle mileage
	 *
	 * @param int $vehicle_id Vehicle ID
	 * @return string Vehicle mileage
	 */
	public static function get_mileage( int $vehicle_id ): string {
		$keys = array( '_mhm_rentiva_mileage', '_mileage', 'mileage' );
		foreach ( $keys as $key ) {
			$val = get_post_meta( $vehicle_id, $key, true );
			if ( ! empty( $val ) ) {
				return (string) $val;
			}
		}
		return '';
	}

	/**
	 * Get vehicle seats
	 *
	 * @param int $vehicle_id Vehicle ID
	 * @return string Vehicle seats
	 */
	public static function get_seats( int $vehicle_id ): string {
		$keys = array( '_mhm_rentiva_seats', '_seats', 'seats' );
		foreach ( $keys as $key ) {
			$val = get_post_meta( $vehicle_id, $key, true );
			if ( ! empty( $val ) ) {
				return (string) $val;
			}
		}
		return '';
	}
	/**
	 * Get fuel type label
	 *
	 * @param string $key Fuel type key
	 * @return string Translated label
	 */
	public static function get_fuel_type_label( string $key ): string {
		$types = array(
			'gasoline' => __( 'Gasoline', 'mhm-rentiva' ),
			'petrol'   => __( 'Gasoline', 'mhm-rentiva' ), // Legacy
			'diesel'   => __( 'Diesel', 'mhm-rentiva' ),
			'lpg'      => __( 'LPG', 'mhm-rentiva' ),
			'electric' => __( 'Electric', 'mhm-rentiva' ),
			'hybrid'   => __( 'Hybrid', 'mhm-rentiva' ),
		);

		return $types[ $key ] ?? $key;
	}

	/**
	 * Get transmission label
	 *
	 * @param string $key Transmission key
	 * @return string Translated label
	 */
	public static function get_transmission_label( string $key ): string {
		$types = array(
			'manual'    => __( 'Manual', 'mhm-rentiva' ),
			'auto'      => __( 'Automatic', 'mhm-rentiva' ),
			'semi_auto' => __( 'Semi-Automatic', 'mhm-rentiva' ),
		);

		return $types[ $key ] ?? $key;
	}
}
