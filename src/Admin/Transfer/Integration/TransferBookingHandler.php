<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Transfer\Integration;

if (!defined('ABSPATH')) {
    exit;
}

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TransferBookingHandler {

	/**
	 * Register hooks
	 */
	public static function register(): void {
		add_action( 'mhm_rentiva_booking_created', array( self::class, 'save_transfer_booking_meta' ), 10, 2 );
	}

	/**
	 * Save transfer booking meta when a booking is created
	 *
	 * @param int   $booking_id The created booking ID
	 * @param array $booking_data The data package from cart/session
	 */
	public static function save_transfer_booking_meta( int $booking_id, array $booking_data ): void {
		// Only process if this is a transfer booking
		if ( ! isset( $booking_data['booking_type'] ) || $booking_data['booking_type'] !== 'transfer' ) {
			return;
		}

		// List of transfer specific keys to save
		$transfer_keys = array(
			'transfer_origin_id',
			'transfer_destination_id',
			'transfer_adults',
			'transfer_children',
			'transfer_luggage_big',
			'transfer_luggage_small',
			'transfer_distance_km',
			'transfer_duration_min',
		);

		foreach ( $transfer_keys as $key ) {
			if ( isset( $booking_data[ $key ] ) ) {
				update_post_meta( $booking_id, '_mhm_' . $key, $booking_data[ $key ] );
			}
		}

		// v4.36.0 — persist addon details on booking post.
		if ( ! empty( $booking_data['selected_addons'] ) && is_array( $booking_data['selected_addons'] ) ) {
			update_post_meta( $booking_id, '_mhm_selected_addons', $booking_data['selected_addons'] );
			update_post_meta( $booking_id, '_mhm_addon_details', $booking_data['addon_details'] ?? array() );
			update_post_meta( $booking_id, '_mhm_addon_total', (float) ( $booking_data['addon_total'] ?? 0 ) );
		}
	}
}
