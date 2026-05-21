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
     * Test Case 4 (RED): expand_to_city=true must include vehicles at any
     * location in the SAME city as the requested location_id, not just exact match.
     *
     * Bug context: TransferSearchEngine had no location filter, so a route
     * search "Kadıköy → Esenler" (both İstanbul) returned vehicles parked in
     * Ankara and Antalya. Fix extends this helper with city expansion so
     * Transfer can reuse the same 3-layer hybrid filter as rental.
     */
    public function test_expand_to_city_includes_all_locations_in_same_city()
    {
        global $wpdb;
        // setUp seeded id=1 (Location A) and id=2 (Location B) without city.
        // Annotate them with cities, then add a third location sharing city with Location 1.
        $wpdb->update($this->table_name, ['city' => 'TestCity1'], ['id' => 1]);
        $wpdb->update($this->table_name, ['city' => 'TestCity2'], ['id' => 2]);
        $wpdb->insert($this->table_name, [
            'id'           => 3,
            'name'         => 'Location C (same city as A)',
            'city'         => 'TestCity1',
            'is_active'    => 1,
            'allow_rental' => 1,
        ]);

        // Vehicle at Location 3 — same city as Location 1.
        $vehicle_id = $this->factory->post->create(['post_type' => 'vehicle']);
        update_post_meta($vehicle_id, MetaKeys::VEHICLE_LOCATION_ID, 3);

        // Search Location 1 WITH expand_to_city=true → must find vehicle at Location 3.
        $subquery = QueryHelper::get_location_subquery(1, true);
        $found = $this->query_vehicles_with_subquery($subquery);
        $this->assertContains(
            $vehicle_id,
            $found,
            'Vehicle at Location 3 (same city as 1) must be found when expand_to_city=true.'
        );

        // Sanity: WITHOUT expansion, vehicle at Location 3 must NOT match Location 1.
        $subquery_strict = QueryHelper::get_location_subquery(1, false);
        $found_strict = $this->query_vehicles_with_subquery($subquery_strict);
        $this->assertNotContains(
            $vehicle_id,
            $found_strict,
            'Vehicle at Location 3 must NOT be found for Location 1 when expand_to_city=false.'
        );
    }

    /**
     * Test Case 5 (regression baseline): default (no second arg) preserves the
     * existing strict location_id matching used by rental search.
     */
    public function test_expand_to_city_false_preserves_existing_behavior()
    {
        global $wpdb;
        // Annotate two seeded locations to share a city.
        $wpdb->update($this->table_name, ['city' => 'CityX'], ['id' => 1]);
        $wpdb->update($this->table_name, ['city' => 'CityX'], ['id' => 2]);

        $vehicle_loc1 = $this->factory->post->create(['post_type' => 'vehicle']);
        update_post_meta($vehicle_loc1, MetaKeys::VEHICLE_LOCATION_ID, 1);

        $vehicle_loc2 = $this->factory->post->create(['post_type' => 'vehicle']);
        update_post_meta($vehicle_loc2, MetaKeys::VEHICLE_LOCATION_ID, 2);

        // Default call (no second arg) and explicit false must produce identical SQL.
        $sql_default  = QueryHelper::get_location_subquery(1);
        $sql_explicit = QueryHelper::get_location_subquery(1, false);
        $this->assertSame($sql_default, $sql_explicit, 'Default and explicit false must produce identical SQL fragment.');

        // Without expansion, vehicle at Location 2 must NOT match strict search for Location 1
        // (even though they share a city). This is the existing rental contract.
        $found = $this->query_vehicles_with_subquery($sql_default);
        $this->assertContains($vehicle_loc1, $found, 'Vehicle at Location 1 found in strict search.');
        $this->assertNotContains(
            $vehicle_loc2,
            $found,
            'Vehicle at Location 2 must NOT be found in strict search for Location 1 (no city expansion).'
        );
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
