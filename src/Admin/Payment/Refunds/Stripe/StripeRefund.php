<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Payment\Refunds\Stripe;

use MHMRentiva\Admin\Payment\Stripe\Client as StripeClient;
use MHMRentiva\Admin\PostTypes\Logs\Logger;
use MHMRentiva\Admin\Payment\Refunds\RefundCalculator;

if (!defined('ABSPATH')) {
    exit;
}

final class StripeRefund
{
    /**
     * Stripe iade işlemi yapar
     */
    public static function processRefund(int $bookingId, int $amountKurus, string $reason = ''): array
    {
        // Stripe ödeme tanımlayıcılarını al
        $paymentIntentId = (string) get_post_meta($bookingId, '_mhm_stripe_payment_intent', true);
        $chargeId = (string) get_post_meta($bookingId, '_mhm_stripe_charge_id', true);

        if (empty($paymentIntentId) && empty($chargeId)) {
            return [
                'ok' => false,
                'message' => 'Stripe payment identifiers missing'
            ];
        }

        try {
            $client = new StripeClient();
            $result = $client->refund([
                'payment_intent' => $paymentIntentId,
                'charge' => $chargeId,
                'amount' => $amountKurus,
                'reason' => $reason,
            ]);

            if ($result['ok']) {
                Logger::add([
                    'gateway' => 'stripe',
                    'action' => 'refund',
                    'status' => 'success',
                    'booking_id' => $bookingId,
                    'amount_kurus' => $amountKurus,
                    'message' => 'Stripe refund successful',
                    'context' => [
                        'payment_intent' => $paymentIntentId,
                        'charge_id' => $chargeId,
                        'refund_id' => $result['id'] ?? '',
                    ],
                ]);
            } else {
                Logger::add([
                    'gateway' => 'stripe',
                    'action' => 'refund',
                    'status' => 'error',
                    'booking_id' => $bookingId,
                    'amount_kurus' => $amountKurus,
                    'message' => $result['message'] ?? 'Stripe iade hatası',
                    'context' => [
                        'payment_intent' => $paymentIntentId,
                        'charge_id' => $chargeId,
                        'error_code' => $result['code'] ?? '',
                    ],
                ]);
            }

            return $result;

        } catch (\Throwable $e) {
            Logger::add([
                'gateway' => 'stripe',
                'action' => 'refund',
                'status' => 'error',
                'booking_id' => $bookingId,
                'amount_kurus' => $amountKurus,
                'message' => 'Stripe refund exception: ' . $e->getMessage(),
                'context' => [
                    'payment_intent' => $paymentIntentId,
                    'charge_id' => $chargeId,
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
     * Stripe tam iade yapar
     */
    public static function fullRefund(int $bookingId, string $reason = ''): array
    {
        // Merkezi hesaplama sınıfını kullan
        $remainingAmount = RefundCalculator::calculateRemainingAmount($bookingId);

        if ($remainingAmount <= 0) {
            return [
                'ok' => false,
                'message' => __('Refund amount not found', 'mhm-rentiva')
            ];
        }

        return self::processRefund($bookingId, $remainingAmount, $reason);
    }

    /**
     * Stripe kısmi iade yapar
     */
    public static function partialRefund(int $bookingId, int $amountKurus, string $reason = ''): array
    {
        if ($amountKurus <= 0) {
            return [
                'ok' => false,
                'message' => 'Invalid refund amount'
            ];
        }

        return self::processRefund($bookingId, $amountKurus, $reason);
    }

    /**
     * Stripe iade durumunu kontrol eder
     */
    public static function isRefundSuccessful(array $result): bool
    {
        return $result['ok'] === true && !empty($result['id']);
    }

    /**
     * Stripe iade ID'sini alır
     */
    public static function getRefundId(array $result): string
    {
        return $result['id'] ?? '';
    }

    /**
     * Stripe iade tutarını alır
     */
    public static function getRefundAmount(array $result): int
    {
        return $result['amount'] ?? 0;
    }
}
