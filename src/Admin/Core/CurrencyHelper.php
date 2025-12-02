<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Core;

use MHMRentiva\Admin\Settings\Core\SettingsCore;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Currency Helper Class
 * 
 * Centralized currency symbol management for the entire plugin.
 * All currency symbols must match the settings page currency list.
 * 
 * @since 3.0.1
 */
final class CurrencyHelper
{
    /**
     * Get all supported currency codes and symbols
     * 
     * This list must match exactly with SettingsCore::render_currency_field()
     * Can be extended via 'mhm_rentiva_currency_symbols' filter hook
     * 
     * @return array<string, string> Currency code => Symbol mapping
     */
    public static function get_all_currency_symbols(): array
    {
        $symbols = [
            'TRY' => '₺',
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'JPY' => '¥',
            'CAD' => 'C$',
            'AUD' => 'A$',
            'CHF' => 'CHF',
            'CNY' => '¥',
            'INR' => '₹',
            'BRL' => 'R$',
            'RUB' => '₽',
            'KRW' => '₩',
            'MXN' => '$',
            'SGD' => 'S$',
            'HKD' => 'HK$',
            'NZD' => 'NZ$',
            'SEK' => 'kr',
            'NOK' => 'kr',
            'DKK' => 'kr',
            'PLN' => 'zł',
            'CZK' => 'Kč',
            'HUF' => 'Ft',
            'RON' => 'lei',
            'BGN' => 'лв',
            'HRK' => 'kn',
            'RSD' => 'дин',
            'UAH' => '₴',
            'BYN' => 'Br',
            'KZT' => '₸',
            'UZS' => 'so\'m',
            'KGS' => 'сом',
            'TJS' => 'SM',
            'TMT' => 'T',
            'AZN' => '₼',
            'GEL' => '₾',
            'AMD' => '֏',
            'AED' => 'د.إ',
            'SAR' => 'ر.س',
            'QAR' => 'ر.ق',
            'KWD' => 'د.ك',
            'BHD' => 'د.ب',
            'OMR' => 'ر.ع.',
            'JOD' => 'د.أ',
            'LBP' => 'ل.ل',
            'EGP' => '£',
            'ILS' => '₪',
            // Legacy aliases (for backward compatibility)
            'TL' => '₺',
            'LIRA' => '₺',
        ];
        
        /**
         * Filter: Allow addons and third-party plugins to add custom currency symbols
         * 
         * @param array<string, string> $symbols Currency code => Symbol mapping
         * @return array Modified currency symbols array
         * 
         * @example
         * add_filter('mhm_rentiva_currency_symbols', function($symbols) {
         *     $symbols['BTC'] = '₿';
         *     $symbols['ETH'] = 'Ξ';
         *     return $symbols;
         * });
         */
        return apply_filters('mhm_rentiva_currency_symbols', $symbols);
    }

    /**
     * Get currency symbol for the current setting
     * 
     * @param string|null $currency_code Optional currency code. If not provided, uses setting value.
     * @return string Currency symbol or currency code as fallback
     */
    public static function get_currency_symbol(?string $currency_code = null): string
    {
        if ($currency_code === null) {
            // If WooCommerce is active, use WooCommerce currency
            if (function_exists('get_woocommerce_currency')) {
                $currency_code = get_woocommerce_currency();
            } else {
                $currency_code = SettingsCore::get('mhm_rentiva_currency', 'USD');
            }
        }

        $currency_code = strtoupper(trim($currency_code));
        $symbols = self::get_all_currency_symbols();

        return $symbols[$currency_code] ?? $currency_code;
    }

    /**
     * Get currency symbol for a specific currency code
     * 
     * @param string $currency_code Currency code (e.g., 'USD', 'EUR')
     * @return string Currency symbol
     */
    public static function get_symbol_for_currency(string $currency_code): string
    {
        return self::get_currency_symbol($currency_code);
    }

    /**
     * Check if a currency code is supported
     * 
     * @param string $currency_code Currency code to check
     * @return bool True if supported
     */
    public static function is_currency_supported(string $currency_code): bool
    {
        $currency_code = strtoupper(trim($currency_code));
        $symbols = self::get_all_currency_symbols();
        
        return isset($symbols[$currency_code]);
    }

    /**
     * Register WordPress filter hooks
     * This should be called during plugin initialization
     */
    public static function register_hooks(): void
    {
        // Register filter for template usage
        add_filter('mhm_rentiva/currency_symbol', [self::class, 'filter_currency_symbol'], 10, 1);
    }

    /**
     * Filter callback for mhm_rentiva/currency_symbol
     * 
     * @param string $default_symbol Default symbol (ignored, we use settings)
     * @return string Currency symbol from settings
     */
    public static function filter_currency_symbol(string $default_symbol = ''): string
    {
        return self::get_currency_symbol();
    }

    /**
     * Get currency list for dropdowns (code => display name with symbol)
     * 
     * This matches SettingsCore::render_currency_field() format
     * Can be extended via 'mhm_rentiva_currency_list' filter hook
     * 
     * @return array<string, string> Currency code => Display name mapping
     */
    public static function get_currency_list_for_dropdown(): array
    {
        $currencies = [
            'TRY' => 'Turkish Lira (₺)',
            'USD' => 'US Dollar ($)',
            'EUR' => 'Euro (€)',
            'GBP' => 'British Pound (£)',
            'JPY' => 'Japanese Yen (¥)',
            'CAD' => 'Canadian Dollar (C$)',
            'AUD' => 'Australian Dollar (A$)',
            'CHF' => 'Swiss Franc (CHF)',
            'CNY' => 'Chinese Yuan (¥)',
            'INR' => 'Indian Rupee (₹)',
            'BRL' => 'Brazilian Real (R$)',
            'RUB' => 'Russian Ruble (₽)',
            'KRW' => 'South Korean Won (₩)',
            'MXN' => 'Mexican Peso ($)',
            'SGD' => 'Singapore Dollar (S$)',
            'HKD' => 'Hong Kong Dollar (HK$)',
            'NZD' => 'New Zealand Dollar (NZ$)',
            'SEK' => 'Swedish Krona (kr)',
            'NOK' => 'Norwegian Krone (kr)',
            'DKK' => 'Danish Krone (kr)',
            'PLN' => 'Polish Zloty (zł)',
            'CZK' => 'Czech Koruna (Kč)',
            'HUF' => 'Hungarian Forint (Ft)',
            'RON' => 'Romanian Leu (lei)',
            'BGN' => 'Bulgarian Lev (лв)',
            'HRK' => 'Croatian Kuna (kn)',
            'RSD' => 'Serbian Dinar (дин)',
            'UAH' => 'Ukrainian Hryvnia (₴)',
            'BYN' => 'Belarusian Ruble (Br)',
            'KZT' => 'Kazakhstani Tenge (₸)',
            'UZS' => 'Uzbekistani Som (so\'m)',
            'KGS' => 'Kyrgyzstani Som (сом)',
            'TJS' => 'Tajikistani Somoni (SM)',
            'TMT' => 'Turkmenistani Manat (T)',
            'AZN' => 'Azerbaijani Manat (₼)',
            'GEL' => 'Georgian Lari (₾)',
            'AMD' => 'Armenian Dram (֏)',
            'AED' => 'UAE Dirham (د.إ)',
            'SAR' => 'Saudi Riyal (ر.س)',
            'QAR' => 'Qatari Riyal (ر.ق)',
            'KWD' => 'Kuwaiti Dinar (د.ك)',
            'BHD' => 'Bahraini Dinar (د.ب)',
            'OMR' => 'Omani Rial (ر.ع.)',
            'JOD' => 'Jordanian Dinar (د.أ)',
            'LBP' => 'Lebanese Pound (ل.ل)',
            'EGP' => 'Egyptian Pound (£)',
            'ILS' => 'Israeli Shekel (₪)',
        ];
        
        /**
         * Filter: Allow addons and third-party plugins to add custom currencies to dropdown
         * 
         * @param array<string, string> $currencies Currency code => Display name mapping
         * @return array Modified currency list array
         * 
         * @example
         * add_filter('mhm_rentiva_currency_list', function($currencies) {
         *     $currencies['BTC'] = 'Bitcoin (₿)';
         *     $currencies['ETH'] = 'Ethereum (Ξ)';
         *     return $currencies;
         * });
         */
        return apply_filters('mhm_rentiva_currency_list', $currencies);
    }
}

