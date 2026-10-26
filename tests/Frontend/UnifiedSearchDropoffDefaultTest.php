<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend;

use WP_UnitTestCase;

/**
 * The search form's "Drop-off" field was shown by default although it is a
 * locked mirror of the pick-up location (unified-search.js disables it) and
 * nothing downstream reads a drop-off location: booking, price and record
 * ignore it. A field the customer cannot use is not shown unless a page asks.
 *
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\UnifiedSearch
 */
final class UnifiedSearchDropoffDefaultTest extends WP_UnitTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        // The location block renders only when the site has a choice of
        // rental locations (LocationContext: two or more).
        add_filter(
            'mhmrentiva_locations',
            static fn (): array => array(
                (object) array( 'id' => 1, 'name' => 'Airport', 'city' => 'Town' ),
                (object) array( 'id' => 2, 'name' => 'City Centre', 'city' => 'Town' ),
            )
        );
        \MHMRentiva\Admin\Core\LocationContext::flush();
    }

    public function test_the_drop_off_field_is_hidden_by_default(): void
    {
        $html = do_shortcode('[rentiva_unified_search]');

        $this->assertStringContainsString('Airport', $html, 'Precondition: the location block rendered.');
        $this->assertStringNotContainsString('data-testid="search-dropoff-location"', $html);
    }

    public function test_a_page_that_asks_for_it_still_shows_it(): void
    {
        $html = do_shortcode('[rentiva_unified_search show_dropoff_location="true"]');

        $this->assertStringContainsString('data-testid="search-dropoff-location"', $html);
    }
}
