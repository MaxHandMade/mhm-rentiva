<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Payment\Settings;

use MHMRentiva\Admin\Settings\Core\SettingsCore;
use MHMRentiva\Admin\Settings\Core\SettingsHelper;
use MHMRentiva\Admin\Licensing\Mode;
use MHMRentiva\Admin\Licensing\Restrictions;

if (!defined('ABSPATH')) {
    exit;
}

final class StripeSettings
{
    public static function register(): void
    {
        $group = SettingsCore::PAGE;

        add_settings_section(
            'mhm_rentiva_stripe_section',
            __('Stripe Payments', 'mhm-rentiva'),
            [self::class, 'render_section_description'],
            $group
        );

        // ✅ CODE DUPLICATION RESOLVED - Using SettingsHelper
        // Enabled - Checkbox field
        SettingsHelper::checkbox_field($group, 'mhm_rentiva_stripe_enabled', __('Enable Stripe Payments', 'mhm-rentiva'), __('Activate Stripe payment method', 'mhm-rentiva'), 'mhm_rentiva_stripe_section');

        // Test Mode - Checkbox field
        SettingsHelper::checkbox_field($group, 'mhm_rentiva_stripe_test_mode', __('Test Mode', 'mhm-rentiva'), __('Run in test mode (for sandbox payments)', 'mhm-rentiva'), 'mhm_rentiva_stripe_section');

        // Mode - Using select field
        $mode_options = [
            'test' => __('Test', 'mhm-rentiva'),
            'live' => __('Live', 'mhm-rentiva')
        ];
        SettingsHelper::select_field($group, 'mhm_rentiva_stripe_mode', __('Mode', 'mhm-rentiva'), $mode_options, '', 'mhm_rentiva_stripe_section');

        // Test keys
        SettingsHelper::text_field($group, 'mhm_rentiva_stripe_pk_test', __('Publishable Key (Test)', 'mhm-rentiva'), 'mhm_rentiva_stripe_section');
        SettingsHelper::password_field($group, 'mhm_rentiva_stripe_sk_test', __('Secret Key (Test)', 'mhm-rentiva'), '', 'mhm_rentiva_stripe_section');
        SettingsHelper::password_field($group, 'mhm_rentiva_stripe_webhook_secret_test', __('Webhook Secret (Test)', 'mhm-rentiva'), '', 'mhm_rentiva_stripe_section');

        // Live keys
        SettingsHelper::text_field($group, 'mhm_rentiva_stripe_pk_live', __('Publishable Key (Live)', 'mhm-rentiva'), 'mhm_rentiva_stripe_section');
        SettingsHelper::password_field($group, 'mhm_rentiva_stripe_sk_live', __('Secret Key (Live)', 'mhm-rentiva'), '', 'mhm_rentiva_stripe_section');
        SettingsHelper::password_field($group, 'mhm_rentiva_stripe_webhook_secret_live', __('Webhook Secret (Live)', 'mhm-rentiva'), '', 'mhm_rentiva_stripe_section');

        // Register settings
        self::register_stripe_settings();
    }

    /**
     * Register General Payment Settings section
     */
    public static function register_general_payment_settings(): void
    {
        $group = SettingsCore::PAGE;

        add_settings_section(
            'mhm_rentiva_general_payment_section',
            __('General Payment Settings', 'mhm-rentiva'),
            [self::class, 'render_general_payment_description'],
            $group
        );

        // Default Payment Method
        SettingsHelper::select_field(
            $group, 
            'mhm_rentiva_booking_default_payment_method', 
            __('Default Payment Method', 'mhm-rentiva'), 
            [
                'offline' => __('Offline Payment', 'mhm-rentiva'),
                'stripe' => __('Stripe', 'mhm-rentiva'),
                'paypal' => __('PayPal', 'mhm-rentiva'),
                'paytr' => __('PayTR', 'mhm-rentiva'),
            ],
            __('Default payment method for new bookings.', 'mhm-rentiva'),
            'mhm_rentiva_general_payment_section'
        );

        // Payment Gateway Timeout - Allow 0 to disable timeout
        SettingsHelper::number_field(
            $group, 
            'mhm_rentiva_booking_payment_gateway_timeout_minutes', 
            __('Payment Gateway Timeout (Minutes)', 'mhm-rentiva'), 
            0, 60, 
            __('Timeout for payment gateway transactions (minutes). Set to 0 to disable timeout.', 'mhm-rentiva'), 
            'mhm_rentiva_general_payment_section'
        );

        // Payment Deadline - Allow 0 to disable deadline
        SettingsHelper::number_field(
            $group, 
            'mhm_rentiva_booking_payment_deadline_minutes', 
            __('Payment Deadline (Minutes)', 'mhm-rentiva'), 
            0, 120, 
            __('Time limit for customers to complete payment. Set to 0 to disable deadline.', 'mhm-rentiva'), 
            'mhm_rentiva_general_payment_section'
        );

        // Deposit Required
        SettingsHelper::checkbox_field(
            $group, 
            'mhm_rentiva_booking_deposit_required', 
            __('Deposit Required', 'mhm-rentiva'), 
            __('Require customers to pay a deposit for bookings.', 'mhm-rentiva'), 
            'mhm_rentiva_general_payment_section'
        );

        // Register general payment settings
        self::register_general_payment_settings_registration();
    }

    /**
     * Register Payment Gateway Status section
     */
    public static function register_payment_gateway_status(): void
    {
        $group = SettingsCore::PAGE;

        add_settings_section(
            'mhm_rentiva_payment_gateway_status_section',
            __('Payment Gateway Status', 'mhm-rentiva'),
            [self::class, 'render_gateway_status_description'],
            $group
        );

        // Gateway Status Display
        add_settings_field(
            'mhm_rentiva_payment_gateway_status_display',
            __('Gateway Status Display', 'mhm-rentiva'),
            [self::class, 'render_gateway_status_display'],
            $group,
            'mhm_rentiva_payment_gateway_status_section'
        );
    }

    /**
     * Render Gateway Status Display
     */
    public static function render_gateway_status_display(): void
    {
        echo '<div class="mhm-gateway-status-container">';
        
        // ✅ Use SettingsCore::get() instead of get_option() to read from mhm_rentiva_settings array
        
        // Stripe Status
        $stripe_enabled = SettingsCore::get('mhm_rentiva_stripe_enabled', '0');
        $stripe_test_mode = SettingsCore::get('mhm_rentiva_stripe_test_mode', '1');
        $stripe_status = ($stripe_enabled === '1' || $stripe_enabled === '') ? ($stripe_test_mode === '1' || $stripe_test_mode === '' ? 'test' : 'live') : 'disabled';
        
        echo '<div class="mhm-gateway-status-item">';
        echo '<strong>Stripe:</strong> ';
        echo '<span class="mhm-status-' . esc_attr($stripe_status) . '">';
        echo esc_html(ucfirst($stripe_status));
        echo '</span>';
        echo '</div>';
        
        // PayPal Status
        $paypal_enabled = SettingsCore::get('mhm_rentiva_paypal_enabled', '0');
        $paypal_test_mode = SettingsCore::get('mhm_rentiva_paypal_test_mode', '1');
        $paypal_status = ($paypal_enabled === '1' || $paypal_enabled === '') ? ($paypal_test_mode === '1' || $paypal_test_mode === '' ? 'test' : 'live') : 'disabled';
        
        echo '<div class="mhm-gateway-status-item">';
        echo '<strong>PayPal:</strong> ';
        echo '<span class="mhm-status-' . esc_attr($paypal_status) . '">';
        echo esc_html(ucfirst($paypal_status));
        echo '</span>';
        echo '</div>';
        
        // PayTR Status
        $paytr_enabled = SettingsCore::get('mhm_rentiva_paytr_enabled', '0');
        $paytr_test_mode = SettingsCore::get('mhm_rentiva_paytr_test_mode', '1');
        $paytr_status = ($paytr_enabled === '1' || $paytr_enabled === '') ? ($paytr_test_mode === '1' || $paytr_test_mode === '' ? 'test' : 'live') : 'disabled';
        
        echo '<div class="mhm-gateway-status-item">';
        echo '<strong>PayTR:</strong> ';
        echo '<span class="mhm-status-' . esc_attr($paytr_status) . '">';
        echo esc_html(ucfirst($paytr_status));
        echo '</span>';
        echo '</div>';
        
        // Offline Status
        $offline_enabled = SettingsCore::get('mhm_rentiva_offline_enabled', '0');
        $offline_status = ($offline_enabled === '1') ? 'enabled' : 'disabled';
        
        echo '<div class="mhm-gateway-status-item">';
        echo '<strong>Offline Payment:</strong> ';
        echo '<span class="mhm-status-' . esc_attr($offline_status) . '">';
        echo esc_html(ucfirst($offline_status));
        echo '</span>';
        echo '</div>';
        
        echo '</div>';
        
        echo '<style>
        .mhm-gateway-status-container { margin: 10px 0; }
        .mhm-gateway-status-item { margin: 5px 0; padding: 5px; background: #f9f9f9; border-radius: 3px; }
        .mhm-status-enabled { color: #46b450; font-weight: bold; }
        .mhm-status-test { color: #ffb900; font-weight: bold; }
        .mhm-status-live { color: #00a32a; font-weight: bold; }
        .mhm-status-disabled { color: #dc3232; font-weight: bold; }
        </style>';
        
        echo '<p class="description">' . esc_html__('Current status of all payment gateways.', 'mhm-rentiva') . '</p>';
    }

    /**
     * Gateway Status Description
     */
    public static function render_gateway_status_description(): void
    {
        echo '<p class="description">' . esc_html__('Overview of all payment gateway statuses.', 'mhm-rentiva') . '</p>';
    }

    /**
     * Register General Payment Settings
     * Note: These settings are handled by SettingsSanitizer::sanitize() 
     * which processes mhm_rentiva_settings array, so no standalone register_setting needed
     */
    private static function register_general_payment_settings_registration(): void
    {
        // Removed standalone register_setting calls for these fields
        // They are handled by SettingsSanitizer::sanitize() which processes mhm_rentiva_settings array
        // The form fields use name="mhm_rentiva_settings[field_name]" format
    }

    /**
     * General Payment Settings Description
     */
    public static function render_general_payment_description(): void
    {
        echo '<p class="description">' . esc_html__('Configure general payment settings that apply to all payment methods.', 'mhm-rentiva') . '</p>';
    }

    /**
     * Register Stripe settings
     */
    private static function register_stripe_settings(): void
    {
        $stripe_settings = [
            'mhm_rentiva_stripe_enabled',
            'mhm_rentiva_stripe_test_mode',
            'mhm_rentiva_stripe_mode',
            'mhm_rentiva_stripe_pk_test',
            'mhm_rentiva_stripe_sk_test',
            'mhm_rentiva_stripe_webhook_secret_test',
            'mhm_rentiva_stripe_pk_live',
            'mhm_rentiva_stripe_sk_live',
            'mhm_rentiva_stripe_webhook_secret_live'
        ];

        foreach ($stripe_settings as $setting) {
            register_setting(SettingsCore::PAGE, $setting, [
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field'
            ]);
        }
    }

    public static function render_section_description(): void
    {
        if (Mode::isLite()) {
            Restrictions::beginProLocked();
        }
        
        echo '<p class="description">' . esc_html__('Configure Stripe API keys for test and live modes. Webhook secret is required for payment confirmation.', 'mhm-rentiva') . '</p>';
        
        if (Mode::isLite()) {
            Restrictions::endProLocked(__('Stripe is a Pro feature. Enter your license key to enable.', 'mhm-rentiva'));
        }
    }
}
