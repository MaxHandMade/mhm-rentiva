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
	 * The stored range, for a saver that must keep it.
	 *
	 * @return array{0: string, 1: string, 2: string, 3: string} pickup date, pickup time, return date, return time
	 */
	public static function stored( int $booking_id ): array {
		return array(
			(string) get_post_meta( $booking_id, '_mhmrentiva_pickup_date', true ),
			(string) get_post_meta( $booking_id, '_mhmrentiva_start_time', true ),
			(string) get_post_meta( $booking_id, '_mhmrentiva_dropoff_date', true ),
			(string) get_post_meta( $booking_id, '_mhmrentiva_end_time', true ),
		);
	}

	public static function notice(): string {
		return __( 'The return date and time must be after the pickup. The dates were not changed.', 'mhm-rentiva' );
	}
}
