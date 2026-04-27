<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Licensing;

use MHMRentiva\Admin\Licensing\LicenseManager;
use MHMRentiva\Admin\Licensing\Mode;
use WP_UnitTestCase;

/**
 * v4.33.0 — Dev-mode bypass for `Mode::featureGranted()`. When BOTH
 * `WP_DEBUG` is true AND the `mhm_rentiva_dev_pro_bypass` filter (which
 * defaults to checking `MHM_RENTIVA_DEV_PRO`) returns true, the token
 * verification step is skipped so local Pro testing works without a
 * real RSA-signed token.
 *
 * Production safety:
 * - WP_DEBUG defaults to false on Hostinger production
 * - MHM_RENTIVA_DEV_PRO is an opt-in constant (undefined by default)
 * - isPro() check still runs — Lite licenses cannot bypass to Pro
 *
 * Filter `mhm_rentiva_dev_pro_bypass` exists for testability since
 * `define()` cannot be undone within a single PHP process.
 */
final class ModeDevBypassTest extends WP_UnitTestCase
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

    private function seedActiveLicenseWithoutToken(): void
    {
        update_option(LicenseManager::OPTION, [
            'key'           => 'TEST-DEV-001',
            'status'        => 'active',
            'plan'          => 'monthly',
            'expires_at'    => time() + 86400,
            'activation_id' => 'a1',
            // No feature_token — simulates dev environment without server
        ], false);
    }

    public function test_bypass_active_when_filter_returns_true(): void
    {
        $this->seedActiveLicenseWithoutToken();
        add_filter('mhm_rentiva_dev_pro_bypass', '__return_true');

        $this->assertTrue(Mode::canUseMessages(), 'Bypass active should grant any feature');
        $this->assertTrue(Mode::canUseAdvancedReports());
        $this->assertTrue(Mode::canUseVendorMarketplace());
        $this->assertTrue(Mode::canUseExport());
    }

    public function test_bypass_inactive_when_filter_returns_false(): void
    {
        $this->seedActiveLicenseWithoutToken();
        add_filter('mhm_rentiva_dev_pro_bypass', '__return_false');

        $this->assertFalse(Mode::canUseMessages(), 'Filter false → token gate enforced → no token → fail');
        $this->assertFalse(Mode::canUseExport());
    }

    public function test_bypass_inactive_when_filter_default(): void
    {
        // No filter added — default behavior (production path).
        $this->seedActiveLicenseWithoutToken();

        $this->assertFalse(Mode::canUseMessages(), 'No filter → production semantics → no token → fail');
        $this->assertFalse(Mode::canUseVendorMarketplace());
    }

    public function test_bypass_requires_isPro(): void
    {
        // No active license at all — Lite mode.
        delete_option(LicenseManager::OPTION);
        add_filter('mhm_rentiva_dev_pro_bypass', '__return_true');

        $this->assertFalse(Mode::canUseMessages(), 'isPro() check is the first gate — Lite cannot bypass to Pro');
        $this->assertFalse(Mode::canUseVendorMarketplace());
        $this->assertFalse(Mode::canUseExport());
    }

    public function test_bypass_filter_receives_default_value_from_constants(): void
    {
        // Filter callback can inspect the default and override.
        $this->seedActiveLicenseWithoutToken();
        $captured_default = null;
        add_filter('mhm_rentiva_dev_pro_bypass', function ($default) use (&$captured_default) {
            $captured_default = $default;
            return false;
        });

        Mode::canUseMessages();

        $this->assertIsBool($captured_default, 'Filter must receive a bool default value');
    }
}
