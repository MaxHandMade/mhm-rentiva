<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Vehicle;

if (! defined('ABSPATH')) {
	exit;
}

// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query,WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Bounded queries for score calculation.

use MHMRentiva\Admin\Core\MetaKeys;

/**
 * Calculates and stores vendor reliability scores (0–100).
 *
 * Formula (base 100, subtract demerits, add bonuses):
 * - Start at 100
 * - Each vendor-initiated cancellation in last 6 months: -5
 * - Each withdrawal in last 12 months: -10
 * - Each pause in last 6 months: -2
 * - Each completed booking in last 6 months: +5 (capped at +20)
 * - Clamped to 0–100
 *
 * Scores are stored as user meta and recalculated daily via cron.
 *
 * @since 4.24.0
 */
final class ReliabilityScoreCalculator
{
	/** Points deducted per vendor cancellation. */
	public const CANCEL_PENALTY = 5;

	/** Points deducted per withdrawal. */
	public const WITHDRAWAL_PENALTY = 10;

	/** Points deducted per pause. */
	public const PAUSE_PENALTY = 2;

	/** Points added per completed booking. */
	public const COMPLETION_BONUS = 5;

	/** Maximum bonus from completed bookings. */
	public const MAX_COMPLETION_BONUS = 20;

	/** Base score for all vendors. */
	public const BASE_SCORE = 100;

	/**
	 * Get cancel penalty points from settings (settings-aware).
	 */
	private static function cancel_penalty(): int {
		return (int) \MHMRentiva\Admin\Settings\Core\SettingsCore::get( 'vendor_score_cancel_penalty', self::CANCEL_PENALTY );
	}

	/**
	 * Get withdrawal penalty points from settings (settings-aware).
	 */
	private static function withdrawal_penalty(): int {
		return (int) \MHMRentiva\Admin\Settings\Core\SettingsCore::get( 'vendor_score_withdrawal_penalty', self::WITHDRAWAL_PENALTY );
	}

	/**
	 * Get pause penalty points from settings (settings-aware).
	 */
	private static function pause_penalty(): int {
		return (int) \MHMRentiva\Admin\Settings\Core\SettingsCore::get( 'vendor_score_pause_penalty', self::PAUSE_PENALTY );
	}

	/**
	 * Get completion bonus points from settings (settings-aware).
	 */
	private static function completion_bonus(): int {
		return (int) \MHMRentiva\Admin\Settings\Core\SettingsCore::get( 'vendor_score_completion_bonus', self::COMPLETION_BONUS );
	}

	/**
	 * Get max completion bonus points from settings (settings-aware).
	 */
	private static function max_completion_bonus(): int {
		return (int) \MHMRentiva\Admin\Settings\Core\SettingsCore::get( 'vendor_score_max_completion_bonus', self::MAX_COMPLETION_BONUS );
	}

	/**
	 * Calculate the reliability score for a vendor.
	 *
	 * @param int $vendor_id Vendor user ID.
	 * @return int Score 0–100.
	 */
	public static function calculate(int $vendor_id): int
	{
		$score = self::BASE_SCORE;

		// Demerits: vendor-initiated cancellations (6 months).
		$score -= self::count_vendor_cancellations($vendor_id) * self::cancel_penalty();

		// Demerits: withdrawals (12 months).
		$score -= PenaltyCalculator::get_rolling_withdrawal_count($vendor_id) * self::withdrawal_penalty();

		// Demerits: pauses (6 months).
		$score -= self::count_recent_pauses($vendor_id) * self::pause_penalty();

		// Bonus: completed bookings (6 months).
		$completions = self::count_completed_bookings($vendor_id);
		$score += min($completions * self::completion_bonus(), self::max_completion_bonus());

		return max(0, min(100, $score));
	}

	/**
	 * Calculate and persist the reliability score for a vendor.
	 *
	 * @param int $vendor_id Vendor user ID.
	 * @return int The calculated score.
	 */
	public static function update(int $vendor_id): int
	{
		$score = self::calculate($vendor_id);

		update_user_meta($vendor_id, MetaKeys::VENDOR_RELIABILITY_SCORE, $score);
		update_user_meta($vendor_id, MetaKeys::VENDOR_RELIABILITY_UPDATED_AT, gmdate('Y-m-d H:i:s'));

		return $score;
	}

	/**
	 * Get the stored reliability score for a vendor.
	 *
	 * Returns 100 (perfect) if no score has been calculated yet.
	 *
	 * @param int $vendor_id Vendor user ID.
	 * @return int Score 0–100.
	 */
	public static function get(int $vendor_id): int
	{
		$score = get_user_meta($vendor_id, MetaKeys::VENDOR_RELIABILITY_SCORE, true);

		if ($score === '' || $score === false) {
			return self::BASE_SCORE;
		}

		return max(0, min(100, (int) $score));
	}

	/**
	 * Get a human-readable label for the score.
	 *
	 * @param int $score Score 0–100.
	 * @return string Label.
	 */
	public static function get_label(int $score): string
	{
		if ($score >= 90) {
			return __('Excellent', 'mhm-rentiva');
		}
		if ($score >= 70) {
			return __('Good', 'mhm-rentiva');
		}
		if ($score >= 50) {
			return __('Fair', 'mhm-rentiva');
		}

		return __('Poor', 'mhm-rentiva');
	}

	/**
	 * Get the color for a score tier.
	 *
	 * @param int $score Score 0–100.
	 * @return string Hex color.
	 */
	public static function get_color(int $score): string
	{
		if ($score >= 90) {
			return '#28a745'; // Green.
		}
		if ($score >= 70) {
			return '#17a2b8'; // Blue.
		}
		if ($score >= 50) {
			return '#ffc107'; // Yellow.
		}

		return '#dc3545'; // Red.
	}

	/**
	 * Count vendor-initiated booking cancellations in last 6 months.
	 *
	 * A vendor cancellation is identified by: the booking's vehicle was owned by the
	 * user who cancelled, and the cancellation was not by admin (user_id > 0).
	 */
	private static function count_vendor_cancellations(int $vendor_id): int
	{
		$cutoff = gmdate('Y-m-d H:i:s', strtotime('-6 months'));

		// Get all vehicle IDs owned by this vendor.
		$vehicle_ids = get_posts(array(
			'post_type'      => 'vehicle',
			'post_status'    => 'any',
			'author'         => $vendor_id,
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		));

		if (empty($vehicle_ids)) {
			return 0;
		}

		// Count cancelled bookings where:
		// - vehicle belongs to this vendor
		// - cancellation was recent
		// - cancelled_by was the vendor (check cancellation_data)
		$cancelled_bookings = get_posts(array(
			'post_type'      => 'vehicle_booking',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'meta_query'     => array(
				'relation' => 'AND',
				array(
					'key'     => '_mhm_vehicle_id',
					'value'   => $vehicle_ids,
					'compare' => 'IN',
				),
				array(
					'key'   => '_mhm_status',
					'value' => 'cancelled',
				),
			),
			'date_query'     => array(
				array(
					'after' => $cutoff,
				),
			),
		));

		$count = 0;

		foreach ($cancelled_bookings as $booking_id) {
			$cancellation_data = get_post_meta((int) $booking_id, '_mhm_cancellation_data', true);

			if (is_array($cancellation_data) && isset($cancellation_data['cancelled_by'])) {
				if ((int) $cancellation_data['cancelled_by'] === $vendor_id) {
					++$count;
				}
			}
		}

		return $count;
	}

	/**
	 * Count pauses across all vehicles for this vendor in last 6 months.
	 *
	 * Uses the monthly pause counter meta format "YYYY-MM:count".
	 */
	private static function count_recent_pauses(int $vendor_id): int
	{
		$vehicle_ids = get_posts(array(
			'post_type'      => 'vehicle',
			'post_status'    => 'any',
			'author'         => $vendor_id,
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		));

		if (empty($vehicle_ids)) {
			return 0;
		}

		$total = 0;
		$current_month = new \DateTime('first day of this month');

		for ($i = 0; $i < 6; $i++) {
			$month_key = $current_month->format('Y-m');

			foreach ($vehicle_ids as $vid) {
				$stored = get_post_meta((int) $vid, '_mhm_vehicle_pause_count_month', true);
				if (is_string($stored) && strpos($stored, $month_key . ':') === 0) {
					$total += (int) substr($stored, strlen($month_key) + 1);
				}
			}

			$current_month->modify('-1 month');
		}

		return $total;
	}

	/**
	 * Count completed bookings for vehicles owned by this vendor in last 6 months.
	 */
	private static function count_completed_bookings(int $vendor_id): int
	{
		$cutoff = gmdate('Y-m-d H:i:s', strtotime('-6 months'));

		$vehicle_ids = get_posts(array(
			'post_type'      => 'vehicle',
			'post_status'    => 'any',
			'author'         => $vendor_id,
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		));

		if (empty($vehicle_ids)) {
			return 0;
		}

		$completed = get_posts(array(
			'post_type'      => 'vehicle_booking',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'meta_query'     => array(
				'relation' => 'AND',
				array(
					'key'     => '_mhm_vehicle_id',
					'value'   => $vehicle_ids,
					'compare' => 'IN',
				),
				array(
					'key'   => '_mhm_status',
					'value' => 'completed',
				),
			),
			'date_query'     => array(
				array(
					'after' => $cutoff,
				),
			),
		));

		return count($completed);
	}
}
