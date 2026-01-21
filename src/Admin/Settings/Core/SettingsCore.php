<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Settings\Core;

use MHMRentiva\Admin\Vehicle\Helpers\VehicleFeatureHelper;

if (!defined('ABSPATH')) {
    exit;
}

final class SettingsCore
{
    public const GROUP = 'mhm_rentiva_settings';
    public const PAGE  = 'mhm_rentiva_settings';

    public static function register(): void
    {
        // Basic WordPress hooks
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_assets']);

        // Dark Mode support
        add_action('wp_head', [self::class, 'apply_dark_mode_frontend']);
        add_action('admin_head', [self::class, 'apply_dark_mode_admin']);
        add_filter('body_class', [self::class, 'add_dark_mode_body_class']);
        add_action('wp_ajax_mhm_save_dark_mode', [self::class, 'ajax_save_dark_mode']);
        add_action('wp_ajax_mhm_run_settings_tests', [self::class, 'ajax_run_settings_tests']);
        add_action('init', [self::class, 'init_rate_limiting']);
        add_action('init', [self::class, 'init_session_management']);
        add_action('init', [self::class, 'init_security_management']);
        add_action('init', [self::class, 'init_privacy_management']);
        add_action('init', [self::class, 'init_notification_management']);


        // Register settings groups - reorganized
        add_action('admin_init', [self::class, 'init']);
        add_action('admin_init', [self::class, 'register_general_settings']);
        add_action('admin_init', [self::class, 'register_vehicle_settings']);
        add_action('admin_init', [self::class, 'register_booking_settings']);
        add_action('admin_init', [self::class, 'register_customer_settings']);
        add_action('admin_init', [self::class, 'register_payment_settings']);
        add_action('admin_init', [self::class, 'register_email_settings']);
        add_action('admin_init', [self::class, 'register_system_settings']);
        add_action('admin_init', [self::class, 'register_frontend_settings']);
        add_action('admin_init', [self::class, 'register_integration_settings']);

        // Hook to flush rewrite rules when settings change
        add_action('updated_option', [self::class, 'on_update_option'], 10, 3);
    }

    public static function enqueue_assets(): void
    {
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'mhm-rentiva-settings') === false) {
            return;
        }

        wp_enqueue_style(
            'mhm-rentiva-settings',
            MHM_RENTIVA_PLUGIN_URL . 'assets/css/admin/settings.css',
            [],
            MHM_RENTIVA_VERSION
        );

        // Dark Mode CSS
        wp_enqueue_style(
            'mhm-rentiva-dark-mode',
            MHM_RENTIVA_PLUGIN_URL . 'assets/css/admin/dark-mode.css',
            ['mhm-rentiva-settings'],
            MHM_RENTIVA_VERSION
        );

        // Settings Testing CSS
        wp_enqueue_style(
            'mhm-rentiva-settings-testing',
            MHM_RENTIVA_PLUGIN_URL . 'assets/css/admin/settings-testing.css',
            ['mhm-rentiva-settings'],
            MHM_RENTIVA_VERSION
        );

        wp_enqueue_style(
            'mhm-rentiva-card-fields',
            MHM_RENTIVA_PLUGIN_URL . 'assets/css/admin/vehicle-card-fields.css',
            ['mhm-rentiva-settings'],
            MHM_RENTIVA_VERSION
        );

        // Dark Mode JavaScript
        wp_enqueue_script(
            'mhm-rentiva-dark-mode',
            MHM_RENTIVA_PLUGIN_URL . 'assets/js/admin/dark-mode.js',
            ['jquery'],
            MHM_RENTIVA_VERSION,
            true
        );

        // Localize script for AJAX
        wp_localize_script('mhm-rentiva-dark-mode', 'mhmDarkMode', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mhm_dark_mode_nonce'),
            'currentMode' => get_option('mhm_rentiva_dark_mode', 'auto')
        ]);

        // ✅ CRITICAL: Enqueue settings form handler to prevent null values
        wp_enqueue_script(
            'mhm-rentiva-settings-form-handler',
            MHM_RENTIVA_PLUGIN_URL . 'assets/js/admin/settings-form-handler.js',
            ['jquery'],
            MHM_RENTIVA_VERSION,
            true
        );

        wp_enqueue_script(
            'mhm-rentiva-card-fields',
            MHM_RENTIVA_PLUGIN_URL . 'assets/js/admin/vehicle-card-fields.js',
            ['jquery', 'jquery-ui-sortable'],
            MHM_RENTIVA_VERSION,
            true
        );
    }

    public static function init(): void
    {
        register_setting(
            'mhm_rentiva_settings',
            'mhm_rentiva_settings',
            [
                'type'              => 'array',
                'sanitize_callback' => [\MHMRentiva\Admin\Settings\Core\SettingsSanitizer::class, 'sanitize'],
                'default'           => self::defaults(),
                'show_in_rest'      => false,
            ]
        );

        register_setting(
            'mhm_rentiva_settings',
            'mhm_rentiva_dark_mode',
            [
                'type'              => 'string',
                'sanitize_callback' => [self::class, 'sanitize_text_field_safe'],
                'default'           => 'auto',
                'show_in_rest'      => false,
            ]
        );

        // General Fields are now registered via GeneralSettings::register()




        // Email settings now managed in EmailSettings class
    }

    public static function defaults(): array
    {
        $defaults = [
            // General Settings (Managed by GeneralSettings class)
            // This merges the array returned by GeneralSettings::get_default_settings() into the main defaults
        ];

        if (class_exists('\MHMRentiva\Admin\Settings\Groups\GeneralSettings')) {
            $defaults = array_merge($defaults, \MHMRentiva\Admin\Settings\Groups\GeneralSettings::get_default_settings());
        }

        // ⭐ Booking Settings (Managed by BookingSettings class)


        if (class_exists('\MHMRentiva\Admin\Settings\Groups\BookingSettings')) {
            $defaults = array_merge($defaults, \MHMRentiva\Admin\Settings\Groups\BookingSettings::get_default_settings());
        }



        if (class_exists('\MHMRentiva\Admin\Settings\Groups\VehicleManagementSettings')) {
            $defaults = array_merge($defaults, \MHMRentiva\Admin\Settings\Groups\VehicleManagementSettings::get_default_settings());
        }

        if (class_exists('\MHMRentiva\Admin\Settings\Groups\FrontendSettings')) {
            $defaults = array_merge($defaults, \MHMRentiva\Admin\Settings\Groups\FrontendSettings::get_default_settings());
        }

        return array_merge($defaults, [
            'comparison_fields'               => [],
            // Email settings managed in EmailSettings class

            // Frontend URL & Text Settings (Global i18n support)
            // Account Endpoints (Dynamic Slugs)
            'mhm_rentiva_endpoint_bookings'        => 'rentiva-bookings',
            'mhm_rentiva_endpoint_favorites'       => 'rentiva-favorites',
            'mhm_rentiva_endpoint_payment_history' => 'rentiva-payment-history',
            'mhm_rentiva_endpoint_edit_account'    => 'rentiva-edit-account',
            'mhm_rentiva_endpoint_messages'        => 'rentiva-messages',

            'mhm_rentiva_booking_url'                     => '',
            'mhm_rentiva_login_url'                       => '',
            'mhm_rentiva_register_url'                    => '',
            'mhm_rentiva_my_account_url'                  => '',
            'mhm_rentiva_my_bookings_url'                 => '',
            'mhm_rentiva_my_favorites_url'                => '',
            'mhm_rentiva_vehicles_list_url'               => '',
            'mhm_rentiva_search_url'                      => '',
            'mhm_rentiva_contact_url'                     => '',
            'mhm_rentiva_text_book_now'                   => '',
            'mhm_rentiva_text_view_details'               => '',
            'mhm_rentiva_text_added_to_favorites'         => '',
            'mhm_rentiva_text_removed_from_favorites'     => '',
            'mhm_rentiva_text_login_required'             => '',
            'mhm_rentiva_text_make_booking'               => '',
            'mhm_rentiva_text_processing'                 => '',
            'mhm_rentiva_text_back_to_bookings'           => '',
            'mhm_rentiva_text_cancel_booking'             => '',
            'mhm_rentiva_text_view_dashboard'             => '',
            'mhm_rentiva_text_login_here'                 => '',
            'mhm_rentiva_text_already_have_account'       => '',
            'mhm_rentiva_text_first_name'                 => '',
            'mhm_rentiva_text_last_name'                  => '',
            'mhm_rentiva_text_email'                      => '',
            'mhm_rentiva_text_phone'                      => '',
            'mhm_rentiva_text_loading'                    => '',
            'mhm_rentiva_text_error'                      => '',
            'mhm_rentiva_text_booking_success'            => '',
            'mhm_rentiva_text_select_vehicle'             => '',
            'mhm_rentiva_text_select_dates'               => '',
            'mhm_rentiva_text_invalid_dates'              => '',
            'mhm_rentiva_text_select_payment_type'        => '',
            'mhm_rentiva_text_select_payment_method'      => '',
            'mhm_rentiva_text_calculating'                => '',
            'mhm_rentiva_text_payment_redirect'           => '',
            'mhm_rentiva_text_payment_success'            => '',
            'mhm_rentiva_text_payment_cancelled'          => '',
            'mhm_rentiva_text_popup_blocked'              => '',

            // Vehicle Management Settings
            'mhm_rentiva_vehicle_base_price'           => 1.0,
            'mhm_rentiva_vehicle_weekend_multiplier'   => 1.2,
            'mhm_rentiva_vehicle_tax_inclusive'        => '0',
            'mhm_rentiva_vehicle_tax_rate'             => 18,
            'mhm_rentiva_vehicle_min_rental_days'      => 1,
            'mhm_rentiva_vehicle_max_rental_days'      => 30,
            'mhm_rentiva_vehicle_advance_booking_days' => 365,
            'mhm_rentiva_vehicle_allow_same_day'       => '1',

            // ⭐ Customer Settings (Managed by CustomerManagementSettings class)
        ]);

        if (class_exists('\MHMRentiva\Admin\Settings\Groups\CustomerManagementSettings')) {
            $defaults = array_merge($defaults, \MHMRentiva\Admin\Settings\Groups\CustomerManagementSettings::get_default_settings());
        }

        if (class_exists('\MHMRentiva\Admin\Settings\Groups\PaymentSettings')) {
            $defaults = array_merge($defaults, \MHMRentiva\Admin\Settings\Groups\PaymentSettings::get_default_settings());
        }

        if (class_exists('\MHMRentiva\Admin\Settings\Groups\EmailSettings')) {
            $defaults = array_merge($defaults, \MHMRentiva\Admin\Settings\Groups\EmailSettings::get_default_settings());
        }

        if (class_exists('\MHMRentiva\Admin\Settings\Groups\CoreSettings')) {
            $defaults = array_merge($defaults, \MHMRentiva\Admin\Settings\Groups\CoreSettings::get_default_settings());
        }

        if (class_exists('\MHMRentiva\Admin\Settings\Groups\SecuritySettings')) {
            $defaults = array_merge($defaults, \MHMRentiva\Admin\Settings\Groups\SecuritySettings::get_default_settings());
        }

        if (class_exists('\MHMRentiva\Admin\Settings\Groups\ReconcileSettings')) {
            $defaults = array_merge($defaults, \MHMRentiva\Admin\Settings\Groups\ReconcileSettings::get_default_settings());
        }

        if (class_exists('\MHMRentiva\Admin\Settings\Groups\LogsSettings')) {
            $defaults = array_merge($defaults, \MHMRentiva\Admin\Settings\Groups\LogsSettings::get_default_settings());
        }

        if (class_exists('\MHMRentiva\Admin\Settings\Groups\MaintenanceSettings')) {
            $defaults = array_merge($defaults, \MHMRentiva\Admin\Settings\Groups\MaintenanceSettings::get_default_settings());
        }

        return array_merge($defaults, [
            // Branding Settings
            'mhm_rentiva_brand_name'          => get_bloginfo('name'),
            'mhm_rentiva_brand_logo_url'      => '',

            // Email Styling (Frontend)
            'mhm_rentiva_email_primary_color' => '#1e88e5',
            'mhm_rentiva_email_footer_text'   => '© {Y} {site}',

            // Use vehicle pricing class if available (for complex nested structure)
            'vehicle_pricing' => class_exists('\MHMRentiva\Admin\Vehicle\Settings\VehiclePricingSettings')
                ? \MHMRentiva\Admin\Vehicle\Settings\VehiclePricingSettings::get_default_settings()
                : [],
        ]);
    }

    /**
     * Set a setting value
     * 
     * @param string $key Setting key
     * @param mixed $value Setting value
     * @return bool Success status
     */
    public static function set(string $key, $value): bool
    {
        $settings = self::get_all();
        $settings[$key] = $value;
        return update_option('mhm_rentiva_settings', $settings);
    }

    /**
     * Delete a setting
     * 
     * @param string $key Setting key
     * @return bool Success status
     */
    public static function delete(string $key): bool
    {
        $settings = self::get_all();
        if (isset($settings[$key])) {
            unset($settings[$key]);
            return update_option('mhm_rentiva_settings', $settings);
        }
        return true;
    }

    public static function get_all(): array
    {
        // ✅ Get from main settings option
        $main_settings = get_option('mhm_rentiva_settings', []);
        $defaults = self::defaults();

        // Fill missing keys with default values
        foreach ($defaults as $key => $default_value) {
            if (!isset($main_settings[$key])) {
                $main_settings[$key] = $default_value;
            }
        }

        return $main_settings;
    }

    /**
     * Reset settings for a specific tab to defaults
     * 
     * @param string $tab Tab name (general, vehicle, booking, customer, email, payment, system, frontend)
     * @return bool Success status
     */
    public static function reset_tab_to_defaults(string $tab): bool
    {
        if (!current_user_can('manage_options')) {
            return false;
        }

        $defaults = self::defaults();
        $main_settings = get_option('mhm_rentiva_settings', []);

        // Define tab-specific settings keys
        $tab_keys = self::get_tab_settings_keys($tab);

        if (empty($tab_keys)) {
            return false;
        }

        // Reset only tab-specific keys to their defaults
        foreach ($tab_keys as $key) {
            if (isset($defaults[$key])) {
                $main_settings[$key] = $defaults[$key];
            } else {
                // If no default exists, remove the key (optional - may want to keep current value)
                // unset($main_settings[$key]);
            }
        }

        return update_option('mhm_rentiva_settings', $main_settings) !== false;
    }

    /**
     * Get settings keys for a specific tab
     * 
     * @param string $tab Tab name
     * @return array Array of setting keys
     */
    private static function get_tab_settings_keys(string $tab): array
    {
        $all_defaults = self::defaults();
        $tab_keys = [];

        switch ($tab) {
            case 'general':
                // General settings keys
                $tab_keys = [
                    'mhm_rentiva_currency',
                    'mhm_rentiva_currency_position',
                    'mhm_rentiva_support_email',
                    'mhm_rentiva_site_language'
                ];
                break;

            case 'vehicle':
                // Vehicle settings keys (all keys starting with mhm_rentiva_vehicle_)
                // EXCLUDE display settings which are now in Frontend settings
                $display_keys = [
                    'mhm_rentiva_vehicle_cards_per_page',
                    'mhm_rentiva_vehicle_default_sort',
                    'mhm_rentiva_vehicle_show_images',
                    'mhm_rentiva_vehicle_show_features',
                    'mhm_rentiva_vehicle_card_fields',
                    'mhm_rentiva_vehicle_show_availability'
                ];
                foreach (array_keys($all_defaults) as $key) {
                    if (strpos($key, 'mhm_rentiva_vehicle_') === 0 && !in_array($key, $display_keys, true)) {
                        $tab_keys[] = $key;
                    }
                }
                break;

            case 'booking':
                // Booking settings keys
                foreach (array_keys($all_defaults) as $key) {
                    if (strpos($key, 'mhm_rentiva_booking_') === 0) {
                        $tab_keys[] = $key;
                    }
                }
                break;

            case 'customer':
                // Customer settings keys
                foreach (array_keys($all_defaults) as $key) {
                    if (strpos($key, 'mhm_rentiva_customer_') === 0) {
                        $tab_keys[] = $key;
                    }
                }
                break;

            case 'email':
                // Email settings keys
                foreach (array_keys($all_defaults) as $key) {
                    if (strpos($key, 'mhm_rentiva_email_') === 0) {
                        $tab_keys[] = $key;
                    }
                }
                break;

            case 'payment':
                // Payment settings keys
                foreach (array_keys($all_defaults) as $key) {
                    if (
                        strpos($key, 'mhm_rentiva_offline_') === 0 ||
                        strpos($key, 'mhm_rentiva_booking_default_payment_method') === 0
                    ) {
                        $tab_keys[] = $key;
                    }
                }
                break;

            case 'system':
                // System & Performance settings keys
                foreach (array_keys($all_defaults) as $key) {
                    if (
                        strpos($key, 'mhm_rentiva_rate_limit_') === 0 ||
                        strpos($key, 'mhm_rentiva_cache_') === 0 ||
                        strpos($key, 'mhm_rentiva_db_') === 0 ||
                        strpos($key, 'mhm_rentiva_auto_') === 0 ||
                        strpos($key, 'mhm_rentiva_log_') === 0 ||
                        strpos($key, 'mhm_rentiva_reconcile_') === 0 ||
                        strpos($key, 'mhm_rentiva_ip_') === 0 ||
                        strpos($key, 'mhm_rentiva_security_') === 0 ||
                        strpos($key, 'mhm_rentiva_authentication_') === 0 ||
                        strpos($key, 'mhm_rentiva_maintenance_') === 0
                    ) {
                        $tab_keys[] = $key;
                    }
                }
                break;

            case 'frontend':
                // Frontend URL & Text settings keys
                foreach (array_keys($all_defaults) as $key) {
                    if (
                        strpos($key, 'mhm_rentiva_booking_url') === 0 ||
                        strpos($key, 'mhm_rentiva_login_url') === 0 ||
                        strpos($key, 'mhm_rentiva_register_url') === 0 ||
                        strpos($key, 'mhm_rentiva_my_') === 0 ||
                        strpos($key, 'mhm_rentiva_vehicles_list_url') === 0 ||
                        strpos($key, 'mhm_rentiva_search_url') === 0 ||
                        strpos($key, 'mhm_rentiva_contact_url') === 0 ||
                        strpos($key, 'mhm_rentiva_text_') === 0 ||
                        strpos($key, 'mhm_rentiva_endpoint_') === 0 ||
                        strpos($key, 'comparison_fields') === 0
                    ) {
                        $tab_keys[] = $key;
                    }
                }

                // Add migrated vehicle display settings
                $display_keys = [
                    'mhm_rentiva_vehicle_cards_per_page',
                    'mhm_rentiva_vehicle_default_sort',
                    'mhm_rentiva_vehicle_show_images',
                    'mhm_rentiva_vehicle_show_features',
                    'mhm_rentiva_vehicle_card_fields',
                    'mhm_rentiva_vehicle_show_availability'
                ];
                foreach ($display_keys as $dk) {
                    if (isset($all_defaults[$dk])) {
                        $tab_keys[] = $dk;
                    }
                }
                break;
        }

        // Filter to only include keys that exist in defaults
        return array_filter($tab_keys, function ($key) use ($all_defaults) {
            return array_key_exists($key, $all_defaults);
        });
    }

    public static function get(string $key, $default = null)
    {
        // ✅ REMOVED: Standalone option check - all settings now in mhm_rentiva_settings array
        // This prevents conflicts with old standalone options

        // ✅ Get from main settings option
        $main_settings = get_option('mhm_rentiva_settings', []);
        $defaults = self::defaults();

        // Check if key exists in main settings
        if (isset($main_settings[$key])) {
            $value = $main_settings[$key];

            // If default exists, check if we should use default instead
            if (isset($defaults[$key])) {
                $default_type = gettype($defaults[$key]);
                // For all types, if value is empty string, use default
                if ($value === '') {
                    return $defaults[$key];
                }
                // For numeric fields, if value is 0 (int or string) and default is not 0, use default
                // This handles cases where 0 was saved unintentionally (empty form submission)
                if ($default_type === 'integer' && ($value === 0 || $value === '0')) {
                    // Check if 0 is a valid value for this field (e.g., timeout can be 0 to disable)
                    $zero_allowed_keys = [
                        'mhm_rentiva_booking_min_advance_hours',
                        'mhm_rentiva_booking_buffer_hours'
                    ];
                    if (!in_array($key, $zero_allowed_keys, true) && $defaults[$key] !== 0) {
                        return $defaults[$key];
                    }
                }
            }

            return $value;
        }

        // ✅ Use defaults array if available
        if (isset($defaults[$key])) {
            return $defaults[$key];
        }

        // ✅ Last resort: use provided default parameter
        return $default;
    }




    // Email settings now managed in EmailSettings class

    /**
     * General Settings - Basic system settings
     */
    public static function register_general_settings(): void
    {
        // General settings already defined in init() function
        // Additional general settings can be added here

        // Register additional general settings if needed
        if (class_exists('\MHMRentiva\Admin\Settings\Groups\GeneralSettings')) {
            \MHMRentiva\Admin\Settings\Groups\GeneralSettings::register();
        }
    }

    /**
     * Vehicle Management Settings
     */
    public static function register_vehicle_settings(): void
    {
        if (class_exists('\MHMRentiva\Admin\Settings\Groups\VehicleManagementSettings')) {
            \MHMRentiva\Admin\Settings\Groups\VehicleManagementSettings::register();
        }

        // Vehicle pricing settings
        if (class_exists('\MHMRentiva\Admin\Vehicle\Settings\VehiclePricingSettings')) {
            // Register VehiclePricingSettings if class exists
        }
    }

    /**
     * Booking Management Settings
     */
    public static function register_booking_settings(): void
    {
        if (class_exists('\MHMRentiva\Admin\Settings\Groups\BookingSettings')) {
            \MHMRentiva\Admin\Settings\Groups\BookingSettings::register();
        }

        if (class_exists('\MHMRentiva\Admin\Settings\Groups\AddonSettings')) {
            \MHMRentiva\Admin\Settings\Groups\AddonSettings::register();
        }
    }

    /**
     * Customer Management Settings
     */
    public static function register_customer_settings(): void
    {
        if (class_exists('\MHMRentiva\Admin\Settings\Groups\CustomerManagementSettings')) {
            \MHMRentiva\Admin\Settings\Groups\CustomerManagementSettings::register();
        }
    }

    /**
     * Payment Settings
     */
    public static function register_payment_settings(): void {}

    /**
     * Email & Notification Settings
     */
    public static function register_email_settings(): void
    {
        if (class_exists('\MHMRentiva\Admin\Settings\Groups\EmailSettings')) {
            \MHMRentiva\Admin\Settings\Groups\EmailSettings::register();
        }
    }

    /**
     * System & Performance Settings
     */
    public static function register_system_settings(): void
    {
        if (class_exists('\MHMRentiva\Admin\Settings\Groups\CoreSettings')) {
            \MHMRentiva\Admin\Settings\Groups\CoreSettings::register();
        }

        if (class_exists('\MHMRentiva\Admin\Settings\Groups\SecuritySettings')) {
            \MHMRentiva\Admin\Settings\Groups\SecuritySettings::register();
        }

        if (class_exists('\MHMRentiva\Admin\Settings\Groups\MaintenanceSettings')) {
            \MHMRentiva\Admin\Settings\Groups\MaintenanceSettings::register();
        }

        if (class_exists('\MHMRentiva\Admin\Settings\Groups\LogsSettings')) {
            \MHMRentiva\Admin\Settings\Groups\LogsSettings::register();
        }

        if (class_exists('\MHMRentiva\Admin\Settings\Groups\ReconcileSettings')) {
            \MHMRentiva\Admin\Settings\Groups\ReconcileSettings::register();
        }
    }

    // Frontend settings are defined below

    /**
     * Integration Settings
     */
    public static function register_integration_settings(): void
    {
        if (class_exists('\MHMRentiva\Admin\REST\Settings\RESTSettings')) {
            \MHMRentiva\Admin\REST\Settings\RESTSettings::init();
        }
    }

    /**
     * Frontend & Display Settings - URL and Text Customizations
     */
    public static function register_frontend_settings(): void
    {
        // Register Vehicle Display Settings
        if (class_exists('\MHMRentiva\Admin\Settings\Groups\FrontendSettings')) {
            \MHMRentiva\Admin\Settings\Groups\FrontendSettings::register();
        }

        add_settings_section(
            'mhm_rentiva_frontend_section',
            __('Page URL Settings', 'mhm-rentiva'),
            [self::class, 'render_frontend_section_description'],
            self::PAGE
        );

        /* Endpoint fields moved to bottom */

        add_settings_field(
            'mhm_rentiva_booking_url',
            __('Booking Page URL', 'mhm-rentiva'),
            [self::class, 'render_booking_url_field'],
            self::PAGE,
            'mhm_rentiva_frontend_section'
        );

        add_settings_field(
            'mhm_rentiva_login_url',
            __('Login Page URL', 'mhm-rentiva'),
            [self::class, 'render_login_url_field'],
            self::PAGE,
            'mhm_rentiva_frontend_section'
        );

        add_settings_field(
            'mhm_rentiva_register_url',
            __('Registration Page URL', 'mhm-rentiva'),
            [self::class, 'render_register_url_field'],
            self::PAGE,
            'mhm_rentiva_frontend_section'
        );

        /* My Account URL field removed - handled by WooCommerce */

        /* My Bookings URL field removed - handled by WooCommerce endpoint */

        /* My Favorites URL field removed - handled by WooCommerce endpoint */

        add_settings_field(
            'mhm_rentiva_vehicles_list_url',
            __('Vehicles List Page URL', 'mhm-rentiva'),
            [self::class, 'render_vehicles_list_url_field'],
            self::PAGE,
            'mhm_rentiva_frontend_section'
        );

        add_settings_field(
            'mhm_rentiva_search_url',
            __('Search Page URL', 'mhm-rentiva'),
            [self::class, 'render_search_url_field'],
            self::PAGE,
            'mhm_rentiva_frontend_section'
        );

        add_settings_field(
            'mhm_rentiva_contact_url',
            __('Contact Page URL', 'mhm-rentiva'),
            [self::class, 'render_contact_url_field'],
            self::PAGE,
            'mhm_rentiva_frontend_section'
        );

        add_settings_section(
            'mhm_rentiva_message_texts_section',
            __('System Messages', 'mhm-rentiva'),
            [self::class, 'render_message_texts_section_description'],
            self::PAGE
        );

        add_settings_field(
            'mhm_rentiva_text_refund_policy',
            __('Refund Policy Text', 'mhm-rentiva'),
            [self::class, 'render_refund_policy_text_field'],
            self::PAGE,
            'mhm_rentiva_message_texts_section'
        );

        // Account Endpoint Slugs Section (Moved to bottom)
        add_settings_section(
            'mhm_rentiva_frontend_endpoints_section',
            __('Account Endpoint Slugs (URL Customization)', 'mhm-rentiva'),
            [self::class, 'render_frontend_endpoints_section_description'],
            self::PAGE
        );

        add_settings_field(
            'mhm_rentiva_endpoint_bookings',
            __('Bookings Endpoint', 'mhm-rentiva'),
            [self::class, 'render_endpoint_bookings_field'],
            self::PAGE,
            'mhm_rentiva_frontend_endpoints_section'
        );

        add_settings_field(
            'mhm_rentiva_endpoint_favorites',
            __('Favorites Endpoint', 'mhm-rentiva'),
            [self::class, 'render_endpoint_favorites_field'],
            self::PAGE,
            'mhm_rentiva_frontend_endpoints_section'
        );

        add_settings_field(
            'mhm_rentiva_endpoint_payment_history',
            __('Payment History Endpoint', 'mhm-rentiva'),
            [self::class, 'render_endpoint_payment_history_field'],
            self::PAGE,
            'mhm_rentiva_frontend_endpoints_section'
        );

        add_settings_field(
            'mhm_rentiva_endpoint_messages',
            __('Messages Endpoint', 'mhm-rentiva'),
            [self::class, 'render_endpoint_messages_field'],
            self::PAGE,
            'mhm_rentiva_frontend_endpoints_section'
        );

        add_settings_field(
            'mhm_rentiva_endpoint_edit_account',
            __('Edit Account Endpoint', 'mhm-rentiva'),
            [self::class, 'render_endpoint_edit_account_field'],
            self::PAGE,
            'mhm_rentiva_frontend_endpoints_section'
        );

        add_settings_field(
            'mhm_rentiva_endpoint_view_booking',
            __('View Booking Endpoint', 'mhm-rentiva'),
            [self::class, 'render_endpoint_view_booking_field'],
            self::PAGE,
            'mhm_rentiva_frontend_endpoints_section'
        );

        // Register Comments Settings
        if (class_exists('\MHMRentiva\Admin\Settings\Groups\CommentsSettingsGroup')) {
            \MHMRentiva\Admin\Settings\Groups\CommentsSettingsGroup::register();
        }
    }

    /**
     * Frontend section description
     */
    public static function render_frontend_section_description(): void
    {
        echo '<p>' . __('Customize frontend page URLs and texts. Default values will be used if left empty.', 'mhm-rentiva') . '</p>';
    }

    /**
     * Button texts section description
     */
    public static function render_button_texts_section_description(): void
    {
        echo '<p>' . __('Customize the texts that will appear on vehicle cards and buttons.', 'mhm-rentiva') . '</p>';
    }

    /**
     * Booking URL field
     */
    public static function render_booking_url_field(): void
    {
        $value = self::get('mhm_rentiva_booking_url', '');
        echo '<input type="text" name="mhm_rentiva_settings[mhm_rentiva_booking_url]" value="' . esc_attr($value) . '" class="regular-text" placeholder="https://example.com/booking/" />';
        echo '<p class="description">' . __('Example: https://example.com/booking/ or /booking-form/ (auto-detected if left empty)', 'mhm-rentiva') . '</p>';
    }

    /**
     * Login URL field
     */
    public static function render_login_url_field(): void
    {
        $value = self::get('mhm_rentiva_login_url', '');
        echo '<input type="text" name="mhm_rentiva_settings[mhm_rentiva_login_url]" value="' . esc_attr($value) . '" class="regular-text" placeholder="https://example.com/login/" />';
        echo '<p class="description">' . __('Example: https://example.com/login/ or /member-login/ (WordPress default login page is used if left empty)', 'mhm-rentiva') . '</p>';
    }

    /**
     * Register URL field
     */
    public static function render_register_url_field(): void
    {
        $value = self::get('mhm_rentiva_register_url', '');
        echo '<input type="text" name="mhm_rentiva_settings[mhm_rentiva_register_url]" value="' . esc_attr($value) . '" class="regular-text" placeholder="https://example.com/register/" />';
        echo '<p class="description">' . __('Example: https://example.com/register/ or /kayit/ (auto-detected if left empty)', 'mhm-rentiva') . '</p>';
    }

    /**
     * My Account URL field
     */
    /* render_my_account_url_field removed */

    /**
     * My Bookings URL field
     */
    /* render_my_bookings_url_field removed */

    /**
     * My Favorites URL field
     */
    /* render_my_favorites_url_field removed */

    /**
     * Vehicles List URL field
     */
    public static function render_vehicles_list_url_field(): void
    {
        $value = self::get('mhm_rentiva_vehicles_list_url', '');
        echo '<input type="text" name="mhm_rentiva_settings[mhm_rentiva_vehicles_list_url]" value="' . esc_attr($value) . '" class="regular-text" placeholder="https://example.com/vehicles/" />';
        echo '<p class="description">' . __('Example: https://example.com/vehicles/ or /araclar/ (auto-detected if left empty)', 'mhm-rentiva') . '</p>';
    }

    /**
     * Search URL field
     */
    public static function render_search_url_field(): void
    {
        $value = self::get('mhm_rentiva_search_url', '');
        echo '<input type="text" name="mhm_rentiva_settings[mhm_rentiva_search_url]" value="' . esc_attr($value) . '" class="regular-text" placeholder="https://example.com/search/" />';
        echo '<p class="description">' . __('Example: https://example.com/search/ or /arama/ (auto-detected if left empty)', 'mhm-rentiva') . '</p>';
    }

    /**
     * Contact URL field
     */
    public static function render_contact_url_field(): void
    {
        $value = self::get('mhm_rentiva_contact_url', '');
        echo '<input type="text" name="mhm_rentiva_settings[mhm_rentiva_contact_url]" value="' . esc_attr($value) . '" class="regular-text" placeholder="https://example.com/contact/" />';
        echo '<p class="description">' . __('Example: https://example.com/contact/ or /iletisim/ (auto-detected if left empty)', 'mhm-rentiva') . '</p>';
    }



    /**
     * Message texts section description
     */
    public static function render_message_texts_section_description(): void
    {
        echo '<p>' . __('Customize system messages, validation errors, and success notifications displayed to users.', 'mhm-rentiva') . '</p>';
    }



    public static function render_refund_policy_text_field(): void
    {
        $value = self::get('mhm_rentiva_text_refund_policy', '');
        echo '<textarea name="mhm_rentiva_settings[mhm_rentiva_text_refund_policy]" rows="3" class="large-text" placeholder="' . esc_attr__('If cancelled, refund will be processed within 5-7 business days.', 'mhm-rentiva') . '">' . esc_textarea($value) . '</textarea>';
        echo '<p class="description">' . sprintf(__('Default: "%s"', 'mhm-rentiva'), __('If cancelled, refund will be processed within 5-7 business days.', 'mhm-rentiva')) . '</p>';
    }




    /**
     * Get company website URL
     */
    public static function get_company_website(): string
    {
        return get_option('mhm_rentiva_company_website', 'https://maxhandmade.com');
    }

    /**
     * Get support email
     */
    public static function get_support_email(): string
    {
        return get_option('mhm_rentiva_support_email', 'destek@maxhandmade.com');
    }

    /**
     * Get dark mode setting
     */
    public static function get_dark_mode(): string
    {
        return get_option('mhm_rentiva_dark_mode', 'auto');
    }

    /**
     * Add dark mode body class
     */
    public static function add_dark_mode_body_class($classes): array
    {
        $dark_mode = self::get_dark_mode();

        if ($dark_mode === 'dark') {
            $classes[] = 'wp-theme-dark';
        } elseif ($dark_mode === 'light') {
            // Explicitly remove dark mode class
            $classes = array_diff($classes, ['wp-theme-dark']);
        } elseif ($dark_mode === 'auto') {
            $classes[] = 'mhm-auto-dark-mode';
        }

        return $classes;
    }

    /**
     * Apply dark mode for frontend
     */
    public static function apply_dark_mode_frontend(): void
    {
        $dark_mode = self::get_dark_mode();

        if ($dark_mode === 'auto') {
            echo '<style>
            .mhm-auto-dark-mode {
            }
            @media (prefers-color-scheme: dark) {
                .mhm-auto-dark-mode {
                    /* System prefers dark mode */
                }
                .mhm-auto-dark-mode .mhm-quick-actions,
                .mhm-auto-dark-mode .mhm-dashboard-widget {
                    background: #1e1e1e !important;
                    border-color: #3c3c3c !important;
                    color: #ffffff !important;
                }
                .mhm-auto-dark-mode .quick-action-card {
                    background: #2d2d2d !important;
                    border-color: #3c3c3c !important;
                    color: #b3b3b3 !important;
                }
                .mhm-auto-dark-mode .quick-action-card:hover {
                    background: #3c3c3c !important;
                    border-color: #4c4c4c !important;
                    color: #ffffff !important;
                }
            }
            </style>';
        }
    }

    /**
     * Apply dark mode for admin
     */
    public static function apply_dark_mode_admin(): void
    {
        $dark_mode = self::get_dark_mode();

        if ($dark_mode === 'auto') {
            echo '<style>
            .mhm-auto-dark-mode {
            }
            @media (prefers-color-scheme: dark) {
                .mhm-auto-dark-mode {
                    /* System prefers dark mode */
                }
                .mhm-auto-dark-mode .mhm-quick-actions,
                .mhm-auto-dark-mode .mhm-dashboard-widget {
                    background: #1e1e1e !important;
                    border-color: #3c3c3c !important;
                    color: #ffffff !important;
                }
                .mhm-auto-dark-mode .quick-action-card {
                    background: #2d2d2d !important;
                    border-color: #3c3c3c !important;
                    color: #b3b3b3 !important;
                }
                .mhm-auto-dark-mode .quick-action-card:hover {
                    background: #3c3c3c !important;
                    border-color: #4c4c4c !important;
                    color: #ffffff !important;
                }
            }
            </style>';
        }
    }

    /**
     * AJAX handler for dark mode
     */
    public static function ajax_save_dark_mode(): void
    {
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'] ?? '')), 'mhm_dark_mode_nonce')) {
            wp_send_json_error(__('Security check failed', 'mhm-rentiva'));
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized access', 'mhm-rentiva'));
            return;
        }

        $dark_mode = self::sanitize_text_field_safe($_POST['mode'] ?? 'auto');

        if (!in_array($dark_mode, ['auto', 'light', 'dark'])) {
            wp_send_json_error(__('Invalid dark mode value', 'mhm-rentiva'));
            return;
        }

        update_option('mhm_rentiva_dark_mode', $dark_mode);
        wp_send_json_success(['message' => __('Dark mode setting saved', 'mhm-rentiva')]);
    }

    /**
     * AJAX handler for running settings tests
     */
    public static function ajax_run_settings_tests(): void
    {
        // Verify nonce
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'] ?? '')), 'mhm_settings_test_nonce')) {
            wp_send_json_error(__('Security check failed', 'mhm-rentiva'));
            return;
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized access', 'mhm-rentiva'));
            return;
        }

        // Check if tester class exists
        if (!class_exists('\MHMRentiva\Admin\Settings\Testing\SettingsTester')) {
            wp_send_json_error(__('Settings tester class not found', 'mhm-rentiva'));
            return;
        }

        // Run tests
        $report = \MHMRentiva\Admin\Settings\Testing\SettingsTester::generate_report();
        wp_send_json_success($report);
    }

    /**
     * Initialize rate limiting
     */
    public static function init_rate_limiting(): void
    {
        if (!class_exists('\MHMRentiva\Admin\Settings\Core\RateLimiter')) {
            return;
        }

        // Check if rate limiting is enabled
        if (!RateLimiter::is_enabled()) {
            return;
        }

        // Check if IP is blocked
        if (RateLimiter::is_ip_blocked()) {
            self::handle_rate_limit_exceeded();
            return;
        }

        // Apply rate limiting to specific actions
        add_action('wp_ajax_mhm_booking_request', [self::class, 'check_booking_rate_limit'], 1);
        add_action('wp_ajax_nopriv_mhm_booking_request', [self::class, 'check_booking_rate_limit'], 1);

        add_action('wp_ajax_mhm_payment_request', [self::class, 'check_payment_rate_limit'], 1);
        add_action('wp_ajax_nopriv_mhm_payment_request', [self::class, 'check_payment_rate_limit'], 1);

        // General rate limiting for admin actions
        if (is_admin()) {
            add_action('admin_init', [self::class, 'check_general_rate_limit'], 1);
        }
    }

    /**
     * Check booking rate limit
     */
    public static function check_booking_rate_limit(): void
    {
        if (!RateLimiter::is_allowed('booking', RateLimiter::get_booking_limit())) {
            RateLimiter::log_violation('booking', RateLimiter::get_client_ip(), RateLimiter::get_booking_limit());
            RateLimiter::block_ip();
            wp_send_json_error([
                'message' => __('Rate limit exceeded. Please try again later.', 'mhm-rentiva'),
                'retry_after' => RateLimiter::get_block_duration() * 60
            ]);
        }
    }

    /**
     * Check payment rate limit
     */
    public static function check_payment_rate_limit(): void
    {
        if (!RateLimiter::is_allowed('payment', RateLimiter::get_payment_limit())) {
            RateLimiter::log_violation('payment', RateLimiter::get_client_ip(), RateLimiter::get_payment_limit());
            RateLimiter::block_ip();
            wp_send_json_error([
                'message' => __('Rate limit exceeded. Please try again later.', 'mhm-rentiva'),
                'retry_after' => RateLimiter::get_block_duration() * 60
            ]);
        }
    }

    /**
     * Check general rate limit
     */
    public static function check_general_rate_limit(): void
    {
        // Allow trusted administrators to operate without rate limiting in the dashboard
        if (is_user_logged_in() && current_user_can('manage_options')) {
            return;
        }

        if (!RateLimiter::is_allowed('general', RateLimiter::get_general_limit())) {
            RateLimiter::log_violation('general', RateLimiter::get_client_ip(), RateLimiter::get_general_limit());
            RateLimiter::block_ip();
            wp_die(__('Rate limit exceeded. Please try again later.', 'mhm-rentiva'), __('Rate Limit Exceeded', 'mhm-rentiva'), ['response' => 429]);
        }
    }

    /**
     * Handle rate limit exceeded
     */
    private static function handle_rate_limit_exceeded(): void
    {
        if (wp_doing_ajax()) {
            wp_send_json_error([
                'message' => __('Rate limit exceeded. Please try again later.', 'mhm-rentiva'),
                'retry_after' => RateLimiter::get_block_duration() * 60
            ]);
        } else {
            wp_die(__('Rate limit exceeded. Please try again later.', 'mhm-rentiva'), __('Rate Limit Exceeded', 'mhm-rentiva'), ['response' => 429]);
        }
    }





    // ========================================
    // DATE & TIME RENDER FUNCTIONS
    // ========================================

    public static function render_datetime_section_description(): void
    {
        echo '<p>' . esc_html__('Date and time format settings for the entire system.', 'mhm-rentiva') . '</p>';
    }

    public static function render_start_of_week_field(): void
    {
        $value = self::get('mhm_rentiva_start_of_week', get_option('start_of_week', 1));
        $days = [
            0 => __('Sunday', 'mhm-rentiva'),
            1 => __('Monday', 'mhm-rentiva'),
            2 => __('Tuesday', 'mhm-rentiva'),
            3 => __('Wednesday', 'mhm-rentiva'),
            4 => __('Thursday', 'mhm-rentiva'),
            5 => __('Friday', 'mhm-rentiva'),
            6 => __('Saturday', 'mhm-rentiva'),
        ];

        echo '<select name="mhm_rentiva_settings[mhm_rentiva_start_of_week]" disabled>';
        foreach ($days as $day_num => $day_name) {
            echo '<option value="' . esc_attr($day_num) . '"' . selected($value, $day_num, false) . '>' . esc_html($day_name) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . esc_html__('WordPress start of week (read-only)', 'mhm-rentiva') . '</p>';
    }

    // ========================================
    // SYSTEM INFORMATION RENDER FUNCTIONS
    // ========================================

    public static function render_system_info_section_description(): void
    {
        echo '<p>' . esc_html__('System version information and technical details.', 'mhm-rentiva') . '</p>';
    }

    public static function render_plugin_version_field(): void
    {
        $version = defined('MHM_RENTIVA_VERSION') ? MHM_RENTIVA_VERSION : 'Unknown';
        echo '<input type="text" value="' . esc_attr($version) . '" class="regular-text" readonly />';
        echo '<p class="description">' . esc_html__('Current plugin version', 'mhm-rentiva') . '</p>';
    }

    public static function render_wp_version_field(): void
    {
        global $wp_version;
        echo '<input type="text" value="' . esc_attr($wp_version) . '" class="regular-text" readonly />';
        echo '<p class="description">' . esc_html__('Current WordPress version', 'mhm-rentiva') . '</p>';
    }

    public static function render_php_version_field(): void
    {
        echo '<input type="text" value="' . esc_attr(PHP_VERSION) . '" class="regular-text" readonly />';
        echo '<p class="description">' . esc_html__('Current PHP version', 'mhm-rentiva') . '</p>';
    }

    /**
     * Initialize session management
     */
    public static function init_session_management(): void
    {
        if (!class_exists('\MHMRentiva\Admin\Auth\SessionManager')) {
            return;
        }

        \MHMRentiva\Admin\Auth\SessionManager::init();
    }

    /**
     * Initialize security management
     */
    public static function init_security_management(): void
    {
        // Initialize security manager (IP whitelist, blacklist, country restrictions)
        if (class_exists('\MHMRentiva\Admin\Security\SecurityManager')) {
            \MHMRentiva\Admin\Security\SecurityManager::init();
        }

        // Initialize lockout manager
        if (class_exists('\MHMRentiva\Admin\Auth\LockoutManager')) {
            \MHMRentiva\Admin\Auth\LockoutManager::init();
        }

        // Initialize 2FA manager
        if (class_exists('\MHMRentiva\Admin\Auth\TwoFactorManager')) {
            \MHMRentiva\Admin\Auth\TwoFactorManager::init();
        }
    }

    /**
     * Initialize privacy management
     */
    public static function init_privacy_management(): void
    {
        // Initialize GDPR manager
        if (class_exists('\MHMRentiva\Admin\Privacy\GDPRManager')) {
            \MHMRentiva\Admin\Privacy\GDPRManager::init();
        }

        // Initialize data retention manager
        if (class_exists('\MHMRentiva\Admin\Privacy\DataRetentionManager')) {
            \MHMRentiva\Admin\Privacy\DataRetentionManager::init();
        }
    }

    /**
     * Initialize notification management
     */
    public static function init_notification_management(): void
    {
        // Initialize notification manager
        if (class_exists('\MHMRentiva\Admin\Notifications\NotificationManager')) {
            \MHMRentiva\Admin\Notifications\NotificationManager::init();
        }
    }

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
    public static function render_frontend_endpoints_section_description(): void
    {
        echo '<p>';
        echo esc_html__('Customize the URL slugs for the My Account endpoints. These are the parts of the URL that appear after the main My Account page URL.', 'mhm-rentiva');
        echo '<br>';
        echo '<strong>' . esc_html__('Important:', 'mhm-rentiva') . '</strong> ';
        echo esc_html__('After changing these settings, you may need to go to Settings > Permalinks and click "Save Changes" to refresh the permalinks structure.', 'mhm-rentiva');
        echo '</p>';
    }

    public static function render_endpoint_bookings_field(): void
    {
        $value = self::get('mhm_rentiva_endpoint_bookings', 'my-vehicle-bookings');
        echo '<input type="text" name="mhm_rentiva_settings[mhm_rentiva_endpoint_bookings]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . esc_html__('Default: rentiva-bookings', 'mhm-rentiva') . '</p>';
    }

    public static function render_endpoint_favorites_field(): void
    {
        $value = self::get('mhm_rentiva_endpoint_favorites', 'my-favorite-vehicles');
        echo '<input type="text" name="mhm_rentiva_settings[mhm_rentiva_endpoint_favorites]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . esc_html__('Default: rentiva-favorites', 'mhm-rentiva') . '</p>';
    }

    public static function render_endpoint_payment_history_field(): void
    {
        $value = self::get('mhm_rentiva_endpoint_payment_history', 'my-payment-history');
        echo '<input type="text" name="mhm_rentiva_settings[mhm_rentiva_endpoint_payment_history]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . esc_html__('Default: rentiva-payment-history', 'mhm-rentiva') . '</p>';
    }

    public static function render_endpoint_messages_field(): void
    {
        $value = self::get('mhm_rentiva_endpoint_messages', 'my-messages');
        echo '<input type="text" name="mhm_rentiva_settings[mhm_rentiva_endpoint_messages]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . esc_html__('Default: rentiva-messages', 'mhm-rentiva') . '</p>';
    }

    public static function render_endpoint_edit_account_field(): void
    {
        $value = self::get('mhm_rentiva_endpoint_edit_account', 'my-edit-account');
        echo '<input type="text" name="mhm_rentiva_settings[mhm_rentiva_endpoint_edit_account]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . esc_html__('Default: rentiva-edit-account', 'mhm-rentiva') . '</p>';
    }

    public static function render_endpoint_view_booking_field(): void
    {
        $value = self::get('mhm_rentiva_endpoint_view_booking', 'view-vehicle-booking');
        echo '<input type="text" name="mhm_rentiva_settings[mhm_rentiva_endpoint_view_booking]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . esc_html__('Default: view-vehicle-booking', 'mhm-rentiva') . '</p>';
    }
    public static function on_update_option(string $option, $old_value, $value): void
    {
        if ($option !== 'mhm_rentiva_settings') {
            return;
        }

        // List of endpoint keys to check
        $endpoints = [
            'mhm_rentiva_endpoint_bookings',
            'mhm_rentiva_endpoint_favorites',
            'mhm_rentiva_endpoint_payment_history',
            'mhm_rentiva_endpoint_messages',
            'mhm_rentiva_endpoint_edit_account',
            'mhm_rentiva_endpoint_view_booking'
        ];

        $should_flush = false;

        foreach ($endpoints as $key) {
            $old_val = $old_value[$key] ?? '';
            $new_val = $value[$key] ?? '';

            if ($old_val !== $new_val) {
                $should_flush = true;
                break;
            }
        }

        if ($should_flush) {
            // Flush rewrite rules
            flush_rewrite_rules();

            // Also force WooCommerce integration to re-register endpoints on next load
            update_option('mhm_rentiva_woocommerce_endpoints_flushed', false);
        }
    }
}
