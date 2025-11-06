<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Emails\Templates;

use MHMRentiva\Admin\Emails\Core\EmailFormRenderer;

if (!defined('ABSPATH')) {
    exit;
}

final class BookingNotifications
{
    public static function register(): void
    {
        // BookingNotifications class only uses render method, no register needed
    }

    public static function render(): void
    {
        echo '<h2>' . esc_html__('Booking Notifications', 'mhm-rentiva') . '</h2>';
        echo '<p class="description">' . esc_html__('Configure email notifications for booking creation and status changes.', 'mhm-rentiva') . '</p>';
        echo '<p class="description">' . esc_html__('Available placeholders: {booking_id}, {vehicle_title}, {pickup_date}, {dropoff_date}, {total_price}, {contact_name}, {contact_email}, {status}, {site_name}, {site_url}', 'mhm-rentiva') . '</p>';
        echo '<p class="description">' . esc_html__('Allowed HTML tags in Content (HTML): a, b, strong, em, i, u, p, br, span(style), ul, ol, li, h1, h2, h3, table(tr, td, th), img(src, alt, width, height, style).', 'mhm-rentiva') . '</p>';

        // Quick Test moved to Email Preview tab for better UX

        // New Booking Email
        $booking_created_fields = [
            [
                'type' => 'checkbox',
                'name' => 'mhm_rentiva_booking_created_enabled',
                'label' => __('Enabled', 'mhm-rentiva'),
                'value' => EmailFormRenderer::get_option('mhm_rentiva_booking_created_enabled', '1'),
            ],
            [
                'type' => 'text',
                'name' => 'mhm_rentiva_booking_created_subject',
                'label' => __('Subject', 'mhm-rentiva'),
                'value' => EmailFormRenderer::get_option('mhm_rentiva_booking_created_subject', __('Booking #{booking_id} confirmed', 'mhm-rentiva')),
                'placeholder' => __('Booking #{booking_id} confirmed', 'mhm-rentiva'),
            ],
            [
                'type' => 'textarea',
                'name' => 'mhm_rentiva_booking_created_body',
                'label' => __('Content (HTML)', 'mhm-rentiva'),
                'value' => EmailFormRenderer::get_option('mhm_rentiva_booking_created_body', __('<p>Dear {contact_name},</p><p>Booking #{booking_id} has been successfully created.</p><p><strong>Booking Details:</strong><br>Vehicle: {vehicle_title}<br>Pickup Date: {pickup_date}<br>Return Date: {dropoff_date}<br>Total Amount: {total_price} TL</p><p>You will be notified when your booking is confirmed.</p><p>{site_name}</p>', 'mhm-rentiva')),
                'rows' => 8,
            ],
        ];
        
        EmailFormRenderer::render_form(
            __('New Booking Email', 'mhm-rentiva'),
            __('Email to be sent when a new booking is created.', 'mhm-rentiva'),
            $booking_created_fields
        );

        // Booking Status Change Email
        $booking_status_fields = [
            [
                'type' => 'checkbox',
                'name' => 'mhm_rentiva_booking_status_enabled',
                'label' => __('Enabled', 'mhm-rentiva'),
                'value' => EmailFormRenderer::get_option('mhm_rentiva_booking_status_enabled', '1'),
            ],
            [
                'type' => 'text',
                'name' => 'mhm_rentiva_booking_status_subject',
                'label' => __('Subject', 'mhm-rentiva'),
                'value' => EmailFormRenderer::get_option('mhm_rentiva_booking_status_subject', __('Booking #{booking_id} status updated', 'mhm-rentiva')),
            ],
            [
                'type' => 'textarea',
                'name' => 'mhm_rentiva_booking_status_body',
                'label' => __('Content (HTML)', 'mhm-rentiva'),
                'value' => EmailFormRenderer::get_option('mhm_rentiva_booking_status_body', __('<p>Dear {contact_name},</p><p>Booking #{booking_id} status has been updated.</p><p><strong>New Status:</strong> {status}<br><strong>Vehicle:</strong> {vehicle_title}<br><strong>Pickup Date:</strong> {pickup_date}<br><strong>Return Date:</strong> {dropoff_date}</p><p>If you have any questions about your booking, please contact us.</p><p>{site_name}</p>', 'mhm-rentiva')),
                'rows' => 8,
            ],
        ];
        
        EmailFormRenderer::render_form(
            __('Booking Status Change Email', 'mhm-rentiva'),
            __('Email to be sent when booking status changes.', 'mhm-rentiva'),
            $booking_status_fields
        );

        // Admin Notification Email
        $booking_admin_fields = [
            [
                'type' => 'checkbox',
                'name' => 'mhm_rentiva_booking_admin_enabled',
                'label' => __('Enabled', 'mhm-rentiva'),
                'value' => EmailFormRenderer::get_option('mhm_rentiva_booking_admin_enabled', '1'),
            ],
            [
                'type' => 'email',
                'name' => 'mhm_rentiva_booking_admin_to',
                'label' => __('Admin Email', 'mhm-rentiva'),
                'value' => EmailFormRenderer::get_option('mhm_rentiva_booking_admin_to', get_option('admin_email')),
                'description' => __('Email address where new booking notifications will be sent.', 'mhm-rentiva'),
            ],
            [
                'type' => 'text',
                'name' => 'mhm_rentiva_booking_admin_subject',
                'label' => __('Subject', 'mhm-rentiva'),
                'value' => EmailFormRenderer::get_option('mhm_rentiva_booking_admin_subject', __('New Booking Request #{booking_id}', 'mhm-rentiva')),
            ],
            [
                'type' => 'textarea',
                'name' => 'mhm_rentiva_booking_admin_body',
                'label' => __('Content (HTML)', 'mhm-rentiva'),
                'value' => EmailFormRenderer::get_option('mhm_rentiva_booking_admin_body', __('<p>New Booking Request</p><p>A new request has been received for booking #{booking_id}.</p><p><strong>Booking Details:</strong><br>Vehicle: {vehicle_title}<br>Customer: {contact_name}<br>Email: {contact_email}<br>Phone: {contact_phone}<br>Pickup Date: {pickup_date}<br>Return Date: {dropoff_date}<br>Total Amount: {total_price} TL</p><p>Log in to the admin panel to manage the booking.</p>', 'mhm-rentiva')),
                'rows' => 8,
            ],
        ];
        
        EmailFormRenderer::render_form(
            __('Admin Notification Email', 'mhm-rentiva'),
            __('Notification to be sent to admin when new booking is created.', 'mhm-rentiva'),
            $booking_admin_fields
        );

        // Reminder Email (Customer)
        $reminder_fields = [
            [
                'type' => 'checkbox',
                'name' => 'mhm_rentiva_booking_reminder_enabled',
                'label' => __('Enabled', 'mhm-rentiva'),
                'value' => get_option('mhm_rentiva_booking_reminder_enabled', '1') === '1',
                'description' => __('Send reminder to customer before pickup.', 'mhm-rentiva'),
            ],
            [
                'type' => 'text',
                'name' => 'mhm_rentiva_booking_reminder_subject',
                'label' => __('Subject', 'mhm-rentiva'),
                'value' => get_option('mhm_rentiva_booking_reminder_subject', __('Reminder: Your booking #{booking_id} starts soon', 'mhm-rentiva')),
            ],
            [
                'type' => 'textarea',
                'name' => 'mhm_rentiva_booking_reminder_body',
                'label' => __('Content (HTML)', 'mhm-rentiva'),
                'value' => get_option('mhm_rentiva_booking_reminder_body', '<p>' . esc_html__('Hi {contact_name}, this is a reminder for your upcoming booking #{booking_id}.', 'mhm-rentiva') . '</p>'),
                'rows' => 8,
            ],
        ];
        EmailFormRenderer::render_form(
            __('Booking Reminder Email', 'mhm-rentiva'),
            __('Email to be sent to customer before pickup time.', 'mhm-rentiva'),
            $reminder_fields
        );
        echo '<p class="description" style="margin-top:-10px;">' . esc_html__('Placeholders: {contact_name}, {booking_id}, {vehicle_title}, {pickup_date}, {return_date}, {site_name}, {site_url}', 'mhm-rentiva') . '</p>';

        // Welcome Email (Customer)
        $welcome_fields = [
            [
                'type' => 'checkbox',
                'name' => 'mhm_rentiva_welcome_email_enabled',
                'label' => __('Enabled', 'mhm-rentiva'),
                'value' => get_option('mhm_rentiva_welcome_email_enabled', '1') === '1',
                'description' => __('Send welcome email to the customer after first booking.', 'mhm-rentiva'),
            ],
            [
                'type' => 'text',
                'name' => 'mhm_rentiva_welcome_email_subject',
                'label' => __('Subject', 'mhm-rentiva'),
                'value' => get_option('mhm_rentiva_welcome_email_subject', __('Welcome to {site_name}', 'mhm-rentiva')),
            ],
            [
                'type' => 'textarea',
                'name' => 'mhm_rentiva_welcome_email_body',
                'label' => __('Content (HTML)', 'mhm-rentiva'),
                'value' => get_option('mhm_rentiva_welcome_email_body', '<p>' . esc_html__('Welcome aboard! You can access your account from {site_url}', 'mhm-rentiva') . '</p>'),
                'rows' => 8,
            ],
        ];
        EmailFormRenderer::render_form(
            __('Welcome Email', 'mhm-rentiva'),
            __('Email to be sent once to customer after their first booking.', 'mhm-rentiva'),
            $welcome_fields
        );
        echo '<p class="description" style="margin-top:-10px;">' . esc_html__('Placeholders: {contact_name}, {site_name}, {site_url}', 'mhm-rentiva') . '</p>';
    }
}
