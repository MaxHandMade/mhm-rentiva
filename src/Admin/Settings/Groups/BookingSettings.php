<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Settings\Groups;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Booking Management Settings
 * 
 * Only includes actively used settings
 * 
 * @since 4.0.0
 */
final class BookingSettings
{
    /**
     * Register settings
     */
    public static function register(): void
    {
        self::register_settings();
    }

    /**
     * Register all booking settings
     */
    public static function register_settings(): void
    {
        // Basic Booking Settings Section
        add_settings_section(
            'mhm_rentiva_booking_basic_section',
            __('Basic Booking Settings', 'mhm-rentiva'),
            [self::class, 'render_basic_section_description'],
            'mhm_rentiva_settings'
        );

        // Time Management Settings Section
        add_settings_section(
            'mhm_rentiva_booking_time_section',
            __('Time Management Settings', 'mhm-rentiva'),
            [self::class, 'render_time_section_description'],
            'mhm_rentiva_settings'
        );

        // Notification Settings Section
        add_settings_section(
            'mhm_rentiva_booking_notification_section',
            __('Notification Settings', 'mhm-rentiva'),
            [self::class, 'render_notification_section_description'],
            'mhm_rentiva_settings'
        );

        // Basic Fields - REMOVED: date_format (duplicate), history_retention_days (unused)

        // Time Management Fields
        add_settings_field(
            'mhm_rentiva_booking_cancellation_deadline_hours',
            __('Cancellation Deadline (Hours)', 'mhm-rentiva'),
            [self::class, 'render_cancellation_deadline_field'],
            'mhm_rentiva_settings',
            'mhm_rentiva_booking_time_section'
        );

        add_settings_field(
            'mhm_rentiva_booking_payment_deadline_minutes',
            __('Payment Deadline (Minutes)', 'mhm-rentiva'),
            [self::class, 'render_payment_deadline_field'],
            'mhm_rentiva_settings',
            'mhm_rentiva_booking_time_section'
        );

        add_settings_field(
            'mhm_rentiva_booking_auto_cancel_enabled',
            __('Enable Auto Cancel', 'mhm-rentiva'),
            [self::class, 'render_auto_cancel_enabled_field'],
            'mhm_rentiva_settings',
            'mhm_rentiva_booking_time_section'
        );

        // REMOVED: confirmation_timeout_hours (no confirmation system)

        // Notification Fields
        add_settings_field(
            'mhm_rentiva_booking_send_confirmation_emails',
            __('Send Confirmation Emails', 'mhm-rentiva'),
            [self::class, 'render_send_confirmation_emails_field'],
            'mhm_rentiva_settings',
            'mhm_rentiva_booking_notification_section'
        );

        add_settings_field(
            'mhm_rentiva_booking_send_reminder_emails',
            __('Send Reminder Emails', 'mhm-rentiva'),
            [self::class, 'render_send_reminder_emails_field'],
            'mhm_rentiva_settings',
            'mhm_rentiva_booking_notification_section'
        );

        // REMOVED: reminder_hours_before (no reminder system)

        add_settings_field(
            'mhm_rentiva_booking_admin_notifications',
            __('Admin Notifications', 'mhm-rentiva'),
            [self::class, 'render_admin_notifications_field'],
            'mhm_rentiva_settings',
            'mhm_rentiva_booking_notification_section'
        );
    }

    /**
     * Section Descriptions
     */
    public static function render_basic_section_description(): void
    {
        echo '<p class="description">' . esc_html__('General booking configuration and data retention settings.', 'mhm-rentiva') . '</p>';
    }

    public static function render_time_section_description(): void
    {
        echo '<p class="description">' . esc_html__('Configure timing rules for bookings, cancellations and payments.', 'mhm-rentiva') . '</p>';
    }

    public static function render_notification_section_description(): void
    {
        echo '<p class="description">' . esc_html__('Email notification settings for bookings.', 'mhm-rentiva') . '</p>';
    }

    /**
     * Time Management Fields
     */
    public static function render_cancellation_deadline_field(): void
    {
        $value = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_booking_cancellation_deadline_hours');
        echo '<input type="number" name="mhm_rentiva_settings[mhm_rentiva_booking_cancellation_deadline_hours]" value="' . esc_attr($value) . '" min="1" max="168" step="1" class="small-text" />';
        echo '<p class="description">' . esc_html__('Hours before booking start that customers can cancel.', 'mhm-rentiva') . '</p>';
    }

    public static function render_payment_deadline_field(): void
    {
        $value = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_booking_payment_deadline_minutes');
        echo '<input type="number" name="mhm_rentiva_settings[mhm_rentiva_booking_payment_deadline_minutes]" value="' . esc_attr($value) . '" min="0" max="1440" step="1" class="small-text" />';
        echo '<p class="description">' . esc_html__('Minutes allowed for payment completion. Set to 0 to disable deadline.', 'mhm-rentiva') . '</p>';
    }

    public static function render_auto_cancel_enabled_field(): void
    {
        $value = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_booking_auto_cancel_enabled');
        echo '<label><input type="checkbox" name="mhm_rentiva_settings[mhm_rentiva_booking_auto_cancel_enabled]" value="1"' . checked($value, '1', false) . '> ' . esc_html__('Automatically cancel bookings that exceed payment deadline', 'mhm-rentiva') . '</label>';
    }

    /**
     * Notification Fields
     */
    public static function render_send_confirmation_emails_field(): void
    {
        $value = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_booking_send_confirmation_emails');
        echo '<label><input type="checkbox" name="mhm_rentiva_settings[mhm_rentiva_booking_send_confirmation_emails]" value="1"' . checked($value, '1', false) . '> ' . esc_html__('Send confirmation emails to customers when bookings are confirmed', 'mhm-rentiva') . '</label>';
    }

    public static function render_send_reminder_emails_field(): void
    {
        $value = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_booking_send_reminder_emails');
        echo '<label><input type="checkbox" name="mhm_rentiva_settings[mhm_rentiva_booking_send_reminder_emails]" value="1"' . checked($value, '1', false) . '> ' . esc_html__('Send reminder emails to customers before booking start time', 'mhm-rentiva') . '</label>';
    }

    public static function render_admin_notifications_field(): void
    {
        $value = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_booking_admin_notifications');
        echo '<label><input type="checkbox" name="mhm_rentiva_settings[mhm_rentiva_booking_admin_notifications]" value="1"' . checked($value, '1', false) . '> ' . esc_html__('Send email notifications to admin when new bookings are created', 'mhm-rentiva') . '</label>';
    }
}
