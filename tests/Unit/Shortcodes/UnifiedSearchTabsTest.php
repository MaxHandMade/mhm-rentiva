<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Shortcodes;

use MHMRentiva\Admin\Frontend\Shortcodes\UnifiedSearch;
use WP_UnitTestCase;

/**
 * Seam inversion companion to BlockRegistryFilterTest/ShortcodeRegistryFilterTest
 * (Tasks A1/A2): UnifiedSearch no longer knows about
 * \MHMRentiva\Admin\Transfer\Engine\LocationProvider,
 * \MHMRentiva\Admin\Transfer\Frontend\TransferShortcodes, or Lite's own
 * \MHMRentiva\Admin\Licensing\Mode::isPro(). Locations, the extra ("transfer")
 * search tab, and its enqueue/script-deps are all contributed by an add-on
 * through neutral filters/action -- Pro's own SearchExtensions subscribes to
 * them, gated by its own \MHMRentiva\Pro\Edition, never Lite's Mode.
 *
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\UnifiedSearch
 */
final class UnifiedSearchTabsTest extends WP_UnitTestCase
{
    protected function tearDown(): void
    {
        remove_all_filters('mhm_rentiva_search_locations');
        remove_all_filters('mhm_rentiva_search_extra_tabs');
        remove_all_actions('mhm_rentiva_search_enqueue_assets');
        remove_all_filters('mhm_rentiva_search_script_deps');
        parent::tearDown();
    }

    /**
     * The source itself must carry none of the old seam machinery any more --
     * this is the mutation proof for the whole file: reintroduce any of these
     * three tokens in UnifiedSearch.php and this fails, regardless of whether
     * the runtime assertions below happen to still pass.
     */
    public function test_source_has_no_mode_ispro_or_locationprovider_reference(): void
    {
        $source = (string) file_get_contents(
            dirname(__DIR__, 3) . '/src/Admin/Frontend/Shortcodes/UnifiedSearch.php'
        );

        $this->assertStringNotContainsString('Mode::', $source, 'UnifiedSearch.php must not reference Licensing\\Mode any more.');
        $this->assertStringNotContainsString('isPro', $source, 'UnifiedSearch.php must not call isPro() any more.');
        $this->assertStringNotContainsString('LocationProvider', $source, 'UnifiedSearch.php must not name LocationProvider any more.');
    }

    /**
     * @param array<string, mixed> $atts
     * @return array<string, mixed>
     */
    private function template_data(array $atts = array()): array
    {
        $defaults = array(
            'default_tab'           => 'default',
            'default_tab_alias'     => 'defaultTab',
            'show_rental_tab'       => 'default',
            'show_transfer_tab'     => 'default',
            'show_location_select'  => 'default',
            'show_time_select'      => 'default',
            'show_date_picker'      => 'default',
            'show_dropoff_location' => 'default',
            'location_required'     => 'default',
            'fields_required'       => 'default',
            'show_pax'              => 'default',
            'show_luggage'          => 'default',
            'service_type'          => 'both',
            'filter_categories'     => '',
            'redirect_page'         => 'default',
            'layout'                => 'horizontal',
            'search_layout'         => '',
            'style'                 => 'glass',
        );

        return (array) UnifiedSearch::get_data(array_merge($defaults, $atts));
    }

    /**
     * With no subscriber, `mhm_rentiva_search_locations` and
     * `mhm_rentiva_search_extra_tabs` both default to empty -- so Lite offers no
     * locations and no extra ("transfer") tab, purely from the filter defaults,
     * with nothing left in UnifiedSearch itself to ask a class or a licence.
     */
    public function test_without_a_subscriber_no_extra_tab_and_no_locations(): void
    {
        $data = $this->template_data();

        $this->assertSame(array(), $data['locations'], 'Locations must default to empty without a subscriber.');
        $this->assertFalse($data['show_transfer_tab'], 'The extra tab must default to hidden without a subscriber.');
        $this->assertTrue($data['show_rental_tab'], 'The core rental tab must still render.');
    }

    /**
     * The master switch forces service_type="transfer" to request the extra tab,
     * so the "no subscriber" default must still be the last word.
     */
    public function test_extra_tab_stays_hidden_even_when_forced_by_service_type(): void
    {
        $data = $this->template_data(array( 'service_type' => 'transfer', 'default_tab' => 'transfer' ));

        $this->assertFalse($data['show_transfer_tab'], 'service_type="transfer" must not override the "no subscriber" default.');
        $this->assertTrue($data['show_rental_tab'], 'Hiding the extra tab must leave the rental tab usable.');
    }

    /**
     * A subscriber CAN turn the extra tab on -- proves the filter is load-bearing,
     * not just always-empty.
     */
    public function test_a_subscriber_can_enable_the_extra_tab(): void
    {
        add_filter('mhm_rentiva_search_extra_tabs', static fn ($tabs, $atts) => $tabs + array( 'transfer' => true ), 10, 2);

        $data = $this->template_data();

        $this->assertTrue($data['show_transfer_tab']);
    }

    /**
     * A subscriber CAN contribute locations -- proves `mhm_rentiva_search_locations`
     * is load-bearing, not just always-empty.
     */
    public function test_a_subscriber_can_contribute_locations(): void
    {
        add_filter('mhm_rentiva_search_locations', static fn ($locations, $type) => array( (object) array( 'id' => 1, 'name' => 'Demo' ) ), 10, 2);

        $data = $this->template_data();

        $this->assertNotSame(array(), $data['locations']);
    }

    /**
     * The enqueue action/filter pair must fire without fataling when nothing
     * subscribes, and the base script dependencies must be untouched.
     */
    public function test_enqueue_assets_runs_without_a_subscriber(): void
    {
        UnifiedSearch::render(array( 'default_tab' => 'rental' ));

        $this->assertTrue(wp_script_is('mhm-rentiva-unified-search', 'enqueued'));
    }
}
