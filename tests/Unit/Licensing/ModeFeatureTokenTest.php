<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Licensing;

use MHMRentiva\Admin\Licensing\LicenseManager;
use MHMRentiva\Admin\Licensing\Mode;
use OpenSSLAsymmetricKey;
use WP_UnitTestCase;

/**
 * v4.31.0 — Mode::canUse*() must consult an RSA-signed feature token, not
 * just `isActive()`. A `return true;` patch on `LicenseManager::isActive()`
 * cannot unlock Pro features when the gate also requires a token whose RSA
 * signature verifies, whose site_hash matches the local site, and whose
 * `features['<key>']` is true.
 *
 * Strict enforcement — there is NO legacy `isPro()`-only fallback any more.
 * Customers running the v4.30.x zero-config deploy got past the HMAC gate
 * because their `FEATURE_TOKEN_KEY` was unset and the gate defaulted to
 * `isPro()`. v4.31.0 closes that hole because the embedded public key is
 * present in every build.
 */
final class ModeFeatureTokenTest extends WP_UnitTestCase
{
    /** @var \OpenSSLAsymmetricKey */
    private $privateKey;

    protected function setUp(): void
    {
        parent::setUp();
        update_option(LicenseManager::OPTION, [], false);

        // Disable dev mode so isActive() actually checks license data.
        update_option('mhm_rentiva_disable_dev_mode', true, false);

        $privatePem = (string) file_get_contents(__DIR__ . '/../../fixtures/test-rsa-private.pem');
        $private    = openssl_pkey_get_private($privatePem);
        $this->assertNotFalse($private, 'Test fixture private key failed to parse');
        $this->privateKey = $private;
    }

    protected function tearDown(): void
    {
        delete_option(LicenseManager::OPTION);
        delete_option('mhm_rentiva_disable_dev_mode');
        parent::tearDown();
    }

    public function test_can_use_vendor_marketplace_returns_true_with_valid_token_granting_feature(): void
    {
        $this->seedActiveLicenseWithToken(['vendor_marketplace' => true]);

        $this->assertTrue(Mode::canUseVendorMarketplace());
    }

    public function test_can_use_vendor_marketplace_returns_false_when_token_missing(): void
    {
        $this->seedActiveLicenseWithoutToken();

        $this->assertFalse(Mode::canUseVendorMarketplace());
    }

    public function test_can_use_vendor_marketplace_returns_false_when_token_does_not_grant_feature(): void
    {
        $this->seedActiveLicenseWithToken(['messaging' => true /* but not vendor_marketplace */]);

        $this->assertFalse(Mode::canUseVendorMarketplace());
    }

    public function test_can_use_messages_checks_messaging_feature_in_token(): void
    {
        $this->seedActiveLicenseWithToken(['messaging' => true]);
        $this->assertTrue(Mode::canUseMessages());

        $this->seedActiveLicenseWithToken(['vendor_marketplace' => true]);
        $this->assertFalse(Mode::canUseMessages());
    }

    public function test_can_use_advanced_reports_checks_advanced_reports_feature_in_token(): void
    {
        $this->seedActiveLicenseWithToken(['advanced_reports' => true]);
        $this->assertTrue(Mode::canUseAdvancedReports());

        $this->seedActiveLicenseWithToken(['messaging' => true]);
        $this->assertFalse(Mode::canUseAdvancedReports());
    }

    public function test_isactive_alone_is_not_enough_to_unlock_features(): void
    {
        // Source-edit attack: license option says active but no token.
        update_option(LicenseManager::OPTION, [
            'key'           => 'EVIL-PATCH-001',
            'status'        => 'active',
            'plan'          => 'pro',
            'expires_at'    => time() + 86400,
            'activation_id' => 'fake-activation',
            // No feature_token — simulating crack
        ], false);

        $this->assertTrue(LicenseManager::instance()->isActive(), 'Local check should pass (the attack works)');
        $this->assertFalse(Mode::canUseVendorMarketplace(), 'But Mode must NOT grant the feature');
        $this->assertFalse(Mode::canUseMessages());
        $this->assertFalse(Mode::canUseAdvancedReports());
    }

    public function test_no_legacy_fallback_when_token_field_empty(): void
    {
        // v4.31.0 — even with isActive() returning true, an empty token must
        // fail closed. The v4.30.x legacy `isPro()` fallback (engaged when
        // FEATURE_TOKEN_KEY was unset) is GONE: clients no longer carry a
        // shared secret, the embedded public key is the single source of
        // truth, so "no token" means "Pro denied" without exception.
        $this->seedActiveLicenseWithoutToken();

        $this->assertFalse(Mode::canUseVendorMarketplace(), 'No legacy fallback — strict token enforcement');
        $this->assertFalse(Mode::canUseMessages());
        $this->assertFalse(Mode::canUseAdvancedReports());
        $this->assertFalse(Mode::canUseVendorPayout());
    }

    public function test_returns_false_when_token_signed_by_foreign_key(): void
    {
        // Cracked binary forge attempt: attacker mints their own key pair
        // and signs a token. The embedded public key cannot verify it.
        $foreign = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        $this->assertNotFalse($foreign, 'Foreign key generation failed');

        $token = $this->buildFeatureToken(['vendor_marketplace' => true], $foreign);
        update_option(LicenseManager::OPTION, [
            'key'           => 'FORGED-001',
            'status'        => 'active',
            'plan'          => 'pro',
            'expires_at'    => time() + 86400,
            'activation_id' => 'a1',
            'feature_token' => $token,
        ], false);

        $this->assertFalse(Mode::canUseVendorMarketplace(), 'Foreign-key-signed token must NOT verify');
    }

    public function test_returns_false_when_token_site_hash_does_not_match(): void
    {
        // Token is RSA-valid but bound to a different site (e.g. moved from
        // staging to prod, or a stolen-token-from-other-customer attack).
        $token = $this->buildFeatureToken(
            ['vendor_marketplace' => true],
            $this->privateKey,
            ['site_hash' => 'totally-different-site-hash']
        );

        update_option(LicenseManager::OPTION, [
            'key'           => 'WRONG-SITE-001',
            'status'        => 'active',
            'plan'          => 'pro',
            'expires_at'    => time() + 86400,
            'activation_id' => 'a1',
            'feature_token' => $token,
        ], false);

        $this->assertFalse(Mode::canUseVendorMarketplace(), 'Site-hash-mismatched token must NOT grant access');
    }

    public function test_returns_false_when_token_expired(): void
    {
        $token = $this->buildFeatureToken(
            ['vendor_marketplace' => true],
            $this->privateKey,
            ['expires_at' => time() - 60, 'issued_at' => time() - 90000]
        );

        update_option(LicenseManager::OPTION, [
            'key'           => 'EXPIRED-001',
            'status'        => 'active',
            'plan'          => 'pro',
            'expires_at'    => time() + 86400,
            'activation_id' => 'a1',
            'feature_token' => $token,
        ], false);

        $this->assertFalse(Mode::canUseVendorMarketplace(), 'Expired token must NOT grant access');
    }

    public function test_returns_false_when_license_inactive_regardless_of_token(): void
    {
        // Valid token but license inactive → license gate trips first.
        $token = $this->buildFeatureToken(['vendor_marketplace' => true]);
        update_option(LicenseManager::OPTION, [
            'key'           => 'INACTIVE-001',
            'status'        => 'inactive',
            'feature_token' => $token,
        ], false);

        $this->assertFalse(Mode::canUseVendorMarketplace());
    }

    /**
     * @param array<string,bool> $features
     */
    private function seedActiveLicenseWithToken(array $features): void
    {
        update_option(LicenseManager::OPTION, [
            'key'           => 'TOKEN-LICENSE-001',
            'status'        => 'active',
            'plan'          => 'pro',
            'expires_at'    => time() + 86400,
            'activation_id' => 'a1',
            'feature_token' => $this->buildFeatureToken($features),
        ], false);
    }

    private function seedActiveLicenseWithoutToken(): void
    {
        update_option(LicenseManager::OPTION, [
            'key'           => 'NO-TOKEN-001',
            'status'        => 'active',
            'plan'          => 'pro',
            'expires_at'    => time() + 86400,
            'activation_id' => 'a1',
            // feature_token deliberately omitted
        ], false);
    }

    /**
     * @param array<string,bool>     $features
     * @param OpenSSLAsymmetricKey|null $signingKey  Defaults to the fixture
     *                                                 private key (matches
     *                                                 the embedded public PEM).
     * @param array<string,mixed>    $payloadOverrides
     */
    private function buildFeatureToken(
        array $features,
        $signingKey = null,
        array $payloadOverrides = []
    ): string {
        $signingKey = $signingKey ?? $this->privateKey;
        $siteHash   = LicenseManager::instance()->getSiteHash();

        $payload = array_merge([
            'license_key_hash' => 'h',
            'product_slug'     => 'mhm-rentiva',
            'plan'             => 'pro',
            'features'         => $features,
            'site_hash'        => $siteHash,
            'issued_at'        => time(),
            'expires_at'       => time() + 86400,
        ], $payloadOverrides);

        $sorted    = $this->recursiveKsort($payload);
        $canonical = (string) wp_json_encode($sorted, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $signature = '';
        openssl_sign($canonical, $signature, $signingKey, OPENSSL_ALGO_SHA256);

        return $this->base64UrlEncode($canonical) . '.' . $this->base64UrlEncode($signature);
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

    private function base64UrlEncode(string $binary): string
    {
        return rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');
    }
}
