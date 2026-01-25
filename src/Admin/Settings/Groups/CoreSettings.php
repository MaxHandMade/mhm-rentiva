<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Settings\Groups;

use MHMRentiva\Admin\Settings\Core\SettingsCore;
use MHMRentiva\Admin\Settings\Core\SettingsHelper;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Core System Settings Group
 * 
 * Manages cache, performance, and low-level system configurations.
 * Optimized for high performance and standardized rendering.
 */
final class CoreSettings
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
            'mhm_rentiva_cache_enabled'       => '1',
            'mhm_rentiva_cache_default_ttl'   => 1.0,
            'mhm_rentiva_cache_lists_ttl'     => 5,
            'mhm_rentiva_cache_reports_ttl'   => 15,
            'mhm_rentiva_cache_charts_ttl'    => 10,

            // Query Limits
            'mhm_rentiva_wp_meta_query_limit' => 5,
        ];
    }

    /**
     * Register core settings
     */
    public static function register(): void
    {
        $page_slug = SettingsCore::PAGE;

        add_settings_section(
            self::SECTION_ID,
            __('System & Performance', 'mhm-rentiva'),
            [self::class, 'render_section_description'],
            $page_slug
        );

        SettingsHelper::checkbox_field($page_slug, 'mhm_rentiva_cache_enabled', __('Enable Object Cache', 'mhm-rentiva'), __('Active object caching reduces database load significantly.', 'mhm-rentiva'), self::SECTION_ID);
        SettingsHelper::number_field($page_slug, 'mhm_rentiva_cache_default_ttl', __('Default Cache TTL (Hours)', 'mhm-rentiva'), 0.5, 24, __('How long general data remains cached.', 'mhm-rentiva'), self::SECTION_ID);
        SettingsHelper::number_field($page_slug, 'mhm_rentiva_cache_lists_ttl', __('Lists Cache TTL (Minutes)', 'mhm-rentiva'), 1, 60, __('Cache duration for vehicle and booking lists.', 'mhm-rentiva'), self::SECTION_ID);
        SettingsHelper::number_field($page_slug, 'mhm_rentiva_cache_reports_ttl', __('Reports Cache TTL (Minutes)', 'mhm-rentiva'), 1, 1440, __('Cache duration for report calculations.', 'mhm-rentiva'), self::SECTION_ID);
        SettingsHelper::number_field($page_slug, 'mhm_rentiva_cache_charts_ttl', __('Charts Cache TTL (Minutes)', 'mhm-rentiva'), 1, 1440, __('Cache duration for dashboard charts.', 'mhm-rentiva'), self::SECTION_ID);
        SettingsHelper::number_field($page_slug, 'mhm_rentiva_wp_meta_query_limit', __('Meta Query Limit', 'mhm-rentiva'), 1, 50, __('Maximum meta queries per request. Lower is faster.', 'mhm-rentiva'), self::SECTION_ID);
    }

    public static function render_section_description(): void
    {
        echo '<p>' . esc_html__('Configure system optimizations and performance thresholds.', 'mhm-rentiva') . '</p>';
    }

    // Static Accessors
    public static function is_cache_enabled(): bool
    {
        return SettingsCore::get('mhm_rentiva_cache_enabled', '1') === '1';
    }
    public static function get_cache_default_ttl(): int
    {
        return (int) (floatval(SettingsCore::get('mhm_rentiva_cache_default_ttl', 1.0)) * HOUR_IN_SECONDS);
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
    public static function get_wp_meta_query_limit(): int
    {
        return (int) SettingsCore::get('mhm_rentiva_wp_meta_query_limit', 5);
    }
}
