<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Layout;

use MHMRentiva\Layout\AdapterRegistry;
use PHPUnit\Framework\TestCase;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Adapter Mapping Test
 *
 * @package MHMRentiva\Tests\Integration\Layout
 */
final class AdapterMappingTest extends TestCase
{

    protected function setUp(): void
    {
        parent::setUp();
        AdapterRegistry::boot_defaults();
    }

    public function test_search_hero_adapter_mapping(): void
    {
        $adapter = AdapterRegistry::get_adapter('search_hero');
        $this->assertNotNull($adapter);

        $attributes = ['layout' => 'horizontal', 'style' => 'glass'];
        $output = $adapter->render($attributes, 'inst_1');

        $this->assertStringContainsString('[rentiva_unified_search', $output);
        $this->assertStringContainsString('layout="horizontal"', $output);
        $this->assertStringContainsString('style="glass"', $output);
    }

    public function test_vehicle_listing_adapter_mapping(): void
    {
        $adapter = AdapterRegistry::get_adapter('vehicle_listing');
        $this->assertNotNull($adapter);

        $attributes = [
            'limit' => 6,
            'ids' => '1,2,3',
            'layout' => 'slider'
        ];

        $output = $adapter->render($attributes, 'inst_2');

        $this->assertStringContainsString('[rentiva_featured_vehicles', $output);
        $this->assertStringContainsString('limit="6"', $output);
        $this->assertStringContainsString('ids="1,2,3"', $output);
        $this->assertStringContainsString('layout="slider"', $output);
    }

    public function test_reviews_adapter_mapping(): void
    {
        $adapter = AdapterRegistry::get_adapter('reviews_grid');
        $this->assertNotNull($adapter);

        $attributes = ['limit' => 4, 'columns' => 2];
        $output = $adapter->render($attributes, 'inst_3');

        $this->assertStringContainsString('[rentiva_testimonials', $output);
        $this->assertStringContainsString('limit="4"', $output);
        $this->assertStringContainsString('columns="2"', $output);
    }

    public function test_unknown_type_returns_null(): void
    {
        $adapter = AdapterRegistry::get_adapter('non_existent_type');
        $this->assertNull($adapter);
    }
}
