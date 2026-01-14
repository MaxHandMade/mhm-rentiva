<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Emails\Templates;

use MHMRentiva\Admin\Emails\Core\EmailFormRenderer;

if (!defined('ABSPATH')) {
    exit;
}

final class OfflinePayment
{
    public static function render(): void
    {
        echo '<h2>' . esc_html__('Offline Payment Emails', 'mhm-rentiva') . '</h2>';
        echo '<p class="description">' . esc_html__('Configure email notifications for offline payment status updates.', 'mhm-rentiva') . '</p>';

        if (class_exists('WooCommerce')) {
            echo '<div class="notice notice-info inline"><p>' . esc_html__('Note: Offline payment requests and confirmations are primarily handled by WooCommerce emails when WooCommerce is active.', 'mhm-rentiva') . '</p></div>';
        }

        // Offline Payment Rejected Email
        $rejected_fields = [
            [
                'type' => 'text',
                'name' => 'mhm_rentiva_offline_email_customer_subject_rejected',
                'label' => __('Subject', 'mhm-rentiva'),
                'value' => EmailFormRenderer::get_option('mhm_rentiva_offline_email_customer_subject_rejected', __('Payment Rejected: Booking #{booking_id}', 'mhm-rentiva')),
            ],
            [
                'type' => 'textarea',
                'name' => 'mhm_rentiva_offline_email_customer_body_rejected',
                'label' => __('Content (HTML)', 'mhm-rentiva'),
                'value' => EmailFormRenderer::get_option('mhm_rentiva_offline_email_customer_body_rejected', __('<p>Dear {contact_name},</p><p>We could not verify your offline payment for booking #{booking_id}.</p><p>Please contact us or try making the payment again.</p><p>{site_name}</p>', 'mhm-rentiva')),
                'rows' => 8,
            ],
        ];

        EmailFormRenderer::render_form(
            __('Offline Payment Rejected Email', 'mhm-rentiva'),
            __('Email to be sent when an offline payment proof is rejected.', 'mhm-rentiva'),
            $rejected_fields
        );

        echo '<p class="description" style="margin-top:-10px;">' . esc_html__('Placeholders: {contact_name}, {booking_id}, {site_name}, {site_url}', 'mhm-rentiva') . '</p>';
    }
}
