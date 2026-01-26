<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Booking\Helpers;

if (! defined('ABSPATH')) {
	exit;
}

final class Locker
{

	/**
	 * Executes callback with database lock for vehicle
	 */
	public static function withLock(int $vehicle_id, callable $callback)
	{
		global $wpdb;

		// Start transaction
		$wpdb->query('START TRANSACTION');

		try {
			// Lock vehicle's postmeta records (FOR UPDATE)
			$wpdb->get_results(
				$wpdb->prepare(
					"SELECT meta_id FROM {$wpdb->postmeta}
                 WHERE post_id = %d AND meta_key LIKE %s
                 FOR UPDATE",
					$vehicle_id,
					$wpdb->esc_like('_mhm_') . '%'
				)
			);

			// Execute callback
			$result = $callback();

			// Commit transaction
			$wpdb->query('COMMIT');

			return $result;
		} catch (\Exception $e) {
			// Rollback on error
			$wpdb->query('ROLLBACK');
			throw $e;
		}
	}

	/**
	 * Lock for a specific booking
	 */
	public static function withBookingLock(int $booking_id, callable $callback)
	{
		global $wpdb;

		$wpdb->query('START TRANSACTION');

		try {
			// Lock booking postmeta records
			$wpdb->get_results(
				$wpdb->prepare(
					"SELECT meta_id FROM {$wpdb->postmeta}
                 WHERE post_id = %d AND meta_key LIKE %s
                 FOR UPDATE",
					$booking_id,
					$wpdb->esc_like('_mhm_') . '%'
				)
			);

			$result = $callback();
			$wpdb->query('COMMIT');

			return $result;
		} catch (\Exception $e) {
			$wpdb->query('ROLLBACK');
			throw $e;
		}
	}

	/**
	 * Lock timeout control
	 */
	public static function setLockTimeout(int $seconds = 30): void
	{
		global $wpdb;
		$wpdb->query($wpdb->prepare('SET innodb_lock_wait_timeout = %d', $seconds));
	}

	/**
	 * Reset lock timeout
	 */
	public static function resetLockTimeout(): void
	{
		global $wpdb;
		$wpdb->query('SET innodb_lock_wait_timeout = 50'); // MySQL default
	}
}
