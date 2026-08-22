<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Payment\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The single writer of a booking's refund status.
 *
 * Atomicity here does NOT come from update_post_meta()'s $prev_value argument.
 * Measured in WordPress core (wp-includes/meta.php:256, 279-281):
 * update_metadata() returns early when $prev_value is empty and only adds the
 * meta_value where-clause when it is non-empty -- so a transition out of the
 * empty string, which is where four of this machine's edges start, could never
 * be a compare-and-swap. (The same empty() also swallows a literal '0', which
 * is why no state in this machine is named '0'.)
 *
 * What makes a transition atomic is the booking lock. This class refuses to
 * write without it, and re-reads the current value after it -- serialisation
 * without freshness is not mutual exclusion.
 *
 * @since 6.1.0
 */
final class RefundStatus {

	public const META_KEY = '_mhmrentiva_refund_status';

	public const PENDING              = 'pending';
	public const NEEDS_REVIEW         = 'needs_review';
	public const MANUAL_PENDING       = 'manual_pending';
	public const PARTIAL_FAILURE      = 'partial_failure';
	public const FAILED               = 'failed';
	public const NOT_REQUIRED         = 'not_required';
	public const COMPLETED_EXTERNALLY = 'completed_externally';
	public const COMPLETED            = 'completed';
	public const COMPLETED_MANUALLY   = 'completed_manually';

	/**
	 * From => allowed destinations. Spec v3 §2.2 verbatim.
	 *
	 * The four terminal states (not_required, completed_externally, completed,
	 * completed_manually) are absent as keys, which is how they have no exit.
	 *
	 * @return array<string, array<int, string>>
	 */
	private static function matrix(): array {
		return array(
			''                    => array( self::PENDING, self::NEEDS_REVIEW ),
			self::PENDING         => array(
				self::COMPLETED,
				self::MANUAL_PENDING,
				self::PARTIAL_FAILURE,
				self::FAILED,
				self::NOT_REQUIRED,
				self::COMPLETED_EXTERNALLY,
			),
			self::NEEDS_REVIEW    => array( self::PENDING, self::NOT_REQUIRED ),
			self::MANUAL_PENDING  => array( self::COMPLETED_MANUALLY ),
			self::PARTIAL_FAILURE => array( self::PENDING ),
			self::FAILED          => array( self::PENDING ),
		);
	}

	public static function get( int $booking_id ): string {
		return (string) get_post_meta( $booking_id, self::META_KEY, true );
	}

	/**
	 * States with no outgoing edge in the transition matrix -- terminal.
	 *
	 * Derived from matrix() itself, not enumerated a second time: a status
	 * that appears somewhere as a destination but never as a key has nowhere
	 * left to go, which is the class docblock's own definition of terminal.
	 * Task 12 (slice 5) needs this so AutoCancel::not_parked_for_review() can
	 * follow the matrix instead of restating a list beside it -- the same
	 * shape selectable_status_values() already uses for Status's machine. A
	 * hand-written list here would drift the same way that one used to: an
	 * edge added to or removed from the matrix would silently stop matching
	 * whatever this method returned.
	 *
	 * @return array<int, string>
	 */
	public static function terminalStates(): array {
		$matrix       = self::matrix();
		$destinations = array_unique( array_merge( ...array_values( $matrix ) ) );

		return array_values( array_diff( $destinations, array_keys( $matrix ) ) );
	}

	/**
	 * @param array<string, mixed> $context
	 * @return bool True only when the status actually changed.
	 */
	public static function transition( int $booking_id, string $to, array $context = array() ): bool {
		if ( ! RefundLock::isHeld( $booking_id ) ) {
			return false;
		}

		// The caller waited for the lock; the request-local meta cache did not.
		wp_cache_delete( $booking_id, 'post_meta' );

		$from = self::get( $booking_id );

		if ( $from === $to ) {
			return false;
		}

		$allowed = self::matrix()[ $from ] ?? array();

		if ( ! in_array( $to, $allowed, true ) ) {
			return false;
		}

		// $prev_value is a second barrier where it can be expressed at all.
		if ( '' === $from ) {
			update_post_meta( $booking_id, self::META_KEY, $to );
		} else {
			update_post_meta( $booking_id, self::META_KEY, $to, $from );
		}

		do_action( 'mhmrentiva_refund_status_changed', $booking_id, $to, $from, $context );

		return true;
	}

	/**
	 * @return array<string, string>
	 */
	public static function labels(): array {
		return array(
			''                         => __( 'No refund flow has run for this booking.', 'mhm-rentiva' ),
			self::PENDING              => __( 'Refund in progress', 'mhm-rentiva' ),
			self::NEEDS_REVIEW         => __( 'Needs review — a paid order was found, no money moved', 'mhm-rentiva' ),
			self::MANUAL_PENDING       => __( 'Awaiting hand transfer', 'mhm-rentiva' ),
			self::PARTIAL_FAILURE      => __( 'Partly refunded — the rest did not go back', 'mhm-rentiva' ),
			self::FAILED               => __( 'Refund failed — nothing went back', 'mhm-rentiva' ),
			self::NOT_REQUIRED         => __( 'No refund was due', 'mhm-rentiva' ),
			self::COMPLETED_EXTERNALLY => __( 'Refunded outside this plugin', 'mhm-rentiva' ),
			self::COMPLETED            => __( 'Refunded through the payment gateway', 'mhm-rentiva' ),
			self::COMPLETED_MANUALLY   => __( 'Hand transfer confirmed', 'mhm-rentiva' ),
		);
	}
}
