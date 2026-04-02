<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\PostTypes\Maintenance;

if (! defined('ABSPATH')) {
	exit;
}

use MHMRentiva\Admin\Vehicle\ReliabilityScoreCalculator;
use MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger;

/**
 * Daily cron job that recalculates vendor reliability scores.
 *
 * Iterates over all users with the `rentiva_vendor` role and
 * persists the updated score to user meta.
 *
 * @since 4.24.0
 */
final class ReliabilityScoreJob
{
	public const EVENT = 'mhm_rentiva_reliability_score_event';

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
			wp_schedule_event(time(), 'daily', self::EVENT);
		}
	}

	/**
	 * Run the recalculation for all vendors.
	 */
	public static function run(): void
	{
		$vendors = get_users(array(
			'role'   => 'rentiva_vendor',
			'fields' => 'ID',
		));

		if (empty($vendors)) {
			return;
		}

		$updated = 0;

		foreach ($vendors as $vendor_id) {
			ReliabilityScoreCalculator::update((int) $vendor_id, 'cron');
			++$updated;
		}

		if ($updated > 0 && class_exists(AdvancedLogger::class)) {
			AdvancedLogger::info(
				"Reliability score cron: recalculated {$updated} vendor score(s).",
				array('count' => $updated),
				'system'
			);
		}
	}
}
