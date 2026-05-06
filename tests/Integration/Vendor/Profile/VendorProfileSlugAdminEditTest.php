<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Vendor\Profile;

use MHMRentiva\Admin\Core\MetaKeys;
use MHMRentiva\Admin\Vendor\Profile\VendorProfileExtension;

/**
 * Regression for v4.38.1 YÜKSEK-1: admin slug edit must route through
 * VendorSlugManager::change_slug() to apply collision suffix and
 * canonical history sanitize. Direct update_user_meta() bypassed both.
 *
 * @group vendor-profile
 * @group vendor-slug
 * @group v4.38.1
 */
final class VendorProfileSlugAdminEditTest extends \WP_UnitTestCase
{
    private int $admin_id = 0;
    private int $vendor_a = 0;
    private int $vendor_b = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin_id = self::factory()->user->create(['role' => 'administrator']);
        wp_set_current_user($this->admin_id);

        $this->vendor_a = self::factory()->user->create(['display_name' => 'Vendor A']);
        $this->vendor_b = self::factory()->user->create(['display_name' => 'Vendor B']);
    }

    protected function tearDown(): void
    {
        unset($_POST['mhm_rentiva_vendor_slug'], $_REQUEST['_wpnonce']);
        parent::tearDown();
    }

    /**
     * Two vendors submit the same raw slug. Second must persist with collision suffix.
     */
    public function test_collision_resolves_with_suffix_when_two_vendors_submit_same_slug(): void
    {
        $this->save_slug_for_user($this->vendor_a, 'ali-otomotiv');
        $this->assertSame(
            'ali-otomotiv',
            get_user_meta($this->vendor_a, MetaKeys::VENDOR_SLUG, true),
            'first vendor takes the bare slug'
        );

        $this->save_slug_for_user($this->vendor_b, 'ali-otomotiv');
        $this->assertSame(
            'ali-otomotiv-2',
            get_user_meta($this->vendor_b, MetaKeys::VENDOR_SLUG, true),
            'second vendor must receive collision suffix, not overwrite first'
        );
    }

    /**
     * Empty slug submission deletes the existing meta cleanly.
     */
    public function test_empty_slug_submission_deletes_existing_meta(): void
    {
        update_user_meta($this->vendor_a, MetaKeys::VENDOR_SLUG, 'starting-slug');

        $this->save_slug_for_user($this->vendor_a, '');

        $this->assertSame(
            '',
            (string) get_user_meta($this->vendor_a, MetaKeys::VENDOR_SLUG, true),
            'empty submission must clear existing slug'
        );
    }

    private function save_slug_for_user(int $user_id, string $raw_slug): void
    {
        $_POST['mhm_rentiva_vendor_slug'] = $raw_slug;
        $_REQUEST['_wpnonce'] = wp_create_nonce('update-user_' . $user_id);

        VendorProfileExtension::save_location_field($user_id);
    }
}
