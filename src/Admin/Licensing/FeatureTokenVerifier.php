<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Licensing;

use OpenSSLAsymmetricKey;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Verifies feature tokens issued by mhm-license-server v1.10.0+.
 *
 * Wire format: `{base64url(canonical_payload)}.{base64url(rsa_signature)}`.
 * The signature is RSA-2048 + PKCS#1 v1.5 + SHA-256, produced by the
 * server's private key and verified here against the public key embedded
 * in {@see LicenseServerPublicKey}.
 *
 * v4.31.0 — Migrated from HMAC. The previous design required clients to
 * carry a copy of the server's signing secret (`FEATURE_TOKEN_KEY`) which
 * left source-edit attacks (`isPro()` patch + real license) unmitigated
 * whenever the customer wp-config skipped the optional secret. Asymmetric
 * crypto closes that hole without giving up the zero-config UX: clients
 * ship only the public key, which can verify but cannot mint.
 *
 * Used by {@see Mode::canUse*()} to gate Pro features. A `return true;`
 * patch on `LicenseManager::isActive()` no longer unlocks anything because
 * the gate also requires `features['<key>'] === true` from a token that
 * passes RSA verify, matches the local site hash, and is unexpired.
 */
final class FeatureTokenVerifier {

    private OpenSSLAsymmetricKey $publicKey;

    /**
     * @param OpenSSLAsymmetricKey|null $publicKey Optional override for tests.
     *                                             Defaults to the embedded
     *                                             production public key.
     */
    public function __construct(?OpenSSLAsymmetricKey $publicKey = null)
    {
        $this->publicKey = $publicKey ?? LicenseServerPublicKey::resource();
    }

    /**
     * Verify a feature token's signature, site binding and freshness.
     *
     * Returns true only when ALL of the following hold:
     *  - Token has the expected `{payload}.{signature}` two-segment shape.
     *  - Both segments base64url-decode without error.
     *  - The signature verifies against the embedded public key.
     *  - The decoded payload's `site_hash` matches `$expectedSiteHash`.
     *  - The decoded payload's `expires_at` is in the future.
     *
     * @param string $token             Wire-format feature token from server.
     * @param string $expectedSiteHash  Local site hash to bind the token to.
     */
    public function verify(string $token, string $expectedSiteHash): bool
    {
        if ($token === '' || substr_count($token, '.') !== 1) {
            return false;
        }

        [$payloadSegment, $signatureSegment] = explode('.', $token, 2);
        if ($payloadSegment === '' || $signatureSegment === '') {
            return false;
        }

        $canonical = self::base64UrlDecode($payloadSegment);
        $signature = self::base64UrlDecode($signatureSegment);
        if ($canonical === '' || $signature === '') {
            return false;
        }

        $verified = openssl_verify($canonical, $signature, $this->publicKey, OPENSSL_ALGO_SHA256);
        if ($verified !== 1) {
            return false;
        }

        $payload = json_decode($canonical, true);
        if (!is_array($payload)) {
            return false;
        }

        if (( $payload['site_hash'] ?? '' ) !== $expectedSiteHash) {
            return false;
        }

        $expires_at = isset($payload['expires_at']) ? (int) $payload['expires_at'] : 0;
        if ($expires_at <= time()) {
            return false;
        }

        return true;
    }

    /**
     * Read a feature flag from a token's payload.
     *
     * Caller is responsible for having verified the token first via
     * {@see self::verify()} — this method does NOT re-verify the signature
     * (that would double the openssl_verify cost on every gate call). It
     * simply decodes the payload segment and looks up the feature key.
     *
     * @param string $token        Wire-format feature token.
     * @param string $featureName  Feature key (e.g. 'vendor_marketplace').
     */
    public function hasFeature(string $token, string $featureName): bool
    {
        if ($token === '' || substr_count($token, '.') !== 1) {
            return false;
        }

        [$payloadSegment] = explode('.', $token, 2);
        $canonical        = self::base64UrlDecode($payloadSegment);
        if ($canonical === '') {
            return false;
        }

        $payload = json_decode($canonical, true);
        if (!is_array($payload) || !isset($payload['features']) || !is_array($payload['features'])) {
            return false;
        }

        return ( $payload['features'][ $featureName ] ?? false ) === true;
    }

    private static function base64UrlDecode(string $input): string
    {
        $remainder = strlen($input) % 4;
        if ($remainder !== 0) {
            $input .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($input, '-_', '+/'), true); // phpcs:ignore WordPress.PHP.DiscouragedFunctions.obfuscation_base64_decode -- binary RSA signature byte transport
        return $decoded === false ? '' : $decoded;
    }
}
