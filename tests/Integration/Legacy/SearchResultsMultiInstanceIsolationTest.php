<?php

namespace MHMRentiva\Tests\Integration\Legacy;

use MHMRentiva\Admin\Frontend\Shortcodes\SearchResults;
use WP_UnitTestCase;

class SearchResultsMultiInstanceIsolationTest extends WP_UnitTestCase
{
    public function test_search_results_defaults_include_card_visibility_toggles(): void
    {
        $method = new \ReflectionMethod(SearchResults::class, 'get_default_attributes');
        $method->setAccessible(true);
        $defaults = $method->invoke(null);

        $this->assertArrayHasKey('show_features', $defaults);
        $this->assertArrayHasKey('show_price', $defaults);
        $this->assertArrayHasKey('show_title', $defaults);
        $this->assertArrayHasKey('show_rating', $defaults);
    }

    private function get_vehicle_fixture(): array
    {
        return array(
            'id'           => 999,
            'title'        => 'Fixture Vehicle',
            'excerpt'      => 'Fixture excerpt',
            'permalink'    => 'https://example.com/vehicle',
            'image'        => array(
                'url' => 'https://example.com/vehicle.jpg',
                'alt' => 'Fixture Vehicle',
            ),
            'price'        => array(
                'raw'       => 1000,
                'formatted' => '1.000 ₺',
            ),
            'availability' => array(
                'is_available' => true,
                'text'         => 'Available',
            ),
            'features'     => array(
                array(
                    'text' => 'Automatic',
                    'svg'  => '<svg class="rv-icon rv-icon-gear" viewBox="0 0 24 24"></svg>',
                ),
            ),
            'rating'       => array(
                'stars'   => '',
                'count'   => 0,
                'average' => 0,
            ),
            'is_featured'  => false,
            'is_favorite'  => false,
        );
    }

    public function test_render_vehicle_card_respects_instance_specific_feature_toggle(): void
    {
        $vehicle = $this->get_vehicle_fixture();

        $html_with_features = SearchResults::render_vehicle_card(
            $vehicle,
            'grid',
            array(
                'show_features' => '1',
                'show_price'    => '1',
                'show_title'    => '1',
            )
        );

        $html_without_features = SearchResults::render_vehicle_card(
            $vehicle,
            'grid',
            array(
                'show_features' => '0',
                'show_price'    => '1',
                'show_title'    => '1',
            )
        );

        $this->assertStringContainsString('mhm-card-features', $html_with_features);
        $this->assertStringNotContainsString('mhm-card-features', $html_without_features);
    }
}

