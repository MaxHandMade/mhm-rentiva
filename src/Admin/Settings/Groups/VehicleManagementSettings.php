<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Settings\Groups;

use MHMRentiva\Admin\Vehicle\Helpers\VehicleFeatureHelper;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Vehicle Management Settings
 * 
 * Vehicle pricing, display, and availability settings
 * 
 * @since 4.0.0
 */
final class VehicleManagementSettings
{
    public const SECTION_PRICING = 'mhm_rentiva_vehicle_pricing_section';
    public const SECTION_AVAILABILITY = 'mhm_rentiva_vehicle_availability_section';

    /**
     * Get default settings
     *
     * @return array
     */
    public static function get_default_settings(): array
    {
        return [
            'mhm_rentiva_vehicle_base_price'           => 1.0,
            'mhm_rentiva_vehicle_weekend_multiplier'   => 1.2,
            'mhm_rentiva_vehicle_tax_inclusive'        => '0',
            'mhm_rentiva_vehicle_tax_rate'             => 18,
            'mhm_rentiva_vehicle_tax_rate'             => 18,
            'mhm_rentiva_vehicle_min_rental_days'      => 1,
            'mhm_rentiva_vehicle_max_rental_days'      => 30,
            'mhm_rentiva_vehicle_advance_booking_days' => 365,
            'mhm_rentiva_vehicle_allow_same_day'       => '1',
        ];
    }

    /**
     * Render the vehicle settings section
     */
    public static function render_settings_section(): void
    {
        // Vehicle Pricing Settings
        \MHMRentiva\Admin\Settings\SettingsView::render_section_clean(self::SECTION_PRICING);

        // Vehicle Availability Settings
        \MHMRentiva\Admin\Settings\SettingsView::render_section_clean(self::SECTION_AVAILABILITY);
    }
    /**
     * Register settings
     */
    public static function register(): void
    {
        self::register_settings();
    }

    /**
     * Register all vehicle management settings
     */
    public static function register_settings(): void
    {
        // Vehicle Pricing Section
        add_settings_section(
            'mhm_rentiva_vehicle_pricing_section',
            __('Vehicle Pricing Settings', 'mhm-rentiva'),
            [self::class, 'render_pricing_section_description'],
            'mhm_rentiva_settings'
        );

        // Vehicle Availability Section
        add_settings_section(
            'mhm_rentiva_vehicle_availability_section',
            __('Vehicle Availability Settings', 'mhm-rentiva'),
            [self::class, 'render_availability_section_description'],
            'mhm_rentiva_settings'
        );

        // Pricing Fields
        add_settings_field(
            'mhm_rentiva_vehicle_base_price',
            __('Base Price Multiplier', 'mhm-rentiva'),
            [self::class, 'render_base_price_field'],
            'mhm_rentiva_settings',
            'mhm_rentiva_vehicle_pricing_section'
        );

        add_settings_field(
            'mhm_rentiva_vehicle_weekend_multiplier',
            __('Weekend Price Multiplier', 'mhm-rentiva'),
            [self::class, 'render_weekend_multiplier_field'],
            'mhm_rentiva_settings',
            'mhm_rentiva_vehicle_pricing_section'
        );

        add_settings_field(
            'mhm_rentiva_vehicle_tax_inclusive',
            __('Tax Inclusive Pricing', 'mhm-rentiva'),
            [self::class, 'render_tax_inclusive_field'],
            'mhm_rentiva_settings',
            'mhm_rentiva_vehicle_pricing_section'
        );

        add_settings_field(
            'mhm_rentiva_vehicle_tax_rate',
            __('Tax Rate (%)', 'mhm-rentiva'),
            [self::class, 'render_tax_rate_field'],
            'mhm_rentiva_settings',
            'mhm_rentiva_vehicle_pricing_section'
        );

        // Availability Fields
        add_settings_field(
            'mhm_rentiva_vehicle_min_rental_days',
            __('Minimum Rental Days', 'mhm-rentiva'),
            [self::class, 'render_min_rental_days_field'],
            'mhm_rentiva_settings',
            'mhm_rentiva_vehicle_availability_section'
        );

        add_settings_field(
            'mhm_rentiva_vehicle_max_rental_days',
            __('Maximum Rental Days', 'mhm-rentiva'),
            [self::class, 'render_max_rental_days_field'],
            'mhm_rentiva_settings',
            'mhm_rentiva_vehicle_availability_section'
        );

        add_settings_field(
            'mhm_rentiva_vehicle_advance_booking_days',
            __('Advance Booking Days', 'mhm-rentiva'),
            [self::class, 'render_advance_booking_days_field'],
            'mhm_rentiva_settings',
            'mhm_rentiva_vehicle_availability_section'
        );

        add_settings_field(
            'mhm_rentiva_vehicle_allow_same_day',
            __('Allow Same Day Booking', 'mhm-rentiva'),
            [self::class, 'render_allow_same_day_field'],
            'mhm_rentiva_settings',
            'mhm_rentiva_vehicle_availability_section'
        );
    }

    /**
     * Pricing section description
     */
    public static function render_pricing_section_description(): void
    {
        echo '<p>' . esc_html__('Configure vehicle pricing rules, multipliers, and tax settings.', 'mhm-rentiva') . '</p>';
    }

    /**
     * Availability section description
     */
    public static function render_availability_section_description(): void
    {
        echo '<p>' . esc_html__('Configure vehicle availability rules and booking restrictions.', 'mhm-rentiva') . '</p>';
    }

    /**
     * Base price field
     */
    public static function render_base_price_field(): void
    {
        $value = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_vehicle_base_price', 1.0);
        echo '<input type="number" name="mhm_rentiva_settings[mhm_rentiva_vehicle_base_price]" value="' . esc_attr($value) . '" step="0.01" min="0" class="regular-text" />';
        echo '<p class="description">' . esc_html__('Base price multiplier for all vehicles (1.0 = normal price)', 'mhm-rentiva') . '</p>';
    }

    /**
     * Weekend multiplier field
     */
    public static function render_weekend_multiplier_field(): void
    {
        $value = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_vehicle_weekend_multiplier', 1.2);
        echo '<input type="number" name="mhm_rentiva_settings[mhm_rentiva_vehicle_weekend_multiplier]" value="' . esc_attr($value) . '" step="0.01" min="0" class="regular-text" />';
        echo '<p class="description">' . esc_html__('Weekend price multiplier (1.2 = 20% increase)', 'mhm-rentiva') . '</p>';
    }

    /**
     * Tax inclusive field
     */
    public static function render_tax_inclusive_field(): void
    {
        if (class_exists('WooCommerce')) {
            echo '<p class="description">' . esc_html__('Tax settings are managed by WooCommerce.', 'mhm-rentiva') . '</p>';
            return;
        }

        $value = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_vehicle_tax_inclusive', '0');
        echo '<label><input type="checkbox" name="mhm_rentiva_settings[mhm_rentiva_vehicle_tax_inclusive]" value="1"' . checked($value, '1', false) . '> ' . esc_html__('Include tax in displayed prices', 'mhm-rentiva') . '</label>';
    }

    /**
     * Tax rate field
     */
    public static function render_tax_rate_field(): void
    {
        if (class_exists('WooCommerce')) {
            echo '<p class="description">' . esc_html__('Tax rates are managed by WooCommerce settings.', 'mhm-rentiva') . '</p>';
            return;
        }

        $value = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_vehicle_tax_rate', 18);
        echo '<input type="number" name="mhm_rentiva_settings[mhm_rentiva_vehicle_tax_rate]" value="' . esc_attr($value) . '" step="0.01" min="0" max="100" class="regular-text" />';
        echo '<p class="description">' . esc_html__('Tax rate percentage (e.g., 18 for 18%)', 'mhm-rentiva') . '</p>';
    }

    /**
     * Minimum rental days field
     */
    public static function render_min_rental_days_field(): void
    {
        $value = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_vehicle_min_rental_days', 1);
        echo '<input type="number" name="mhm_rentiva_settings[mhm_rentiva_vehicle_min_rental_days]" value="' . esc_attr($value) . '" min="1" class="regular-text" />';
        echo '<p class="description">' . esc_html__('Minimum number of rental days', 'mhm-rentiva') . '</p>';
    }

    /**
     * Maximum rental days field
     */
    public static function render_max_rental_days_field(): void
    {
        $value = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_vehicle_max_rental_days', 30);
        echo '<input type="number" name="mhm_rentiva_settings[mhm_rentiva_vehicle_max_rental_days]" value="' . esc_attr($value) . '" min="1" class="regular-text" />';
        echo '<p class="description">' . esc_html__('Maximum number of rental days', 'mhm-rentiva') . '</p>';
    }

    /**
     * Advance booking days field
     */
    public static function render_advance_booking_days_field(): void
    {
        $value = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_vehicle_advance_booking_days', 365);
        echo '<input type="number" name="mhm_rentiva_settings[mhm_rentiva_vehicle_advance_booking_days]" value="' . esc_attr($value) . '" min="1" class="regular-text" />';
        echo '<p class="description">' . esc_html__('How many days in advance can customers book', 'mhm-rentiva') . '</p>';
    }

    /**
     * Allow same day field
     */
    public static function render_allow_same_day_field(): void
    {
        $value = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_vehicle_allow_same_day', '1');
        echo '<label><input type="checkbox" name="mhm_rentiva_settings[mhm_rentiva_vehicle_allow_same_day]" value="1"' . checked($value, '1', false) . '> ' . esc_html__('Allow same day bookings', 'mhm-rentiva') . '</label>';
    }
}
