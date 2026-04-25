<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Licensing;

use MHMRentiva\Admin\Licensing\FeatureTokenVerifier;
use OpenSSLAsymmetricKey;
use WP_UnitTestCase;

/**
 * v4.31.0 — Mirror of mhm-license-server v1.10.0 FeatureTokenIssuer (verify
 * side). Tests inject the fixture public key via constructor DI so they
 * exercise real RSA verification rather than a mocked path.
 */
final class FeatureTokenVerifierTest extends WP_UnitTestCase
{
    private const SITE_HASH = 'site-hash-fixture';

    private OpenSSLAsymmetricKey $publicKey;
    /** @var \OpenSSLAsymmetricKey */
    private $privateKey;

    protected function setUp(): void
    {
        parent::setUp();

        $publicPem  = (string) file_get_contents(__DIR__ . '/../../fixtures/test-rsa-public.pem');
        $privatePem = (string) file_get_contents(__DIR__ . '/../../fixtures/test-rsa-private.pem');

        $public = openssl_pkey_get_public($publicPem);
        $this->assertNotFalse($public, 'Test fixture public key failed to parse');
        $this->publicKey = $public;

        $private = openssl_pkey_get_private($privatePem);
        $this->assertNotFalse($private, 'Test fixture private key failed to parse');
        $this->privateKey = $private;
    }

    public function testVerifyAcceptsValidRsaToken(): void
    {
        $token = $this->buildToken([
            'features' => ['vendor_marketplace' => true],
        ]);

        $verifier = new FeatureTokenVerifier($this->publicKey);
        $this->assertTrue($verifier->verify($token, self::SITE_HASH));
    }

    public function testVerifyRejectsTokenSignedWithDifferentKeyPair(): void
    {
        // Mint a foreign key pair and sign with that — the embedded public
        // key cannot verify it. This is the cracked-binary forge attempt.
        $foreign = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        $this->assertNotFalse($foreign, 'Foreign key generation failed');

        $canonical = $this->canonicalize($this->defaultPayload(['features' => ['vendor_marketplace' => true]]));
        $signature = '';
        openssl_sign($canonical, $signature, $foreign, OPENSSL_ALGO_SHA256);

        $forgedToken = self::base64UrlEncode($canonical) . '.' . self::base64UrlEncode($signature);

        $verifier = new FeatureTokenVerifier($this->publicKey);
        $this->assertFalse($verifier->verify($forgedToken, self::SITE_HASH));
    }

    public function testVerifyRejectsTamperedSignatureByte(): void
    {
        $token = $this->buildToken();

        [$payloadSegment, $signatureSegment] = explode('.', $token, 2);
        $sigBytes    = self::base64UrlDecode($signatureSegment);
        $sigBytes[0] = chr(ord($sigBytes[0]) ^ 0x01);
        $tampered    = $payloadSegment . '.' . self::base64UrlEncode($sigBytes);

        $verifier = new FeatureTokenVerifier($this->publicKey);
        $this->assertFalse($verifier->verify($tampered, self::SITE_HASH));
    }

    public function testVerifyRejectsTamperedPayload(): void
    {
        $token = $this->buildToken();

        [, $signatureSegment] = explode('.', $token, 2);
        $tamperedPayload      = $this->canonicalize($this->defaultPayload([
            'features'  => ['vendor_marketplace' => true],
            'site_hash' => self::SITE_HASH,
            // The signature was bound to the original payload; flipping the
            // payload now makes openssl_verify fail.
            'license_key_hash' => 'evil-hash',
        ]));

        $tampered = self::base64UrlEncode($tamperedPayload) . '.' . $signatureSegment;

        $verifier = new FeatureTokenVerifier($this->publicKey);
        $this->assertFalse($verifier->verify($tampered, self::SITE_HASH));
    }

    public function testVerifyRejectsExpiredToken(): void
    {
        $token = $this->buildToken([
            'expires_at' => time() - 60,
            'issued_at'  => time() - 90000,
        ]);

        $verifier = new FeatureTokenVerifier($this->publicKey);
        $this->assertFalse($verifier->verify($token, self::SITE_HASH));
    }

    public function testVerifyRejectsMismatchedSiteHash(): void
    {
        $token = $this->buildToken();

        $verifier = new FeatureTokenVerifier($this->publicKey);
        $this->assertFalse($verifier->verify($token, 'totally-different-site-hash'));
    }

    public function testVerifyRejectsMalformedTokens(): void
    {
        $verifier = new FeatureTokenVerifier($this->publicKey);

        $this->assertFalse($verifier->verify('', self::SITE_HASH));
        $this->assertFalse($verifier->verify('no-dot', self::SITE_HASH));
        $this->assertFalse($verifier->verify('a.b.c.d', self::SITE_HASH));
        $this->assertFalse($verifier->verify('!!!.!!!', self::SITE_HASH));
        $this->assertFalse($verifier->verify('.signature-only', self::SITE_HASH));
        $this->assertFalse($verifier->verify('payload-only.', self::SITE_HASH));
    }

    public function testHasFeatureReadsFeatureFlag(): void
    {
        $token = $this->buildToken([
            'features' => [
                'vendor_marketplace' => true,
                'advanced_reports'   => true,
                'messaging'          => false,
            ],
        ]);

        $verifier = new FeatureTokenVerifier($this->publicKey);

        $this->assertTrue($verifier->hasFeature($token, 'vendor_marketplace'));
        $this->assertTrue($verifier->hasFeature($token, 'advanced_reports'));
        $this->assertFalse($verifier->hasFeature($token, 'messaging'));
        $this->assertFalse($verifier->hasFeature($token, 'nonexistent_feature'));
    }

    public function testHasFeatureReturnsFalseForMalformedToken(): void
    {
        $verifier = new FeatureTokenVerifier($this->publicKey);

        $this->assertFalse($verifier->hasFeature('', 'vendor_marketplace'));
        $this->assertFalse($verifier->hasFeature('no-dot', 'vendor_marketplace'));
        $this->assertFalse($verifier->hasFeature('!!!.!!!', 'vendor_marketplace'));
    }

    /**
     * @param array<string,mixed> $overrides
     */
    private function buildToken(array $overrides = []): string
    {
        $payload   = $this->defaultPayload($overrides);
        $canonical = $this->canonicalize($payload);

        $signature = '';
        openssl_sign($canonical, $signature, $this->privateKey, OPENSSL_ALGO_SHA256);

        return self::base64UrlEncode($canonical) . '.' . self::base64UrlEncode($signature);
    }

    /**
     * @param array<string,mixed> $overrides
     * @return array<string,mixed>
     */
    private function defaultPayload(array $overrides = []): array
    {
        return array_merge(
            [
                'license_key_hash' => 'license-hash-fixture',
                'product_slug'     => 'mhm-rentiva',
                'plan'             => 'pro',
                'features'         => ['vendor_marketplace' => true],
                'site_hash'        => self::SITE_HASH,
                'issued_at'        => time(),
                'expires_at'       => time() + 86400,
            ],
            $overrides
        );
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function canonicalize(array $payload): string
    {
        $sorted = $this->recursiveKsort($payload);
        return (string) wp_json_encode($sorted, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param array<int|string,mixed> $array
     * @return array<int|string,mixed>
     */
    private function recursiveKsort(array $array): array
    {
        ksort($array);
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = $this->recursiveKsort($value);
            }
        }
        return $array;
    }

    private static function base64UrlEncode(string $binary): string
    {
        return rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $input): string
    {
        $remainder = strlen($input) % 4;
        if ($remainder !== 0) {
            $input .= str_repeat('=', 4 - $remainder);
        }
        $decoded = base64_decode(strtr($input, '-_', '+/'), true);
        return $decoded === false ? '' : $decoded;
    }
}
