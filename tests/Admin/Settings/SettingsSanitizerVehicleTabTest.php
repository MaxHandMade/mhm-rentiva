<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Settings;

use MHMRentiva\Admin\Settings\Core\SettingsSanitizer;
use WP_UnitTestCase;

/**
 * Tests for SettingsSanitizer vehicle management tab.
 */
class SettingsSanitizerVehicleTabTest extends WP_UnitTestCase
{
    private function sanitize_vehicle(array $fields): array
    {
        $input = array_merge(['current_active_tab' => 'vehicle'], $fields);
        return SettingsSanitizer::sanitize($input);
    }

    // vehicle_url_base — sanitize_title(), empty → 'vehicle'

    public function test_url_base_is_slugified()
    {
        $result = $this->sanitize_vehicle(['mhm_rentiva_vehicle_url_base' => 'My Vehicles!']);
        $this->assertSame('my-vehicles', $result['mhm_rentiva_vehicle_url_base']);
    }

    public function test_url_base_empty_falls_back_to_vehicle()
    {
        $result = $this->sanitize_vehicle(['mhm_rentiva_vehicle_url_base' => '']);
        $this->assertSame('vehicle', $result['mhm_rentiva_vehicle_url_base']);
    }

    // vehicle_default_sort — enum: price_asc|price_desc|name_asc|name_desc|year_desc|year_asc

    public function test_sort_accepts_valid_enum_value()
    {
        $result = $this->sanitize_vehicle(['mhm_rentiva_vehicle_default_sort' => 'name_desc']);
        $this->assertSame('name_desc', $result['mhm_rentiva_vehicle_default_sort']);
    }

    public function test_sort_invalid_value_falls_back_to_price_asc()
    {
        $result = $this->sanitize_vehicle(['mhm_rentiva_vehicle_default_sort' => 'totally_invalid']);
        $this->assertSame('price_asc', $result['mhm_rentiva_vehicle_default_sort']);
    }

    // vehicle_min_rental_days — min=1, max=365, default=1

    public function test_min_rental_days_accepts_valid_value()
    {
        $result = $this->sanitize_vehicle(['mhm_rentiva_vehicle_min_rental_days' => '3']);
        $this->assertSame(3, $result['mhm_rentiva_vehicle_min_rental_days']);
    }

    public function test_min_rental_days_clamps_below_min()
    {
        $result = $this->sanitize_vehicle(['mhm_rentiva_vehicle_min_rental_days' => '0']);
        $this->assertSame(1, $result['mhm_rentiva_vehicle_min_rental_days']);
    }

    public function test_min_rental_days_clamps_above_max()
    {
        $result = $this->sanitize_vehicle(['mhm_rentiva_vehicle_min_rental_days' => '999']);
        $this->assertSame(365, $result['mhm_rentiva_vehicle_min_rental_days']);
    }

    // vehicle_tax_rate — clamp 0-100

    public function test_tax_rate_accepts_valid_value()
    {
        $result = $this->sanitize_vehicle(['mhm_rentiva_vehicle_tax_rate' => '18']);
        $this->assertEqualsWithDelta(18.0, $result['mhm_rentiva_vehicle_tax_rate'], 0.001);
    }

    public function test_tax_rate_clamps_negative_to_zero()
    {
        $result = $this->sanitize_vehicle(['mhm_rentiva_vehicle_tax_rate' => '-5']);
        $this->assertEqualsWithDelta(0.0, $result['mhm_rentiva_vehicle_tax_rate'], 0.001);
    }

    public function test_tax_rate_clamps_above_100()
    {
        $result = $this->sanitize_vehicle(['mhm_rentiva_vehicle_tax_rate' => '150']);
        $this->assertEqualsWithDelta(100.0, $result['mhm_rentiva_vehicle_tax_rate'], 0.001);
    }

    // vehicle_base_price — min enforced at 0.1

    public function test_base_price_negative_is_floored_to_minimum()
    {
        $result = $this->sanitize_vehicle(['mhm_rentiva_vehicle_base_price' => '-10']);
        $this->assertGreaterThanOrEqual(0.1, $result['mhm_rentiva_vehicle_base_price']);
    }
}
