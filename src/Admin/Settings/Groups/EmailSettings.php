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

    /**
     * Get default settings for email
     *
     * @return array
     */
    public static function get_default_settings(): array
    {
        return [
            'mhm_rentiva_email_from_name'            => get_bloginfo('name'),
            'mhm_rentiva_email_from_address'         => get_option('admin_email'),
            'mhm_rentiva_email_reply_to'             => get_option('admin_email'),
            'mhm_rentiva_email_send_enabled'         => '1',
            'mhm_rentiva_email_test_mode'            => '0',
            'mhm_rentiva_email_test_address'         => get_option('admin_email'),
            'mhm_rentiva_email_template_path'        => 'mhm-rentiva/emails/',
            'mhm_rentiva_email_auto_send'            => '1',
            'mhm_rentiva_email_log_enabled'          => '1',
            'mhm_rentiva_email_log_retention_days'   => 30,
            'mhm_rentiva_email_booking_confirmation' => '1',
            'mhm_rentiva_email_booking_reminder'     => '1',
            'mhm_rentiva_email_booking_cancellation' => '1',
            'mhm_rentiva_email_reminder_hours'       => 24,
            'mhm_rentiva_booking_reminder_subject'   => __('Reminder: Your Booking #{booking_id} Starts Soon - {site_name}', 'mhm-rentiva'),
            'mhm_rentiva_booking_reminder_body'      => self::get_default_booking_reminder_body(),
            'mhm_rentiva_email_base_color'           => '#0073aa',
            'mhm_rentiva_email_header_image'         => '',
            'mhm_rentiva_email_footer_text'          => sprintf(esc_html__('%s - Powered by MHM Rentiva', 'mhm-rentiva'), get_bloginfo('name')),
            'mhm_rentiva_auto_cancel_email_subject'  => __('Booking Cancelled: #{order_id}', 'mhm-rentiva'),
            'mhm_rentiva_auto_cancel_email_content'  => self::get_default_auto_cancel_body(),

            // Refund Emails
            'mhm_rentiva_refund_customer_subject'    => __('Refund Processed for Booking #{booking_id}', 'mhm-rentiva'),
            'mhm_rentiva_refund_customer_body'       => self::get_default_refund_customer_body(),
            'mhm_rentiva_refund_admin_subject'       => __('Refund Processed: Booking #{booking_id}', 'mhm-rentiva'),
            'mhm_rentiva_refund_admin_body'          => self::get_default_refund_admin_body(),

            // Message Emails
            'mhm_rentiva_message_received_admin_subject' => __('New Message from {customer_name}', 'mhm-rentiva'),
            'mhm_rentiva_message_received_admin_body'    => self::get_default_message_admin_body(),
            'mhm_rentiva_message_replied_customer_subject' => __('New Reply for Booking #{booking_id}', 'mhm-rentiva'),
            'mhm_rentiva_message_replied_customer_body'    => self::get_default_message_customer_body(),

            // Admin Booking Alert
            'mhm_rentiva_booking_admin_subject'      => __('New Booking Alert: #{booking_id} - {site_name}', 'mhm-rentiva'),
            'mhm_rentiva_booking_admin_body'         => self::get_default_admin_notification_body(),

            // Booking Status Change (Customer)
            'mhm_rentiva_booking_status_subject'       => __('Booking #{booking_id} status updated', 'mhm-rentiva'),
            'mhm_rentiva_booking_status_body'          => self::get_default_booking_status_body(),

            // Admin Booking Status Change
            'mhm_rentiva_booking_status_admin_subject' => __('Admin Alert: Status Changed for Booking #{booking_id}', 'mhm-rentiva'),
            'mhm_rentiva_booking_status_admin_body'    => self::get_default_admin_status_change_body(),

            // Customer Booking Confirmation
            'mhm_rentiva_booking_created_subject'    => __('Booking Confirmed: #{booking_id}', 'mhm-rentiva'),
            'mhm_rentiva_booking_created_body'       => self::get_default_customer_confirmation_body(),

            // Welcome Email (One-time)
            'mhm_rentiva_welcome_email_body'         => self::get_default_welcome_email_body(),

            // Auto Cancel Email
            'mhm_rentiva_auto_cancel_email_content'  => self::get_default_auto_cancel_body(),

            // Manual Cancel Email
            'mhm_rentiva_booking_cancelled_body'     => self::get_default_booking_cancelled_body(),

            // Message Reply Customer Email
            'mhm_rentiva_message_replied_customer_body' => self::get_default_message_customer_body(),
        ];
    }

    /**
     * Get default body for Manual Cancellation email
     */
    public static function get_default_booking_cancelled_body(): string
    {
        return '
        <p>' . esc_html__('Dear {contact_name},', 'mhm-rentiva') . '</p>
        <p>' . esc_html__('Your booking #{booking_id} has been cancelled.', 'mhm-rentiva') . '</p>
        
        <table style="width: 100%; border-collapse: collapse; margin: 15px 0; background: #f8f9fa; border-radius: 8px;">
            <tr>
                <td style="padding: 10px 15px; border-bottom: 1px solid #e9ecef; color: #555;"><strong>' . esc_html__('Booking ID:', 'mhm-rentiva') . '</strong></td>
                <td style="padding: 10px 15px; border-bottom: 1px solid #e9ecef; text-align: right;">#{booking_id}</td>
            </tr>
            <tr>
                <td style="padding: 10px 15px; border-bottom: 1px solid #e9ecef; color: #555;"><strong>' . esc_html__('Vehicle:', 'mhm-rentiva') . '</strong></td>
                <td style="padding: 10px 15px; border-bottom: 1px solid #e9ecef; text-align: right;">{vehicle_title}</td>
            </tr>
            <tr>
                <td style="padding: 10px 15px; border-bottom: 1px solid #e9ecef; color: #555;"><strong>' . esc_html__('Pick-up:', 'mhm-rentiva') . '</strong></td>
                <td style="padding: 10px 15px; border-bottom: 1px solid #e9ecef; text-align: right;">{pickup_date}</td>
            </tr>
            <tr>
                <td style="padding: 10px 15px; border-bottom: 1px solid #e9ecef; color: #555;"><strong>' . esc_html__('Drop-off:', 'mhm-rentiva') . '</strong></td>
                <td style="padding: 10px 15px; border-bottom: 1px solid #e9ecef; text-align: right;">{dropoff_date}</td>
            </tr>
            <tr>
                <td style="padding: 10px 15px; color: #555;"><strong>' . esc_html__('Status:', 'mhm-rentiva') . '</strong></td>
                <td style="padding: 10px 15px; text-align: right; color: #dc3545; font-weight: bold;">' . esc_html__('Cancelled', 'mhm-rentiva') . '</td>
            </tr>
        </table>
        
        <div style="text-align: center; margin-top: 20px;">
            <a href="{site_url}" style="display: inline-block; background: #6c757d; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">' . esc_html__('Visit Website', 'mhm-rentiva') . '</a>
        </div>
        ';
    }

    // =========================================================================
    // CENTRALIZED DEFAULT EMAIL BODY TEMPLATES
    // =========================================================================

    /**
     * Admin Notification Email - Default Body
     * Used when new booking is received
     */
    public static function get_default_admin_notification_body(): string
    {
        return '<div style="background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 15px; border-radius: 6px; margin-bottom: 20px;">
    <strong>' . esc_html__('Attention:', 'mhm-rentiva') . '</strong> ' . esc_html__('A new booking request has been received. Please check from the admin panel.', 'mhm-rentiva') . '
</div>

<h3 style="color: #555; border-bottom: 2px solid #e3f2fd; padding-bottom: 10px; margin-bottom: 15px;">' . esc_html__('Customer Information', 'mhm-rentiva') . '</h3>
<table style="width: 100%; border-collapse: collapse; margin-bottom: 25px; background: #e3f2fd; border-radius: 8px;">
    <tr>
        <td style="padding: 12px 15px; border-bottom: 1px solid #bbdefb; color: #1565c0; width: 35%;"><strong>' . esc_html__('Name:', 'mhm-rentiva') . '</strong></td>
        <td style="padding: 12px 15px; border-bottom: 1px solid #bbdefb; text-align: right;">{contact_name}</td>
    </tr>
    <tr>
        <td style="padding: 12px 15px; border-bottom: 1px solid #bbdefb; color: #1565c0;"><strong>' . esc_html__('Email:', 'mhm-rentiva') . '</strong></td>
        <td style="padding: 12px 15px; border-bottom: 1px solid #bbdefb; text-align: right;">{contact_email}</td>
    </tr>
    <tr>
        <td style="padding: 12px 15px; color: #1565c0;"><strong>' . esc_html__('Phone:', 'mhm-rentiva') . '</strong></td>
        <td style="padding: 12px 15px; text-align: right;">{contact_phone}</td>
    </tr>
</table>

<h3 style="color: #555; border-bottom: 2px solid #f8f9fa; padding-bottom: 10px; margin-bottom: 15px;">' . esc_html__('Booking Details', 'mhm-rentiva') . '</h3>
<table style="width: 100%; border-collapse: collapse; margin-bottom: 25px; background: #f8f9fa; border-radius: 8px;">
    <tr>
        <td style="padding: 12px 15px; border-bottom: 1px solid #e9ecef; color: #555; width: 35%;"><strong>' . esc_html__('Booking ID:', 'mhm-rentiva') . '</strong></td>
        <td style="padding: 12px 15px; border-bottom: 1px solid #e9ecef; text-align: right;">#{booking_id}</td>
    </tr>
    <tr>
        <td style="padding: 12px 15px; border-bottom: 1px solid #e9ecef; color: #555;"><strong>' . esc_html__('Vehicle:', 'mhm-rentiva') . '</strong></td>
        <td style="padding: 12px 15px; border-bottom: 1px solid #e9ecef; text-align: right;">{vehicle_title}</td>
    </tr>
    <tr>
        <td style="padding: 12px 15px; border-bottom: 1px solid #e9ecef; color: #555;"><strong>' . esc_html__('Pickup Date:', 'mhm-rentiva') . '</strong></td>
        <td style="padding: 12px 15px; border-bottom: 1px solid #e9ecef; text-align: right;">{pickup_date}</td>
    </tr>
    <tr>
        <td style="padding: 12px 15px; border-bottom: 1px solid #e9ecef; color: #555;"><strong>' . esc_html__('Return Date:', 'mhm-rentiva') . '</strong></td>
        <td style="padding: 12px 15px; border-bottom: 1px solid #e9ecef; text-align: right;">{dropoff_date}</td>
    </tr>
    <tr>
        <td style="padding: 12px 15px; color: #555;"><strong>' . esc_html__('Total Amount:', 'mhm-rentiva') . '</strong></td>
        <td style="padding: 12px 15px; text-align: right; color: #28a745; font-weight: bold;">{total_price}</td>
    </tr>
</table>

<div style="text-align: center;">
    <a href="{admin_booking_url}" style="display: inline-block; background: #007bff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; margin: 20px 0;">' . esc_html__('Manage Booking', 'mhm-rentiva') . '</a>
</div>';
    }

    /**
     * Customer Confirmation Email - Default Body
     */
    public static function get_default_customer_confirmation_body(): string
    {
        return '
    <p>' . esc_html__('Dear {contact_name},', 'mhm-rentiva') . '</p>
    <p>' . esc_html__('Your booking #{booking_id} has been successfully created. We are waiting for your payment to confirm the reservation.', 'mhm-rentiva') . '</p>

    <h3 style="color: #555; border-bottom: 2px solid #f8f9fa; padding-bottom: 10px; margin-bottom: 15px;">' . esc_html__('Reservation Details', 'mhm-rentiva') . '</h3>
    
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 25px; background: #f8f9fa; border-radius: 8px;">
        <tr>
            <td style="padding: 12px 15px; border-bottom: 1px solid #e9ecef; color: #555; width: 35%;"><strong>' . esc_html__('Booking Number:', 'mhm-rentiva') . '</strong></td>
            <td style="padding: 12px 15px; border-bottom: 1px solid #e9ecef; text-align: right;">#{booking_id}</td>
        </tr>
        <tr>
            <td style="padding: 12px 15px; border-bottom: 1px solid #e9ecef; color: #555;"><strong>' . esc_html__('Vehicle:', 'mhm-rentiva') . '</strong></td>
            <td style="padding: 12px 15px; border-bottom: 1px solid #e9ecef; text-align: right;">{vehicle_title}</td>
        </tr>
        <tr>
            <td style="padding: 12px 15px; border-bottom: 1px solid #e9ecef; color: #555;"><strong>' . esc_html__('Pick-up:', 'mhm-rentiva') . '</strong></td>
            <td style="padding: 12px 15px; border-bottom: 1px solid #e9ecef; text-align: right;">{pickup_date}</td>
        </tr>
        <tr>
            <td style="padding: 12px 15px; border-bottom: 1px solid #e9ecef; color: #555;"><strong>' . esc_html__('Drop-off:', 'mhm-rentiva') . '</strong></td>
            <td style="padding: 12px 15px; border-bottom: 1px solid #e9ecef; text-align: right;">{dropoff_date}</td>
        </tr>
        <tr>
            <td style="padding: 12px 15px; color: #555;"><strong>' . esc_html__('Total Amount:', 'mhm-rentiva') . '</strong></td>
            <td style="padding: 12px 15px; text-align: right; color: #28a745; font-weight: bold; font-size: 16px;">{total_price}</td>
        </tr>
    </table>

    <div style="text-align: center; margin-top: 30px;">
        <a href="{my_account_url}" target="_blank" style="display: inline-block; background: #007bff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold;">
            ' . esc_html__('Go to My Account', 'mhm-rentiva') . '
        </a>
    </div>
    
    <p style="margin-top: 30px; font-size: 12px; color: #999; text-align: center;">' . esc_html__('Thank you for choosing us.', 'mhm-rentiva') . '</p>
    ';
    }

    /**
     * Welcome Email - Default Body
     */
    public static function get_default_welcome_email_body(): string
    {
        return '<p>' . esc_html__('Dear {contact_name},', 'mhm-rentiva') . '</p>
<p>' . esc_html__('Welcome to {site_name}! We are thrilled to have you with us.', 'mhm-rentiva') . '</p>
<p>' . esc_html__('You can manage your bookings and profile by visiting your account.', 'mhm-rentiva') . '</p>
<p style="text-align: center; margin-top: 30px;">
    <a href="{site_url}" target="_blank" style="background: #007bff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold;">' . esc_html__('Go to My Account', 'mhm-rentiva') . '</a>
</p>';
    }

    /**
     * Booking Status Change - Default Body
     */
    public static function get_default_booking_status_body(): string
    {
        return '<p>' . esc_html__('Dear {contact_name},', 'mhm-rentiva') . '</p>
<p>' . esc_html__('The status of your booking #{booking_id} has been updated.', 'mhm-rentiva') . '</p>

<table style="width: 100%; border-collapse: collapse; margin-bottom: 25px; background: #f8f9fa; border-radius: 8px;">
    <tr>
        <td style="padding: 12px 15px; border-bottom: 1px solid #e9ecef; color: #555; width: 35%;"><strong>' . esc_html__('New Status:', 'mhm-rentiva') . '</strong></td>
        <td style="padding: 12px 15px; border-bottom: 1px solid #e9ecef; text-align: right; color: #007bff; font-weight: bold;">{status}</td>
    </tr>
    <tr>
        <td style="padding: 12px 15px; border-bottom: 1px solid #e9ecef; color: #555;"><strong>' . esc_html__('Vehicle:', 'mhm-rentiva') . '</strong></td>
        <td style="padding: 12px 15px; border-bottom: 1px solid #e9ecef; text-align: right;">{vehicle_title}</td>
    </tr>
    <tr>
        <td style="padding: 12px 15px; border-bottom: 1px solid #e9ecef; color: #555;"><strong>' . esc_html__('Pickup Date:', 'mhm-rentiva') . '</strong></td>
        <td style="padding: 12px 15px; border-bottom: 1px solid #e9ecef; text-align: right;">{pickup_date}</td>
    </tr>
    <tr>
        <td style="padding: 12px 15px; color: #555;"><strong>' . esc_html__('Return Date:', 'mhm-rentiva') . '</strong></td>
        <td style="padding: 12px 15px; text-align: right;">{dropoff_date}</td>
    </tr>
</table>

<div style="text-align: center; margin-top: 30px;">
    <a href="{my_account_url}" target="_blank" style="display: inline-block; background: #007bff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold;">
        ' . esc_html__('View Booking', 'mhm-rentiva') . '
    </a>
</div>

<p style="margin-top: 30px; font-size: 12px; color: #999; text-align: center;">' . esc_html__('If you have any questions, please contact us.', 'mhm-rentiva') . '</p>';
    }




    /**
     * Auto Cancel Email - Default Body
     */
    public static function get_default_auto_cancel_body(): string
    {
        return '<p>' . esc_html__('Dear {contact_name},', 'mhm-rentiva') . '</p>
<p>' . esc_html__('We regret to inform you that your booking has been cancelled due to payment timeout.', 'mhm-rentiva') . '</p>

<table style="width: 100%; border-collapse: collapse; margin: 15px 0; background: #f8f9fa; border-radius: 8px;">
    <tr>
        <td style="padding: 10px 15px; border-bottom: 1px solid #e9ecef;"><strong>' . esc_html__('Booking ID:', 'mhm-rentiva') . '</strong></td>
        <td style="padding: 10px 15px; border-bottom: 1px solid #e9ecef; text-align: right;">#{booking_id}</td>
    </tr>
    <tr>
        <td style="padding: 10px 15px; border-bottom: 1px solid #e9ecef;"><strong>' . esc_html__('Status:', 'mhm-rentiva') . '</strong></td>
        <td style="padding: 10px 15px; border-bottom: 1px solid #e9ecef; text-align: right; color: #dc3545; font-weight: bold;">' . esc_html__('Cancelled', 'mhm-rentiva') . '</td>
    </tr>
    <tr>
        <td style="padding: 10px 15px;"><strong>' . esc_html__('Reason:', 'mhm-rentiva') . '</strong></td>
        <td style="padding: 10px 15px; text-align: right;">' . esc_html__('Payment Timeout', 'mhm-rentiva') . '</td>
    </tr>
</table>

<p>' . esc_html__('If you believe this is an error, please contact us immediately.', 'mhm-rentiva') . '</p>
<p style="text-align: center; margin-top: 20px;">
    <a href="{site_url}" style="background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">' . esc_html__('Contact Us', 'mhm-rentiva') . '</a>
</p>';
    }

    /**
     * Refund Customer Email - Default Body
     */
    public static function get_default_refund_customer_body(): string
    {
        return '<p>' . esc_html__('Dear {contact_name},', 'mhm-rentiva') . '</p>
<p>' . esc_html__('We have processed a refund for your booking.', 'mhm-rentiva') . '</p>
<table style="width: 100%; border-collapse: collapse; margin: 15px 0; background: #f8f9fa; border-radius: 8px;">
    <tr>
        <td style="padding: 10px 15px; border-bottom: 1px solid #e9ecef;"><strong>' . esc_html__('Booking ID:', 'mhm-rentiva') . '</strong></td>
        <td style="padding: 10px 15px; border-bottom: 1px solid #e9ecef; text-align: right;">#{booking_id}</td>
    </tr>
    <tr>
        <td style="padding: 10px 15px; border-bottom: 1px solid #e9ecef;"><strong>' . esc_html__('Refund Amount:', 'mhm-rentiva') . '</strong></td>
        <td style="padding: 10px 15px; border-bottom: 1px solid #e9ecef; text-align: right; font-weight: bold; color: #dc3545;">{amount}</td>
    </tr>
    <tr>
        <td style="padding: 10px 15px;"><strong>' . esc_html__('Refund Status:', 'mhm-rentiva') . '</strong></td>
        <td style="padding: 10px 15px; text-align: right;">{status}</td>
    </tr>
</table>
<p>' . esc_html__('The amount will appear in your account shortly.', 'mhm-rentiva') . '</p>
<p style="text-align: center; margin-top: 20px;">
    <a href="{my_account_url}" style="background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">' . esc_html__('View Booking', 'mhm-rentiva') . '</a>
</p>';
    }

    /**
     * Refund Admin Email - Default Body
     */
    public static function get_default_refund_admin_body(): string
    {
        return '<p><strong>' . esc_html__('Refund Notification', 'mhm-rentiva') . '</strong></p>
<p>' . esc_html__('A refund of {amount} has been processed for booking #{booking_id}.', 'mhm-rentiva') . '</p>
<table style="width: 100%; border-collapse: collapse; margin: 15px 0; background: #f8f9fa; border-radius: 8px;">
    <tr>
        <td style="padding: 10px 15px; border-bottom: 1px solid #e9ecef;"><strong>' . esc_html__('Customer:', 'mhm-rentiva') . '</strong></td>
        <td style="padding: 10px 15px; border-bottom: 1px solid #e9ecef; text-align: right;">{customer_name}</td>
    </tr>
    <tr>
        <td style="padding: 10px 15px;"><strong>' . esc_html__('Refund Amount:', 'mhm-rentiva') . '</strong></td>
        <td style="padding: 10px 15px; text-align: right; color: #dc3545; font-weight: bold;">{amount}</td>
    </tr>
</table>';
    }

    /**
     * Admin Booking Status Change - Default Body
     */
    public static function get_default_admin_status_change_body(): string
    {
        return '<p><strong>' . esc_html__('Booking Status Update', 'mhm-rentiva') . '</strong></p>
<p>' . esc_html__('The status of booking #{booking_id} has been changed.', 'mhm-rentiva') . '</p>
<table style="width: 100%; border-collapse: collapse; margin: 15px 0; background: #f8f9fa; border-radius: 8px;">
    <tr>
        <td style="padding: 10px 15px; border-bottom: 1px solid #e9ecef;"><strong>' . esc_html__('Old Status:', 'mhm-rentiva') . '</strong></td>
        <td style="padding: 10px 15px; border-bottom: 1px solid #e9ecef; text-align: right; color: #6c757d;">{old_status}</td>
    </tr>
    <tr>
        <td style="padding: 10px 15px;"><strong>' . esc_html__('New Status:', 'mhm-rentiva') . '</strong></td>
        <td style="padding: 10px 15px; text-align: right; font-weight: bold; color: #007bff;">{new_status}</td>
    </tr>
</table>
<p style="text-align: center; margin-top: 20px;">
    <a href="{admin_booking_url}" style="background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">' . esc_html__('View Booking', 'mhm-rentiva') . '</a>
</p>';
    }

    /**
     * Booking Reminder - Default Body (Gold Standard)
     */
    public static function get_default_booking_reminder_body(): string
    {
        return '<p>' . esc_html__('Dear {contact_name},', 'mhm-rentiva') . '</p>
<p>' . esc_html__('This is a friendly reminder regarding your upcoming booking.', 'mhm-rentiva') . '</p>
<table style="width: 100%; border-collapse: collapse; margin: 15px 0; background: #f8f9fa; border-radius: 8px;">
    <tr>
        <td style="padding: 10px 15px; border-bottom: 1px solid #e9ecef;"><strong>' . esc_html__('Booking ID:', 'mhm-rentiva') . '</strong></td>
        <td style="padding: 10px 15px; border-bottom: 1px solid #e9ecef; text-align: right;">#{booking_id}</td>
    </tr>
    <tr>
        <td style="padding: 10px 15px; border-bottom: 1px solid #e9ecef;"><strong>' . esc_html__('Vehicle:', 'mhm-rentiva') . '</strong></td>
        <td style="padding: 10px 15px; border-bottom: 1px solid #e9ecef; text-align: right;">{vehicle_title}</td>
    </tr>
    <tr>
        <td style="padding: 10px 15px; border-bottom: 1px solid #e9ecef;"><strong>' . esc_html__('Pickup Date:', 'mhm-rentiva') . '</strong></td>
        <td style="padding: 10px 15px; border-bottom: 1px solid #e9ecef; text-align: right;">{pickup_date}</td>
    </tr>
    <tr>
        <td style="padding: 10px 15px;"><strong>' . esc_html__('Return Date:', 'mhm-rentiva') . '</strong></td>
        <td style="padding: 10px 15px; text-align: right;">{dropoff_date}</td>
    </tr>
</table>
<p>' . esc_html__('We look forward to seeing you.', 'mhm-rentiva') . '</p>
<p style="text-align: center; margin-top: 20px;">
    <a href="{my_account_url}" style="background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">' . esc_html__('View Booking', 'mhm-rentiva') . '</a>
</p>';
    }

    /**
     * Message Received Admin - Default Body
     */
    public static function get_default_message_admin_body(): string
    {
        return '<p><strong>' . esc_html__('New Message Received', 'mhm-rentiva') . '</strong></p>
<p>' . esc_html__('You have received a new message regarding booking #{booking_id}.', 'mhm-rentiva') . '</p>
<table style="width: 100%; border-collapse: collapse; margin: 15px 0; background: #e3f2fd; border-radius: 8px;">
    <tr>
        <td style="padding: 10px 15px; border-bottom: 1px solid #bbdefb; width: 30%; white-space: nowrap;"><strong>' . esc_html__('From:', 'mhm-rentiva') . '</strong></td>
        <td style="padding: 10px 15px; border-bottom: 1px solid #bbdefb; text-align: right;">{customer_name}</td>
    </tr>
    <tr>
        <td style="padding: 10px 15px; vertical-align: top;"><strong>' . esc_html__('Message:', 'mhm-rentiva') . '</strong></td>
        <td style="padding: 10px 15px; text-align: left; vertical-align: top;">{message_body}</td>
    </tr>
</table>';
    }

    /**
     * Message Reply Customer - Default Body
     */
    public static function get_default_message_customer_body(): string
    {
        return '<p>' . esc_html__('Dear {customer_name},', 'mhm-rentiva') . '</p>
<p>' . esc_html__('You have received a new reply regarding your booking #{booking_id}.', 'mhm-rentiva') . '</p>

<div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 5px solid #007bff; font-style: italic; color: #555;">
    {reply_body}
</div>

<p>' . esc_html__('You can reply to this message directly from your account dashboard.', 'mhm-rentiva') . '</p>

<div style="text-align: center; margin-top: 30px;">
    <a href="{my_account_url}" target="_blank" style="display: inline-block; background: #007bff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold;">
        ' . esc_html__('Go to My Account', 'mhm-rentiva') . '
    </a>
</div>

<p style="margin-top: 30px; font-size: 12px; color: #999; text-align: center;">' . esc_html__('Thank you for choosing us.', 'mhm-rentiva') . '</p>';
    }

    public static function register(): void
    {
        add_settings_section(
            self::SECTION_ID,
            __('Email Settings', 'mhm-rentiva'),
            [self::class, 'render_section_description'],
            'mhm_rentiva_settings'
        );

        // if (class_exists('WooCommerce')) {
        //    return;
        // }

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
            'mhm_rentiva_email_base_color',
            __('Base Color', 'mhm-rentiva'),
            [self::class, 'render_base_color_field'],
            'mhm_rentiva_settings',
            self::SECTION_ID
        );

        add_settings_field(
            'mhm_rentiva_email_header_image',
            __('Header Image URL', 'mhm-rentiva'),
            [self::class, 'render_header_image_field'],
            'mhm_rentiva_settings',
            self::SECTION_ID
        );

        add_settings_field(
            'mhm_rentiva_email_footer_text',
            __('Footer Text', 'mhm-rentiva'),
            [self::class, 'render_footer_text_field'],
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
            'mhm_rentiva_email_log_retention_days',
            'mhm_rentiva_auto_cancel_email_subject',
            'mhm_rentiva_auto_cancel_email_content',
            // Branding settings (CRITICAL)
            'mhm_rentiva_email_base_color',
            'mhm_rentiva_email_header_image',
            'mhm_rentiva_email_footer_text',
            // New templates
            'mhm_rentiva_refund_customer_subject',
            'mhm_rentiva_refund_customer_body',
            'mhm_rentiva_refund_admin_subject',
            'mhm_rentiva_refund_admin_body',
            'mhm_rentiva_message_received_admin_subject',
            'mhm_rentiva_message_received_admin_body',
            'mhm_rentiva_message_replied_customer_subject',
            'mhm_rentiva_message_replied_customer_body',
            'mhm_rentiva_booking_admin_subject',
            'mhm_rentiva_booking_admin_body',
            'mhm_rentiva_booking_status_admin_subject',
            'mhm_rentiva_booking_status_admin_body',
            'mhm_rentiva_booking_reminder_subject',
            'mhm_rentiva_booking_reminder_body',
        ];

        foreach ($settings as $setting) {
            $sanitize_callback = 'sanitize_text_field';
            if ($setting === 'mhm_rentiva_email_log_retention_days') {
                $sanitize_callback = 'absint';
            } elseif (in_array($setting, ['mhm_rentiva_email_from_address', 'mhm_rentiva_email_reply_to', 'mhm_rentiva_email_test_address'])) {
                // ✅ Use safe email sanitizer to prevent strlen() errors
                $sanitize_callback = [\MHMRentiva\Admin\Settings\Core\SettingsHelper::class, 'sanitize_email_safe'];
            } elseif (strpos($setting, '_body') !== false || strpos($setting, '_content') !== false) {
                // HTML content fields
                $sanitize_callback = 'wp_kses_post';
            }
            register_setting('mhm_rentiva_settings', $setting, ['sanitize_callback' => $sanitize_callback]);
        }
    }

    public static function render_section_description(): void
    {
        if (class_exists('WooCommerce')) {
            echo '<div class="notice notice-info inline" style="margin: 10px 0;">';
            echo '<p><strong>' . esc_html__('WooCommerce Active:', 'mhm-rentiva') . '</strong> ';
            echo esc_html__('WooCommerce manages transactional emails. The settings below apply to MHM Rentiva internal notifications (e.g. Messages, Staff Alerts).', 'mhm-rentiva');
            echo '</p></div>';
            // Allow processing to continue
        } else {
            echo '<p>' . esc_html__('Configure email sending and template settings.', 'mhm-rentiva') . '</p>';
        }

        echo '<div class="notice notice-info inline" style="margin: 10px 0;">';
        echo '<p><strong>' . esc_html__('Note:', 'mhm-rentiva') . '</strong> ';
        echo esc_html__('These settings affect all email sending. In test mode, emails are only sent to the test address.', 'mhm-rentiva');
        echo '</p></div>';

        // Link to email templates
        // The "Edit Email Templates" button is removed as it checks now have its own tab "Notification Templates"
        // $email_templates_url = admin_url('admin.php?page=mhm-rentiva-settings&tab=email-templates');
        // echo '<div class="notice notice-info inline" style="margin: 10px 0;">';
        // echo '<p><strong>' . esc_html__('Email Contents:', 'mhm-rentiva') . '</strong> ';
        // echo '<a href="' . esc_url($email_templates_url) . '" class="button button-secondary" style="margin-left: 10px;">';
        // echo esc_html__('Edit Email Templates', 'mhm-rentiva') . '</a>';
        // echo '</p></div>';

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
        $value = esc_attr(self::get_from_address());
        echo '<input type="email" name="mhm_rentiva_settings[mhm_rentiva_email_from_address]" value="' . $value . '" class="regular-text">';
        echo '<p class="description">' . esc_html__('Email address to send from. Defaults to WordPress admin email.', 'mhm-rentiva') . '</p>';
    }

    public static function render_base_color_field(): void
    {
        $value = esc_attr(self::get_base_color());
        echo '<input type="color" name="mhm_rentiva_settings[mhm_rentiva_email_base_color]" value="' . $value . '" style="height:30px; width:60px; padding:0; border:none;">';
        echo '<p class="description">' . esc_html__('Main color for email header and accents.', 'mhm-rentiva') . '</p>';
    }

    public static function render_header_image_field(): void
    {
        $value = esc_attr(self::get_header_image());
        echo '<input type="url" name="mhm_rentiva_settings[mhm_rentiva_email_header_image]" value="' . $value . '" class="regular-text placeholder" placeholder="https://...">';
        echo '<p class="description">' . esc_html__('URL of the logo image to display in the header.', 'mhm-rentiva') . '</p>';
    }

    public static function render_footer_text_field(): void
    {
        $value = esc_textarea(self::get_footer_text());
        echo '<textarea name="mhm_rentiva_settings[mhm_rentiva_email_footer_text]" class="large-text" rows="2">' . $value . '</textarea>';
        echo '<p class="description">' . esc_html__('Text to display in the email footer.', 'mhm-rentiva') . '</p>';
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
        return SettingsCore::get('mhm_rentiva_email_from_address', get_option('admin_email'));
    }

    public static function get_base_color(): string
    {
        return SettingsCore::get('mhm_rentiva_email_base_color', '#667eea');
    }

    public static function get_header_image(): string
    {
        return SettingsCore::get('mhm_rentiva_email_header_image', '');
    }

    public static function get_footer_text(): string
    {
        return SettingsCore::get('mhm_rentiva_email_footer_text', get_bloginfo('name'));
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
