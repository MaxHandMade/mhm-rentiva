<?php declare(strict_types=1);

namespace MHMRentiva\REST\PayTR\Helpers;

use MHMRentiva\Admin\Core\Utilities\BookingQueryHelper;

if (!defined('ABSPATH')) {
    exit;
}

final class BookingQuery
{
    /**
     * PayTR merchant OID ile booking bulma
     * 
     * Merkezi BookingQueryHelper sınıfını kullanarak
     * PayTR-specific booking sorgusu yapar.
     * 
     * @param string $oid PayTR merchant OID
     * @return int Booking ID (0 = bulunamadı)
     */
    public static function findBookingByOid(string $oid): int
    {
        return BookingQueryHelper::findBookingByPayTROid($oid);
    }
    
    /**
     * PayTR booking bilgilerini al
     * 
     * @param string $oid PayTR merchant OID
     * @return array Booking information
     */
    public static function getBookingInfo(string $oid): array
    {
        $booking_id = self::findBookingByOid($oid);
        
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
            ]
        ];
    }
}
