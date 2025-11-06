<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Settings\Groups;

use MHMRentiva\Admin\Settings\Core\SettingsCore;
use MHMRentiva\Admin\Settings\Core\SettingsHelper;

if (!defined('ABSPATH')) {
    exit;
}

final class ReconcileSettings
{
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
            'mhm_rentiva_reconcile_section',
            __('Automatic Reconciliation', 'mhm-rentiva'),
            [self::class, 'render_section_description'],
            $group
        );

        // Automatic Reconciliation
        add_settings_field('mhm_rentiva_reconcile_enabled', __('Automatic Reconciliation', 'mhm-rentiva'), [self::class, 'render_reconcile_enabled_field'], $group, 'mhm_rentiva_reconcile_section');
        register_setting($group, 'mhm_rentiva_reconcile_enabled', ['type' => 'string', 'sanitize_callback' => function ($v) {
            return $v === '1' ? '1' : '0';
        }]);

        // Reconciliation Frequency
        add_settings_field('mhm_rentiva_reconcile_frequency', __('Reconciliation Frequency', 'mhm-rentiva'), [self::class, 'render_reconcile_frequency_field'], $group, 'mhm_rentiva_reconcile_section');
        register_setting($group, 'mhm_rentiva_reconcile_frequency', ['type' => 'string', 'sanitize_callback' => function ($v) {
            $frequencies = ['hourly', 'daily', 'weekly'];
            return in_array($v, $frequencies, true) ? $v : 'daily';
        }]);

        // Reconciliation Timeout
        add_settings_field('mhm_rentiva_reconcile_timeout', __('Reconciliation Timeout (minutes)', 'mhm-rentiva'), [self::class, 'render_reconcile_timeout_field'], $group, 'mhm_rentiva_reconcile_section');
        register_setting($group, 'mhm_rentiva_reconcile_timeout', ['type' => 'integer', 'sanitize_callback' => function ($v) {
            $v = (int) $v;
            return $v >= 5 && $v <= 60 ? $v : 30;
        }]);

        // Error Notification
        add_settings_field('mhm_rentiva_reconcile_notify_errors', __('Error Notification', 'mhm-rentiva'), [self::class, 'render_reconcile_notify_field'], $group, 'mhm_rentiva_reconcile_section');
        register_setting($group, 'mhm_rentiva_reconcile_notify_errors', ['type' => 'string', 'sanitize_callback' => function ($v) {
            return $v === '1' ? '1' : '0';
        }]);
    }

    public static function render_section_description(): void
    {
        echo '<p class="description">' . esc_html__('Automatically reconcile and update payment statuses. This feature provides synchronization with payment providers.', 'mhm-rentiva') . '</p>';
    }

    public static function render_reconcile_enabled_field(): void
    {
        $val = self::sanitize_text_field_safe(get_option('mhm_rentiva_reconcile_enabled', '0'));
        echo '<label><input type="radio" name="mhm_rentiva_reconcile_enabled" value="1" ' . checked($val, '1', false) . '> ' . esc_html__('Enabled', 'mhm-rentiva') . '</label><br>';
        echo '<label><input type="radio" name="mhm_rentiva_reconcile_enabled" value="0" ' . checked($val, '0', false) . '> ' . esc_html__('Disabled', 'mhm-rentiva') . '</label>';
        echo '<p class="description">' . esc_html__('Automatically reconcile and update payment statuses.', 'mhm-rentiva') . '</p>';
    }

    public static function render_reconcile_frequency_field(): void
    {
        $val = self::sanitize_text_field_safe(get_option('mhm_rentiva_reconcile_frequency', 'daily'));
        $frequencies = [
            'hourly' => __('Hourly', 'mhm-rentiva'),
            'daily' => __('Daily', 'mhm-rentiva'),
            'weekly' => __('Weekly', 'mhm-rentiva')
        ];
        
        echo '<select name="mhm_rentiva_reconcile_frequency">';
        foreach ($frequencies as $freq => $label) {
            echo '<option value="' . esc_attr($freq) . '"' . selected($val, $freq, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . esc_html__('Determine how often the reconciliation process will run.', 'mhm-rentiva') . '</p>';
    }

    public static function render_reconcile_timeout_field(): void
    {
        $val = absint(get_option('mhm_rentiva_reconcile_timeout', 30));
        echo '<input type="number" class="small-text" min="5" max="60" name="mhm_rentiva_reconcile_timeout" value="' . esc_attr((string) $val) . '"/>';
        echo '<p class="description">' . esc_html__('Maximum waiting time for reconciliation process (5-60 minutes).', 'mhm-rentiva') . '</p>';
    }

    public static function render_reconcile_notify_field(): void
    {
        $val = self::sanitize_text_field_safe(get_option('mhm_rentiva_reconcile_notify_errors', '1'));
        echo '<label><input type="radio" name="mhm_rentiva_reconcile_notify_errors" value="1" ' . checked($val, '1', false) . '> ' . esc_html__('Enabled', 'mhm-rentiva') . '</label><br>';
        echo '<label><input type="radio" name="mhm_rentiva_reconcile_notify_errors" value="0" ' . checked($val, '0', false) . '> ' . esc_html__('Disabled', 'mhm-rentiva') . '</label>';
        echo '<p class="description">' . esc_html__('Send email notification for reconciliation errors.', 'mhm-rentiva') . '</p>';
    }

    /**
     * Check if reconciliation is enabled
     */
    public static function is_reconcile_enabled(): bool
    {
        return get_option('mhm_rentiva_reconcile_enabled', '0') === '1';
    }

    /**
     * Get reconciliation frequency
     */
    public static function get_reconcile_frequency(): string
    {
        $frequency = get_option('mhm_rentiva_reconcile_frequency', 'daily');
        $frequencies = ['hourly', 'daily', 'weekly'];
        return in_array($frequency, $frequencies, true) ? $frequency : 'daily';
    }

    /**
     * Get reconciliation timeout in minutes
     */
    public static function get_reconcile_timeout(): int
    {
        $timeout = absint(get_option('mhm_rentiva_reconcile_timeout', 30));
        return max(5, min(60, $timeout));
    }

    /**
     * Check if error notifications are enabled
     */
    public static function is_error_notification_enabled(): bool
    {
        return get_option('mhm_rentiva_reconcile_notify_errors', '1') === '1';
    }

    /**
     * Get all reconciliation settings as array
     */
    public static function get_all_settings(): array
    {
        return [
            'enabled' => self::is_reconcile_enabled(),
            'frequency' => self::get_reconcile_frequency(),
            'timeout' => self::get_reconcile_timeout(),
            'notify_errors' => self::is_error_notification_enabled()
        ];
    }

    /**
     * Validate reconciliation frequency
     */
    public static function validate_frequency(string $frequency): bool
    {
        $frequencies = ['hourly', 'daily', 'weekly'];
        return in_array($frequency, $frequencies, true);
    }

    /**
     * Validate reconciliation timeout
     */
    public static function validate_timeout(int $timeout): bool
    {
        return $timeout >= 5 && $timeout <= 60;
    }

    /**
     * Get frequency label for display
     */
    public static function get_frequency_label(string $frequency): string
    {
        $labels = [
            'hourly' => __('Hourly', 'mhm-rentiva'),
            'daily' => __('Daily', 'mhm-rentiva'),
            'weekly' => __('Weekly', 'mhm-rentiva')
        ];
        
        return $labels[$frequency] ?? __('Daily', 'mhm-rentiva');
    }
}
