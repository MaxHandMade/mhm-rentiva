<?php

namespace MHMRentiva\Admin\Settings\Groups;

use MHMRentiva\Admin\Settings\Core\SettingsCore;
use MHMRentiva\Admin\Settings\Core\SettingsHelper;

if (!defined('ABSPATH')) {
    exit;
}

class EmailSettings
{
    public const SECTION_ID = 'mhm_rentiva_email_section';
    public const SECTION_TITLE = 'Email Settings';
    public const SECTION_DESCRIPTION = 'Configure email sending and template settings.';

    public static function register(): void
    {
        add_settings_section(
            self::SECTION_ID,
            self::SECTION_TITLE,
            [self::class, 'render_section_description'],
            'mhm_rentiva_settings'
        );

        if (class_exists('WooCommerce')) {
            return;
        }

        // General Email Settings
        add_settings_field(
            'mhm_rentiva_email_from_name',
            __('Sender Name', 'mhm-rentiva'),
            [self::class, 'render_from_name_field'],
            'mhm_rentiva_settings',
            self::SECTION_ID
        );

        add_settings_field(
            'mhm_rentiva_email_from_address',
            __('Sender Email', 'mhm-rentiva'),
            [self::class, 'render_from_address_field'],
            'mhm_rentiva_settings',
            self::SECTION_ID
        );

        add_settings_field(
            'mhm_rentiva_email_reply_to',
            __('Reply Address', 'mhm-rentiva'),
            [self::class, 'render_reply_to_field'],
            'mhm_rentiva_settings',
            self::SECTION_ID
        );

        // Email Sending Settings
        add_settings_field(
            'mhm_rentiva_email_send_enabled',
            __('Email Sending Enabled', 'mhm-rentiva'),
            [self::class, 'render_send_enabled_field'],
            'mhm_rentiva_settings',
            self::SECTION_ID
        );

        add_settings_field(
            'mhm_rentiva_email_test_mode',
            __('Test Mode', 'mhm-rentiva'),
            [self::class, 'render_test_mode_field'],
            'mhm_rentiva_settings',
            self::SECTION_ID
        );

        add_settings_field(
            'mhm_rentiva_email_test_address',
            __('Test Email Address', 'mhm-rentiva'),
            [self::class, 'render_test_address_field'],
            'mhm_rentiva_settings',
            self::SECTION_ID
        );

        // Email Template Settings
        add_settings_field(
            'mhm_rentiva_email_template_path',
            __('Template File Path', 'mhm-rentiva'),
            [self::class, 'render_template_path_field'],
            'mhm_rentiva_settings',
            self::SECTION_ID
        );

        add_settings_field(
            'mhm_rentiva_email_auto_send',
            __('Automatic Email Sending', 'mhm-rentiva'),
            [self::class, 'render_auto_send_field'],
            'mhm_rentiva_settings',
            self::SECTION_ID
        );

        // Email Statistics Settings
        add_settings_field(
            'mhm_rentiva_email_log_enabled',
            __('Email Logging', 'mhm-rentiva'),
            [self::class, 'render_log_enabled_field'],
            'mhm_rentiva_settings',
            self::SECTION_ID
        );

        add_settings_field(
            'mhm_rentiva_email_log_retention_days',
            __('Log Retention Period (Days)', 'mhm-rentiva'),
            [self::class, 'render_log_retention_field'],
            'mhm_rentiva_settings',
            self::SECTION_ID
        );

        // Register all settings with proper sanitization
        $settings = [
            'mhm_rentiva_email_from_name',
            'mhm_rentiva_email_from_address',
            'mhm_rentiva_email_reply_to',
            'mhm_rentiva_email_send_enabled',
            'mhm_rentiva_email_test_mode',
            'mhm_rentiva_email_test_address',
            'mhm_rentiva_email_template_path',
            'mhm_rentiva_email_auto_send',
            'mhm_rentiva_email_log_enabled',
            'mhm_rentiva_email_log_retention_days'
        ];

        foreach ($settings as $setting) {
            $sanitize_callback = 'sanitize_text_field';
            if ($setting === 'mhm_rentiva_email_log_retention_days') {
                $sanitize_callback = 'absint';
            } elseif (in_array($setting, ['mhm_rentiva_email_from_address', 'mhm_rentiva_email_reply_to', 'mhm_rentiva_email_test_address'])) {
                // ✅ Use safe email sanitizer to prevent strlen() errors
                $sanitize_callback = [\MHMRentiva\Admin\Settings\Core\SettingsHelper::class, 'sanitize_email_safe'];
            }
            register_setting('mhm_rentiva_settings', $setting, ['sanitize_callback' => $sanitize_callback]);
        }
    }

    public static function render_section_description(): void
    {
        if (class_exists('WooCommerce')) {
            echo '<div class="notice notice-info inline" style="margin: 10px 0;">';
            echo '<p><strong>' . esc_html__('WooCommerce Active:', 'mhm-rentiva') . '</strong> ';
            echo esc_html__('Email notifications are handled by WooCommerce. These settings are disabled.', 'mhm-rentiva');
            echo '</p></div>';
            return;
        }

        echo '<p>' . esc_html(self::SECTION_DESCRIPTION) . '</p>';
        echo '<div class="notice notice-info inline" style="margin: 10px 0;">';
        echo '<p><strong>' . esc_html__('Note:', 'mhm-rentiva') . '</strong> ';
        echo esc_html__('These settings affect all email sending. In test mode, emails are only sent to the test address.', 'mhm-rentiva');
        echo '</p></div>';
        
        // Link to email templates
        $email_templates_url = admin_url('admin.php?page=mhm-rentiva-settings&tab=email-templates');
        echo '<div class="notice notice-info inline" style="margin: 10px 0;">';
        echo '<p><strong>' . esc_html__('Email Contents:', 'mhm-rentiva') . '</strong> ';
        echo '<a href="' . esc_url($email_templates_url) . '" class="button button-secondary" style="margin-left: 10px;">';
        echo esc_html__('Edit Email Templates', 'mhm-rentiva') . '</a>';
        echo '</p></div>';

        // Send Test Email form (respects test mode)
        if (current_user_can('manage_options')) {
            $action_url = admin_url('admin-post.php');
            echo '<form method="post" action="' . esc_url($action_url) . '" style="margin-top:10px;">';
            echo '<input type="hidden" name="action" value="mhm_rentiva_send_test_email" />';
            echo wp_nonce_field('mhm_rentiva_send_test_email', '_wpnonce', true, false);
            echo '<button type="submit" class="button">' . esc_html__('Send Test Email', 'mhm-rentiva') . '</button>';
            echo '</form>';

            if (isset($_GET['mhm_email_test'])) {
                $status = sanitize_text_field(wp_unslash($_GET['mhm_email_test'] ?? ''));
                if ($status === 'success') {
                    echo '<div class="notice notice-success inline" style="margin-top:8px;"><p>' . esc_html__('Test email sent successfully.', 'mhm-rentiva') . '</p></div>';
                } elseif ($status === 'failed') {
                    echo '<div class="notice notice-error inline" style="margin-top:8px;"><p>' . esc_html__('Failed to send test email. Check email settings or server mail configuration.', 'mhm-rentiva') . '</p></div>';
                }
            }
        }
    }

    public static function render_from_name_field(): void
    {
        $value = SettingsHelper::sanitize_text_field_safe(SettingsCore::get('mhm_rentiva_email_from_name', get_bloginfo('name')));
        echo '<input type="text" name="mhm_rentiva_settings[mhm_rentiva_email_from_name]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . esc_html__('Sender name to appear in emails.', 'mhm-rentiva') . '</p>';
    }

    public static function render_from_address_field(): void
    {
        $value = sanitize_email(SettingsCore::get('mhm_rentiva_email_from_address', get_option('admin_email')));
        echo '<input type="email" name="mhm_rentiva_settings[mhm_rentiva_email_from_address]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . esc_html__('Email address from which emails will be sent.', 'mhm-rentiva') . '</p>';
    }

    public static function render_reply_to_field(): void
    {
        $value = sanitize_email(SettingsCore::get('mhm_rentiva_email_reply_to', get_option('admin_email')));
        echo '<input type="email" name="mhm_rentiva_settings[mhm_rentiva_email_reply_to]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . esc_html__('Address to which replies will be sent.', 'mhm-rentiva') . '</p>';
    }

    public static function render_send_enabled_field(): void
    {
        $value = SettingsHelper::sanitize_text_field_safe(SettingsCore::get('mhm_rentiva_email_send_enabled', '1'));
        echo '<label><input type="checkbox" name="mhm_rentiva_settings[mhm_rentiva_email_send_enabled]" value="1"' . checked($value, '1', false) . '> ' . esc_html__('Email sending enabled', 'mhm-rentiva') . '</label>';
        echo '<p class="description">' . esc_html__('If this option is disabled, no emails will be sent.', 'mhm-rentiva') . '</p>';
    }

    public static function render_test_mode_field(): void
    {
        $value = SettingsHelper::sanitize_text_field_safe(SettingsCore::get('mhm_rentiva_email_test_mode', '0'));
        echo '<label><input type="checkbox" name="mhm_rentiva_settings[mhm_rentiva_email_test_mode]" value="1"' . checked($value, '1', false) . '> ' . esc_html__('Test mode enabled', 'mhm-rentiva') . '</label>';
        echo '<p class="description">' . esc_html__('When test mode is active, all emails are sent to the test address.', 'mhm-rentiva') . '</p>';
    }

    public static function render_test_address_field(): void
    {
        $value = sanitize_email(SettingsCore::get('mhm_rentiva_email_test_address', get_option('admin_email')));
        echo '<input type="email" name="mhm_rentiva_settings[mhm_rentiva_email_test_address]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . esc_html__('Address to which emails will be sent in test mode.', 'mhm-rentiva') . '</p>';
    }

    public static function render_template_path_field(): void
    {
        $value = SettingsHelper::sanitize_text_field_safe(SettingsCore::get('mhm_rentiva_email_template_path', 'mhm-rentiva/emails/'));
        echo '<input type="text" name="mhm_rentiva_settings[mhm_rentiva_email_template_path]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . esc_html__('Theme folder path where email templates are located.', 'mhm-rentiva') . '</p>';
    }

    public static function render_auto_send_field(): void
    {
        $value = SettingsHelper::sanitize_text_field_safe(SettingsCore::get('mhm_rentiva_email_auto_send', '1'));
        echo '<label><input type="checkbox" name="mhm_rentiva_settings[mhm_rentiva_email_auto_send]" value="1"' . checked($value, '1', false) . '> ' . esc_html__('Automatic email sending enabled', 'mhm-rentiva') . '</label>';
        echo '<p class="description">' . esc_html__('Send automatic emails when booking status changes.', 'mhm-rentiva') . '</p>';
    }

    public static function render_log_enabled_field(): void
    {
        $value = SettingsHelper::sanitize_text_field_safe(SettingsCore::get('mhm_rentiva_email_log_enabled', '1'));
        echo '<label><input type="checkbox" name="mhm_rentiva_settings[mhm_rentiva_email_log_enabled]" value="1"' . checked($value, '1', false) . '> ' . esc_html__('Email logging enabled', 'mhm-rentiva') . '</label>';
        echo '<p class="description">' . esc_html__('Sent emails are logged.', 'mhm-rentiva') . '</p>';
    }

    public static function render_log_retention_field(): void
    {
        $value = absint(SettingsCore::get('mhm_rentiva_email_log_retention_days', 30));
        echo '<input type="number" name="mhm_rentiva_settings[mhm_rentiva_email_log_retention_days]" value="' . esc_attr($value) . '" min="1" max="365" class="small-text" />';
        echo '<p class="description">' . esc_html__('Number of days to keep email logs.', 'mhm-rentiva') . '</p>';
    }

    // Getter methods
    public static function get_from_name(): string
    {
        return SettingsHelper::sanitize_text_field_safe(SettingsCore::get('mhm_rentiva_email_from_name', get_bloginfo('name')));
    }

    public static function get_from_address(): string
    {
        return sanitize_email(SettingsCore::get('mhm_rentiva_email_from_address', get_option('admin_email')));
    }

    public static function get_reply_to(): string
    {
        return sanitize_email(SettingsCore::get('mhm_rentiva_email_reply_to', get_option('admin_email')));
    }

    public static function is_send_enabled(): bool
    {
        return SettingsHelper::sanitize_text_field_safe(SettingsCore::get('mhm_rentiva_email_send_enabled', '1')) === '1';
    }

    public static function is_test_mode(): bool
    {
        return SettingsHelper::sanitize_text_field_safe(SettingsCore::get('mhm_rentiva_email_test_mode', '0')) === '1';
    }

    public static function get_test_address(): string
    {
        return sanitize_email(SettingsCore::get('mhm_rentiva_email_test_address', get_option('admin_email')));
    }

    public static function get_template_path(): string
    {
        return SettingsHelper::sanitize_text_field_safe(SettingsCore::get('mhm_rentiva_email_template_path', 'mhm-rentiva/emails/'));
    }

    public static function is_auto_send_enabled(): bool
    {
        return SettingsHelper::sanitize_text_field_safe(SettingsCore::get('mhm_rentiva_email_auto_send', '1')) === '1';
    }

    public static function is_log_enabled(): bool
    {
        return SettingsHelper::sanitize_text_field_safe(SettingsCore::get('mhm_rentiva_email_log_enabled', '1')) === '1';
    }

    public static function get_log_retention_days(): int
    {
        return absint(SettingsCore::get('mhm_rentiva_email_log_retention_days', 30));
    }
}
