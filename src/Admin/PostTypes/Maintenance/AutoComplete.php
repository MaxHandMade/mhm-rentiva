<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\PostTypes\Maintenance;

if (! defined('ABSPATH')) {
	exit;
}

// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query,WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

use MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger;
use MHMRentiva\Admin\Booking\Core\Status;
use WP_Query;

final class AutoComplete {

	public const EVENT    = 'mhm_rentiva_auto_complete_event';
	public const SCHEDULE = 'mhm_rentiva_15min';

	public static function register(): void
	{
		add_filter('cron_schedules', array( self::class, 'schedules' ), 1);
		add_action('init', array( self::class, 'maybe_schedule' ), 101);
		add_action(self::EVENT, array( self::class, 'run' ));
	}

	public static function schedules(array $schedules): array
	{
		if (! isset($schedules['mhm_rentiva_15min'])) {
			$schedules['mhm_rentiva_15min'] = array(
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
		$enabled = (string) \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_booking_auto_complete_enabled', '1') === '1';

		if (! $enabled) {
			return;
		}

		$limit = 50;
		$now   = current_time('mysql'); // Local time — consistent with _mhm_dropoff_date

		$q = new WP_Query(
			array(
				'post_type'      => 'vehicle_booking',
				'post_status'    => 'any',
				'fields'         => 'ids',
				'posts_per_page' => $limit,
				'no_found_rows'  => true,
				'meta_query'     => array(
					'relation' => 'AND',
					array(
						'key'     => '_mhm_status',
						'value'   => array( 'confirmed', 'in_progress' ),
						'compare' => 'IN',
					),
					array(
						'relation' => 'OR',
						array(
							'key'     => '_mhm_dropoff_date',
							'value'   => $now,
							'compare' => '<',
							'type'    => 'DATETIME',
						),
						array(
							'key'     => '_mhm_end_date',
							'value'   => $now,
							'compare' => '<',
							'type'    => 'DATETIME',
						),
					),
				),
			)
		);

		if (! $q->have_posts()) {
			return;
		}

		foreach ($q->posts as $bid) {
			$bid = (int) $bid;

			try {
				$updated = Status::update_status($bid, 'completed', 0);

				if (! $updated) {
					continue;
				}

				// Clear availability cache
				$vehicle_id = (int) get_post_meta($bid, '_mhm_vehicle_id', true);
				if ($vehicle_id && class_exists('MHMRentiva\Admin\Booking\Helpers\Cache')) {
					\MHMRentiva\Admin\Booking\Helpers\Cache::invalidateVehicle($vehicle_id);
				}

				if (class_exists(AdvancedLogger::class)) {
					AdvancedLogger::info(
						"Booking #$bid auto-completed (rental end date passed).",
						array( 'booking_id' => $bid ),
						'system'
					);
				}

				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- prefix `mhm_rentiva_` matches Text Domain; Plugin Check false positive.
				do_action('mhm_rentiva_booking_auto_completed', $bid);
			} catch (\Throwable $e) {
				// Per-booking failure must not abort the cron sweep; log and continue.
				if (function_exists('error_log')) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log('[mhm-rentiva] auto-complete skipped booking ' . $bid . ': ' . $e->getMessage());
				}
			}
		}

		wp_reset_postdata();
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
