<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Vendor\Profile;

use MHMRentiva\Admin\Vendor\Profile\VendorProfileSettingsSave;

/**
 * @group vendor-profile
 * @group vendor-profile-settings
 */
final class VendorProfileSettingsSaveTest extends \WP_UnitTestCase
{
    public function test_validate_upload_accepts_jpeg(): void
    {
        $file = ['type' => 'image/jpeg', 'size' => 500_000];
        $result = VendorProfileSettingsSave::validate_upload($file);
        $this->assertNull($result);
    }

    public function test_validate_upload_accepts_png(): void
    {
        $file = ['type' => 'image/png', 'size' => 1_000_000];
        $result = VendorProfileSettingsSave::validate_upload($file);
        $this->assertNull($result);
    }

    public function test_validate_upload_rejects_gif(): void
    {
        $file = ['type' => 'image/gif', 'size' => 100_000];
        $result = VendorProfileSettingsSave::validate_upload($file);
        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    public function test_validate_upload_rejects_oversized_file(): void
    {
        $file = ['type' => 'image/jpeg', 'size' => 3_000_000]; // 3 MB
        $result = VendorProfileSettingsSave::validate_upload($file);
        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    public function test_handle_saves_phone_city_bio(): void
    {
        $user_id = self::factory()->user->create(['role' => 'rentiva_vendor']);

        VendorProfileSettingsSave::handle(
            $user_id,
            ['phone' => '+90 555 111 22 33', 'city' => 'Antalya', 'bio' => 'Test bio', 'slug' => ''],
            null
        );

        $this->assertSame('+90 555 111 22 33', get_user_meta($user_id, '_rentiva_vendor_phone', true));
        $this->assertSame('Antalya', get_user_meta($user_id, '_rentiva_vendor_city', true));
        $this->assertSame('Test bio', get_user_meta($user_id, '_rentiva_vendor_bio', true));
    }

    public function test_handle_with_empty_slug_preserves_existing_slug(): void
    {
        $user_id = self::factory()->user->create(['display_name' => 'Ali Test']);
        update_user_meta($user_id, \MHMRentiva\Admin\Core\MetaKeys::VENDOR_SLUG, 'ali-test');

        VendorProfileSettingsSave::handle(
            $user_id,
            ['phone' => '', 'city' => '', 'bio' => '', 'slug' => ''],
            null
        );

        $this->assertSame('ali-test', get_user_meta($user_id, \MHMRentiva\Admin\Core\MetaKeys::VENDOR_SLUG, true));
    }

    public function test_handle_with_new_slug_updates_and_stores_history(): void
    {
        $user_id = self::factory()->user->create(['display_name' => 'Veli Test']);
        update_user_meta($user_id, \MHMRentiva\Admin\Core\MetaKeys::VENDOR_SLUG, 'veli-test');

        VendorProfileSettingsSave::handle(
            $user_id,
            ['phone' => '', 'city' => '', 'bio' => '', 'slug' => 'veli-yeni'],
            null
        );

        $this->assertSame('veli-yeni', get_user_meta($user_id, \MHMRentiva\Admin\Core\MetaKeys::VENDOR_SLUG, true));
        $history = (array) get_user_meta($user_id, \MHMRentiva\Admin\Core\MetaKeys::VENDOR_SLUG_HISTORY, true);
        $this->assertContains('veli-test', $history);
    }

    public function test_handle_returns_error_on_invalid_file_type(): void
    {
        $user_id = self::factory()->user->create(['role' => 'rentiva_vendor']);
        $file = ['name' => 'test.gif', 'type' => 'image/gif', 'size' => 50_000, 'tmp_name' => '', 'error' => UPLOAD_ERR_OK];

        $result = VendorProfileSettingsSave::handle(
            $user_id,
            ['phone' => '', 'city' => '', 'bio' => '', 'slug' => ''],
            $file
        );

        $this->assertFalse($result['success']);
        $this->assertNotEmpty($result['error']);
        $this->assertSame('', (string) get_user_meta($user_id, '_rentiva_vendor_phone', true));
    }

    public function test_vendor_nav_contains_profil_tab(): void
    {
        $items = \MHMRentiva\Core\Dashboard\DashboardNavigation::get_items('vendor');
        $this->assertArrayHasKey('profil', $items);
        $this->assertNotEmpty($items['profil']['label']);
    }
}
