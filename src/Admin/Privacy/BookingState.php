<?php
/**
 * Whether a booking may be anonymised on an erasure request.
 *
 * @package MHMRentiva
 */

declare(strict_types=1);

namespace MHMRentiva\Admin\Privacy;

use MHMRentiva\Admin\Booking\Core\Status;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Closed bookings are anonymised; everything else is kept (user decision U1,
 * 2026-11-09): a pending, confirmed or running rental still needs a person to
 * reach.
 *
 * The status is resolved for privacy on purpose, not copied from the list
 * table: the current key when it holds a known status, otherwise the legacy
 * `_mhmrentiva_booking_status` when that does, otherwise unknown. Unknown is
 * kept -- a booking whose state cannot be read is never erased by guess.
 * (The list and occupancy views fall back only when the current key is EMPTY;
 * the privacy rule also falls back past an invalid value.)
 *
 * ONE definition: of() runs the same SQL predicate the erasure queue uses, so
 * the queue and a single-booking check cannot disagree -- not on a second
 * meta row for the same key, and not on letter case (comparisons are BINARY;
 * the postmeta collation is case-insensitive, Status values are lower-case).
 */
final class BookingState {

	public const CLOSED = array(
		Status::COMPLETED,
		Status::CANCELLED,
		Status::REFUNDED,
		Status::NO_SHOW,
	);

	/**
	 * 'closed' or 'active'.
	 *
	 * @param int $booking_id Booking post ID.
	 * @return string
	 */
	public static function of( int $booking_id ): string {
		global $wpdb;

		$closed = self::sql_closed( 'p' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $closed is built from Status constants and a sanitised alias; no input.
		$sql = $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} p WHERE p.ID = %d AND {$closed}", $booking_id );

		return '1' === (string) $wpdb->get_var( $sql ) ? 'closed' : 'active'; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared above.
	}

	/**
	 * SQL predicate: the booking row aliased $alias is closed.
	 *
	 * Built only from Status constants and the alias, so it carries no
	 * placeholders and can be concatenated into a prepared statement.
	 *
	 * @param string $alias Alias of the posts table in the outer query.
	 * @return string
	 */
	public static function sql_closed( string $alias ): string {
		global $wpdb;

		$a      = (string) preg_replace( '/[^A-Za-z0-9_]/', '', $alias );
		$closed = self::in_list( self::CLOSED );
		$open   = self::in_list( array_values( array_diff( Status::allowed(), self::CLOSED ) ) );
		$any    = self::in_list( Status::allowed() );
		$row    = static fn( string $tag, string $key, string $list ): string => "EXISTS ( SELECT 1 FROM {$wpdb->postmeta} {$a}_{$tag}
				WHERE {$a}_{$tag}.post_id = {$a}.ID AND {$a}_{$tag}.meta_key = '{$key}'
				AND BINARY {$a}_{$tag}.meta_value IN ( {$list} ) )";

		// Closed only when nothing says otherwise: a key with a closed row AND
		// an open row (two rows for one key) is a conflict, and a conflict is
		// kept, never erased.
		return '(
			( ' . $row( 'nc', '_mhmrentiva_status', $closed ) . ' AND NOT ' . $row( 'no', '_mhmrentiva_status', $open ) . ' )
			OR (
				NOT ' . $row( 'na', '_mhmrentiva_status', $any ) . '
				AND ' . $row( 'oc', '_mhmrentiva_booking_status', $closed ) . '
				AND NOT ' . $row( 'oo', '_mhmrentiva_booking_status', $open ) . '
			)
		)';
	}

	/**
	 * Quoted IN-list of status constants (lower-case ASCII slugs).
	 *
	 * @param string[] $statuses Status slugs.
	 * @return string
	 */
	private static function in_list( array $statuses ): string {
		$safe = array_map(
			static fn( string $s ): string => "'" . preg_replace( '/[^a-z_]/', '', $s ) . "'",
			$statuses
		);

		return implode( ', ', $safe );
	}
}
