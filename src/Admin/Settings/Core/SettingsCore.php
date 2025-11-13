<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Settings\Core;

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

        \add_settings_section(
            'mhm_rentiva_general_section',
            __('General Settings', 'mhm-rentiva'),
            [self::class, 'render_general_section_description'],
            self::PAGE
        );

        \add_settings_field(
            'mhm_rentiva_currency',
            __('Currency', 'mhm-rentiva'),
            [self::class, 'render_currency_field'],
            self::PAGE,
            'mhm_rentiva_general_section'
        );

        add_settings_field(
            'mhm_rentiva_currency_position',
            __('Currency Position', 'mhm-rentiva'),
            [self::class, 'render_currency_position_field'],
            self::PAGE,
            'mhm_rentiva_general_section'
        );

        add_settings_field(
            'mhm_rentiva_dark_mode',
            __('Dark Mode', 'mhm-rentiva'),
            [self::class, 'render_dark_mode_field'],
            self::PAGE,
            'mhm_rentiva_general_section'
        );

        // ⭐ Company & Support URLs
        add_settings_field(
            'mhm_rentiva_company_website',
            __('Company Website', 'mhm-rentiva'),
            [self::class, 'render_company_website_field'],
            self::PAGE,
            'mhm_rentiva_general_section'
        );

        add_settings_field(
            'mhm_rentiva_support_email',
            __('Support Email', 'mhm-rentiva'),
            [self::class, 'render_support_email_field'],
            self::PAGE,
            'mhm_rentiva_general_section'
        );

        // ⭐ Site Information Section
        add_settings_section(
            'mhm_rentiva_site_info_section',
            __('Site Information', 'mhm-rentiva'),
            [self::class, 'render_site_info_section_description'],
            self::PAGE
        );

        add_settings_field(
            'mhm_rentiva_site_url',
            __('Site URL', 'mhm-rentiva'),
            [self::class, 'render_site_url_field'],
            self::PAGE,
            'mhm_rentiva_site_info_section'
        );

        add_settings_field(
            'mhm_rentiva_home_url',
            __('Home URL', 'mhm-rentiva'),
            [self::class, 'render_home_url_field'],
            self::PAGE,
            'mhm_rentiva_site_info_section'
        );

        add_settings_field(
            'mhm_rentiva_admin_email',
            __('Admin Email', 'mhm-rentiva'),
            [self::class, 'render_admin_email_field'],
            self::PAGE,
            'mhm_rentiva_site_info_section'
        );

        add_settings_field(
            'mhm_rentiva_site_language',
            __('Site Language', 'mhm-rentiva'),
            [self::class, 'render_site_language_field'],
            self::PAGE,
            'mhm_rentiva_site_info_section'
        );

        // ⭐ Date & Time Section
        add_settings_section(
            'mhm_rentiva_datetime_section',
            __('Date & Time Settings', 'mhm-rentiva'),
            [self::class, 'render_datetime_section_description'],
            self::PAGE
        );

        add_settings_field(
            'mhm_rentiva_date_format',
            __('Date Format', 'mhm-rentiva'),
            [self::class, 'render_date_format_field'],
            self::PAGE,
            'mhm_rentiva_datetime_section'
        );

        add_settings_field(
            'mhm_rentiva_start_of_week',
            __('Start of Week', 'mhm-rentiva'),
            [self::class, 'render_start_of_week_field'],
            self::PAGE,
            'mhm_rentiva_datetime_section'
        );

        add_settings_field(
            'mhm_rentiva_default_rental_days',
            __('Default Rental Days', 'mhm-rentiva'),
            [self::class, 'render_default_rental_days_field'],
            self::PAGE,
            'mhm_rentiva_datetime_section'
        );

        add_settings_field(
            'mhm_rentiva_brand_name',
            __('Brand Name', 'mhm-rentiva'),
            [self::class, 'render_brand_name_field'],
            self::PAGE,
            'mhm_rentiva_general_section'
        );

        // Clean Data on Uninstall setting
        add_settings_field(
            'mhm_rentiva_clean_data_on_uninstall',
            __('Clean Data on Uninstall', 'mhm-rentiva'),
            [self::class, 'render_clean_data_on_uninstall_field'],
            self::PAGE,
            'mhm_rentiva_general_section'
        );

        // ⭐ System Information Section
        add_settings_section(
            'mhm_rentiva_system_info_section',
            __('System Information', 'mhm-rentiva'),
            [self::class, 'render_system_info_section_description'],
            self::PAGE
        );

        add_settings_field(
            'mhm_rentiva_plugin_version',
            __('Plugin Version', 'mhm-rentiva'),
            [self::class, 'render_plugin_version_field'],
            self::PAGE,
            'mhm_rentiva_system_info_section'
        );

        add_settings_field(
            'mhm_rentiva_wp_version',
            __('WordPress Version', 'mhm-rentiva'),
            [self::class, 'render_wp_version_field'],
            self::PAGE,
            'mhm_rentiva_system_info_section'
        );

        add_settings_field(
            'mhm_rentiva_php_version',
            __('PHP Version', 'mhm-rentiva'),
            [self::class, 'render_php_version_field'],
            self::PAGE,
            'mhm_rentiva_system_info_section'
        );

        // Email settings now managed in EmailSettings class
    }

    public static function defaults(): array
    {
        return [
            'mhm_rentiva_currency'            => 'USD',
            'mhm_rentiva_currency_position'   => 'right_space', // left, right, left_space, right_space
            // ⭐ Company & Support
            'mhm_rentiva_company_website'     => 'https://maxhandmade.com',
            'mhm_rentiva_support_email'       => 'destek@maxhandmade.com',
            'mhm_rentiva_date_format'         => 'Y-m-d',
            'mhm_rentiva_default_rental_days' => 1,
            
            // ⭐ Site Information
            'mhm_rentiva_site_url'            => get_option('siteurl', ''),
            'mhm_rentiva_home_url'            => get_option('home', ''),
            'mhm_rentiva_admin_email'         => get_option('admin_email', ''),
            'mhm_rentiva_site_language'       => get_locale(),
            'mhm_rentiva_start_of_week'       => get_option('start_of_week', 1),

            // ⭐ Booking Settings (Only actively used settings)
            'mhm_rentiva_booking_cancellation_deadline_hours' => 24,
            'mhm_rentiva_booking_payment_deadline_minutes' => 30,
            'mhm_rentiva_booking_auto_cancel_enabled' => '1',
            'mhm_rentiva_booking_send_confirmation_emails' => '1',
            'mhm_rentiva_booking_send_reminder_emails' => '1',
            'mhm_rentiva_booking_admin_notifications' => '1',

            'comparison_fields'               => [],
            // Email settings managed in EmailSettings class
            
            // Frontend URL & Text Settings (Global i18n support)
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
            'mhm_rentiva_vehicle_cards_per_page'       => 12,
            'mhm_rentiva_vehicle_default_sort'         => 'price_asc',
            'mhm_rentiva_vehicle_show_images'          => '1',
            'mhm_rentiva_vehicle_show_features'        => '1',
            'mhm_rentiva_vehicle_show_availability'    => '1',
            'mhm_rentiva_vehicle_min_rental_days'      => 1,
            'mhm_rentiva_vehicle_max_rental_days'      => 30,
            'mhm_rentiva_vehicle_advance_booking_days' => 365,
            'mhm_rentiva_vehicle_allow_same_day'       => '1',
            
            // Customer Management Settings (Only actively used settings)
            'mhm_rentiva_customer_registration_enabled'      => '1',
            'mhm_rentiva_customer_email_verification'        => '1',
            'mhm_rentiva_customer_phone_required'            => '1',
            'mhm_rentiva_customer_terms_required'            => '1',
            'mhm_rentiva_customer_terms_text'                => 'I accept the terms of use and privacy policy.',
            'mhm_rentiva_customer_auto_login'                => '1',
            'mhm_rentiva_customer_welcome_email'             => '1',
            'mhm_rentiva_customer_booking_notifications'     => '1',
            'mhm_rentiva_customer_password_min_length'       => 8,
            'mhm_rentiva_customer_password_require_special'  => '1',
            'mhm_rentiva_customer_gdpr_compliance'           => '1',
            'mhm_rentiva_customer_default_role'              => 'customer',
            'mhm_rentiva_customer_notification_frequency'    => 'immediate',
            
            // Notification Settings
            'mhm_rentiva_email_booking_confirmation'     => '1',
            'mhm_rentiva_email_payment_confirmation'     => '1',
            'mhm_rentiva_email_booking_reminder'         => '1',
            'mhm_rentiva_email_booking_cancellation'     => '1',
            'mhm_rentiva_email_reminder_hours'           => 24,
            'mhm_rentiva_email_from_name'                => get_bloginfo('name'),
            'mhm_rentiva_email_from_address'             => get_option('admin_email'),
            
            // Security Settings (Rate Limiting & Performance)
            'mhm_rentiva_rate_limit_enabled'                => '1',
            'mhm_rentiva_rate_limit_requests_per_minute'    => 60,
            'mhm_rentiva_rate_limit_booking_per_minute'     => 5,
            'mhm_rentiva_rate_limit_payment_per_minute'     => 3,
            'mhm_rentiva_rate_limit_block_duration'         => 15,
            'mhm_rentiva_rate_limit_general_minute'         => 60,
            'mhm_rentiva_rate_limit_booking_minute'         => 5,
            'mhm_rentiva_rate_limit_payment_minute'         => 3,
            // Cache Settings
            'mhm_rentiva_cache_enabled'                     => '1',
            'mhm_rentiva_cache_default_ttl'                 => 1,
            'mhm_rentiva_cache_lists_ttl'                   => 5,
            'mhm_rentiva_cache_reports_ttl'                 => 15,
            'mhm_rentiva_cache_charts_ttl'                  => 10,
            // Database Settings
            'mhm_rentiva_db_auto_optimize'                  => '0',
            'mhm_rentiva_db_performance_threshold'          => 100,
            // WordPress Optimization
            'mhm_rentiva_wp_optimization_enabled'           => '1',
            'mhm_rentiva_wp_memory_limit'                   => 256,
            'mhm_rentiva_wp_meta_query_limit'               => 5,
            // IP Control & Security
            'mhm_rentiva_ip_whitelist_enabled'              => '0',
            'mhm_rentiva_ip_whitelist'                      => '',
            'mhm_rentiva_ip_blacklist_enabled'              => '1',
            'mhm_rentiva_ip_blacklist'                      => '',
            'mhm_rentiva_country_restriction_enabled'       => '0',
            'mhm_rentiva_allowed_countries'                 => '',
            'mhm_rentiva_brute_force_protection'            => '1',
            'mhm_rentiva_max_login_attempts'                => 5,
            'mhm_rentiva_login_lockout_duration'            => 30,
            'mhm_rentiva_sql_injection_protection'          => '1',
            'mhm_rentiva_xss_protection'                    => '1',
            'mhm_rentiva_csrf_protection'                   => '1',
            'mhm_rentiva_strong_passwords'                  => '1',
            'mhm_rentiva_password_expiry_days'              => 0,
            'mhm_rentiva_two_factor_auth'                   => '0',
            'mhm_rentiva_session_security'                  => '1',
            // Reconciliation Settings
            'mhm_rentiva_reconcile_frequency'               => 'daily',
            'mhm_rentiva_reconcile_timeout'                 => 30,
            'mhm_rentiva_reconcile_notify_errors'           => '1',
            'mhm_rentiva_brand_name'          => get_bloginfo('name'),
            'mhm_rentiva_brand_logo_url'      => '',
            'mhm_rentiva_email_primary_color' => '#1e88e5',
            'mhm_rentiva_email_footer_text'   => '© {Y} {site}',
            'mhm_rentiva_stripe_enabled'      => '0',
            'mhm_rentiva_stripe_mode'         => 'test',
            'mhm_rentiva_stripe_test_mode'    => '1',
            'mhm_rentiva_stripe_publishable_key' => '',
            'mhm_rentiva_stripe_secret_key'   => '',
            'mhm_rentiva_stripe_pk_test'      => '',
            'mhm_rentiva_stripe_sk_test'      => '',
            'mhm_rentiva_stripe_webhook_secret_test' => '',
            'mhm_rentiva_stripe_pk_live'      => '',
            'mhm_rentiva_stripe_sk_live'      => '',
            'mhm_rentiva_stripe_webhook_secret_live' => '',
            'mhm_rentiva_paytr_enabled'       => '0',
            'mhm_rentiva_paytr_test_mode'     => '1',
            'mhm_rentiva_paytr_merchant_id'   => '',
            'mhm_rentiva_paytr_merchant_key'  => '',
            'mhm_rentiva_paytr_merchant_salt' => '',
            'mhm_rentiva_paytr_no_installment' => '0',
            'mhm_rentiva_paytr_max_installment' => '12',
            'mhm_rentiva_paytr_non_3d'        => '0',
            'mhm_rentiva_paytr_timeout_limit' => 30,
            'mhm_rentiva_paytr_debug_on'      => '0',
            'mhm_rentiva_auto_cancel_enabled' => '1',
            'mhm_rentiva_auto_cancel_minutes' => 30,
            'mhm_rentiva_reconcile_enabled' => '0',
            'mhm_rentiva_log_level' => 'info',
            'mhm_rentiva_log_retention_days'  => 30,
            'mhm_rentiva_log_cleanup_enabled' => '1',
            'mhm_rentiva_debug_mode' => '0',
            'mhm_rentiva_log_max_size' => 10,
            'mhm_rentiva_offline_enabled'     => '1',
            'mhm_rentiva_offline_instructions' => __('You can make payments via bank transfer. Please don\'t forget to write your reservation number in the description.', 'mhm-rentiva'),
            'mhm_rentiva_offline_accounts'    => '',
            'mhm_rentiva_paypal_enabled'      => '0',
            'mhm_rentiva_paypal_mode'         => 'sandbox',
            'mhm_rentiva_paypal_test_mode'    => '1',
            'mhm_rentiva_paypal_client_id'    => '',
            'mhm_rentiva_paypal_client_secret' => '',
            'mhm_rentiva_paypal_client_id_test' => '',
            'mhm_rentiva_paypal_client_secret_test' => '',
            'mhm_rentiva_paypal_client_id_live' => '',
            'mhm_rentiva_paypal_client_secret_live' => '',
            'mhm_rentiva_paypal_currency' => 'USD',
            'mhm_rentiva_paypal_webhook_id' => '',
            'mhm_rentiva_paypal_debug_mode' => '0',
            'mhm_rentiva_paypal_timeout' => 30,
            
            // Clean Data on Uninstall
            'mhm_rentiva_clean_data_on_uninstall' => '0',
            
            
            // Vehicle Pricing settings - integrated from VehiclePricingSettings
            'vehicle_pricing' => [
                'seasonal_multipliers' => [
                    'spring' => [
                        'name' => __('Spring', 'mhm-rentiva'),
                        'months' => [3, 4, 5],
                        'multiplier' => 1.0,
                        'description' => __('Standard pricing', 'mhm-rentiva')
                    ],
                    'summer' => [
                        'name' => __('Summer', 'mhm-rentiva'),
                        'months' => [6, 7, 8],
                        'multiplier' => 1.3,
                        'description' => __('High season pricing', 'mhm-rentiva')
                    ],
                    'autumn' => [
                        'name' => __('Autumn', 'mhm-rentiva'),
                        'months' => [9, 10, 11],
                        'multiplier' => 1.1,
                        'description' => __('Mid season pricing', 'mhm-rentiva')
                    ],
                    'winter' => [
                        'name' => __('Winter', 'mhm-rentiva'),
                        'months' => [12, 1, 2],
                        'multiplier' => 0.8,
                        'description' => __('Low season pricing', 'mhm-rentiva')
                    ]
                ],
                
                'discount_options' => [
                    'weekly' => [
                        'name' => __('Weekly Discount', 'mhm-rentiva'),
                        'description' => __('7 days and above rental', 'mhm-rentiva'),
                        'min_days' => 7,
                        'discount_percent' => 10,
                        'type' => 'percentage',
                        'enabled' => true
                    ],
                    'monthly' => [
                        'name' => __('Monthly Discount', 'mhm-rentiva'),
                        'description' => __('30 days and above rental', 'mhm-rentiva'),
                        'min_days' => 30,
                        'discount_percent' => 20,
                        'type' => 'percentage',
                        'enabled' => true
                    ],
                    'early_booking' => [
                        'name' => __('Early Booking', 'mhm-rentiva'),
                        'description' => __('30 days advance booking', 'mhm-rentiva'),
                        'advance_days' => 30,
                        'discount_percent' => 5,
                        'type' => 'percentage',
                        'enabled' => true
                    ],
                    'loyalty' => [
                        'name' => __('Loyalty Discount', 'mhm-rentiva'),
                        'description' => __('Regular customer discount', 'mhm-rentiva'),
                        'discount_percent' => 15,
                        'type' => 'percentage',
                        'enabled' => false
                    ]
                ],
                
                'addon_services' => [],
                
                'currency_settings' => [
                    'default_currency' => 'USD'
                ],
                
                'deposit_settings' => [
                    'enable_deposit' => true,
                    'deposit_type' => 'both', // 'fixed', 'percentage', 'both'
                    'allow_no_deposit' => true,
                    'required_for_booking' => false,
                    'show_deposit_in_listing' => true,
                    'show_deposit_in_detail' => true,
                    'deposit_refund_policy' => __('Deposit is non-refundable, deducted from total rental amount.', 'mhm-rentiva'),
                    'deposit_payment_methods' => ['credit_card', 'cash', 'bank_transfer']
                ],
                
                'general_settings' => [
                    'min_rental_days' => 1,
                    'max_rental_days' => 365,
                    'default_rental_days' => 3,
                    'price_calculation_method' => 'daily',
                    'round_prices' => true,
                    'decimal_places' => 2
                ]
            ]
        ];
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
                    'mhm_rentiva_company_website',
                    'mhm_rentiva_support_email',
                    'mhm_rentiva_date_format',
                    'mhm_rentiva_default_rental_days',
                    'mhm_rentiva_site_url',
                    'mhm_rentiva_home_url',
                    'mhm_rentiva_admin_email',
                    'mhm_rentiva_site_language',
                    'mhm_rentiva_start_of_week',
                    'mhm_rentiva_clean_data_on_uninstall'
                ];
                break;
                
            case 'vehicle':
                // Vehicle settings keys (all keys starting with mhm_rentiva_vehicle_)
                foreach (array_keys($all_defaults) as $key) {
                    if (strpos($key, 'mhm_rentiva_vehicle_') === 0) {
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
                    if (strpos($key, 'mhm_rentiva_stripe_') === 0 ||
                        strpos($key, 'mhm_rentiva_paypal_') === 0 ||
                        strpos($key, 'mhm_rentiva_paytr_') === 0 ||
                        strpos($key, 'mhm_rentiva_offline_') === 0 ||
                        strpos($key, 'mhm_rentiva_booking_default_payment_method') === 0) {
                        $tab_keys[] = $key;
                    }
                }
                break;
                
            case 'system':
                // System & Performance settings keys
                foreach (array_keys($all_defaults) as $key) {
                    if (strpos($key, 'mhm_rentiva_rate_limit_') === 0 ||
                        strpos($key, 'mhm_rentiva_cache_') === 0 ||
                        strpos($key, 'mhm_rentiva_db_') === 0 ||
                        strpos($key, 'mhm_rentiva_auto_') === 0 ||
                        strpos($key, 'mhm_rentiva_log_') === 0 ||
                        strpos($key, 'mhm_rentiva_reconcile_') === 0 ||
                        strpos($key, 'mhm_rentiva_ip_') === 0 ||
                        strpos($key, 'mhm_rentiva_security_') === 0 ||
                        strpos($key, 'mhm_rentiva_authentication_') === 0 ||
                        strpos($key, 'mhm_rentiva_maintenance_') === 0) {
                        $tab_keys[] = $key;
                    }
                }
                break;
                
            case 'frontend':
                // Frontend URL & Text settings keys
                foreach (array_keys($all_defaults) as $key) {
                    if (strpos($key, 'mhm_rentiva_booking_url') === 0 ||
                        strpos($key, 'mhm_rentiva_login_url') === 0 ||
                        strpos($key, 'mhm_rentiva_register_url') === 0 ||
                        strpos($key, 'mhm_rentiva_my_') === 0 ||
                        strpos($key, 'mhm_rentiva_vehicles_list_url') === 0 ||
                        strpos($key, 'mhm_rentiva_search_url') === 0 ||
                        strpos($key, 'mhm_rentiva_contact_url') === 0 ||
                        strpos($key, 'mhm_rentiva_text_') === 0 ||
                        strpos($key, 'comparison_fields') === 0) {
                        $tab_keys[] = $key;
                    }
                }
                break;
        }
        
        // Filter to only include keys that exist in defaults
        return array_filter($tab_keys, function($key) use ($all_defaults) {
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


    /**
     * General Settings Section Description
     */
    public static function render_general_section_description(): void
    {
        echo '<p>' . esc_html__('Configure general system settings.', 'mhm-rentiva') . '</p>';
    }

    /**
     * Currency Field
     */
    public static function render_currency_field(): void
    {
        $currency = self::get('mhm_rentiva_currency', 'USD');
        // Use centralized currency list from CurrencyHelper
        $currencies = \MHMRentiva\Admin\Core\CurrencyHelper::get_currency_list_for_dropdown();
        
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
        $position = self::get('mhm_rentiva_currency_position', 'right_space');
        $positions = [
            'left' => 'Left ($100)',
            'left_space' => 'Left Space ($ 100)',
            'right' => 'Right (100$)',
            'right_space' => 'Right Space (100 $)'
        ];
        
        echo '<select name="mhm_rentiva_settings[mhm_rentiva_currency_position]">';
        foreach ($positions as $pos => $name) {
            echo '<option value="' . esc_attr($pos) . '"' . selected($position, $pos, false) . '>' . esc_html($name) . '</option>';
        }
        echo '</select>';
    }

    /**
     * Date Format Field
     */
    public static function render_date_format_field(): void
    {
        $format = self::get('mhm_rentiva_date_format', 'Y-m-d');
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
        $days = self::get('mhm_rentiva_default_rental_days', 1);
        echo '<input type="number" name="mhm_rentiva_settings[mhm_rentiva_default_rental_days]" value="' . esc_attr($days) . '" min="1" max="365" class="small-text" />';
        echo '<p class="description">' . esc_html__('Default rental duration in booking form (days).', 'mhm-rentiva') . '</p>';
    }

    /**
     * Brand Name Field
     */
    public static function render_brand_name_field(): void
    {
        $brand = self::get('mhm_rentiva_brand_name', get_bloginfo('name'));
        echo '<input type="text" name="mhm_rentiva_settings[mhm_rentiva_brand_name]" value="' . esc_attr($brand) . '" class="regular-text" />';
        echo '<p class="description">' . esc_html__('Brand name to appear in emails and documents.', 'mhm-rentiva') . '</p>';
    }

    /**
     * Clean Data on Uninstall Field
     */
    public static function render_clean_data_on_uninstall_field(): void
    {
        $enabled = self::get('mhm_rentiva_clean_data_on_uninstall', '0');
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
        
        if (class_exists('\MHMRentiva\Admin\Settings\Groups\VehicleComparisonSettings')) {
            \MHMRentiva\Admin\Settings\Groups\VehicleComparisonSettings::register();
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
    public static function register_payment_settings(): void
    {
        // Register Stripe Settings
        if (class_exists('\MHMRentiva\Admin\Payment\Settings\StripeSettings')) {
            \MHMRentiva\Admin\Payment\Settings\StripeSettings::register_general_payment_settings();
            \MHMRentiva\Admin\Payment\Settings\StripeSettings::register_payment_gateway_status();
            \MHMRentiva\Admin\Payment\Settings\StripeSettings::register();
        }
        
        // Register PayPal Settings
        if (class_exists('\MHMRentiva\Admin\Payment\Settings\PayPalSettings')) {
            \MHMRentiva\Admin\Payment\Settings\PayPalSettings::register();
        }
        
        // Register PayTR Settings
        if (class_exists('\MHMRentiva\Admin\Payment\Settings\PayTRSettings')) {
            \MHMRentiva\Admin\Payment\Settings\PayTRSettings::register();
        }
        
        // Register Offline Payment Settings
        if (class_exists('\MHMRentiva\Admin\Payment\Settings\OfflinePaymentSettings')) {
            \MHMRentiva\Admin\Payment\Settings\OfflinePaymentSettings::register();
        }
    }

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
        add_settings_section(
            'mhm_rentiva_frontend_section',
            __('Frontend URL and Text Settings', 'mhm-rentiva'),
            [self::class, 'render_frontend_section_description'],
            self::PAGE
        );

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

        add_settings_field(
            'mhm_rentiva_my_account_url',
            __('My Account Page URL', 'mhm-rentiva'),
            [self::class, 'render_my_account_url_field'],
            self::PAGE,
            'mhm_rentiva_frontend_section'
        );

        add_settings_field(
            'mhm_rentiva_my_bookings_url',
            __('My Bookings Page URL', 'mhm-rentiva'),
            [self::class, 'render_my_bookings_url_field'],
            self::PAGE,
            'mhm_rentiva_frontend_section'
        );

        add_settings_field(
            'mhm_rentiva_my_favorites_url',
            __('My Favorites Page URL', 'mhm-rentiva'),
            [self::class, 'render_my_favorites_url_field'],
            self::PAGE,
            'mhm_rentiva_frontend_section'
        );

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
            'mhm_rentiva_button_texts_section',
            __('Button and Text Customizations', 'mhm-rentiva'),
            [self::class, 'render_button_texts_section_description'],
            self::PAGE
        );

        add_settings_field(
            'mhm_rentiva_text_book_now',
            __('Booking Button Text', 'mhm-rentiva'),
            [self::class, 'render_book_now_text_field'],
            self::PAGE,
            'mhm_rentiva_button_texts_section'
        );

        add_settings_field(
            'mhm_rentiva_text_view_details',
            __('View Details Button Text', 'mhm-rentiva'),
            [self::class, 'render_view_details_text_field'],
            self::PAGE,
            'mhm_rentiva_button_texts_section'
        );

        add_settings_field(
            'mhm_rentiva_text_added_to_favorites',
            __('Added to Favorites Message', 'mhm-rentiva'),
            [self::class, 'render_added_to_favorites_text_field'],
            self::PAGE,
            'mhm_rentiva_button_texts_section'
        );

        add_settings_field(
            'mhm_rentiva_text_removed_from_favorites',
            __('Removed from Favorites Message', 'mhm-rentiva'),
            [self::class, 'render_removed_from_favorites_text_field'],
            self::PAGE,
            'mhm_rentiva_button_texts_section'
        );

        add_settings_field(
            'mhm_rentiva_text_login_required',
            __('Login Required Message', 'mhm-rentiva'),
            [self::class, 'render_login_required_text_field'],
            self::PAGE,
            'mhm_rentiva_button_texts_section'
        );

        add_settings_section(
            'mhm_rentiva_action_texts_section',
            __('Action Buttons and Messages', 'mhm-rentiva'),
            [self::class, 'render_action_texts_section_description'],
            self::PAGE
        );

        add_settings_field(
            'mhm_rentiva_text_make_booking',
            __('Make Booking Button', 'mhm-rentiva'),
            [self::class, 'render_make_booking_text_field'],
            self::PAGE,
            'mhm_rentiva_action_texts_section'
        );

        add_settings_field(
            'mhm_rentiva_text_processing',
            __('Processing Button', 'mhm-rentiva'),
            [self::class, 'render_processing_text_field'],
            self::PAGE,
            'mhm_rentiva_action_texts_section'
        );

        add_settings_field(
            'mhm_rentiva_text_back_to_bookings',
            __('Back to Bookings Button', 'mhm-rentiva'),
            [self::class, 'render_back_to_bookings_text_field'],
            self::PAGE,
            'mhm_rentiva_action_texts_section'
        );

        add_settings_field(
            'mhm_rentiva_text_cancel_booking',
            __('Cancel Booking Button', 'mhm-rentiva'),
            [self::class, 'render_cancel_booking_text_field'],
            self::PAGE,
            'mhm_rentiva_action_texts_section'
        );

        add_settings_field(
            'mhm_rentiva_text_view_dashboard',
            __('View Dashboard Button', 'mhm-rentiva'),
            [self::class, 'render_view_dashboard_text_field'],
            self::PAGE,
            'mhm_rentiva_action_texts_section'
        );

        add_settings_field(
            'mhm_rentiva_text_login_here',
            __('Login Here Link', 'mhm-rentiva'),
            [self::class, 'render_login_here_text_field'],
            self::PAGE,
            'mhm_rentiva_action_texts_section'
        );

        add_settings_field(
            'mhm_rentiva_text_already_have_account',
            __('Already Have Account Message', 'mhm-rentiva'),
            [self::class, 'render_already_have_account_text_field'],
            self::PAGE,
            'mhm_rentiva_action_texts_section'
        );

        add_settings_section(
            'mhm_rentiva_form_labels_section',
            __('Form Labels', 'mhm-rentiva'),
            [self::class, 'render_form_labels_section_description'],
            self::PAGE
        );

        add_settings_field(
            'mhm_rentiva_text_first_name',
            __('First Name Label', 'mhm-rentiva'),
            [self::class, 'render_first_name_text_field'],
            self::PAGE,
            'mhm_rentiva_form_labels_section'
        );

        add_settings_field(
            'mhm_rentiva_text_last_name',
            __('Last Name Label', 'mhm-rentiva'),
            [self::class, 'render_last_name_text_field'],
            self::PAGE,
            'mhm_rentiva_form_labels_section'
        );

        add_settings_field(
            'mhm_rentiva_text_email',
            __('Email Label', 'mhm-rentiva'),
            [self::class, 'render_email_text_field'],
            self::PAGE,
            'mhm_rentiva_form_labels_section'
        );

        add_settings_field(
            'mhm_rentiva_text_phone',
            __('Phone Label', 'mhm-rentiva'),
            [self::class, 'render_phone_text_field'],
            self::PAGE,
            'mhm_rentiva_form_labels_section'
        );

        add_settings_section(
            'mhm_rentiva_message_texts_section',
            __('System Messages', 'mhm-rentiva'),
            [self::class, 'render_message_texts_section_description'],
            self::PAGE
        );

        add_settings_field(
            'mhm_rentiva_text_loading',
            __('Loading Message', 'mhm-rentiva'),
            [self::class, 'render_loading_text_field'],
            self::PAGE,
            'mhm_rentiva_message_texts_section'
        );

        add_settings_field(
            'mhm_rentiva_text_error',
            __('Error Message', 'mhm-rentiva'),
            [self::class, 'render_error_text_field'],
            self::PAGE,
            'mhm_rentiva_message_texts_section'
        );

        add_settings_field(
            'mhm_rentiva_text_booking_success',
            __('Booking Success Message', 'mhm-rentiva'),
            [self::class, 'render_booking_success_text_field'],
            self::PAGE,
            'mhm_rentiva_message_texts_section'
        );

        add_settings_field(
            'mhm_rentiva_text_select_vehicle',
            __('Select Vehicle Message', 'mhm-rentiva'),
            [self::class, 'render_select_vehicle_text_field'],
            self::PAGE,
            'mhm_rentiva_message_texts_section'
        );

        add_settings_field(
            'mhm_rentiva_text_select_dates',
            __('Select Dates Message', 'mhm-rentiva'),
            [self::class, 'render_select_dates_text_field'],
            self::PAGE,
            'mhm_rentiva_message_texts_section'
        );

        add_settings_field(
            'mhm_rentiva_text_invalid_dates',
            __('Invalid Dates Message', 'mhm-rentiva'),
            [self::class, 'render_invalid_dates_text_field'],
            self::PAGE,
            'mhm_rentiva_message_texts_section'
        );

        add_settings_field(
            'mhm_rentiva_text_select_payment_type',
            __('Select Payment Type Message', 'mhm-rentiva'),
            [self::class, 'render_select_payment_type_text_field'],
            self::PAGE,
            'mhm_rentiva_message_texts_section'
        );

        add_settings_field(
            'mhm_rentiva_text_select_payment_method',
            __('Select Payment Method Message', 'mhm-rentiva'),
            [self::class, 'render_select_payment_method_text_field'],
            self::PAGE,
            'mhm_rentiva_message_texts_section'
        );

        add_settings_field(
            'mhm_rentiva_text_calculating',
            __('Calculating Message', 'mhm-rentiva'),
            [self::class, 'render_calculating_text_field'],
            self::PAGE,
            'mhm_rentiva_message_texts_section'
        );

        add_settings_field(
            'mhm_rentiva_text_payment_redirect',
            __('Payment Redirect Message', 'mhm-rentiva'),
            [self::class, 'render_payment_redirect_text_field'],
            self::PAGE,
            'mhm_rentiva_message_texts_section'
        );

        add_settings_field(
            'mhm_rentiva_text_payment_success',
            __('Payment Success Message', 'mhm-rentiva'),
            [self::class, 'render_payment_success_text_field'],
            self::PAGE,
            'mhm_rentiva_message_texts_section'
        );

        add_settings_field(
            'mhm_rentiva_text_payment_cancelled',
            __('Payment Cancelled Message', 'mhm-rentiva'),
            [self::class, 'render_payment_cancelled_text_field'],
            self::PAGE,
            'mhm_rentiva_message_texts_section'
        );

        add_settings_field(
            'mhm_rentiva_text_popup_blocked',
            __('Popup Blocked Message', 'mhm-rentiva'),
            [self::class, 'render_popup_blocked_text_field'],
            self::PAGE,
            'mhm_rentiva_message_texts_section'
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
        echo '<input type="url" name="mhm_rentiva_settings[mhm_rentiva_booking_url]" value="' . esc_attr($value) . '" class="regular-text" placeholder="https://example.com/booking/" />';
        echo '<p class="description">' . __('Example: https://example.com/booking/ or /booking-form/ (auto-detected if left empty)', 'mhm-rentiva') . '</p>';
    }

    /**
     * Login URL field
     */
    public static function render_login_url_field(): void
    {
        $value = self::get('mhm_rentiva_login_url', '');
        echo '<input type="url" name="mhm_rentiva_settings[mhm_rentiva_login_url]" value="' . esc_attr($value) . '" class="regular-text" placeholder="https://example.com/login/" />';
        echo '<p class="description">' . __('Example: https://example.com/login/ or /member-login/ (WordPress default login page is used if left empty)', 'mhm-rentiva') . '</p>';
    }

    /**
     * Register URL field
     */
    public static function render_register_url_field(): void
    {
        $value = self::get('mhm_rentiva_register_url', '');
        echo '<input type="url" name="mhm_rentiva_settings[mhm_rentiva_register_url]" value="' . esc_attr($value) . '" class="regular-text" placeholder="https://example.com/register/" />';
        echo '<p class="description">' . __('Example: https://example.com/register/ or /kayit/ (auto-detected if left empty)', 'mhm-rentiva') . '</p>';
    }

    /**
     * My Account URL field
     */
    public static function render_my_account_url_field(): void
    {
        $value = self::get('mhm_rentiva_my_account_url', '');
        echo '<input type="url" name="mhm_rentiva_settings[mhm_rentiva_my_account_url]" value="' . esc_attr($value) . '" class="regular-text" placeholder="https://example.com/my-account/" />';
        echo '<p class="description">' . __('Example: https://example.com/my-account/ or /hesabim/ (auto-detected if left empty)', 'mhm-rentiva') . '</p>';
    }

    /**
     * My Bookings URL field
     */
    public static function render_my_bookings_url_field(): void
    {
        $value = self::get('mhm_rentiva_my_bookings_url', '');
        echo '<input type="url" name="mhm_rentiva_settings[mhm_rentiva_my_bookings_url]" value="' . esc_attr($value) . '" class="regular-text" placeholder="https://example.com/my-bookings/" />';
        echo '<p class="description">' . __('Example: https://example.com/my-bookings/ or /rezervasyonlarim/ (auto-detected if left empty)', 'mhm-rentiva') . '</p>';
    }

    /**
     * My Favorites URL field
     */
    public static function render_my_favorites_url_field(): void
    {
        $value = self::get('mhm_rentiva_my_favorites_url', '');
        echo '<input type="url" name="mhm_rentiva_settings[mhm_rentiva_my_favorites_url]" value="' . esc_attr($value) . '" class="regular-text" placeholder="https://example.com/my-favorites/" />';
        echo '<p class="description">' . __('Example: https://example.com/my-favorites/ or /favorilerim/ (auto-detected if left empty)', 'mhm-rentiva') . '</p>';
    }

    /**
     * Vehicles List URL field
     */
    public static function render_vehicles_list_url_field(): void
    {
        $value = self::get('mhm_rentiva_vehicles_list_url', '');
        echo '<input type="url" name="mhm_rentiva_settings[mhm_rentiva_vehicles_list_url]" value="' . esc_attr($value) . '" class="regular-text" placeholder="https://example.com/vehicles/" />';
        echo '<p class="description">' . __('Example: https://example.com/vehicles/ or /araclar/ (auto-detected if left empty)', 'mhm-rentiva') . '</p>';
    }

    /**
     * Search URL field
     */
    public static function render_search_url_field(): void
    {
        $value = self::get('mhm_rentiva_search_url', '');
        echo '<input type="url" name="mhm_rentiva_settings[mhm_rentiva_search_url]" value="' . esc_attr($value) . '" class="regular-text" placeholder="https://example.com/search/" />';
        echo '<p class="description">' . __('Example: https://example.com/search/ or /arama/ (auto-detected if left empty)', 'mhm-rentiva') . '</p>';
    }

    /**
     * Contact URL field
     */
    public static function render_contact_url_field(): void
    {
        $value = self::get('mhm_rentiva_contact_url', '');
        echo '<input type="url" name="mhm_rentiva_settings[mhm_rentiva_contact_url]" value="' . esc_attr($value) . '" class="regular-text" placeholder="https://example.com/contact/" />';
        echo '<p class="description">' . __('Example: https://example.com/contact/ or /iletisim/ (auto-detected if left empty)', 'mhm-rentiva') . '</p>';
    }

    /**
     * Booking button text field
     */
    public static function render_book_now_text_field(): void
    {
        $value = self::get('mhm_rentiva_text_book_now', '');
        echo '<input type="text" name="mhm_rentiva_settings[mhm_rentiva_text_book_now]" value="' . esc_attr($value) . '" class="regular-text" placeholder="Book Now" />';
        echo '<p class="description">' . __('Default: "Book Now"', 'mhm-rentiva') . '</p>';
    }

    /**
     * View details button text field
     */
    public static function render_view_details_text_field(): void
    {
        $value = self::get('mhm_rentiva_text_view_details', '');
        echo '<input type="text" name="mhm_rentiva_settings[mhm_rentiva_text_view_details]" value="' . esc_attr($value) . '" class="regular-text" placeholder="View Details" />';
        echo '<p class="description">' . __('Default: "View Details"', 'mhm-rentiva') . '</p>';
    }

    /**
     * Added to favorites message field
     */
    public static function render_added_to_favorites_text_field(): void
    {
        $value = self::get('mhm_rentiva_text_added_to_favorites', '');
        echo '<input type="text" name="mhm_rentiva_settings[mhm_rentiva_text_added_to_favorites]" value="' . esc_attr($value) . '" class="regular-text" placeholder="Added to favorites" />';
        echo '<p class="description">' . __('Default: "Added to favorites"', 'mhm-rentiva') . '</p>';
    }

    /**
     * Removed from favorites message field
     */
    public static function render_removed_from_favorites_text_field(): void
    {
        $value = self::get('mhm_rentiva_text_removed_from_favorites', '');
        echo '<input type="text" name="mhm_rentiva_settings[mhm_rentiva_text_removed_from_favorites]" value="' . esc_attr($value) . '" class="regular-text" placeholder="Removed from favorites" />';
        echo '<p class="description">' . __('Default: "Removed from favorites"', 'mhm-rentiva') . '</p>';
    }

    /**
     * Login required message field
     */
    public static function render_login_required_text_field(): void
    {
        $value = self::get('mhm_rentiva_text_login_required', '');
        echo '<textarea name="mhm_rentiva_settings[mhm_rentiva_text_login_required]" rows="2" class="large-text" placeholder="You need to log in to add to favorites.">' . esc_textarea($value) . '</textarea>';
        echo '<p class="description">' . __('Default: "You need to log in to add to favorites."', 'mhm-rentiva') . '</p>';
    }

    /**
     * Action texts section description
     */
    public static function render_action_texts_section_description(): void
    {
        echo '<p>' . __('Customize action button texts and messages used throughout the booking process.', 'mhm-rentiva') . '</p>';
    }

    /**
     * Make Booking Button Text
     */
    public static function render_make_booking_text_field(): void
    {
        $value = self::get('mhm_rentiva_text_make_booking', '');
        echo '<input type="text" name="mhm_rentiva_settings[mhm_rentiva_text_make_booking]" value="' . esc_attr($value) . '" class="regular-text" placeholder="Make Booking" />';
        echo '<p class="description">' . __('Default: "Make Booking"', 'mhm-rentiva') . '</p>';
    }

    /**
     * Processing Button Text
     */
    public static function render_processing_text_field(): void
    {
        $value = self::get('mhm_rentiva_text_processing', '');
        echo '<input type="text" name="mhm_rentiva_settings[mhm_rentiva_text_processing]" value="' . esc_attr($value) . '" class="regular-text" placeholder="Processing..." />';
        echo '<p class="description">' . __('Default: "Processing..."', 'mhm-rentiva') . '</p>';
    }

    /**
     * Back to Bookings Button Text
     */
    public static function render_back_to_bookings_text_field(): void
    {
        $value = self::get('mhm_rentiva_text_back_to_bookings', '');
        echo '<input type="text" name="mhm_rentiva_settings[mhm_rentiva_text_back_to_bookings]" value="' . esc_attr($value) . '" class="regular-text" placeholder="Back to Bookings" />';
        echo '<p class="description">' . __('Default: "Back to Bookings"', 'mhm-rentiva') . '</p>';
    }

    /**
     * Cancel Booking Button Text
     */
    public static function render_cancel_booking_text_field(): void
    {
        $value = self::get('mhm_rentiva_text_cancel_booking', '');
        echo '<input type="text" name="mhm_rentiva_settings[mhm_rentiva_text_cancel_booking]" value="' . esc_attr($value) . '" class="regular-text" placeholder="Cancel Booking" />';
        echo '<p class="description">' . __('Default: "Cancel Booking"', 'mhm-rentiva') . '</p>';
    }

    /**
     * View Dashboard Button Text
     */
    public static function render_view_dashboard_text_field(): void
    {
        $value = self::get('mhm_rentiva_text_view_dashboard', '');
        echo '<input type="text" name="mhm_rentiva_settings[mhm_rentiva_text_view_dashboard]" value="' . esc_attr($value) . '" class="regular-text" placeholder="View Dashboard" />';
        echo '<p class="description">' . __('Default: "View Dashboard"', 'mhm-rentiva') . '</p>';
    }

    /**
     * Login Here Link Text
     */
    public static function render_login_here_text_field(): void
    {
        $value = self::get('mhm_rentiva_text_login_here', '');
        echo '<input type="text" name="mhm_rentiva_settings[mhm_rentiva_text_login_here]" value="' . esc_attr($value) . '" class="regular-text" placeholder="Login here" />';
        echo '<p class="description">' . __('Default: "Login here"', 'mhm-rentiva') . '</p>';
    }

    /**
     * Already Have Account Message
     */
    public static function render_already_have_account_text_field(): void
    {
        $value = self::get('mhm_rentiva_text_already_have_account', '');
        echo '<input type="text" name="mhm_rentiva_settings[mhm_rentiva_text_already_have_account]" value="' . esc_attr($value) . '" class="regular-text" placeholder="Already have an account?" />';
        echo '<p class="description">' . __('Default: "Already have an account?"', 'mhm-rentiva') . '</p>';
    }

    /**
     * Form labels section description
     */
    public static function render_form_labels_section_description(): void
    {
        echo '<p>' . __('Customize form field labels for customer registration and booking forms.', 'mhm-rentiva') . '</p>';
    }

    /**
     * First Name Label Text
     */
    public static function render_first_name_text_field(): void
    {
        $value = self::get('mhm_rentiva_text_first_name', '');
        echo '<input type="text" name="mhm_rentiva_settings[mhm_rentiva_text_first_name]" value="' . esc_attr($value) . '" class="regular-text" placeholder="First Name" />';
        echo '<p class="description">' . __('Default: "First Name"', 'mhm-rentiva') . '</p>';
    }

    /**
     * Last Name Label Text
     */
    public static function render_last_name_text_field(): void
    {
        $value = self::get('mhm_rentiva_text_last_name', '');
        echo '<input type="text" name="mhm_rentiva_settings[mhm_rentiva_text_last_name]" value="' . esc_attr($value) . '" class="regular-text" placeholder="Last Name" />';
        echo '<p class="description">' . __('Default: "Last Name"', 'mhm-rentiva') . '</p>';
    }

    /**
     * Email Label Text
     */
    public static function render_email_text_field(): void
    {
        $value = self::get('mhm_rentiva_text_email', '');
        echo '<input type="text" name="mhm_rentiva_settings[mhm_rentiva_text_email]" value="' . esc_attr($value) . '" class="regular-text" placeholder="Email" />';
        echo '<p class="description">' . __('Default: "Email"', 'mhm-rentiva') . '</p>';
    }

    /**
     * Phone Label Text
     */
    public static function render_phone_text_field(): void
    {
        $value = self::get('mhm_rentiva_text_phone', '');
        echo '<input type="text" name="mhm_rentiva_settings[mhm_rentiva_text_phone]" value="' . esc_attr($value) . '" class="regular-text" placeholder="Phone" />';
        echo '<p class="description">' . __('Default: "Phone"', 'mhm-rentiva') . '</p>';
    }

    /**
     * Message texts section description
     */
    public static function render_message_texts_section_description(): void
    {
        echo '<p>' . __('Customize system messages, validation errors, and success notifications displayed to users.', 'mhm-rentiva') . '</p>';
    }

    public static function render_loading_text_field(): void
    {
        $value = self::get('mhm_rentiva_text_loading', '');
        echo '<input type="text" name="mhm_rentiva_settings[mhm_rentiva_text_loading]" value="' . esc_attr($value) . '" class="regular-text" placeholder="Loading..." />';
        echo '<p class="description">' . __('Default: "Loading..."', 'mhm-rentiva') . '</p>';
    }

    public static function render_error_text_field(): void
    {
        $value = self::get('mhm_rentiva_text_error', '');
        echo '<input type="text" name="mhm_rentiva_settings[mhm_rentiva_text_error]" value="' . esc_attr($value) . '" class="regular-text" placeholder="An error occurred." />';
        echo '<p class="description">' . __('Default: "An error occurred."', 'mhm-rentiva') . '</p>';
    }

    public static function render_booking_success_text_field(): void
    {
        $value = self::get('mhm_rentiva_text_booking_success', '');
        echo '<input type="text" name="mhm_rentiva_settings[mhm_rentiva_text_booking_success]" value="' . esc_attr($value) . '" class="regular-text" placeholder="Booking successful" />';
        echo '<p class="description">' . __('Default: "Booking successful"', 'mhm-rentiva') . '</p>';
    }

    public static function render_select_vehicle_text_field(): void
    {
        $value = self::get('mhm_rentiva_text_select_vehicle', '');
        echo '<input type="text" name="mhm_rentiva_settings[mhm_rentiva_text_select_vehicle]" value="' . esc_attr($value) . '" class="regular-text" placeholder="Please select a vehicle" />';
        echo '<p class="description">' . __('Default: "Please select a vehicle"', 'mhm-rentiva') . '</p>';
    }

    public static function render_select_dates_text_field(): void
    {
        $value = self::get('mhm_rentiva_text_select_dates', '');
        echo '<input type="text" name="mhm_rentiva_settings[mhm_rentiva_text_select_dates]" value="' . esc_attr($value) . '" class="regular-text" placeholder="Please select dates" />';
        echo '<p class="description">' . __('Default: "Please select dates"', 'mhm-rentiva') . '</p>';
    }

    public static function render_invalid_dates_text_field(): void
    {
        $value = self::get('mhm_rentiva_text_invalid_dates', '');
        echo '<input type="text" name="mhm_rentiva_settings[mhm_rentiva_text_invalid_dates]" value="' . esc_attr($value) . '" class="regular-text" placeholder="Invalid date range" />';
        echo '<p class="description">' . __('Default: "Invalid date range"', 'mhm-rentiva') . '</p>';
    }

    public static function render_select_payment_type_text_field(): void
    {
        $value = self::get('mhm_rentiva_text_select_payment_type', '');
        echo '<input type="text" name="mhm_rentiva_settings[mhm_rentiva_text_select_payment_type]" value="' . esc_attr($value) . '" class="regular-text" placeholder="Please select payment type" />';
        echo '<p class="description">' . __('Default: "Please select payment type"', 'mhm-rentiva') . '</p>';
    }

    public static function render_select_payment_method_text_field(): void
    {
        $value = self::get('mhm_rentiva_text_select_payment_method', '');
        echo '<input type="text" name="mhm_rentiva_settings[mhm_rentiva_text_select_payment_method]" value="' . esc_attr($value) . '" class="regular-text" placeholder="Please select payment method" />';
        echo '<p class="description">' . __('Default: "Please select payment method"', 'mhm-rentiva') . '</p>';
    }

    public static function render_calculating_text_field(): void
    {
        $value = self::get('mhm_rentiva_text_calculating', '');
        echo '<input type="text" name="mhm_rentiva_settings[mhm_rentiva_text_calculating]" value="' . esc_attr($value) . '" class="regular-text" placeholder="Calculating..." />';
        echo '<p class="description">' . __('Default: "Calculating..."', 'mhm-rentiva') . '</p>';
    }

    public static function render_payment_redirect_text_field(): void
    {
        $value = self::get('mhm_rentiva_text_payment_redirect', '');
        echo '<input type="text" name="mhm_rentiva_settings[mhm_rentiva_text_payment_redirect]" value="' . esc_attr($value) . '" class="regular-text" placeholder="Redirecting to payment page..." />';
        echo '<p class="description">' . __('Default: "Redirecting to payment page..."', 'mhm-rentiva') . '</p>';
    }

    public static function render_payment_success_text_field(): void
    {
        $value = self::get('mhm_rentiva_text_payment_success', '');
        echo '<input type="text" name="mhm_rentiva_settings[mhm_rentiva_text_payment_success]" value="' . esc_attr($value) . '" class="regular-text" placeholder="Payment completed successfully!" />';
        echo '<p class="description">' . __('Default: "Payment completed successfully!"', 'mhm-rentiva') . '</p>';
    }

    public static function render_payment_cancelled_text_field(): void
    {
        $value = self::get('mhm_rentiva_text_payment_cancelled', '');
        echo '<input type="text" name="mhm_rentiva_settings[mhm_rentiva_text_payment_cancelled]" value="' . esc_attr($value) . '" class="regular-text" placeholder="Payment cancelled." />';
        echo '<p class="description">' . __('Default: "Payment cancelled."', 'mhm-rentiva') . '</p>';
    }

    public static function render_popup_blocked_text_field(): void
    {
        $value = self::get('mhm_rentiva_text_popup_blocked', '');
        echo '<input type="text" name="mhm_rentiva_settings[mhm_rentiva_text_popup_blocked]" value="' . esc_attr($value) . '" class="regular-text" placeholder="Popup blocked. Redirecting to payment page..." />';
        echo '<p class="description">' . __('Default: "Popup blocked. Redirecting to payment page..."', 'mhm-rentiva') . '</p>';
    }

    /**
     * Dark Mode Settings Field
     */
    public static function render_dark_mode_field(): void
    {
        $value = get_option('mhm_rentiva_dark_mode', 'auto');
        $options = [
            'auto' => __('Automatic (System Preference)', 'mhm-rentiva'),
            'light' => __('Light Mode', 'mhm-rentiva'),
            'dark' => __('Dark Mode', 'mhm-rentiva'),
        ];
        
        echo '<select name="mhm_rentiva_settings[mhm_rentiva_dark_mode]" id="mhm_rentiva_dark_mode">';
        foreach ($options as $key => $label) {
            echo '<option value="' . esc_attr($key) . '"' . selected($value, $key, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . esc_html__('Choose your preferred theme mode. Automatic follows your system preference.', 'mhm-rentiva') . '</p>';
        
        echo '<div style="margin-top: 10px;">';
        echo '<button type="button" id="mhm-test-dark-mode" class="button button-secondary">' . esc_html__('Test Dark Mode', 'mhm-rentiva') . '</button>';
        echo '<span id="mhm-dark-mode-status" style="margin-left: 10px; font-weight: bold;"></span>';
        echo '</div>';
        
        // JavaScript for testing
        echo '<script>
        jQuery(document).ready(function($) {
            $("#mhm-test-dark-mode").on("click", function() {
                var currentMode = $("#mhm_rentiva_dark_mode").val();
                var status = $("#mhm-dark-mode-status");
                
                if (!document.body) {
                    status.text("❌ Body not ready").css("color", "#d63638");
                    return;
                }
                
                if (currentMode === "dark") {
                    document.body.classList.add("wp-theme-dark");
                    status.text("✅ Dark Mode Active").css("color", "#00a32a");
                } else if (currentMode === "light") {
                    document.body.classList.remove("wp-theme-dark");
                    status.text("✅ Light Mode Active").css("color", "#00a32a");
                } else {
                    if (window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)").matches) {
                        document.body.classList.add("wp-theme-dark");
                        status.text("✅ Auto Mode: Dark (System)").css("color", "#00a32a");
                    } else {
                        document.body.classList.remove("wp-theme-dark");
                        status.text("✅ Auto Mode: Light (System)").css("color", "#00a32a");
                    }
                }
                
                var testElement = $("<div>").css({
                    "position": "fixed",
                    "top": "50%",
                    "left": "50%",
                    "transform": "translate(-50%, -50%)",
                    "background": "var(--mhm-tooltip-bg)",
                    "color": "var(--mhm-tooltip-color)",
                    "padding": "20px",
                    "border-radius": "8px",
                    "z-index": "9999",
                    "box-shadow": "var(--mhm-tooltip-shadow)"
                }).text("Dark Mode Test - CSS Variables Working!");
                
                $("body").append(testElement);
                setTimeout(function() {
                    testElement.fadeOut(500, function() {
                        testElement.remove();
                    });
                }, 2000);
                
                $.post(ajaxurl, {
                    action: "mhm_save_dark_mode",
                    dark_mode: currentMode,
                    nonce: "' . wp_create_nonce('mhm_dark_mode_nonce') . '"
                }, function(response) {
                    if (response.success) {
                        console.log("Dark mode setting saved:", currentMode);
                    } else {
                        console.error("Failed to save dark mode:", response);
                    }
                }).fail(function(xhr, status, error) {
                    console.error("AJAX error:", error);
                });
            });
        });
        </script>';
    }

    /**
     * ⭐ Company & Support URL Fields
     */
    public static function render_company_website_field(): void
    {
        $value = get_option('mhm_rentiva_company_website', 'https://maxhandmade.com');
        echo '<input type="url" name="mhm_rentiva_company_website" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . esc_html__('Company website URL to be used in About and Support pages', 'mhm-rentiva') . '</p>';
    }

    public static function render_support_email_field(): void
    {
        $value = get_option('mhm_rentiva_support_email', 'destek@maxhandmade.com');
        echo '<input type="email" name="mhm_rentiva_support_email" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . esc_html__('Email address to be used for customer support', 'mhm-rentiva') . '</p>';
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
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'mhm_dark_mode_nonce')) {
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
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'mhm_settings_test_nonce')) {
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
    // SITE INFORMATION RENDER FUNCTIONS
    // ========================================
    
    public static function render_site_info_section_description(): void
    {
        echo '<p>' . esc_html__('Basic site information and configuration settings.', 'mhm-rentiva') . '</p>';
    }

    public static function render_site_url_field(): void
    {
        $value = self::get('mhm_rentiva_site_url', get_option('siteurl', ''));
        echo '<input type="url" name="mhm_rentiva_settings[mhm_rentiva_site_url]" value="' . esc_attr($value) . '" class="regular-text" readonly />';
        echo '<p class="description">' . esc_html__('WordPress site URL (read-only)', 'mhm-rentiva') . '</p>';
    }

    public static function render_home_url_field(): void
    {
        $value = self::get('mhm_rentiva_home_url', get_option('home', ''));
        echo '<input type="url" name="mhm_rentiva_settings[mhm_rentiva_home_url]" value="' . esc_attr($value) . '" class="regular-text" readonly />';
        echo '<p class="description">' . esc_html__('WordPress home URL (read-only)', 'mhm-rentiva') . '</p>';
    }

    public static function render_admin_email_field(): void
    {
        $value = self::get('mhm_rentiva_admin_email', get_option('admin_email', ''));
        echo '<input type="email" name="mhm_rentiva_settings[mhm_rentiva_admin_email]" value="' . esc_attr($value) . '" class="regular-text" readonly />';
        echo '<p class="description">' . esc_html__('WordPress admin email address (read-only)', 'mhm-rentiva') . '</p>';
    }

    public static function render_site_language_field(): void
    {
        $value = self::get('mhm_rentiva_site_language', get_locale());
        echo '<input type="text" name="mhm_rentiva_settings[mhm_rentiva_site_language]" value="' . esc_attr($value) . '" class="regular-text" readonly />';
        echo '<p class="description">' . esc_html__('WordPress site language (read-only)', 'mhm-rentiva') . '</p>';
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
}

