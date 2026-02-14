<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Vehicle\Helpers;

if (! defined('ABSPATH')) {
	exit;
}

use MHMRentiva\Admin\Core\MetaKeys;

/**
 * Vehicle Data Helper
 *
 * Centralized helper for retrieving vehicle data, handling legacy meta keys,
 * and ensuring consistency across the plugin.
 */
class VehicleDataHelper
{


	/**
	 * Get vehicle price per day
	 *
	 * Checks multiple meta keys for backward compatibility.
	 *
	 * @param int $vehicle_id Vehicle ID
	 * @return float Price per day
	 */
	public static function get_price_per_day(int $vehicle_id): float
	{
		// 1. Prioritize Standard Key
		$price = get_post_meta($vehicle_id, MetaKeys::VEHICLE_PRICE_PER_DAY, true);
		if (! empty($price) && is_numeric($price) && floatval($price) > 0) {
			return floatval($price);
		}

		// 2. Legacy fallback (DEV MODE ONLY)
		if (\MHMRentiva\Admin\Core\Utilities\MetaQueryHelper::is_migration_fallback_active()) {
			$meta_keys = array(
				'_mhm_rentiva_daily_price',
				'_mhm_rentiva_price',
				'daily_price',
				'price_per_day',
				'_price_per_day',
				'_mhm_price_per_day',
				'price',
			);

			foreach ($meta_keys as $key) {
				$price = get_post_meta($vehicle_id, $key, true);
				if (! empty($price) && is_numeric($price) && floatval($price) > 0) {
					return floatval($price);
				}
			}
		}

		return 0.0;
	}

	/**
	 * Get vehicle year
	 *
	 * @param int $vehicle_id Vehicle ID
	 * @return string Vehicle year
	 */
	public static function get_year(int $vehicle_id): string
	{
		$val = get_post_meta($vehicle_id, MetaKeys::VEHICLE_YEAR, true);
		if (! empty($val)) {
			return (string) $val;
		}

		if (\MHMRentiva\Admin\Core\Utilities\MetaQueryHelper::is_migration_fallback_active()) {
			$keys = array('_year', 'year');
			foreach ($keys as $key) {
				$val = get_post_meta($vehicle_id, $key, true);
				if (! empty($val)) {
					return (string) $val;
				}
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
	public static function get_mileage(int $vehicle_id): string
	{
		$keys = array(MetaKeys::VEHICLE_MILEAGE, '_mileage', 'mileage');
		foreach ($keys as $key) {
			$val = get_post_meta($vehicle_id, $key, true);
			if (! empty($val)) {
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
	public static function get_seats(int $vehicle_id): string
	{
		$val = get_post_meta($vehicle_id, MetaKeys::VEHICLE_SEATS, true);
		if (! empty($val)) {
			return (string) $val;
		}

		if (\MHMRentiva\Admin\Core\Utilities\MetaQueryHelper::is_migration_fallback_active()) {
			$keys = array('_seats', 'seats');
			foreach ($keys as $key) {
				$val = get_post_meta($vehicle_id, $key, true);
				if (! empty($val)) {
					return (string) $val;
				}
			}
		}

		return '';
	}

	/**
	 * Get vehicle featured status
	 */
	public static function is_featured(int $vehicle_id): bool
	{
		$val = get_post_meta($vehicle_id, MetaKeys::VEHICLE_FEATURED, true);
		if ($val === '1') {
			return true;
		}

		if (\MHMRentiva\Admin\Core\Utilities\MetaQueryHelper::is_migration_fallback_active()) {
			return get_post_meta($vehicle_id, '_mhm_rentiva_is_featured', true) === '1';
		}

		return false;
	}

	/**
	 * Get vehicle status
	 */
	public static function get_status(int $vehicle_id): string
	{
		$status = get_post_meta($vehicle_id, MetaKeys::VEHICLE_STATUS, true);
		if (! empty($status)) {
			return (string) $status;
		}

		if (\MHMRentiva\Admin\Core\Utilities\MetaQueryHelper::is_migration_fallback_active()) {
			$old = get_post_meta($vehicle_id, '_mhm_vehicle_availability', true);
			if (empty($old)) {
				$old = get_post_meta($vehicle_id, '_mhm_vehicle_availability', true);
			}

			$mapping = array(
				'yes'      => 'active',
				'no'       => 'inactive',
				'1'        => 'active',
				'active'   => 'active',
				'0'        => 'inactive',
				'inactive' => 'inactive',
				'passive'  => 'inactive',
				'maintenance' => 'maintenance',
			);

			if (isset($mapping[$old])) {
				return $mapping[$old];
			}
		}

		return 'active'; // Default
	}

	/**
	 * Get status label
	 */
	public static function get_status_label(string $status): string
	{
		$labels = array(
			'active'      => __('Active', 'mhm-rentiva'),
			'inactive'    => __('Inactive', 'mhm-rentiva'),
			'maintenance' => __('Maintenance', 'mhm-rentiva'),
		);
		return $labels[$status] ?? ucfirst($status);
	}
	/**
	 * Get fuel type label
	 *
	 * @param string $key Fuel type key
	 * @return string Translated label
	 */
	public static function get_fuel_type_label(string $key): string
	{
		$types = array(
			'gasoline' => __('Gasoline', 'mhm-rentiva'),
			'petrol'   => __('Gasoline', 'mhm-rentiva'), // Legacy
			'diesel'   => __('Diesel', 'mhm-rentiva'),
			'lpg'      => __('LPG', 'mhm-rentiva'),
			'electric' => __('Electric', 'mhm-rentiva'),
			'hybrid'   => __('Hybrid', 'mhm-rentiva'),
		);

		return $types[$key] ?? $key;
	}

	/**
	 * Get transmission label
	 *
	 * @param string $key Transmission key
	 * @return string Translated label
	 */
	public static function get_transmission_label(string $key): string
	{
		$types = array(
			'manual'    => __('Manual', 'mhm-rentiva'),
			'auto'      => __('Automatic', 'mhm-rentiva'),
			'semi_auto' => __('Semi-Automatic', 'mhm-rentiva'),
		);

		return $types[$key] ?? $key;
	}
}
