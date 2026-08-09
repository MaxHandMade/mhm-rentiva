<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Frontend;

use MHMRentiva\Admin\Frontend\Shortcodes\BookingForm;
use MHMRentiva\Admin\Frontend\Shortcodes\Core\AbstractShortcode;
use MHMRentiva\Admin\Frontend\Shortcodes\SearchResults;
use MHMRentiva\Admin\Vehicle\PostType\Vehicle;
use ReflectionFunction;
use ReflectionMethod;
use WP_UnitTestCase;

/**
 * WP.org submission hardening -- no generic name this plugin puts on
 * WordPress's GLOBAL public query-var namespace may go unprefixed.
 *
 * `query_vars` is site-wide: core, this plugin and every other active plugin
 * share one list. A bare `sort`, `brand`, `seats` or `vehicle` registered there
 * is a name this plugin has no business owning, and it collides with whatever
 * else claims it. The lock below is deliberately NOT a hardcoded list of the
 * vars we know about today: it walks the live `query_vars` hook, keeps only the
 * callbacks that live in THIS plugin's files, and asserts that whatever they
 * add is prefixed. A new class registering a bare `city` tomorrow fails here
 * without anyone remembering to update a fixture.
 *
 * WordPress core's own defaults (`page`, `s`, `cat`, `year`, ...) are subtracted
 * and must stay unprefixed -- they are core's names, not ours.
 */
class PublicQueryVarPrefixTest extends WP_UnitTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        // WP_UnitTestCase rolls $wp_filter back between tests, so the hooks the
        // boot-time registration added are gone. Clear the register()-once cache
        // and re-register, or the filters under test simply are not there.
        AbstractShortcode::reset_shortcode_cache_for_tests();
        SearchResults::register();
        BookingForm::register();

        // register() only schedules register_post_type() on `init`, which has
        // already fired: call it directly so the CPT under test is the REAL
        // registration with the plugin's own args.
        Vehicle::register_post_type();
    }

    /**
     * WordPress core's own default public query vars, read off the WP class's
     * property defaults rather than a copied literal -- so the baseline tracks
     * whatever core version the suite runs against.
     *
     * @return array<int, string>
     */
    private function core_public_query_vars(): array
    {
        $defaults = get_class_vars('WP');

        $this->assertArrayHasKey(
            'public_query_vars',
            $defaults,
            'WP::$public_query_vars disappeared -- this baseline needs rewriting against the current core.'
        );

        return (array) $defaults['public_query_vars'];
    }

    /**
     * True when a `query_vars` callback is defined inside this plugin.
     *
     * Covers both shapes the plugin uses: [Class::class, 'method'] and the
     * static closure Plugin::register_vehicle_rewrite_rules() adds.
     *
     * @param mixed $callback
     */
    private function callback_is_ours($callback): bool
    {
        try {
            if (is_array($callback) && count($callback) === 2) {
                $reflection = new ReflectionMethod($callback[0], $callback[1]);
            } elseif (is_string($callback) && strpos($callback, '::') !== false) {
                $reflection = new ReflectionMethod($callback);
            } elseif ($callback instanceof \Closure || is_string($callback)) {
                $reflection = new ReflectionFunction($callback);
            } else {
                return false;
            }
        } catch (\ReflectionException $e) {
            return false;
        }

        $file = $reflection->getFileName();
        if (! is_string($file) || '' === $file) {
            return false;
        }

        $plugin_path = wp_normalize_path((string) MHMRENTIVA_PLUGIN_PATH);
        $file        = wp_normalize_path($file);

        return strpos($file, $plugin_path) === 0;
    }

    /**
     * Every public query var contributed by THIS plugin, measured against the
     * core baseline.
     *
     * @return array<int, string>
     */
    private function query_vars_added_by_this_plugin(): array
    {
        $core = $this->core_public_query_vars();
        $hook = $GLOBALS['wp_filter']['query_vars'] ?? null;

        $this->assertNotNull($hook, 'Nothing is hooked on `query_vars` -- the plugin did not register.');

        $added = array();

        foreach ($hook->callbacks as $callbacks) {
            foreach ($callbacks as $registered) {
                $callback = $registered['function'];

                if (! $this->callback_is_ours($callback)) {
                    continue;
                }

                $result = call_user_func($callback, $core);

                $this->assertIsArray($result, 'A `query_vars` callback must return the array it was given.');

                $added = array_merge($added, array_diff($result, $core));
            }
        }

        return array_values(array_unique($added));
    }

    /**
     * THE REGRESSION LOCK. Read the live whitelist, subtract core's own
     * defaults, and require the remainder this plugin contributes to be
     * entirely `mhmrentiva_`-prefixed.
     */
    public function test_every_public_query_var_this_plugin_registers_is_prefixed(): void
    {
        $added = $this->query_vars_added_by_this_plugin();

        $this->assertNotEmpty(
            $added,
            'Measured zero plugin-contributed query vars -- the probe is broken, not the plugin.'
        );

        $unprefixed = array_values(
            array_filter(
                $added,
                static function (string $var): bool {
                    return strpos($var, AbstractShortcode::QUERY_VAR_PREFIX) !== 0;
                }
            )
        );

        $this->assertSame(
            array(),
            $unprefixed,
            'These public query vars sit unprefixed in WordPress\'s global namespace: ' . implode(', ', $unprefixed)
        );
    }

    /**
     * Negative control: the assertion above would actually catch a bare name.
     * Without this, a probe that silently measured nothing would still be green.
     */
    public function test_the_prefix_check_rejects_a_bare_name(): void
    {
        $bare = array_values(
            array_filter(
                array( 'mhmrentiva_sort', 'sort' ),
                static function (string $var): bool {
                    return strpos($var, AbstractShortcode::QUERY_VAR_PREFIX) !== 0;
                }
            )
        );

        $this->assertSame(array('sort'), $bare, 'The prefix predicate must reject an unprefixed name.');
    }

    /**
     * Core's `page` must stay unregistered and unprefixed: it is core's own
     * public query var, and paginate_links() emits `?page=N` hrefs against it.
     */
    public function test_core_page_var_is_neither_claimed_nor_prefixed(): void
    {
        $added = $this->query_vars_added_by_this_plugin();

        $this->assertNotContains('page', $added, 'The plugin must not re-register core\'s `page` var.');
        $this->assertNotContains('mhmrentiva_page', $added, '`page` belongs to core and must not be prefixed.');

        $this->go_to('/?page=2');
        $this->assertSame(2, (int) get_query_var('page'), 'Core pagination must keep resolving.');
    }

    /**
     * The vehicle CPT's query_var moved off the generic `vehicle`, while the
     * post type itself (and therefore its pretty permalinks) is untouched.
     */
    public function test_vehicle_post_type_query_var_is_prefixed(): void
    {
        $this->assertTrue(post_type_exists('mhmrentiva_vehicle'), 'The vehicle post type must still be registered.');

        $object = get_post_type_object('mhmrentiva_vehicle');

        $this->assertNotNull($object);
        $this->assertSame(
            'mhmrentiva_vehicle',
            $object->query_var,
            'The vehicle CPT must not claim the bare `vehicle` public query var.'
        );
    }

    /**
     * The CPT's rewrite slug is a separate concern from its query var: pretty
     * permalinks must keep working exactly as before the rename.
     */
    public function test_vehicle_post_type_rewrite_slug_is_untouched(): void
    {
        $object = get_post_type_object('mhmrentiva_vehicle');

        $this->assertNotNull($object);
        $this->assertIsArray($object->rewrite);
        $this->assertArrayHasKey('slug', $object->rewrite);
        $this->assertNotSame(
            '',
            (string) $object->rewrite['slug'],
            'The rewrite slug drives the public URL and must survive the query-var rename.'
        );
        $this->assertStringNotContainsString(
            AbstractShortcode::QUERY_VAR_PREFIX,
            (string) $object->rewrite['slug'],
            'The prefix belongs on the query var, not in the user-visible permalink.'
        );
    }

    /**
     * The SEO sub-path rewrite reads its slug under the prefixed name.
     */
    public function test_vehicle_slug_rewrite_var_is_prefixed(): void
    {
        $vars = apply_filters('query_vars', array());

        $this->assertContains('mhmrentiva_vehicle_slug', $vars);
        $this->assertNotContains('vehicle_slug', $vars, 'The bare `vehicle_slug` must no longer be registered.');
    }

    /**
     * Drift lock: the declared whitelist must BE query_var() of the logical
     * keys the read path uses -- not a hand-typed parallel list that can rot.
     *
     * @dataProvider whitelist_provider
     */
    public function test_declared_whitelist_is_the_prefixed_form_of_the_logical_keys(
        string $class,
        array $logical_keys
    ): void {
        $property = new \ReflectionClassConstant($class, 'PUBLIC_QUERY_VARS');
        $declared = (array) $property->getValue();

        $expected = array_map(
            static function (string $logical) use ($class): string {
                return call_user_func(array($class, 'query_var'), $logical);
            },
            $logical_keys
        );

        $this->assertSame($expected, $declared, "{$class}::PUBLIC_QUERY_VARS drifted from its logical keys.");
    }

    /**
     * @return array<string, array{0: string, 1: array<int, string>}>
     */
    public function whitelist_provider(): array
    {
        return array(
            'search filters' => array(SearchResults::class, SearchResults::FILTER_PARAMS),
            'booking params' => array(BookingForm::class, BookingForm::FILTER_PARAMS),
        );
    }

    /**
     * End to end: a prefixed param on a real request must reach the search read
     * path under its logical key, across text, int and int-array shapes.
     */
    public function test_search_read_path_answers_for_the_prefixed_query_vars(): void
    {
        $this->go_to(
            '/?mhmrentiva_keyword=SUV&mhmrentiva_min_price=300&mhmrentiva_max_price=900'
            . '&mhmrentiva_fuel_type=diesel&mhmrentiva_year_min=2018&mhmrentiva_sort=price_asc'
            . '&mhmrentiva_pickup_location%5B%5D=4&mhmrentiva_pickup_location%5B%5D=7'
        );

        $method = new ReflectionMethod(SearchResults::class, 'get_search_params_from_url');
        $method->setAccessible(true);
        $params = $method->invoke(null);

        $this->assertSame('SUV', $params['keyword']);
        $this->assertSame(300, $params['min_price']);
        $this->assertSame(900, $params['max_price']);
        $this->assertSame('diesel', $params['fuel_type']);
        $this->assertSame(2018, $params['year_min']);
        $this->assertSame('price_asc', $params['sort']);
        $this->assertSame(array(4, 7), $params['pickup_location']);
    }

    /**
     * Negative proof that the rename actually took effect on the wire: the OLD
     * bare names must no longer be readable, or the "rename" would be additive
     * and the generic names would still be sitting in the global namespace.
     */
    public function test_the_old_bare_names_no_longer_answer(): void
    {
        $this->go_to('/?min_price=300&sort=price_asc&keyword=SUV');

        $method = new ReflectionMethod(SearchResults::class, 'get_search_params_from_url');
        $method->setAccessible(true);
        $params = $method->invoke(null);

        $this->assertSame(0, $params['min_price'], 'A bare ?min_price= must no longer be read.');
        $this->assertSame('relevance', $params['sort'], 'A bare ?sort= must no longer be read.');
        $this->assertSame('', $params['keyword'], 'A bare ?keyword= must no longer be read.');
    }

    /**
     * Same for the booking side: the prefixed "Book Now" params must reach
     * BookingForm's reader.
     */
    public function test_booking_read_path_answers_for_the_prefixed_query_vars(): void
    {
        $this->go_to('/?mhmrentiva_pickup_time=09:00&mhmrentiva_return_time=17:00');

        $method = new ReflectionMethod(BookingForm::class, 'get_text');
        $method->setAccessible(true);

        $this->assertSame('09:00', $method->invoke(null, 'pickup_time'));
        $this->assertSame('17:00', $method->invoke(null, 'return_time'));
    }
}
