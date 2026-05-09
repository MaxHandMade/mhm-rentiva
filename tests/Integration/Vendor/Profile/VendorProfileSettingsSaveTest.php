<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Vendor\Profile;

/**
 * @group vendor-profile
 * @group vendor-profile-settings
 */
final class VendorProfileSettingsSaveTest extends \WP_UnitTestCase
{
    public function test_validate_upload_accepts_jpeg(): void
    {
        $file = ['type' => 'image/jpeg', 'size' => 500_000];
        $result = \MHMRentiva\Admin\Vendor\Profile\VendorProfileSettingsSave::validate_upload($file);
        $this->assertNull($result);
    }

    public function test_validate_upload_accepts_png(): void
    {
        $file = ['type' => 'image/png', 'size' => 1_000_000];
        $result = \MHMRentiva\Admin\Vendor\Profile\VendorProfileSettingsSave::validate_upload($file);
        $this->assertNull($result);
    }

    public function test_validate_upload_rejects_gif(): void
    {
        $file = ['type' => 'image/gif', 'size' => 100_000];
        $result = \MHMRentiva\Admin\Vendor\Profile\VendorProfileSettingsSave::validate_upload($file);
        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    public function test_validate_upload_rejects_oversized_file(): void
    {
        $file = ['type' => 'image/jpeg', 'size' => 3_000_000]; // 3 MB
        $result = \MHMRentiva\Admin\Vendor\Profile\VendorProfileSettingsSave::validate_upload($file);
        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }
}
