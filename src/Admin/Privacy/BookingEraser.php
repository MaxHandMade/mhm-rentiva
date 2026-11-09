<?php
/**
 * Eraser callback for a person's bookings.
 *
 * @package MHMRentiva
 */

declare(strict_types=1);

namespace MHMRentiva\Admin\Privacy;

use MHMRentiva\Admin\Utilities\Dashboard\DashboardPage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * `mhm-rentiva-bookings` eraser (5a spec T4, T5; user decision U1).
 *
 * Closed bookings are anonymised, active ones are kept and named once. The
 * queue never shifts by $page: an anonymised booking stops matching, so each
 * call takes the first fifty closed bookings that still match and were not
 * marked as failed in the last ErasureMarker::WINDOW seconds. No lock: the
 * anonymiser is idempotent, so overlapping runs reach the same state.
 */
final class BookingEraser {

	private const PER_PAGE = 50;

	private const ACTIVE_LIST = 20;

	/**
	 * Core eraser callback.
	 *
	 * @param string $email Requested address.
	 * @param int    $page  1-based page.
	 * @return array{items_removed: bool, items_retained: bool, messages: string[], done: bool}
	 */
	public static function erase( string $email, int $page = 1 ): array {
		$result = array(
			'items_removed'  => false,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);

		if ( '' === EmailMatch::normalise( $email ) ) {
			return $result;
		}

		$account         = get_user_by( 'email', EmailMatch::normalise( $email ) );
		$subject_user_id = $account instanceof \WP_User ? (int) $account->ID : 0;

		if ( 1 === $page ) {
			$result = self::page_one_notes( $email, $subject_user_id, $result );
		}

		$queue = BookingMatcher::ids( $email, 'queue', self::PER_PAGE );
		foreach ( $queue as $booking_id ) {
			$left = BookingAnonymizer::anonymize( $booking_id, $subject_user_id, $email );
			if ( array() === $left ) {
				ErasureMarker::clear( $booking_id );
				$result['items_removed'] = true;
				continue;
			}

			$result['items_retained'] = true;
			if ( ! ErasureMarker::mark( $booking_id ) ) {
				// The booking would come back on the next page: stop rather than loop.
				$result['messages'][] = __( 'The erasure stopped: a booking could not be marked as failed.', 'mhm-rentiva' );
				$result['done']       = true;
				self::finish();

				return $result;
			}
			$result['messages'][] = sprintf(
				/* translators: 1: booking ID, 2: comma-separated internal field group names. */
				__( 'Booking #%1$d could not be fully anonymised (%2$s); run the erasure again in 15 minutes.', 'mhm-rentiva' ),
				$booking_id,
				implode( ', ', $left )
			);
		}

		$result['done'] = count( $queue ) < self::PER_PAGE;
		if ( $result['done'] ) {
			self::finish();
		}

		return $result;
	}

	/**
	 * Messages the administrator needs once per run.
	 *
	 * @param string                                                                        $email           Requested address.
	 * @param int                                                                           $subject_user_id Account of the address.
	 * @param array{items_removed: bool, items_retained: bool, messages: string[], done: bool} $result          Result so far.
	 * @return array{items_removed: bool, items_retained: bool, messages: string[], done: bool}
	 */
	private static function page_one_notes( string $email, int $subject_user_id, array $result ): array {
		$active = BookingMatcher::count( $email, 'active' );
		if ( $active > 0 ) {
			$ids  = BookingMatcher::ids( $email, 'active', self::ACTIVE_LIST );
			$list = implode( ', ', array_map( static fn( int $id ): string => '#' . $id, $ids ) );
			if ( $active > count( $ids ) ) {
				/* translators: %d: number of further bookings not listed. */
				$list .= sprintf( __( ' (+%d more)', 'mhm-rentiva' ), $active - count( $ids ) );
			}
			$result['messages'][] = sprintf(
				/* translators: 1: number of bookings, 2: list of booking numbers. */
				_n( '%1$d active booking kept: %2$s', '%1$d active bookings kept: %2$s', $active, 'mhm-rentiva' ),
				$active,
				$list
			);
			$result['items_retained'] = true;
		}

		$marked = BookingMatcher::count( $email, 'recently_marked' );
		if ( $marked > 0 ) {
			$result['messages'][] = sprintf(
				/* translators: %d: number of bookings. */
				_n( '%d booking could not be fully anonymised in the last 15 minutes; run the erasure again in 15 minutes.', '%d bookings could not be fully anonymised in the last 15 minutes; run the erasure again in 15 minutes.', $marked, 'mhm-rentiva' ),
				$marked
			);
			$result['items_retained'] = true;
		}

		if ( BookingMatcher::any_linked_order( $email ) ) {
			$result['messages'][] = __( 'Linked WooCommerce orders keep their billing details according to the WooCommerce privacy setting.', 'mhm-rentiva' );
		}

		if ( $subject_user_id > 0 ) {
			$result['messages'][] = __( 'The user account was not deleted; delete it from the Users screen if needed.', 'mhm-rentiva' );
		}

		return $result;
	}

	/**
	 * The dashboard caches customer names and phones for twelve hours.
	 *
	 * @return void
	 */
	private static function finish(): void {
		if ( class_exists( DashboardPage::class ) ) {
			DashboardPage::clear_dashboard_cache();
		}
	}
}
