<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Licensing;

use MHMRentiva\Admin\Licensing\LicenseManager;
use MHMRentiva\Admin\Licensing\Mode;
use WP_UnitTestCase;

/**
 * v4.33.0 — Gate vocabulary unification (Bug A2).
 *
 * 22 callsites previously called Mode::featureEnabled() which only
 * checked isPro() (no RSA token verify), giving cracked-binary +
 * Mode::isActive() patch attacks a free pass. All callsites migrate
 * to canUse*() which token-verify.
 *
 * Mode::featureEnabled() is soft-deprecated (kept as wrapper that emits
 * _deprecated_function() notice, body unchanged for 3rd-party back-compat).
 *
 * canUseExport() is a new wrapper introduced for the Export migration.
 */
final class ModeGateMigrationTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        update_option('mhm_rentiva_disable_dev_mode', true, false);
    }

    protected function tearDown(): void
    {
        delete_option(LicenseManager::OPTION);
        delete_option('mhm_rentiva_disable_dev_mode');
        remove_all_filters('mhm_rentiva_dev_pro_bypass');
        parent::tearDown();
    }

    private function seedActive(): void
    {
        update_option(LicenseManager::OPTION, [
            'key'           => 'GATE-001',
            'status'        => 'active',
            'plan'          => 'monthly',
            'expires_at'    => time() + 86400,
            'activation_id' => 'a1',
        ], false);
    }

    public function test_canUseExport_method_exists(): void
    {
        $this->assertTrue(
            method_exists(Mode::class, 'canUseExport'),
            'Mode::canUseExport() must exist as v4.33.0 introduces this gate'
        );
    }

    public function test_canUseExport_returns_true_when_bypass_active(): void
    {
        $this->seedActive();
        add_filter('mhm_rentiva_dev_pro_bypass', '__return_true');

        $this->assertTrue(Mode::canUseExport(), 'Bypass should grant export');
    }

    public function test_canUseExport_returns_false_when_lite(): void
    {
        delete_option(LicenseManager::OPTION);

        $this->assertFalse(Mode::canUseExport(), 'Lite users do not have export gate (CSV/JSON via direct format check)');
    }

    public function test_canUseExport_returns_false_when_token_lacks_export_feature(): void
    {
        // Active license but no token in storage (legacy server simulation).
        $this->seedActive();
        // No filter, no bypass → token check runs → empty token → false.

        $this->assertFalse(Mode::canUseExport());
    }

    public function test_featureEnabled_emits_deprecation_notice(): void
    {
        // Capture deprecation notice via WP's testing infrastructure.
        $this->setExpectedDeprecated('MHMRentiva\\Admin\\Licensing\\Mode::featureEnabled');

        // Call the deprecated method.
        Mode::featureEnabled(Mode::FEATURE_MESSAGES);
    }

    public function test_featureEnabled_preserves_legacy_behavior(): void
    {
        // The body is kept intact for 3rd-party back-compat.
        // setExpectedDeprecated suppresses the notice.
        $this->setExpectedDeprecated('MHMRentiva\\Admin\\Licensing\\Mode::featureEnabled');

        // Lite + EXPORT → true (legacy behavior).
        delete_option(LicenseManager::OPTION);
        $this->assertTrue(Mode::featureEnabled(Mode::FEATURE_EXPORT), 'Legacy behavior: Lite + EXPORT returns true');

        // Lite + MESSAGES → false (legacy behavior).
        $this->assertFalse(Mode::featureEnabled(Mode::FEATURE_MESSAGES), 'Legacy behavior: Lite + MESSAGES returns false');
    }
}
