<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Payment\Refunds;

use WP_Post;

if (!defined('ABSPATH')) {
    exit;
}

final class RefundValidator
{
    /**
     * Validates booking for refund
     */
    public static function validateBooking(int $bookingId): array
    {
        if ($bookingId <= 0) {
            return [
                'valid' => false,
                'message' => __('Invalid booking ID', 'mhm-rentiva')
            ];
        }

        $post = get_post($bookingId);
        if (!$post || $post->post_type !== 'vehicle_booking') {
            return [
                'valid' => false,
                'message' => __('Invalid booking type', 'mhm-rentiva')
            ];
        }

        return [
            'valid' => true,
            'booking' => $post
        ];
    }

    /**
     * Validates payment gateway
     */
    public static function validateGateway(string $gateway): array
    {
        $supportedGateways = ['paytr', 'stripe', 'paypal'];
        
        if (!in_array($gateway, $supportedGateways, true)) {
            return [
                'valid' => false,
                'message' => __('Unsupported payment method for refund', 'mhm-rentiva')
            ];
        }

        return [
            'valid' => true,
            'gateway' => $gateway
        ];
    }

    /**
     * Validates refund amount
     */
    public static function validateAmount(int $bookingId, int $amountKurus): array
    {
        return RefundCalculator::validateRefundAmount($bookingId, $amountKurus);
    }

    /**
     * Checks payment status
     */
    public static function validatePaymentStatus(int $bookingId): array
    {
        $paymentStatus = (string) get_post_meta($bookingId, '_mhm_payment_status', true);
        
        if (empty($paymentStatus)) {
            return [
                'valid' => false,
                'message' => __('Payment status not found', 'mhm-rentiva')
            ];
        }

        if ($paymentStatus === 'pending') {
            return [
                'valid' => false,
                'message' => __('Pending payments cannot be refunded', 'mhm-rentiva')
            ];
        }

        if ($paymentStatus === 'failed') {
            return [
                'valid' => false,
                'message' => __('Failed payments cannot be refunded', 'mhm-rentiva')
            ];
        }

        if ($paymentStatus === 'refunded') {
            return [
                'valid' => false,
                'message' => __('Already fully refunded', 'mhm-rentiva')
            ];
        }

        return [
            'valid' => true,
            'status' => $paymentStatus
        ];
    }

    /**
     * Performs gateway-specific validation
     */
    public static function validateGatewaySpecific(int $bookingId, string $gateway): array
    {
        switch ($gateway) {
            case 'paytr':
                $merchantOid = (string) get_post_meta($bookingId, '_mhm_paytr_merchant_oid', true);
                if (empty($merchantOid)) {
                    return [
                        'valid' => false,
                        'message' => __('PayTR merchant OID missing', 'mhm-rentiva')
                    ];
                }
                break;

            case 'stripe':
                $paymentIntent = (string) get_post_meta($bookingId, '_mhm_stripe_payment_intent', true);
                $chargeId = (string) get_post_meta($bookingId, '_mhm_stripe_charge_id', true);
                if (empty($paymentIntent) && empty($chargeId)) {
                    return [
                        'valid' => false,
                        'message' => __('Stripe payment identifiers missing', 'mhm-rentiva')
                    ];
                }
                break;

            case 'paypal':
                $captureId = (string) get_post_meta($bookingId, '_mhm_paypal_payment_id', true);
                if (empty($captureId)) {
                    return [
                        'valid' => false,
                        'message' => __('PayPal capture ID missing', 'mhm-rentiva')
                    ];
                }
                break;
        }

        return [
            'valid' => true,
            'gateway' => $gateway
        ];
    }

    /**
     * Performs full refund validation
     */
    public static function validateFullRefund(int $bookingId): array
    {
        // Booking validation
        $bookingValidation = self::validateBooking($bookingId);
        if (!$bookingValidation['valid']) {
            return $bookingValidation;
        }

        // Payment status validation
        $statusValidation = self::validatePaymentStatus($bookingId);
        if (!$statusValidation['valid']) {
            return $statusValidation;
        }

        // Gateway validation
        $gateway = (string) get_post_meta($bookingId, '_mhm_payment_gateway', true);
        $gatewayValidation = self::validateGateway($gateway);
        if (!$gatewayValidation['valid']) {
            return $gatewayValidation;
        }

        // Gateway-specific validation
        $gatewaySpecificValidation = self::validateGatewaySpecific($bookingId, $gateway);
        if (!$gatewaySpecificValidation['valid']) {
            return $gatewaySpecificValidation;
        }

        // Amount validation (for full refund)
        $amountValidation = self::validateAmount($bookingId, 0); // 0 = tam iade
        if (!$amountValidation['valid']) {
            return $amountValidation;
        }

        return [
            'valid' => true,
            'booking_id' => $bookingId,
            'gateway' => $gateway,
            'amount' => $amountValidation['remaining']
        ];
    }

    /**
     * Performs partial refund validation
     */
    public static function validatePartialRefund(int $bookingId, int $amountKurus): array
    {
        // Booking validation
        $bookingValidation = self::validateBooking($bookingId);
        if (!$bookingValidation['valid']) {
            return $bookingValidation;
        }

        // Payment status validation
        $statusValidation = self::validatePaymentStatus($bookingId);
        if (!$statusValidation['valid']) {
            return $statusValidation;
        }

        // Gateway validation
        $gateway = (string) get_post_meta($bookingId, '_mhm_payment_gateway', true);
        $gatewayValidation = self::validateGateway($gateway);
        if (!$gatewayValidation['valid']) {
            return $gatewayValidation;
        }

        // Gateway-specific validation
        $gatewaySpecificValidation = self::validateGatewaySpecific($bookingId, $gateway);
        if (!$gatewaySpecificValidation['valid']) {
            return $gatewaySpecificValidation;
        }

        // Amount validation
        $amountValidation = self::validateAmount($bookingId, $amountKurus);
        if (!$amountValidation['valid']) {
            return $amountValidation;
        }

        return [
            'valid' => true,
            'booking_id' => $bookingId,
            'gateway' => $gateway,
            'amount' => $amountKurus
        ];
    }
}
