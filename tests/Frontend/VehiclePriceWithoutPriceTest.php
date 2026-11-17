<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend;

use MHMRentiva\Admin\Frontend\Shortcodes\VehiclesList;
use WP_UnitTestCase;

/**
 * A vehicle without a daily price has no price in the shared vehicle data
 * either. The helper used to substitute 1000, a figure nobody entered, which
 * every consumer of VehiclesList::get_vehicle_data_for_shortcode() received.
 */
final class VehiclePriceWithoutPriceTest extends WP_UnitTestCase
{
    public function test_a_vehicle_without_a_price_gets_no_invented_price(): void
    {
        $id = self::factory()->post->create(array('post_type' => 'mhmrentiva_vehicle'));

        $price = VehiclesList::get_vehicle_price($id);

        $this->assertSame(0.0, $price['raw']);
        $this->assertSame(0.0, $price['daily']);
        $this->assertFalse($price['has_price']);
        $this->assertSame('Price on request', $price['formatted']);
    }

    public function test_a_priced_vehicle_keeps_its_price(): void
    {
        $id = self::factory()->post->create(array('post_type' => 'mhmrentiva_vehicle'));
        update_post_meta($id, '_mhmrentiva_price_per_day', '850');

        $price = VehiclesList::get_vehicle_price($id);

        $this->assertSame(850.0, $price['raw']);
        $this->assertTrue($price['has_price']);
        $this->assertStringContainsString('850', $price['formatted']);
    }
}
