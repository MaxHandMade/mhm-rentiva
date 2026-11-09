<?php
/**
 * Price-input changes an admin Update may not write yet.
 *
 * @package MHM_Rentiva
 */

declare(strict_types=1);

namespace MHMRentiva\Admin\Booking\Meta;

use MHMRentiva\Admin\Addons\AddonSelection;
use MHMRentiva\Admin\Payment\Core\Money;
use MHMRentiva\Admin\Payment\Core\PaymentState;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Should this Update's vehicle, dates, times and add-ons be kept as stored?
 *
 * Re-pricing keeps the money already collected and makes the difference owed
 * (BookingMeta::recalculate_booking_costs()). Four cases cannot be written that
 * way yet, and the price inputs then stay as stored while the rest of the
 * Update (notes, status, guests) is saved, the way a reversed date range is
 * handled (BookingEditDates):
 *
 * (i)   the new total is below what was collected -- the offline channel derives
 *       the paid amount from the total, so the excess would vanish from the
 *       record (user decision 2026-11-09: refuse and point to cancel + refund);
 * (ii)  a remaining-payment order exists for the booking, whatever its status,
 *       and the total changes -- that order was built for the old amount
 *       (WooCommerce remaining-order races, plan r4-r9; lifted by PR-2);
 * (iii) the payments are in more than one currency and the total changes --
 *       the collected amount cannot be known (PaymentState::paid() is 0);
 * (iv)  the booking is in a refund status and the total rises -- "Process
 *       remaining" is not offered there, so the difference could not be taken.
 *
 * Asked once per booking per request: both savers (BookingEditMetaBox at 5,
 * BookingMeta::save_meta at 10) and a nested save_post (the title update) see
 * the same answer, read before anything was written.
 *
 * Plan: docs/superpowers/plans/2026-11-09-h2-yuz-2-fark-kalan.md (r7-r10).
 */
final class BookingEditPriceFloor {

	/** @var array<int, string> Booking id => refusal message ('' = allowed). */
	private static array $verdicts = array();

	/** Forget the answers (tests; a new request starts empty). */
	public static function reset(): void {
		self::$verdicts = array();
	}

	/**
	 * Decide for this booking, once. Returns true when the price inputs must stay
	 * as stored; the notice is queued the first time.
	 */
	public static function refuse( int $post_id, int $vehicle_id, string $pickup_date, string $pickup_time, string $dropoff_date, string $dropoff_time, AddonSelection $selection ): bool {
		if ( ! array_key_exists( $post_id, self::$verdicts ) ) {
			self::$verdicts[ $post_id ] = self::decide( $post_id, $vehicle_id, $pickup_date, $pickup_time, $dropoff_date, $dropoff_time, $selection );
			if ( '' !== self::$verdicts[ $post_id ] ) {
				self::notify( $post_id, self::$verdicts[ $post_id ] );
			}
		}

		return '' !== self::$verdicts[ $post_id ];
	}

	/** Has this request refused this booking's price inputs? */
	public static function refused( int $post_id ): bool {
		return '' !== ( self::$verdicts[ $post_id ] ?? '' );
	}

	/**
	 * The stored range, the way the savers name it.
	 *
	 * @return array{0: string, 1: string, 2: string, 3: string} pickup date, pickup time, return date, return time.
	 */
	public static function stored_range( int $post_id ): array {
		return array(
			(string) get_post_meta( $post_id, '_mhmrentiva_pickup_date', true ),
			(string) get_post_meta( $post_id, '_mhmrentiva_start_time', true ),
			(string) get_post_meta( $post_id, '_mhmrentiva_dropoff_date', true ),
			(string) get_post_meta( $post_id, '_mhmrentiva_end_time', true ),
		);
	}

	/**
	 * Does any remaining-payment order point at this booking? Any status: the
	 * booking's own pointer is deleted when such an order fails or is cancelled
	 * (WooCommerceBridge), so the order meta is what is asked.
	 */
	public static function has_remaining_order( int $post_id ): bool {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return false;
		}

		// The posts store drops a caller's meta_query (WC_Data_Store_WP::
		// get_wp_query_args() skips the key), which would answer with any order
		// at all (Codex r10 2). There the orders are posts and are asked directly.
		if ( ! self::orders_in_custom_tables() ) {
			return self::remaining_order_in_posts( $post_id );
		}

		$found = wc_get_orders(
			array(
				'limit'      => 1,
				'return'     => 'ids',
				// 'all', not 'any': 'any' still leaves trashed orders out
				// (OrdersTableQuery), and the posts half counts them (Codex r11 2).
				'status'     => 'all',
				'type'       => 'shop_order',
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- one booking's orders, limit 1.
					array(
						'key'   => '_mhmrentiva_booking_id',
						'value' => (string) $post_id,
					),
					array(
						'key'   => '_mhmrentiva_is_remaining_payment',
						'value' => '1',
					),
				),
			)
		);

		return is_array( $found ) && array() !== $found;
	}

	/** Are orders kept in WooCommerce's own tables (HPOS) rather than as posts? */
	private static function orders_in_custom_tables(): bool {
		return class_exists( \Automattic\WooCommerce\Utilities\OrderUtil::class )
			&& \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
	}

	/**
	 * The posts-store half of has_remaining_order(): a shop_order post carrying
	 * both meta, any status.
	 */
	public static function remaining_order_in_posts( int $post_id ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- a decision about money, read from the database; one booking, LIMIT 1.
		$found = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} b ON b.post_id = p.ID AND b.meta_key = '_mhmrentiva_booking_id' AND b.meta_value = %s
				INNER JOIN {$wpdb->postmeta} r ON r.post_id = p.ID AND r.meta_key = '_mhmrentiva_is_remaining_payment' AND r.meta_value = '1'
				WHERE p.post_type = 'shop_order' LIMIT 1",
				(string) $post_id
			)
		);

		return null !== $found && (int) $found > 0;
	}

	/**
	 * Does a paid order of this booking carry anything besides this booking?
	 *
	 * WooCommerceBridge books one order line per booking, so a cart with two
	 * vehicles is one order with two bookings, and PaymentState::paid() reads
	 * the whole order total for each of them -- as it does for any other product
	 * in the same cart. No per-booking share is recorded, so the amount this
	 * booking collected is unknown: re-pricing keeps the payment fields as they
	 * are and a total change is refused (Codex P1 on PR #183, r10 3).
	 *
	 * An order whose lines carry no booking id (the order-level id only, older
	 * orders) counts as this booking's alone only when it has a single line.
	 */
	public static function shares_an_order( int $booking_id, PaymentState $state ): bool {
		foreach ( $state->orders() as $order_id ) {
			$order = function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : false;
			if ( ! $order instanceof \WC_Order ) {
				continue;
			}
			$lines  = 0;
			$linked = 0;
			foreach ( $order->get_items() as $item ) {
				++$lines;
				$id = (int) $item->get_meta( '_mhmrentiva_booking_id', true );
				if ( $id > 0 ) {
					++$linked;
					if ( $id !== $booking_id ) {
						return true;
					}
				}
			}
			if ( 0 === $linked ) {
				if ( $lines > 1 || (int) $order->get_meta( '_mhmrentiva_booking_id', true ) !== $booking_id ) {
					return true;
				}
			} elseif ( $linked < $lines ) {
				// Another product shares the order.
				return true;
			}
		}

		return false;
	}

	private static function decide( int $post_id, int $vehicle_id, string $pickup_date, string $pickup_time, string $dropoff_date, string $dropoff_time, AddonSelection $selection ): string {
		// A transfer is not re-priced (BookingMeta::recalculate_booking_costs()).
		if ( BookingMeta::is_transfer_booking( $post_id ) ) {
			return '';
		}

		$state     = PaymentState::forBooking( $post_id );
		$mixed     = $state->isMixedCurrency();
		$shared    = self::shares_an_order( $post_id, $state );
		$collected = $mixed || $shared ? 0 : $state->paid();
		$type      = (string) get_post_meta( $post_id, '_mhmrentiva_payment_type', true );

		// The same mode recalculate_booking_costs() prices with, so an unchanged
		// booking compares equal to its stored total.
		$quote = BookingMeta::quote_total(
			$post_id,
			$vehicle_id > 0 ? $vehicle_id : (int) get_post_meta( $post_id, '_mhmrentiva_vehicle_id', true ),
			$pickup_date,
			$pickup_time,
			$dropoff_date,
			$dropoff_time,
			$selection,
			$collected > 0 || 'full' === $type ? 'full' : 'deposit'
		);
		if ( null === $quote ) {
			return '';
		}

		$new    = Money::toMinor( $quote['total'] );
		$stored = Money::toMinor( (float) get_post_meta( $post_id, '_mhmrentiva_total_price', true ) );
		if ( $new === $stored ) {
			return '';
		}

		if ( $mixed ) {
			return __( 'This booking was paid in more than one currency, so the amount collected cannot be compared with a new price. The vehicle, dates and add-ons were not changed.', 'mhm-rentiva' );
		}

		if ( $shared ) {
			return __( 'This booking was paid in an order that also contains other bookings or products, so the amount collected for it alone cannot be known. The vehicle, dates and add-ons were not changed. To change the price, cancel the booking and create a new one.', 'mhm-rentiva' );
		}

		if ( self::has_remaining_order( $post_id ) ) {
			return __( 'A remaining-payment link has been created for this booking, so a change that alters the total is not saved for now. The vehicle, dates and add-ons were not changed. To change the price, cancel the booking and create a new one.', 'mhm-rentiva' );
		}

		if ( $collected > 0 && $new < $collected ) {
			return sprintf(
				/* translators: 1: the new total, 2: the amount collected. */
				__( 'The new amount (%1$s) is less than what was collected (%2$s), so the vehicle, dates and add-ons were not changed. To shorten the booking, cancel it, refund the payment and create a new booking.', 'mhm-rentiva' ),
				self::money( $new ),
				self::money( $collected )
			);
		}

		$payment_status = (string) get_post_meta( $post_id, '_mhmrentiva_payment_status', true );
		if ( $new > $stored && in_array( $payment_status, array( 'partially_refunded', 'refunded' ), true ) ) {
			return __( 'This booking has been refunded, so a change that raises the total is not saved. The vehicle, dates and add-ons were not changed.', 'mhm-rentiva' );
		}

		return '';
	}

	/** One notice slot per booking: a date refusal in the same Update is kept. */
	private static function notify( int $post_id, string $message ): void {
		$earlier = BookingEditNotice::peek( $post_id );
		BookingEditNotice::queue( $post_id, '' !== $earlier && $earlier !== $message ? $earlier . ' ' . $message : $message, 'warning' );
	}

	private static function money( int $minor ): string {
		$major = Money::toMajor( $minor );
		if ( function_exists( 'wc_price' ) ) {
			return html_entity_decode( wp_strip_all_tags( wc_price( (float) $major ) ), ENT_QUOTES, 'UTF-8' );
		}
		return $major;
	}
}
