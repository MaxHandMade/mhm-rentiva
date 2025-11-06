<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Payment\Gateways\Stripe;

use MHMRentiva\Admin\Licensing\Mode;
use MHMRentiva\Admin\Payment\Gateways\Stripe\API\PaymentIntents;
use MHMRentiva\Admin\Payment\Gateways\Stripe\API\Refunds;

if (!defined('ABSPATH')) {
    exit;
}

final class Client
{
    /**
     * Stripe Client kayıt işlemleri
     */
    public static function register(): void
    {
        if (!Mode::featureEnabled(Mode::FEATURE_GATEWAY_STRIPE)) {
            return;
        }
        // Stripe Client kayıt işlemleri buraya eklenebilir
    }

    /**
     * PaymentIntent durumunu sorgular
     */
    public function retrievePaymentIntent(string $paymentIntentId): array
    {
        return PaymentIntents::retrievePaymentIntent($paymentIntentId);
    }

    /**
     * PaymentIntent oluşturur
     */
    public function createPaymentIntent(array $data): array
    {
        return PaymentIntents::createPaymentIntent($data);
    }

    /**
     * PaymentIntent'i günceller
     */
    public function updatePaymentIntent(string $paymentIntentId, array $data): array
    {
        return PaymentIntents::updatePaymentIntent($paymentIntentId, $data);
    }

    /**
     * PaymentIntent'i iptal eder
     */
    public function cancelPaymentIntent(string $paymentIntentId): array
    {
        return PaymentIntents::cancelPaymentIntent($paymentIntentId);
    }

    /**
     * Stripe iade işlemi yapar
     */
    public function refund(array $args): array
    {
        return Refunds::createRefund($args);
    }

    /**
     * İade detaylarını alır
     */
    public function getRefund(string $refundId): array
    {
        return Refunds::getRefund($refundId);
    }

    /**
     * Tam iade yapar
     */
    public function fullRefund(string $paymentIntentId, string $reason = ''): array
    {
        return Refunds::fullRefund($paymentIntentId, $reason);
    }

    /**
     * Kısmi iade yapar
     */
    public function partialRefund(string $paymentIntentId, int $amount, string $reason = ''): array
    {
        return Refunds::partialRefund($paymentIntentId, $amount, $reason);
    }

    /**
     * PaymentIntent başarılı mı kontrol eder
     */
    public function isPaymentSuccessful(array $paymentIntent): bool
    {
        return PaymentIntents::isPaymentSuccessful($paymentIntent);
    }

    /**
     * PaymentIntent tutarını alır
     */
    public function getPaymentAmount(array $paymentIntent): int
    {
        return PaymentIntents::getPaymentAmount($paymentIntent);
    }

    /**
     * PaymentIntent para birimini alır
     */
    public function getPaymentCurrency(array $paymentIntent): string
    {
        return PaymentIntents::getPaymentCurrency($paymentIntent);
    }

    /**
     * PaymentIntent durumunu alır
     */
    public function getPaymentStatus(array $paymentIntent): string
    {
        return PaymentIntents::getPaymentStatus($paymentIntent);
    }

    /**
     * İade başarılı mı kontrol eder
     */
    public function isRefundSuccessful(array $refundResult): bool
    {
        return Refunds::isRefundSuccessful($refundResult);
    }

    /**
     * İade tutarını alır
     */
    public function getRefundAmount(array $refundData): int
    {
        return Refunds::getRefundAmount($refundData);
    }

    /**
     * İade ID'sini alır
     */
    public function getRefundId(array $refundData): string
    {
        return Refunds::getRefundId($refundData);
    }

    /**
     * İade durumunu alır
     */
    public function getRefundStatus(array $refundData): string
    {
        return Refunds::getRefundStatus($refundData);
    }

    /**
     * İade para birimini alır
     */
    public function getRefundCurrency(array $refundData): string
    {
        return Refunds::getRefundCurrency($refundData);
    }
}
