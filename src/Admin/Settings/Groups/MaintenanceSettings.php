<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Settings\Groups;

use MHMRentiva\Admin\Settings\Core\SettingsCore;
use MHMRentiva\Admin\Settings\Core\SettingsHelper;

if (!defined('ABSPATH')) {
    exit;
}

final class MaintenanceSettings
{
    /**
     * Get default settings for maintenance
     *
     * @return array
     */
    public static function get_default_settings(): array
    {
        return [
            'mhm_rentiva_auto_cancel_enabled'     => '1',
            'mhm_rentiva_auto_cancel_minutes'     => 30,
            'mhm_rentiva_clean_data_on_uninstall' => '0',
        ];
    }

    public static function register(): void
    {
        $group = SettingsCore::PAGE;

        add_settings_section(
            'mhm_rentiva_maintenance_section',
            __('Maintenance and Cleanup', 'mhm-rentiva'),
            [self::class, 'render_section_description'],
            $group
        );

        // Automatic Cancellation Settings
        SettingsHelper::checkbox_field($group, 'mhm_rentiva_auto_cancel_enabled', __('Automatic Cancellation', 'mhm-rentiva'), __('Automatically cancel unapproved reservations', 'mhm-rentiva'), 'mhm_rentiva_maintenance_section');
        SettingsHelper::number_field($group, 'mhm_rentiva_auto_cancel_minutes', __('Cancellation Time (minutes)', 'mhm-rentiva'), 5, 1440, __('Reservation will be cancelled after this time if not approved.', 'mhm-rentiva'), 'mhm_rentiva_maintenance_section');

        // Register all settings with proper sanitization
        $settings = [
            'mhm_rentiva_auto_cancel_enabled',
            'mhm_rentiva_auto_cancel_minutes'
        ];

        foreach ($settings as $setting) {
            $sanitize_callback = 'sanitize_text_field';
            if ($setting === 'mhm_rentiva_auto_cancel_minutes') {
                $sanitize_callback = 'absint';
            }
            register_setting($group, $setting, ['sanitize_callback' => $sanitize_callback]);
        }
    }

    public static function render_section_description(): void
    {
        echo '<p class="description">' . esc_html__('Settings for system maintenance and automatic tasks.', 'mhm-rentiva') . '</p>';
    }

    /**
     * Check if automatic cancellation is enabled
     */
    public static function is_auto_cancel_enabled(): bool
    {
        return get_option('mhm_rentiva_auto_cancel_enabled', '0') === '1';
    }

    /**
     * Get auto cancellation time in minutes
     */
    public static function get_auto_cancel_minutes(): int
    {
        $minutes = absint(get_option('mhm_rentiva_auto_cancel_minutes', 30));
        return max(5, min(1440, $minutes)); // Ensure between 5 and 1440 minutes
    }

    /**
     * Get all maintenance settings as array
     */
    public static function get_all_settings(): array
    {
        return [
            'auto_cancel_enabled' => self::is_auto_cancel_enabled(),
            'auto_cancel_minutes' => self::get_auto_cancel_minutes()
        ];
    }
}
