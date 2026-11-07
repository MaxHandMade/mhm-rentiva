<?php
/**
 * The date rule of the booking edit form.
 *
 * @package MHM_Rentiva
 */

declare(strict_types=1);

namespace MHMRentiva\Admin\Booking\Meta;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A return before the pickup is not a rental (function scan H4). An equal
 * date and time is left alone: a transfer or a same-day booking may carry it,
 * and refusing it would warn on every Update of such a booking (Fable code
 * audit M-2).
 *
 * Both savers ask this one rule; a reversed range keeps the stored dates and
 * the rest of the Update is saved. The browser check in booking-edit-meta.js
 * applies the same rule before the form is sent.
 */
final class BookingEditDates {

	public static function is_reversed( string $pickup_date, string $pickup_time, string $dropoff_date, string $dropoff_time ): bool {
		if ( '' === $pickup_date || '' === $dropoff_date ) {
			return false;
		}

		$pickup  = strtotime( $pickup_date . ' ' . ( '' !== $pickup_time ? $pickup_time : '00:00' ) );
		$dropoff = strtotime( $dropoff_date . ' ' . ( '' !== $dropoff_time ? $dropoff_time : '00:00' ) );

		return false !== $pickup && false !== $dropoff && $dropoff < $pickup;
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
		return __( 'The return date is before the pickup date. The dates were not changed.', 'mhm-rentiva' );
	}
}
