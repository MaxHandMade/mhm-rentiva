<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration;

use MHMRentiva\Admin\Core\Utilities\MetaQueryHelper;

class VehicleVisibilityFilterTest extends \WP_UnitTestCase
{
    private int $active_vehicle;
    private int $maintenance_vehicle;
    private int $no_status_vehicle;

    protected function setUp(): void
    {
        parent::setUp();

        $this->active_vehicle = wp_insert_post(array(
            'post_type'   => 'vehicle',
            'post_status' => 'publish',
            'post_title'  => 'Active Vehicle',
        ));
        update_post_meta($this->active_vehicle, '_mhm_vehicle_status', 'active');

        $this->maintenance_vehicle = wp_insert_post(array(
            'post_type'   => 'vehicle',
            'post_status' => 'publish',
            'post_title'  => 'Maintenance Vehicle',
        ));
        update_post_meta($this->maintenance_vehicle, '_mhm_vehicle_status', 'maintenance');

        $this->no_status_vehicle = wp_insert_post(array(
            'post_type'   => 'vehicle',
            'post_status' => 'publish',
            'post_title'  => 'Legacy Vehicle (no status meta)',
        ));
        // Intentionally no _mhm_vehicle_status meta — should default to active.
    }

    public function test_active_vehicle_meta_query_returns_correct_structure(): void
    {
        $query = MetaQueryHelper::get_active_vehicle_meta_query();
        $this->assertArrayHasKey('relation', $query);
        $this->assertSame('OR', $query['relation']);
    }

    public function test_active_filter_includes_active_vehicle(): void
    {
        $results = $this->query_with_active_filter();
        $this->assertContains($this->active_vehicle, $results);
    }

    public function test_active_filter_excludes_maintenance_vehicle(): void
    {
        $results = $this->query_with_active_filter();
        $this->assertNotContains($this->maintenance_vehicle, $results);
    }

    public function test_active_filter_includes_vehicle_without_status_meta(): void
    {
        $results = $this->query_with_active_filter();
        $this->assertContains($this->no_status_vehicle, $results);
    }

    public function test_inactive_vehicle_excluded(): void
    {
        $inactive_id = wp_insert_post(array(
            'post_type'   => 'vehicle',
            'post_status' => 'publish',
            'post_title'  => 'Inactive Vehicle',
        ));
        update_post_meta($inactive_id, '_mhm_vehicle_status', 'inactive');

        $results = $this->query_with_active_filter();
        $this->assertNotContains($inactive_id, $results);

        wp_delete_post($inactive_id, true);
    }

    private function query_with_active_filter(): array
    {
        $query = new \WP_Query(array(
            'post_type'      => 'vehicle',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => array(
                MetaQueryHelper::get_active_vehicle_meta_query(),
            ),
        ));

        return $query->posts;
    }

    protected function tearDown(): void
    {
        wp_delete_post($this->active_vehicle, true);
        wp_delete_post($this->maintenance_vehicle, true);
        wp_delete_post($this->no_status_vehicle, true);
        parent::tearDown();
    }
}
