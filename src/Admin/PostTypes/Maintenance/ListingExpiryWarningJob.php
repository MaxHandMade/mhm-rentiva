<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\PostTypes\Maintenance;

if (! defined('ABSPATH')) {
	exit;
}

// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query,WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Bounded cron query is intentional.

use MHMRentiva\Admin\Core\MetaKeys;
use MHMRentiva\Admin\Vehicle\VehicleLifecycleStatus;
use MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger;
use WP_Query;

/**
 * Daily cron job that sends expiry warning notifications to vendors.
 *
 * Fires hooks at two thresholds:
 * - EXPIRY_WARNING_DAYS_FIRST  (10 days before expiry)
 * - EXPIRY_WARNING_DAYS_SECOND (3 days before expiry)
 *
 * Notification delivery is handled by listeners on the fired hooks (Phase 6).
 *
 * @since 4.24.0
 */
final class ListingExpiryWarningJob {

	public const EVENT = 'mhm_rentiva_listing_expiry_warning_event';

	/**
	 * Register the cron event and runner.
	 */
	public static function register(): void
	{
		add_action('init', array( self::class, 'maybe_schedule' ), 100);
		add_action(self::EVENT, array( self::class, 'run' ));
	}

	/**
	 * Schedule the event if not already scheduled.
	 */
	public static function maybe_schedule(): void
	{
		if (! wp_next_scheduled(self::EVENT)) {
			wp_schedule_event(time(), 'daily', self::EVENT);
		}
	}

	/**
	 * Run the warning check at both thresholds.
	 */
	public static function run(): void
	{
		self::send_warnings(
			VehicleLifecycleStatus::expiry_warning_first(),
			'_mhm_vehicle_expiry_warning_first_sent',
			'mhm_rentiva_vehicle_expiry_warning_first'
		);

		self::send_warnings(
			VehicleLifecycleStatus::expiry_warning_second(),
			'_mhm_vehicle_expiry_warning_second_sent',
			'mhm_rentiva_vehicle_expiry_warning_second'
		);
	}

	/**
	 * Find active vehicles expiring within the given number of days and fire warning hook.
	 *
	 * Uses a "sent" meta flag to avoid duplicate notifications on the same threshold.
	 *
	 * @param int    $days_before  Days before expiry to trigger warning.
	 * @param string $sent_meta    Meta key used to track that this warning was already sent.
	 * @param string $action_hook  Action hook to fire per vehicle.
	 */
	private static function send_warnings(int $days_before, string $sent_meta, string $action_hook): void
	{
		$now       = gmdate('Y-m-d H:i:s');
		$threshold = gmdate('Y-m-d H:i:s', strtotime('+' . $days_before . ' days'));

		$query = new WP_Query(array(
			'post_type'      => 'vehicle',
			'post_status'    => 'publish',
			'posts_per_page' => 100,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'meta_query'     => array(
				'relation' => 'AND',
				array(
					'key'   => MetaKeys::VEHICLE_LIFECYCLE_STATUS,
					'value' => VehicleLifecycleStatus::ACTIVE,
				),
				array(
					'key'     => MetaKeys::VEHICLE_LISTING_EXPIRES_AT,
					'value'   => $threshold,
					'compare' => '<=',
					'type'    => 'DATETIME',
				),
				array(
					'key'     => MetaKeys::VEHICLE_LISTING_EXPIRES_AT,
					'value'   => $now,
					'compare' => '>',
					'type'    => 'DATETIME',
				),
				// Not yet notified at this threshold.
				array(
					'key'     => $sent_meta,
					'compare' => 'NOT EXISTS',
				),
			),
		));

		if (! $query->have_posts()) {
			return;
		}

		$warned_count = 0;

		foreach ($query->posts as $vehicle_id) {
			$vehicle_id = (int) $vehicle_id;
			$vendor_id  = (int) get_post_field('post_author', $vehicle_id);
			$expires_at = get_post_meta($vehicle_id, MetaKeys::VEHICLE_LISTING_EXPIRES_AT, true);

			// Mark as sent first to prevent re-sending if hook processing fails downstream.
			update_post_meta($vehicle_id, $sent_meta, gmdate('Y-m-d H:i:s'));

			/**
			 * Fires when a vehicle listing is approaching expiry.
			 *
			 * @param int    $vehicle_id  Vehicle post ID.
			 * @param int    $vendor_id   Vendor user ID.
			 * @param string $expires_at  Expiry datetime (UTC).
			 * @param int    $days_before Warning threshold in days.
			 */
			do_action($action_hook, $vehicle_id, $vendor_id, $expires_at, $days_before);

			++$warned_count;
		}

		wp_reset_postdata();

		if ($warned_count > 0 && class_exists(AdvancedLogger::class)) {
			AdvancedLogger::info(
				"Expiry warning cron ({$days_before}-day): notified {$warned_count} vendor(s).",
				array(
					'threshold' => $days_before,
					'count'     => $warned_count,
				),
				'system'
			);
		}
	}
}
