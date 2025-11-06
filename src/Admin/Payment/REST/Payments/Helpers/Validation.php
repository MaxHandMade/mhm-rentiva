<?php declare(strict_types=1);

namespace MHMRentiva\REST\Payments\Helpers;

use MHMRentiva\Admin\REST\Helpers\ValidationHelper;

if (!defined('ABSPATH')) {
    exit;
}

final class Validation
{
    /**
     * Genel ödeme için rezervasyon validasyonu
     */
    public static function validateBookingForPayment(int $booking_id): array
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
     * İade için rezervasyon validasyonu (Stripe payment intent kontrolü ile)
     */
    public static function validateBookingForRefund(int $booking_id): array
    {
        // Önce temel validasyon
        $basicValidation = ValidationHelper::validateBookingForRefund(
            $booking_id,
            'vehicle_booking',
            '_mhm_payment_status'
        );
        
        if (!$basicValidation['valid']) {
            return $basicValidation;
        }

        // Stripe payment intent kontrolü
        $paymentIntent = (string) get_post_meta($booking_id, '_mhm_stripe_payment_intent', true);
        if (empty($paymentIntent)) {
            return ['valid' => false, 'error' => 'no_payment_intent'];
        }

        return ['valid' => true];
    }
}
