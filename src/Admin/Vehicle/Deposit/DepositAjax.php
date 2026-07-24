<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Vehicle\Deposit;

if (!defined('ABSPATH')) {
    exit;
}

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Deposit AJAX Handler
 *
 * This class manages AJAX requests for deposit calculations.
 */
class DepositAjax {

	/**
	 * Safe sanitize text field that handles null values
	 */
	public static function sanitize_text_field_safe( $value ) {
		if ( $value === null || $value === '' ) {
			return '';
		}
		return sanitize_text_field( (string) $value );
	}

	/**
	 * Register AJAX handlers
	 */
	public static function register(): void {
		add_action( 'wp_ajax_mhm_rentiva_calculate_deposit', array( self::class, 'ajax_calculate_booking_deposit' ) );
		add_action( 'wp_ajax_nopriv_mhm_rentiva_calculate_deposit', array( self::class, 'ajax_calculate_booking_deposit' ) );
	}

	// Removed: ajax_calculate_vehicle_deposit(). It was never registered on any wp_ajax_*
	// hook and its nonce action ('mhm_vehicle_deposit_calculation') was referenced nowhere
	// else in PHP or JS -- dead code. The live deposit endpoint is
	// ajax_calculate_booking_deposit() below.

	/**
	 * Booking deposit calculation AJAX handler
	 */
	public static function ajax_calculate_booking_deposit(): void {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['nonce'] ) ) : '';
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'mhm_rentiva_booking_action' ) ) {
			wp_send_json_error( __( 'Security error', 'mhm-rentiva' ) );
		}

		$vehicle_id   = isset( $_POST['vehicle_id'] ) ? absint( sanitize_text_field( wp_unslash( (string) $_POST['vehicle_id'] ) ) ) : 0;
		$rental_days  = isset( $_POST['rental_days'] ) ? absint( sanitize_text_field( wp_unslash( (string) $_POST['rental_days'] ) ) ) : 1;
		$payment_type = isset( $_POST['payment_type'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['payment_type'] ) ) : 'deposit';
		$addons       = isset( $_POST['addons'] ) && is_array( $_POST['addons'] ) ? array_map( 'intval', wp_unslash( $_POST['addons'] ) ) : array();

		if ( $vehicle_id <= 0 ) {
			wp_send_json_error( __( 'Invalid vehicle ID', 'mhm-rentiva' ) );
		}

		if ( $rental_days <= 0 ) {
			wp_send_json_error( __( 'Invalid rental days', 'mhm-rentiva' ) );
		}

		// ⭐ SAFETY CHECK: Force Full Payment if Deposit field is removed/empty
		$deposit_meta = get_post_meta( $vehicle_id, '_mhm_rentiva_deposit', true );
		if ( empty( $deposit_meta ) ) {
			$payment_type = 'full';
		}

		if ( ! DepositCalculator::validate_payment_type( $payment_type ) ) {
			wp_send_json_error( __( 'Invalid payment type', 'mhm-rentiva' ) );
		}

		$result = DepositCalculator::calculate_booking_deposit( $vehicle_id, $rental_days, $payment_type, $addons );

		if ( ! $result['success'] ) {
			wp_send_json_error( $result['error'] );
		}

		$vehicle = get_post( $vehicle_id );
		if ( $vehicle ) {
			$result['vehicle_name'] = $vehicle->post_title;
			$result['vehicle_id']   = $vehicle_id;
		}

		wp_send_json_success( $result );
	}
}
