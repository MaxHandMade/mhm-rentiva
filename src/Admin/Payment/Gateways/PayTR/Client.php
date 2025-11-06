<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Payment\Gateways\PayTR;

use MHMRentiva\Admin\Licensing\Mode;
use MHMRentiva\Admin\Payment\Gateways\PayTR\API\Token;
use MHMRentiva\Admin\Payment\Gateways\PayTR\API\Inquiry;
use MHMRentiva\Admin\Payment\Gateways\PayTR\API\Refund;
use MHMRentiva\Admin\Payment\Gateways\PayTR\API\Callback;
use MHMRentiva\Admin\PostTypes\Logs\Logger;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

final class Client
{
    /**
     * PayTR Client kayıt işlemleri
     */
    public static function register(): void
    {
        if (!Mode::featureEnabled(Mode::FEATURE_GATEWAY_PAYTR)) {
            return;
        }

        // PayTR Client kayıt işlemleri buraya eklenebilir
    }

    /**
     * PayTR token oluşturur
     */
    public static function createToken(array $payload): array|WP_Error
    {
        return Token::createToken($payload);
    }

    /**
     * PayTR ödeme durumu sorgular
     */
    public function inquire(array $args): array
    {
        return Inquiry::inquire($args);
    }

    /**
     * PayTR iade işlemi yapar
     */
    public function refund(array $args): array
    {
        return Refund::processRefund($args);
    }

    /**
     * PayTR callback hash'ini doğrular
     */
    public static function verifyCallback(array $callbackData): bool
    {
        return Callback::verifyCallback($callbackData);
    }

    /**
     * PayTR callback verilerini işler
     */
    public static function processCallback(array $callbackData): array
    {
        return Callback::processCallback($callbackData);
    }

    /**
     * Tam iade yapar
     */
    public function fullRefund(string $merchantOid, string $reason = ''): array
    {
        return Refund::fullRefund($merchantOid, $reason);
    }

    /**
     * Kısmi iade yapar
     */
    public function partialRefund(string $merchantOid, int $amount, string $reason = ''): array
    {
        return Refund::partialRefund($merchantOid, $amount, $reason);
    }

    /**
     * Ödeme durumunu kontrol eder
     */
    public function checkPaymentStatus(string $merchantOid): array
    {
        return Inquiry::checkPaymentStatus($merchantOid);
    }

    /**
     * Token için payload hazırlar
     */
    public static function prepareTokenPayload(array $bookingData): array
    {
        return Token::preparePayload($bookingData);
    }

    /**
     * Callback verilerini normalize eder
     */
    public static function normalizeCallbackData(array $rawData): array
    {
        return Callback::normalizeCallbackData($rawData);
    }

    /**
     * Ödeme başarılı mı kontrol eder
     */
    public function isPaymentSuccessful(array $result): bool
    {
        if (isset($result['is_paid'])) {
            return $result['is_paid'] === true;
        }
        
        if (isset($result['paid'])) {
            return $result['paid'] === true;
        }
        
        return false;
    }

    /**
     * Ödeme tutarını alır
     */
    public function getPaymentAmount(array $result): int
    {
        if (isset($result['payment_amount'])) {
            return (int) $result['payment_amount'];
        }
        
        if (isset($result['total_amount'])) {
            return (int) $result['total_amount'];
        }
        
        return 0;
    }

    /**
     * Taksit sayısını alır
     */
    public function getInstallmentCount(array $result): int
    {
        if (isset($result['installment'])) {
            return (int) $result['installment'];
        }
        
        if (isset($result['installment_count'])) {
            return (int) $result['installment_count'];
        }
        
        return 0;
    }
}
