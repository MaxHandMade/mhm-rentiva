<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Transfer\Integration;

use MHMRentiva\Admin\Transfer\Engine\TransferSearchEngine;
use MHMRentiva\Admin\Payment\WooCommerce\WooCommerceBridge;
use MHMRentiva\Admin\Booking\Helpers\Util;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TransferCartIntegration {



	/**
	 * Register hooks
	 */
	public static function register(): void {
		// AJAX Handlers
		add_action( 'wp_ajax_mhm_transfer_add_to_cart', array( self::class, 'handle_add_to_cart_ajax' ) );
		add_action( 'wp_ajax_nopriv_mhm_transfer_add_to_cart', array( self::class, 'handle_add_to_cart_ajax' ) );

		// 1. CRITICAL: Restore Transfer Data from Session
		add_filter( 'woocommerce_get_cart_item_from_session', array( self::class, 'get_cart_item_from_session' ), 20, 2 );

		// 2. Customize Cart Item Name
		add_filter( 'woocommerce_cart_item_name', array( self::class, 'customize_cart_item_name' ), 20, 3 );

		// 3. Customize Cart Item Data (Meta)
		add_filter( 'woocommerce_get_item_data', array( self::class, 'customize_cart_item_data' ), 20, 2 );

		// 4. Customize Order Item for Transfers (Checkout)
		add_action( 'woocommerce_checkout_create_order_line_item', array( self::class, 'add_transfer_order_item_meta' ), 20, 4 );
	}

	/**
	 * Add Transfer Meta to Order Item
	 */
	public static function add_transfer_order_item_meta( $item, $cart_item_key, $values, $order ): void {
		// Check using normalized mhm_booking_data
		$booking_data = $values['mhm_booking_data'] ?? array();

		if ( ! isset( $booking_data['booking_type'] ) || $booking_data['booking_type'] !== 'transfer' ) {
			return;
		}

		// Rename Item
		$origin_name      = self::get_location_name( intval( $booking_data['transfer_origin_id'] ?? 0 ) );
		$destination_name = self::get_location_name( intval( $booking_data['transfer_destination_id'] ?? 0 ) );

		if ( $origin_name && $destination_name ) {
			/* translators: 1: origin location, 2: destination location */
			$item->set_name( sprintf( __( 'VIP Transfer: %1$s ➝ %2$s', 'mhm-rentiva' ), $origin_name, $destination_name ) );
		}

		// Add Meta
		$distance_km  = $booking_data['transfer_distance_km'] ?? 0;
		$duration_min = $booking_data['transfer_duration_min'] ?? 0;

		if ( $distance_km ) {
			$item->add_meta_data( __( 'Distance', 'mhm-rentiva' ), $distance_km . ' km' );
		}
		if ( $duration_min ) {
			$item->add_meta_data( __( 'Duration', 'mhm-rentiva' ), $duration_min . ' min' );
		}
	}

	/**
	 * Handle Add to Cart AJAX
	 */
	public static function handle_add_to_cart_ajax(): void {
		// 1. Validate Nonce
		check_ajax_referer( 'mhm_rentiva_transfer_nonce', 'security' );

		// Unpack Input Data (Handle nested transfer_data from JS object)
		$input_data = isset( $_POST['transfer_data'] ) && is_array( $_POST['transfer_data'] ) ? $_POST['transfer_data'] : $_POST;

		$vehicle_id     = intval( $_POST['vehicle_id'] ?? $input_data['vehicle_id'] ?? 0 );
		$origin_id      = intval( $input_data['origin_id'] ?? 0 );
		$destination_id = intval( $input_data['destination_id'] ?? 0 );
		$date           = sanitize_text_field( $input_data['date'] ?? '' );
		$time           = sanitize_text_field( $input_data['time'] ?? '' );
		$adults         = intval( $input_data['adults'] ?? 1 );
		$children       = intval( $input_data['children'] ?? 0 );
		$luggage_big    = intval( $input_data['luggage_big'] ?? 0 );
		$luggage_small  = intval( $input_data['luggage_small'] ?? 0 );

		// 2. Validate Vehicle Exists
		$vehicle_post = get_post( $vehicle_id );
		if ( ! $vehicle_post || $vehicle_post->post_type !== 'vehicle' ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Vehicle ID not found.', 'mhm-rentiva' ) ) );
			return;
		}

		// 3. Get Price & Duration (Relaxed Validation)
		// If frontend provided trusted data (price/duration), use it.
		$selected_price    = isset( $input_data['price'] ) ? floatval( $input_data['price'] ) : null;
		$selected_duration = isset( $input_data['duration'] ) ? intval( $input_data['duration'] ) : null;
		$selected_distance = isset( $input_data['distance'] ) ? floatval( $input_data['distance'] ) : null;

		// SANITY CHECK: Verify Price if coming from Frontend
		if ( $selected_price !== null && $selected_distance !== null ) {
			$price_per_km = (float) get_post_meta( $vehicle_id, '_mhm_vehicle_price_per_km', true );
			$base_price   = (float) get_post_meta( $vehicle_id, '_mhm_vehicle_base_price', true );

			// Only verify if we have a valid KM price configuration
			if ( $price_per_km > 0 ) {
				$server_calculated_price = $base_price + ( $selected_distance * $price_per_km );

				// 5.0 Tolerance for Tax/Rounding
				if ( abs( $server_calculated_price - $selected_price ) > 5.0 ) {
					error_log( "MHM Security Alert: Price Mismatch. Client: $selected_price, Server: $server_calculated_price, Dist: $selected_distance" );
					wp_send_json_error( array( 'message' => esc_html__( 'Security Alert: Price could not be verified. Please refresh.', 'mhm-rentiva' ) ) );
					return;
				}
			}
		}

		// Fallback: If data missing (e.g. old cache), run strict search
		if ( $selected_price === null || $selected_duration === null ) {
			$criteria = array(
				'origin_id'      => $origin_id,
				'destination_id' => $destination_id,
				'date'           => $date,
				'time'           => $time,
				'adults'         => $adults,
				'children'       => $children,
				'luggage_big'    => $luggage_big,
				'luggage_small'  => $luggage_small,
			);

			$results = TransferSearchEngine::search( $criteria );
			$found   = false;
			foreach ( $results as $res ) {
				if ( $res['id'] === $vehicle_id ) {
					$selected_price    = (float) $res['price'];
					$selected_duration = (int) $res['duration'];
					$selected_distance = (float) $res['distance'];
					$found             = true;
					break;
				}
			}

			if ( ! $found ) {
				wp_send_json_error( array( 'message' => esc_html__( 'Vehicle not available or price changed. Please search again.', 'mhm-rentiva' ) ) );
				return;
			}
		}

		// 4. Financial Calculation (Deposit vs Full)
		$deposit_type = \MHMRentiva\Admin\Settings\Core\SettingsCore::get( 'mhm_transfer_deposit_type', 'full_payment' );
		$deposit_rate = (int) \MHMRentiva\Admin\Settings\Core\SettingsCore::get( 'mhm_transfer_deposit_rate', '20' );
		$total_price  = (float) $selected_price;

		$deposit_amount   = 0.0;
		$remaining_amount = 0.0;
		$payment_type     = 'full';

		if ( $deposit_type === 'percentage' ) {
			$deposit_amount   = ( $total_price * $deposit_rate ) / 100;
			$remaining_amount = $total_price - $deposit_amount;
			$payment_type     = 'deposit';
		} else {
			// Full Payment
			$deposit_amount   = $total_price;
			$remaining_amount = 0;
			$payment_type     = 'full';
		}

		// 5. Prepare Booking Data
		$timezone = wp_timezone();
		try {
			// Date formatting
			$start_datetime = new \DateTimeImmutable( "$date $time", $timezone );
			// $selected_duration is in minutes
			$end_datetime = $start_datetime->modify( "+{$selected_duration} minutes" );

			$dropoff_date = $end_datetime->format( 'Y-m-d' );
			$dropoff_time = $end_datetime->format( 'H:i' );
		} catch ( \Exception $e ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid date time.', 'mhm-rentiva' ) ) );
			return;
		}

		$booking_data = array(
			'vehicle_id'              => $vehicle_id,
			'pickup_date'             => $date,
			'pickup_time'             => $time,
			'dropoff_date'            => $dropoff_date,
			'dropoff_time'            => $dropoff_time,
			'guests'                  => ( $adults + $children ),
			'customer_user_id'        => get_current_user_id(),
			// Customer details will be filled by WooCommerce Checkout
			'customer_name'           => '',
			'customer_first_name'     => '',
			'customer_last_name'      => '',
			'customer_email'          => '',
			'customer_phone'          => '',

			// Financials
			'total_price'             => $total_price,
			'deposit_amount'          => $deposit_amount,
			'remaining_amount'        => $remaining_amount,
			'payment_type'            => $payment_type, // 'deposit' or 'full'
			'payment_display'         => ( $payment_type === 'deposit' ) ?
				/* translators: 1: deposit amount, 2: deposit percentage */
				sprintf( __( 'Deposit: %1$s (%2$s%%)', 'mhm-rentiva' ), wp_kses_post( wc_price( $deposit_amount ) ), $deposit_rate ) :
				__( 'Full Payment', 'mhm-rentiva' ),
			'pay_now_price'           => $deposit_amount, // Important helper

			'rental_days'             => 1, // Transfer is conceptually 1 unit
			'selected_addons'         => array(),
			'booking_type'            => 'transfer', // Distinct from 'rental'

			// Extra Transfer Meta
			'transfer_origin_id'      => $origin_id,
			'transfer_destination_id' => $destination_id,
			'transfer_adults'         => $adults,
			'transfer_children'       => $children,
			'transfer_luggage_big'    => $luggage_big,
			'transfer_luggage_small'  => $luggage_small,
			'transfer_distance_km'    => $selected_distance ?: 0,
			'transfer_duration_min'   => $selected_duration ?: 0,
		);

		// 6. Add to Cart via Bridge
		if ( WooCommerceBridge::add_booking_data_to_cart( $booking_data, $deposit_amount ) ) {
			wp_send_json_success(
				array(
					'redirect_url' => function_exists( 'wc_get_cart_url' ) ? call_user_func( 'wc_get_cart_url' ) : '/',
					'message'      => esc_html__( 'Transfer added to cart successfully.', 'mhm-rentiva' ),
				)
			);
		} else {
			wp_send_json_error( array( 'message' => esc_html__( 'Failed to add to cart.', 'mhm-rentiva' ) ) );
		}
	}

	/**
	 * Restore Transfer Data from Session
	 */
	public static function get_cart_item_from_session( $cart_item, $values ) {
		// Bridge already restores mhm_booking_data. We verify transfer type.
		if ( isset( $values['mhm_booking_data']['booking_type'] ) && $values['mhm_booking_data']['booking_type'] === 'transfer' ) {
			$cart_item['is_transfer_booking'] = true;
		}
		return $cart_item;
	}

	/**
	 * Helper: Get Location Name from Custom Table
	 */
	private static function get_location_name( int $id ): string {
		if ( $id <= 0 ) {
			return __( 'Unknown Location', 'mhm-rentiva' );
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'mhm_rentiva_transfer_locations';

		// Cache could be added here if needed, but for now direct query is fine for cart
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Table name is safe (prefix + constant).
		$name = $wpdb->get_var( $wpdb->prepare( "SELECT name FROM $table_name WHERE id = %d", $id ) );

		return $name ? $name : __( 'Unknown Location', 'mhm-rentiva' );
	}

	/**
	 * Customize Cart Item Name
	 */
	public static function customize_cart_item_name( $name, $cart_item, $cart_item_key ) {
		$booking_data = $cart_item['mhm_booking_data'] ?? array();

		// CHECK: Is this a transfer booking?
		if ( isset( $booking_data['booking_type'] ) && $booking_data['booking_type'] === 'transfer' ) {

			$origin_id      = intval( $booking_data['transfer_origin_id'] ?? 0 );
			$destination_id = intval( $booking_data['transfer_destination_id'] ?? 0 );

			// USE CUSTOM HELPER instead of get_the_title
			$origin_name      = self::get_location_name( $origin_id );
			$destination_name = self::get_location_name( $destination_id );

			// New Format: VIP Transfer: Origin -> Destination
			/* translators: 1: Origin Name, 2: Destination Name */
			$new_name = sprintf( __( 'VIP Transfer: %1$s ➝ %2$s', 'mhm-rentiva' ), $origin_name, $destination_name );

			// UX: Return plain text, do NOT link to the generic product page
			return $new_name;
		}
		return $name;
	}

	/**
	 * Customize Cart Item Meta Data
	 */
	public static function customize_cart_item_data( $item_data, $cart_item ) {
		$booking_data = $cart_item['mhm_booking_data'] ?? array();

		// CHECK: Is this a transfer booking?
		if ( ! isset( $booking_data['booking_type'] ) || $booking_data['booking_type'] !== 'transfer' ) {
			return $item_data;
		}

		$new_data        = array();
		$vehicle_exists  = false;
		$duration_exists = false;

		foreach ( $item_data as $data ) {
			$key = $data['key'];

			// 1. HIDE: Return Date, End Date etc.
			if ( preg_match( '/(Return|End Date)/i', $key ) ) {
				continue;
			}

			// 2. FIX: Duration -> Estimated Duration
			if ( $key === __( 'Duration', 'mhm-rentiva' ) || $key === 'Duration' ) {
				$duration_exists = true;
				$duration_min    = $booking_data['transfer_duration_min'] ?? 0;

				$data['key']     = __( 'Estimated Duration', 'mhm-rentiva' );
				$data['display'] = $duration_min . ' ' . __( 'min', 'mhm-rentiva' );
				$data['value']   = $duration_min . ' ' . __( 'min', 'mhm-rentiva' );
			}

			// Check if vehicle is already added
			if ( $key === __( 'Vehicle', 'mhm-rentiva' ) ) {
				$vehicle_exists = true;
			}

			$new_data[] = $data;
		}

		// 3. ADD: Distance
		$distance_km = $booking_data['transfer_distance_km'] ?? 0;
		$new_data[]  = array(
			'key'     => __( 'Distance', 'mhm-rentiva' ),
			'display' => $distance_km . ' km',
			'value'   => $distance_km . ' km',
		);

		// 4. ADD: Duration (If not already present)
		$duration_min = $booking_data['transfer_duration_min'] ?? 0;
		if ( ! $duration_exists && $duration_min > 0 ) {
			$new_data[] = array(
				'key'     => __( 'Estimated Duration', 'mhm-rentiva' ),
				'display' => $duration_min . ' ' . __( 'min', 'mhm-rentiva' ),
				'value'   => $duration_min . ' ' . __( 'min', 'mhm-rentiva' ),
			);
		}

		// 4. ADD: Vehicle Name (Only if not already added by bridge)
		if ( ! $vehicle_exists && isset( $booking_data['vehicle_id'] ) ) {
			$new_data[] = array(
				'key'     => __( 'Vehicle', 'mhm-rentiva' ),
				'display' => get_the_title( $booking_data['vehicle_id'] ),
				'value'   => get_the_title( $booking_data['vehicle_id'] ),
			);
		}

		// 5. ADD: Payment Type (Ensuring Consistency)
		$payment_type_exists = false;
		foreach ( $new_data as $data ) {
			if ( $data['key'] === __( 'Payment Type', 'mhm-rentiva' ) ) {
				$payment_type_exists = true;
				break;
			}
		}

		if ( ! $payment_type_exists ) {
			$payment_type = $booking_data['payment_type'] ?? 'full';
			$type_label   = $payment_type === 'deposit'
				? __( 'Deposit Payment', 'mhm-rentiva' )
				: __( 'Full Payment', 'mhm-rentiva' );

			$new_data[] = array(
				'key'     => __( 'Payment Type', 'mhm-rentiva' ),
				'display' => $type_label,
				'value'   => $type_label,
			);
		}

		return $new_data;
	}
}
