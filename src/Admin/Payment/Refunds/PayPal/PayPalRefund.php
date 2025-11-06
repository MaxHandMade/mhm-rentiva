<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Payment\Refunds\PayPal;

use MHMRentiva\Admin\Payment\PayPal\Client as PayPalClient;
use MHMRentiva\Admin\PostTypes\Logs\Logger;

if (!defined('ABSPATH')) {
    exit;
}

final class PayPalRefund
{
    /**
     * PayPal iade işlemi yapar
     */
    public static function processRefund(int $bookingId, int $amountKurus, string $reason = ''): array
    {
        // PayPal capture ID'sini al
        $captureId = (string) get_post_meta($bookingId, '_mhm_paypal_payment_id', true);
        if (empty($captureId)) {
            return [
                'ok' => false,
                'message' => 'PayPal capture ID eksik'
            ];
        }

        try {
            $client = new PayPalClient();
            $result = $client->refundPayment($captureId, $amountKurus);

            if ($result['ok']) {
                Logger::add([
                    'gateway' => 'paypal',
                    'action' => 'refund',
                    'status' => 'success',
                    'booking_id' => $bookingId,
                    'amount_kurus' => $amountKurus,
                    'message' => __('PayPal refund successful', 'mhm-rentiva'),
                    'context' => [
                        'capture_id' => $captureId,
                        'refund_id' => $result['refund_id'] ?? '',
                        'status' => $result['status'] ?? '',
                    ],
                ]);
            } else {
                Logger::add([
                    'gateway' => 'paypal',
                    'action' => 'refund',
                    'status' => 'error',
                    'booking_id' => $bookingId,
                    'amount_kurus' => $amountKurus,
                    'message' => $result['message'] ?? 'PayPal iade hatası',
                    'context' => [
                        'capture_id' => $captureId,
                    ],
                ]);
            }

            return $result;

        } catch (\Throwable $e) {
            Logger::add([
                'gateway' => 'paypal',
                'action' => 'refund',
                'status' => 'error',
                'booking_id' => $bookingId,
                'amount_kurus' => $amountKurus,
                'message' => 'PayPal iade exception: ' . $e->getMessage(),
                'context' => [
                    'capture_id' => $captureId,
                    'exception' => $e->getTraceAsString(),
                ],
            ]);

            return [
                'ok' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * PayPal tam iade yapar
     */
    public static function fullRefund(int $bookingId, string $reason = ''): array
    {
        // Önce ödeme tutarını al
        $paidAmount = (int) get_post_meta($bookingId, '_mhm_payment_amount', true);
        $refundedAmount = (int) get_post_meta($bookingId, '_mhm_refunded_amount', true);
        $remainingAmount = max(0, $paidAmount - $refundedAmount);

        if ($remainingAmount <= 0) {
            return [
                'ok' => false,
                'message' => __('No amount to refund', 'mhm-rentiva')
            ];
        }

        return self::processRefund($bookingId, $remainingAmount, $reason);
    }

    /**
     * PayPal kısmi iade yapar
     */
    public static function partialRefund(int $bookingId, int $amountKurus, string $reason = ''): array
    {
        if ($amountKurus <= 0) {
            return [
                'ok' => false,
                'message' => __('Invalid refund amount', 'mhm-rentiva')
            ];
        }

        return self::processRefund($bookingId, $amountKurus, $reason);
    }

    /**
     * PayPal iade durumunu kontrol eder
     */
    public static function isRefundSuccessful(array $result): bool
    {
        return $result['ok'] === true && !empty($result['refund_id']);
    }

    /**
     * PayPal iade ID'sini alır
     */
    public static function getRefundId(array $result): string
    {
        return $result['refund_id'] ?? '';
    }

    /**
     * PayPal iade tutarını alır
     */
    public static function getRefundAmount(array $result): int
    {
        if (isset($result['amount']['value'])) {
            return (int) ($result['amount']['value'] * 100); // PayPal cent'e çevir
        }
        
        return 0;
    }
}
