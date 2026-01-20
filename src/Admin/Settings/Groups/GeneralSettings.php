<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Settings\Groups;

use MHMRentiva\Admin\Settings\Core\SettingsCore;
use MHMRentiva\Admin\Core\CurrencyHelper;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * General Settings Group
 */
final class GeneralSettings
{
    public const SECTION_GENERAL = 'mhm_rentiva_general_section';
    public const SECTION_SITE_INFO = 'mhm_rentiva_site_info_section';
    public const SECTION_DATETIME = 'mhm_rentiva_datetime_section';

    /**
     * Render the general settings section
     */
    public static function render_settings_section(): void
    {
        // General Section
        \MHMRentiva\Admin\Settings\SettingsView::render_section_clean(self::SECTION_GENERAL);
    }

    /**
     * Get default settings
     * 
     * @return array
     */
    public static function get_default_settings(): array
    {
        // Check for WooCommerce currency
        $currency = function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'USD';

        return [
            'mhm_rentiva_currency'            => $currency,
            'mhm_rentiva_currency_position'   => 'right_space',
            'mhm_rentiva_brand_name'          => get_bloginfo('name'),
            'mhm_rentiva_support_email'       => get_option('admin_email'),
            'mhm_rentiva_contact_phone'       => '',
            'mhm_rentiva_contact_hours'       => __('09:00 - 18:00', 'mhm-rentiva'),
            'mhm_rentiva_dark_mode'           => 'auto',
        ];
    }

    /**
     * Register general settings
     */
    public static function register(): void
    {
        // 1. General Section
        add_settings_section(
            self::SECTION_GENERAL,
            '',
            '__return_false',
            SettingsCore::PAGE
        );

        add_settings_field(
            'mhm_rentiva_currency',
            __('Currency', 'mhm-rentiva'),
            [self::class, 'render_currency_field'],
            SettingsCore::PAGE,
            self::SECTION_GENERAL
        );

        add_settings_field(
            'mhm_rentiva_currency_position',
            __('Currency Position', 'mhm-rentiva'),
            [self::class, 'render_currency_position_field'],
            SettingsCore::PAGE,
            self::SECTION_GENERAL
        );

        add_settings_field(
            'mhm_rentiva_brand_name',
            __('Brand Name', 'mhm-rentiva'),
            [self::class, 'render_brand_name_field'],
            SettingsCore::PAGE,
            self::SECTION_GENERAL
        );

        add_settings_field(
            'mhm_rentiva_support_email',
            __('Support Email', 'mhm-rentiva'),
            [self::class, 'render_support_email_field'],
            SettingsCore::PAGE,
            self::SECTION_GENERAL
        );

        add_settings_field(
            'mhm_rentiva_contact_phone',
            __('Contact Phone', 'mhm-rentiva'),
            [self::class, 'render_contact_phone_field'],
            SettingsCore::PAGE,
            self::SECTION_GENERAL
        );

        add_settings_field(
            'mhm_rentiva_contact_hours',
            __('Support Hours', 'mhm-rentiva'),
            [self::class, 'render_contact_hours_field'],
            SettingsCore::PAGE,
            self::SECTION_GENERAL
        );

        add_settings_field(
            'mhm_rentiva_dark_mode',
            __('Dark Mode', 'mhm-rentiva'),
            [self::class, 'render_dark_mode_field'],
            SettingsCore::PAGE,
            self::SECTION_GENERAL
        );
    }

    /**
     * General Settings Section Description
     */
    public static function render_section_description(): void
    {
        echo '<p>' . esc_html__('Configure general system settings.', 'mhm-rentiva') . '</p>';
    }


    /**
     * Currency Field
     */
    public static function render_currency_field(): void
    {
        if (class_exists('WooCommerce') && function_exists('get_woocommerce_currency')) {
            echo '<p class="description"><strong>' . esc_html__('Managed by WooCommerce:', 'mhm-rentiva') . '</strong> ' . call_user_func('get_woocommerce_currency') . '</p>';
            return;
        }

        $currency = SettingsCore::get('mhm_rentiva_currency', 'USD');
        // Use centralized currency list from CurrencyHelper
        $currencies = class_exists('\MHMRentiva\Admin\Core\CurrencyHelper')
            ? \MHMRentiva\Admin\Core\CurrencyHelper::get_currency_list_for_dropdown()
            : ['USD' => 'US Dollar', 'EUR' => 'Euro', 'TRY' => 'Turkish Lira'];

        echo '<select name="mhm_rentiva_settings[mhm_rentiva_currency]">';
        foreach ($currencies as $code => $name) {
            echo '<option value="' . esc_attr($code) . '"' . selected($currency, $code, false) . '>' . esc_html($name) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . esc_html__('Currency to be used throughout the system.', 'mhm-rentiva') . '</p>';
    }

    /**
     * Currency Position Field
     */
    public static function render_currency_position_field(): void
    {
        if (class_exists('WooCommerce')) {
            echo '<p class="description">' . esc_html__('Currency position is managed by WooCommerce settings.', 'mhm-rentiva') . '</p>';
            return;
        }

        $position = SettingsCore::get('mhm_rentiva_currency_position', 'right_space');
        $positions = [
            'left' => __('Left ($100)', 'mhm-rentiva'),
            'left_space' => __('Left Space ($ 100)', 'mhm-rentiva'),
            'right' => __('Right (100$)', 'mhm-rentiva'),
            'right_space' => __('Right Space (100 $)', 'mhm-rentiva')
        ];

        echo '<select name="mhm_rentiva_settings[mhm_rentiva_currency_position]">';
        foreach ($positions as $pos => $name) {
            echo '<option value="' . esc_attr($pos) . '"' . selected($position, $pos, false) . '>' . esc_html($name) . '</option>';
        }
        echo '</select>';
    }

    /**
     * Dark Mode Field
     */
    public static function render_dark_mode_field(): void
    {
        $mode = get_option('mhm_rentiva_dark_mode', 'auto');
        $options = [
            'auto' => __('Auto (System)', 'mhm-rentiva'),
            'light' => __('Light', 'mhm-rentiva'),
            'dark' => __('Dark', 'mhm-rentiva'),
        ];

        echo '<select name="mhm_rentiva_dark_mode">';
        foreach ($options as $key => $label) {
            echo '<option value="' . esc_attr($key) . '"' . selected($mode, $key, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . esc_html__('Select admin panel color scheme.', 'mhm-rentiva') . '</p>';
    }

    /**
     * Support Email Field
     */
    public static function render_support_email_field(): void
    {
        $value = SettingsCore::get('mhm_rentiva_support_email', 'destek@maxhandmade.com');
        echo '<input type="email" name="mhm_rentiva_settings[mhm_rentiva_support_email]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . esc_html__('Email address to be used for customer support', 'mhm-rentiva') . '</p>';
    }

    /**
     * Contact Phone Field
     */
    public static function render_contact_phone_field(): void
    {
        $phone = SettingsCore::get('mhm_rentiva_contact_phone', '+90 555 555 55 55');
        echo '<input type="text" name="mhm_rentiva_settings[mhm_rentiva_contact_phone]" value="' . esc_attr($phone) . '" class="regular-text" />';
        echo '<p class="description">' . esc_html__('Phone number for contact form footer.', 'mhm-rentiva') . '</p>';
    }

    /**
     * Contact Hours Field
     */
    public static function render_contact_hours_field(): void
    {
        $hours = SettingsCore::get('mhm_rentiva_contact_hours', __('7/24 Support', 'mhm-rentiva'));
        echo '<input type="text" name="mhm_rentiva_settings[mhm_rentiva_contact_hours]" value="' . esc_attr($hours) . '" class="regular-text" />';
        echo '<p class="description">' . esc_html__('Working hours text for contact form footer.', 'mhm-rentiva') . '</p>';
    }

    /**
     * Brand Name Field
     */
    public static function render_brand_name_field(): void
    {
        $brand = SettingsCore::get('mhm_rentiva_brand_name', get_bloginfo('name'));
        echo '<input type="text" name="mhm_rentiva_settings[mhm_rentiva_brand_name]" value="' . esc_attr($brand) . '" class="regular-text" />';
        echo '<p class="description">' . esc_html__('Brand name to appear in emails and documents.', 'mhm-rentiva') . '</p>';
    }
}
