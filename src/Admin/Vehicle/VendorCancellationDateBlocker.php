<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Vehicle;

if (! defined('ABSPATH')) {
	exit;
}

use MHMRentiva\Admin\Vehicle\Meta\BlockedDatesMetaBox;

/**
 * Anti-gaming: blocks vehicle dates when a vendor cancels a confirmed booking.
 *
 * Prevents the pattern: vendor cancels a booking → relists the same dates
 * at a higher price. When the vendor (vehicle owner) cancels, the booking's
 * date range is re-blocked on the vehicle calendar for BLOCK_DURATION_DAYS.
 *
 * These blocked dates appear in the existing BlockedDatesMetaBox calendar
 * and are respected by the availability engine.
 *
 * @since 4.24.0
 */
final class VendorCancellationDateBlocker {

	/** How long vendor-cancelled dates remain blocked (days). */
	public const BLOCK_DURATION_DAYS = 30;

	/** Meta key tracking penalty-blocked date entries per vehicle. */
	private const PENALTY_BLOCKS_META = '_mhm_vehicle_penalty_blocked_dates';

	/**
	 * Get block duration days from settings (settings-aware).
	 */
	private static function block_duration_days(): int {
		return (int) \MHMRentiva\Admin\Settings\Core\SettingsCore::get( 'vendor_anti_gaming_block_days', self::BLOCK_DURATION_DAYS );
	}

	/**
	 * Register the hook listener.
	 */
	public static function register(): void
	{
		add_action('mhm_rentiva_booking_cancelled', array( self::class, 'maybe_block_dates' ), 30, 3);
	}

	/**
	 * If the canceller is the vehicle owner (vendor), re-block the dates.
	 *
	 * @param int    $booking_id Booking post ID.
	 * @param int    $user_id    User who cancelled (0 = admin).
	 * @param string $reason     Cancellation reason.
	 */
	public static function maybe_block_dates(int $booking_id, int $user_id, string $reason): void
	{
		if ($user_id <= 0) {
			return; // Admin cancellation — no penalty.
		}

		$vehicle_id = (int) get_post_meta($booking_id, '_mhm_vehicle_id', true);
		if ($vehicle_id <= 0) {
			return;
		}

		$vehicle_author = (int) get_post_field('post_author', $vehicle_id);

		// Only block if the VENDOR (vehicle owner) cancelled.
		if ($user_id !== $vehicle_author) {
			return;
		}

		$pickup_date  = get_post_meta($booking_id, '_mhm_pickup_date', true);
		$dropoff_date = get_post_meta($booking_id, '_mhm_dropoff_date', true);

		if (empty($pickup_date) || empty($dropoff_date)) {
			return;
		}

		$dates_to_block = self::generate_date_range($pickup_date, $dropoff_date);

		if (empty($dates_to_block)) {
			return;
		}

		self::add_blocked_dates($vehicle_id, $dates_to_block, $booking_id);
	}

	/**
	 * Generate an array of date strings (Y-m-d) from start to end (inclusive start, exclusive end).
	 *
	 * @return string[]
	 */
	private static function generate_date_range(string $start, string $end): array
	{
		$dates    = array();
		$current  = new \DateTime($start);
		$end_date = new \DateTime($end);

		while ($current < $end_date) {
			$dates[] = $current->format('Y-m-d');
			$current->modify('+1 day');
		}

		return $dates;
	}

	/**
	 * Add dates to the vehicle's blocked dates (existing BlockedDatesMetaBox system).
	 *
	 * Also records the penalty block with an expiry date so it can be auto-unblocked later.
	 *
	 * @param int      $vehicle_id    Vehicle post ID.
	 * @param string[] $dates         Dates to block (Y-m-d format).
	 * @param int      $booking_id    Source booking ID.
	 */
	private static function add_blocked_dates(int $vehicle_id, array $dates, int $booking_id): void
	{
		// 1. Add to the existing blocked dates meta (read by BlockedDatesMetaBox and availability engine).
		$existing = get_post_meta($vehicle_id, '_mhm_blocked_dates', true);
		if (! is_array($existing)) {
			$existing = array();
		}

		$merged = array_values(array_unique(array_merge($existing, $dates)));
		sort($merged);
		update_post_meta($vehicle_id, '_mhm_blocked_dates', $merged);

		// 2. Track penalty blocks separately for auto-unblock cron (future enhancement).
		$penalty_blocks = get_post_meta($vehicle_id, self::PENALTY_BLOCKS_META, true);
		if (! is_array($penalty_blocks)) {
			$penalty_blocks = array();
		}

		$expires_at = gmdate('Y-m-d', strtotime('+' . self::block_duration_days() . ' days'));

		$penalty_blocks[] = array(
			'booking_id' => $booking_id,
			'dates'      => $dates,
			'blocked_at' => gmdate('Y-m-d H:i:s'),
			'expires_at' => $expires_at,
		);

		update_post_meta($vehicle_id, self::PENALTY_BLOCKS_META, $penalty_blocks);

		do_action('mhm_rentiva_vehicle_dates_penalty_blocked', $vehicle_id, $booking_id, $dates);
	}

	/**
	 * Get all active penalty-blocked date entries for a vehicle.
	 *
	 * @param int $vehicle_id Vehicle post ID.
	 * @return array[] Array of penalty block entries that have not expired.
	 */
	public static function get_active_blocks(int $vehicle_id): array
	{
		$penalty_blocks = get_post_meta($vehicle_id, self::PENALTY_BLOCKS_META, true);
		if (! is_array($penalty_blocks)) {
			return array();
		}

		$today  = gmdate('Y-m-d');
		$active = array();

		foreach ($penalty_blocks as $block) {
			if (isset($block['expires_at']) && $block['expires_at'] >= $today) {
				$active[] = $block;
			}
		}

		return $active;
	}
}
