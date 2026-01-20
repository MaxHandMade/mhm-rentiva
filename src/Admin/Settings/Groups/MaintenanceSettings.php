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
     * NOTE: Auto-cancel settings are now exclusively in BookingSettings:
     * - mhm_rentiva_booking_auto_cancel_enabled
     * - mhm_rentiva_booking_payment_deadline_minutes
     *
     * @return array
     */
    public static function get_default_settings(): array
    {
        return [
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

        add_settings_field(
            'mhm_rentiva_clean_data_on_uninstall',
            __('Clean Data on Uninstall', 'mhm-rentiva'),
            [self::class, 'render_uninstall_cleanup_field'],
            $group,
            'mhm_rentiva_maintenance_section'
        );

        // NOTE: Auto-cancel settings removed - now handled by BookingSettings
        // See: Rezervasyon Yönetimi > Zaman Yönetimi Ayarları
    }

    /**
     * Clear Data on Uninstall Field
     */
    public static function render_uninstall_cleanup_field(): void
    {
        $enabled = SettingsCore::get('mhm_rentiva_clean_data_on_uninstall', '0');
        echo '<label>';
        echo '<input type="checkbox" name="mhm_rentiva_settings[mhm_rentiva_clean_data_on_uninstall]" value="1"' . checked($enabled, '1', false) . '> ';
        echo esc_html__('Clean all plugin data and database tables when the plugin is deleted from WordPress.', 'mhm-rentiva');
        echo '</label>';
        echo '<p class="description" style="color: #d63638; font-weight: bold;">';
        echo '<strong>' . esc_html__('Caution:', 'mhm-rentiva') . '</strong> ';
        echo esc_html__('If enabled, when you delete the plugin from WordPress (Plugins > Installed Plugins > Delete), all database tables and settings will be permanently deleted. This cannot be undone.', 'mhm-rentiva');
        echo '</p>';
    }

    public static function render_section_description(): void
    {
        echo '<p class="description">' . esc_html__('Settings for system maintenance and automatic tasks.', 'mhm-rentiva') . '</p>';
        echo '<p class="description" style="color: #666; font-style: italic;">' .
            sprintf(
                /* translators: %s: Settings tab name */
                esc_html__('Auto-cancel settings have been moved to %s tab.', 'mhm-rentiva'),
                '<strong>' . esc_html__('Booking Management', 'mhm-rentiva') . '</strong>'
            ) .
            '</p>';
    }
}
