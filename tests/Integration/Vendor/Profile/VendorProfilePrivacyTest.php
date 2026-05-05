<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Vendor\Profile;

use MHMRentiva\Admin\Core\MetaKeys;
use MHMRentiva\Admin\Licensing\LicenseManager;

/**
 * @group vendor-profile
 * @group vendor-privacy
 */
final class VendorProfilePrivacyTest extends \WP_UnitTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        \MHMRentiva\Admin\Frontend\Shortcodes\Vendor\VendorProfile::register();
        if (!defined('MHM_RENTIVA_DEV_PRO')) {
            define('MHM_RENTIVA_DEV_PRO', true);
        }
        // Same gating pattern as VendorProfileShortcodeTest — license seed +
        // dev-pro filter so Mode::canUseVendorMarketplace() returns true.
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

    private function make_vendor_with_sensitive_data(): int
    {
        $user_id = self::factory()->user->create(['display_name' => 'Akif Otomotiv']);
        update_user_meta($user_id, MetaKeys::VENDOR_SLUG, 'akif');
        update_user_meta($user_id, '_rentiva_vendor_status', 'active');
        update_user_meta($user_id, '_rentiva_vendor_approved_at', gmdate('Y-m-d H:i:s'));
        update_user_meta($user_id, '_rentiva_vendor_phone', '+90-555-1234567');
        update_user_meta($user_id, '_rentiva_vendor_iban', 'TR000011112222333344');
        update_user_meta($user_id, '_rentiva_vendor_tax_number', '1234567890');
        update_user_meta($user_id, '_rentiva_vendor_account_holder', 'Akif Soyadı');
        return $user_id;
    }

    public function test_phone_never_in_rendered_output(): void
    {
        $this->make_vendor_with_sensitive_data();
        $html = do_shortcode('[rentiva_vendor_profile slug="akif"]');

        $this->assertStringNotContainsString('+90-555-1234567', $html);
        $this->assertStringNotContainsString('555-1234567', $html);
    }

    public function test_iban_never_in_rendered_output(): void
    {
        $this->make_vendor_with_sensitive_data();
        $html = do_shortcode('[rentiva_vendor_profile slug="akif"]');

        $this->assertStringNotContainsString('TR000011112222333344', $html);
        $this->assertStringNotContainsString('11112222', $html);
    }

    public function test_tax_and_account_holder_never_in_rendered_output(): void
    {
        $this->make_vendor_with_sensitive_data();
        $html = do_shortcode('[rentiva_vendor_profile slug="akif"]');

        $this->assertStringNotContainsString('1234567890', $html);
        $this->assertStringNotContainsString('Akif Soyadı', $html);
    }

    public function test_bio_html_is_kses_filtered(): void
    {
        $user_id = self::factory()->user->create(['display_name' => 'Akif']);
        update_user_meta($user_id, MetaKeys::VENDOR_SLUG, 'akif-x');
        update_user_meta($user_id, '_rentiva_vendor_status', 'active');
        update_user_meta($user_id, '_rentiva_vendor_approved_at', gmdate('Y-m-d H:i:s'));
        update_user_meta($user_id, '_rentiva_vendor_bio', '<script>alert(1)</script><strong>OK</strong>');

        $html = do_shortcode('[rentiva_vendor_profile slug="akif-x"]');

        // wp_kses_post() strips disallowed tags (no <script>, no </script>)
        // but preserves their inner text content as plain text. The XSS
        // contract is "no executable script reaches the browser" — tag
        // removal satisfies that. We do NOT assert that the literal text
        // "alert(1)" is gone, because wp_kses_post() does not promise that.
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('</script>', $html);
        $this->assertStringContainsString('<strong>OK</strong>', $html);
    }
}
