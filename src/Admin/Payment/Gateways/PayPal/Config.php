<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Payment\Gateways\PayPal;

if (!defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\Payment\Gateways\AbstractGatewayConfig;

final class Config extends AbstractGatewayConfig
{
    protected static function getGatewayPrefix(): string
    {
        return 'paypal';
    }

    /**
     * PayPal Client ID'sini döndürür
     */
    public static function clientId(): string
    {
        $testMode = self::testMode();
        $optionKey = $testMode ? 'client_id_test' : 'client_id_live';
        
        return self::getSetting($optionKey);
    }

    /**
     * PayPal Client Secret'ını döndürür
     */
    public static function clientSecret(): string
    {
        $testMode = self::testMode();
        $optionKey = $testMode ? 'client_secret_test' : 'client_secret_live';
        
        return self::getSetting($optionKey);
    }

    /**
     * Para birimini döndürür
     */
    public static function currency(): string
    {
        $currency = self::getSetting('currency', 'USD');

        // PayPal desteklenen para birimleri
        $supportedCurrencies = ['USD', 'EUR', 'TRY', 'GBP', 'CAD', 'AUD'];

        return in_array($currency, $supportedCurrencies, true) ? $currency : 'USD';
    }

    /**
     * PayPal Webhook ID'sini döndürür
     */
    public static function webhookId(): string
    {
        return self::getSetting('webhook_id');
    }

    /**
     * PayPal API URL'sini döndürür
     */
    public static function apiUrl(): string
    {
        return self::testMode()
            ? 'https://api-m.sandbox.paypal.com'
            : 'https://api-m.paypal.com';
    }

    /**
     * Webhook URL'sini döndürür
     */
    public static function webhookUrl(): string
    {
        return home_url('/wp-json/mhm-rentiva/v1/paypal/webhook');
    }

    /**
     * Access token için cache key
     */
    public static function getAccessTokenCacheKey(): string
    {
        return 'mhm_paypal_access_token_' . (self::testMode() ? 'test' : 'live');
    }

    /**
     * Debug modunun aktif olup olmadığını kontrol eder
     */
    public static function debugMode(): bool
    {
        return self::getBooleanSetting('debug_mode');
    }

    /**
     * Timeout süresini döndürür (saniye)
     */
    public static function timeout(): int
    {
        $timeout = self::getIntegerSetting('timeout', 30);
        return max(10, min(120, $timeout)); // 10-120 saniye arası
    }

    /**
     * PayPal desteklenen para birimlerini döndürür
     */
    public static function supportedCurrencies(): array
    {
        return [
            'USD' => __('US Dollar', 'mhm-rentiva'),
            'EUR' => __('Euro', 'mhm-rentiva'),
            'TRY' => __('Turkish Lira', 'mhm-rentiva'),
            'GBP' => __('British Pound', 'mhm-rentiva'),
            'CAD' => __('Canadian Dollar', 'mhm-rentiva'),
            'AUD' => __('Australian Dollar', 'mhm-rentiva'),
        ];
    }

    /**
     * Yapılandırma bilgilerini döndürür (debug için)
     */
    public static function getConfigInfo(): array
    {
        return [
            'enabled' => self::enabled(),
            'test_mode' => self::testMode(),
            'currency' => self::currency(),
            'has_client_id' => !empty(self::clientId()),
            'has_client_secret' => !empty(self::clientSecret()),
            'has_webhook_id' => !empty(self::webhookId()),
            'api_url' => self::apiUrl(),
            'webhook_url' => self::webhookUrl(),
            'debug_mode' => self::debugMode(),
            'timeout' => self::timeout(),
        ];
    }
}
