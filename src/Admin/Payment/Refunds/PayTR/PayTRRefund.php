<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Payment\Refunds\PayTR;

use MHMRentiva\Admin\Payment\PayTR\Client as PayTRClient;
use MHMRentiva\Admin\PostTypes\Logs\Logger;

if (!defined('ABSPATH')) {
    exit;
}

final class PayTRRefund
{
    /**
     * PayTR iade işlemi yapar
     */
    public static function processRefund(int $bookingId, int $amountKurus, string $reason = ''): array
    {
        // Merchant OID'yi al
        $merchantOid = (string) get_post_meta($bookingId, '_mhm_paytr_merchant_oid', true);
        if (empty($merchantOid)) {
            return [
                'ok' => false,
                'message' => 'PayTR merchant OID eksik'
            ];
        }

        try {
            $client = new PayTRClient();
            $result = $client->refund([
                'merchant_oid' => $merchantOid,
                'amount_kurus' => $amountKurus,
                'reason' => $reason,
            ]);

            if ($result['ok']) {
                Logger::add([
                    'gateway' => 'paytr',
                    'action' => 'refund',
                    'status' => 'success',
                    'booking_id' => $bookingId,
                    'amount_kurus' => $amountKurus,
                    'message' => __('PayTR refund successful', 'mhm-rentiva'),
                    'context' => [
                        'merchant_oid' => $merchantOid,
                        'refund_id' => $result['id'] ?? '',
                    ],
                ]);
            } else {
                Logger::add([
                    'gateway' => 'paytr',
                    'action' => 'refund',
                    'status' => 'error',
                    'booking_id' => $bookingId,
                    'amount_kurus' => $amountKurus,
                    'message' => $result['message'] ?? 'PayTR iade hatası',
                    'context' => [
                        'merchant_oid' => $merchantOid,
                        'error_code' => $result['code'] ?? '',
                    ],
                ]);
            }

            return $result;

        } catch (\Throwable $e) {
            Logger::add([
                'gateway' => 'paytr',
                'action' => 'refund',
                'status' => 'error',
                'booking_id' => $bookingId,
                'amount_kurus' => $amountKurus,
                'message' => 'PayTR iade exception: ' . $e->getMessage(),
                'context' => [
                    'merchant_oid' => $merchantOid,
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
     * PayTR tam iade yapar
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
     * PayTR kısmi iade yapar
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
     * PayTR iade durumunu kontrol eder
     */
    public static function isRefundSuccessful(array $result): bool
    {
        return $result['ok'] === true && !empty($result['id']);
    }

    /**
     * PayTR iade ID'sini alır
     */
    public static function getRefundId(array $result): string
    {
        return $result['id'] ?? '';
    }

    /**
     * PayTR iade tutarını alır
     */
    public static function getRefundAmount(array $result): int
    {
        return $result['amount'] ?? 0;
    }
}
