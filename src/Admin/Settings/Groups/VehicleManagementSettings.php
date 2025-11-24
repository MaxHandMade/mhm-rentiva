<?php declare(strict_types=1);

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

        // Vehicle Display Section
        add_settings_section(
            'mhm_rentiva_vehicle_display_section',
            __('Vehicle Display Settings', 'mhm-rentiva'),
            [self::class, 'render_display_section_description'],
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

        // Display Fields
        add_settings_field(
            'mhm_rentiva_vehicle_cards_per_page',
            __('Vehicles Per Page', 'mhm-rentiva'),
            [self::class, 'render_cards_per_page_field'],
            'mhm_rentiva_settings',
            'mhm_rentiva_vehicle_display_section'
        );

        add_settings_field(
            'mhm_rentiva_vehicle_default_sort',
            __('Default Sort Order', 'mhm-rentiva'),
            [self::class, 'render_default_sort_field'],
            'mhm_rentiva_settings',
            'mhm_rentiva_vehicle_display_section'
        );

        add_settings_field(
            'mhm_rentiva_vehicle_show_images',
            __('Show Vehicle Images', 'mhm-rentiva'),
            [self::class, 'render_show_images_field'],
            'mhm_rentiva_settings',
            'mhm_rentiva_vehicle_display_section'
        );

        add_settings_field(
            'mhm_rentiva_vehicle_show_features',
            __('Show Vehicle Features', 'mhm-rentiva'),
            [self::class, 'render_show_features_field'],
            'mhm_rentiva_settings',
            'mhm_rentiva_vehicle_display_section'
        );

        add_settings_field(
            'mhm_rentiva_vehicle_card_fields',
            __('Visible Card Items', 'mhm-rentiva'),
            [self::class, 'render_card_fields_field'],
            'mhm_rentiva_settings',
            'mhm_rentiva_vehicle_display_section'
        );

        add_settings_field(
            'mhm_rentiva_vehicle_show_availability',
            __('Show Availability Status', 'mhm-rentiva'),
            [self::class, 'render_show_availability_field'],
            'mhm_rentiva_settings',
            'mhm_rentiva_vehicle_display_section'
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
     * Display section description
     */
    public static function render_display_section_description(): void
    {
        echo '<p>' . esc_html__('Configure how vehicles are displayed on the frontend.', 'mhm-rentiva') . '</p>';
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
     * Cards per page field
     */
    public static function render_cards_per_page_field(): void
    {
        $value = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_vehicle_cards_per_page', 12);
        echo '<input type="number" name="mhm_rentiva_settings[mhm_rentiva_vehicle_cards_per_page]" value="' . esc_attr($value) . '" min="1" max="50" class="regular-text" />';
        echo '<p class="description">' . esc_html__('Number of vehicles to display per page', 'mhm-rentiva') . '</p>';
    }

    /**
     * Default sort field
     */
    public static function render_default_sort_field(): void
    {
        $value = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_vehicle_default_sort', 'price_asc');
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
        $value = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_vehicle_show_images', '1');
        echo '<label><input type="checkbox" name="mhm_rentiva_settings[mhm_rentiva_vehicle_show_images]" value="1"' . checked($value, '1', false) . '> ' . esc_html__('Display vehicle images in listings', 'mhm-rentiva') . '</label>';
    }

    /**
     * Show features field
     */
    public static function render_show_features_field(): void
    {
        $value = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_vehicle_show_features', '1');
        echo '<label><input type="checkbox" name="mhm_rentiva_settings[mhm_rentiva_vehicle_show_features]" value="1"' . checked($value, '1', false) . '> ' . esc_html__('Display vehicle features in listings', 'mhm-rentiva') . '</label>';
    }

    /**
     * Feature selection field
     */
    public static function render_card_fields_field(): void
    {
        $available_map = VehicleFeatureHelper::get_available_fields_map();
        $selected      = VehicleFeatureHelper::get_selected_card_fields();

        // Build lookup for quick label resolution.
        $available_flat = [];
        foreach ($available_map as $type => $fields) {
            foreach ($fields as $key => $field) {
                $available_flat[$type . ':' . $key] = $field;
            }
        }

        $selected_items = [];
        foreach ($selected as $item) {
            $id = $item['type'] . ':' . $item['key'];
            if (!isset($available_flat[$id])) {
                continue;
            }
            $selected_items[] = [
                'type' => $item['type'],
                'key'  => $item['key'],
                'label'=> $available_flat[$id]['label'],
            ];
            unset($available_flat[$id]);
        }

        $available_items = [];
        foreach ($available_flat as $id => $data) {
            $available_items[] = [
                'type'  => $data['type'],
                'key'   => $data['key'] ?? $id,
                'label' => $data['label'],
            ];
        }

        $hidden_value = esc_attr(wp_json_encode($selected));

        echo '<div class="mhm-card-fields-wrapper">';
        echo '<input type="hidden" id="mhm-vehicle-card-fields-input" name="mhm_rentiva_settings[mhm_rentiva_vehicle_card_fields]" value="' . $hidden_value . '" />';

        echo '<div class="mhm-card-fields-columns">';

        echo '<div class="mhm-card-fields-column">';
        echo '<h4>' . esc_html__('Visible Items', 'mhm-rentiva') . '</h4>';
        echo '<p class="description">' . esc_html__('Drag to reorder or click to remove items from the vehicle card.', 'mhm-rentiva') . '</p>';
        echo '<ul id="mhm-card-fields-selected" class="mhm-card-fields-list">';
        if (!empty($selected_items)) {
            foreach ($selected_items as $item) {
                echo self::render_card_field_list_item($item['type'], $item['key'], $item['label'], true);
            }
        }
        echo '</ul>';
        echo '</div>';

        echo '<div class="mhm-card-fields-column">';
        echo '<h4>' . esc_html__('Available Items', 'mhm-rentiva') . '</h4>';
        echo '<p class="description">' . esc_html__('Drag items here to hide them from the card. Only fields enabled on the Vehicle Settings page are listed.', 'mhm-rentiva') . '</p>';
        echo '<ul id="mhm-card-fields-available" class="mhm-card-fields-list">';
        if (!empty($available_items)) {
            foreach ($available_items as $item) {
                echo self::render_card_field_list_item($item['type'], $item['key'], $item['label'], false);
            }
        }
        echo '</ul>';
        echo '</div>';

        echo '</div>'; // columns

        echo '<p class="description mhm-card-fields-footer">' .
             esc_html__('Tip: The order you set here applies to vehicle grids, list views and the My Account favorites grid.', 'mhm-rentiva') .
             '</p>';

        echo '</div>'; // wrapper
    }

    /**
     * Render a sortable list item.
     */
    private static function render_card_field_list_item(string $type, string $key, string $label, bool $selected): string
    {
        $type  = sanitize_key($type);
        $key   = sanitize_key($key);
        $label = esc_html($label);

        $remove_button = $selected
            ? '<button type="button" class="button-link remove-field" aria-label="' . esc_attr__('Remove item', 'mhm-rentiva') . '">&times;</button>'
            : '';

        return sprintf(
            '<li class="mhm-card-field-item" data-field-type="%1$s" data-field-key="%2$s"><span class="mhm-card-field-label">%3$s</span>%4$s</li>',
            esc_attr($type),
            esc_attr($key),
            $label,
            $remove_button
        );
    }

    /**
     * Show availability field
     */
    public static function render_show_availability_field(): void
    {
        $value = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_vehicle_show_availability', '1');
        echo '<label><input type="checkbox" name="mhm_rentiva_settings[mhm_rentiva_vehicle_show_availability]" value="1"' . checked($value, '1', false) . '> ' . esc_html__('Display availability status in listings', 'mhm-rentiva') . '</label>';
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
