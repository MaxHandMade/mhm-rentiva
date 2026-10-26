<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Booking\Pricing;

use MHMRentiva\Admin\Addons\AddonPricingCalculator;
use MHMRentiva\Admin\Booking\Helpers\SiteTime;
use MHMRentiva\Admin\Booking\Helpers\Util;
use MHMRentiva\Admin\Settings\Core\SettingsCore;
use MHMRentiva\Admin\Vehicle\Helpers\VehicleDataHelper;
use MHMRentiva\Admin\Vehicle\Settings\VehiclePricingSettings;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * The one place a rental is priced.
 *
 * Before this class the booking form quoted from its own loop (season and a
 * tax line included) while the charge went through Util::total_price()
 * (no season, no tax), so a visitor could be shown one total and charged
 * another. Every path -- form quote, submit, deposit, cart, manual booking,
 * recalculation, availability -- now reaches this computation.
 *
 * Per day: daily price x base multiplier x season multiplier, then x weekend
 * multiplier on Saturdays and Sundays. Days -- weekday and season alike -- are
 * the local calendar days in the site timezone (`wp_timezone()`), counted from
 * the pickup's local date.
 */
final class RentalPricing {

	/**
	 * Price the window between two timestamps (form normalisation applies:
	 * the day count comes from Util::rental_days()).
	 *
	 * @param array<int|string, int|string> $addon_ids
	 * @param array<int|string, int>        $quantities Add-on id => quantity (missing = 1).
	 */
	public static function quote(int $vehicle_id, int $start_ts, int $end_ts, array $addon_ids = array(), array $quantities = array()): RentalQuote
	{
		return self::quote_for_days($vehicle_id, Util::rental_days($start_ts, $end_ts), $start_ts, $addon_ids, $quantities);
	}

	/**
	 * Price a known number of days starting at $start_ts.
	 *
	 * Without a start date ($start_ts <= 0) there is no way to know which days
	 * are weekends or in which season they fall, so every day is charged at
	 * the base rate -- the same answer Util::total_price() has always given.
	 *
	 * @param array<int|string, int|string> $addon_ids
	 * @param array<int|string, int>        $quantities Add-on id => quantity (missing = 1).
	 */
	public static function quote_for_days(int $vehicle_id, int $days, int $start_ts, array $addon_ids = array(), array $quantities = array()): RentalQuote
	{
		$days = max(0, $days);

		$base_multiplier = (float) SettingsCore::get('mhmrentiva_vehicle_base_price', 1.0);
		if ($base_multiplier <= 0) {
			$base_multiplier = 1.0;
		}
		$weekend_multiplier = (float) SettingsCore::get('mhmrentiva_vehicle_weekend_multiplier', 1.2);
		$seasonal_enabled   = '1' === (string) SettingsCore::get('mhmrentiva_vehicle_seasonal_pricing', '0');

		$daily_price = VehicleDataHelper::get_price_per_day($vehicle_id);
		if ($daily_price > 0) {
			$daily_price *= $base_multiplier;
		} else {
			$daily_price = 0.0;
		}

		$day_lines    = array();
		$rental       = 0.0;
		$surcharge    = 0.0;
		$weekend_days = 0;

		$dates         = $start_ts > 0 ? SiteTime::days_from($start_ts, $days) : array();
		$first_weekday = $start_ts > 0 ? SiteTime::weekday($start_ts) : 0;

		for ($i = 0; $i < $days; $i++) {
			$date     = $dates[ $i ] ?? '';
			$weekday  = '' !== $date ? ( ( $first_weekday - 1 + $i ) % 7 ) + 1 : 0;
			$standard = $daily_price;

			if ($seasonal_enabled && '' !== $date) {
				$standard *= VehiclePricingSettings::get_seasonal_multiplier_for_date($date);
			}

			$extra = 0.0;
			if ($weekday >= 6 && $weekend_multiplier > 1.0) {
				$extra = ( $standard * $weekend_multiplier ) - $standard;
				++$weekend_days;
			}

			$day_lines[] = array(
				'date'          => $date,
				'standard'      => $standard,
				'weekend_extra' => $extra,
			);
			$rental     += $standard + $extra;
			$surcharge  += $extra;
		}

		$addon_total = 0.0;
		foreach ($addon_ids as $addon_id) {
			$addon_total += AddonPricingCalculator::calculate(
				(int) $addon_id,
				array(
					'rental_days' => $days,
					'quantity'    => (int) ( $quantities[ (int) $addon_id ] ?? 1 ),
				)
			);
		}

		return new RentalQuote(
			$days,
			$day_lines,
			$daily_price,
			$base_multiplier,
			$weekend_multiplier,
			$weekend_days,
			$surcharge,
			$rental,
			$addon_total,
			$rental + $addon_total
		);
	}
}
