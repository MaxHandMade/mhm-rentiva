<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Licensing;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Resolves the v4.30.0+ shared secrets used to talk to mhm-license-server.
 *
 *   - RESPONSE_HMAC_SECRET — verifies the HMAC on every successful activate/validate response
 *   - PING_SECRET          — answers the X-MHM-Challenge during reverse site validation (optional)
 *
 * v4.31.0 — `FEATURE_TOKEN_KEY` removed: feature_token signing migrated to
 * RSA, so the client no longer carries a shared secret for that path. The
 * embedded {@see LicenseServerPublicKey} is enough to verify tokens.
 *
 * Each value MUST match the corresponding wp-config constant on the license server
 * (`MHM_LICENSE_SERVER_RESPONSE_HMAC_SECRET`, etc.). Operators define them in their
 * own `wp-config.php`; in CI/dev we also accept environment variables (`getenv()`).
 *
 * The plugin source is public, so we deliberately avoid hardcoding the values
 * here — they would be the first thing an attacker grepped for.
 */
final class ClientSecrets {

    public const CONST_RESPONSE_HMAC = 'MHM_RENTIVA_LICENSE_RESPONSE_HMAC_SECRET';
    public const CONST_PING          = 'MHM_RENTIVA_LICENSE_PING_SECRET';

    public static function getResponseHmacSecret(): string
    {
        return self::resolve(self::CONST_RESPONSE_HMAC);
    }

    public static function getPingSecret(): string
    {
        return self::resolve(self::CONST_PING);
    }

    private static function resolve(string $constant): string
    {
        if (defined($constant)) {
            return trim( (string) constant($constant));
        }

        $env = getenv($constant);
        if ($env === false) {
            return '';
        }

        return trim( (string) $env);
    }
}
