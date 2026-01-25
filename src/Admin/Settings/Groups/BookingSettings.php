<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Settings\Groups;

use MHMRentiva\Admin\Settings\Core\SettingsCore;
use MHMRentiva\Admin\Settings\Core\SettingsHelper;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Booking Management Settings
 * 
 * Only includes actively used settings.
 * Refactored for modularity and high performance.
 * 
 * @since 4.0.0
 */
final class BookingSettings
{
    public const SECTION_BASIC = 'mhm_rentiva_booking_basic_section';
    public const SECTION_TIME = 'mhm_rentiva_booking_time_section';
    public const SECTION_NOTIFICATION = 'mhm_rentiva_booking_notification_section';

    /**
     * Get default settings
     * 
     * @return array
     */
    public static function get_default_settings(): array
    {
        return [
            'mhm_rentiva_booking_cancellation_deadline_hours' => 24,
            'mhm_rentiva_booking_payment_deadline_minutes' => 30,
            'mhm_rentiva_booking_auto_cancel_enabled' => '1',
            'mhm_rentiva_booking_send_confirmation_emails' => '1',
            'mhm_rentiva_booking_send_reminder_emails' => '1',
            'mhm_rentiva_booking_admin_notifications' => '1',
            'mhm_rentiva_send_auto_cancel_email' => '0',
            'mhm_rentiva_default_rental_days' => 1,
            'mhm_rentiva_booking_buffer_time' => 60,
        ];
    }

    /**
     * Render the booking settings section
     */
    public static function render_settings_section(): void
    {
        if (class_exists('\MHMRentiva\Admin\Settings\View\SettingsViewHelper')) {
            \MHMRentiva\Admin\Settings\View\SettingsViewHelper::render_section_cleanly(self::SECTION_BASIC);
            \MHMRentiva\Admin\Settings\View\SettingsViewHelper::render_section_cleanly(self::SECTION_TIME);
            \MHMRentiva\Admin\Settings\View\SettingsViewHelper::render_section_cleanly(self::SECTION_NOTIFICATION);
        }
    }

    /**
     * Register settings
     */
    public static function register(): void
    {
        $page_slug = SettingsCore::PAGE;

        // 1. Basic Booking Settings Section
        add_settings_section(
            self::SECTION_BASIC,
            __('Basic Booking Settings', 'mhm-rentiva'),
            fn() => print('<p class="description">' . esc_html__('General booking configuration and data retention settings.', 'mhm-rentiva') . '</p>'),
            $page_slug
        );

        SettingsHelper::number_field(
            $page_slug,
            'mhm_rentiva_default_rental_days',
            __('Default Rental Days', 'mhm-rentiva'),
            1,
            365,
            __('Default rental duration in booking form (days).', 'mhm-rentiva'),
            self::SECTION_BASIC
        );

        // 2. Time Management Settings Section
        add_settings_section(
            self::SECTION_TIME,
            __('Time Management Settings', 'mhm-rentiva'),
            fn() => print('<p class="description">' . esc_html__('Configure timing rules for bookings, cancellations and payments.', 'mhm-rentiva') . '</p>'),
            $page_slug
        );

        SettingsHelper::number_field(
            $page_slug,
            'mhm_rentiva_booking_cancellation_deadline_hours',
            __('Cancellation Deadline (Hours)', 'mhm-rentiva'),
            1,
            168,
            __('Hours before booking start that customers can cancel.', 'mhm-rentiva'),
            self::SECTION_TIME
        );

        SettingsHelper::number_field(
            $page_slug,
            'mhm_rentiva_booking_payment_deadline_minutes',
            __('Payment Deadline (Minutes)', 'mhm-rentiva'),
            0,
            1440,
            __('Minutes allowed for payment completion. Set to 0 to disable deadline.', 'mhm-rentiva'),
            self::SECTION_TIME
        );

        SettingsHelper::checkbox_field(
            $page_slug,
            'mhm_rentiva_booking_auto_cancel_enabled',
            __('Enable Auto Cancel', 'mhm-rentiva'),
            __('Automatically cancel bookings that exceed payment deadline', 'mhm-rentiva'),
            self::SECTION_TIME
        );

        SettingsHelper::number_field(
            $page_slug,
            'mhm_rentiva_booking_buffer_time',
            __('Buffer Time (Minutes)', 'mhm-rentiva'),
            0,
            1440,
            __('Minimum time gap required between bookings for cleaning and preparation.', 'mhm-rentiva'),
            self::SECTION_TIME
        );

        // 3. Notification Settings Section
        add_settings_section(
            self::SECTION_NOTIFICATION,
            __('Notification Settings', 'mhm-rentiva'),
            fn() => print('<p class="description">' . esc_html__('Email notification settings for bookings.', 'mhm-rentiva') . '</p>'),
            $page_slug
        );

        SettingsHelper::checkbox_field(
            $page_slug,
            'mhm_rentiva_booking_send_confirmation_emails',
            __('Send Confirmation Emails', 'mhm-rentiva'),
            __('Send confirmation emails to customers when bookings are confirmed', 'mhm-rentiva'),
            self::SECTION_NOTIFICATION
        );

        SettingsHelper::checkbox_field(
            $page_slug,
            'mhm_rentiva_booking_send_reminder_emails',
            __('Send Reminder Emails', 'mhm-rentiva'),
            __('Send reminder emails to customers before booking start time', 'mhm-rentiva'),
            self::SECTION_NOTIFICATION
        );

        SettingsHelper::checkbox_field(
            $page_slug,
            'mhm_rentiva_booking_admin_notifications',
            __('Admin Notifications', 'mhm-rentiva'),
            __('Send email notifications to admin when new bookings are created', 'mhm-rentiva'),
            self::SECTION_NOTIFICATION
        );

        SettingsHelper::checkbox_field(
            $page_slug,
            'mhm_rentiva_send_auto_cancel_email',
            __('Send Auto Cancel Email', 'mhm-rentiva'),
            __('Send notification to customer when booking is auto-cancelled due to timeout.', 'mhm-rentiva'),
            self::SECTION_NOTIFICATION
        );
    }
}
