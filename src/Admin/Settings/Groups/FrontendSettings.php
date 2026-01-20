<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Settings\Groups;

use MHMRentiva\Admin\Settings\Core\SettingsCore;
use MHMRentiva\Admin\Vehicle\Helpers\VehicleFeatureHelper;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Frontend & Display Settings
 * 
 * Manages frontend display options, including vehicle card layouts.
 * 
 * @since 4.0.0
 */
final class FrontendSettings
{
    public const SECTION_VEHICLE_DISPLAY = 'mhm_rentiva_vehicle_display_section';


    /**
     * Get default settings
     *
     * @return array
     */
    public static function get_default_settings(): array
    {
        return [
            'mhm_rentiva_vehicle_cards_per_page'       => 12,
            'mhm_rentiva_vehicle_default_sort'         => 'price_asc',
            'mhm_rentiva_vehicle_show_images'          => '1',
            'mhm_rentiva_vehicle_show_features'        => '1',
            'mhm_rentiva_vehicle_card_fields'          => class_exists('\MHMRentiva\Admin\Vehicle\Helpers\VehicleFeatureHelper') ? \MHMRentiva\Admin\Vehicle\Helpers\VehicleFeatureHelper::get_default_card_fields() : [],
            'mhm_rentiva_vehicle_show_availability'    => '1',
        ];
    }

    /**
     * Register settings
     */
    public static function register(): void
    {
        // Vehicle Display Section
        add_settings_section(
            self::SECTION_VEHICLE_DISPLAY,
            __('Vehicle Display Settings', 'mhm-rentiva'),
            [self::class, 'render_display_section_description'],
            SettingsCore::PAGE
        );

        // Display Fields
        add_settings_field(
            'mhm_rentiva_vehicle_cards_per_page',
            __('Vehicles Per Page', 'mhm-rentiva'),
            [self::class, 'render_cards_per_page_field'],
            SettingsCore::PAGE,
            self::SECTION_VEHICLE_DISPLAY
        );

        add_settings_field(
            'mhm_rentiva_vehicle_default_sort',
            __('Default Sort Order', 'mhm-rentiva'),
            [self::class, 'render_default_sort_field'],
            SettingsCore::PAGE,
            self::SECTION_VEHICLE_DISPLAY
        );

        add_settings_field(
            'mhm_rentiva_vehicle_show_images',
            __('Show Vehicle Images', 'mhm-rentiva'),
            [self::class, 'render_show_images_field'],
            SettingsCore::PAGE,
            self::SECTION_VEHICLE_DISPLAY
        );

        add_settings_field(
            'mhm_rentiva_vehicle_show_features',
            __('Show Vehicle Features', 'mhm-rentiva'),
            [self::class, 'render_show_features_field'],
            SettingsCore::PAGE,
            self::SECTION_VEHICLE_DISPLAY
        );



        add_settings_field(
            'mhm_rentiva_vehicle_show_availability',
            __('Show Availability Status', 'mhm-rentiva'),
            [self::class, 'render_show_availability_field'],
            SettingsCore::PAGE,
            self::SECTION_VEHICLE_DISPLAY
        );
    }

    /**
     * Display section description
     */
    public static function render_display_section_description(): void
    {
        echo '<p>' . esc_html__('Configure how vehicles are displayed on the frontend.', 'mhm-rentiva') . '</p>';
    }

    /**
     * Cards per page field
     */
    public static function render_cards_per_page_field(): void
    {
        $value = SettingsCore::get('mhm_rentiva_vehicle_cards_per_page', 12);
        echo '<input type="number" name="mhm_rentiva_settings[mhm_rentiva_vehicle_cards_per_page]" value="' . esc_attr($value) . '" min="1" max="50" class="regular-text" />';
        echo '<p class="description">' . esc_html__('Number of vehicles to display per page', 'mhm-rentiva') . '</p>';
    }

    /**
     * Default sort field
     */
    public static function render_default_sort_field(): void
    {
        $value = SettingsCore::get('mhm_rentiva_vehicle_default_sort', 'price_asc');
        $options = [
            'price_asc' => __('Price: Low to High', 'mhm-rentiva'),
            'price_desc' => __('Price: High to Low', 'mhm-rentiva'),
            'name_asc' => __('Name: A to Z', 'mhm-rentiva'),
            'name_desc' => __('Name: Z to A', 'mhm-rentiva'),
            'year_desc' => __('Year: Newest First', 'mhm-rentiva'),
            'year_asc' => __('Year: Oldest First', 'mhm-rentiva'),
        ];

        echo '<select name="mhm_rentiva_settings[mhm_rentiva_vehicle_default_sort]" class="regular-text">';
        foreach ($options as $option_value => $option_label) {
            echo '<option value="' . esc_attr($option_value) . '" ' . selected($value, $option_value, false) . '>' . esc_html($option_label) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . esc_html__('Default sort order for vehicle listings', 'mhm-rentiva') . '</p>';
    }

    /**
     * Show images field
     */
    public static function render_show_images_field(): void
    {
        $value = SettingsCore::get('mhm_rentiva_vehicle_show_images', '1');
        echo '<label><input type="checkbox" name="mhm_rentiva_settings[mhm_rentiva_vehicle_show_images]" value="1"' . checked($value, '1', false) . '> ' . esc_html__('Display vehicle images in listings', 'mhm-rentiva') . '</label>';
    }

    /**
     * Show features field
     */
    public static function render_show_features_field(): void
    {
        $value = SettingsCore::get('mhm_rentiva_vehicle_show_features', '1');
        echo '<label><input type="checkbox" name="mhm_rentiva_settings[mhm_rentiva_vehicle_show_features]" value="1"' . checked($value, '1', false) . '> ' . esc_html__('Display vehicle features in listings', 'mhm-rentiva') . '</label>';
    }

    /**
     * Show availability field
     */
    public static function render_show_availability_field(): void
    {
        $value = SettingsCore::get('mhm_rentiva_vehicle_show_availability', '1');
        echo '<label><input type="checkbox" name="mhm_rentiva_settings[mhm_rentiva_vehicle_show_availability]" value="1"' . checked($value, '1', false) . '> ' . esc_html__('Display availability status in listings', 'mhm-rentiva') . '</label>';
    }
}
