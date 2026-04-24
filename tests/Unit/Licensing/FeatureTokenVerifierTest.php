<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Licensing;

use MHMRentiva\Admin\Licensing\FeatureTokenVerifier;
use WP_UnitTestCase;

/**
 * Mirror of mhm-license-server v1.9.0 FeatureTokenIssuer — verify side only.
 *
 * Token wire format: `{base64(json_payload)}.{hmac_hex}`. Tests must
 * reproduce the server side issuance exactly so a drift here means
 * the live server stopped being parseable.
 */
final class FeatureTokenVerifierTest extends WP_UnitTestCase
{
    private const SECRET = 'test-feature-token-key';

    public function test_verifies_well_formed_server_issued_token(): void
    {
        $token = $this->serverStyleIssue([
            'license_key_hash' => 'h',
            'product_slug'     => 'mhm-rentiva',
            'plan'             => 'pro',
            'features'         => ['vendor_marketplace' => true, 'messaging' => true],
            'site_hash'        => 's',
            'issued_at'        => time(),
            'expires_at'       => time() + 3600,
        ]);

        $verifier = new FeatureTokenVerifier(self::SECRET);
        $payload  = $verifier->verify($token);

        $this->assertIsArray($payload);
        $this->assertSame('mhm-rentiva', $payload['product_slug']);
        $this->assertTrue($payload['features']['vendor_marketplace']);
    }

    public function test_returns_null_for_token_signed_with_different_secret(): void
    {
        $token = $this->serverStyleIssue([
            'features' => ['vendor_marketplace' => true],
            'expires_at' => time() + 3600,
        ], 'wrong-secret');

        $verifier = new FeatureTokenVerifier(self::SECRET);
        $this->assertNull($verifier->verify($token));
    }

    public function test_returns_null_for_expired_token(): void
    {
        $token = $this->serverStyleIssue([
            'features'   => ['messaging' => true],
            'expires_at' => time() - 10,
        ]);

        $verifier = new FeatureTokenVerifier(self::SECRET);
        $this->assertNull($verifier->verify($token));
    }

    public function test_returns_null_for_malformed_tokens(): void
    {
        $verifier = new FeatureTokenVerifier(self::SECRET);
        $this->assertNull($verifier->verify(''));
        $this->assertNull($verifier->verify('no-dot'));
        $this->assertNull($verifier->verify('a.b.c.d'));
        $this->assertNull($verifier->verify('!!!.!!!'));
    }

    public function test_has_feature_returns_true_only_when_payload_grants_it(): void
    {
        $verifier = new FeatureTokenVerifier(self::SECRET);

        $payload = [
            'features' => ['vendor_marketplace' => true, 'messaging' => false],
        ];

        $this->assertTrue($verifier->hasFeature($payload, 'vendor_marketplace'));
        $this->assertFalse($verifier->hasFeature($payload, 'messaging'));
        $this->assertFalse($verifier->hasFeature($payload, 'nonexistent_feature'));
        $this->assertFalse($verifier->hasFeature(null, 'vendor_marketplace'));
        $this->assertFalse($verifier->hasFeature([], 'vendor_marketplace'));
        $this->assertFalse($verifier->hasFeature(['features' => 'not-an-array'], 'vendor_marketplace'));
    }

    /**
     * Reproduces server-side FeatureTokenIssuer::issue() output format.
     *
     * @param array<string,mixed> $payload
     */
    private function serverStyleIssue(array $payload, ?string $secret = null): string
    {
        $secret = $secret ?? self::SECRET;
        $payloadB64 = base64_encode(
            (string) wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
        $signature = hash_hmac('sha256', $payloadB64, $secret);
        return $payloadB64 . '.' . $signature;
    }
}
