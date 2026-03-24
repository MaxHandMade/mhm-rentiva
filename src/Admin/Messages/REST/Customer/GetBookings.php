<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Messages\REST\Customer;

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query,WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value,WordPress.DB.SlowDBQuery.slow_db_query_tax_query,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded application queries are intentional in this module.



use MHMRentiva\Admin\Frontend\Account\AccountRenderer;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class GetBookings {

	/**
	 * Get customer bookings for messages form
	 */
	public static function handle( WP_REST_Request $request ): WP_REST_Response {
		// WordPress user authentication check
		if ( ! is_user_logged_in() ) {
			return new WP_REST_Response(
				array(
					'error' => __( 'Please login to access your bookings.', 'mhm-rentiva' ),
				),
				401
			);
		}

		$user = wp_get_current_user();

		// Get user bookings using AccountRenderer's method via reflection or create a public method
		// Since get_user_bookings is private, we'll use WP_Query directly
		$bookings = self::get_user_bookings( $user->ID );

		// Format bookings for dropdown
		$formatted_bookings = array();
		foreach ( $bookings as $booking ) {
			$booking_id = $booking->ID;
			// Try both meta key formats
			$vehicle_id    = get_post_meta( $booking_id, '_mhm_vehicle_id', true ) ?:
						get_post_meta( $booking_id, '_booking_vehicle_id', true );
			$vehicle_title = $vehicle_id ? get_the_title( (int) $vehicle_id ) : __( 'Unknown Vehicle', 'mhm-rentiva' );

			// Try both meta key formats for dates
			$pickup_date  = get_post_meta( $booking_id, '_mhm_pickup_date', true ) ?:
							get_post_meta( $booking_id, '_booking_pickup_date', true );
			$dropoff_date = get_post_meta( $booking_id, '_mhm_dropoff_date', true ) ?:
							get_post_meta( $booking_id, '_booking_dropoff_date', true );

			// Booking ID display format: #POST_ID (same as admin list)
			$booking_id_display = '#' . $booking_id;

			// Format dates
			$pickup_formatted  = $pickup_date ? date_i18n( get_option( 'date_format' ), strtotime( $pickup_date ) ) : '';
			$dropoff_formatted = $dropoff_date ? date_i18n( get_option( 'date_format' ), strtotime( $dropoff_date ) ) : '';

			$formatted_bookings[] = array(
				'id'           => $booking_id,
				'booking_id'   => $booking_id_display,
				'vehicle'      => $vehicle_title,
				'pickup_date'  => $pickup_formatted,
				'dropoff_date' => $dropoff_formatted,
				'label'        => sprintf(
					/* translators: 1: %1$s; 2: %2$s; 3: %3$s; 4: %4$s. */
					__( '%1$s - %2$s (%3$s to %4$s)', 'mhm-rentiva' ),
					$booking_id_display,
					$vehicle_title,
					$pickup_formatted,
					$dropoff_formatted
				),
			);
		}

		return new WP_REST_Response(
			array(
				'bookings' => $formatted_bookings,
			),
			200
		);
	}

	/**
	 * Get user bookings (same logic as AccountRenderer::get_user_bookings)
	 */
	private static function get_user_bookings( int $user_id, array $args = array() ): array {
		$defaults = array(
			'limit'   => 50, // Get more bookings for dropdown
			'status'  => '',
			'orderby' => 'date',
			'order'   => 'DESC',
		);

		$args = wp_parse_args( $args, $defaults );

		// Meta query
		$meta_query = array(
			array(
				'key'     => '_mhm_customer_user_id',
				'value'   => $user_id,
				'compare' => '=',
			),
		);

		// Status filter - only show active bookings (not cancelled)
		$meta_query[] = array(
			'key'     => '_mhm_status',
			'value'   => 'cancelled',
			'compare' => '!=',
		);

		$query_args = array(
			'post_type'      => 'vehicle_booking',
			'post_status'    => 'publish',
			'posts_per_page' => (int) $args['limit'],
			'orderby'        => sanitize_text_field( $args['orderby'] ),
			'order'          => sanitize_text_field( $args['order'] ),
			'meta_query'     => $meta_query,
		);

		$query = new \WP_Query( $query_args );

		return $query->posts;
	}
}
