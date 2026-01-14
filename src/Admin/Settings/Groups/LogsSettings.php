<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Settings\Groups;

use MHMRentiva\Admin\Settings\Core\SettingsCore;
use MHMRentiva\Admin\Settings\Core\SettingsHelper;

if (!defined('ABSPATH')) {
    exit;
}

final class LogsSettings
{
    /**
     * Get default settings for logs
     *
     * @return array
     */
    public static function get_default_settings(): array
    {
        return [
            'mhm_rentiva_log_level'            => 'error',
            'mhm_rentiva_log_cleanup_enabled'  => '1',
            'mhm_rentiva_log_retention_days'   => 30,
            'mhm_rentiva_debug_mode'           => '0',
            'mhm_rentiva_log_max_size'         => 10,
        ];
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

    public static function register(): void
    {
        $group = SettingsCore::PAGE;

        add_settings_section(
            'mhm_rentiva_logs_section',
            __('Log Settings', 'mhm-rentiva'),
            [self::class, 'render_section_description'],
            $group
        );

        // Log Level
        $log_levels = [
            'error' => __('Error', 'mhm-rentiva'),
            'warning' => __('Warning', 'mhm-rentiva'),
            'info' => __('Info', 'mhm-rentiva'),
            'debug' => __('Debug', 'mhm-rentiva')
        ];
        SettingsHelper::select_field($group, 'mhm_rentiva_log_level', __('Log Level', 'mhm-rentiva'), $log_levels, __('Which level of logs will be recorded.', 'mhm-rentiva'), 'mhm_rentiva_logs_section');

        // Automatic Log Cleanup
        SettingsHelper::checkbox_field($group, 'mhm_rentiva_log_cleanup_enabled', __('Automatic Log Cleanup', 'mhm-rentiva'), __('Automatically clean old log records', 'mhm-rentiva'), 'mhm_rentiva_logs_section');

        // Log Retention Period
        SettingsHelper::number_field($group, 'mhm_rentiva_log_retention_days', __('Log Retention Period (days)', 'mhm-rentiva'), 1, 365, __('How many days log records will be kept.', 'mhm-rentiva'), 'mhm_rentiva_logs_section');

        // Debug Mode
        SettingsHelper::checkbox_field($group, 'mhm_rentiva_debug_mode', __('Debug Mode', 'mhm-rentiva'), __('Enable debug mode (for development only)', 'mhm-rentiva'), 'mhm_rentiva_logs_section');

        // Maximum Log File Size
        SettingsHelper::number_field($group, 'mhm_rentiva_log_max_size', __('Maximum Log File Size (MB)', 'mhm-rentiva'), 1, 100, __('Maximum size of log files.', 'mhm-rentiva'), 'mhm_rentiva_logs_section');

        // Register all settings with proper sanitization
        $settings = [
            'mhm_rentiva_log_level',
            'mhm_rentiva_log_cleanup_enabled',
            'mhm_rentiva_log_retention_days',
            'mhm_rentiva_debug_mode',
            'mhm_rentiva_log_max_size'
        ];

        foreach ($settings as $setting) {
            $sanitize_callback = 'sanitize_text_field';
            if (in_array($setting, ['mhm_rentiva_log_retention_days', 'mhm_rentiva_log_max_size'])) {
                $sanitize_callback = 'absint';
            }
            register_setting($group, $setting, ['sanitize_callback' => $sanitize_callback]);
        }
    }

    public static function render_section_description(): void
    {
        echo '<p class="description">' . esc_html__('Configure system log settings.', 'mhm-rentiva') . '</p>';
    }

    /**
     * Get log level setting
     */
    public static function get_log_level(): string
    {
        $level = get_option('mhm_rentiva_log_level', 'error');
        $allowed_levels = ['error', 'warning', 'info', 'debug'];

        if (!in_array($level, $allowed_levels, true)) {
            return 'error';
        }

        return self::sanitize_text_field_safe($level);
    }

    /**
     * Check if log cleanup is enabled
     */
    public static function is_log_cleanup_enabled(): bool
    {
        return get_option('mhm_rentiva_log_cleanup_enabled', '0') === '1';
    }

    /**
     * Get log retention days
     */
    public static function get_log_retention_days(): int
    {
        $days = absint(get_option('mhm_rentiva_log_retention_days', 30));
        return max(1, min(365, $days)); // Ensure between 1 and 365 days
    }

    /**
     * Check if debug mode is enabled
     */
    public static function is_debug_mode_enabled(): bool
    {
        return get_option('mhm_rentiva_debug_mode', '0') === '1';
    }

    /**
     * Get maximum log file size in MB
     */
    public static function get_log_max_size(): int
    {
        $size = absint(get_option('mhm_rentiva_log_max_size', 10));
        return max(1, min(100, $size)); // Ensure between 1 and 100 MB
    }

    /**
     * Get all log settings as array
     */
    public static function get_all_settings(): array
    {
        return [
            'log_level' => self::get_log_level(),
            'cleanup_enabled' => self::is_log_cleanup_enabled(),
            'retention_days' => self::get_log_retention_days(),
            'debug_mode' => self::is_debug_mode_enabled(),
            'max_size' => self::get_log_max_size()
        ];
    }
}
