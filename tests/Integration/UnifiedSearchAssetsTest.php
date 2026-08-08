<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration;

use MHMRentiva\Admin\Frontend\Shortcodes\UnifiedSearch;

final class UnifiedSearchAssetsTest extends \WP_UnitTestCase
{
    public function test_unified_search_enqueues_only_the_rental_base_style(): void
    {
        UnifiedSearch::render([
            'default_tab' => 'rental',
        ]);

        $this->assertTrue(
            wp_style_is('mhm-rentiva-unified-search-base', 'enqueued'),
            'Expected unified-search base CSS to be enqueued.'
        );

        $this->assertFalse(
            wp_style_is('mhm-rentiva-search-premium', 'enqueued'),
            'Lite must not enqueue the removed paid-search stylesheet.'
        );
    }
}
