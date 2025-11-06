<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Core\Utilities;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ✅ INTERNATIONALIZATION IMPROVEMENT - I18n Helper
 * 
 * Central class for internationalization and translation
 */
final class I18nHelper
{
    /**
     * Text domain
     */
    private const TEXT_DOMAIN = 'mhm-rentiva';

    /**
     * Safe string translation
     */
    public static function __(string $text, string $domain = self::TEXT_DOMAIN): string
    {
        return __($text, $domain);
    }

    /**
     * Safe string translation (echo)
     */
    public static function _e(string $text, string $domain = self::TEXT_DOMAIN): void
    {
        _e($text, $domain);
    }

    /**
     * Safe string translation (escaped)
     */
    public static function esc_html__(string $text, string $domain = self::TEXT_DOMAIN): string
    {
        return esc_html__($text, $domain);
    }

    /**
     * Safe string translation (escaped, echo)
     */
    public static function esc_html_e(string $text, string $domain = self::TEXT_DOMAIN): void
    {
        esc_html_e($text, $domain);
    }

    /**
     * Safe string translation (attribute escaped)
     */
    public static function esc_attr__(string $text, string $domain = self::TEXT_DOMAIN): string
    {
        return esc_attr__($text, $domain);
    }

    /**
     * Safe string translation (attribute escaped, echo)
     */
    public static function esc_attr_e(string $text, string $domain = self::TEXT_DOMAIN): void
    {
        esc_attr_e($text, $domain);
    }

    /**
     * String translation with context
     */
    public static function _x(string $text, string $context, string $domain = self::TEXT_DOMAIN): string
    {
        return _x($text, $context, $domain);
    }

    /**
     * String translation with context (echo)
     */
    public static function _ex(string $text, string $context, string $domain = self::TEXT_DOMAIN): void
    {
        _ex($text, $context, $domain);
    }

    /**
     * String translation with pluralization
     */
    public static function _n(string $single, string $plural, int $number, string $domain = self::TEXT_DOMAIN): string
    {
        return _n($single, $plural, $number, $domain);
    }

    /**
     * String translation with pluralization (echo)
     */
    public static function _en(string $single, string $plural, int $number, string $domain = self::TEXT_DOMAIN): void
    {
        _en($single, $plural, $number, $domain);
    }

    /**
     * String translation with context and pluralization
     */
    public static function _nx(string $single, string $plural, int $number, string $context, string $domain = self::TEXT_DOMAIN): string
    {
        return _nx($single, $plural, $number, $context, $domain);
    }

    /**
     * String translation with context and pluralization (echo)
     */
    public static function _enx(string $single, string $plural, int $number, string $context, string $domain = self::TEXT_DOMAIN): void
    {
        _enx($single, $plural, $number, $context, $domain);
    }

    /**
     * String translation with sprintf
     */
    public static function sprintf(string $text, ...$args): string
    {
        return sprintf(self::__($text), ...$args);
    }

    /**
     * String translation with sprintf (escaped)
     */
    public static function esc_html_sprintf(string $text, ...$args): string
    {
        return sprintf(self::esc_html__($text), ...$args);
    }

    /**
     * String translation with sprintf (attribute escaped)
     */
    public static function esc_attr_sprintf(string $text, ...$args): string
    {
        return sprintf(self::esc_attr__($text), ...$args);
    }

    /**
     * String translation with sprintf (echo)
     */
    public static function printf(string $text, ...$args): void
    {
        printf(self::__($text), ...$args);
    }

    /**
     * String translation with sprintf (escaped, echo)
     */
    public static function esc_html_printf(string $text, ...$args): void
    {
        printf(self::esc_html__($text), ...$args);
    }

    /**
     * String translation with sprintf (attribute escaped, echo)
     */
    public static function esc_attr_printf(string $text, ...$args): void
    {
        printf(self::esc_attr__($text), ...$args);
    }

    /**
     * Error message translation
     */
    public static function error(string $text, ...$args): string
    {
        return self::sprintf($text, ...$args);
    }

    /**
     * Success message translation
     */
    public static function success(string $text, ...$args): string
    {
        return self::sprintf($text, ...$args);
    }

    /**
     * Warning message translation
     */
    public static function warning(string $text, ...$args): string
    {
        return self::sprintf($text, ...$args);
    }

    /**
     * Info message translation
     */
    public static function info(string $text, ...$args): string
    {
        return self::sprintf($text, ...$args);
    }

    /**
     * Button text translation
     */
    public static function button(string $text, ...$args): string
    {
        return self::sprintf($text, ...$args);
    }

    /**
     * Label text translation
     */
    public static function label(string $text, ...$args): string
    {
        return self::sprintf($text, ...$args);
    }

    /**
     * Title text translation
     */
    public static function title(string $text, ...$args): string
    {
        return self::sprintf($text, ...$args);
    }

    /**
     * Description text translation
     */
    public static function description(string $text, ...$args): string
    {
        return self::sprintf($text, ...$args);
    }

    /**
     * Placeholder text translation
     */
    public static function placeholder(string $text, ...$args): string
    {
        return self::sprintf($text, ...$args);
    }

    /**
     * Tooltip text translation
     */
    public static function tooltip(string $text, ...$args): string
    {
        return self::sprintf($text, ...$args);
    }

    /**
     * Meta text translation
     */
    public static function meta(string $text, ...$args): string
    {
        return self::sprintf($text, ...$args);
    }

    /**
     * Status text translation
     */
    public static function status(string $text, ...$args): string
    {
        return self::sprintf($text, ...$args);
    }

    /**
     * Action text translation
     */
    public static function action(string $text, ...$args): string
    {
        return self::sprintf($text, ...$args);
    }

    /**
     * Common strings
     */
    public static function common(): array
    {
        return [
            'save' => self::__('Save'),
            'cancel' => self::__('Cancel'),
            'delete' => self::__('Delete'),
            'edit' => self::__('Edit'),
            'view' => self::__('View'),
            'add' => self::__('Add'),
            'remove' => self::__('Remove'),
            'search' => self::__('Search'),
            'filter' => self::__('Filter'),
            'export' => self::__('Export'),
            'import' => self::__('Import'),
            'yes' => self::__('Yes'),
            'no' => self::__('No'),
            'ok' => self::__('OK'),
            'close' => self::__('Close'),
            'loading' => self::__('Loading...'),
            'error' => self::__('Error'),
            'success' => self::__('Success'),
            'warning' => self::__('Warning'),
            'info' => self::__('Information'),
        ];
    }

    /**
     * Validation messages
     */
    public static function validation(): array
    {
        return [
            'required' => self::__('This field is required.'),
            'email' => self::__('Please enter a valid email address.'),
            'phone' => self::__('Please enter a valid phone number.'),
            'numeric' => self::__('Please enter numeric value only.'),
            'min_length' => self::__('Must be at least %d characters.'),
            'max_length' => self::__('Must be at most %d characters.'),
            'min_value' => self::__('Must be at least %d.'),
            'max_value' => self::__('Must be at most %d.'),
        ];
    }

    /**
     * Error messages
     */
    public static function errors(): array
    {
        return [
            'permission_denied' => self::__('You do not have permission to perform this action.'),
            'invalid_request' => self::__('Invalid request.'),
            'not_found' => self::__('Record not found.'),
            'already_exists' => self::__('This record already exists.'),
            'database_error' => self::__('Database error occurred.'),
            'network_error' => self::__('Network error occurred.'),
            'timeout' => self::__('Operation timed out.'),
            'unknown_error' => self::__('An unknown error occurred.'),
        ];
    }
}
