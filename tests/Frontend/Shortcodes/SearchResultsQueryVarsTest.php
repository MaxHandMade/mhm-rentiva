<?php

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use MHMRentiva\Admin\Frontend\Shortcodes\SearchResults;
use WP_UnitTestCase;

/**
 * WP.org T4 #11 -- SearchResults' public, bookmarkable search-filter params
 * (keyword, min_price, fuel_type, sort, pickup_location, ...) must be
 * registered on WordPress's `query_vars` whitelist and read via
 * get_query_var(), not raw $_GET.
 *
 * These params build/populate a real shareable URL. Every name carries the
 * `mhmrentiva_` prefix (SearchResults::query_var()) because `query_vars` is a
 * site-wide namespace shared with core and every other plugin, e.g.:
 *   /search/?mhmrentiva_min_price=250&mhmrentiva_fuel_type=diesel&mhmrentiva_sort=price_asc
 *
 * The AJAX refine-filters path (`ajax_filter_results`, POST + nonce) is
 * intentionally untouched and out of scope -- it is covered separately by
 * the existing AJAX tests.
 */
class SearchResultsQueryVarsTest extends WP_UnitTestCase
{
    /**
     * The `query_vars` filter callback must RETURN the modified array (never
     * echo/side-effect) and must include every public search-filter param.
     */
    public function test_register_query_vars_returns_modified_array_with_all_public_filter_params(): void
    {
        $result = SearchResults::register_query_vars(array('some_other_plugins_var'));

        $this->assertIsArray($result);
        $this->assertContains(
            'some_other_plugins_var',
            $result,
            'Callback must not drop vars already registered by core/other plugins.'
        );

        $expected = array_map(
            array(SearchResults::class, 'query_var'),
            SearchResults::FILTER_PARAMS
        );

        foreach ($expected as $var) {
            $this->assertContains($var, $result, "query_vars must include '{$var}'.");
        }

        // `category` was registered as a public query var and collected into the
        // search params, but no code path ever read it -- and the name collides
        // with core's own category handling, which is precisely what the prefix
        // rule exists to prevent. Registering a public var the plugin does not
        // consume only widens the surface WordPress parses on every request.
        $this->assertNotContains(
            'mhmrentiva_category',
            $result,
            'A public query var nothing reads must not be registered.'
        );
    }

    /**
     * The filter must actually be wired up (register_hooks() -> add_filter)
     * so the plugin's own boot-time registration lands on WordPress's live
     * `query_vars` whitelist, not just be a standalone testable method.
     */
    public function test_query_vars_filter_is_wired_up_on_the_live_whitelist(): void
    {
        $vars = apply_filters('query_vars', array());

        $this->assertContains('mhmrentiva_min_price', $vars);
        $this->assertContains('mhmrentiva_fuel_type', $vars);
        $this->assertContains('mhmrentiva_pickup_location', $vars);

        // The bare names must be GONE, not merely joined by prefixed twins --
        // an additive rename would leave the generic names in the global list.
        $this->assertNotContains('min_price', $vars);
        $this->assertNotContains('fuel_type', $vars);
        $this->assertNotContains('pickup_location', $vars);
    }

    /**
     * A registered public param, present on the request URL, must be
     * readable via get_query_var() -- proving the whitelist registration
     * actually takes effect for a real front-end request.
     */
    public function test_registered_param_is_readable_via_get_query_var_after_request(): void
    {
        $this->go_to(
            '/?mhmrentiva_keyword=SUV&mhmrentiva_min_price=250&mhmrentiva_sort=price_asc'
            . '&mhmrentiva_pickup_location%5B%5D=7&mhmrentiva_pickup_location%5B%5D=9'
        );

        $this->assertSame('SUV', get_query_var('mhmrentiva_keyword'));
        $this->assertSame('250', get_query_var('mhmrentiva_min_price'));
        $this->assertSame('price_asc', get_query_var('mhmrentiva_sort'));
        $this->assertSame(array('7', '9'), get_query_var('mhmrentiva_pickup_location'));
    }

    /**
     * Proves the read path is the whitelist, NOT raw $_GET: set $_GET
     * directly (bypassing WP's request parsing / go_to()), so query_vars is
     * never populated for this key. If SearchResults::get_text() still read
     * raw $_GET, the value would leak through here.
     */
    public function test_get_text_does_not_fall_back_to_raw_get_for_a_registered_param(): void
    {
        $_GET['mhmrentiva_keyword'] = 'LeakedRawGetValue';

        $method = new \ReflectionMethod(SearchResults::class, 'get_text');
        $method->setAccessible(true);
        $value = $method->invoke(null, 'keyword');

        unset($_GET['mhmrentiva_keyword']);

        $this->assertSame(
            '',
            $value,
            'get_text() must read from the query_vars whitelist, not fall back to raw $_GET.'
        );
    }

    /**
     * Scoping test: the full search-params builder (get_search_params_from_url)
     * must reflect values from the query_vars whitelist for a real request,
     * across text, int and int-array param shapes.
     */
    public function test_search_params_from_url_reflect_the_registered_query_vars(): void
    {
        $this->go_to(
            '/?mhmrentiva_min_price=300&mhmrentiva_max_price=900&mhmrentiva_fuel_type=diesel'
            . '&mhmrentiva_year_min=2018&mhmrentiva_pickup_location%5B%5D=4'
        );

        $method = new \ReflectionMethod(SearchResults::class, 'get_search_params_from_url');
        $method->setAccessible(true);
        $params = $method->invoke(null);

        $this->assertSame(300, $params['min_price']);
        $this->assertSame(900, $params['max_price']);
        $this->assertSame('diesel', $params['fuel_type']);
        $this->assertSame(2018, $params['year_min']);
        $this->assertSame(array(4), $params['pickup_location']);
    }

    /**
     * Same request shape via SearchResults::get_data() (the public wrapper
     * around prepare_template_data()) -- proves the whitelist read reaches
     * all the way through to the shortcode's rendered template data, which is
     * what the search-results page and its pagination links actually consume.
     */
    public function test_get_data_search_params_reflect_the_registered_query_vars(): void
    {
        // `page` stays bare: it is core's own public query var, not ours.
        $this->go_to('/?mhmrentiva_keyword=Van&mhmrentiva_sort=price_desc&page=2');

        $defaultsMethod = new \ReflectionMethod(SearchResults::class, 'get_default_attributes');
        $defaultsMethod->setAccessible(true);
        $atts = $defaultsMethod->invoke(null);

        $data = SearchResults::get_data($atts);

        $this->assertSame('Van', $data['search_params']['keyword']);
        $this->assertSame('price_desc', $data['search_params']['sort']);
        $this->assertSame(2, $data['search_params']['page']);
    }
}
