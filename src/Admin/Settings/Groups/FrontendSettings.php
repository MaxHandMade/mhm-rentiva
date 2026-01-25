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
    public const SECTION_TEXTS_LABELS = 'mhm_rentiva_texts_labels_section';

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
     * Render the settings section
     */
    public static function render_settings_section(): void
    {
        if (class_exists('\MHMRentiva\Admin\Settings\View\SettingsViewHelper')) {
            // Vehicle Display Section
            \MHMRentiva\Admin\Settings\View\SettingsViewHelper::render_section_cleanly(self::SECTION_VEHICLE_DISPLAY);

            // Texts & Labels Section
            \MHMRentiva\Admin\Settings\View\SettingsViewHelper::render_section_cleanly(self::SECTION_TEXTS_LABELS);
        }
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

        // Texts & Labels Section (Accordion)
        add_settings_section(
            self::SECTION_TEXTS_LABELS,
            __('Texts & Labels', 'mhm-rentiva'),
            [self::class, 'render_texts_labels_section_description'],
            SettingsCore::PAGE
        );

        // Group 1: General Buttons
        add_settings_field(
            'group_general_buttons',
            __('General Buttons', 'mhm-rentiva'),
            [self::class, 'render_group_general_buttons'],
            SettingsCore::PAGE,
            self::SECTION_TEXTS_LABELS
        );

        // Group 2: Notifications
        add_settings_field(
            'group_notifications',
            __('Notifications', 'mhm-rentiva'),
            [self::class, 'render_group_notifications'],
            SettingsCore::PAGE,
            self::SECTION_TEXTS_LABELS
        );

        // Group 3: Form Labels
        add_settings_field(
            'group_form_labels',
            __('Form Labels', 'mhm-rentiva'),
            [self::class, 'render_group_form_labels'],
            SettingsCore::PAGE,
            self::SECTION_TEXTS_LABELS
        );

        // Group 4: Validation & Selection
        add_settings_field(
            'group_validation_selection',
            __('Validation & Selection', 'mhm-rentiva'),
            [self::class, 'render_group_validation_selection'],
            SettingsCore::PAGE,
            self::SECTION_TEXTS_LABELS
        );

        // Group 5: Payment Messages
        add_settings_field(
            'group_payment_messages',
            __('Payment Messages', 'mhm-rentiva'),
            [self::class, 'render_group_payment_messages'],
            SettingsCore::PAGE,
            self::SECTION_TEXTS_LABELS
        );

        // Group 6: Account & Navigation
        add_settings_field(
            'group_account_navigation',
            __('Account & Navigation', 'mhm-rentiva'),
            [self::class, 'render_group_account_navigation'],
            SettingsCore::PAGE,
            self::SECTION_TEXTS_LABELS
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
        echo '<input type="hidden" name="mhm_rentiva_settings[mhm_rentiva_vehicle_show_images]" value="0">';
        echo '<label><input type="checkbox" name="mhm_rentiva_settings[mhm_rentiva_vehicle_show_images]" value="1"' . checked($value, '1', false) . '> ' . esc_html__('Display vehicle images in listings', 'mhm-rentiva') . '</label>';
    }

    /**
     * Show features field
     */
    public static function render_show_features_field(): void
    {
        $value = SettingsCore::get('mhm_rentiva_vehicle_show_features', '1');
        echo '<input type="hidden" name="mhm_rentiva_settings[mhm_rentiva_vehicle_show_features]" value="0">';
        echo '<label><input type="checkbox" name="mhm_rentiva_settings[mhm_rentiva_vehicle_show_features]" value="1"' . checked($value, '1', false) . '> ' . esc_html__('Display vehicle features in listings', 'mhm-rentiva') . '</label>';
    }

    /**
     * Show availability field
     */
    public static function render_show_availability_field(): void
    {
        $value = SettingsCore::get('mhm_rentiva_vehicle_show_availability', '1');
        echo '<input type="hidden" name="mhm_rentiva_settings[mhm_rentiva_vehicle_show_availability]" value="0">';
        echo '<label><input type="checkbox" name="mhm_rentiva_settings[mhm_rentiva_vehicle_show_availability]" value="1"' . checked($value, '1', false) . '> ' . esc_html__('Display availability status in listings', 'mhm-rentiva') . '</label>';
    }

    /**
     * Texts & Labels section description
     */
    public static function render_texts_labels_section_description(): void
    {
        echo '<p>' . esc_html__('Customize the text labels and button texts displayed throughout the frontend. Leave empty to use default values.', 'mhm-rentiva') . '</p>';
    }

    /**
     * Render Group: General Buttons
     */
    public static function render_group_general_buttons(): void
    {
        echo '<div class="mhm-accordion-group">';
        echo '<div class="mhm-accordion-header" id="accordion-general-buttons">';
        echo '<span>' . esc_html__('General Buttons', 'mhm-rentiva') . '</span>';
        echo '<span class="dashicons dashicons-arrow-down"></span>';
        echo '</div>';
        echo '<div class="mhm-accordion-content">';

        self::render_input_field('mhm_rentiva_text_book_now', __('Book Now Button', 'mhm-rentiva'), __('Book Now', 'mhm-rentiva'));
        self::render_input_field('mhm_rentiva_text_view_details', __('View Details Button', 'mhm-rentiva'), __('View Details', 'mhm-rentiva'));
        self::render_input_field('mhm_rentiva_text_make_booking', __('Make Booking Button', 'mhm-rentiva'), __('Make Booking', 'mhm-rentiva'));
        self::render_input_field('mhm_rentiva_text_cancel_booking', __('Cancel Booking Button', 'mhm-rentiva'), __('Cancel Booking', 'mhm-rentiva'));

        echo '</div></div>';
    }

    /**
     * Render Group: Notifications
     */
    public static function render_group_notifications(): void
    {
        echo '<div class="mhm-accordion-group">';
        echo '<div class="mhm-accordion-header" id="accordion-notifications">';
        echo '<span>' . esc_html__('Notifications', 'mhm-rentiva') . '</span>';
        echo '<span class="dashicons dashicons-arrow-down"></span>';
        echo '</div>';
        echo '<div class="mhm-accordion-content">';

        self::render_input_field('mhm_rentiva_text_added_to_favorites', __('Added to Favorites Message', 'mhm-rentiva'), __('Added to favorites', 'mhm-rentiva'));
        self::render_input_field('mhm_rentiva_text_removed_from_favorites', __('Removed from Favorites Message', 'mhm-rentiva'), __('Removed from favorites', 'mhm-rentiva'));
        self::render_input_field('mhm_rentiva_text_login_required', __('Login Required Message', 'mhm-rentiva'), __('You must be logged in to add to favorites', 'mhm-rentiva'));
        self::render_input_field('mhm_rentiva_text_login_here', __('Login Here Text', 'mhm-rentiva'), __('Login Here', 'mhm-rentiva'));
        self::render_input_field('mhm_rentiva_text_processing', __('Processing Message', 'mhm-rentiva'), __('Processing', 'mhm-rentiva'));
        self::render_input_field('mhm_rentiva_text_loading', __('Loading Message', 'mhm-rentiva'), __('Loading', 'mhm-rentiva'));
        self::render_input_field('mhm_rentiva_text_error', __('Error Message', 'mhm-rentiva'), __('Error', 'mhm-rentiva'));
        self::render_input_field('mhm_rentiva_text_booking_success', __('Booking Success Message', 'mhm-rentiva'), __('Booking Success', 'mhm-rentiva'));

        echo '</div></div>';
    }

    /**
     * Render Group: Form Labels
     */
    public static function render_group_form_labels(): void
    {
        echo '<div class="mhm-accordion-group">';
        echo '<div class="mhm-accordion-header" id="accordion-form-labels">';
        echo '<span>' . esc_html__('Form Labels', 'mhm-rentiva') . '</span>';
        echo '<span class="dashicons dashicons-arrow-down"></span>';
        echo '</div>';
        echo '<div class="mhm-accordion-content">';

        self::render_input_field('mhm_rentiva_text_first_name', __('First Name Label', 'mhm-rentiva'), __('First Name', 'mhm-rentiva'));
        self::render_input_field('mhm_rentiva_text_last_name', __('Last Name Label', 'mhm-rentiva'), __('Last Name', 'mhm-rentiva'));
        self::render_input_field('mhm_rentiva_text_email', __('Email Label', 'mhm-rentiva'), __('Email', 'mhm-rentiva'));
        self::render_input_field('mhm_rentiva_text_phone', __('Phone Label', 'mhm-rentiva'), __('Phone', 'mhm-rentiva'));

        echo '</div></div>';
    }

    /**
     * Render Group: Validation & Selection
     */
    public static function render_group_validation_selection(): void
    {
        echo '<div class="mhm-accordion-group">';
        echo '<div class="mhm-accordion-header" id="accordion-validation-selection">';
        echo '<span>' . esc_html__('Validation & Selection', 'mhm-rentiva') . '</span>';
        echo '<span class="dashicons dashicons-arrow-down"></span>';
        echo '</div>';
        echo '<div class="mhm-accordion-content">';

        self::render_input_field('mhm_rentiva_text_select_vehicle', __('Select Vehicle Message', 'mhm-rentiva'), __('Select Vehicle', 'mhm-rentiva'));
        self::render_input_field('mhm_rentiva_text_select_dates', __('Select Dates Message', 'mhm-rentiva'), __('Select Dates', 'mhm-rentiva'));
        self::render_input_field('mhm_rentiva_text_invalid_dates', __('Invalid Dates Message', 'mhm-rentiva'), __('Invalid Dates', 'mhm-rentiva'));
        self::render_input_field('mhm_rentiva_text_select_payment_type', __('Select Payment Type Message', 'mhm-rentiva'), __('Select Payment Type', 'mhm-rentiva'));
        self::render_input_field('mhm_rentiva_text_select_payment_method', __('Select Payment Method Message', 'mhm-rentiva'), __('Select Payment Method', 'mhm-rentiva'));

        echo '</div></div>';
    }

    /**
     * Render Group: Payment Messages
     */
    public static function render_group_payment_messages(): void
    {
        echo '<div class="mhm-accordion-group">';
        echo '<div class="mhm-accordion-header" id="accordion-payment-messages">';
        echo '<span>' . esc_html__('Payment Messages', 'mhm-rentiva') . '</span>';
        echo '<span class="dashicons dashicons-arrow-down"></span>';
        echo '</div>';
        echo '<div class="mhm-accordion-content">';

        self::render_input_field('mhm_rentiva_text_calculating', __('Calculating Message', 'mhm-rentiva'), __('Calculating', 'mhm-rentiva'));
        self::render_input_field('mhm_rentiva_text_payment_redirect', __('Payment Redirect Message', 'mhm-rentiva'), __('Payment Redirect', 'mhm-rentiva'));
        self::render_input_field('mhm_rentiva_text_payment_success', __('Payment Success Message', 'mhm-rentiva'), __('Payment Success', 'mhm-rentiva'));
        self::render_input_field('mhm_rentiva_text_payment_cancelled', __('Payment Cancelled Message', 'mhm-rentiva'), __('Payment Cancelled', 'mhm-rentiva'));
        self::render_input_field('mhm_rentiva_text_popup_blocked', __('Popup Blocked Message', 'mhm-rentiva'), __('Popup Blocked', 'mhm-rentiva'));

        echo '</div></div>';
    }

    /**
     * Render Group: Account & Navigation
     */
    public static function render_group_account_navigation(): void
    {
        echo '<div class="mhm-accordion-group">';
        echo '<div class="mhm-accordion-header" id="accordion-account-navigation">';
        echo '<span>' . esc_html__('Account & Navigation', 'mhm-rentiva') . '</span>';
        echo '<span class="dashicons dashicons-arrow-down"></span>';
        echo '</div>';
        echo '<div class="mhm-accordion-content">';

        self::render_input_field('mhm_rentiva_text_view_dashboard', __('View Dashboard Text', 'mhm-rentiva'), __('View Dashboard', 'mhm-rentiva'));
        self::render_input_field('mhm_rentiva_text_back_to_bookings', __('Back to Bookings Text', 'mhm-rentiva'), __('Back to Bookings', 'mhm-rentiva'));
        self::render_input_field('mhm_rentiva_text_already_have_account', __('Already Have Account Text', 'mhm-rentiva'), __('Already have account?', 'mhm-rentiva'));

        echo '</div></div>';
    }

    /**
     * Helper to render a single input field within an accordion
     */
    private static function render_input_field(string $option_name, string $label, string $default_placeholder): void
    {
        $value = SettingsCore::get($option_name, '');

        echo '<div class="mhm-form-group">';
        echo '<label>' . esc_html($label) . '</label>';
        echo '<input type="text" name="mhm_rentiva_settings[' . esc_attr($option_name) . ']" value="' . esc_attr($value) . '" class="regular-text" placeholder="' . esc_attr($default_placeholder) . '" />';
        echo '<p class="description">' . sprintf(__('Default: "%s"', 'mhm-rentiva'), esc_html($default_placeholder)) . '</p>';
        echo '</div>';
    }
}
