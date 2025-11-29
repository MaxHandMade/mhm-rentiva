<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Emails\Core;

use MHMRentiva\Admin\Core\Utilities\BookingQueryHelper;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Booking Query Helper Adapter
 * 
 * Adapter class that implements BookingDataProviderInterface
 * using BookingQueryHelper. This provides loose coupling.
 * 
 * @since 4.0.0
 */
final class BookingQueryHelperAdapter implements BookingDataProviderInterface
{
    /**
     * Get booking customer information
     * 
     * @param int $booking_id Booking ID
     * @return array<string, mixed> Customer information
     */
    public function getBookingCustomerInfo(int $booking_id): array
    {
        return BookingQueryHelper::getBookingCustomerInfo($booking_id);
    }

    /**
     * Get booking vehicle information
     * 
     * @param int $booking_id Booking ID
     * @return array<string, mixed> Vehicle information
     */
    public function getBookingVehicleInfo(int $booking_id): array
    {
        return BookingQueryHelper::getBookingVehicleInfo($booking_id);
    }

    /**
     * Get booking date information
     * 
     * @param int $booking_id Booking ID
     * @return array<string, mixed> Date information
     */
    public function getBookingDateInfo(int $booking_id): array
    {
        return BookingQueryHelper::getBookingDateInfo($booking_id);
    }

    /**
     * Get booking payment status
     * 
     * @param int $booking_id Booking ID
     * @return string Payment status
     */
    public function getBookingPaymentStatus(int $booking_id): string
    {
        return BookingQueryHelper::getBookingPaymentStatus($booking_id);
    }

    /**
     * Get booking payment gateway
     * 
     * @param int $booking_id Booking ID
     * @return string Payment gateway
     */
    public function getBookingPaymentGateway(int $booking_id): string
    {
        return BookingQueryHelper::getBookingPaymentGateway($booking_id);
    }

    /**
     * Get booking total price
     * 
     * @param int $booking_id Booking ID
     * @return float Total price
     */
    public function getBookingTotalPrice(int $booking_id): float
    {
        return BookingQueryHelper::getBookingTotalPrice($booking_id);
    }
}

