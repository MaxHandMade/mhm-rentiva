<?php declare(strict_types=1);

namespace MHMRentiva\REST\StripeWebhook\Helpers;

use MHMRentiva\Admin\Core\Utilities\BookingQueryHelper;

if (!defined('ABSPATH')) {
    exit;
}

final class BookingQuery
{
    /**
     * Stripe payment intent ile booking bulma
     * 
     * Merkezi BookingQueryHelper sınıfını kullanarak
     * Stripe-specific booking sorgusu yapar.
     * 
     * @param string $intent Stripe payment intent ID
     * @return int Booking ID (0 = bulunamadı)
     */
    public static function findBookingByIntent(string $intent): int
    {
        return BookingQueryHelper::findBookingByStripeIntent($intent);
    }
    
    /**
     * Stripe booking bilgilerini al
     * 
     * @param string $intent Stripe payment intent ID
     * @return array Booking information
     */
    public static function getBookingInfo(string $intent): array
    {
        $booking_id = self::findBookingByIntent($intent);
        
        if ($booking_id <= 0) {
            return [];
        }

        return [
            'booking_id' => $booking_id,
            'customer' => BookingQueryHelper::getBookingCustomerInfo($booking_id),
            'vehicle' => BookingQueryHelper::getBookingVehicleInfo($booking_id),
            'dates' => BookingQueryHelper::getBookingDateInfo($booking_id),
            'payment' => [
                'status' => BookingQueryHelper::getBookingPaymentStatus($booking_id),
                'gateway' => BookingQueryHelper::getBookingPaymentGateway($booking_id),
                'total' => BookingQueryHelper::getBookingTotalPrice($booking_id),
                'intent' => $intent,
            ]
        ];
    }
    
    /**
     * Stripe payment intent durumunu güncelle
     * 
     * @param string $intent Stripe payment intent ID
     * @param string $status Payment status
     * @param array $additional_data Additional payment data
     * @return bool Success status
     */
    public static function updatePaymentStatus(string $intent, string $status, array $additional_data = []): bool
    {
        $booking_id = self::findBookingByIntent($intent);
        
        if ($booking_id <= 0) {
            return false;
        }

        // Payment status güncelle
        update_post_meta($booking_id, '_booking_payment_status', $status);
        update_post_meta($booking_id, '_mhm_payment_status', $status);

        // Additional data güncelle
        foreach ($additional_data as $key => $value) {
            update_post_meta($booking_id, '_booking_' . $key, $value);
        }

        // Booking status güncelle (payment durumuna göre)
        $booking_status = 'pending';
        if ($status === 'paid') {
            $booking_status = 'confirmed';
        } elseif ($status === 'failed' || $status === 'cancelled') {
            $booking_status = 'cancelled';
        }

        update_post_meta($booking_id, '_booking_status', $booking_status);
        update_post_meta($booking_id, '_mhm_status', $booking_status);

        return true;
    }
}
