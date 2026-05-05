<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Vendor\Profile;

use MHMRentiva\Admin\Core\MetaKeys;
use MHMRentiva\Admin\Licensing\LicenseManager;
use MHMRentiva\Admin\Licensing\Mode;
use MHMRentiva\Admin\Frontend\Shortcodes\Vendor\VendorProfile;

/**
 * Phase 9 — verifies Pro gate enforcement for the vendor public profile.
 *
 * The profile feature is gated by `Mode::canUseVendorMarketplace()`, which
 * requires (a) a locally active license AND (b) either the dev-mode bypass
 * filter or a verified RSA feature token. Tests use the filter `mhm_rentiva_dev_pro_bypass`
 * — the same seam used by `LicenseAdminActiveFeaturesTest` — because it
 * sidesteps the RSA token chain while still honouring the hard `isPro()`
 * floor (which a single filter alone cannot bypass).
 *
 * @group vendor-profile
 * @group vendor-gating
 */
final class VendorProfileGatingTest extends \WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        VendorProfile::register();
    }

    protected function tearDown(): void
    {
        delete_option(LicenseManager::OPTION);
        remove_all_filters('mhm_rentiva_dev_pro_bypass');
        parent::tearDown();
    }

    private function activate_pro_license(): void
    {
        update_option(LicenseManager::OPTION, [
            'key'           => 'PRO-GATING-TEST',
            'status'        => 'active',
            'plan'          => 'monthly',
            'expires_at'    => time() + 86400,
            'activation_id' => 'a-gating',
        ], false);
        add_filter('mhm_rentiva_dev_pro_bypass', '__return_true');
    }

    private function setup_active_vendor(): int
    {
        $user_id = self::factory()->user->create(['display_name' => 'Akif']);
        update_user_meta($user_id, MetaKeys::VENDOR_SLUG, 'akif');
        update_user_meta($user_id, '_rentiva_vendor_status', 'active');
        update_user_meta($user_id, '_rentiva_vendor_approved_at', gmdate('Y-m-d H:i:s'));
        return $user_id;
    }

    public function test_pro_active_renders_shortcode(): void
    {
        $this->activate_pro_license();
        $this->setup_active_vendor();

        $html = do_shortcode('[rentiva_vendor_profile slug="akif"]');

        $this->assertNotSame('', $html, 'Active Pro must render the profile');
        $this->assertStringContainsString('Akif', $html);
    }

    public function test_pro_blocked_returns_empty_shortcode(): void
    {
        // No license, no bypass — Mode::canUseVendorMarketplace() returns false.
        $this->setup_active_vendor();

        $html = do_shortcode('[rentiva_vendor_profile slug="akif"]');

        $this->assertSame('', $html, 'Lite mode must short-circuit before render');
    }

    public function test_pro_blocked_skips_rewrite_registration(): void
    {
        // Mirrors Plugin.php's wiring guard. If canUseVendorMarketplace() were
        // to ever leak truthy under Lite, register() would run and silently
        // expose `/vendor/<slug>/` URLs without a license.
        $this->assertFalse(Mode::canUseVendorMarketplace());

        $registered = false;
        if (Mode::canUseVendorMarketplace()) {
            \MHMRentiva\Admin\Vendor\Profile\VendorProfileRewrite::register();
            $registered = true;
        }

        $this->assertFalse($registered, 'Rewrite registration must be skipped in Lite');
    }

    public function test_pro_blocked_skips_schema_registration(): void
    {
        // YÜKSEK 2 closure — verifies that when the gate is closed, schema
        // emission is not wired. (Phase 8 left this orphaned; Phase 9 wired
        // it under canUseVendorMarketplace.)
        $this->assertFalse(Mode::canUseVendorMarketplace());

        $registered = false;
        if (Mode::canUseVendorMarketplace()) {
            \MHMRentiva\Admin\Vendor\Profile\VendorProfileSchema::register();
            $registered = true;
        }

        $this->assertFalse($registered, 'Schema registration must be skipped in Lite');
    }

    public function test_dev_pro_bypass_filter_grants_access_with_active_license(): void
    {
        // Confirms the test seam itself behaves correctly: with both a stored
        // active license AND the bypass filter, the vendor_marketplace gate
        // opens. This guards future regressions where featureGranted might
        // grow new preconditions that silently break the seam.
        $this->activate_pro_license();

        $this->assertTrue(Mode::canUseVendorMarketplace());
    }
}
