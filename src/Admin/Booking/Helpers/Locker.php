<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Booking\Helpers;

if (!defined('ABSPATH')) {
    exit;
}

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Booking lock flow intentionally uses transaction/row locks on postmeta for consistency.
final class Locker {


	/**
	 * Executes callback with database lock for vehicle
	 */
	public static function withLock( int $vehicle_id, callable $callback ) {
		global $wpdb;

		// Start transaction
		$wpdb->query( 'START TRANSACTION' );

		try {
			// prefix-rename:ignore-start
			// Lock vehicle's postmeta records (FOR UPDATE)
			//
			// 🔴 The pattern must cover BOTH the pre- and post-6.0.0 spellings, and
			// this is the one place where getting it wrong is invisible. On a site
			// running the new code before the 6.0.0 migration, every row is still
			// '_mhm_*': a '_mhmrentiva_%' pattern selects ZERO rows, so FOR UPDATE
			// locks nothing, and this method proceeds and COMMITs believing it holds
			// the lock. That is the double-booking guard on the booking path, and it
			// fails silently -- no error, no failing test, just two bookings on one
			// vehicle. '_mhm' covers both families; esc_like() keeps the leading
			// underscore literal so the pattern cannot widen further.
			$wpdb->get_results(
				$wpdb->prepare(
					"SELECT meta_id FROM {$wpdb->postmeta}
                 WHERE post_id = %d AND meta_key LIKE %s
                 FOR UPDATE",
					$vehicle_id,
					$wpdb->esc_like( '_mhm' ) . '%'
				)
			);
			// prefix-rename:ignore-end

			// Execute callback
			$result = $callback();

			// Commit transaction
			$wpdb->query( 'COMMIT' );

			return $result;
		} catch ( \Exception $e ) {
			// Rollback on error
			$wpdb->query( 'ROLLBACK' );
			throw $e;
		}
	}

	/**
	 * Lock for a specific booking
	 */
	public static function withBookingLock( int $booking_id, callable $callback ) {
		global $wpdb;

		$wpdb->query( 'START TRANSACTION' );

		try {
			// prefix-rename:ignore-start
			// Lock booking postmeta records. Both spellings, for the same reason
			// spelled out in withLock() above: a new-prefix-only pattern locks
			// nothing on an un-migrated site and this method COMMITs believing it
			// held the lock.
			$wpdb->get_results(
				$wpdb->prepare(
					"SELECT meta_id FROM {$wpdb->postmeta}
                 WHERE post_id = %d AND meta_key LIKE %s
                 FOR UPDATE",
					$booking_id,
					$wpdb->esc_like( '_mhm' ) . '%'
				)
			);
			// prefix-rename:ignore-end

			$result = $callback();
			$wpdb->query( 'COMMIT' );

			return $result;
		} catch ( \Exception $e ) {
			$wpdb->query( 'ROLLBACK' );
			throw $e;
		}
	}

	/**
	 * Lock timeout control
	 */
	public static function setLockTimeout( int $seconds = 30 ): void {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'SET innodb_lock_wait_timeout = %d', $seconds ) );
	}

	/**
	 * Reset lock timeout
	 */
	public static function resetLockTimeout(): void {
		global $wpdb;
		$wpdb->query( 'SET innodb_lock_wait_timeout = 50' ); // MySQL default
	}
}
