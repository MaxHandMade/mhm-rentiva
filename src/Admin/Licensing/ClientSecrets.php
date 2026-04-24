<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Licensing;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Resolves the three v4.30.0+ shared secrets used to talk to mhm-license-server v1.9.0+.
 *
 *   - RESPONSE_HMAC_SECRET — verifies the HMAC on every successful activate/validate response
 *   - FEATURE_TOKEN_KEY    — verifies the server-issued feature token used by Mode::canUse*()
 *   - PING_SECRET          — answers the X-MHM-Challenge during reverse site validation
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
    public const CONST_FEATURE_TOKEN = 'MHM_RENTIVA_LICENSE_FEATURE_TOKEN_KEY';
    public const CONST_PING          = 'MHM_RENTIVA_LICENSE_PING_SECRET';

    public static function getResponseHmacSecret(): string
    {
        return self::resolve(self::CONST_RESPONSE_HMAC);
    }

    public static function getFeatureTokenKey(): string
    {
        return self::resolve(self::CONST_FEATURE_TOKEN);
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
