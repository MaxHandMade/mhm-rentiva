<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Transfer;

use MHMRentiva\Admin\Transfer\Engine\TransferSearchEngine;

class TransferSearchEngineRouteFilterTest extends \WP_UnitTestCase
{
    private int $route_id = 0;
    private int $vehicle_with_route = 0;
    private int $vehicle_without_route = 0;

    public function setUp(): void
    {
        parent::setUp();
        global $wpdb;

        // Create locations first.
        $loc_table = $wpdb->prefix . 'rentiva_transfer_locations';
        $wpdb->insert($loc_table, [
            'id'             => 9901,
            'name'           => 'Test Origin',
            'city'           => 'TestCity',
            'type'           => 'airport',
            'is_active'      => 1,
            'allow_transfer' => 1,
        ]);
        $wpdb->insert($loc_table, [
            'id'             => 9902,
            'name'           => 'Test Dest',
            'city'           => 'TestCity',
            'type'           => 'city_center',
            'is_active'      => 1,
            'allow_transfer' => 1,
        ]);

        // Create a route.
        $routes_table = $wpdb->prefix . 'rentiva_transfer_routes';
        $wpdb->insert($routes_table, [
            'origin_id'      => 9901,
            'destination_id' => 9902,
            'distance_km'    => 50,
            'duration_min'   => 60,
            'pricing_method' => 'fixed',
            'base_price'     => 500.00,
            'min_price'      => 300.00,
            'max_price'      => 800.00,
        ]);
        $this->route_id = (int) $wpdb->insert_id;

        // Vehicle WITH route assigned.
        $this->vehicle_with_route = self::factory()->post->create([
            'post_type'   => 'vehicle',
            'post_status' => 'publish',
            'post_title'  => 'Transfer Van With Route',
        ]);
        update_post_meta($this->vehicle_with_route, '_rentiva_vehicle_service_type', 'transfer');
        update_post_meta($this->vehicle_with_route, '_rentiva_transfer_max_pax', 8);
        update_post_meta($this->vehicle_with_route, '_rentiva_transfer_max_luggage_score', 20);
        update_post_meta($this->vehicle_with_route, '_mhm_rentiva_transfer_routes', [$this->route_id]);
        update_post_meta($this->vehicle_with_route, '_mhm_rentiva_transfer_route_prices', wp_json_encode([$this->route_id => 650.00]));

        // Vehicle WITHOUT route assigned (has routes array but not this route).
        $this->vehicle_without_route = self::factory()->post->create([
            'post_type'   => 'vehicle',
            'post_status' => 'publish',
            'post_title'  => 'Transfer Van Without Route',
        ]);
        update_post_meta($this->vehicle_without_route, '_rentiva_vehicle_service_type', 'transfer');
        update_post_meta($this->vehicle_without_route, '_rentiva_transfer_max_pax', 8);
        update_post_meta($this->vehicle_without_route, '_rentiva_transfer_max_luggage_score', 20);
        update_post_meta($this->vehicle_without_route, '_mhm_rentiva_transfer_routes', [99999]); // different route
    }

    public function tearDown(): void
    {
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->prefix}rentiva_transfer_routes WHERE origin_id = 9901");
        $wpdb->query("DELETE FROM {$wpdb->prefix}rentiva_transfer_locations WHERE id IN (9901, 9902)");
        wp_delete_post($this->vehicle_with_route, true);
        wp_delete_post($this->vehicle_without_route, true);
        parent::tearDown();
    }

    public function test_vehicle_without_matching_route_is_excluded(): void
    {
        $results = TransferSearchEngine::search([
            'origin_id'      => 9901,
            'destination_id' => 9902,
            'date'           => wp_date('Y-m-d', strtotime('+7 days')),
            'time'           => '10:00',
            'adults'         => 2,
            'children'       => 0,
            'luggage_big'    => 0,
            'luggage_small'  => 0,
        ]);

        $ids = array_column($results, 'id');
        $this->assertContains($this->vehicle_with_route, $ids, 'Vehicle with matching route should be included');
        $this->assertNotContains($this->vehicle_without_route, $ids, 'Vehicle with different route should be excluded');
    }

    public function test_vendor_price_is_used_over_base_price(): void
    {
        $results = TransferSearchEngine::search([
            'origin_id'      => 9901,
            'destination_id' => 9902,
            'date'           => wp_date('Y-m-d', strtotime('+7 days')),
            'time'           => '10:00',
            'adults'         => 2,
            'children'       => 0,
            'luggage_big'    => 0,
            'luggage_small'  => 0,
        ]);

        $vehicle = null;
        foreach ($results as $r) {
            if ($r['id'] === $this->vehicle_with_route) {
                $vehicle = $r;
                break;
            }
        }

        $this->assertNotNull($vehicle, 'Vehicle should be in results');
        $this->assertEquals(650.00, $vehicle['price'], 'Should use vendor route price (650), not route base_price (500)');
    }
}
