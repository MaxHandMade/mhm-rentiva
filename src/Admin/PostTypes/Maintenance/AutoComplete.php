<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\PostTypes\Maintenance;

if (! defined('ABSPATH')) {
	exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- WP-Cron sweep that finds bookings whose end date has passed and completes them. It runs unattended on a schedule, so a cached answer is a wrong answer: the whole point is to see rows that changed since the last run. Scope is bounded by a date comparison, not by a page of results.

use MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger;
use MHMRentiva\Admin\Booking\Core\Status;

final class AutoComplete {

	public const EVENT    = 'mhmrentiva_auto_complete_event';
	public const SCHEDULE = 'mhmrentiva_15min';

	public static function register(): void
	{
		add_filter('cron_schedules', array( self::class, 'schedules' ), 1);
		add_action('init', array( self::class, 'maybe_schedule' ), 101);
		add_action(self::EVENT, array( self::class, 'run' ));
	}

	public static function schedules(array $schedules): array
	{
		if (! isset($schedules['mhmrentiva_15min'])) {
			$schedules['mhmrentiva_15min'] = array(
				'interval' => 900, // 15 dakika
				'display'  => __('Every 15 Minutes (Rentiva)', 'mhm-rentiva'),
			);
		}

		return $schedules;
	}

	public static function maybe_schedule(): void
	{
		add_filter('cron_schedules', array( self::class, 'schedules' ), 1);
		$schedules = wp_get_schedules();

		if (! isset($schedules[ self::SCHEDULE ])) {
			return;
		}

		if (wp_next_scheduled(self::EVENT)) {
			return;
		}

		self::direct_schedule_event();
	}

	public static function run(): void
	{
		$enabled = (string) \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhmrentiva_booking_auto_complete_enabled', '1') === '1';

		if (! $enabled) {
			return;
		}

		$limit     = 50;
		$now_ts    = (int) current_time('timestamp');
		$now_local = current_time('mysql'); // Local time — consistent with _mhmrentiva_dropoff_date

		global $wpdb;

		/*
		 * Datetime-based selection: _mhmrentiva_end_ts (UNIX) is authoritative when present.
		 * Fallback: CONCAT(dropoff_date, ' ', dropoff_time) — defaults to '23:59:59'
		 *           when dropoff_time meta is missing/empty (treat as end-of-day,
		 *           never auto-complete mid-day).
		 * Legacy fallback: _mhmrentiva_end_date (parallel field for pre-checkout-rewrite data).
		 *
		 * Direct SQL because WP_Query meta_query cannot compose CONCAT across two meta keys.
		 */
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Cron sweep, no caching by design.
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT p.ID
				 FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} st  ON st.post_id  = p.ID AND st.meta_key  = '_mhmrentiva_status'
				 LEFT  JOIN {$wpdb->postmeta} ets ON ets.post_id = p.ID AND ets.meta_key = '_mhmrentiva_end_ts'
				 LEFT  JOIN {$wpdb->postmeta} dd  ON dd.post_id  = p.ID AND dd.meta_key  = '_mhmrentiva_dropoff_date'
				 LEFT  JOIN {$wpdb->postmeta} dt  ON dt.post_id  = p.ID AND dt.meta_key  = '_mhmrentiva_dropoff_time'
				 LEFT  JOIN {$wpdb->postmeta} ed  ON ed.post_id  = p.ID AND ed.meta_key  = '_mhmrentiva_end_date'
				 WHERE p.post_type = 'mhmrentiva_booking'
				   AND p.post_status NOT IN ('trash', 'auto-draft')
				   AND st.meta_value IN ('confirmed', 'in_progress')
				   AND (
					   ( ets.meta_value IS NOT NULL AND ets.meta_value <> '' AND CAST(ets.meta_value AS UNSIGNED) < %d )
					   OR
					   (
						 ( ets.meta_value IS NULL OR ets.meta_value = '' )
						 AND dd.meta_value IS NOT NULL AND dd.meta_value <> ''
						 AND CONCAT(dd.meta_value, ' ', COALESCE(NULLIF(dt.meta_value, ''), '23:59:59')) < %s
					   )
					   OR
					   (
						 ( ets.meta_value IS NULL OR ets.meta_value = '' )
						 AND ( dd.meta_value IS NULL OR dd.meta_value = '' )
						 AND ed.meta_value IS NOT NULL AND ed.meta_value <> ''
						 AND CONCAT(ed.meta_value, ' 23:59:59') < %s
					   )
				   )
				 LIMIT %d",
				$now_ts,
				$now_local,
				$now_local,
				$limit
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

		if (empty($ids)) {
			return;
		}

		foreach ($ids as $bid) {
			$bid = (int) $bid;

			try {
				$updated = Status::update_status($bid, 'completed', 0);

				if (! $updated) {
					continue;
				}

				// Clear availability cache
				$vehicle_id = (int) get_post_meta($bid, '_mhmrentiva_vehicle_id', true);
				if ($vehicle_id && class_exists('MHMRentiva\Admin\Booking\Helpers\Cache')) {
					\MHMRentiva\Admin\Booking\Helpers\Cache::invalidateVehicle($vehicle_id);
				}

				if (class_exists(AdvancedLogger::class)) {
					AdvancedLogger::info(
						"Booking #$bid auto-completed (rental end datetime passed).",
						array( 'booking_id' => $bid ),
						'system'
					);
				}

				do_action('mhmrentiva_booking_auto_completed', $bid);
			} catch (\Throwable $e) {
				// Per-booking failure must not abort the cron sweep; log and continue.
				// Routed to the plugin's own logger instead of the site's PHP error log.
				//
				// Task 14b item 3: promoted from warning() to error(). A
				// per-booking exception here means the sweep silently never
				// completed a booking it selected -- the same shape as
				// AutoCancel.php's sibling catch -- and warning() is
				// exactly the level a stock install drops.
				if (class_exists(\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::class)) {
					\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::error_linked(
						sprintf(
							/* translators: %s: the throwable message that interrupted this booking's auto-complete. */
							__( 'Auto-complete skipped a booking: %s', 'mhm-rentiva' ),
							$e->getMessage()
						),
						$bid,
						array( 'error' => $e->getMessage() ),
						\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::CATEGORY_SYSTEM
					);
				}
			}
		}
	}

	private static function direct_schedule_event(): void
	{
		$schedules = wp_get_schedules();

		if (! isset($schedules[ self::SCHEDULE ])) {
			return;
		}

		$cron = _get_cron_array();
		if ($cron === false) {
			$cron = array();
		}

		foreach ($cron as $timestamp => $cronhooks) {
			if (isset($cronhooks[ self::EVENT ])) {
				unset($cron[ $timestamp ][ self::EVENT ]);
				if (empty($cron[ $timestamp ])) {
					unset($cron[ $timestamp ]);
				}
			}
		}

		$interval  = $schedules[ self::SCHEDULE ]['interval'];
		$timestamp = time() + $interval;
		$key       = md5(serialize(array()));

		$cron[ $timestamp ][ self::EVENT ][ $key ] = array(
			'schedule' => self::SCHEDULE,
			'args'     => array(),
			'interval' => $interval,
		);

		ksort($cron);
		_set_cron_array($cron);
	}
}
