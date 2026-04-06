<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Admin\Testing;

use MHMRentiva\Admin\Testing\DemoDataProvider;
use WP_UnitTestCase;

/**
 * DemoDataProvider Test Suite
 *
 * Verifies locale-aware demo data structure and counts.
 *
 * @package MHMRentiva\Tests\Unit\Admin\Testing
 * @since   4.25.1
 */
final class DemoDataProviderTest extends WP_UnitTestCase
{
    /**
     * Required vehicle array keys (16 total).
     */
    private const VEHICLE_KEYS = array(
        'title',
        'brand',
        'model',
        'year',
        'category',
        'price_per_day',
        'color',
        'engine_power',
        'license_plate',
        'mileage',
        'seats',
        'doors',
        'transmission',
        'fuel_type',
        'features',
        'image_file',
    );

    /**
     * Required customer array keys.
     */
    private const CUSTOMER_KEYS = array( 'name', 'first', 'last', 'email', 'phone' );

    // -------------------------------------------------------------------------
    // Vehicles
    // -------------------------------------------------------------------------

    public function test_get_vehicles_returns_five_entries(): void
    {
        $vehicles = DemoDataProvider::get_vehicles();

        $this->assertCount(5, $vehicles);
    }

    public function test_vehicle_has_required_keys(): void
    {
        $vehicles = DemoDataProvider::get_vehicles();

        foreach ($vehicles as $index => $vehicle) {
            foreach (self::VEHICLE_KEYS as $key) {
                $this->assertArrayHasKey(
                    $key,
                    $vehicle,
                    sprintf('Vehicle at index %d is missing key "%s".', $index, $key)
                );
            }
        }
    }

    // -------------------------------------------------------------------------
    // Customers
    // -------------------------------------------------------------------------

    public function test_get_customers_returns_eight_entries(): void
    {
        $customers = DemoDataProvider::get_customers();

        $this->assertCount(8, $customers);
    }

    public function test_customer_has_required_keys(): void
    {
        $customers = DemoDataProvider::get_customers();

        foreach ($customers as $index => $customer) {
            foreach (self::CUSTOMER_KEYS as $key) {
                $this->assertArrayHasKey(
                    $key,
                    $customer,
                    sprintf('Customer at index %d is missing key "%s".', $index, $key)
                );
            }
        }
    }

    // -------------------------------------------------------------------------
    // Locations
    // -------------------------------------------------------------------------

    public function test_get_locations_returns_four_entries(): void
    {
        $locations = DemoDataProvider::get_locations();

        $this->assertCount(4, $locations);
    }

    // -------------------------------------------------------------------------
    // Addons
    // -------------------------------------------------------------------------

    public function test_get_addons_returns_three_entries(): void
    {
        $addons = DemoDataProvider::get_addons();

        $this->assertCount(3, $addons);
    }

    // -------------------------------------------------------------------------
    // Locale detection
    // -------------------------------------------------------------------------

    public function test_is_turkish_with_tr_locale(): void
    {
        add_filter('locale', static function () {
            return 'tr_TR';
        });

        $result = DemoDataProvider::is_turkish();

        remove_all_filters('locale');

        $this->assertTrue($result);
    }

    public function test_is_turkish_with_en_locale(): void
    {
        add_filter('locale', static function () {
            return 'en_US';
        });

        $result = DemoDataProvider::is_turkish();

        remove_all_filters('locale');

        $this->assertFalse($result);
    }
}
