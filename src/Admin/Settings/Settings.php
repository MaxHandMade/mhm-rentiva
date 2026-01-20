<?php

declare(strict_types=1);

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
        // 1. Security Check
        check_ajax_referer('mhm_rentiva_settings', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'mhm-rentiva')]);
            return;
        }

        try {
            $tab = sanitize_key($_POST['tab'] ?? '');

            if (empty($tab)) {
                throw new \Exception(__('Tab name is required.', 'mhm-rentiva'));
            }

            // 2. Get defaults based on tab (Dynamic Approach)
            $defaults = [];

            switch ($tab) {
                case 'general':
                    if (class_exists('\MHMRentiva\Admin\Settings\Groups\GeneralSettings')) {
                        $defaults = \MHMRentiva\Admin\Settings\Groups\GeneralSettings::get_default_settings();
                    }
                    break;
                case 'booking':
                    if (class_exists('\MHMRentiva\Admin\Settings\Groups\BookingSettings')) {
                        $defaults = \MHMRentiva\Admin\Settings\Groups\BookingSettings::get_default_settings();
                    }
                    break;
                case 'system': // maintenance settings are under system section in reset logic usually, but let's be explicit if tab is sent as 'maintenance' or part of system
                    if ($tab === 'system') {
                        // System tab often includes maintenance, logs etc.
                        // For now fallback to SettingsCore for complex tabs, or add MaintenanceSettings if needed.
                    }
                    // Fallthrough to default for system tab for now as it's complex
                default:
                    // Fallback to SettingsCore logic for other tabs
                    if (!SettingsCore::reset_tab_to_defaults($tab)) {
                        // It might return false if no changes needed, which is fine
                    }
                    wp_send_json_success([
                        'message' => __('Settings reset to defaults successfully.', 'mhm-rentiva'),
                        'redirect' => admin_url('admin.php?page=mhm-rentiva-settings&tab=' . $tab)
                    ]);
                    return;
            }

            // 3. Update Main Settings Array (Dynamic Loop)
            if (!empty($defaults)) {
                $main_settings = get_option('mhm_rentiva_settings', []);

                foreach ($defaults as $key => $default_value) {
                    $main_settings[$key] = $default_value;
                }

                update_option('mhm_rentiva_settings', $main_settings);
            }

            wp_send_json_success([
                'message' => __('Settings reset to defaults successfully.', 'mhm-rentiva'),
                'redirect' => admin_url('admin.php?page=mhm-rentiva-settings&tab=' . $tab)
            ]);
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
}
