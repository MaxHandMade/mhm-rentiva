<?php declare(strict_types=1);

namespace MHMRentiva\REST\StripeWebhook;

use MHMRentiva\Admin\Payment\Config;
use MHMRentiva\Admin\Booking\Core\Status;
use MHMRentiva\REST\StripeWebhook\Helpers\SignatureVerifier;
use MHMRentiva\REST\StripeWebhook\Helpers\BookingQuery;
use MHMRentiva\REST\StripeWebhook\Helpers\EventProcessor;
use WP_REST_Request;
use WP_REST_Response;

if (!defined('ABSPATH')) {
    exit;
}

final class Webhook
{
    public static function handle(WP_REST_Request $request): WP_REST_Response
    {
        $secret = Config::webhookSecret();
        if (!$secret) {
            return new WP_REST_Response(['ok' => false, 'code' => 'no_secret'], 400);
        }

        $sigHeader = $request->get_header('Stripe-Signature');
        $payload   = $request->get_body();
        if (!SignatureVerifier::verifySignature($payload, $sigHeader, $secret)) {
            return new WP_REST_Response(['ok' => false, 'code' => 'invalid_signature'], 400);
        }

        $event = json_decode($payload, true);
        if (!is_array($event) || empty($event['type'])) {
            return new WP_REST_Response(['ok' => false, 'code' => 'invalid_payload'], 400);
        }

        $type = (string) $event['type'];
        $pi   = $event['data']['object'] ?? null;

        if (!is_array($pi)) {
            return new WP_REST_Response(['ok' => true], 200);
        }

        $payment_intent_id = (string) ($pi['id'] ?? '');
        $metadata = $pi['metadata'] ?? [];
        $booking_id = isset($metadata['booking_id']) ? (int) $metadata['booking_id'] : 0;

        if (!$booking_id) {
            // fallback: find by saved meta
            $booking_id = BookingQuery::findBookingByIntent($payment_intent_id);
        }

        if ($booking_id) {
            EventProcessor::processEvent($type, $booking_id, $payment_intent_id);
        }

        return new WP_REST_Response(['ok' => true], 200);
    }
}
