<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Vendor\Profile;

use MHMRentiva\Admin\Vendor\Profile\VendorProfileProvider;

/**
 * v4.37.2 regression: vehicle thumbnail cascade — featured image first,
 * then Rentiva gallery first attachment, then empty (compact card).
 *
 * @group vendor-profile
 * @group vendor-vehicles
 */
final class VendorVehicleThumbnailCascadeTest extends \WP_UnitTestCase
{
    public function test_thumb_empty_when_no_featured_and_no_gallery(): void
    {
        $vendor_id  = $this->seed_vendor();
        $vehicle_id = $this->seed_active_vehicle($vendor_id, 'Bare Vehicle');

        $data = $this->fetch_profile($vendor_id);

        $this->assertNotEmpty($data['vehicles']);
        $vehicle = $this->vehicle_by_title($data['vehicles'], 'Bare Vehicle');
        $this->assertSame('', $vehicle['thumb'], 'Compact mode triggers when no image source is available.');
    }

    public function test_thumb_uses_gallery_first_attachment_when_no_featured_image(): void
    {
        $vendor_id  = $this->seed_vendor();
        $vehicle_id = $this->seed_active_vehicle($vendor_id, 'Gallery Only Vehicle');

        $att_id = self::factory()->attachment->create_upload_object(
            DIR_TESTDATA . '/images/canola.jpg',
            $vehicle_id
        );
        update_post_meta(
            $vehicle_id,
            '_mhm_rentiva_gallery_images',
            wp_json_encode([['id' => $att_id]])
        );

        $data    = $this->fetch_profile($vendor_id);
        $vehicle = $this->vehicle_by_title($data['vehicles'], 'Gallery Only Vehicle');

        $this->assertNotSame('', $vehicle['thumb']);
        $this->assertStringContainsString('canola', $vehicle['thumb']);
    }

    public function test_thumb_prefers_featured_image_over_gallery(): void
    {
        $vendor_id  = $this->seed_vendor();
        $vehicle_id = $this->seed_active_vehicle($vendor_id, 'Both Sources Vehicle');

        $featured_id = self::factory()->attachment->create_upload_object(
            DIR_TESTDATA . '/images/canola.jpg',
            $vehicle_id
        );
        set_post_thumbnail($vehicle_id, $featured_id);

        $gallery_id = self::factory()->attachment->create_upload_object(
            DIR_TESTDATA . '/images/test-image.jpg',
            $vehicle_id
        );
        update_post_meta(
            $vehicle_id,
            '_mhm_rentiva_gallery_images',
            wp_json_encode([['id' => $gallery_id]])
        );

        $data    = $this->fetch_profile($vendor_id);
        $vehicle = $this->vehicle_by_title($data['vehicles'], 'Both Sources Vehicle');

        $this->assertStringContainsString('canola', $vehicle['thumb'], 'Featured image must win over gallery.');
        $this->assertStringNotContainsString('test-image', $vehicle['thumb']);
    }

    private function seed_vendor(): int
    {
        $uid = self::factory()->user->create(['role' => 'rentiva_vendor']);
        update_user_meta($uid, '_rentiva_vendor_status', 'active');
        update_user_meta($uid, '_rentiva_vendor_slug', 'thumb-cascade-vendor-' . $uid);
        return $uid;
    }

    private function seed_active_vehicle(int $vendor_id, string $title): int
    {
        $vid = wp_insert_post([
            'post_type'   => 'vehicle',
            'post_status' => 'publish',
            'post_author' => $vendor_id,
            'post_title'  => $title,
        ]);
        update_post_meta($vid, '_mhm_vehicle_lifecycle_status', 'active');
        // The collect_vehicles query orders by `_mhm_rentiva_rating_average`
        // and uses `meta_key`, which excludes posts without that meta. Seed a
        // 0 rating so the test vehicle survives the ordering filter — the
        // template hides the rating block when count === 0 anyway.
        update_post_meta($vid, '_mhm_rentiva_rating_average', 0);
        update_post_meta($vid, '_mhm_rentiva_rating_count', 0);
        return (int) $vid;
    }

    /**
     * @return array<string,mixed>
     */
    private function fetch_profile(int $vendor_id): array
    {
        VendorProfileProvider::clear_cache_for_user($vendor_id);
        return VendorProfileProvider::get_profile_data($vendor_id);
    }

    /**
     * @param array<int,array<string,mixed>> $vehicles
     * @return array<string,mixed>
     */
    private function vehicle_by_title(array $vehicles, string $title): array
    {
        foreach ($vehicles as $v) {
            if ($v['title'] === $title) {
                return $v;
            }
        }
        $this->fail('Vehicle "' . $title . '" not found in profile data.');
    }
}
