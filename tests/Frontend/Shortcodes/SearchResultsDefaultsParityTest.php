<?php

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use MHMRentiva\Admin\Frontend\Shortcodes\SearchResults;
use WP_UnitTestCase;

class SearchResultsDefaultsParityTest extends WP_UnitTestCase
{
    public function test_block_defaults_match_shortcode_defaults_for_shared_fields()
    {
        $method = new \ReflectionMethod(SearchResults::class, 'get_default_attributes');
        $method->setAccessible(true);
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

    public function test_sort_defaults_map_to_default_sort_contract()
    {
        $method = new \ReflectionMethod(SearchResults::class, 'get_default_attributes');
        $method->setAccessible(true);
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
}
