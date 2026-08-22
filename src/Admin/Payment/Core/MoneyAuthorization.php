<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Payment\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * May this actor take money out of a gateway on this booking?
 *
 * Deliberately NOT current_user_can(): the actor is passed in explicitly. A
 * request-scoped question answers for the wrong subject on any path where the
 * caller is acting on someone else's behalf, and current_user_can() also
 * returns true for super admins by default, which blurs the answer further.
 *
 * There is no $system leg. Since K6 no unattended path moves money at all, a
 * bypass boolean would be a hole any future caller could open by passing true.
 * If system-initiated money movement is ever needed it earns its own spec
 * section and its own audit.
 *
 * This is the single home for the question (spec §5). Before this class, the
 * predicate lived at each call site -- CancellationHandler had its own private
 * may_move_money(), duplicated rather than shared -- and a new caller had to
 * remember to ask. Actions::refund_booking() never did (Fable, slice 5).
 * Service::process() and Service::processFullRefund() now ask this as their
 * first statement, so every caller inherits the gate whether it remembers to
 * ask or not.
 *
 * @since 6.1.0
 */
final class MoneyAuthorization {

	public static function mayMoveMoney( int $booking_id, int $actor_id, string $surface = '' ): bool {
		// Hard floor: runs BEFORE the filter. Nothing may authorise an
		// unattributed actor.
		if ( $actor_id <= 0 ) {
			return false;
		}

		$customer_id = (int) get_post_meta( $booking_id, '_mhmrentiva_customer_user_id', true );

		$allowed = ( $actor_id === $customer_id ) || user_can( $actor_id, 'manage_options' );

		/**
		 * Widen (or narrow) who may move money on a booking.
		 *
		 * Reachable only after the nonce and the entry gate. The floor above
		 * is not filterable.
		 *
		 * @param bool   $allowed    Whether $actor_id may move money on $booking_id.
		 * @param int    $booking_id The booking.
		 * @param int    $actor_id   The actor, always > 0 here.
		 * @param string $surface    Which caller is asking (e.g. 'service', 'cancel', 'refund').
		 */
		return (bool) apply_filters( 'mhmrentiva_may_move_money', $allowed, $booking_id, $actor_id, $surface );
	}
}
