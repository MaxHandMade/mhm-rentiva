<?php

namespace MHMRentiva\Admin\Settings\Groups;

use MHMRentiva\Admin\Settings\Core\SettingsCore;

/**
 * Core System Settings
 */
class CoreSettings
{
    public const SECTION_ID = 'mhm_rentiva_core_section';

    /**
     * Get default settings for core system
     *
     * @return array
     */
    public static function get_default_settings(): array
    {
        return [


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
        ];
    }

    /**
     * Register settings
     */
    public static function register(): void
    {
        // Create section
        add_settings_section(
            self::SECTION_ID,
            __('Core System Settings', 'mhm-rentiva'),
            [self::class, 'render_section_description'],
            'mhm_rentiva_settings'
        );



        // Cache settings
        add_settings_field(
            'mhm_rentiva_cache_enabled',
            __('Object Cache Enabled', 'mhm-rentiva'),
            [self::class, 'render_cache_enabled_field'],
            'mhm_rentiva_settings',
            self::SECTION_ID
        );

        add_settings_field(
            'mhm_rentiva_cache_default_ttl',
            __('Cache Default TTL (Hours)', 'mhm-rentiva'),
            [self::class, 'render_cache_default_ttl_field'],
            'mhm_rentiva_settings',
            self::SECTION_ID
        );

        add_settings_field(
            'mhm_rentiva_cache_lists_ttl',
            __('Lists Cache TTL (Minutes)', 'mhm-rentiva'),
            [self::class, 'render_cache_lists_ttl_field'],
            'mhm_rentiva_settings',
            self::SECTION_ID
        );

        add_settings_field(
            'mhm_rentiva_cache_reports_ttl',
            __('Reports Cache TTL (Minutes)', 'mhm-rentiva'),
            [self::class, 'render_cache_reports_ttl_field'],
            'mhm_rentiva_settings',
            self::SECTION_ID
        );

        add_settings_field(
            'mhm_rentiva_cache_charts_ttl',
            __('Charts Cache TTL (Minutes)', 'mhm-rentiva'),
            [self::class, 'render_cache_charts_ttl_field'],
            'mhm_rentiva_settings',
            self::SECTION_ID
        );

        // Database settings
        add_settings_field(
            'mhm_rentiva_db_auto_optimize',
            __('Automatic Database Optimization', 'mhm-rentiva'),
            [self::class, 'render_db_auto_optimize_field'],
            'mhm_rentiva_settings',
            self::SECTION_ID
        );

        add_settings_field(
            'mhm_rentiva_db_performance_threshold',
            __('Performance Threshold (ms)', 'mhm-rentiva'),
            [self::class, 'render_db_performance_threshold_field'],
            'mhm_rentiva_settings',
            self::SECTION_ID
        );

        // WordPress Optimization settings
        add_settings_field(
            'mhm_rentiva_wp_optimization_enabled',
            __('WordPress Optimization Enabled', 'mhm-rentiva'),
            [self::class, 'render_wp_optimization_enabled_field'],
            'mhm_rentiva_settings',
            self::SECTION_ID
        );

        add_settings_field(
            'mhm_rentiva_wp_memory_limit',
            __('WordPress Memory Limit (MB)', 'mhm-rentiva'),
            [self::class, 'render_wp_memory_limit_field'],
            'mhm_rentiva_settings',
            self::SECTION_ID
        );

        add_settings_field(
            'mhm_rentiva_wp_meta_query_limit',
            __('Meta Query Limit', 'mhm-rentiva'),
            [self::class, 'render_wp_meta_query_limit_field'],
            'mhm_rentiva_settings',
            self::SECTION_ID
        );

        // Register all settings to mhm_rentiva_settings group
        $settings = [
            'mhm_rentiva_cache_enabled',
            'mhm_rentiva_cache_default_ttl',
            'mhm_rentiva_cache_lists_ttl',
            'mhm_rentiva_cache_reports_ttl',
            'mhm_rentiva_cache_charts_ttl',
            'mhm_rentiva_db_auto_optimize',
            'mhm_rentiva_db_performance_threshold',
            'mhm_rentiva_wp_optimization_enabled',
            'mhm_rentiva_wp_memory_limit',
            'mhm_rentiva_wp_meta_query_limit'
        ];

        foreach ($settings as $setting) {
            $sanitize_callback = 'sanitize_text_field';
            if (in_array($setting, ['mhm_rentiva_cache_lists_ttl', 'mhm_rentiva_cache_reports_ttl', 'mhm_rentiva_cache_charts_ttl', 'mhm_rentiva_db_performance_threshold', 'mhm_rentiva_wp_memory_limit', 'mhm_rentiva_wp_meta_query_limit'])) {
                $sanitize_callback = 'absint';
            } elseif ($setting === 'mhm_rentiva_cache_default_ttl') {
                $sanitize_callback = 'floatval';
            }
            register_setting('mhm_rentiva_settings', $setting, ['sanitize_callback' => $sanitize_callback]);
        }
    }

    /**
     * Section description
     */
    public static function render_section_description(): void
    {
        echo '<p>' . esc_html__('Basic system settings and performance optimizations for the plugin.', 'mhm-rentiva') . '</p>';
        echo '<div class="notice notice-warning inline" style="margin: 10px 0;">';
        echo '<p><strong>' . esc_html__('Warning:', 'mhm-rentiva') . '</strong> ';
        echo esc_html__('These settings directly affect the plugin\'s performance and security. Back up before making changes.', 'mhm-rentiva');
        echo '</p></div>';
    }



    // Cache Settings
    public static function render_cache_enabled_field(): void
    {
        $value = SettingsCore::get('mhm_rentiva_cache_enabled', '1');
        echo '<label><input type="checkbox" name="mhm_rentiva_settings[mhm_rentiva_cache_enabled]" value="1"' . checked($value, '1', false) . '> ' . esc_html__('Yes, object cache active', 'mhm-rentiva') . '</label>';
        echo '<p class="description">' . esc_html__('Use object cache for performance.', 'mhm-rentiva') . '</p>';
    }

    public static function render_cache_default_ttl_field(): void
    {
        $value = floatval(SettingsCore::get('mhm_rentiva_cache_default_ttl', 1));
        echo '<input type="number" name="mhm_rentiva_settings[mhm_rentiva_cache_default_ttl]" value="' . esc_attr($value) . '" min="0.5" max="24" step="0.5" class="small-text" />';
        echo '<p class="description">' . esc_html__('Default lifetime of cache data (hours).', 'mhm-rentiva') . '</p>';
    }

    public static function render_cache_lists_ttl_field(): void
    {
        $value = absint(SettingsCore::get('mhm_rentiva_cache_lists_ttl', 5));
        echo '<input type="number" name="mhm_rentiva_settings[mhm_rentiva_cache_lists_ttl]" value="' . esc_attr($value) . '" min="1" max="60" step="1" class="small-text" />';
        echo '<p class="description">' . esc_html__('Cache duration of list data (minutes).', 'mhm-rentiva') . '</p>';
    }

    public static function render_cache_reports_ttl_field(): void
    {
        $value = absint(SettingsCore::get('mhm_rentiva_cache_reports_ttl', 15));
        echo '<input type="number" name="mhm_rentiva_settings[mhm_rentiva_cache_reports_ttl]" value="' . esc_attr($value) . '" min="1" max="1440" step="1" class="small-text" />';
        echo '<p class="description">' . esc_html__('Cache duration of report data (minutes).', 'mhm-rentiva') . '</p>';
    }

    public static function render_cache_charts_ttl_field(): void
    {
        $value = absint(SettingsCore::get('mhm_rentiva_cache_charts_ttl', 10));
        echo '<input type="number" name="mhm_rentiva_settings[mhm_rentiva_cache_charts_ttl]" value="' . esc_attr($value) . '" min="1" max="1440" step="1" class="small-text" />';
        echo '<p class="description">' . esc_html__('Cache duration of chart data (minutes).', 'mhm-rentiva') . '</p>';
    }

    // Database Settings
    public static function render_db_auto_optimize_field(): void
    {
        $value = SettingsCore::get('mhm_rentiva_db_auto_optimize', '0');
        echo '<label><input type="checkbox" name="mhm_rentiva_settings[mhm_rentiva_db_auto_optimize]" value="1"' . checked($value, '1', false) . '> ' . esc_html__('Yes, auto optimize', 'mhm-rentiva') . '</label>';
        echo '<p class="description">' . esc_html__('Automatically optimize database performance.', 'mhm-rentiva') . '</p>';
    }

    public static function render_db_performance_threshold_field(): void
    {
        $value = absint(SettingsCore::get('mhm_rentiva_db_performance_threshold', 100));
        echo '<input type="number" name="mhm_rentiva_settings[mhm_rentiva_db_performance_threshold]" value="' . esc_attr($value) . '" min="50" max="1000" step="10" class="small-text" />';
        echo '<p class="description">' . esc_html__('Threshold value for performance warning (milliseconds).', 'mhm-rentiva') . '</p>';
    }

    // WordPress Optimization Settings
    public static function render_wp_optimization_enabled_field(): void
    {
        $value = SettingsCore::get('mhm_rentiva_wp_optimization_enabled', '1');
        echo '<label><input type="checkbox" name="mhm_rentiva_settings[mhm_rentiva_wp_optimization_enabled]" value="1"' . checked($value, '1', false) . '> ' . esc_html__('Yes, optimize WordPress', 'mhm-rentiva') . '</label>';
        echo '<p class="description">' . esc_html__('Disable unnecessary WordPress features.', 'mhm-rentiva') . '</p>';
    }

    public static function render_wp_memory_limit_field(): void
    {
        $value = absint(SettingsCore::get('mhm_rentiva_wp_memory_limit', 256));
        echo '<input type="number" name="mhm_rentiva_settings[mhm_rentiva_wp_memory_limit]" value="' . esc_attr($value) . '" min="128" max="1024" step="64" class="small-text" />';
        echo '<p class="description">' . esc_html__('WordPress memory limit (MB).', 'mhm-rentiva') . '</p>';
    }

    public static function render_wp_meta_query_limit_field(): void
    {
        $value = absint(SettingsCore::get('mhm_rentiva_wp_meta_query_limit', 5));
        echo '<input type="number" name="mhm_rentiva_settings[mhm_rentiva_wp_meta_query_limit]" value="' . esc_attr($value) . '" min="1" max="20" step="1" class="small-text" />';
        echo '<p class="description">' . esc_html__('Meta query count limit.', 'mhm-rentiva') . '</p>';
    }

    // Getter Methods


    public static function is_cache_enabled(): bool
    {
        return SettingsCore::get('mhm_rentiva_cache_enabled', '1') === '1';
    }

    public static function get_cache_default_ttl(): int
    {
        return (int) (floatval(SettingsCore::get('mhm_rentiva_cache_default_ttl', 1)) * HOUR_IN_SECONDS);
    }

    public static function get_cache_lists_ttl(): int
    {
        return (int) (absint(SettingsCore::get('mhm_rentiva_cache_lists_ttl', 5)) * MINUTE_IN_SECONDS);
    }

    public static function get_cache_reports_ttl(): int
    {
        return (int) (absint(SettingsCore::get('mhm_rentiva_cache_reports_ttl', 15)) * MINUTE_IN_SECONDS);
    }

    public static function get_cache_charts_ttl(): int
    {
        return (int) (absint(SettingsCore::get('mhm_rentiva_cache_charts_ttl', 10)) * MINUTE_IN_SECONDS);
    }

    public static function is_db_auto_optimize(): bool
    {
        return SettingsCore::get('mhm_rentiva_db_auto_optimize', '0') === '1';
    }

    public static function get_db_performance_threshold(): int
    {
        return absint(SettingsCore::get('mhm_rentiva_db_performance_threshold', 100));
    }

    public static function is_wp_optimization_enabled(): bool
    {
        return SettingsCore::get('mhm_rentiva_wp_optimization_enabled', '1') === '1';
    }

    public static function get_wp_memory_limit(): int
    {
        return absint(SettingsCore::get('mhm_rentiva_wp_memory_limit', 256));
    }

    public static function get_wp_meta_query_limit(): int
    {
        return absint(SettingsCore::get('mhm_rentiva_wp_meta_query_limit', 5));
    }
}
