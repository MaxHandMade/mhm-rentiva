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

        // Site Information Section
        \MHMRentiva\Admin\Settings\SettingsView::render_section_clean(self::SECTION_SITE_INFO);

        // Date & Time Settings Section
        \MHMRentiva\Admin\Settings\SettingsView::render_section_clean(self::SECTION_DATETIME);
    }

    /**
     * Get default settings
     * 
     * @return array
     */
    public static function get_default_settings(): array
    {
        return [
            'mhm_rentiva_currency'            => 'USD',
            'mhm_rentiva_currency_position'   => 'right_space',
            'mhm_rentiva_support_email'       => 'destek@maxhandmade.com',
            'mhm_rentiva_contact_phone'       => '+90 555 555 55 55',
            'mhm_rentiva_contact_hours'       => __('7/24 Support', 'mhm-rentiva'),
            'mhm_rentiva_date_format'         => 'Y-m-d',
            'mhm_rentiva_default_rental_days' => 1,
            'mhm_rentiva_brand_name'          => get_bloginfo('name'),
            'mhm_rentiva_clean_data_on_uninstall' => '0',
            'mhm_rentiva_dark_mode'           => 'auto',

            // Read-only Site Info
            'mhm_rentiva_site_url'            => get_option('siteurl', ''),
            'mhm_rentiva_home_url'            => get_option('home', ''),
            'mhm_rentiva_admin_email'         => get_option('admin_email', ''),
            'mhm_rentiva_site_language'       => get_locale(),
            'mhm_rentiva_start_of_week'       => get_option('start_of_week', 1),
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
            __('General Settings', 'mhm-rentiva'),
            [self::class, 'render_section_description'],
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

        add_settings_field(
            'mhm_rentiva_clean_data_on_uninstall',
            __('Clean Data on Uninstall', 'mhm-rentiva'),
            [self::class, 'render_clean_data_on_uninstall_field'],
            SettingsCore::PAGE,
            self::SECTION_GENERAL
        );

        // 2. Site Information Section
        add_settings_section(
            self::SECTION_SITE_INFO,
            __('Site Information', 'mhm-rentiva'),
            [self::class, 'render_site_info_section_description'],
            SettingsCore::PAGE
        );

        add_settings_field(
            'mhm_rentiva_site_url',
            __('Site URL', 'mhm-rentiva'),
            [self::class, 'render_site_url_field'],
            SettingsCore::PAGE,
            self::SECTION_SITE_INFO
        );

        add_settings_field(
            'mhm_rentiva_home_url',
            __('Home URL', 'mhm-rentiva'),
            [self::class, 'render_home_url_field'],
            SettingsCore::PAGE,
            self::SECTION_SITE_INFO
        );

        // 3. Date & Time Section
        add_settings_section(
            self::SECTION_DATETIME,
            __('Date & Time Settings', 'mhm-rentiva'),
            '__return_null', // No description description
            SettingsCore::PAGE
        );

        add_settings_field(
            'mhm_rentiva_date_format',
            __('Date Format', 'mhm-rentiva'),
            [self::class, 'render_date_format_field'],
            SettingsCore::PAGE,
            self::SECTION_DATETIME
        );

        add_settings_field(
            'mhm_rentiva_default_rental_days',
            __('Default Rental Days', 'mhm-rentiva'),
            [self::class, 'render_default_rental_days_field'],
            SettingsCore::PAGE,
            self::SECTION_DATETIME
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
     * Site Info Section Description
     */
    public static function render_site_info_section_description(): void
    {
        echo '<p>' . esc_html__('Basic site information and configuration settings.', 'mhm-rentiva') . '</p>';
    }

    public static function render_site_url_field(): void
    {
        $value = SettingsCore::get('mhm_rentiva_site_url', get_option('siteurl', ''));
        echo '<input type="url" name="mhm_rentiva_settings[mhm_rentiva_site_url]" value="' . esc_attr($value) . '" class="regular-text" readonly />';
        echo '<p class="description">' . esc_html__('WordPress site URL (read-only)', 'mhm-rentiva') . '</p>';
    }

    public static function render_home_url_field(): void
    {
        $value = SettingsCore::get('mhm_rentiva_home_url', get_option('home', ''));
        echo '<input type="url" name="mhm_rentiva_settings[mhm_rentiva_home_url]" value="' . esc_attr($value) . '" class="regular-text" readonly />';
        echo '<p class="description">' . esc_html__('WordPress home URL (read-only)', 'mhm-rentiva') . '</p>';
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

    /**
     * Clean Data on Uninstall Field
     */
    public static function render_clean_data_on_uninstall_field(): void
    {
        $enabled = SettingsCore::get('mhm_rentiva_clean_data_on_uninstall', '0');
        echo '<label>';
        echo '<input type="checkbox" name="mhm_rentiva_settings[mhm_rentiva_clean_data_on_uninstall]" value="1"' . checked($enabled, '1', false) . '> ';
        echo esc_html__('Clean all plugin data and database tables when the plugin is deleted from WordPress.', 'mhm-rentiva');
        echo '</label>';
        echo '<p class="description">';
        echo '<strong>' . esc_html__('Important:', 'mhm-rentiva') . '</strong> ';
        echo esc_html__('If enabled, when you delete the plugin from WordPress (Plugins > Installed Plugins > Delete), all plugin data including vehicles, bookings, settings, custom tables, and related database records will be permanently removed. This action is irreversible. Make sure you have a backup before enabling this option.', 'mhm-rentiva');
        echo '</p>';
        echo '<div class="notice notice-warning inline" style="margin-top: 10px;">';
        echo '<p><strong>' . esc_html__('⚠️ Warning:', 'mhm-rentiva') . '</strong> ';
        echo esc_html__('This will delete all plugin-related files and database records when the plugin is uninstalled. This cannot be undone.', 'mhm-rentiva');
        echo '</p>';
        echo '</div>';
    }

    /**
     * Date Format Field
     */
    public static function render_date_format_field(): void
    {
        $format = SettingsCore::get('mhm_rentiva_date_format', 'Y-m-d');
        $formats = [
            'Y-m-d' => '2024-01-15',
            'd-m-Y' => '15-01-2024',
            'm/d/Y' => '01/15/2024',
            'd/m/Y' => '15/01/2024'
        ];

        echo '<select name="mhm_rentiva_settings[mhm_rentiva_date_format]">';
        foreach ($formats as $fmt => $example) {
            echo '<option value="' . esc_attr($fmt) . '"' . selected($format, $fmt, false) . '>' . esc_html($example) . '</option>';
        }
        echo '</select>';
    }

    /**
     * Default Rental Days Field
     */
    public static function render_default_rental_days_field(): void
    {
        $days = SettingsCore::get('mhm_rentiva_default_rental_days', 1);
        echo '<input type="number" name="mhm_rentiva_settings[mhm_rentiva_default_rental_days]" value="' . esc_attr($days) . '" min="1" max="365" class="small-text" />';
        echo '<p class="description">' . esc_html__('Default rental duration in booking form (days).', 'mhm-rentiva') . '</p>';
    }
}
