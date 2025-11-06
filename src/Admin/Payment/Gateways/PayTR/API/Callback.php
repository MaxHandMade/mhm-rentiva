<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Payment\Gateways\PayTR\API;

use MHMRentiva\Admin\Payment\Gateways\PayTR\Config;
use MHMRentiva\Admin\PostTypes\Logs\Logger;

if (!defined('ABSPATH')) {
    exit;
}

final class Callback
{
    /**
     * Verifies PayTR callback hash
     */
    public static function verifyCallback(array $callbackData): bool
    {
        $merchantKey = Config::merchantKey();
        $merchantSalt = Config::merchantSalt();

        if (empty($merchantKey) || empty($merchantSalt)) {
            Logger::error('PayTR callback verification: Merchant key or salt missing');
            return false;
        }

        $merchantOid = $callbackData['merchant_oid'] ?? '';
        $status = $callbackData['status'] ?? '';
        $totalAmount = $callbackData['total_amount'] ?? '';
        $hash = $callbackData['hash'] ?? '';

        if (empty($merchantOid) || empty($status) || empty($totalAmount) || empty($hash)) {
            Logger::error('PayTR callback verification: Required fields missing');
            return false;
        }

        // Calculate hash
        $calculatedHash = base64_encode(hash_hmac('sha256', $merchantOid . $merchantSalt . $status . $totalAmount, $merchantKey, true));

        $isValid = hash_equals($calculatedHash, $hash);

        if (!$isValid) {
            Logger::error('PayTR callback verification failed: ' . $merchantOid);
        } else {
            Logger::info('PayTR callback verification successful: ' . $merchantOid);
        }

        return $isValid;
    }

    /**
     * Processes callback data
     */
    public static function processCallback(array $callbackData): array
    {
        if (!self::verifyCallback($callbackData)) {
            return [
                'ok' => false,
                'message' => __('Callback verification failed', 'mhm-rentiva'),
            ];
        }

        $merchantOid = $callbackData['merchant_oid'] ?? '';
        $status = $callbackData['status'] ?? '';
        $totalAmount = $callbackData['total_amount'] ?? '';
        $paymentType = $callbackData['payment_type'] ?? '';
        $installmentCount = $callbackData['installment_count'] ?? '0';

        // Check payment status
        $isPaid = ($status === 'success');

        Logger::info('PayTR callback processed: ' . $merchantOid . ' - Status: ' . $status . ' - Amount: ' . $totalAmount);

        return [
            'ok' => true,
            'merchant_oid' => $merchantOid,
            'status' => $status,
            'total_amount' => $totalAmount,
            'payment_type' => $paymentType,
            'installment_count' => (int) $installmentCount,
            'is_paid' => $isPaid,
            'raw_data' => $callbackData,
        ];
    }

    /**
     * Gets payment status from callback
     */
    public static function getPaymentStatus(array $callbackData): string
    {
        return $callbackData['status'] ?? 'unknown';
    }

    /**
     * Gets payment amount from callback
     */
    public static function getPaymentAmount(array $callbackData): int
    {
        return (int) ($callbackData['total_amount'] ?? 0);
    }

    /**
     * Gets installment count from callback
     */
    public static function getInstallmentCount(array $callbackData): int
    {
        return (int) ($callbackData['installment_count'] ?? 0);
    }

    /**
     * Gets payment type from callback
     */
    public static function getPaymentType(array $callbackData): string
    {
        return $callbackData['payment_type'] ?? '';
    }

    /**
     * Checks if payment is successful
     */
    public static function isPaymentSuccessful(array $callbackData): bool
    {
        return self::getPaymentStatus($callbackData) === 'success';
    }

    /**
     * Callback verilerini normalize eder
     */
    public static function normalizeCallbackData(array $rawData): array
    {
        return [
            'merchant_oid' => sanitize_text_field((string) ($rawData['merchant_oid'] ?? '')),
            'status' => sanitize_text_field((string) ($rawData['status'] ?? '')),
            'total_amount' => sanitize_text_field((string) ($rawData['total_amount'] ?? '')),
            'payment_type' => sanitize_text_field((string) ($rawData['payment_type'] ?? '')),
            'installment_count' => sanitize_text_field((string) ($rawData['installment_count'] ?? '0')),
            'hash' => sanitize_text_field((string) ($rawData['hash'] ?? '')),
            'failed_reason_code' => sanitize_text_field((string) ($rawData['failed_reason_code'] ?? '')),
            'failed_reason_msg' => sanitize_text_field((string) ($rawData['failed_reason_msg'] ?? '')),
        ];
    }
}
