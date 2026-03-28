<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Vehicle;

if (! defined('ABSPATH')) {
	exit;
}

// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query,WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Bounded query for penalty calculation.
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query for aggregated ledger data.

use MHMRentiva\Admin\Core\MetaKeys;

/**
 * Calculates progressive withdrawal penalties for vendors.
 *
 * Penalty tiers (per rolling 12-month window):
 * - 1st withdrawal: Free (₺0)
 * - 2nd withdrawal: 10% of monthly average revenue
 * - 3rd+ withdrawal: 25% of monthly average revenue
 *
 * @since 4.24.0
 */
final class PenaltyCalculator
{
	/** First withdrawal is free. */
	public const TIER_1_RATE = 0.0;

	/** Second withdrawal: 10% of monthly avg revenue. */
	public const TIER_2_RATE = 0.10;

	/** Third and subsequent: 25% of monthly avg revenue. */
	public const TIER_3_RATE = 0.25;

	/** Rolling window for withdrawal count (months). */
	public const ROLLING_WINDOW_MONTHS = 12;

	/**
	 * Get tier 2 penalty rate from settings (settings-aware).
	 */
	public static function tier2_rate(): float {
		return (float) \MHMRentiva\Admin\Settings\Core\SettingsCore::get( 'vendor_penalty_tier2_rate', 10 ) / 100.0;
	}

	/**
	 * Get tier 3 penalty rate from settings (settings-aware).
	 */
	public static function tier3_rate(): float {
		return (float) \MHMRentiva\Admin\Settings\Core\SettingsCore::get( 'vendor_penalty_tier3_rate', 25 ) / 100.0;
	}

	/**
	 * Get rolling window months from settings (settings-aware).
	 */
	public static function rolling_window_months(): int {
		return (int) \MHMRentiva\Admin\Settings\Core\SettingsCore::get( 'vendor_penalty_rolling_window_months', self::ROLLING_WINDOW_MONTHS );
	}

	/**
	 * Calculate the withdrawal penalty amount for a vehicle.
	 *
	 * @param int $vehicle_id Vehicle post ID.
	 * @param int $vendor_id  Vendor user ID.
	 * @return float Penalty amount (0.0 if first withdrawal or no revenue).
	 */
	public static function calculate_withdrawal_penalty(int $vehicle_id, int $vendor_id): float
	{
		$withdrawal_count = self::get_rolling_withdrawal_count($vendor_id);
		$rate = self::get_penalty_rate($withdrawal_count);

		if ($rate <= 0.0) {
			return 0.0;
		}

		$monthly_avg = self::get_monthly_average_revenue($vendor_id);

		if ($monthly_avg <= 0.0) {
			return 0.0;
		}

		return round($monthly_avg * $rate, 2);
	}

	/**
	 * Get the penalty rate based on the number of prior withdrawals.
	 *
	 * @param int $count Number of withdrawals in rolling window (BEFORE the current one).
	 * @return float Penalty rate (0.0 to 0.25).
	 */
	public static function get_penalty_rate(int $count): float
	{
		if ($count <= 0) {
			return self::TIER_1_RATE;
		}

		if ($count === 1) {
			return self::tier2_rate();
		}

		return self::tier3_rate();
	}

	/**
	 * Count how many withdrawals the vendor has made in the rolling 12-month window.
	 *
	 * Counts all vehicles owned by this vendor that have been withdrawn.
	 *
	 * @param int $vendor_id Vendor user ID.
	 * @return int Number of withdrawals in the window.
	 */
	public static function get_rolling_withdrawal_count(int $vendor_id): int
	{
		$cutoff = gmdate('Y-m-d H:i:s', strtotime('-' . self::rolling_window_months() . ' months'));

		$withdrawn_vehicles = get_posts(array(
			'post_type'      => 'vehicle',
			'post_status'    => 'any',
			'author'         => $vendor_id,
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'meta_query'     => array(
				array(
					'key'     => MetaKeys::VEHICLE_WITHDRAWN_AT,
					'value'   => $cutoff,
					'compare' => '>=',
					'type'    => 'DATETIME',
				),
			),
		));

		return count($withdrawn_vehicles);
	}

	/**
	 * Get the vendor's monthly average revenue from the ledger.
	 *
	 * Looks at cleared commission_credit entries over the last 6 months.
	 *
	 * @param int $vendor_id Vendor user ID.
	 * @return float Monthly average revenue.
	 */
	public static function get_monthly_average_revenue(int $vendor_id): float
	{
		global $wpdb;

		$table  = esc_sql($wpdb->prefix . 'mhm_rentiva_ledger');
		$cutoff = gmdate('Y-m-d H:i:s', strtotime('-6 months'));

		$result = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(amount), 0) as total, COUNT(DISTINCT DATE_FORMAT(created_at, '%%Y-%%m')) as months
				 FROM `{$table}`
				 WHERE vendor_id = %d
				   AND type = 'commission_credit'
				   AND status IN ('cleared', 'pending')
				   AND created_at >= %s",
				$vendor_id,
				$cutoff
			)
		);

		if (! $result || (int) $result->months === 0) {
			return 0.0;
		}

		return round((float) $result->total / (int) $result->months, 2);
	}
}
