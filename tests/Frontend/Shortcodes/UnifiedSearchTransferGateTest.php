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
 * The Task A4 seam inversion removed that mechanism entirely.
 * UnifiedSearch no longer references Mode::isPro(), LocationProvider, or
 * TransferShortcodes at all -- it only reads `mhm_rentiva_search_extra_tabs`,
 * `mhm_rentiva_search_locations`, `mhm_rentiva_search_enqueue_assets` and
 * `mhm_rentiva_search_script_deps`. Whether an add-on's contribution is
 * actually gated by a licence is now entirely Pro's own concern, tested in
 * Pro's own suite: see
 * mhm-rentiva-pro/tests/Integration/Pro/SearchExtensionsTest.php.
 *
 * The class_alias() stand-in trick is gone with it -- it was a source-scan-proof
 * workaround for a mechanism (class presence) that no longer exists in this
 * file, and it was process-wide/irreversible, so removing it also removes a
 * standing side effect on the rest of the suite.
 *
 * What remains here are the two premises that are still true regardless of
 * mechanism: the visibility keys the template contract exposes, and the
 * positive control that hiding the extra tab must not take rental down with
 * it. See
 * mhm-rentiva/tests/Unit/Shortcodes/UnifiedSearchTabsTest.php for the current,
 * filter-based coverage of the seam itself (defaults empty without a
 * subscriber; a subscriber can turn locations/the extra tab on; the source has
 * no more Mode::/isPro/LocationProvider reference).
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
            'service_type'          => 'both',
            'show_rental_tab'       => '1',
            'show_transfer_tab'     => '1',
            'show_location_select'  => '1',
            'show_time_select'      => '1',
            'show_date_picker'      => '1',
            'show_dropoff_location' => '1',
            'location_required'     => '1',
            'fields_required'       => '1',
            'show_pax'              => '1',
            'show_luggage'          => '1',
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
     * hidden" passed while testing absolutely nothing. Only the rental positive
     * control caught it. The real keys are *_tab.
     */
    public function test_premise_the_visibility_keys_exist(): void
    {
        $data = $this->template_data();

        foreach (array( 'show_rental_tab', 'show_transfer_tab', 'show_location_select', 'locations' ) as $key) {
            $this->assertArrayHasKey($key, $data, sprintf('Key "%s" is gone -- assertions on it would pass vacuously.', $key));
        }
    }

    /**
     * Positive control: the core rental side must survive whatever gates the extra
     * tab. A fix that hid everything would satisfy other assertions while breaking
     * the plugin.
     */
    public function test_the_rental_tab_still_renders_with_no_subscriber(): void
    {
        $data = $this->template_data();

        $this->assertTrue((bool) $data['show_rental_tab'], 'The core rental tab must always render.');
    }
}
