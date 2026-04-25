<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Licensing;

use MHMRentiva\Admin\Licensing\LicenseServerPublicKey;
use OpenSSLAsymmetricKey;
use ReflectionClass;
use WP_UnitTestCase;

final class LicenseServerPublicKeyTest extends WP_UnitTestCase
{
    protected function tearDown(): void
    {
        LicenseServerPublicKey::resetCache();
        parent::tearDown();
    }

    public function testResourceReturnsParsedPublicKey(): void
    {
        $key = LicenseServerPublicKey::resource();

        $this->assertInstanceOf(OpenSSLAsymmetricKey::class, $key);
    }

    public function testResourceMatchesFixturePublicKey(): void
    {
        // During development, the embedded PEM is the test fixture's public
        // half. Verifying the constant matches the fixture file gives us a
        // canary on the release-time swap: if someone replaces the constant
        // with the production key in this branch (it should be done on the
        // release tag commit only), this test fails loudly.
        $reflect    = new ReflectionClass(LicenseServerPublicKey::class);
        $constant   = $reflect->getReflectionConstant('PEM');
        $embedded   = trim((string) $constant->getValue());
        $fixturePem = trim((string) file_get_contents(__DIR__ . '/../../fixtures/test-rsa-public.pem'));

        $this->assertSame(
            $fixturePem,
            $embedded,
            'Embedded PEM must match tests/fixtures/test-rsa-public.pem during development. '
            . 'Replace with production public.pem ONLY on the release tag commit.'
        );
    }

    public function testResourceReusesCachedResourceAcrossCalls(): void
    {
        $first  = LicenseServerPublicKey::resource();
        $second = LicenseServerPublicKey::resource();

        $this->assertSame(
            $first,
            $second,
            'Public key resource must be cached — re-parsing on every gate call would cost 50ms+'
        );
    }

    public function testResetCacheForcesReparse(): void
    {
        $first = LicenseServerPublicKey::resource();
        LicenseServerPublicKey::resetCache();
        $second = LicenseServerPublicKey::resource();

        // After reset, parser produces a NEW resource (different identity)
        // even though the constant PEM is unchanged.
        $this->assertNotSame(
            $first,
            $second,
            'resetCache() must force a fresh openssl_pkey_get_public() call'
        );
    }

    public function testResourceProducesKeyUsableForVerify(): void
    {
        $publicKey = LicenseServerPublicKey::resource();

        // Sign with the matching fixture private key and verify with the
        // embedded public key — round-trip proof the pair is consistent.
        $privatePem = (string) file_get_contents(__DIR__ . '/../../fixtures/test-rsa-private.pem');
        $privateKey = openssl_pkey_get_private($privatePem);

        $payload   = 'round-trip-test-payload';
        $signature = '';
        openssl_sign($payload, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        $verified = openssl_verify($payload, $signature, $publicKey, OPENSSL_ALGO_SHA256);
        $this->assertSame(1, $verified, 'Embedded public key must verify signatures from paired private key');
    }
}
