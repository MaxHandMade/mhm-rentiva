<?php declare(strict_types=1);

namespace MHMRentiva\REST\StripeWebhook\Helpers;

use MHMRentiva\Admin\Booking\Core\Status;

if (!defined('ABSPATH')) {
    exit;
}

final class EventProcessor
{
    /**
     * Process Stripe webhook event
     */
    public static function processEvent(string $type, int $booking_id, string $payment_intent_id): void
    {
        if ($type === 'payment_intent.succeeded') {
            update_post_meta($booking_id, '_mhm_payment_status', 'paid');
            update_post_meta($booking_id, '_mhm_stripe_payment_intent', $payment_intent_id);
            
            // Rezervasyon durumunu onayla
            $old = get_post_meta($booking_id, '_mhm_status', true) ?: 'pending';
            if ($old !== 'confirmed') {
                Status::update_status($booking_id, 'confirmed', get_current_user_id() ?: 0);
            }
        } elseif (in_array($type, ['payment_intent.payment_failed', 'payment_intent.canceled'], true)) {
            update_post_meta($booking_id, '_mhm_payment_status', 'failed');
            update_post_meta($booking_id, '_mhm_stripe_payment_intent', $payment_intent_id);
        }
    }
}
