<?php

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use MHMRentiva\Admin\Vehicle\PostType\Vehicle;
use MHMRentiva\Admin\Frontend\Shortcodes\FeaturedVehicles;
use MHMRentiva\Admin\Frontend\Shortcodes\SearchResults;
use MHMRentiva\Admin\Frontend\Shortcodes\VehiclesGrid;
use MHMRentiva\Admin\Frontend\Shortcodes\VehiclesList;
use WP_UnitTestCase;

class VehiclesListTest extends WP_UnitTestCase
{
    private $vehicle_id;

    public function setUp(): void
    {
        parent::setUp();

        // Ensure post type is registered
        if (!post_type_exists('vehicle')) {
            register_post_type('vehicle', array(
                'public'      => true,
                'has_archive' => true,
                'supports'    => array('title', 'editor', 'thumbnail', 'excerpt'),
                'post_status' => 'publish',
            ));
        }

        VehiclesList::register();

        // Create a dummy vehicle
        $this->vehicle_id = $this->factory->post->create([
            'post_type' => 'vehicle',
            'post_title' => 'Test Vehicle',
            'post_excerpt' => 'Test vehicle excerpt for description visibility checks.',
            'post_status' => 'publish',
            'meta_input' => [
                '_mhm_rentiva_price_per_day' => '100',
                '_mhm_vehicle_status' => 'active'
            ]
        ]);

        wp_cache_delete($this->vehicle_id, 'post_meta');
    }

    public function test_show_price_true()
    {
        $output = do_shortcode('[rentiva_vehicles_list limit="1" show_price="1"]');

        $this->assertStringContainsString('mhm-vehicle-card', $output);
        $this->assertStringContainsString('mhm-price-amount', $output);
        $this->assertStringContainsString('100', $output);
    }

    public function test_show_price_false()
    {
        $output = do_shortcode('[rentiva_vehicles_list limit="1" show_price="0"]');

        $this->assertStringNotContainsString('mhm-price-amount', $output);
    }

    public function test_show_price_false_string()
    {
        $output = do_shortcode('[rentiva_vehicles_list limit="1" show_price="false"]');

        $this->assertStringNotContainsString('mhm-price-amount', $output);
    }

    public function test_snake_case_attribute_usage()
    {
        $output = do_shortcode('[rentiva_vehicles_list limit="1" show_booking_button="0"]');

        file_put_contents('/tmp/test_booking_debug.html', "--- OUTPUT ---\n$output");

        $this->assertStringNotContainsString('mhm-btn-booking', $output, 'Booking button should be hidden when show_booking_button="0"');
    }

    public function test_show_description_true_renders_description_wrapper()
    {
        $output = do_shortcode('[rentiva_vehicles_list limit="1" layout="list" show_description="1"]');

        $this->assertStringContainsString('mhm-card-description', $output);
        $this->assertStringContainsString('Test vehicle excerpt for description visibility checks.', $output);
    }

    public function test_show_description_false_hides_description_wrapper()
    {
        $output = do_shortcode('[rentiva_vehicles_list limit="1" layout="list" show_description="0"]');

        $this->assertStringNotContainsString('mhm-card-description', $output);
        $this->assertStringNotContainsString('Test vehicle excerpt for description visibility checks.', $output);
    }

    public function test_block_defaults_match_shortcode_defaults_for_shared_fields()
    {
        $method = new \ReflectionMethod(VehiclesList::class, 'get_default_attributes');
        $shortcode_defaults = $method->invoke(null);

        $block_json_path = dirname(__DIR__, 3) . '/assets/blocks/vehicles-list/block.json';
        $block_json = json_decode((string) file_get_contents($block_json_path), true);
        $block_attrs = $block_json['attributes'] ?? [];

        $pairs = [
            'show_description'    => 'showDescription',
            'show_image'          => 'showImage',
            'show_price'          => 'showPrice',
            'show_rating'         => 'showRating',
            'show_booking_button'    => 'showBookButton',
            'show_favorite_button' => 'showFavoriteButton',
            'show_compare_button' => 'showCompareButton',
            'show_features'       => 'showFeatures',
            'show_badges'         => 'showBadges',
            'limit'               => 'limit',
            'orderby'             => 'orderby',
            'order'               => 'order',
        ];

        foreach ($pairs as $shortcode_key => $block_key) {
            $this->assertArrayHasKey($shortcode_key, $shortcode_defaults);
            $this->assertArrayHasKey($block_key, $block_attrs);

            $left = $shortcode_defaults[$shortcode_key];
            $right = $block_attrs[$block_key]['default'] ?? null;

            if (in_array($left, ['0', '1'], true)) {
                $this->assertSame($left === '1', (bool) $right, "Mismatch for {$shortcode_key} <-> {$block_key}");
                continue;
            }

            if ($shortcode_key === 'order') {
                $this->assertSame(strtolower((string) $left), strtolower((string) $right), "Mismatch for {$shortcode_key} <-> {$block_key}");
                continue;
            }

            $this->assertSame((string) $left, (string) $right, "Mismatch for {$shortcode_key} <-> {$block_key}");
        }
    }

    public function test_vehicles_grid_block_defaults_match_shortcode_defaults_for_shared_fields()
    {
        $method = new \ReflectionMethod(VehiclesGrid::class, 'get_default_attributes');
        $shortcode_defaults = $method->invoke(null);

        $block_json_path = dirname(__DIR__, 3) . '/assets/blocks/vehicles-grid/block.json';
        $block_json = json_decode((string) file_get_contents($block_json_path), true);
        $block_attrs = $block_json['attributes'] ?? [];

        $pairs = [
            'layout'               => 'layout',
            'show_image'           => 'showImage',
            'show_price'           => 'showPrice',
            'show_rating'          => 'showRating',
            'show_booking_button'     => 'showBookButton',
            'show_favorite_button' => 'showFavoriteButton',
            'show_compare_button'  => 'showCompareButton',
            'show_features'        => 'showFeatures',
            'columns'              => 'columns',
            'limit'                => 'limit',
            'orderby'              => 'sortBy',
            'order'                => 'sortOrder',
        ];

        foreach ($pairs as $shortcode_key => $block_key) {
            $this->assertArrayHasKey($shortcode_key, $shortcode_defaults);
            $this->assertArrayHasKey($block_key, $block_attrs);

            $left = $shortcode_defaults[$shortcode_key];
            $right = $block_attrs[$block_key]['default'] ?? null;

            if (in_array($left, ['0', '1'], true)) {
                $this->assertSame($left === '1', (bool) $right, "Mismatch for {$shortcode_key} <-> {$block_key}");
                continue;
            }

            if ($shortcode_key === 'order') {
                $this->assertSame(strtolower((string) $left), strtolower((string) $right), "Mismatch for {$shortcode_key} <-> {$block_key}");
                continue;
            }

            $this->assertSame((string) $left, (string) $right, "Mismatch for {$shortcode_key} <-> {$block_key}");
        }
    }

    public function test_search_results_block_defaults_match_shortcode_defaults_for_shared_fields()
    {
        $method = new \ReflectionMethod(SearchResults::class, 'get_default_attributes');
        $shortcode_defaults = $method->invoke(null);

        $block_json_path = dirname(__DIR__, 3) . '/assets/blocks/search-results/block.json';
        $block_json = json_decode((string) file_get_contents($block_json_path), true);
        $block_attrs = $block_json['attributes'] ?? [];

        $pairs = [
            'layout'               => 'layout',
            'results_per_page'     => 'limit',
            'show_filters'         => 'showFilters',
            'show_pagination'      => 'showPagination',
            'show_sorting'         => 'showSorting',
            'show_favorite_button' => 'showFavoriteButton',
            'show_compare_button'  => 'showCompareButton',
        ];

        foreach ($pairs as $shortcode_key => $block_key) {
            $this->assertArrayHasKey($shortcode_key, $shortcode_defaults);
            $this->assertArrayHasKey($block_key, $block_attrs);

            $left = $shortcode_defaults[$shortcode_key];
            $right = $block_attrs[$block_key]['default'] ?? null;

            if (in_array($left, ['0', '1'], true)) {
                $this->assertSame($left === '1', (bool) $right, "Mismatch for {$shortcode_key} <-> {$block_key}");
                continue;
            }

            $this->assertSame((string) $left, (string) $right, "Mismatch for {$shortcode_key} <-> {$block_key}");
        }
    }

    public function test_search_results_sort_defaults_map_to_shortcode_contract()
    {
        $method = new \ReflectionMethod(SearchResults::class, 'get_default_attributes');
        $shortcode_defaults = $method->invoke(null);

        $block_json_path = dirname(__DIR__, 3) . '/assets/blocks/search-results/block.json';
        $block_json = json_decode((string) file_get_contents($block_json_path), true);
        $block_attrs = $block_json['attributes'] ?? [];

        $sort_by = (string) ($block_attrs['sortBy']['default'] ?? '');
        $sort_order = strtolower((string) ($block_attrs['sortOrder']['default'] ?? ''));

        $mapped = '';
        if ($sort_by === 'price' && $sort_order === 'asc') {
            $mapped = 'price_asc';
        }

        $this->assertSame((string) $shortcode_defaults['default_sort'], $mapped);
    }

    public function test_featured_vehicles_block_defaults_match_shortcode_defaults_for_shared_fields()
    {
        $method = new \ReflectionMethod(FeaturedVehicles::class, 'get_default_attributes');
        $shortcode_defaults = $method->invoke(null);

        $block_json_path = dirname(__DIR__, 3) . '/assets/blocks/featured-vehicles/block.json';
        $block_json = json_decode((string) file_get_contents($block_json_path), true);
        $block_attrs = $block_json['attributes'] ?? [];

        $pairs = [
            'layout'               => 'layout',
            'show_price'           => 'showPrice',
            'show_rating'          => 'showRating',
            'show_book_button'     => 'showBookButton',
            'show_favorite_button' => 'showFavoriteButton',
            'show_compare_button'  => 'showCompareButton',
            'show_features'        => 'showFeatures',
            'show_badges'          => 'showBadges',
            'columns'              => 'columns',
            'limit'                => 'limit',
            'orderby'              => 'sortBy',
            'order'                => 'sortOrder',
        ];

        foreach ($pairs as $shortcode_key => $block_key) {
            $this->assertArrayHasKey($shortcode_key, $shortcode_defaults);
            $this->assertArrayHasKey($block_key, $block_attrs);

            $left = $shortcode_defaults[$shortcode_key];
            $right = $block_attrs[$block_key]['default'] ?? null;

            if (in_array($left, ['0', '1'], true)) {
                $this->assertSame($left === '1', (bool) $right, "Mismatch for {$shortcode_key} <-> {$block_key}");
                continue;
            }

            if ($shortcode_key === 'order') {
                $this->assertSame(strtolower((string) $left), strtolower((string) $right), "Mismatch for {$shortcode_key} <-> {$block_key}");
                continue;
            }

            $this->assertSame((string) $left, (string) $right, "Mismatch for {$shortcode_key} <-> {$block_key}");
        }
    }
}
