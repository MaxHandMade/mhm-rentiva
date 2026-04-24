<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\PostTypes\Maintenance;

if (! defined('ABSPATH')) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Legacy/public hook and template naming kept for backward compatibility.


// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query,WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value,WordPress.DB.SlowDBQuery.slow_db_query_tax_query,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded application queries are intentional in this module.



use MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger;
use MHMRentiva\Admin\Booking\Core\Status;
use WP_Query;
use Exception;



final class AutoCancel {



	public const EVENT    = 'mhm_rentiva_auto_cancel_event';
	public const SCHEDULE = 'mhm_rentiva_5min'; // Changed to 5min to match DatabaseInitialization

	public static function register(): void
	{
		// Add custom schedules - register immediately so it's available when wp_schedule_event is called
		// The filter is lazy-loaded, so it will be applied when wp_get_schedules() is called
		// Use priority 1 to ensure it's registered before other plugins
		add_filter('cron_schedules', array( self::class, 'schedules' ), 1);

		// Also ensure it's registered on plugins_loaded if not already
		if (! did_action('plugins_loaded')) {
			add_action(
				'plugins_loaded',
				function () {
					add_filter('cron_schedules', array( self::class, 'schedules' ), 1);
				},
				1
			);
		}

		// Schedule event if not scheduled
		add_action('init', array( self::class, 'maybe_schedule' ), 100);

		// Hook runner
		add_action(self::EVENT, array( self::class, 'run' ));
	}

	public static function schedules(array $schedules): array
	{
		if (! isset($schedules['mhm_rentiva_5min'])) {
			$schedules['mhm_rentiva_5min'] = array(
				'interval' => 300, // 5 min
				'display'  => __('Every 5 Minutes (Rentiva)', 'mhm-rentiva'),
			);
		}

		if (! isset($schedules['mhm_rentiva_15min'])) {
			$schedules['mhm_rentiva_15min'] = array(
				'interval' => 900, // 15 min
				'display'  => __('Every 15 Minutes (Rentiva)', 'mhm-rentiva'),
			);
		}

		return $schedules;
	}

	public static function maybe_schedule(): void
	{
		// Ensure schedule filter is applied before checking schedules
		add_filter('cron_schedules', array( self::class, 'schedules' ), 1);

		// Get schedules (this will trigger the filter)
		$schedules = wp_get_schedules();

		if (! isset($schedules[ self::SCHEDULE ])) {
			AdvancedLogger::warning('Custom schedule not found', array(
				'schedule'  => self::SCHEDULE,
				'available' => array_keys($schedules),
			));
			return;
		}

		// If already scheduled, check if it's using the correct schedule
		$next_scheduled = wp_next_scheduled(self::EVENT);
		if ($next_scheduled) {
			// FIX: Check for timezone issue (UTC vs Local)
			// If the event is scheduled far in the future (> 10 mins), it's likely using Local Time (UTC+3)
			// WP-Cron runs on UTC, so this would delay execution by 3 hours.
			if ($next_scheduled > ( time() + 600 )) {
				AdvancedLogger::warning('Timezone mismatch detected (Event > 10min in future). Unscheduling to fix.');
				wp_unschedule_event($next_scheduled, self::EVENT);
				$next_scheduled = false;
			} else {
				$current_schedule = wp_get_schedule(self::EVENT);
				// If schedule is wrong, unschedule and reschedule
				if ($current_schedule !== self::SCHEDULE) {
					wp_unschedule_event($next_scheduled, self::EVENT);
					$next_scheduled = false; // Force reschedule
				} else {
					// Verify the schedule is still valid
					$verify_schedule = wp_get_schedule(self::EVENT);
					if ($verify_schedule === self::SCHEDULE) {
						return; // Already scheduled correctly
					}
					// Schedule is invalid, unschedule it
					wp_unschedule_event($next_scheduled, self::EVENT);
					$next_scheduled = false;
				}
			}
		}

		// Double-check schedule exists before scheduling
		// Force filter application by calling wp_get_schedules() multiple times
		$schedules = wp_get_schedules();
		if (! isset($schedules[ self::SCHEDULE ])) {
			AdvancedLogger::error('Schedule not available when attempting to schedule event', array(
				'schedule'  => self::SCHEDULE,
				'available' => array_keys($schedules),
			));
			return;
		}

		// Verify schedule details
		$schedule_info = $schedules[ self::SCHEDULE ];
		if (! isset($schedule_info['interval']) || $schedule_info['interval'] !== 300) {
			AdvancedLogger::error('Schedule has incorrect interval', array(
				'schedule' => self::SCHEDULE,
				'interval' => $schedule_info['interval'] ?? 'missing',
			));
			return;
		}

		// Use direct cron array manipulation to avoid WordPress's schedule validation
		// This bypasses the invalid_schedule error that occurs when wp_schedule_event()
		// checks the schedule before the filter is applied
		self::direct_schedule_event();
	}

	public static function run(): void
	{
		// Read from unified settings array
		$enabled = (string) \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_booking_auto_cancel_enabled', '0') === '1';

		if (! $enabled) {
			return;
		}

		// Use Booking Management setting: payment deadline minutes
		$minutes = (int) \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_booking_payment_deadline_minutes', 30); // Default 30 min
		if ($minutes < 5) {
			$minutes = 5; // Minimum 5 minutes
		}

		// Reasonable batch limit
		$limit = 50;

		// 1. Get current UTC timestamp
		$current_utc_ts = time();

		// 2. Subtract deadline minutes (Minutes -> Seconds)
		$deadline_ts = $current_utc_ts - ( $minutes * 60 );

		// 3. Convert to MySQL format (Local time)
		// wp_date will automatically apply the site's timezone setting to the standard UTC timestamp
		$deadline_str = wp_date('Y-m-d H:i:s', $deadline_ts);

		// Find unpaid bookings created before the time limit
		$q = new WP_Query(
			array(
				'post_type'      => 'vehicle_booking',
				'post_status'    => 'any', // Check all statuses to be safe, filter later
				'fields'         => 'ids',
				'posts_per_page' => $limit,
				'no_found_rows'  => true,
				'date_query'     => array(
					array(
						'column'    => 'post_date',
						'before'    => $deadline_str, // "Older than this local time"
						'inclusive' => true,
					),
				),
				'meta_query'     => array(
					'relation' => 'AND',
					array(
						'key'     => '_mhm_payment_status',
						'value'   => array( 'pending', 'pending_payment' ),
						'compare' => 'IN',
					),
					array(
						'key'     => '_mhm_status',
						'value'   => array( 'pending', 'pending_payment' ), // Also check booking status
						'compare' => 'IN',
					),
				),
			)
		);

		if (! $q->have_posts()) {
			return;
		}

		foreach ($q->posts as $bid) {
			$bid = (int) $bid;

			// Perform cancellation
			try {
				$newStatus = 'cancelled';
				update_post_meta($bid, '_mhm_status', $newStatus);
				update_post_meta($bid, '_mhm_payment_status', 'cancelled');
				update_post_meta($bid, '_mhm_auto_cancelled', current_time('timestamp'));
				update_post_meta($bid, '_mhm_auto_cancelled_reason', 'Payment deadline expired (' . $minutes . ' minutes)');

				// Send Auto Cancellation Email
				if (class_exists('\MHMRentiva\Helpers\NotificationHelper')) {
					\MHMRentiva\Helpers\NotificationHelper::send_auto_cancel_email($bid);
				}

				// 1. Cancel WooCommerce Order if exists
				$order_id = get_post_meta($bid, '_mhm_wc_order_id', true);
				if ($order_id) {
					$order = function_exists('wc_get_order') ? call_user_func('\wc_get_order', $order_id) : null;
					// Also cancel 'processing' orders — payment may have been captured but booking cancelled
					if ($order && $order->has_status(array( 'pending', 'on-hold', 'failed', 'processing' ))) {
						$order->update_status('cancelled', __('Reservation time expired.', 'mhm-rentiva'));
					}
				}

				// Clear availability cache
				$vehicle_id = (int) get_post_meta($bid, '_mhm_vehicle_id', true);
				if ($vehicle_id && class_exists('MHMRentiva\Admin\Booking\Helpers\Cache')) {
					\MHMRentiva\Admin\Booking\Helpers\Cache::invalidateVehicle($vehicle_id);
				}

				// Log action
				if (class_exists(AdvancedLogger::class)) {
					AdvancedLogger::info(
						"Booking #$bid auto-cancelled due to payment deadline expiration.",
						array(
							'booking_id'       => $bid,
							'deadline_minutes' => $minutes,
						),
						'system'
					);
				}

				do_action('mhm_rentiva_booking_auto_cancelled', $bid, $newStatus);
			} catch (\Throwable $e) {
				// Per-booking failure must not abort the cron sweep; log and continue.
				if (function_exists('error_log')) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log('[mhm-rentiva] auto-cancel skipped booking ' . $bid . ': ' . $e->getMessage());
				}
			}
		}
		wp_reset_postdata();
	}

	/**
	 * Direct schedule event - bypasses wp_schedule_event's schedule validation
	 * This method directly manipulates the cron array to avoid the invalid_schedule error
	 */
	private static function direct_schedule_event(): void
	{
		// Ensure schedule filter is applied
		add_filter('cron_schedules', array( self::class, 'schedules' ), 1);
		$schedules = wp_get_schedules();

		if (! isset($schedules[ self::SCHEDULE ])) {
			AdvancedLogger::error('Cannot schedule - schedule not available', array( 'schedule' => self::SCHEDULE ));
			return;
		}

		// Get cron array
		$cron = _get_cron_array();
		if ($cron === false) {
			$cron = array();
		}

		// Remove any existing events for this hook
		foreach ($cron as $timestamp => $cronhooks) {
			if (isset($cronhooks[ self::EVENT ])) {
				unset($cron[ $timestamp ][ self::EVENT ]);
				// Clean up empty timestamps
				if (empty($cron[ $timestamp ])) {
					unset($cron[ $timestamp ]);
				}
			}
		}

		// Calculate next run time (5 minutes from now)
		$next_run = time() + 300;

		// Add to cron array with proper structure
		$cron[ $next_run ][ self::EVENT ][ md5(serialize(array())) ] = array(
			'schedule' => self::SCHEDULE,
			'args'     => array(),
		);

		// Sort by timestamp
		ksort($cron);

		// Save cron array
		_set_cron_array($cron);

		// Verify it was scheduled
		$verify_next     = wp_next_scheduled(self::EVENT);
		$verify_schedule = wp_get_schedule(self::EVENT);

		if ($verify_next && $verify_schedule === self::SCHEDULE) {
			AdvancedLogger::info('Successfully scheduled recurring event', array(
				'schedule' => self::SCHEDULE,
				'next_run' => wp_date('Y-m-d H:i:s', $verify_next),
			));
		} else {
			AdvancedLogger::error('Direct schedule failed', array(
				'next'     => $verify_next ? wp_date('Y-m-d H:i:s', $verify_next) : 'none',
				'schedule' => $verify_schedule ?: 'none',
			));
		}
	}
}
