<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Licensing;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Verifies feature tokens issued by mhm-license-server v1.9.0+.
 *
 * Wire format: `{base64(json_payload)}.{hmac_hex}`. The payload is plaintext-
 * readable on the client (we only verify the HMAC, not decrypt) — see plan
 * §4 Layer 3 Option A. Tamper resistance is the goal, not confidentiality.
 *
 * Used by `Mode::canUse*()` to gate Pro features. A `return true;` patch on
 * `LicenseManager::isActive()` no longer unlocks anything because the gate
 * also requires `features['<key>'] === true` from a valid, non-expired,
 * server-signed token.
 */
final class FeatureTokenVerifier {

    private string $secret;

    public function __construct(string $secret)
    {
        $this->secret = $secret;
    }

    /**
     * @return array<string,mixed>|null Payload on success, null on tamper/expiry/malformed.
     */
    public function verify(string $token): ?array
    {
        if ($token === '' || substr_count($token, '.') !== 1) {
            return null;
        }

        [$payload_b64, $signature] = explode('.', $token, 2);

        if ($payload_b64 === '' || $signature === '') {
            return null;
        }

        $expected = hash_hmac('sha256', $payload_b64, $this->secret);
        if (!hash_equals($expected, $signature)) {
            return null;
        }

        $json = base64_decode($payload_b64, true);
        if ($json === false) {
            return null;
        }

        $payload = json_decode($json, true);
        if (!is_array($payload)) {
            return null;
        }

        $expires_at = isset($payload['expires_at']) ? (int) $payload['expires_at'] : 0;
        if ($expires_at <= time()) {
            return null;
        }

        return $payload;
    }

    /**
     * @param array<string,mixed>|null $payload Output of verify().
     */
    public function hasFeature(?array $payload, string $feature): bool
    {
        if ($payload === null || !isset($payload['features']) || !is_array($payload['features'])) {
            return false;
        }

        return ( $payload['features'][ $feature ] ?? false ) === true;
    }
}
