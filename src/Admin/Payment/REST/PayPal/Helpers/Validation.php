<?php declare(strict_types=1);

namespace MHMRentiva\REST\PayPal\Helpers;

use MHMRentiva\Admin\REST\Helpers\ValidationHelper;

if (!defined('ABSPATH')) {
    exit;
}

final class Validation
{
    /**
     * PayPal için rezervasyon ödeme validasyonu
     */
    public static function validateBookingForPayment(int $booking_id): bool
    {
        return ValidationHelper::validateBookingForPayment(
            $booking_id,
            'vehicle_booking',
            '_mhm_payment_status',
            ['unpaid', 'failed', '']
        );
    }

    /**
     * PayPal için detaylı rezervasyon validasyonu
     */
    public static function validateBookingDetailed(int $booking_id): array
    {
        return ValidationHelper::validateBookingDetailed(
            $booking_id,
            'vehicle_booking',
            '_mhm_status',
            '_mhm_payment_status',
            ['pending', 'confirmed'],
            ['paid', 'refunded']
        );
    }

    /**
     * Client IP adresini alır
     */
    public static function getClientIp(): string
    {
        return ValidationHelper::getClientIp();
    }
}
