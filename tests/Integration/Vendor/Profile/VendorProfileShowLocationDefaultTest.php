<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Vendor\Profile;

use MHMRentiva\Admin\Frontend\Shortcodes\Vendor\VendorProfile;
use MHMRentiva\Admin\Licensing\LicenseManager;

/**
 * v4.37.2 regression: show_location default flipped to 'no' so the hero
 * city meta is no longer duplicated by an empty-content section. Layouts
 * that want the section render explicitly with show_location="yes".
 *
 * @group vendor-profile
 * @group vendor-shortcode
 */
final class VendorProfileShowLocationDefaultTest extends \WP_UnitTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        VendorProfile::register();
        // Pro bypass is driven entirely by the mhm_rentiva_dev_pro_bypass
        // filter below (add_filter(..., '__return_true') always wins over
        // Mode::featureGranted()'s constant-derived default). Do NOT also
        // define('MHM_RENTIVA_DEV_PRO', true) here: PHP constants cannot be
        // undefined within a process, so doing so would permanently flip the
        // constant for every later-running test in the same PHPUnit run --
        // including ModeDevBypassTest's assertions about the *default*
        // (no-filter) bypass behavior.
        update_option(LicenseManager::OPTION, [
            'key'           => 'TEST-DEV-001',
            'status'        => 'active',
            'plan'          => 'monthly',
            'expires_at'    => time() + 86400,
            'activation_id' => 'a1',
        ], false);
        add_filter('mhm_rentiva_dev_pro_bypass', '__return_true');
    }

    public function tearDown(): void
    {
        delete_option(LicenseManager::OPTION);
        remove_all_filters('mhm_rentiva_dev_pro_bypass');
        parent::tearDown();
    }

    public function test_show_location_section_hidden_by_default_but_hero_city_remains(): void
    {
        $vendor_id = $this->seed_vendor('default-location-vendor');

        $html = do_shortcode('[rentiva_vendor_profile slug="default-location-vendor"]');

        $this->assertStringNotContainsString('mhm-vendor-location', $html, 'Location section must be hidden by default.');
        $this->assertStringContainsString('Antalya', $html, 'City still surfaces via the hero meta.');
    }

    public function test_show_location_yes_renders_section_explicitly(): void
    {
        $vendor_id = $this->seed_vendor('explicit-location-vendor');

        $html = do_shortcode('[rentiva_vendor_profile slug="explicit-location-vendor" show_location="yes"]');

        $this->assertStringContainsString('mhm-vendor-location', $html, 'Explicit show_location="yes" must render the section.');
    }

    private function seed_vendor(string $slug): int
    {
        $user_id = self::factory()->user->create([
            'display_name' => ucwords(str_replace('-', ' ', $slug)),
            'role'         => 'rentiva_vendor',
        ]);
        update_user_meta($user_id, '_rentiva_vendor_slug', $slug);
        update_user_meta($user_id, '_rentiva_vendor_status', 'active');
        update_user_meta($user_id, '_rentiva_vendor_approved_at', gmdate('Y-m-d H:i:s'));
        update_user_meta($user_id, '_rentiva_vendor_city', 'Antalya');
        update_user_meta($user_id, '_mhm_rentiva_vendor_city', 'Antalya');
        return $user_id;
    }
}
