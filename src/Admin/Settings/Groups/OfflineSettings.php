<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Settings\Groups;

use MHMRentiva\Admin\Settings\Core\SettingsCore;
use MHMRentiva\Admin\Settings\Core\SettingsHelper;

if (!defined('ABSPATH')) {
    exit;
}

final class OfflineSettings
{
    public static function register(): void
    {
        $group = SettingsCore::PAGE;

        add_settings_section(
            'mhm_rentiva_offline_section',
            __('Offline Payments (Bank Transfer)', 'mhm-rentiva'),
            [self::class, 'render_section_description'],
            $group
        );

        // Offline Payment Fields
        SettingsHelper::checkbox_field($group, 'mhm_rentiva_offline_enabled', __('Enable Offline Payment', 'mhm-rentiva'), __('Accept bank transfer payments', 'mhm-rentiva'), 'mhm_rentiva_offline_section');
        
        SettingsHelper::textarea_field($group, 'mhm_rentiva_offline_instructions', __('Payment Instructions', 'mhm-rentiva'), 5, __('You can make payment by bank transfer. Please do not forget to write your reservation number in the description section.', 'mhm-rentiva'), 'mhm_rentiva_offline_section');
        
        SettingsHelper::textarea_field($group, 'mhm_rentiva_offline_accounts', __('Bank Account Information', 'mhm-rentiva'), 5, __('Bank Name\nIBAN: TR12 0006 4000 0011 2345 6789 01\nAccount Name: MHM RENTIVA A.Ş.', 'mhm-rentiva'), 'mhm_rentiva_offline_section');

        // Register offline settings with proper sanitization
        register_setting($group, 'mhm_rentiva_offline_enabled', ['sanitize_callback' => [SettingsHelper::class, 'sanitize_checkbox'], 'default' => '0']);
        register_setting($group, 'mhm_rentiva_offline_instructions', ['sanitize_callback' => [SettingsHelper::class, 'sanitize_textarea_field_safe'], 'default' => '']);
        register_setting($group, 'mhm_rentiva_offline_accounts', ['sanitize_callback' => [SettingsHelper::class, 'sanitize_textarea_field_safe'], 'default' => '']);
    }

    public static function render_section_description(): void
    {
        echo '<p class="description">' . esc_html__('Enable bank transfer (Wire/EFT) payments and allow receipt upload.', 'mhm-rentiva') . '</p>';
    }

    /**
     * Check if offline payment is enabled
     */
    public static function is_offline_enabled(): bool
    {
        return get_option('mhm_rentiva_offline_enabled', '0') === '1';
    }

    /**
     * Get payment instructions
     */
    public static function get_payment_instructions(): string
    {
        $instructions = get_option('mhm_rentiva_offline_instructions', '');
        return $instructions !== null ? sanitize_textarea_field((string) $instructions) : '';
    }

    /**
     * Get bank account information
     */
    public static function get_bank_accounts(): string
    {
        $accounts = get_option('mhm_rentiva_offline_accounts', '');
        return $accounts !== null ? sanitize_textarea_field((string) $accounts) : '';
    }

    /**
     * Get all offline payment settings as array
     */
    public static function get_all_settings(): array
    {
        return [
            'enabled' => self::is_offline_enabled(),
            'instructions' => self::get_payment_instructions(),
            'accounts' => self::get_bank_accounts()
        ];
    }

    /**
     * Validate bank account information format
     */
    public static function validate_bank_accounts(string $accounts): bool
    {
        if (empty($accounts)) {
            return false;
        }

        // Basic validation - check if it contains IBAN pattern
        return preg_match('/TR\d{2}\s?\d{4}\s?\d{4}\s?\d{4}\s?\d{4}\s?\d{4}\s?\d{2}/', $accounts) === 1;
    }

    /**
     * Get formatted bank account information for display
     */
    public static function get_formatted_accounts(): string
    {
        $accounts = self::get_bank_accounts();
        if (empty($accounts)) {
            return '';
        }

        // Convert line breaks to HTML and escape
        $formatted = nl2br(esc_html($accounts));
        return $formatted;
    }
}
