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
	 * Spec v3 section 3: the value sets the refund_status_changed context is
	 * allowed to announce. They are listed here rather than in each caller so
	 * that "which surfaces exist" has one answer, and so a caller inventing a
	 * sixth one cannot quietly teach integrators a value the spec never named.
	 */
	public const SURFACES = array( 'admin_deposit', 'customer_account', 'auto_cancel', 'manual_close', 'review_action' );
	public const CHANNELS = array( 'auto', 'manual', 'mixed', 'external', 'none' );

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

		// $prev_value is a second barrier where it can be expressed at all --
		// and a barrier whose verdict is thrown away is not a barrier. The
		// write can still be refused after every guard above passed: a plugin
		// short-circuiting update_post_metadata, a failed row write, or this
		// compare-and-swap rejecting the update because another request
		// changed the value inside the lock's 300s lease-stealing window.
		if ( '' === $from ) {
			$written = update_post_meta( $booking_id, self::META_KEY, $to );
		} else {
			$written = update_post_meta( $booking_id, self::META_KEY, $to, $from );
		}

		// Spec v3 section 2.3: the event and the status cannot diverge, and a
		// true return means the status really changed. Announcing a write that
		// never landed would have callers record audit trails, send operator
		// mail and write order notes for a transition the database refused.
		if ( ! $written ) {
			// Callers read one bit and narrate it. Without this line a
			// database refusal reaches the operator wearing the matrix's
			// clothes -- "that transition is not allowed" -- and the real
			// cause never surfaces anywhere.
			\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::error_linked(
				sprintf(
					/* translators: 1: the refund status the booking was moving from, 2: the refund status it was moving to. */
					__( 'The refund_status write was refused by the database: %1$s -> %2$s did not land.', 'mhm-rentiva' ),
					'' === $from ? '(empty)' : $from,
					$to
				),
				$booking_id,
				array(
					'from'    => $from,
					'to'      => $to,
					'context' => $context,
				)
			);

			return false;
		}

		do_action(
			'mhmrentiva_refund_status_changed',
			$booking_id,
			$to,
			$from,
			self::normaliseContext( $booking_id, $context )
		);

		return true;
	}

	/**
	 * Fill the spec's triple, and refuse to republish a value it does not name.
	 *
	 * Spec v3 section 3 says the context carries AT LEAST channel, surface and
	 * actor_id. Thirteen call sites passed one or two of them and none passed
	 * all three, so an integrator mirroring refund state had to guess -- and
	 * two sites announced surface "refunds_service", which is not one of the
	 * five the spec names and which no listener could switch on.
	 *
	 * Gaps are filled rather than made fatal: refund_status is money state and
	 * the context is telemetry, so a caller that forgot a key must not cost the
	 * booking its recorded state. An out-of-contract value is a different case
	 * -- passing it through would publish a vocabulary the spec does not have --
	 * so it is dropped to "unstated" and logged where someone will read it. Our
	 * own call sites are held to the real values by
	 * RefundStatusContextInventoryTest; an empty surface in the wild therefore
	 * means a third-party caller, not one of ours.
	 *
	 * @param array<string, mixed> $context
	 * @return array<string, mixed>
	 */
	private static function normaliseContext( int $booking_id, array $context ): array {
		$surface = isset( $context['surface'] ) ? (string) $context['surface'] : '';
		if ( '' !== $surface && ! in_array( $surface, self::SURFACES, true ) ) {
			self::reportOutOfContract( $booking_id, 'surface', $surface, self::SURFACES );
			$surface = '';
		}

		$channel = isset( $context['channel'] ) ? (string) $context['channel'] : 'none';
		if ( ! in_array( $channel, self::CHANNELS, true ) ) {
			self::reportOutOfContract( $booking_id, 'channel', $channel, self::CHANNELS );
			$channel = 'none';
		}

		$context['surface']  = $surface;
		$context['channel']  = $channel;
		$context['actor_id'] = isset( $context['actor_id'] ) ? (int) $context['actor_id'] : get_current_user_id();

		return $context;
	}

	/**
	 * @param array<int, string> $allowed
	 */
	private static function reportOutOfContract( int $booking_id, string $key, string $value, array $allowed ): void {
		\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::error_linked(
			sprintf(
				/* translators: 1: the context key, 2: the value the caller passed, 3: the comma-separated list of values the spec allows. */
				__( 'A refund_status_changed context announced %1$s "%2$s", which is not one of: %3$s. It was dropped.', 'mhm-rentiva' ),
				$key,
				$value,
				implode( ', ', $allowed )
			),
			$booking_id,
			array(
				'key'   => $key,
				'value' => $value,
			)
		);
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
