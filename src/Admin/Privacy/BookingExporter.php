<?php
/**
 * A person's bookings in the core personal data export.
 *
 * @package MHMRentiva
 */

declare(strict_types=1);

namespace MHMRentiva\Admin\Privacy;

use MHMRentiva\Admin\Booking\Core\PaymentView;
use MHMRentiva\Admin\Booking\Core\Status;
use MHMRentiva\Admin\Booking\Meta\BookingMeta;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exporter callback for `mhm-rentiva-bookings` (5a spec T6).
 *
 * Every booking that matches, active or closed: the right of access covers
 * all of them. The History box's entries go out in full, staff notes included
 * (decision K4, 2026-11-03: the box itself tells staff so). Left out: who
 * cancelled and with which roles (the operator's data), the internal action
 * log, internal IDs.
 */
final class BookingExporter {

	public const GROUP_ID = 'mhm-rentiva-bookings';

	private const PER_PAGE = 50;

	/**
	 * Core exporter callback.
	 *
	 * @param string $email Requested address.
	 * @param int    $page  1-based page.
	 * @return array{data: array<int, array<string, mixed>>, done: bool}
	 */
	public static function export( string $email, int $page = 1 ): array {
		$page = max( 1, $page );
		$ids  = BookingMatcher::ids( $email, 'all', self::PER_PAGE, ( $page - 1 ) * self::PER_PAGE );

		$items = array();
		foreach ( $ids as $id ) {
			$items[] = array(
				'group_id'    => self::GROUP_ID,
				'group_label' => __( 'Bookings', 'mhm-rentiva' ),
				'item_id'     => 'booking-' . $id,
				'data'        => self::fields( $id ),
			);
		}

		return array(
			'data' => $items,
			'done' => count( $ids ) < self::PER_PAGE,
		);
	}

	/**
	 * Name/value pairs for one booking; empty values are left out.
	 *
	 * @param int $id Booking ID.
	 * @return array<int, array{name: string, value: string}>
	 */
	private static function fields( int $id ): array {
		$meta = static fn( string $key ): string => is_scalar( $v = get_post_meta( $id, $key, true ) ) ? trim( (string) $v ) : '';

		$vehicle_id   = (int) $meta( '_mhmrentiva_vehicle_id' );
		$cancellation = get_post_meta( $id, '_mhmrentiva_cancellation_data', true );
		$cancellation = is_array( $cancellation ) ? $cancellation : array();
		$receipt_id   = (int) $meta( '_mhmrentiva_receipt_attachment_id' );
		$status       = (string) get_post_meta( $id, '_mhmrentiva_status', true );
		$payment      = $meta( '_mhmrentiva_payment_status' );
		// Current key first, then the legacy spellings the matcher also finds.
		$first = static function ( array $keys ) use ( $meta ): string {
			foreach ( $keys as $key ) {
				if ( '' !== $meta( $key ) ) {
					return $meta( $key );
				}
			}
			return '';
		};
		$name = $meta( '_mhmrentiva_customer_name' );
		if ( '' === $name ) {
			$name = trim( $meta( '_mhmrentiva_customer_first_name' ) . ' ' . $meta( '_mhmrentiva_customer_last_name' ) );
		}
		if ( '' === $name ) {
			$name = $first( array( '_mhmrentiva_booking_customer_name', '_mhmrentiva_booking_customer_first_name', '_mhmrentiva_contact_name' ) );
		}

		$pairs = array(
			__( 'Booking number', 'mhm-rentiva' )          => (string) $id,
			__( 'Status', 'mhm-rentiva' )                  => '' !== $status ? Status::get_label( $status ) : '',
			__( 'Vehicle', 'mhm-rentiva' )                 => $vehicle_id > 0 ? get_the_title( $vehicle_id ) : '',
			__( 'Pick-up date', 'mhm-rentiva' )            => trim( $meta( '_mhmrentiva_pickup_date' ) . ' ' . $meta( '_mhmrentiva_start_time' ) ),
			__( 'Return date', 'mhm-rentiva' )             => trim( ( '' !== $meta( '_mhmrentiva_return_date' ) ? $meta( '_mhmrentiva_return_date' ) : $meta( '_mhmrentiva_dropoff_date' ) ) . ' ' . $meta( '_mhmrentiva_end_time' ) ),
			__( 'Name', 'mhm-rentiva' )                    => $name,
			__( 'E-mail', 'mhm-rentiva' )                  => $first( array( '_mhmrentiva_customer_email', '_mhmrentiva_booking_customer_email', '_mhmrentiva_contact_email' ) ),
			__( 'Phone', 'mhm-rentiva' )                   => $first( array( '_mhmrentiva_customer_phone', '_mhmrentiva_booking_customer_phone', '_mhmrentiva_contact_phone' ) ),
			__( 'Guests', 'mhm-rentiva' )                  => $meta( '_mhmrentiva_guests' ),
			__( 'Special notes', 'mhm-rentiva' )           => $meta( '_mhmrentiva_special_notes' ),
			__( 'Booking note', 'mhm-rentiva' )            => trim( (string) get_post_field( 'post_content', $id ) ),
			__( 'Total', 'mhm-rentiva' )                   => $meta( '_mhmrentiva_total_price' ),
			__( 'Payment status', 'mhm-rentiva' )          => '' !== $payment ? PaymentView::label( $payment ) : '',
			__( 'Cancellation reason', 'mhm-rentiva' )     => is_scalar( $cancellation['cancellation_reason'] ?? null ) ? (string) $cancellation['cancellation_reason'] : '',
			__( 'Cancelled via', 'mhm-rentiva' )           => is_scalar( $cancellation['cancelled_via'] ?? null ) ? (string) $cancellation['cancelled_via'] : '',
			__( 'Refund reason', 'mhm-rentiva' )           => $meta( '_mhmrentiva_refund_reason' ),
			__( 'Refund reference', 'mhm-rentiva' )        => $meta( '_mhmrentiva_refund_completed_reference' ),
			__( 'Refund review reason', 'mhm-rentiva' )    => $meta( '_mhmrentiva_refund_review_dismissed_reason' ),
			__( 'Payment receipt', 'mhm-rentiva' )         => $receipt_id > 0 ? (string) wp_get_attachment_url( $receipt_id ) : '',
			__( 'Payment receipt note', 'mhm-rentiva' )    => $meta( '_mhmrentiva_receipt_note' ),
			__( 'History', 'mhm-rentiva' )                 => self::history( $id ),
		);

		$out = array();
		foreach ( $pairs as $name_label => $value ) {
			if ( '' !== $value ) {
				$out[] = array(
					'name'  => $name_label,
					'value' => $value,
				);
			}
		}

		return $out;
	}

	/**
	 * When a History entry was written, as a site date and time.
	 *
	 * `at` is the UTC instant (current writer); older entries only carry
	 * `timestamp`, a site-local clock reading, shown the way the box does.
	 *
	 * @param array<string, mixed> $entry History entry.
	 * @return string
	 */
	private static function when( array $entry ): string {
		$format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
		if ( isset( $entry['at'] ) && is_numeric( $entry['at'] ) && (int) $entry['at'] > 0 ) {
			return (string) wp_date( $format, (int) $entry['at'] );
		}
		if ( isset( $entry['timestamp'] ) && is_numeric( $entry['timestamp'] ) && (int) $entry['timestamp'] > 0 ) {
			return (string) date_i18n( $format, (int) $entry['timestamp'] );
		}

		return '';
	}

	/**
	 * The History box's entries, one per line: "date — type — note".
	 *
	 * @param int $id Booking ID.
	 * @return string
	 */
	private static function history( int $id ): string {
		$history = BookingMeta::history_sorted( $id );

		$lines = array();
		foreach ( $history as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}
			$note = trim( (string) ( $entry['note'] ?? '' ) );
			if ( '' === $note && isset( $entry['payment_from'], $entry['payment_to'] ) ) {
				$note = PaymentView::label( (string) $entry['payment_from'] ) . ' → ' . PaymentView::label( (string) $entry['payment_to'] );
			}
			$lines[] = implode(
				' — ',
				array_filter(
					array(
						self::when( $entry ),
						BookingMeta::get_history_type_label( (string) ( $entry['type'] ?? 'note' ) ),
						$note,
					),
					static fn( string $part ): bool => '' !== $part
				)
			);
		}

		return implode( "\n", $lines );
	}
}
