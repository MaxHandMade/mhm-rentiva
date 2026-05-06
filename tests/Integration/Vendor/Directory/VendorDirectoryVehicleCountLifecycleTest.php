<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Vendor\Directory;

use MHMRentiva\Admin\Vendor\Directory\VendorDirectoryProvider;
use WP_UnitTestCase;

/**
 * Regression for v4.38.1 ORTA-1: directory card vehicle_count must respect
 * `_mhm_vehicle_lifecycle_status='active'` parity with VendorProfileProvider's
 * collect_vehicles(). v4.38.0 baseline counted all `publish` vehicles
 * regardless of lifecycle, causing inconsistency between directory card and
 * profile vehicle list (paused/withdrawn vehicles inflated card count).
 *
 * @group vendor-directory
 * @group v4.38.1
 * @covers \MHMRentiva\Admin\Vendor\Directory\VendorDirectoryProvider
 */
final class VendorDirectoryVehicleCountLifecycleTest extends WP_UnitTestCase
{
    private int $vendor_id = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vendor_id = self::factory()->user->create([
            'role'         => 'vendor',
            'display_name' => 'Vendor V',
        ]);
        update_user_meta($this->vendor_id, '_rentiva_vendor_status', 'active');
        update_user_meta($this->vendor_id, '_rentiva_vendor_city', 'Istanbul');
        update_user_meta($this->vendor_id, '_rentiva_vendor_reliability_score', 90);
        update_user_meta(
            $this->vendor_id,
            '_rentiva_vendor_approved_at',
            gmdate('Y-m-d H:i:s', time() - (200 * DAY_IN_SECONDS))
        );
        update_user_meta($this->vendor_id, '_rentiva_vendor_slug', 'vendor-v');
    }

    public function test_vehicle_count_excludes_paused_lifecycle_vehicles(): void
    {
        $this->create_vehicle('active');
        $this->create_vehicle('active');
        $this->create_vehicle('paused');
        $this->create_vehicle('paused');
        $this->create_vehicle('paused');

        $result = VendorDirectoryProvider::query([]);
        $card = $this->find_card_for($result['vendors'], $this->vendor_id);

        $this->assertNotNull($card, 'vendor must appear in directory');
        $this->assertSame(
            2,
            $card['vehicle_count'],
            'vehicle_count must count active lifecycle vehicles only, not paused'
        );
    }

    public function test_vehicle_count_zero_when_all_published_vehicles_are_paused(): void
    {
        $this->create_vehicle('paused');
        $this->create_vehicle('paused');
        $this->create_vehicle('paused');
        $this->create_vehicle('paused');

        $result = VendorDirectoryProvider::query([]);
        $card = $this->find_card_for($result['vendors'], $this->vendor_id);

        $this->assertNotNull($card, 'vendor still listable even with no active vehicles');
        $this->assertSame(0, $card['vehicle_count']);
    }

    private function create_vehicle(string $lifecycle): int
    {
        $vehicle_id = self::factory()->post->create([
            'post_type'   => 'vehicle',
            'post_status' => 'publish',
            'post_author' => $this->vendor_id,
        ]);
        update_post_meta($vehicle_id, '_mhm_vehicle_lifecycle_status', $lifecycle);

        return $vehicle_id;
    }

    /**
     * @param array<int, array<string, mixed>> $cards
     * @return array<string, mixed>|null
     */
    private function find_card_for(array $cards, int $vendor_id): ?array
    {
        foreach ($cards as $card) {
            if ((int) ($card['id'] ?? 0) === $vendor_id) {
                return $card;
            }
        }
        return null;
    }
}
