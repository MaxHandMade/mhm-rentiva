<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Emails\Templates;

use MHMRentiva\Admin\Emails\Core\EmailFormRenderer;
use MHMRentiva\Admin\Settings\Groups\EmailSettings;

if (!defined('ABSPATH')) {
    exit;
}

final class MessageEmails
{
    public static function render(): void
    {
        echo '<h2>' . esc_html__('Message Notifications', 'mhm-rentiva') . '</h2>';
        echo '<p class="description">' . esc_html__('Configure email notifications for the messaging system.', 'mhm-rentiva') . '</p>';
        echo '<p class="description">' . esc_html__('Available placeholders: {booking_id}, {customer_name}, {contact_email}, {message_body}, {reply_body}, {site_name}', 'mhm-rentiva') . '</p>';

        // New Message (Admin)
        $admin_fields = [
            [
                'type' => 'text',
                'name' => 'mhm_rentiva_message_received_admin_subject',
                'label' => esc_html__('Subject (Admin)', 'mhm-rentiva'),
                'value' => (function () {
                    $val = EmailFormRenderer::get_option('mhm_rentiva_message_received_admin_subject', '');
                    return !empty($val) ? $val : __('New Message from {customer_name}', 'mhm-rentiva');
                })(),
            ],
            [
                'type' => 'textarea',
                'name' => 'mhm_rentiva_message_received_admin_body',
                'label' => esc_html__('Content (Admin HTML)', 'mhm-rentiva'),
                'value' => (function () {
                    $val = EmailFormRenderer::get_option('mhm_rentiva_message_received_admin_body', '');
                    return !empty($val) ? $val : EmailSettings::get_default_message_admin_body();
                })(),
                'rows' => 8,
            ],
        ];

        EmailFormRenderer::render_form(
            __('New Message Notification (Admin)', 'mhm-rentiva'),
            __('Email sent to admin when a customer sends a new message.', 'mhm-rentiva'),
            $admin_fields
        );

        // Reply Message (Customer)
        $customer_fields = [
            [
                'type' => 'text',
                'name' => 'mhm_rentiva_message_replied_customer_subject',
                'label' => esc_html__('Subject (Customer)', 'mhm-rentiva'),
                'value' => (function () {
                    $val = EmailFormRenderer::get_option('mhm_rentiva_message_replied_customer_subject', '');
                    return !empty($val) ? $val : __('New Reply for Booking #{booking_id}', 'mhm-rentiva');
                })(),
            ],
            [
                'type' => 'textarea',
                'name' => 'mhm_rentiva_message_replied_customer_body',
                'label' => esc_html__('Content (Customer HTML)', 'mhm-rentiva'),
                'value' => (function () {
                    $val = \MHMRentiva\Admin\Emails\Core\EmailFormRenderer::get_option('mhm_rentiva_message_replied_customer_body', '');
                    $val = is_string($val) ? trim($val) : '';
                    // If empty or simple legacy text (no button), force Gold Standard
                    if ($val === '' || strpos($val, '<a href="{my_account_url}"') === false) {
                        return \MHMRentiva\Admin\Settings\Groups\EmailSettings::get_default_message_customer_body();
                    }
                    return $val;
                })(),
                'rows' => 8,
            ],
        ];

        EmailFormRenderer::render_form(
            __('Message Reply Notification (Customer)', 'mhm-rentiva'),
            __('Email sent to customer when admin replies to a message.', 'mhm-rentiva'),
            $customer_fields
        );
    }
}
