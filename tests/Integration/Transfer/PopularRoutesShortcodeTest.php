<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Transfer;

use MHMRentiva\Admin\Transfer\Engine\TransferRouteProvider;

/**
 * @group transfer
 * @group popular-routes
 */
final class PopularRoutesShortcodeTest extends \WP_UnitTestCase
{
    private string $loc_table = '';
    private string $routes_table = '';

    /**
     * Origin location IDs created in setUp (high IDs to avoid collisions).
     *
     * @var array<string,int>
     */
    private array $loc = [];

    public function setUp(): void
    {
        parent::setUp();

        global $wpdb;
        $this->loc_table    = $wpdb->prefix . 'rentiva_transfer_locations';
        $this->routes_table = $wpdb->prefix . 'rentiva_transfer_routes';

        // Clear any leftover data within our test ID range.
        $wpdb->query("DELETE FROM {$this->routes_table} WHERE origin_id >= 88000 OR destination_id >= 88000");
        $wpdb->query("DELETE FROM {$this->loc_table} WHERE id >= 88000");

        // Seed locations (Istanbul + Ankara, 2 origin types).
        $wpdb->insert($this->loc_table, [
            'id'             => 88001,
            'name'           => 'Istanbul Airport',
            'city'           => 'Istanbul',
            'type'           => 'airport',
            'is_active'      => 1,
            'allow_rental'   => 0,
            'allow_transfer' => 1,
            'priority'       => 1,
        ]);
        $wpdb->insert($this->loc_table, [
            'id'             => 88002,
            'name'           => 'Taksim',
            'city'           => 'Istanbul',
            'type'           => 'city_center',
            'is_active'      => 1,
            'allow_rental'   => 0,
            'allow_transfer' => 1,
            'priority'       => 2,
        ]);
        $wpdb->insert($this->loc_table, [
            'id'             => 88003,
            'name'           => 'Ankara Esenboga',
            'city'           => 'Ankara',
            'type'           => 'airport',
            'is_active'      => 1,
            'allow_rental'   => 0,
            'allow_transfer' => 1,
            'priority'       => 3,
        ]);
        $wpdb->insert($this->loc_table, [
            'id'             => 88004,
            'name'           => 'Inactive Hotel',
            'city'           => 'Bodrum',
            'type'           => 'hotel',
            'is_active'      => 0,
            'allow_rental'   => 0,
            'allow_transfer' => 1,
            'priority'       => 99,
        ]);

        $this->loc = [
            'ist_airport' => 88001,
            'taksim'      => 88002,
            'ank_airport' => 88003,
            'inactive'    => 88004,
        ];

        TransferRouteProvider::clear_cache();
    }

    public function tearDown(): void
    {
        global $wpdb;
        $wpdb->query("DELETE FROM {$this->routes_table} WHERE origin_id >= 88000 OR destination_id >= 88000");
        $wpdb->query("DELETE FROM {$this->loc_table} WHERE id >= 88000");
        TransferRouteProvider::clear_cache();
        parent::tearDown();
    }

    /**
     * Helper to insert a route. Returns inserted row id.
     */
    private function insert_route(array $overrides = []): int
    {
        global $wpdb;
        $row = array_merge([
            'origin_id'      => $this->loc['ist_airport'],
            'destination_id' => $this->loc['taksim'],
            'distance_km'    => 35.0,
            'duration_min'   => 45,
            'pricing_method' => 'fixed',
            'base_price'     => 850.00,
            'min_price'      => 850.00,
            'max_price'      => 950.00,
            'is_featured'    => 0,
        ], $overrides);
        $wpdb->insert($this->routes_table, $row);
        return (int) $wpdb->insert_id;
    }

    public function test_empty_state_renders_nothing(): void
    {
        $output = do_shortcode('[rentiva_popular_routes]');
        $this->assertSame('', trim($output), 'With no routes seeded shortcode must render empty string');
    }

    public function test_renders_single_route_card(): void
    {
        $this->insert_route();
        $output = do_shortcode('[rentiva_popular_routes]');

        $this->assertStringContainsString('mhm-popular-routes', $output);
        $this->assertStringContainsString('Istanbul Airport', $output);
        $this->assertStringContainsString('Taksim', $output);
    }

    public function test_renders_multiple_route_cards_up_to_limit(): void
    {
        // Seed 3 routes (within Lite limit of 3 to be safe).
        $this->insert_route([ 'origin_id' => $this->loc['ist_airport'], 'destination_id' => $this->loc['taksim'] ]);
        $this->insert_route([ 'origin_id' => $this->loc['ank_airport'], 'destination_id' => $this->loc['taksim'] ]);
        $this->insert_route([ 'origin_id' => $this->loc['ist_airport'], 'destination_id' => $this->loc['ank_airport'] ]);

        $output = do_shortcode('[rentiva_popular_routes limit="6"]');

        $card_count = substr_count($output, '<article class="mhm-popular-route-card');
        $this->assertSame(3, $card_count, 'Should render exactly 3 cards when 3 routes exist');
    }

    public function test_filter_origin_city(): void
    {
        $this->insert_route([ 'origin_id' => $this->loc['ist_airport'], 'destination_id' => $this->loc['taksim'] ]);
        $this->insert_route([ 'origin_id' => $this->loc['ank_airport'], 'destination_id' => $this->loc['taksim'] ]);

        $output = do_shortcode('[rentiva_popular_routes filter_origin_city="Ankara"]');

        $this->assertStringContainsString('Ankara Esenboga', $output);
        $this->assertStringNotContainsString('Istanbul Airport', $output);
    }

    public function test_filter_origin_type(): void
    {
        // ist_airport (airport) → taksim, taksim (city_center) → ank_airport.
        $this->insert_route([ 'origin_id' => $this->loc['ist_airport'], 'destination_id' => $this->loc['taksim'] ]);
        $this->insert_route([ 'origin_id' => $this->loc['taksim'],      'destination_id' => $this->loc['ank_airport'] ]);

        $output = do_shortcode('[rentiva_popular_routes filter_origin_type="airport"]');

        $this->assertStringContainsString('Istanbul Airport', $output);
        // The reverse-direction route (Taksim → Ankara Esenboga) must be hidden;
        // 'Ankara Esenboga' only appears in the second route's destination name.
        $this->assertStringNotContainsString('Ankara Esenboga', $output, 'Reverse-direction route must be hidden by origin type filter');
    }

    public function test_featured_only_returns_only_pinned_routes(): void
    {
        $this->insert_route([ 'origin_id' => $this->loc['ist_airport'], 'destination_id' => $this->loc['taksim'],      'is_featured' => 1 ]);
        $this->insert_route([ 'origin_id' => $this->loc['ank_airport'], 'destination_id' => $this->loc['taksim'],      'is_featured' => 0 ]);

        $output = do_shortcode('[rentiva_popular_routes featured_only="true"]');

        $card_count = substr_count($output, '<article class="mhm-popular-route-card');
        $this->assertSame(1, $card_count, 'featured_only=true must show ONLY pinned routes');
        $this->assertStringContainsString('Istanbul Airport', $output);
        $this->assertStringNotContainsString('Ankara Esenboga', $output);
    }

    public function test_order_featured_pins_featured_first(): void
    {
        // Insert non-featured first, then featured. Expect featured first in output.
        $this->insert_route([ 'origin_id' => $this->loc['ank_airport'], 'destination_id' => $this->loc['taksim'],      'is_featured' => 0, 'min_price' => 500.00 ]);
        $this->insert_route([ 'origin_id' => $this->loc['ist_airport'], 'destination_id' => $this->loc['taksim'],      'is_featured' => 1, 'min_price' => 850.00 ]);

        $output = do_shortcode('[rentiva_popular_routes order="featured"]');

        $istanbul_pos = strpos($output, 'Istanbul Airport');
        $ankara_pos   = strpos($output, 'Ankara Esenboga');
        $this->assertNotFalse($istanbul_pos);
        $this->assertNotFalse($ankara_pos);
        $this->assertLessThan($ankara_pos, $istanbul_pos, 'Featured route must appear before non-featured');
    }

    public function test_order_price_asc_sorts_by_min_price(): void
    {
        $this->insert_route([ 'origin_id' => $this->loc['ist_airport'], 'destination_id' => $this->loc['taksim'],      'min_price' => 950.00 ]);
        $this->insert_route([ 'origin_id' => $this->loc['ank_airport'], 'destination_id' => $this->loc['taksim'],      'min_price' => 450.00 ]);

        $output = do_shortcode('[rentiva_popular_routes order="price_asc"]');

        $ankara_pos   = strpos($output, 'Ankara Esenboga');
        $istanbul_pos = strpos($output, 'Istanbul Airport');
        $this->assertLessThan($istanbul_pos, $ankara_pos, 'Cheaper route must appear first with price_asc');
    }

    public function test_inactive_origin_location_hides_route(): void
    {
        // Inactive origin = inactive location id 88004.
        $this->insert_route([ 'origin_id' => $this->loc['inactive'], 'destination_id' => $this->loc['taksim'] ]);

        $output = do_shortcode('[rentiva_popular_routes]');
        $this->assertSame('', trim($output), 'Routes with inactive origin must be hidden');
    }

    public function test_inactive_destination_location_hides_route(): void
    {
        $this->insert_route([ 'origin_id' => $this->loc['ist_airport'], 'destination_id' => $this->loc['inactive'] ]);

        $output = do_shortcode('[rentiva_popular_routes]');
        $this->assertSame('', trim($output), 'Routes with inactive destination must be hidden');
    }

    public function test_show_duration_attribute_controls_duration_visibility(): void
    {
        $this->insert_route([ 'origin_id' => $this->loc['ist_airport'], 'destination_id' => $this->loc['taksim'], 'duration_min' => 45 ]);

        $with    = do_shortcode('[rentiva_popular_routes show_duration="true" show_distance="false" show_traffic_note="false"]');
        $without = do_shortcode('[rentiva_popular_routes show_duration="false" show_distance="false" show_traffic_note="false"]');

        $this->assertStringContainsString('45', $with, 'duration_min should render when show_duration=true');
        $this->assertStringNotContainsString('mhm-popular-route-duration', $without);
    }

    public function test_price_uses_system_currency_not_hardcoded(): void
    {
        // Regression guard: the old code hardcoded "₺850" (symbol prefixed, no space).
        // The fix routes through CurrencyHelper::format_price(), which uses the active
        // WooCommerce/system currency + position — in the test env: USD "850 $".
        $this->insert_route([ 'min_price' => 850.00 ]);

        $output = do_shortcode('[rentiva_popular_routes show_price="true"]');

        $this->assertStringContainsString('850', $output, 'price value should render');
        $this->assertStringNotContainsString('₺', $output, 'old hardcoded TRY symbol must be gone');
        $this->assertStringContainsString('$', $output, 'currency must come from the active system currency (USD in test env)');
    }

    public function test_invalid_columns_attribute_falls_back_to_default(): void
    {
        $this->insert_route();

        // Columns 5 is not in allowlist (2,3,4) — should fall back gracefully.
        $output = do_shortcode('[rentiva_popular_routes columns="5"]');
        $this->assertStringContainsString('mhm-popular-routes', $output);
    }

    public function test_card_is_wrapped_in_link_pointing_to_transfer_search(): void
    {
        $this->insert_route([ 'origin_id' => $this->loc['ist_airport'], 'destination_id' => $this->loc['taksim'] ]);

        $output = do_shortcode('[rentiva_popular_routes]');

        $this->assertStringContainsString('<a class="mhm-popular-route-card-link"', $output, 'Each card must be wrapped in an anchor');
        $this->assertMatchesRegularExpression(
            '/href="[^"]*origin_id=' . $this->loc['ist_airport'] . '[^"]*destination_id=' . $this->loc['taksim'] . '/',
            $output,
            'Card link must carry origin_id and destination_id query params'
        );
    }

    public function test_card_link_target_is_filterable(): void
    {
        $this->insert_route([ 'origin_id' => $this->loc['ist_airport'], 'destination_id' => $this->loc['taksim'] ]);

        $custom_url = 'https://example.test/custom-transfer-search/';
        $filter = static function () use ($custom_url) {
            return $custom_url;
        };
        add_filter('mhm_rentiva_popular_routes_search_url', $filter);

        $output = do_shortcode('[rentiva_popular_routes]');

        remove_filter('mhm_rentiva_popular_routes_search_url', $filter);

        $this->assertStringContainsString('https://example.test/custom-transfer-search/', $output, 'Cards must use the filtered base URL when set');
        $this->assertStringContainsString('origin_id=' . $this->loc['ist_airport'], $output);
    }

    public function test_view_all_label_renamed_to_search_transfers(): void
    {
        // Insert more routes than the default limit so view_all renders.
        $this->insert_route([ 'origin_id' => $this->loc['ist_airport'], 'destination_id' => $this->loc['taksim'] ]);
        $this->insert_route([ 'origin_id' => $this->loc['ank_airport'], 'destination_id' => $this->loc['taksim'] ]);

        // Force a non-empty view_all_url so the link element renders.
        $output = do_shortcode('[rentiva_popular_routes limit="1" view_all_url="/transfer/"]');

        $this->assertStringContainsString('Search transfers', $output, 'View all label should now read "Search transfers"');
        $this->assertStringNotContainsString('>View all<', $output, 'Old "View all" label must be removed');
    }
}
