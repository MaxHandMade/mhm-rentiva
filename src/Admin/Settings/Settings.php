<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Settings;

use MHMRentiva\Admin\Settings\Core\SettingsCore;
use MHMRentiva\Admin\Settings\Core\SettingsSanitizer;

if (!defined('ABSPATH')) {
    exit;
}

final class Settings
{
    public const GROUP = 'mhm_rentiva_settings';
    public const PAGE  = 'mhm_rentiva_settings';

    public static function register(): void
    {
        // Use new Core class
        SettingsCore::register();
        
        // Register AJAX handlers for reset functionality
        add_action('wp_ajax_mhm_reset_settings_tab', [self::class, 'ajax_reset_settings_tab']);
    }

    public static function init(): void
    {
        // Use new Core class
        SettingsCore::init();
    }

    public static function defaults(): array
    {
        // Use new Core class
        return SettingsCore::defaults();
    }

    public static function get_all(): array
    {
        // Use new Core class
        return SettingsCore::get_all();
    }

    public static function get(string $key, $default = null)
    {
        // Use new Core class
        return SettingsCore::get($key, $default);
    }

    public static function sanitize($input): array
    {
        // Use new Sanitizer class
        return SettingsSanitizer::sanitize($input);
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

    public static function render_settings_page(): void
    {
        SettingsView::render_settings_page();
    }

    /**
     * AJAX handler for resetting settings tab to defaults
     */
    public static function ajax_reset_settings_tab(): void
    {
        check_ajax_referer('mhm_rentiva_settings', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'mhm-rentiva')]);
            return;
        }
        
        $tab = sanitize_key($_POST['tab'] ?? '');
        
        if (empty($tab)) {
            wp_send_json_error(['message' => __('Tab name is required.', 'mhm-rentiva')]);
            return;
        }
        
        $valid_tabs = ['general', 'vehicle', 'booking', 'customer', 'email', 'payment', 'system', 'frontend'];
        
        if (!in_array($tab, $valid_tabs, true)) {
            wp_send_json_error(['message' => __('Invalid tab name.', 'mhm-rentiva')]);
            return;
        }
        
        $result = SettingsCore::reset_tab_to_defaults($tab);
        
        if (!$result) {
            wp_send_json_error(['message' => __('Failed to reset settings to defaults.', 'mhm-rentiva')]);
            return;
        }
        
        wp_send_json_success([
            'message' => __('Settings reset to defaults successfully.', 'mhm-rentiva'),
            'redirect' => admin_url('admin.php?page=mhm-rentiva-settings&tab=' . $tab)
        ]);
    }
}
