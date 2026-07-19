<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use MHMRentiva\Admin\Frontend\Shortcodes\UnifiedSearch;
use ReflectionMethod;
use WP_UnitTestCase;

/**
 * FORMER PURPOSE (kept for history; do not resurrect the mechanism below)
 * ------------------------------------------------------------------------
 * This file used to mutation-proof `class_exists(...) && Mode::isPro()` gates
 * inside UnifiedSearch::prepare_template_data() -- both for the Transfer tab
 * and for the location dropdown -- using class_alias() stand-ins to simulate
 * "the Pro class is present but the site is unlicensed" (the exact shape that
 * leaked real Transfer UI and real location rows to anonymous visitors before
 * the licence check was added; see git history for the incident writeup).
 *
 * The Task A4 seam inversion removed that mechanism entirely, and Task A10
 * went further: the unified-search transfer TAB itself is gone (Lite ships
 * rental-only; transfer search is the standalone `rentiva_transfer_search`
 * shortcode/block). `show_transfer_tab` and `mhm_rentiva_search_extra_tabs`
 * no longer exist anywhere in UnifiedSearch -- there is nothing left to gate.
 *
 * What remains here are the premises that are still true regardless of
 * mechanism: the visibility keys the (now rental-only) template contract
 * exposes, and the positive control that the rental tab always renders. See
 * mhm-rentiva/tests/Unit/Shortcodes/UnifiedSearchTabsTest.php for the current,
 * filter-based coverage of the surviving `mhm_rentiva_search_locations` seam
 * (defaults empty without a subscriber; a subscriber can turn locations on;
 * the source has no more Mode::/isPro/LocationProvider reference).
 *
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\UnifiedSearch::prepare_template_data
 */
final class UnifiedSearchTransferGateTest extends WP_UnitTestCase
{
    /**
     * @param array<string, mixed> $atts
     * @return array<string, mixed>
     */
    private function template_data(array $atts = array()): array
    {
        $method = new ReflectionMethod(UnifiedSearch::class, 'prepare_template_data');
        $method->setAccessible(true);

        $defaults = array(
            'default_tab'           => 'rental',
            'service_type'          => 'rental',
            'show_rental_tab'       => '1',
            'show_location_select'  => '1',
            'show_time_select'      => '1',
            'show_date_picker'      => '1',
            'show_dropoff_location' => '1',
            'location_required'     => '1',
            'fields_required'       => '1',
            'filter_categories'     => '',
            'redirect_page'         => '',
            'layout'                => 'horizontal',
            'search_layout'         => '',
            'style'                 => 'glass',
        );

        return (array) $method->invoke(null, array_merge($defaults, $atts));
    }

    /**
     * Guards the KEY NAMES, not just the values.
     *
     * Written after this suite's first run asserted on 'show_transfer' -- a key that
     * does not exist. The lookup yielded null, null is falsy, and "the tab is
     * hidden" passed while testing absolutely nothing. `show_transfer_tab` itself
     * is gone as of Task A10 -- there is no second tab left to guard.
     */
    public function test_premise_the_visibility_keys_exist(): void
    {
        $data = $this->template_data();

        foreach (array( 'show_rental_tab', 'show_location_select', 'locations' ) as $key) {
            $this->assertArrayHasKey($key, $data, sprintf('Key "%s" is gone -- assertions on it would pass vacuously.', $key));
        }
    }

    /**
     * Positive control: the core (only) rental tab must always render.
     */
    public function test_the_rental_tab_still_renders_with_no_subscriber(): void
    {
        $data = $this->template_data();

        $this->assertTrue((bool) $data['show_rental_tab'], 'The core rental tab must always render.');
    }
}
