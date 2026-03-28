<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\PostTypes\Maintenance;

if (! defined('ABSPATH')) {
	exit;
}

// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query,WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Bounded cron query is intentional.

use MHMRentiva\Admin\Core\MetaKeys;
use MHMRentiva\Admin\Vehicle\VehicleLifecycleManager;
use MHMRentiva\Admin\Vehicle\VehicleLifecycleStatus;
use MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger;
use WP_Query;

/**
 * Twice-daily cron job that expires active vehicle listings past their 90-day duration.
 *
 * Also handles grace period → auto-withdrawal for listings that have been expired
 * longer than EXPIRY_GRACE_DAYS without renewal.
 *
 * @since 4.24.0
 */
final class ListingExpiryJob
{
	public const EVENT = 'mhm_rentiva_listing_expiry_event';

	/**
	 * Register the cron event and runner.
	 */
	public static function register(): void
	{
		add_action('init', array(self::class, 'maybe_schedule'), 100);
		add_action(self::EVENT, array(self::class, 'run'));
	}

	/**
	 * Schedule the event if not already scheduled.
	 */
	public static function maybe_schedule(): void
	{
		if (! wp_next_scheduled(self::EVENT)) {
			wp_schedule_event(time(), 'twicedaily', self::EVENT);
		}
	}

	/**
	 * Run the expiry check.
	 *
	 * 1. Find active vehicles whose listing has expired → expire them.
	 * 2. Find expired vehicles past grace period → auto-withdraw.
	 */
	public static function run(): void
	{
		self::expire_overdue_listings();
		self::auto_withdraw_past_grace();
	}

	/**
	 * Expire active listings whose expires_at has passed.
	 */
	private static function expire_overdue_listings(): void
	{
		$now = gmdate('Y-m-d H:i:s');

		$query = new WP_Query(array(
			'post_type'      => 'vehicle',
			'post_status'    => 'publish',
			'posts_per_page' => 50,
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
					'value'   => $now,
					'compare' => '<',
					'type'    => 'DATETIME',
				),
			),
		));

		if (! $query->have_posts()) {
			return;
		}

		$expired_count = 0;

		foreach ($query->posts as $vehicle_id) {
			$vehicle_id = (int) $vehicle_id;
			$result     = VehicleLifecycleManager::expire($vehicle_id);

			if (is_wp_error($result)) {
				if (class_exists(AdvancedLogger::class)) {
					AdvancedLogger::warning(
						"Failed to expire vehicle #{$vehicle_id}: " . $result->get_error_message(),
						array('vehicle_id' => $vehicle_id),
						'system'
					);
				}
				continue;
			}

			++$expired_count;
		}

		wp_reset_postdata();

		if ($expired_count > 0 && class_exists(AdvancedLogger::class)) {
			AdvancedLogger::info(
				"Listing expiry cron: expired {$expired_count} vehicle(s).",
				array('count' => $expired_count),
				'system'
			);
		}
	}

	/**
	 * Auto-withdraw expired vehicles that have passed the grace period without renewal.
	 */
	private static function auto_withdraw_past_grace(): void
	{
		$grace_cutoff = gmdate('Y-m-d H:i:s', strtotime('-' . VehicleLifecycleStatus::EXPIRY_GRACE_DAYS . ' days'));

		$query = new WP_Query(array(
			'post_type'      => 'vehicle',
			'post_status'    => 'any',
			'posts_per_page' => 50,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'meta_query'     => array(
				'relation' => 'AND',
				array(
					'key'   => MetaKeys::VEHICLE_LIFECYCLE_STATUS,
					'value' => VehicleLifecycleStatus::EXPIRED,
				),
				array(
					'key'     => MetaKeys::VEHICLE_LISTING_EXPIRES_AT,
					'value'   => $grace_cutoff,
					'compare' => '<',
					'type'    => 'DATETIME',
				),
			),
		));

		if (! $query->have_posts()) {
			return;
		}

		$withdrawn_count = 0;

		foreach ($query->posts as $vehicle_id) {
			$vehicle_id = (int) $vehicle_id;
			$vendor_id  = (int) get_post_field('post_author', $vehicle_id);

			// Direct state change (bypass ownership check — system action).
			update_post_meta($vehicle_id, MetaKeys::VEHICLE_LIFECYCLE_STATUS, VehicleLifecycleStatus::WITHDRAWN);
			update_post_meta($vehicle_id, MetaKeys::VEHICLE_STATUS, 'inactive');
			update_post_meta($vehicle_id, MetaKeys::VEHICLE_WITHDRAWN_AT, gmdate('Y-m-d H:i:s'));
			update_post_meta(
				$vehicle_id,
				MetaKeys::VEHICLE_COOLDOWN_ENDS_AT,
				gmdate('Y-m-d H:i:s', strtotime('+' . VehicleLifecycleStatus::WITHDRAWAL_COOLDOWN_DAYS . ' days'))
			);

			wp_update_post(array(
				'ID'          => $vehicle_id,
				'post_status' => 'draft',
			));

			do_action('mhm_rentiva_vehicle_auto_withdrawn', $vehicle_id, $vendor_id);
			do_action('mhm_rentiva_vehicle_lifecycle_changed', $vehicle_id, VehicleLifecycleStatus::EXPIRED, VehicleLifecycleStatus::WITHDRAWN);

			++$withdrawn_count;
		}

		wp_reset_postdata();

		if ($withdrawn_count > 0 && class_exists(AdvancedLogger::class)) {
			AdvancedLogger::info(
				"Listing expiry cron: auto-withdrew {$withdrawn_count} vehicle(s) past grace period.",
				array('count' => $withdrawn_count),
				'system'
			);
		}
	}
}
