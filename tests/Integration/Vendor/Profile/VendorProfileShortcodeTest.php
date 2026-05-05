<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Vendor\Profile;

use MHMRentiva\Admin\Core\MetaKeys;
use MHMRentiva\Admin\Frontend\Shortcodes\Vendor\VendorProfile;
use MHMRentiva\Admin\Licensing\LicenseManager;

/**
 * @group vendor-profile
 * @group vendor-shortcode
 */
final class VendorProfileShortcodeTest extends \WP_UnitTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        VendorProfile::register();
        if (!defined('MHM_RENTIVA_DEV_PRO')) {
            define('MHM_RENTIVA_DEV_PRO', true);
        }
        // Seed an active license + force the dev-mode bypass filter so
        // Mode::canUseVendorMarketplace() returns true. WP_DEBUG is not
        // set in the test bootstrap, so the constant alone is insufficient.
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

    private function make_active_vendor(string $slug): int
    {
        $user_id = self::factory()->user->create(['display_name' => 'Akif Otomotiv']);
        update_user_meta($user_id, MetaKeys::VENDOR_SLUG, $slug);
        update_user_meta($user_id, '_rentiva_vendor_status', 'active');
        update_user_meta($user_id, '_rentiva_vendor_approved_at', gmdate('Y-m-d H:i:s'));
        update_user_meta($user_id, '_rentiva_vendor_city', 'Antalya');
        return $user_id;
    }

    public function test_shortcode_renders_vendor_name_in_hero(): void
    {
        $this->make_active_vendor('akif-otomotiv');

        $html = do_shortcode('[rentiva_vendor_profile slug="akif-otomotiv"]');

        $this->assertStringContainsString('Akif Otomotiv', $html);
        $this->assertStringContainsString('mhm-rentiva-vendor-profile', $html);
    }

    public function test_shortcode_returns_empty_for_missing_slug(): void
    {
        $html = do_shortcode('[rentiva_vendor_profile slug="nonexistent"]');

        $this->assertSame('', $html);
    }

    public function test_shortcode_returns_empty_for_blank_slug_attribute(): void
    {
        $html = do_shortcode('[rentiva_vendor_profile]');

        $this->assertSame('', $html);
    }

    public function test_shortcode_renders_about_when_bio_present(): void
    {
        $user_id = $this->make_active_vendor('akif');
        update_user_meta($user_id, '_rentiva_vendor_bio', 'Trusted local rental.');

        $html = do_shortcode('[rentiva_vendor_profile slug="akif"]');

        $this->assertStringContainsString('Trusted local rental.', $html);
        $this->assertStringContainsString('mhm-vendor-about', $html);
    }

    public function test_shortcode_skips_about_when_show_about_no(): void
    {
        $user_id = $this->make_active_vendor('akif');
        update_user_meta($user_id, '_rentiva_vendor_bio', 'Trusted local rental.');

        $html = do_shortcode('[rentiva_vendor_profile slug="akif" show_about="no"]');

        $this->assertStringNotContainsString('mhm-vendor-about', $html);
    }

    public function test_shortcode_renders_empty_state_when_no_vehicles(): void
    {
        $this->make_active_vendor('akif');

        $html = do_shortcode('[rentiva_vendor_profile slug="akif"]');

        $this->assertStringContainsString('mhm-vendor-vehicles-empty', $html);
    }

    /**
     * Regression — Phase 6 reviewer YÜKSEK-5: shortcode max_vehicles attribute
     * was declared but ignored by the Provider (hardcoded limit=6). Site owners
     * writing max_vehicles="2" got 6 cards silently. Provider now honors it
     * and the cache key includes the cap so per-shortcode overrides cannot
     * pollute each other.
     */
    public function test_max_vehicles_attribute_caps_vehicle_count(): void
    {
        $user_id = $this->make_active_vendor('akif-cap');
        for ($i = 0; $i < 5; $i++) {
            $vid = self::factory()->post->create([
                'post_type'   => 'vehicle',
                'post_author' => $user_id,
                'post_status' => 'publish',
                'post_title'  => "Vehicle $i",
            ]);
            update_post_meta($vid, '_mhm_vehicle_lifecycle_status', 'active');
            // Provider's collect_vehicles() uses meta_key=_mhm_rentiva_rating_average
            // for orderby; without the meta the post is excluded by WP_Query's
            // meta_key INNER JOIN. Match the seeding pattern from VendorProfileProviderTest.
            update_post_meta($vid, '_mhm_rentiva_rating_average', 4.0);
        }
        $html       = do_shortcode('[rentiva_vendor_profile slug="akif-cap" max_vehicles="2"]');
        // Count exact card class (not the subclasses ‑title / ‑rating that share the prefix).
        $card_count = substr_count($html, 'class="mhm-vendor-vehicle-card"');
        $this->assertSame(2, $card_count, 'max_vehicles shortcode attribute should cap rendered vehicle cards.');
    }

    /**
     * Regression — Phase 6 reviewer ORTA-4: vendor display_name is rendered
     * via esc_html() in the hero partial. A malicious display_name containing
     * <script> must not leak into the public profile page.
     */
    public function test_display_name_is_escaped_in_hero(): void
    {
        global $wpdb;
        $user_id = self::factory()->user->create();
        update_user_meta($user_id, MetaKeys::VENDOR_SLUG, 'xss-test');
        update_user_meta($user_id, '_rentiva_vendor_status', 'active');
        update_user_meta($user_id, '_rentiva_vendor_approved_at', gmdate('Y-m-d H:i:s'));

        // wp_insert_user / wp_update_user sanitize display_name and strip <script>.
        // Bypass that to seed an unsanitized stored value — the contract under
        // test is the render-layer escape (esc_html()) in vendor-profile-hero.php,
        // not WP's user-input sanitizer.
        $wpdb->update($wpdb->users, ['display_name' => '<script>xss</script>'], ['ID' => $user_id]);
        clean_user_cache($user_id);

        $html = do_shortcode('[rentiva_vendor_profile slug="xss-test"]');

        $this->assertStringNotContainsString('<script>xss</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }
}
