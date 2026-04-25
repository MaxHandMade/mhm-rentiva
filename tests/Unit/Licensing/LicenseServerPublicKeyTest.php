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
        // Pre-release this test pinned the embedded PEM to the fixture file
        // as a canary: if someone swapped the constant for production keys
        // mid-development, this would fail loudly. Once the release tag
        // ships with the production public key embedded, the invariant is
        // intentionally broken — fixture-bound tests now reach the fixture
        // key via tests/bootstrap.php LicenseServerPublicKey::injectForTesting()
        // override instead. The reflection-direct constant read here cannot
        // see that override, so this test is skipped in the released branch.
        $this->markTestSkipped(
            'Embedded PEM is the production public key after the v4.31.0 release '
            . 'swap. Fixture-bound suite uses bootstrap.php injectForTesting() override; '
            . 'reflection-direct constant read here cannot see the override.'
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
