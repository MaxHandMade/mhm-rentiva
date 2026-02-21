<?php

declare(strict_types=1);
// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query,WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value,WordPress.DB.SlowDBQuery.slow_db_query_tax_query,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Booking query helper intentionally composes bounded lookup/reporting queries.

namespace MHMRentiva\Admin\Core\Utilities;

use WP_Query;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Central Booking Query Helper
 *
 * Provides common functions for all booking queries
 * and prevents code duplication.
 */
final class BookingQueryHelper {

	/**
	 * Find booking by meta key
	 *
	 * @param string $meta_key Meta key
	 * @param string $meta_value Meta value
	 * @param string $post_type Post type (default: 'vehicle_booking')
	 * @param string $post_status Post status (default: 'any')
	 * @return int Booking ID (0 = not found)
	 */
	public static function findBookingByMeta(
		string $meta_key,
		string $meta_value,
		string $post_type = 'vehicle_booking',
		string $post_status = 'any'
	): int {
		if ( empty( $meta_key ) || empty( $meta_value ) ) {
			return 0;
		}

		$query_args = array(
			'post_type'      => $post_type,
			'post_status'    => $post_status,
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'     => $meta_key,
					'value'   => $meta_value,
					'compare' => '=',
				),
			),
		);

		$query = new WP_Query( $query_args );

		return $query->have_posts() ? (int) $query->posts[0] : 0;
	}



	/**
	 * Find bookings by customer email
	 *
	 * @param string $email Customer email
	 * @param array  $statuses Post statuses (default: ['publish'])
	 * @param int    $limit Limit (default: -1 = all)
	 * @return array Booking IDs
	 */
	public static function findBookingsByCustomerEmail(
		string $email,
		array $statuses = array( 'publish' ),
		int $limit = -1
	): array {
		if ( empty( $email ) ) {
			return array();
		}

		$query_args = array(
			'post_type'      => 'vehicle_booking',
			'post_status'    => $statuses,
			'posts_per_page' => $limit,
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'     => '_booking_customer_email',
					'value'   => $email,
					'compare' => '=',
				),
			),
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		$query = new WP_Query( $query_args );

		return $query->have_posts() ? array_map( 'intval', $query->posts ) : array();
	}

	/**
	 * Find bookings by vehicle ID
	 *
	 * @param int   $vehicle_id Vehicle ID
	 * @param array $statuses Post statuses (default: ['publish'])
	 * @param int   $limit Limit (default: -1 = all)
	 * @return array Booking IDs
	 */
	public static function findBookingsByVehicle(
		int $vehicle_id,
		array $statuses = array( 'publish' ),
		int $limit = -1
	): array {
		if ( $vehicle_id <= 0 ) {
			return array();
		}

		$query_args = array(
			'post_type'      => 'vehicle_booking',
			'post_status'    => $statuses,
			'posts_per_page' => $limit,
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'     => '_booking_vehicle_id',
					'value'   => $vehicle_id,
					'compare' => '=',
				),
			),
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		$query = new WP_Query( $query_args );

		return $query->have_posts() ? array_map( 'intval', $query->posts ) : array();
	}

	/**
	 * Find bookings by date range
	 *
	 * @param string $start_date Start date (Y-m-d)
	 * @param string $end_date End date (Y-m-d)
	 * @param array  $statuses Post statuses (default: ['publish'])
	 * @param int    $limit Limit (default: -1 = all)
	 * @return array Booking IDs
	 */
	public static function findBookingsByDateRange(
		string $start_date,
		string $end_date,
		array $statuses = array( 'publish' ),
		int $limit = -1
	): array {
		if ( empty( $start_date ) || empty( $end_date ) ) {
			return array();
		}

		$query_args = array(
			'post_type'      => 'vehicle_booking',
			'post_status'    => $statuses,
			'posts_per_page' => $limit,
			'fields'         => 'ids',
			'meta_query'     => array(
				'relation' => 'AND',
				array(
					'key'     => '_booking_pickup_date',
					'value'   => array( $start_date, $end_date ),
					'compare' => 'BETWEEN',
					'type'    => 'DATE',
				),
			),
			'orderby'        => 'meta_value',
			'meta_key'       => '_booking_pickup_date',
			'order'          => 'ASC',
		);

		$query = new WP_Query( $query_args );

		return $query->have_posts() ? array_map( 'intval', $query->posts ) : array();
	}

	/**
	 * Find bookings by payment status
	 *
	 * @param string $payment_status Payment status
	 * @param array  $post_statuses Post statuses (default: ['publish'])
	 * @param int    $limit Limit (default: -1 = all)
	 * @return array Booking IDs
	 */
	public static function findBookingsByPaymentStatus(
		string $payment_status,
		array $post_statuses = array( 'publish' ),
		int $limit = -1
	): array {
		if ( empty( $payment_status ) ) {
			return array();
		}

		$query_args = array(
			'post_type'      => 'vehicle_booking',
			'post_status'    => $post_statuses,
			'posts_per_page' => $limit,
			'fields'         => 'ids',
			'meta_query'     => array(
				'relation' => 'OR',
				array(
					'key'     => '_booking_payment_status',
					'value'   => $payment_status,
					'compare' => '=',
				),
				array(
					'key'     => '_mhm_payment_status',
					'value'   => $payment_status,
					'compare' => '=',
				),
			),
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		$query = new WP_Query( $query_args );

		return $query->have_posts() ? array_map( 'intval', $query->posts ) : array();
	}

	/**
	 * Get booking's payment gateway information
	 *
	 * @param int $booking_id Booking ID
	 * @return string Payment gateway
	 */
	public static function getBookingPaymentGateway( int $booking_id ): string {
		if ( $booking_id <= 0 ) {
			return '';
		}

		// Try new meta key
		$gateway = get_post_meta( $booking_id, '_booking_payment_gateway', true );
		if ( ! empty( $gateway ) ) {
			return $gateway;
		}

		// Try old meta key
		$gateway = get_post_meta( $booking_id, '_mhm_payment_gateway', true );
		if ( ! empty( $gateway ) ) {
			return $gateway;
		}

		return 'unknown';
	}

	/**
	 * Get booking's payment status
	 *
	 * @param int $booking_id Booking ID
	 * @return string Payment status
	 */
	public static function getBookingPaymentStatus( int $booking_id ): string {
		if ( $booking_id <= 0 ) {
			return 'unknown';
		}

		// Try new meta key
		$status = get_post_meta( $booking_id, '_booking_payment_status', true );
		if ( ! empty( $status ) ) {
			return $status;
		}

		// Try old meta key
		$status = get_post_meta( $booking_id, '_mhm_payment_status', true );
		if ( ! empty( $status ) ) {
			return $status;
		}

		return 'unknown';
	}

	/**
	 * Get booking's total price
	 *
	 * @param int $booking_id Booking ID
	 * @return float Total price
	 */
	public static function getBookingTotalPrice( int $booking_id ): float {
		if ( $booking_id <= 0 ) {
			return 0.0;
		}

		// Try new meta key
		$price = get_post_meta( $booking_id, '_booking_total_price', true );
		if ( is_numeric( $price ) ) {
			return (float) $price;
		}

		// Try old meta key
		$price = get_post_meta( $booking_id, '_mhm_total_price', true );
		if ( is_numeric( $price ) ) {
			return (float) $price;
		}

		return 0.0;
	}

	/**
	 * Get booking's customer information
	 *
	 * @param int $booking_id Booking ID
	 * @return array Customer information
	 */
	public static function getBookingCustomerInfo( int $booking_id ): array {
		if ( $booking_id <= 0 ) {
			return array();
		}

		// Get from new meta keys (first/last name separate)
		$first_name = get_post_meta( $booking_id, '_mhm_customer_first_name', true );
		$last_name  = get_post_meta( $booking_id, '_mhm_customer_last_name', true );
		$email      = get_post_meta( $booking_id, '_mhm_customer_email', true );
		$phone      = get_post_meta( $booking_id, '_mhm_customer_phone', true );

		// If no data in new keys, try old keys
		if ( empty( $first_name ) ) {
			$first_name = get_post_meta( $booking_id, '_booking_customer_first_name', true );
		}

		// If still empty, try full name fields
		if ( empty( $first_name ) && empty( $last_name ) ) {
			$full_name = get_post_meta( $booking_id, '_booking_customer_name', true ) ?:
						get_post_meta( $booking_id, '_mhm_customer_name', true ) ?:
						get_post_meta( $booking_id, '_mhm_contact_name', true );

			if ( $full_name ) {
				// Try to split full name into first and last name
				$name_parts = explode( ' ', trim( $full_name ), 2 );
				if ( count( $name_parts ) >= 2 ) {
					$first_name = $name_parts[0];
					$last_name  = $name_parts[1];
				} else {
					$first_name = $full_name;
				}
			}
		}

		if ( empty( $email ) ) {
			$email = get_post_meta( $booking_id, '_booking_customer_email', true ) ?:
					get_post_meta( $booking_id, '_mhm_contact_email', true ) ?: '';
		}

		if ( empty( $phone ) ) {
			$phone = get_post_meta( $booking_id, '_booking_customer_phone', true ) ?:
					get_post_meta( $booking_id, '_mhm_contact_phone', true ) ?: '';
		}

		// If still empty, try WooCommerce order
		if ( ( empty( $first_name ) || empty( $email ) ) && function_exists( 'wc_get_order' ) ) {
			// ⭐ Check multiple order ID meta keys (WooCommerce integration)
			$order_id = get_post_meta( $booking_id, '_mhm_woocommerce_order_id', true ) ?:
						get_post_meta( $booking_id, '_mhm_wc_order_id', true ) ?:
						get_post_meta( $booking_id, '_mhm_order_id', true ) ?:
						get_post_meta( $booking_id, '_booking_order_id', true );

			if ( $order_id ) {
				$order = wc_get_order( $order_id );
				if ( $order ) {
					if ( empty( $first_name ) ) {
						$first_name = $order->get_billing_first_name();
					}
					if ( empty( $last_name ) ) {
						$last_name = $order->get_billing_last_name();
					}
					if ( empty( $email ) ) {
						$email = $order->get_billing_email();
					}
					if ( empty( $phone ) ) {
						$phone = $order->get_billing_phone();
					}
				}
			}
		}

		// If still empty, try WordPress user
		if ( empty( $first_name ) || empty( $email ) ) {
			$user_id = get_post_meta( $booking_id, '_mhm_customer_user_id', true );
			if ( $user_id ) {
				$user = get_userdata( $user_id );
				if ( $user ) {
					if ( empty( $first_name ) ) {
						$first_name = $user->first_name;
					}
					if ( empty( $last_name ) ) {
						$last_name = $user->last_name;
					}
					if ( empty( $email ) ) {
						$email = $user->user_email;
					}
					if ( empty( $phone ) ) {
						$phone = get_user_meta( $user_id, 'phone', true );
					}
				}
			}
		}

		return array(
			'email'      => $email,
			'first_name' => $first_name,
			'last_name'  => $last_name,
			'phone'      => $phone,
		);
	}

	/**
	 * Get booking's vehicle information
	 *
	 * @param int $booking_id Booking ID
	 * @return array Vehicle information
	 */
	public static function getBookingVehicleInfo( int $booking_id ): array {
		if ( $booking_id <= 0 ) {
			return array();
		}

		$vehicle_id = (int) ( get_post_meta( $booking_id, '_booking_vehicle_id', true ) ?:
							get_post_meta( $booking_id, '_mhm_vehicle_id', true ) ?: 0 );

		if ( $vehicle_id <= 0 ) {
			return array();
		}

		$vehicle = get_post( $vehicle_id );

		return array(
			'id'             => $vehicle_id,
			'title'          => $vehicle ? $vehicle->post_title : '',
			'price_per_day'  => get_post_meta( $vehicle_id, '_mhm_rentiva_price_per_day', true ) ?: 0,
			'featured_image' => get_the_post_thumbnail_url( $vehicle_id, 'medium' ) ?: '',
		);
	}

	/**
	 * Get booking's date information
	 *
	 * @param int $booking_id Booking ID
	 * @return array Date information
	 */
	public static function getBookingDateInfo( int $booking_id ): array {
		if ( $booking_id <= 0 ) {
			return array();
		}

		return array(
			'pickup_date' => get_post_meta( $booking_id, '_booking_pickup_date', true ) ?:
							get_post_meta( $booking_id, '_mhm_pickup_date', true ) ?: '',
			'return_date' => get_post_meta( $booking_id, '_booking_return_date', true ) ?:
							get_post_meta( $booking_id, '_mhm_dropoff_date', true ) ?: '',
			'rental_days' => (int) ( get_post_meta( $booking_id, '_booking_rental_days', true ) ?:
									get_post_meta( $booking_id, '_mhm_rental_days', true ) ?: 0 ),
		);
	}

	/**
	 * Get booking statistics
	 *
	 * @param array $filters Filters (status, payment_status, date_range, etc.)
	 * @return array Statistics
	 */
	public static function getBookingStats( array $filters = array() ): array {
		$query_args = array(
			'post_type'      => 'vehicle_booking',
			'post_status'    => $filters['post_status'] ?? array( 'publish' ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
		);

		// Add meta query
		$meta_query = array();

		if ( ! empty( $filters['payment_status'] ) ) {
			$meta_query[] = array(
				'relation' => 'OR',
				array(
					'key'     => '_booking_payment_status',
					'value'   => $filters['payment_status'],
					'compare' => '=',
				),
				array(
					'key'     => '_mhm_payment_status',
					'value'   => $filters['payment_status'],
					'compare' => '=',
				),
			);
		}

		if ( ! empty( $filters['date_range'] ) ) {
			$meta_query[] = array(
				'key'     => '_booking_pickup_date',
				'value'   => $filters['date_range'],
				'compare' => 'BETWEEN',
				'type'    => 'DATE',
			);
		}

		if ( ! empty( $meta_query ) ) {
			$query_args['meta_query'] = $meta_query;
		}

		$query       = new WP_Query( $query_args );
		$booking_ids = $query->have_posts() ? $query->posts : array();

		// Calculate statistics
		$total_bookings   = count( $booking_ids );
		$total_revenue    = 0.0;
		$payment_statuses = array();
		$gateways         = array();

		foreach ( $booking_ids as $booking_id ) {
			$total_revenue += self::getBookingTotalPrice( (int) $booking_id );
			$payment_status = self::getBookingPaymentStatus( (int) $booking_id );
			$gateway        = self::getBookingPaymentGateway( (int) $booking_id );

			$payment_statuses[ $payment_status ] = ( $payment_statuses[ $payment_status ] ?? 0 ) + 1;
			$gateways[ $gateway ]                = ( $gateways[ $gateway ] ?? 0 ) + 1;
		}

		return array(
			'total_bookings'   => $total_bookings,
			'total_revenue'    => $total_revenue,
			'payment_statuses' => $payment_statuses,
			'payment_gateways' => $gateways,
			'average_revenue'  => $total_bookings > 0 ? $total_revenue / $total_bookings : 0.0,
		);
	}
}

