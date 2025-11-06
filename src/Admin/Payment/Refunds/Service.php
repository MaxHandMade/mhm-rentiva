<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Payment\Refunds;

use MHMRentiva\Admin\Payment\Refunds\PayTR\PayTRRefund;
use MHMRentiva\Admin\Payment\Refunds\Stripe\StripeRefund;
use MHMRentiva\Admin\Payment\Refunds\PayPal\PayPalRefund;
use MHMRentiva\Admin\PostTypes\Logs\Logger;
use MHMRentiva\Admin\Emails\Notifications\RefundNotifications;

if (!defined('ABSPATH')) {
    exit;
}

final class Service
{
    /**
     * Processes refund
     */
    public static function process(int $bookingId, int $amountKurus, string $reason = ''): array
    {
        // Validate
        $validation = RefundValidator::validatePartialRefund($bookingId, $amountKurus);
        if (!$validation['valid']) {
            return [
                'mhm_refund' => '0',
                'mhm_refund_msg' => $validation['message']
            ];
        }

        $gateway = $validation['gateway'];
        $amount = $validation['amount'];

        // Process refund based on gateway
        $result = self::processGatewayRefund($bookingId, $gateway, $amount, $reason);

        if (!$result['ok']) {
            Logger::add([
                'gateway' => $gateway,
                'action' => 'refund',
                'status' => 'error',
                'booking_id' => $bookingId,
                'amount_kurus' => $amount,
                'message' => $result['message'] ?? __('Refund failed', 'mhm-rentiva'),
            ]);

            return [
                'mhm_refund' => '0',
                'mhm_refund_msg' => $result['message'] ?? __('Refund failed', 'mhm-rentiva')
            ];
        }

        // Update booking meta
        self::updateBookingMeta($bookingId, $amount, $result);

        // Send email notification
        self::sendRefundNotification($bookingId, $amount, $reason);

        Logger::add([
            'gateway' => $gateway,
            'action' => 'refund',
            'status' => 'success',
            'booking_id' => $bookingId,
            'amount_kurus' => $amount,
            'message' => __('Refund successful', 'mhm-rentiva'),
            'context' => [
                'refund_id' => $result['id'] ?? '',
                'gateway' => $gateway,
            ],
        ]);

        return [
            'mhm_refund' => '1',
            'mhm_refund_msg' => ''
        ];
    }

    /**
     * Processes full refund
     */
    public static function processFullRefund(int $bookingId, string $reason = ''): array
    {
        // Validate
        $validation = RefundValidator::validateFullRefund($bookingId);
        if (!$validation['valid']) {
            return [
                'mhm_refund' => '0',
                'mhm_refund_msg' => $validation['message']
            ];
        }

        $gateway = $validation['gateway'];
        $amount = $validation['amount'];

        // Process full refund based on gateway
        $result = self::processGatewayFullRefund($bookingId, $gateway, $reason);

        if (!$result['ok']) {
            Logger::add([
                'gateway' => $gateway,
                'action' => 'full_refund',
                'status' => 'error',
                'booking_id' => $bookingId,
                'amount_kurus' => $amount,
                'message' => $result['message'] ?? __('Full refund failed', 'mhm-rentiva'),
            ]);

            return [
                'mhm_refund' => '0',
                'mhm_refund_msg' => $result['message'] ?? __('Full refund failed', 'mhm-rentiva')
            ];
        }

        // Update booking meta
        self::updateBookingMeta($bookingId, $amount, $result);

        // Send email notification
        self::sendRefundNotification($bookingId, $amount, $reason);

        Logger::add([
            'gateway' => $gateway,
            'action' => 'full_refund',
            'status' => 'success',
            'booking_id' => $bookingId,
            'amount_kurus' => $amount,
            'message' => __('Full refund successful', 'mhm-rentiva'),
            'context' => [
                'refund_id' => $result['id'] ?? '',
                'gateway' => $gateway,
            ],
        ]);

        return [
            'mhm_refund' => '1',
            'mhm_refund_msg' => ''
        ];
    }

    /**
     * Processes refund based on gateway
     */
    private static function processGatewayRefund(int $bookingId, string $gateway, int $amount, string $reason): array
    {
        switch ($gateway) {
            case 'paytr':
                return PayTRRefund::partialRefund($bookingId, $amount, $reason);

            case 'stripe':
                return StripeRefund::partialRefund($bookingId, $amount, $reason);

            case 'paypal':
                return PayPalRefund::partialRefund($bookingId, $amount, $reason);

            default:
                return [
                    'ok' => false,
                    'message' => __('Unsupported payment gateway', 'mhm-rentiva')
                ];
        }
    }

    /**
     * Processes full refund based on gateway
     */
    private static function processGatewayFullRefund(int $bookingId, string $gateway, string $reason): array
    {
        switch ($gateway) {
            case 'paytr':
                return PayTRRefund::fullRefund($bookingId, $reason);

            case 'stripe':
                return StripeRefund::fullRefund($bookingId, $reason);

            case 'paypal':
                return PayPalRefund::fullRefund($bookingId, $reason);

            default:
                return [
                    'ok' => false,
                    'message' => __('Unsupported payment gateway', 'mhm-rentiva')
                ];
        }
    }

    /**
     * Updates booking meta
     */
    private static function updateBookingMeta(int $bookingId, int $amount, array $result): void
    {
        $refundedAmount = (int) get_post_meta($bookingId, '_mhm_refunded_amount', true);
        $newRefundedAmount = $refundedAmount + $amount;
        
        update_post_meta($bookingId, '_mhm_refunded_amount', $newRefundedAmount);

        $paidAmount = (int) get_post_meta($bookingId, '_mhm_payment_amount', true);
        $newPaymentStatus = $newRefundedAmount >= $paidAmount ? 'refunded' : 'partially_refunded';
        
        update_post_meta($bookingId, '_mhm_payment_status', $newPaymentStatus);

        // Save refund transaction ID
        if (!empty($result['id'])) {
            add_post_meta($bookingId, '_mhm_refund_txn_id', (string) $result['id']);
        }
    }

    /**
     * Sends refund notification
     */
    private static function sendRefundNotification(int $bookingId, int $amount, string $reason): void
    {
        try {
            $currency = (string) get_post_meta($bookingId, '_mhm_payment_currency', true) ?: 'TRY';
            $refundedAmount = (int) get_post_meta($bookingId, '_mhm_refunded_amount', true);
            $paidAmount = (int) get_post_meta($bookingId, '_mhm_payment_amount', true);
            $paymentStatus = $refundedAmount >= $paidAmount ? 'refunded' : 'partially_refunded';

            RefundNotifications::notify($bookingId, $amount, $currency, $paymentStatus, $reason);
        } catch (\Throwable $e) {
            // Email error is not critical, log it
            Logger::add([
                'action' => 'refund_notification',
                'status' => 'error',
                'booking_id' => $bookingId,
                'message' => __('Refund notification could not be sent:', 'mhm-rentiva') . ' ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Checks refund status
     */
    public static function isRefundSuccessful(array $result): bool
    {
        return $result['ok'] === true && !empty($result['id']);
    }

    /**
     * Gets refund ID
     */
    public static function getRefundId(array $result): string
    {
        return $result['id'] ?? '';
    }

    /**
     * Gets refund amount
     */
    public static function getRefundAmount(array $result): int
    {
        return $result['amount'] ?? 0;
    }
}
