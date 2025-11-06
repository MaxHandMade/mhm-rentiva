<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Core;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Language Helper Class
 * 
 * Centralized language/locale management for the entire plugin.
 * Supports WordPress locale codes and converts them to JavaScript/API compatible formats.
 * 
 * @since 3.0.1
 */
final class LanguageHelper
{
    /**
     * Get all supported WordPress locales with display names
     * 
     * This list includes major languages used globally.
     * Based on WordPress locale codes.
     * 
     * @return array<string, string> WordPress locale => Display name mapping
     */
    public static function get_supported_locales(): array
    {
        return [
            'en_US' => __('English (United States)', 'mhm-rentiva'),
            'en_GB' => __('English (United Kingdom)', 'mhm-rentiva'),
            'tr_TR' => __('Turkish', 'mhm-rentiva'),
            'de_DE' => __('German', 'mhm-rentiva'),
            'de_AT' => __('German (Austria)', 'mhm-rentiva'),
            'de_CH' => __('German (Switzerland)', 'mhm-rentiva'),
            'fr_FR' => __('French', 'mhm-rentiva'),
            'fr_CA' => __('French (Canada)', 'mhm-rentiva'),
            'es_ES' => __('Spanish (Spain)', 'mhm-rentiva'),
            'es_MX' => __('Spanish (Mexico)', 'mhm-rentiva'),
            'es_AR' => __('Spanish (Argentina)', 'mhm-rentiva'),
            'es_CO' => __('Spanish (Colombia)', 'mhm-rentiva'),
            'it_IT' => __('Italian', 'mhm-rentiva'),
            'pt_BR' => __('Portuguese (Brazil)', 'mhm-rentiva'),
            'pt_PT' => __('Portuguese (Portugal)', 'mhm-rentiva'),
            'nl_NL' => __('Dutch', 'mhm-rentiva'),
            'nl_BE' => __('Dutch (Belgium)', 'mhm-rentiva'),
            'pl_PL' => __('Polish', 'mhm-rentiva'),
            'ru_RU' => __('Russian', 'mhm-rentiva'),
            'ja' => __('Japanese', 'mhm-rentiva'),
            'zh_CN' => __('Chinese (Simplified)', 'mhm-rentiva'),
            'zh_TW' => __('Chinese (Traditional)', 'mhm-rentiva'),
            'ko_KR' => __('Korean', 'mhm-rentiva'),
            'ar' => __('Arabic', 'mhm-rentiva'),
            'he_IL' => __('Hebrew', 'mhm-rentiva'),
            'hi_IN' => __('Hindi', 'mhm-rentiva'),
            'sv_SE' => __('Swedish', 'mhm-rentiva'),
            'da_DK' => __('Danish', 'mhm-rentiva'),
            'fi' => __('Finnish', 'mhm-rentiva'),
            'no_NO' => __('Norwegian', 'mhm-rentiva'),
            'cs_CZ' => __('Czech', 'mhm-rentiva'),
            'hu_HU' => __('Hungarian', 'mhm-rentiva'),
            'ro_RO' => __('Romanian', 'mhm-rentiva'),
            'bg_BG' => __('Bulgarian', 'mhm-rentiva'),
            'hr' => __('Croatian', 'mhm-rentiva'),
            'sr_RS' => __('Serbian', 'mhm-rentiva'),
            'uk' => __('Ukrainian', 'mhm-rentiva'),
            'el' => __('Greek', 'mhm-rentiva'),
            'th' => __('Thai', 'mhm-rentiva'),
            'vi' => __('Vietnamese', 'mhm-rentiva'),
            'id_ID' => __('Indonesian', 'mhm-rentiva'),
            'ms_MY' => __('Malay', 'mhm-rentiva'),
            'sk_SK' => __('Slovak', 'mhm-rentiva'),
            'sl_SI' => __('Slovenian', 'mhm-rentiva'),
            'lt_LT' => __('Lithuanian', 'mhm-rentiva'),
            'lv_LV' => __('Latvian', 'mhm-rentiva'),
            'et' => __('Estonian', 'mhm-rentiva'),
            'ca' => __('Catalan', 'mhm-rentiva'),
            'eu' => __('Basque', 'mhm-rentiva'),
            'gl_ES' => __('Galician', 'mhm-rentiva'),
            'is_IS' => __('Icelandic', 'mhm-rentiva'),
            'mk_MK' => __('Macedonian', 'mhm-rentiva'),
            'sq' => __('Albanian', 'mhm-rentiva'),
            'bs_BA' => __('Bosnian', 'mhm-rentiva'),
            'mt_MT' => __('Maltese', 'mhm-rentiva'),
            'cy' => __('Welsh', 'mhm-rentiva'),
            'ga' => __('Irish', 'mhm-rentiva'),
        ];
    }

    /**
     * Convert WordPress locale to JavaScript/API compatible format
     * 
     * WordPress uses format like 'en_US', 'tr_TR'
     * JavaScript/APIs use format like 'en-US', 'tr-TR'
     * 
     * @param string|null $wp_locale WordPress locale code (e.g., 'en_US', 'tr_TR'). If null, uses current locale.
     * @return string JavaScript locale format (e.g., 'en-US', 'tr-TR')
     */
    public static function wp_locale_to_js_locale(?string $wp_locale = null): string
    {
        if ($wp_locale === null) {
            $wp_locale = get_locale();
        }

        // Direct mapping for common locales
        $locale_map = [
            'en_US' => 'en-US',
            'en_GB' => 'en-GB',
            'en_CA' => 'en-CA',
            'en_AU' => 'en-AU',
            'en_NZ' => 'en-NZ',
            'tr_TR' => 'tr-TR',
            'de_DE' => 'de-DE',
            'de_AT' => 'de-AT',
            'de_CH' => 'de-CH',
            'fr_FR' => 'fr-FR',
            'fr_CA' => 'fr-CA',
            'fr_BE' => 'fr-BE',
            'fr_CH' => 'fr-CH',
            'es_ES' => 'es-ES',
            'es_MX' => 'es-MX',
            'es_AR' => 'es-AR',
            'es_CO' => 'es-CO',
            'es_CL' => 'es-CL',
            'es_PE' => 'es-PE',
            'es_VE' => 'es-VE',
            'it_IT' => 'it-IT',
            'it_CH' => 'it-CH',
            'pt_BR' => 'pt-BR',
            'pt_PT' => 'pt-PT',
            'nl_NL' => 'nl-NL',
            'nl_BE' => 'nl-BE',
            'pl_PL' => 'pl-PL',
            'ru_RU' => 'ru-RU',
            'ja' => 'ja',
            'zh_CN' => 'zh-CN',
            'zh_TW' => 'zh-TW',
            'ko_KR' => 'ko-KR',
            'ar' => 'ar',
            'he_IL' => 'he-IL',
            'hi_IN' => 'hi-IN',
            'sv_SE' => 'sv-SE',
            'da_DK' => 'da-DK',
            'fi' => 'fi',
            'no_NO' => 'no-NO',
            'nb_NO' => 'nb-NO',
            'nn_NO' => 'nn-NO',
            'cs_CZ' => 'cs-CZ',
            'hu_HU' => 'hu-HU',
            'ro_RO' => 'ro-RO',
            'bg_BG' => 'bg-BG',
            'hr' => 'hr',
            'sr_RS' => 'sr-RS',
            'uk' => 'uk',
            'el' => 'el',
            'th' => 'th',
            'vi' => 'vi',
            'id_ID' => 'id-ID',
            'ms_MY' => 'ms-MY',
            'sk_SK' => 'sk-SK',
            'sl_SI' => 'sl-SI',
            'lt_LT' => 'lt-LT',
            'lv_LV' => 'lv-LV',
            'et' => 'et',
            'ca' => 'ca',
            'eu' => 'eu',
            'gl_ES' => 'gl-ES',
            'is_IS' => 'is-IS',
            'mk_MK' => 'mk-MK',
            'sq' => 'sq',
            'bs_BA' => 'bs-BA',
            'mt_MT' => 'mt-MT',
            'cy' => 'cy',
            'ga' => 'ga',
        ];

        // If exact match exists, return it
        if (isset($locale_map[$wp_locale])) {
            return $locale_map[$wp_locale];
        }

        // Fallback: Convert WordPress locale format to JavaScript format
        // Replace underscore with hyphen
        $js_locale = str_replace('_', '-', $wp_locale);

        // If locale is 2 characters (like 'ar', 'ja'), return as is
        if (strlen($wp_locale) <= 2) {
            return $wp_locale;
        }

        return $js_locale;
    }

    /**
     * Get current WordPress locale
     * 
     * @return string WordPress locale code
     */
    public static function get_current_locale(): string
    {
        return get_locale();
    }

    /**
     * Get current JavaScript locale
     * 
     * @return string JavaScript locale format
     */
    public static function get_current_js_locale(): string
    {
        return self::wp_locale_to_js_locale();
    }

    /**
     * Check if a locale is supported
     * 
     * @param string $locale WordPress locale code
     * @return bool True if supported
     */
    public static function is_locale_supported(string $locale): bool
    {
        $locales = self::get_supported_locales();
        return isset($locales[$locale]);
    }

    /**
     * Get language name for a locale
     * 
     * @param string|null $locale WordPress locale code. If null, uses current locale.
     * @return string Language display name
     */
    public static function get_language_name(?string $locale = null): string
    {
        if ($locale === null) {
            $locale = get_locale();
        }

        $locales = self::get_supported_locales();
        return $locales[$locale] ?? $locale;
    }

    /**
     * Get language list for dropdowns
     * 
     * @return array<string, string> Locale code => Display name mapping
     */
    public static function get_language_list_for_dropdown(): array
    {
        return self::get_supported_locales();
    }
}

