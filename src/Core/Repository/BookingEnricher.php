<?php
declare(strict_types=1);

namespace MHMRentiva\Core\Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared customer-info enrichment for booking / operation row sets.
 *
 * Extracted (behaviour-preserving) from the customer-name fallback that was
 * duplicated in DashboardService::get_recent_bookings_paginated() and
 * ReportRepository::get_upcoming_operations_paginated().
 *
 * Invariant: phone enrichment runs ONLY for rows that already carry a
 * 'customer_phone' key. Dashboard rows have no such key (left untouched);
 * Report rows do (phone fallback preserved). This keeps both call sites
 * byte-for-byte identical in behaviour to their pre-refactor state.
 */
final class BookingEnricher {

	/**
	 * Fill a missing customer_name (and customer_phone, when that key already
	 * exists on the row) from the linked WooCommerce order first, then the
	 * linked WordPress user.
	 *
	 * @param array<int,array<string,mixed>> $rows Mutated in place by reference.
	 */
	public static function enrich_customer_info( array &$rows ): void {
		foreach ( $rows as &$row ) {
			if ( ! empty( $row['customer_name'] ) || empty( $row['id'] ) ) {
				continue;
			}

			$booking_id  = (int) $row['id'];
			$wants_phone = array_key_exists( 'customer_phone', $row );

			// Try WooCommerce order.
			if ( function_exists( 'wc_get_order' ) ) {
				$order_id = get_post_meta( $booking_id, '_mhm_woocommerce_order_id', true )
					?: get_post_meta( $booking_id, '_mhm_wc_order_id', true )
					?: get_post_meta( $booking_id, '_mhm_order_id', true )
					?: get_post_meta( $booking_id, '_booking_order_id', true );

				if ( $order_id ) {
					$order = wc_get_order( $order_id );
					if ( $order ) {
						$first = $order->get_billing_first_name();
						$last  = $order->get_billing_last_name();
						if ( $first || $last ) {
							$row['customer_name'] = trim( $first . ' ' . $last );
						}
						if ( $wants_phone && empty( $row['customer_phone'] ) ) {
							$row['customer_phone'] = $order->get_billing_phone();
						}
						continue;
					}
				}
			}

			// Try WordPress user.
			$user_id = get_post_meta( $booking_id, '_mhm_customer_user_id', true );
			if ( $user_id ) {
				$user = get_userdata( (int) $user_id );
				if ( $user ) {
					$first = $user->first_name;
					$last  = $user->last_name;
					if ( $first || $last ) {
						$row['customer_name'] = trim( $first . ' ' . $last );
					}
					if ( $wants_phone && empty( $row['customer_phone'] ) ) {
						$row['customer_phone'] = get_user_meta( (int) $user_id, 'phone', true );
					}
				}
			}
		}
		unset( $row );
	}
}
