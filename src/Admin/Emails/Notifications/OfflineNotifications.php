<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Emails\Notifications;

use MHMRentiva\Admin\Emails\Core\Mailer;

if (!defined('ABSPATH')) {
    exit;
}

final class OfflineNotifications
{
    public static function register(): void
    {
        add_action('mhm_rentiva_offline_receipt_uploaded', [self::class, 'on_receipt_uploaded'], 10, 2);
        add_action('mhm_rentiva_offline_verified', [self::class, 'on_verified'], 10, 2);
    }

    /**
     * Send notification to admin when receipt is uploaded
     * 
     * @param int $booking_id Booking ID
     * @param int $attachment_id Attachment ID
     */
    public static function on_receipt_uploaded(int $booking_id, int $attachment_id): void
    {
        $enabled = (string) get_option('mhm_rentiva_offline_email_admin_enabled', '1') === '1';
        if (!$enabled) return;

        $receipt_url = wp_get_attachment_url($attachment_id) ?: '';
        $additional_context = [
            'offline_payment' => [
                'receipt_url' => $receipt_url,
                'attachment_id' => $attachment_id,
            ]
        ];

        Mailer::sendBookingEmail('offline_receipt_uploaded_admin', $booking_id, 'admin', $additional_context);
    }

    /**
     * Send notification to customer when offline payment is verified
     * 
     * @param int $booking_id Booking ID
     * @param string $decision 'approve' or 'reject'
     */
    public static function on_verified(int $booking_id, string $decision): void
    {
        $enabled = (string) get_option('mhm_rentiva_offline_email_customer_enabled', '1') === '1';
        if (!$enabled) return;

        $is_approved = ($decision === 'approve');
        $template_key = $is_approved 
            ? 'offline_verified_approved_customer' 
            : 'offline_verified_rejected_customer';

        $additional_context = [
            'offline_payment' => [
                'decision' => $decision,
                'is_approved' => $is_approved,
                'status' => $is_approved ? 'paid' : 'failed',
                'status_label' => $is_approved ? __('Approved', 'mhm-rentiva') : __('Rejected', 'mhm-rentiva'),
            ]
        ];

        Mailer::sendBookingEmail($template_key, $booking_id, 'customer', $additional_context);
    }
}
