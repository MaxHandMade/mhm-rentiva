<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Emails\Notifications;

use MHMRentiva\Admin\Emails\Core\Mailer;
use MHMRentiva\Admin\Settings\Core\SettingsCore;
use MHMRentiva\Admin\Settings\Groups\EmailSettings;
use MHMRentiva\Admin\Core\Utilities\BookingQueryHelper;

if (!defined('ABSPATH')) {
    exit;
}

final class BookingNotifications
{
    public static function register(): void
    {
        add_action('mhm_rentiva_booking_created', [self::class, 'on_created']);
        add_action('mhm_rentiva_booking_status_changed', [self::class, 'on_status_changed'], 10, 3);
    }

    /**
     * Send email when new booking is created
     * 
     * @param int $booking_id Booking ID
     */
    public static function on_created(int $booking_id): void
    {
        // Respect global auto-send setting
        if (!EmailSettings::is_auto_send_enabled()) {
            return;
        }
        self::send_new_booking_emails($booking_id);
    }

    /**
     * Send email when booking status changes
     * 
     * @param int $booking_id Booking ID
     * @param string $old_status Old status
     * @param string $new_status New status
     */
    public static function on_status_changed(int $booking_id, string $old_status, string $new_status): void
    {
        // Respect global auto-send setting
        if (!EmailSettings::is_auto_send_enabled()) {
            return;
        }
        self::send_status_change_emails($booking_id, $old_status, $new_status);
    }

    /**
     * Send new booking emails
     * 
     * @param int $booking_id Booking ID
     */
    private static function send_new_booking_emails(int $booking_id): void
    {
        // Customer email (confirmation)
        $send_customer = SettingsCore::get('mhm_rentiva_booking_send_confirmation_emails', '1') === '1';
        if ($send_customer) {
            Mailer::sendBookingEmail('booking_created_customer', $booking_id, 'customer');
        }

        // Welcome email (send once per user/email)
        self::send_welcome_if_applicable($booking_id);

        // Admin email (notifications)
        $send_admin = SettingsCore::get('mhm_rentiva_booking_admin_notifications', '1') === '1';
        if ($send_admin) {
            Mailer::sendBookingEmail('booking_created_admin', $booking_id, 'admin');
        }
    }

    /**
     * Send welcome email once per customer (based on WP user or email)
     */
    private static function send_welcome_if_applicable(int $booking_id): void
    {
        // Optional global toggle; default on
        $enabled = SettingsCore::get('mhm_rentiva_customer_welcome_email', '1') === '1';
        if (!$enabled) {
            return;
        }

        $customer = BookingQueryHelper::getBookingCustomerInfo($booking_id);
        $email = isset($customer['email']) ? (string) $customer['email'] : '';
        if (empty($email) || !is_email($email)) {
            return;
        }

        $user = get_user_by('email', $email);
        if ($user) {
            if (get_user_meta($user->ID, '_mhm_rentiva_welcome_sent', true) === '1') {
                return;
            }
            Mailer::sendBookingEmail('welcome_customer', $booking_id, 'customer');
            update_user_meta($user->ID, '_mhm_rentiva_welcome_sent', '1');
            return;
        }

        // Fallback: avoid duplicates for guests within 30 days
        $tkey = 'mhm_welcome_sent_' . md5(strtolower($email));
        if (get_transient($tkey)) {
            return;
        }
        Mailer::sendBookingEmail('welcome_customer', $booking_id, 'customer');
        set_transient($tkey, 1, 30 * DAY_IN_SECONDS);
    }

    /**
     * Send status change emails
     * 
     * @param int $booking_id Booking ID
     * @param string $old_status Old status
     * @param string $new_status New status
     */
    private static function send_status_change_emails(int $booking_id, string $old_status, string $new_status): void
    {
        $additional_context = [
            'status_change' => [
                'old_status' => $old_status,
                'new_status' => $new_status,
                'old_status_label' => self::get_status_label($old_status),
                'new_status_label' => self::get_status_label($new_status),
            ]
        ];

        $send_customer = SettingsCore::get('mhm_rentiva_booking_send_confirmation_emails', '1') === '1';
        if ($send_customer) {
            Mailer::sendBookingEmail('booking_status_changed_customer', $booking_id, 'customer', $additional_context);
        }

        $send_admin = SettingsCore::get('mhm_rentiva_booking_admin_notifications', '1') === '1';
        if ($send_admin) {
            Mailer::sendBookingEmail('booking_status_changed_admin', $booking_id, 'admin', $additional_context);
        }
    }

    /**
     * Get status label
     * 
     * @param string $status Status
     * @return string Status label
     */
    private static function get_status_label(string $status): string
    {
        $labels = [
            'pending' => __('Pending', 'mhm-rentiva'),
            'confirmed' => __('Confirmed', 'mhm-rentiva'),
            'in_progress' => __('In Progress', 'mhm-rentiva'),
            'completed' => __('Completed', 'mhm-rentiva'),
            'cancelled' => __('Cancelled', 'mhm-rentiva'),
            'pending_payment' => __('Pending Payment', 'mhm-rentiva'),
        ];

        return $labels[$status] ?? $status;
    }
}
