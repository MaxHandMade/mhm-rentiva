<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Emails\Notifications;

use MHMRentiva\Admin\Emails\Core\Mailer;

if (!defined('ABSPATH')) {
    exit;
}

final class RefundNotifications
{
    public static function register(): void
    {
        // nothing to hook right now; kept for consistency
    }

    public static function notify(int $booking_id, int $amount_kurus, string $currency, string $newPayStatus, string $reason = ''): void
    {
        $email = (string) get_post_meta($booking_id, '_mhm_contact_email', true);
        $name  = (string) get_post_meta($booking_id, '_mhm_contact_name', true);
        $admin = get_option('admin_email');

        $amountHuman = number_format_i18n($amount_kurus / 100, 2) . ' ' . strtoupper($currency ?: 'TRY');
        $statusText = $newPayStatus === 'refunded' ? __('full refund', 'mhm-rentiva') : __('partial refund', 'mhm-rentiva');

        $context = [
            'booking' => [
                'id'      => (int) $booking_id,
                'title'   => get_the_title($booking_id),
                'status'  => (string) get_post_meta($booking_id, '_mhm_status', true),
                'payment' => [
                    'status'   => $newPayStatus,
                    'amount'   => (int) get_post_meta($booking_id, '_mhm_payment_amount', true),
                    'currency' => (string) get_post_meta($booking_id, '_mhm_payment_currency', true) ?: 'TRY',
                ],
            ],
            'amount'   => $amountHuman,
            'status'   => $statusText,
            'reason'   => (string) $reason,
            'customer' => [
                'email' => $email,
                'name'  => $name,
            ],
            'site' => [
                'name' => get_bloginfo('name'),
                'url'  => home_url('/'),
            ],
        ];
        if ($email) {
            Mailer::send('refund_customer', $email, $context);
        }
        if ($admin) {
            Mailer::send('refund_admin', $admin, $context);
        }
    }
}
