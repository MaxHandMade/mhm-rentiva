<?php declare(strict_types=1);

namespace MHMRentiva\REST\PayTR\Helpers;

use MHMRentiva\Admin\REST\Helpers\ValidationHelper;

if (!defined('ABSPATH')) {
    exit;
}

final class Validation
{
    /**
     * PayTR için client IP alır
     */
    public static function clientIp(): string
    {
        return ValidationHelper::getClientIp();
    }

    /**
     * PayTR için rezervasyon token validasyonu
     */
    public static function validateBookingForToken(int $booking_id): bool
    {
        return ValidationHelper::validateBookingForPayment(
            $booking_id,
            'vehicle_booking',
            '_mhm_payment_status',
            ['', 'unpaid', 'failed']
        );
    }

    /**
     * PayTR için detaylı rezervasyon validasyonu
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
}
