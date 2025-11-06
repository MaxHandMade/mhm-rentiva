<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Payment\Settings;

use MHMRentiva\Admin\Settings\Core\SettingsCore;
use MHMRentiva\Admin\Settings\Core\SettingsHelper;

if (!defined('ABSPATH')) {
    exit;
}

final class PayPalSettings
{
    public static function register(): void
    {
        $group = SettingsCore::PAGE;

        add_settings_section(
            'mhm_rentiva_paypal_section',
            __('PayPal Payments', 'mhm-rentiva'),
            [self::class, 'render_section_description'],
            $group
        );

        // ✅ CODE DUPLICATION RESOLVED - Using SettingsHelper
        // Enabled - Checkbox field
        SettingsHelper::checkbox_field($group, 'mhm_rentiva_paypal_enabled', __('Enable PayPal Payments', 'mhm-rentiva'), __('Activate PayPal payment method', 'mhm-rentiva'), 'mhm_rentiva_paypal_section');

        // Test Mode
        SettingsHelper::checkbox_field($group, 'mhm_rentiva_paypal_test_mode', __('Test Mode', 'mhm-rentiva'), __('Run in sandbox mode (for test payments)', 'mhm-rentiva'), 'mhm_rentiva_paypal_section');

        // Test Client ID
        SettingsHelper::text_field($group, 'mhm_rentiva_paypal_client_id_test', __('Client ID (Test)', 'mhm-rentiva'), 'mhm_rentiva_paypal_section');

        // Test Client Secret
        SettingsHelper::password_field($group, 'mhm_rentiva_paypal_client_secret_test', __('Client Secret (Test)', 'mhm-rentiva'), '', 'mhm_rentiva_paypal_section');

        // Live Client ID
        SettingsHelper::text_field($group, 'mhm_rentiva_paypal_client_id_live', __('Client ID (Live)', 'mhm-rentiva'), 'mhm_rentiva_paypal_section');

        // Live Client Secret
        SettingsHelper::password_field($group, 'mhm_rentiva_paypal_client_secret_live', __('Client Secret (Live)', 'mhm-rentiva'), '', 'mhm_rentiva_paypal_section');

        // Currency - Using select field
        // Note: PayPal supports limited currencies, but we filter from full list
        $all_currencies = \MHMRentiva\Admin\Core\CurrencyHelper::get_currency_list_for_dropdown();
        // PayPal supported currencies (from PayPal API documentation)
        $paypal_supported = ['USD', 'EUR', 'TRY', 'GBP', 'CAD', 'AUD'];
        $currencies = [];
        foreach ($paypal_supported as $code) {
            if (isset($all_currencies[$code])) {
                $currencies[$code] = $all_currencies[$code];
            }
        }
        SettingsHelper::select_field($group, 'mhm_rentiva_paypal_currency', __('Currency', 'mhm-rentiva'), $currencies, '', 'mhm_rentiva_paypal_section');

        // Webhook ID
        SettingsHelper::text_field($group, 'mhm_rentiva_paypal_webhook_id', __('Webhook ID', 'mhm-rentiva'), 'mhm_rentiva_paypal_section');

        // Webhook URL Info - Readonly field
        $webhook_url = get_rest_url(null, 'mhm-rentiva/v1/paypal/webhook');
        SettingsHelper::readonly_field($group, 'mhm_rentiva_paypal_webhook_url', __('Webhook URL', 'mhm-rentiva'), $webhook_url, __('Set this URL as the webhook URL in the PayPal Developer panel.', 'mhm-rentiva'), 'mhm_rentiva_paypal_section');

        // Debug Mode
        SettingsHelper::checkbox_field($group, 'mhm_rentiva_paypal_debug_mode', __('Debug Mode', 'mhm-rentiva'), __('Enable debug mode (for troubleshooting only)', 'mhm-rentiva'), 'mhm_rentiva_paypal_section');

        // Timeout - Allow 0 to disable timeout
        SettingsHelper::number_field($group, 'mhm_rentiva_paypal_timeout', __('Timeout (seconds)', 'mhm-rentiva'), 0, 120, __('Maximum wait time for PayPal API calls. Set to 0 to disable timeout.', 'mhm-rentiva'), 'mhm_rentiva_paypal_section');

        // Register settings
        self::register_paypal_settings();
    }

    /**
     * Register PayPal settings
     */
    private static function register_paypal_settings(): void
    {
        // All PayPal settings are handled by SettingsSanitizer::sanitize()
        // which processes mhm_rentiva_settings array, so no standalone register_setting needed
        // The form fields use name="mhm_rentiva_settings[field_name]" format
        
        // These settings are registered but not used for form submission
        // They are kept for backward compatibility and Settings API compatibility
        $paypal_settings = [
            'mhm_rentiva_paypal_enabled',
            'mhm_rentiva_paypal_test_mode',
            'mhm_rentiva_paypal_client_id_test',
            'mhm_rentiva_paypal_client_secret_test',
            'mhm_rentiva_paypal_client_id_live',
            'mhm_rentiva_paypal_client_secret_live',
            'mhm_rentiva_paypal_currency',
            'mhm_rentiva_paypal_webhook_id',
            'mhm_rentiva_paypal_timeout',
            'mhm_rentiva_paypal_debug_mode'
        ];

        foreach ($paypal_settings as $setting) {
            register_setting(SettingsCore::PAGE, $setting, [
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => ''
            ]);
        }
    }

    public static function render_section_description(): void
    {
        echo '<p class="description">' . esc_html__('Secure online payments with PayPal Express Checkout. This feature, active in the Lite version, supports USD, EUR, and TRY currencies.', 'mhm-rentiva') . '</p>';
        echo '<p class="description">' . esc_html__('Obtain Client ID and Client Secret information from your PayPal Developer account.', 'mhm-rentiva') . '</p>';
    }
}
