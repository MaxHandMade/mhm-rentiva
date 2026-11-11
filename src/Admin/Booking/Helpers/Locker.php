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
	 * Executes callback with database lock for vehicle.
	 *
	 * A transaction that could not be opened, or a lock read that failed, is not
	 * a lock: the callback does not run, the transaction is rolled back, null is
	 * returned and $locked says false (PR K, Codex delta H2). Every caller reads
	 * null as "could not decide", never as "free".
	 *
	 * @param bool $locked Set to whether the lock was held for the callback.
	 * @return mixed The callback's result; null when the lock failed.
	 */
	public static function withLock( int $vehicle_id, callable $callback, bool &$locked = false ) {
		global $wpdb;

		$locked = false;

		if ( ! self::may_open() ) {
			return null;
		}

		// Start transaction
		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			return null;
		}
		self::$open = true;

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
			if ( self::last_query_failed() ) {
				$wpdb->query( 'ROLLBACK' );
				return null;
			}

			// The postmeta lock above is not sufficient on its own: if the vehicle
			// happens to have no '_mhm%' row, that statement matches nothing and
			// leaves only a gap lock -- and gap locks are compatible with each
			// other, so two transactions would both sail through the guard. Lock
			// the post row as well, which is guaranteed to exist for a real
			// vehicle, so exclusion never depends on the meta set being non-empty.
			$row = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts} WHERE ID = %d FOR UPDATE",
					$vehicle_id
				)
			);
			if ( self::last_query_failed() || ! is_array( $row ) || array() === $row ) {
				$wpdb->query( 'ROLLBACK' );
				return null;
			}

			// Execute callback
			$locked = true;
			$result = $callback();

			// Commit transaction. A commit that fails kept nothing the callback wrote.
			if ( false === $wpdb->query( 'COMMIT' ) ) {
				$locked = false;
				$wpdb->query( 'ROLLBACK' );
				return null;
			}

			return $result;
		} catch ( \Throwable $e ) {
			// Rollback on ANY failure. Catching \Exception alone let a \TypeError
			// (or any other \Error) escape with the transaction still open: the
			// row lock stayed held and every later write on this connection joined
			// a transaction that would never be committed.
			$wpdb->query( 'ROLLBACK' );
			throw $e;
		} finally {
			self::$open = false;
		}
	}

	/**
	 * Is a transaction this class opened still open on this request?
	 *
	 * MySQL has no nested transactions: a START TRANSACTION while one is open
	 * commits the open one implicitly and drops its row locks, with no error
	 * (WooCommerceBridge.php:1822-1827 relies on knowing this). Both lock
	 * methods therefore refuse to open a second one, in either order (PR-2 A5).
	 * A transaction opened by other code is not seen here.
	 */
	private static bool $open = false;

	private static function may_open(): bool {
		if ( ! self::$open ) {
			return true;
		}

		_doing_it_wrong(
			__METHOD__,
			esc_html__( 'A booking or vehicle lock was requested while another one is held on this request. MySQL would commit the held lock implicitly, so the request is refused.', 'mhm-rentiva' ),
			'6.4.0'
		);

		return false;
	}

	/**
	 * Did the statement just run fail? wpdb clears last_error before each query.
	 *
	 * @phpstan-impure It reads the state the latest query left.
	 */
	private static function last_query_failed(): bool {
		global $wpdb;

		return '' !== (string) $wpdb->last_error;
	}

	/**
	 * Lock for a specific booking
	 */
	public static function withBookingLock( int $booking_id, callable $callback ) {
		global $wpdb;

		// Same contract as withLock() (PR-2 A5): a transaction that could not be
		// opened, a lock read that failed, or a booking row that is not there is
		// not a lock. Until PR-2 none of the three was checked and the callback
		// ran unlocked; its one caller already reads null as "could not create
		// the order" (RemainingPaymentHandler::get_or_create_remaining_order()).
		if ( ! self::may_open() ) {
			return null;
		}

		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			return null;
		}
		self::$open = true;

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
			if ( self::last_query_failed() ) {
				$wpdb->query( 'ROLLBACK' );
				return null;
			}

			// Same reason as withLock(): a booking with no matching meta rows would
			// otherwise be "locked" by a gap lock that excludes nobody.
			$row = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts} WHERE ID = %d FOR UPDATE",
					$booking_id
				)
			);
			if ( self::last_query_failed() || ! is_array( $row ) || array() === $row ) {
				$wpdb->query( 'ROLLBACK' );
				return null;
			}

			$result = $callback();

			// A commit that fails kept nothing the callback wrote.
			if ( false === $wpdb->query( 'COMMIT' ) ) {
				$wpdb->query( 'ROLLBACK' );
				return null;
			}

			return $result;
		} catch ( \Throwable $e ) {
			// See withLock(): \Exception alone let \Error escape without rollback.
			$wpdb->query( 'ROLLBACK' );
			throw $e;
		} finally {
			self::$open = false;
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
