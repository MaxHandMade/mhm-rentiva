<?php

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use MHMRentiva\Admin\Frontend\Shortcodes\VehiclesGrid;
use WP_UnitTestCase;

class VehiclesGridDefaultsParityTest extends WP_UnitTestCase
{
    public function test_block_defaults_match_shortcode_defaults_for_shared_fields()
    {
        $method = new \ReflectionMethod(VehiclesGrid::class, 'get_default_attributes');
        $method->setAccessible(true);
        $shortcode_defaults = $method->invoke(null);

        $block_json_path = dirname(__DIR__, 3) . '/assets/blocks/vehicles-grid/block.json';
        $block_json = json_decode((string) file_get_contents($block_json_path), true);
        $block_attrs = $block_json['attributes'] ?? [];

        $pairs = [
            'layout'               => 'layout',
            'show_image'           => 'showImage',
            'show_price'           => 'showPrice',
            'show_rating'          => 'showRating',
            'show_booking_button'  => 'showBookButton',
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
}
