<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Transfer;

use MHMRentiva\Admin\Transfer\Engine\LocationProvider;

class LocationProviderCityFilterTest extends \WP_UnitTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        global $wpdb;
        $table = $wpdb->prefix . 'rentiva_transfer_locations';

        $wpdb->insert($table, [
            'name'           => 'IST Airport',
            'city'           => 'Istanbul',
            'type'           => 'airport',
            'is_active'      => 1,
            'allow_transfer' => 1,
        ]);
        $wpdb->insert($table, [
            'name'           => 'Kadikoy Center',
            'city'           => 'Istanbul',
            'type'           => 'city_center',
            'is_active'      => 1,
            'allow_transfer' => 1,
        ]);
        $wpdb->insert($table, [
            'name'           => 'Esenboga Airport',
            'city'           => 'Ankara',
            'type'           => 'airport',
            'is_active'      => 1,
            'allow_transfer' => 1,
        ]);

        LocationProvider::clear_cache();
    }

    public function tearDown(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'rentiva_transfer_locations';
        $wpdb->query("DELETE FROM {$table} WHERE name IN ('IST Airport','Kadikoy Center','Esenboga Airport')");
        LocationProvider::clear_cache();
        parent::tearDown();
    }

    public function test_get_by_city_returns_only_matching_city(): void
    {
        $istanbul = LocationProvider::get_by_city('Istanbul', 'transfer');

        $names = array_column($istanbul, 'name');
        $this->assertContains('IST Airport', $names);
        $this->assertContains('Kadikoy Center', $names);
        $this->assertNotContains('Esenboga Airport', $names);
    }

    public function test_get_by_city_returns_empty_for_unknown_city(): void
    {
        $result = LocationProvider::get_by_city('Izmir', 'transfer');
        $this->assertEmpty($result);
    }

    public function test_get_by_city_is_case_insensitive(): void
    {
        $result = LocationProvider::get_by_city('istanbul', 'transfer');
        $this->assertNotEmpty($result);
    }
}
