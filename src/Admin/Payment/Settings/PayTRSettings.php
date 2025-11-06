<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Payment\Settings;

use MHMRentiva\Admin\Settings\Core\SettingsCore;
use MHMRentiva\Admin\Settings\Core\SettingsHelper;
use MHMRentiva\Admin\Licensing\Mode;
use MHMRentiva\Admin\Licensing\Restrictions;

if (!defined('ABSPATH')) {
    exit;
}

final class PayTRSettings
{
    public static function register(): void
    {
        $group = SettingsCore::PAGE;

        add_settings_section(
            'mhm_rentiva_paytr_section',
            __('PayTR', 'mhm-rentiva'),
            [self::class, 'render_section_description'],
            $group
        );

        // ✅ CODE DUPLICATION RESOLVED - Using SettingsHelper
        // Enabled - Checkbox field
        SettingsHelper::checkbox_field($group, 'mhm_rentiva_paytr_enabled', __('Enable PayTR Payments', 'mhm-rentiva'), __('Activate PayTR payment method', 'mhm-rentiva'), 'mhm_rentiva_paytr_section');

        // PayTR credentials
        foreach ([
            'mhm_rentiva_paytr_merchant_id'   => __('Merchant ID', 'mhm-rentiva'),
            'mhm_rentiva_paytr_merchant_key'  => __('Merchant Key', 'mhm-rentiva'),
            'mhm_rentiva_paytr_merchant_salt' => __('Merchant Salt', 'mhm-rentiva'),
        ] as $key => $label) {
            SettingsHelper::text_field($group, $key, $label, 'mhm_rentiva_paytr_section');
        }

        // Test Mode
        SettingsHelper::checkbox_field($group, 'mhm_rentiva_paytr_test_mode', __('Test Mode', 'mhm-rentiva'), __('Enabled', 'mhm-rentiva'), 'mhm_rentiva_paytr_section');

        // Installment settings
        SettingsHelper::checkbox_field($group, 'mhm_rentiva_paytr_no_installment', __('Disable Installments', 'mhm-rentiva'), __('No installments (single payment only)', 'mhm-rentiva'), 'mhm_rentiva_paytr_section');

        // Max Installment - Using select field
        $installment_options = [
            '1' => '1',
            '2' => '2', 
            '3' => '3',
            '4' => '4',
            '5' => '5',
            '6' => '6',
            '7' => '7',
            '8' => '8',
            '9' => '9',
            '10' => '10',
            '11' => '11',
            '12' => '12'
        ];
        SettingsHelper::select_field($group, 'mhm_rentiva_paytr_max_installment', __('Maximum Installments', 'mhm-rentiva'), $installment_options, __('Installments are valid when allowed by bank/card and contract.', 'mhm-rentiva'), 'mhm_rentiva_paytr_section');

        // 3D Secure mode
        SettingsHelper::checkbox_field($group, 'mhm_rentiva_paytr_non_3d', __('Non-3D Mode', 'mhm-rentiva'), __('Try non-3D payment if available (may require PayTR permission)', 'mhm-rentiva'), 'mhm_rentiva_paytr_section');

        // Timeout & Debug - Allow 0 to disable timeout
        SettingsHelper::number_field($group, 'mhm_rentiva_paytr_timeout_limit', __('Timeout (seconds)', 'mhm-rentiva'), 0, 120, __('Customer has this much time to complete payment. Set to 0 to disable timeout.', 'mhm-rentiva'), 'mhm_rentiva_paytr_section');

        SettingsHelper::checkbox_field($group, 'mhm_rentiva_paytr_debug_on', __('Debug Mode', 'mhm-rentiva'), __('Enable PayTR debug output (for troubleshooting only)', 'mhm-rentiva'), 'mhm_rentiva_paytr_section');

        // Callback URL Info - Readonly field
        $callback_url = get_rest_url(null, 'mhm-rentiva/v1/paytr/callback');
        SettingsHelper::readonly_field($group, 'mhm_rentiva_paytr_callback_info', __('Callback URL', 'mhm-rentiva'), $callback_url, __('Set this as the "Notification URL" (callback) in your PayTR merchant panel.', 'mhm-rentiva'), 'mhm_rentiva_paytr_section');

        // Register settings
        self::register_paytr_settings();
    }

    /**
     * Register PayTR settings
     */
    private static function register_paytr_settings(): void
    {
        // All PayTR settings are handled by SettingsSanitizer::sanitize()
        // which processes mhm_rentiva_settings array, so no standalone register_setting needed
        // The form fields use name="mhm_rentiva_settings[field_name]" format
        
        // These settings are registered but not used for form submission
        // They are kept for backward compatibility and Settings API compatibility
        $paytr_settings = [
            'mhm_rentiva_paytr_enabled',
            'mhm_rentiva_paytr_merchant_id',
            'mhm_rentiva_paytr_merchant_key',
            'mhm_rentiva_paytr_merchant_salt',
            'mhm_rentiva_paytr_test_mode',
            'mhm_rentiva_paytr_no_installment',
            'mhm_rentiva_paytr_max_installment',
            'mhm_rentiva_paytr_non_3d',
            'mhm_rentiva_paytr_timeout_limit',
            'mhm_rentiva_paytr_debug_on'
        ];

        foreach ($paytr_settings as $setting) {
            register_setting(SettingsCore::PAGE, $setting, [
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => ''
            ]);
        }
    }

    public static function render_section_description(): void
    {
        if (Mode::isLite()) {
            Restrictions::beginProLocked();
        }
        
        echo '<p class="description">' . esc_html__('Configure PayTR credentials. Token creation and callback validation use these values.', 'mhm-rentiva') . '</p>';
        
        if (Mode::isLite()) {
            Restrictions::endProLocked(__('PayTR is a Pro feature. Enter your license key to enable.', 'mhm-rentiva'));
        }
    }
}
