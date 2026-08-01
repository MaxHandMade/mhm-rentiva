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
 * \MHMRentiva\Admin\Licensing\Mode::isPro(). Locations are contributed by an
 * add-on through a neutral filter -- Pro's own SearchExtensions subscribes to
 * it, gated by its own \MHMRentiva\Pro\Edition, never Lite's Mode.
 *
 * Task A10 removed the unified-search transfer TAB entirely (Lite ships
 * rental-only; transfer search is the standalone `rentiva_transfer_search`
 * shortcode/block), and with it `mhmrentiva_search_extra_tabs`,
 * `mhmrentiva_search_enqueue_assets` and `mhmrentiva_search_script_deps` --
 * none of them have any reader left in UnifiedSearch. Only
 * `mhmrentiva_search_locations` survives, because the rental panel's own
 * pickup/dropoff selects read it too.
 *
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\UnifiedSearch
 */
final class UnifiedSearchTabsTest extends WP_UnitTestCase
{
    protected function tearDown(): void
    {
        remove_all_filters('mhmrentiva_search_locations');
        parent::tearDown();
    }

    /**
     * The source itself must carry none of the old seam machinery any more --
     * this is the mutation proof for the whole file: reintroduce any of these
     * tokens in UnifiedSearch.php and this fails, regardless of whether the
     * runtime assertions below happen to still pass.
     */
    public function test_source_has_no_mode_ispro_or_locationprovider_reference(): void
    {
        $source = (string) file_get_contents(
            dirname(__DIR__, 3) . '/src/Admin/Frontend/Shortcodes/UnifiedSearch.php'
        );

        $this->assertStringNotContainsString('Mode::', $source, 'UnifiedSearch.php must not reference Licensing\\Mode any more.');
        $this->assertStringNotContainsString('isPro', $source, 'UnifiedSearch.php must not call isPro() any more.');
        $this->assertStringNotContainsString('LocationProvider', $source, 'UnifiedSearch.php must not name LocationProvider any more.');

        // Task A10: the transfer TAB is gone, and with it every hook that only
        // ever served it.
        $this->assertStringNotContainsString('mhmrentiva_search_extra_tabs', $source, 'UnifiedSearch.php must not read the removed extra-tab filter any more.');
        $this->assertStringNotContainsString('mhmrentiva_search_enqueue_assets', $source, 'UnifiedSearch.php must not fire the removed transfer-only enqueue action any more.');
        $this->assertStringNotContainsString('mhmrentiva_search_script_deps', $source, 'UnifiedSearch.php must not read the removed transfer-only script-deps filter any more.');
        $this->assertStringNotContainsString('show_transfer_tab', $source, 'UnifiedSearch.php must not carry the removed transfer-tab visibility key any more.');
    }

    /**
     * @param array<string, mixed> $atts
     * @return array<string, mixed>
     */
    private function template_data(array $atts = array()): array
    {
        $defaults = array(
            'default_tab'           => 'default',
            'show_rental_tab'       => 'default',
            'show_location_select'  => 'default',
            'show_time_select'      => 'default',
            'show_date_picker'      => 'default',
            'show_dropoff_location' => 'default',
            'location_required'     => 'default',
            'fields_required'       => 'default',
            'service_type'          => 'rental',
            'filter_categories'     => '',
            'redirect_page'         => 'default',
            'layout'                => 'horizontal',
            'search_layout'         => '',
        );

        return (array) UnifiedSearch::get_data(array_merge($defaults, $atts));
    }

    /**
     * With no subscriber, `mhmrentiva_search_locations` defaults to empty -- so
     * Lite offers no locations, purely from the filter default, with nothing left
     * in UnifiedSearch itself to ask a class or a licence. There is no extra
     * ("transfer") tab left to default at all as of Task A10.
     */
    public function test_without_a_subscriber_no_locations(): void
    {
        $data = $this->template_data();

        $this->assertSame(array(), $data['locations'], 'Locations must default to empty without a subscriber.');
        $this->assertTrue($data['show_rental_tab'], 'The core rental tab must still render.');
    }

    /**
     * A subscriber CAN contribute locations -- proves `mhmrentiva_search_locations`
     * is load-bearing, not just always-empty.
     */
    public function test_a_subscriber_can_contribute_locations(): void
    {
        add_filter('mhmrentiva_search_locations', static fn ($locations, $type) => array( (object) array( 'id' => 1, 'name' => 'Demo' ) ), 10, 2);

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
