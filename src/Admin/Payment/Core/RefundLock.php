<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Payment\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A per-booking mutex for the money step.
 *
 * One INSERT IGNORE against wp_options' UNIQUE KEY option_name: atomic in a
 * single statement, no transaction opened, no schema change. The three
 * alternatives were measured and rejected (spec §5.4 and the plan's evidence
 * section): Locker::withLock() commits PHPUnit's transaction; add_option() is
 * INSERT ... ON DUPLICATE KEY UPDATE, so the second racer overwrites the first
 * lock; wp_cache_add() is a request-local array without a persistent object
 * cache. GET_LOCK() is atomic too, but it is re-entrant per connection, which
 * makes the refusal untestable in single-process PHPUnit.
 *
 * Re-entrant within a request on purpose: the cancellation flow holds the lock
 * across its call into Refunds\Service, which takes the same lock. A
 * non-re-entrant lock would deadlock that path against itself.
 *
 * The row carries the owner's token and the moment it was taken, so a lock left
 * behind by a process that died can be stolen after TTL_SECONDS instead of
 * blocking the booking forever, and a release can only delete the row it
 * itself wrote.
 *
 * This is a lease, not a guarantee: TTL_SECONDS does not distinguish "the
 * holder died" from "the holder is still running, just slower than
 * expected." A holder that is merely slow can have its row stolen out from
 * under it; its later release() then matches a row it no longer owns, is a
 * silent no-op, and for a window both the original holder and the new
 * acquirer believe they hold the lock. That risk is accepted, not
 * overlooked, and it is bounded twice over: the critical section this lock
 * actually guards is Refunds\Service::withLock()'s whole closure -- the
 * wc_create_refund() calls, but also finish(), which runs inside the same
 * lock and sends two wp_mail() calls and fires the public
 * mhmrentiva_refund_completed action, i.e. arbitrary third-party code. A
 * slow mail transport or a slow listener therefore does extend how long the
 * lock is held, not just the gateway legs. What still bounds the risk is
 * the second leg: WooCommerce itself re-checks the booking's remaining
 * refundable amount at call time (WC 11.0.1 class-wc-order-refund.php or
 * class-wc-order.php's create_refund path, ~:584-586), so a duplicate
 * refund from a preempted holder is refused by WooCommerce, not by this
 * lock. This class is defence in depth over that check, not the only guard
 * -- which is why the lease design is kept as-is rather than growing a
 * renewal or heartbeat.
 *
 * Spec §5.3 puts mhmrentiva_refund_completed AFTER the lock is released;
 * Refunds\Service fires it from inside finish(), which runs INSIDE
 * withLock(). Known deviation, kept deliberately: both of finish()'s
 * callers (process() and processFullRefund()) return finish()'s array as
 * their own result, so moving the hook outside the lock would mean firing
 * it after the return value is already computed, from both entry points --
 * a larger change to their contract than this slice carries.
 *
 * A request that dies mid-refund (a fatal, a killed worker) leaves its
 * mhmrentiva_refund_lock_<id> row standing: nothing runs release() for it.
 * The row is not permanently orphaned -- it is picked up the same way any
 * stale row is, by a later acquire() on the same booking finding it past
 * TTL_SECONDS and stealing it, or, failing that, by Uninstaller's
 * `option_name LIKE 'mhmrentiva%'` sweep when the plugin is removed.
 *
 * ⚠️ Cross-process exclusion is not provable in this test suite (one process,
 * one connection). What the tests prove is the refusal, the re-entrancy, the
 * ownership of release, and the steal.
 *
 * @since 6.1.0
 */
final class RefundLock {

	/**
	 * How long a lock may sit before another request may take it over.
	 *
	 * Public since Task 14b fix round 1, F5: three operator/customer-facing
	 * messages (DepositManagementAjax.php x2, Refunds/Service.php) used to
	 * hardcode "within 5 minutes" -- exactly the kind of confident
	 * falsehood item 2 removed elsewhere in this task, the moment this
	 * constant ever changes. Widened to public so those messages can
	 * derive the number instead of restating it.
	 */
	public const TTL_SECONDS = 300;

	/**
	 * Booking id => how many times THIS request has acquired the lock.
	 *
	 * @var array<int, int>
	 */
	private static array $depth = array();

	/**
	 * Booking id => the token this request wrote into the row.
	 *
	 * @var array<int, string>
	 */
	private static array $tokens = array();

	/**
	 * Does THIS request hold the lock on this booking?
	 *
	 * Reads the same re-entrancy counter acquire()/release() maintain, so it
	 * answers for this process only -- which is exactly the question
	 * RefundStatus::transition() asks before writing.
	 */
	public static function isHeld( int $booking_id ): bool {
		return isset( self::$depth[ $booking_id ] );
	}

	public static function acquire( int $booking_id ): bool {
		if ( isset( self::$depth[ $booking_id ] ) ) {
			++self::$depth[ $booking_id ];

			return true;
		}

		$token = wp_generate_uuid4() . ':' . time();

		if ( ! self::insert( $booking_id, $token ) ) {
			if ( ! self::steal_if_stale( $booking_id ) ) {
				return false;
			}

			if ( ! self::insert( $booking_id, $token ) ) {
				return false;
			}
		}

		self::$depth[ $booking_id ]  = 1;
		self::$tokens[ $booking_id ] = $token;

		return true;
	}

	public static function release( int $booking_id ): void {
		if ( ! isset( self::$depth[ $booking_id ] ) ) {
			return;
		}

		--self::$depth[ $booking_id ];

		if ( self::$depth[ $booking_id ] > 0 ) {
			return;
		}

		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- A mutex is the one thing that must never be answered from a cache.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s",
				self::name( $booking_id ),
				self::$tokens[ $booking_id ]
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		unset( self::$depth[ $booking_id ], self::$tokens[ $booking_id ] );
	}

	/**
	 * @phpstan-impure Writes a row via $wpdb->query(); its result is not a
	 *                  function of its arguments alone, so a second call
	 *                  with the same $booking_id and $token (after
	 *                  steal_if_stale() has removed the blocking row) can
	 *                  legitimately return a different result than the first.
	 */
	private static function insert( int $booking_id, string $token ): bool {
		global $wpdb;

		// autoload 'no' keeps the row out of alloptions; nothing ever reads
		// this name through the options API, so no cache has to be primed.
		//
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The options API cannot express INSERT IGNORE.
		$written = $wpdb->query(
			$wpdb->prepare(
				"INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')",
				self::name( $booking_id ),
				$token
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return 1 === (int) $written;
	}

	/**
	 * Delete the row only if it is older than the TTL, and only the exact row
	 * that was read -- a lock refreshed between the read and the delete stays.
	 */
	private static function steal_if_stale( int $booking_id ): bool {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- A mutex is the one thing that must never be answered from a cache.
		$current = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
				self::name( $booking_id )
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( null === $current ) {
			// It disappeared between the failed insert and this read; the
			// caller retries the insert, which is the correct outcome.
			return true;
		}

		$colon_position = strrpos( (string) $current, ':' );

		if ( false === $colon_position ) {
			// Fail closed: only this class's own "<token>:<unix-time>" rows
			// are stealable. Without this guard, strrpos() returning false
			// on a colon-less value makes substr()'s offset false + 1 = 1,
			// which reads as a near-zero timestamp -- an unparseable row
			// would look maximally stale and be stolen instantly instead of
			// being refused. Nothing but this class writes these rows today,
			// so the branch is currently unreachable in production.
			return false;
		}

		$taken_at = (int) substr( (string) $current, $colon_position + 1 );

		if ( time() - $taken_at < self::TTL_SECONDS ) {
			return false;
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- A mutex is the one thing that must never be answered from a cache.
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s",
				self::name( $booking_id ),
				(string) $current
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return (int) $deleted > 0;
	}

	private static function name( int $booking_id ): string {
		return 'mhmrentiva_refund_lock_' . $booking_id;
	}
}
