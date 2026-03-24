<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Emails\Core;

if (!defined('ABSPATH')) {
    exit;
}

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Booking Data Provider Interface
 *
 * Defines the contract for providing booking data to email templates.
 * This allows loose coupling and better testability.
 *
 * @since 4.0.0
 */
interface BookingDataProviderInterface {

	/**
	 * Get booking customer information
	 *
	 * @param int $booking_id Booking ID
	 * @return array<string, mixed> Customer information
	 */
	public function getBookingCustomerInfo( int $booking_id ): array;

	/**
	 * Get booking vehicle information
	 *
	 * @param int $booking_id Booking ID
	 * @return array<string, mixed> Vehicle information
	 */
	public function getBookingVehicleInfo( int $booking_id ): array;

	/**
	 * Get booking date information
	 *
	 * @param int $booking_id Booking ID
	 * @return array<string, mixed> Date information
	 */
	public function getBookingDateInfo( int $booking_id ): array;

	/**
	 * Get booking payment status
	 *
	 * @param int $booking_id Booking ID
	 * @return string Payment status
	 */
	public function getBookingPaymentStatus( int $booking_id ): string;

	/**
	 * Get booking payment gateway
	 *
	 * @param int $booking_id Booking ID
	 * @return string Payment gateway
	 */
	public function getBookingPaymentGateway( int $booking_id ): string;

	/**
	 * Get booking total price
	 *
	 * @param int $booking_id Booking ID
	 * @return float Total price
	 */
	public function getBookingTotalPrice( int $booking_id ): float;
}
