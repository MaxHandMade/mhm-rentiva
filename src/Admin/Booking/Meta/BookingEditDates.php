<?php
/**
 * The date rule of the booking edit form.
 *
 * @package MHM_Rentiva
 */

declare(strict_types=1);

namespace MHMRentiva\Admin\Booking\Meta;

use MHMRentiva\Admin\Booking\Helpers\Util;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A range the rest of Rentiva cannot use is not saved (function scan H4).
 *
 * The rule is Util::parse_datetimes() itself -- the one availability, pricing
 * and the booking timestamps run on: the return must come after the pickup
 * (an empty time is the default 10:00). An own copy of the rule let an equal
 * date and time through while those three refused it, so the dates were saved
 * and the price, days and occupancy stayed on the old range (Codex code audit
 * 3b-2, r2).
 *
 * Only a CHANGED range is refused: a booking whose stored range is already
 * equal (a transfer may carry one) saves its other fields without a warning
 * (Fable code audit M-2). Both savers ask this one rule; a refused range keeps
 * the stored dates and the rest of the Update is saved. The browser check in
 * booking-edit-meta.js applies the same rule before the form is sent.
 */
final class BookingEditDates {

	public static function is_refused( int $booking_id, string $pickup_date, string $pickup_time, string $dropoff_date, string $dropoff_time ): bool {
		if ( '' === $pickup_date || '' === $dropoff_date ) {
			return false;
		}

		if ( ! is_wp_error( Util::parse_datetimes( $pickup_date, $pickup_time, $dropoff_date, $dropoff_time ) ) ) {
			return false;
		}

		return array( $pickup_date, $pickup_time, $dropoff_date, $dropoff_time ) !== self::stored( $booking_id );
	}

	/**
	 * What a saver writes for the posted range: the database values when the
	 * range is refused OR untouched (it equals what the form drew), the posted
	 * values otherwise. An untouched form must not rewrite a stored value the
	 * inputs cannot hold ('9:00' is drawn as '' but other readers still read
	 * it) -- Codex r7.
	 *
	 * @param array{0: string, 1: string, 2: string, 3: string} $posted pickup date, pickup time, return date, return time
	 * @return array{0: string, 1: string, 2: string, 3: string}
	 */
	public static function to_write( int $booking_id, array $posted ): array {
		$posted = array_values( array_map( 'strval', $posted ) );
		if ( self::is_refused( $booking_id, ...$posted ) || $posted === self::stored( $booking_id ) ) {
			return self::raw( $booking_id );
		}
		return array( $posted[0], $posted[1], $posted[2], $posted[3] );
	}

	/**
	 * What the availability check runs on for the posted range: the values
	 * to_write() keeps, unless those cannot be parsed (a stored '9:00') --
	 * then the range as the form shows it, which the check can read. Without
	 * this an untouched booking with a malformed stored time skipped the
	 * check on a vehicle change (Fable r8, M-1).
	 *
	 * @param array{0: string, 1: string, 2: string, 3: string} $posted
	 * @return array{0: string, 1: string, 2: string, 3: string}
	 */
	public static function to_check( int $booking_id, array $posted ): array {
		$written = self::to_write( $booking_id, $posted );
		if ( ! is_wp_error( Util::parse_datetimes( ...$written ) ) ) {
			return $written;
		}
		return self::stored( $booking_id );
	}

	/**
	 * The stored range exactly as the edit form shows it -- the form draws its
	 * fields from this, so an untouched form posts these very values back.
	 * Current keys first, then the older ones; an empty or '0' value is "not
	 * set" (a time input cannot hold '0', the browser posts '' for it). A
	 * saver that keeps the stored range writes these values (Codex r3 Minor 1,
	 * Fable M-D).
	 *
	 * @return array{0: string, 1: string, 2: string, 3: string} pickup date, pickup time, return date, return time
	 */
	public static function stored( int $booking_id ): array {
		list( $pickup_date, $pickup_time, $dropoff_date, $dropoff_time ) = self::raw( $booking_id );

		// As the date and time inputs hold them: a value those inputs cannot
		// hold ('9:00', '24:00', '2099-13-01') is shown and posted as '', so it
		// is '' here too -- otherwise an untouched form would look changed
		// (Codex r5). For comparing and drawing only: a saver keeping the
		// stored range writes raw(), never this (Codex r6).
		return array(
			self::as_date_input( $pickup_date ),
			self::as_time_input( $pickup_time ),
			self::as_date_input( $dropoff_date ),
			self::as_time_input( $dropoff_time ),
		);
	}

	/**
	 * The stored range as it is in the database (current keys, then the older
	 * ones; '0' as not set) -- what a saver writes back when it keeps the
	 * stored range, so a refused change leaves the meta and the timestamps
	 * exactly as they were (Codex r6).
	 *
	 * @return array{0: string, 1: string, 2: string, 3: string}
	 */
	public static function raw( int $booking_id ): array {
		$first = static function ( string ...$keys ) use ( $booking_id ): string {
			foreach ( $keys as $key ) {
				$value = (string) get_post_meta( $booking_id, $key, true );
				if ( '' !== $value && '0' !== $value ) {
					return $value;
				}
			}
			return '';
		};

		return array(
			$first( '_mhmrentiva_pickup_date', '_mhmrentiva_booking_pickup_date' ),
			$first( '_mhmrentiva_start_time', '_mhmrentiva_pickup_time', '_mhmrentiva_booking_pickup_time' ),
			$first( '_mhmrentiva_dropoff_date', '_mhmrentiva_booking_dropoff_date' ),
			$first( '_mhmrentiva_end_time', '_mhmrentiva_dropoff_time', '_mhmrentiva_booking_dropoff_time' ),
		);
	}

	/**
	 * A value an <input type="date"> holds (a valid date, the year four or
	 * more digits), else ''. \z, not $: $ lets a trailing newline through.
	 */
	private static function as_date_input( string $value ): string {
		if ( ! preg_match( '/^(\d{4,})-(\d{2})-(\d{2})\z/', $value, $m ) ) {
			return '';
		}
		// The proleptic Gregorian calendar the date input uses, for any year
		// (checkdate() stops at 32767 -- Codex r7). The year stays a string:
		// leap years follow from its last four digits (10000 is a multiple of
		// 400), so no integer limit applies (Codex r8).
		$month = (int) $m[2];
		$day   = (int) $m[3];
		if ( '' === ltrim( $m[1], '0' ) || $month < 1 || $month > 12 || $day < 1 ) {
			return '';
		}
		$tail = (int) substr( $m[1], -4 );
		$leap = ( 0 === $tail % 4 && 0 !== $tail % 100 ) || 0 === $tail % 400;
		$days = array( 31, $leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31 );
		return $day <= $days[ $month - 1 ] ? $value : '';
	}

	/**
	 * A value an <input type="time"> holds (HH:MM, optionally :SS), else ''.
	 */
	private static function as_time_input( string $value ): string {
		return preg_match( '/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d(\.\d{1,3})?)?\z/', $value ) ? $value : '';
	}

	/**
	 * The empty-time defaults Util::parse_datetimes() applies, for the browser
	 * check (Codex r3 Minor 2, Fable M-E).
	 *
	 * @return array{pickup: string, dropoff: string}
	 */
	public static function default_times(): array {
		return array(
			'pickup'  => (string) apply_filters( 'mhmrentiva_default_pickup_time', '10:00' ),
			'dropoff' => (string) apply_filters( 'mhmrentiva_default_dropoff_time', '10:00' ),
		);
	}

	public static function notice(): string {
		return __( 'The return date and time must be after the pickup. The dates were not changed.', 'mhm-rentiva' );
	}
}
