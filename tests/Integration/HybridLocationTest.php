<?php

namespace MHMRentiva\Tests\Integration;

use MHMRentiva\Admin\Core\MetaKeys;
use MHMRentiva\Admin\Core\QueryHelper;
use MHMRentiva\Admin\Settings\Core\SettingsCore;

class HybridLocationTest extends \WP_UnitTestCase
{
    private $table_name;

    public function setUp(): void
    {
        parent::setUp();
        global $wpdb;
        
        // Resolve table name similar to LocationProvider
        $new_table = $wpdb->prefix . 'rentiva_transfer_locations';
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $new_table));
        $this->table_name = ($table_exists === $new_table) ? $new_table : $wpdb->prefix . 'mhm_rentiva_transfer_locations';

        // Clear existing locations for a clean test
        $wpdb->query("TRUNCATE TABLE {$this->table_name}");

        // Seed two locations
        $wpdb->insert($this->table_name, [
            'id' => 1,
            'name' => 'Location A',
            'is_active' => 1,
            'allow_rental' => 1
        ]);
        $wpdb->insert($this->table_name, [
            'id' => 2,
            'name' => 'Location B',
            'is_active' => 1,
            'allow_rental' => 1
        ]);
    }

    /**
     * Test Case 1: Vehicle with specific location A
     */
    public function test_vehicle_with_specific_location()
    {
        $vehicle_id = $this->factory->post->create(['post_type' => 'vehicle']);
        update_post_meta($vehicle_id, MetaKeys::VEHICLE_LOCATION_ID, 1);

        // Search for Location 1
        $subquery = QueryHelper::get_location_subquery(1);
        $found = $this->query_vehicles_with_subquery($subquery);
        $this->assertContains($vehicle_id, $found, "Vehicle with Location A should be found when searching for Location 1");

        // Search for Location 2
        $subquery = QueryHelper::get_location_subquery(2);
        $found = $this->query_vehicles_with_subquery($subquery);
        $this->assertNotContains($vehicle_id, $found, "Vehicle with Location A should NOT be found when searching for Location 2");
    }

    /**
     * Test Case 2: Inheritance - No vehicle location, but Global Default is set
     */
    public function test_inheritance_global_default()
    {
        $vehicle_id = $this->factory->post->create(['post_type' => 'vehicle']);
        delete_post_meta($vehicle_id, MetaKeys::VEHICLE_LOCATION_ID);

        // Set Global Default to Location 2
        update_option('mhm_rentiva_settings', array_merge(
            (array)get_option('mhm_rentiva_settings', []),
            ['mhm_rentiva_default_rental_location' => 2]
        ));

        // Search for Location 2
        $subquery = QueryHelper::get_location_subquery(2);
        $found = $this->query_vehicles_with_subquery($subquery);
        $this->assertContains($vehicle_id, $found, "Vehicle with no location should inherit Global Default (Location 2)");

        // Search for Location 1
        $subquery = QueryHelper::get_location_subquery(1);
        $found = $this->query_vehicles_with_subquery($subquery);
        $this->assertNotContains($vehicle_id, $found, "Vehicle inheriting Location 2 should NOT be found when searching for Location 1");
    }

    /**
     * Test Case 3: Inheritance - No vehicle location, but Vendor (Author) location is set
     */
    public function test_inheritance_vendor_meta()
    {
        $vendor_id = $this->factory->user->create(['role' => 'author']);
        $vehicle_id = $this->factory->post->create([
            'post_type' => 'vehicle',
            'post_author' => $vendor_id
        ]);
        
        // Ensure no vehicle location
        delete_post_meta($vehicle_id, MetaKeys::VEHICLE_LOCATION_ID);

        // Set Vendor Location to Location 1
        update_user_meta($vendor_id, MetaKeys::VENDOR_LOCATION_ID, 1);

        // Search for Location 1
        $subquery = QueryHelper::get_location_subquery(1);
        $found = $this->query_vehicles_with_subquery($subquery);
        $this->assertContains($vehicle_id, $found, "Vehicle should inherit location from Vendor (Author)");

        // Search for Location 2
        $subquery = QueryHelper::get_location_subquery(2);
        $found = $this->query_vehicles_with_subquery($subquery);
        $this->assertNotContains($vehicle_id, $found, "Vehicle inheriting Vendor Location 1 should NOT be found for Location 2");
    }

    /**
     * Helper to run WP_Query with specific subquery
     */
    private function query_vehicles_with_subquery(string $subquery): array
    {
        $filter = function ($where) use ($subquery) {
            return $where . $subquery;
        };

        add_filter('posts_where', $filter);
        $query = new \WP_Query([
            'post_type' => 'vehicle',
            'post_status' => 'publish',
            'fields' => 'ids'
        ]);
        remove_filter('posts_where', $filter);

        return $query->posts;
    }
}
