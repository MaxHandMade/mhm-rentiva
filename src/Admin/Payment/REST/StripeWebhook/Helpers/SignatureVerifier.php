<?php declare(strict_types=1);

namespace MHMRentiva\REST\StripeWebhook\Helpers;

if (!defined('ABSPATH')) {
    exit;
}

final class SignatureVerifier
{
    /**
     * Verify Stripe webhook signature
     */
    public static function verifySignature(string $payload, ?string $sigHeader, string $secret): bool
    {
        if (!$sigHeader) {
            return false;
        }
        
        // Parse header: t=timestamp,v1=signature[,v1=...]
        $parts = [];
        foreach (explode(',', $sigHeader) as $seg) {
            [$k, $v] = array_map('trim', array_pad(explode('=', $seg, 2), 2, ''));
            if ($k !== '' && $v !== '') {
                $parts[$k][] = $v;
            }
        }
        
        if (empty($parts['t']) || empty($parts['v1'])) {
            return false;
        }
        
        $timestamp = (int) $parts['t'][0];
        if (abs(time() - $timestamp) > 300) { // 5 min tolerance
            return false;
        }
        
        $signedPayload = $timestamp . '.' . $payload;
        $expected = hash_hmac('sha256', $signedPayload, $secret);
        
        foreach ($parts['v1'] as $sig) {
            if (hash_equals($expected, $sig)) {
                return true;
            }
        }
        
        return false;
    }
}
