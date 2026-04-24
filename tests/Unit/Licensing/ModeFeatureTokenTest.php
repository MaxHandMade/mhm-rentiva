<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Licensing;

use MHMRentiva\Admin\Licensing\LicenseManager;
use MHMRentiva\Admin\Licensing\Mode;
use WP_UnitTestCase;

/**
 * Phase B (v4.30.0+) — Mode::canUse*() must consult the server-issued feature
 * token, not just `isActive()`. A `return true;` patch on `LicenseManager::isActive()`
 * should NOT unlock Pro features when the feature token is missing or tampered.
 *
 * Backward-compat: when no FEATURE_TOKEN_KEY secret is configured (legacy
 * deploy), gates fall back to `isPro()` so existing customers keep working.
 */
final class ModeFeatureTokenTest extends WP_UnitTestCase
{
    private const FEATURE_SECRET = 'test-feature-token-secret';

    protected function setUp(): void
    {
        parent::setUp();
        update_option(LicenseManager::OPTION, [], false);

        if (!defined('MHM_RENTIVA_LICENSE_FEATURE_TOKEN_KEY')) {
            putenv('MHM_RENTIVA_LICENSE_FEATURE_TOKEN_KEY=' . self::FEATURE_SECRET);
        }

        // Disable dev mode so isActive() actually checks license data
        update_option('mhm_rentiva_disable_dev_mode', true, false);
    }

    protected function tearDown(): void
    {
        delete_option(LicenseManager::OPTION);
        delete_option('mhm_rentiva_disable_dev_mode');
        if (!defined('MHM_RENTIVA_LICENSE_FEATURE_TOKEN_KEY')) {
            putenv('MHM_RENTIVA_LICENSE_FEATURE_TOKEN_KEY=');
        }
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
        // Simulating source-edit attack: license option says active but no token.
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

    public function test_falls_back_to_ispro_when_feature_token_secret_not_configured(): void
    {
        if (defined('MHM_RENTIVA_LICENSE_FEATURE_TOKEN_KEY')) {
            $this->markTestSkipped('Constant defined; legacy fallback path cannot be asserted.');
        }

        // Clear secret
        putenv('MHM_RENTIVA_LICENSE_FEATURE_TOKEN_KEY=');

        // Simulating talking to a legacy server (no token) with no client
        // secret configured — gracefully fall back to `isPro()`.
        $this->seedActiveLicenseWithoutToken();

        $this->assertTrue(Mode::canUseVendorMarketplace(), 'Legacy fallback when no secret');
    }

    public function test_returns_false_when_license_inactive_regardless_of_token(): void
    {
        // Token says feature granted but license is inactive
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
     * @param array<string,bool> $features
     */
    private function buildFeatureToken(array $features): string
    {
        $payload = [
            'license_key_hash' => 'h',
            'product_slug'     => 'mhm-rentiva',
            'plan'             => 'pro',
            'features'         => $features,
            'site_hash'        => 's',
            'issued_at'        => time(),
            'expires_at'       => time() + 86400,
        ];
        $b64 = base64_encode((string) wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        return $b64 . '.' . hash_hmac('sha256', $b64, self::FEATURE_SECRET);
    }
}
