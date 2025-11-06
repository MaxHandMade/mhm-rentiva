<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Settings\Groups;

if (!defined('ABSPATH')) {
    exit;
}

final class AddonSettings
{
    public const SECTION_ID = 'mhm_rentiva_addons_section';
    public const SECTION_TITLE = 'Additional Services Settings';
    public const SECTION_DESCRIPTION = 'Configure general settings for additional services.';

    /**
     * Safe sanitize text field that handles null values
     */
    public static function sanitize_text_field_safe($value)
    {
        if ($value === null || $value === '') {
            return '';
        }
        return sanitize_text_field((string) $value);
    }

    public static function register(): void
    {
        // Additional Services Section
        add_settings_section(
            self::SECTION_ID,
            self::SECTION_TITLE,
            [self::class, 'render_section_description'],
            'mhm_rentiva_settings'
        );

        
        // Additional services settings - Lite Version Additional Service Limit



        // Default Prices - Removed (now edited in table)

        // Additional Settings
        add_settings_field(
            'mhm_rentiva_addon_require_confirmation',
            __('Require Confirmation for Additional Services', 'mhm-rentiva'),
            [self::class, 'render_require_confirmation_field'],
            'mhm_rentiva_settings',
            self::SECTION_ID
        );

        add_settings_field(
            'mhm_rentiva_addon_show_prices_in_calendar',
            __('Show Additional Service Prices in Calendar', 'mhm-rentiva'),
            [self::class, 'render_show_prices_field'],
            'mhm_rentiva_settings',
            self::SECTION_ID
        );

        add_settings_field(
            'mhm_rentiva_addon_display_order',
            __('Additional Services Display Order', 'mhm-rentiva'),
            [self::class, 'render_display_order_field'],
            'mhm_rentiva_settings',
            self::SECTION_ID
        );

        add_settings_field(
            'mhm_rentiva_addon_tax_inclusive',
            __('Prices Include Tax', 'mhm-rentiva'),
            [self::class, 'render_tax_inclusive_field'],
            'mhm_rentiva_settings',
            self::SECTION_ID
        );

        add_settings_field(
            'mhm_rentiva_addon_tax_rate',
            __('Tax Rate (%)', 'mhm-rentiva'),
            [self::class, 'render_tax_rate_field'],
            'mhm_rentiva_settings',
            self::SECTION_ID
        );
    }

    /**
     * Section description
     */
    public static function render_section_description(): void
    {
        echo '<p>' . esc_html(self::SECTION_DESCRIPTION) . '</p>';
        echo '<div class="notice notice-info inline" style="margin: 10px 0;">';
        echo '<p><strong>' . esc_html__('Note:', 'mhm-rentiva') . '</strong> ';
        echo esc_html__('These settings determine the general behavior of additional services. Use the "Additional Services" page from the left menu to add, edit, or delete additional services.', 'mhm-rentiva');
        echo '</p></div>';
    }





    /**
     * Require confirmation field
     */
    public static function render_require_confirmation_field(): void
    {
        $value = self::sanitize_text_field_safe(get_option('mhm_rentiva_addon_require_confirmation', '0'));
        echo '<label><input type="checkbox" name="mhm_rentiva_settings[mhm_rentiva_addon_require_confirmation]" value="1"' . checked($value, '1', false) . '> ' . esc_html__('Yes, confirmation required', 'mhm-rentiva') . '</label>';
        echo '<p class="description">' . esc_html__('Require additional confirmation when customers select additional services.', 'mhm-rentiva') . '</p>';
    }

    /**
     * Show prices in calendar field
     */
    public static function render_show_prices_field(): void
    {
        $value = self::sanitize_text_field_safe(get_option('mhm_rentiva_addon_show_prices_in_calendar', '1'));
        echo '<label><input type="checkbox" name="mhm_rentiva_settings[mhm_rentiva_addon_show_prices_in_calendar]" value="1"' . checked($value, '1', false) . '> ' . esc_html__('Yes, show', 'mhm-rentiva') . '</label>';
        echo '<p class="description">' . esc_html__('Display additional service prices in the booking calendar.', 'mhm-rentiva') . '</p>';
    }

    /**
     * Display order field
     */
    public static function render_display_order_field(): void
    {
        $value = self::sanitize_text_field_safe(get_option('mhm_rentiva_addon_display_order', 'menu_order'));
        $options = [
            'menu_order' => __('Menu Order (Default)', 'mhm-rentiva'),
            'title' => __('Title (A-Z)', 'mhm-rentiva'),
            'price_asc' => __('Price (Low to High)', 'mhm-rentiva'),
            'price_desc' => __('Price (High to Low)', 'mhm-rentiva'),
            'date_created' => __('Creation Date', 'mhm-rentiva'),
        ];

        // Validate the value against allowed options
        if (!array_key_exists($value, $options)) {
            $value = 'menu_order';
        }

        echo '<select name="mhm_rentiva_settings[mhm_rentiva_addon_display_order]">';
        foreach ($options as $key => $label) {
            echo '<option value="' . esc_attr($key) . '"' . selected($value, $key, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . esc_html__('The order in which additional services are displayed to customers.', 'mhm-rentiva') . '</p>';
    }

    /**
     * Tax inclusive field
     */
    public static function render_tax_inclusive_field(): void
    {
        $value = self::sanitize_text_field_safe(get_option('mhm_rentiva_addon_tax_inclusive', '1'));
        echo '<label><input type="checkbox" name="mhm_rentiva_settings[mhm_rentiva_addon_tax_inclusive]" value="1"' . checked($value, '1', false) . '> ' . esc_html__('Yes, tax included', 'mhm-rentiva') . '</label>';
        echo '<p class="description">' . esc_html__('Display additional service prices with tax included.', 'mhm-rentiva') . '</p>';
    }

    /**
     * Tax rate field
     */
    public static function render_tax_rate_field(): void
    {
        $value = floatval(get_option('mhm_rentiva_addon_tax_rate', '20.00'));
        
        // Validate and sanitize the tax rate
        $value = max(0, min(100, $value));
        
        echo '<input type="number" name="mhm_rentiva_settings[mhm_rentiva_addon_tax_rate]" value="' . esc_attr($value) . '" min="0" max="100" step="0.01" class="small-text" /> %';
        echo '<p class="description">' . esc_html__('The applicable tax rate for additional services.', 'mhm-rentiva') . '</p>';
    }
}
