<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration;

use MHMRentiva\Admin\Frontend\Shortcodes\UnifiedSearch;

final class UnifiedSearchAssetsTest extends \WP_UnitTestCase
{
    public function test_unified_search_enqueues_base_and_premium_styles(): void
    {
        UnifiedSearch::render([
            'default_tab' => 'rental',
        ]);

        $this->assertTrue(
            wp_style_is('mhm-rentiva-unified-search-base', 'enqueued'),
            'Expected unified-search base CSS to be enqueued.'
        );

        $this->assertTrue(
            wp_style_is('mhm-rentiva-search-premium', 'enqueued'),
            'Expected search-premium CSS to be enqueued.'
        );
    }
}

