<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Payment\Settings;

use MHMRentiva\Admin\Settings\Core\SettingsCore;
use MHMRentiva\Admin\Settings\Core\SettingsHelper;

if (!defined('ABSPATH')) {
    exit;
}

final class OfflinePaymentSettings
{
    public static function register(): void
    {
        $group = SettingsCore::PAGE;

        \add_settings_section(
            'mhm_rentiva_offline_section',
            __('Offline Payment', 'mhm-rentiva'),
            [self::class, 'render_section_description'],
            $group
        );

        // ✅ CODE DUPLICATION RESOLVED - Using SettingsHelper
        // Enabled - Checkbox field
        SettingsHelper::checkbox_field($group, 'mhm_rentiva_offline_enabled', __('Enable Offline Payment', 'mhm-rentiva'), __('Activate offline payment method', 'mhm-rentiva'), 'mhm_rentiva_offline_section');

        // Payment Instructions - Custom render to handle translation
        add_settings_field(
            'mhm_rentiva_offline_instructions',
            __('Payment Instructions', 'mhm-rentiva'),
            [self::class, 'render_instructions_field'],
            $group,
            'mhm_rentiva_offline_section'
        );

        // Bank Accounts
        SettingsHelper::textarea_field($group, 'mhm_rentiva_offline_accounts', __('Bank Accounts', 'mhm-rentiva'), 5, __('Bank account information (one account per line)', 'mhm-rentiva'), 'mhm_rentiva_offline_section');

        // Register settings
        self::register_offline_settings();
    }

    /**
     * Register Offline Payment settings
     * 
     * ✅ REMOVED: Standalone registration - all settings now use mhm_rentiva_settings array
     * This prevents conflicts between standalone and array storage
     */
    private static function register_offline_settings(): void
    {
        // ✅ No registration needed - settings are handled by SettingsSanitizer::sanitize()
        // which processes mhm_rentiva_settings array
        // The form fields use name="mhm_rentiva_settings[field_name]" format
    }

    public static function render_section_description(): void
    {
        echo '<p class="description">' . esc_html__('Configure offline payment methods such as bank transfer, cash on delivery.', 'mhm-rentiva') . '</p>';
    }

    /**
     * Render payment instructions field with translation support
     */
    public static function render_instructions_field(): void
    {
        $value = SettingsCore::get('mhm_rentiva_offline_instructions');
        
        // If value is empty or equals the English default, use translated default
        $default_text = __('You can make payments via bank transfer. Please don\'t forget to write your reservation number in the description.', 'mhm-rentiva');
        if (empty($value) || $value === 'You can make payments via bank transfer. Please don\'t forget to write your reservation number in the description.') {
            $value = $default_text;
        }
        
        echo '<textarea name="mhm_rentiva_settings[mhm_rentiva_offline_instructions]" class="large-text code" rows="5">' . esc_textarea($value) . '</textarea>';
        echo '<p class="description">' . esc_html__('Payment instructions to display to customers', 'mhm-rentiva') . '</p>';
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
