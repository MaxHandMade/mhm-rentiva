<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Admin\Frontend;

use MHMRentiva\Admin\Frontend\Account\AccountController;
use WP_UnitTestCase;

/**
 * WP.org T7 — the My Account bookings template read its two filter params with
 * `filter_input( INPUT_GET, ... )`, one of the shapes this round removes.
 *
 * The replacement is the same mechanism SearchResults uses (the fix WP.org
 * accepted for T4 #11): the params are registered on WordPress's `query_vars`
 * whitelist and read with get_query_var(), so the template touches no
 * superglobal. Unlike the admin list tables, this genuinely works here — the
 * account screen is front-end, where wp() runs WP::parse_request().
 *
 * Registration is invisible at the call site: if the `query_vars` filter is
 * dropped, get_query_var() simply answers the default and filtering silently
 * stops working with no error anywhere. That is what these tests pin.
 *
 * @covers \MHMRentiva\Admin\Frontend\Account\AccountController
 */
final class AccountBookingFilterQueryVarsTest extends WP_UnitTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        // Deliberately the REAL wiring path, not a hand-added filter: the thing
        // that can silently disappear is the add_filter() call inside register(),
        // and a test that installs the filter itself would stay green without it.
        AccountController::register();
    }

    /**
     * @return array<string, array{0:string,1:string}>
     */
    public function accountFilterParamProvider(): array
    {
        return array(
            'status filter' => array( 'mhmrentiva_status_filter', 'completed' ),
            'search query'  => array( 'mhmrentiva_search_booking', '3726' ),
        );
    }

    /**
     * @dataProvider accountFilterParamProvider
     */
    public function test_account_filter_param_survives_parse_request(string $key, string $value): void
    {
        $this->go_to(home_url('/?' . http_build_query(array( $key => $value ))));

        $this->assertSame(
            $value,
            (string) get_query_var($key),
            sprintf('"%s" must be registered on the query_vars whitelist, or the bookings filter silently stops working.', $key)
        );
    }

    /**
     * The names carry the plugin prefix on purpose: an unprefixed
     * `status_filter` / `search_booking` on a GLOBAL whitelist would collide
     * with any other plugin registering the same word.
     */
    public function test_registered_names_are_prefixed(): void
    {
        $added = array_diff(AccountController::register_query_vars(array()), array());

        $this->assertNotEmpty($added);
        foreach ($added as $var) {
            $this->assertStringStartsWith('mhmrentiva_', $var, "Public query var '$var' must carry the plugin prefix.");
        }
    }

    /**
     * Registering a public query var puts it into the MAIN WP_Query's vars on
     * every front-end request, so the side effect is measured rather than
     * assumed. This pins it on the page the params actually appear on -- an
     * ordinary inner page, which is where the account screen lives -- and the
     * request must resolve to exactly the same page, with the same main query,
     * and must not become a search.
     *
     * Known, deliberately NOT asserted here: appending ANY registered public
     * query var to the site root makes WordPress serve the blog index instead
     * of a static front page, because WP::parse_request() only applies the
     * static front page when no query var was matched. That is core behaviour
     * shared by every var this plugin already registers (`keyword`,
     * `pickup_date`, ... -- the T4 #11 fix WP.org accepted), it predates these
     * two, and no link the product emits puts a bookings filter on the site
     * root. Verified in the browser: `/?pickup_date=...` degrades identically.
     */
    public function test_registration_leaves_the_main_query_untouched_on_an_inner_page(): void
    {
        $page_id = self::factory()->post->create(
            array(
                'post_type'  => 'page',
                'post_title' => 'Query var probe page',
            )
        );
        $permalink = get_permalink($page_id);

        $this->go_to($permalink);
        $baseline = wp_list_pluck($GLOBALS['wp_query']->posts, 'ID');
        $this->assertSame(array( $page_id ), $baseline, 'Fixture must resolve to the probe page, or this proves nothing.');

        $this->go_to(add_query_arg(
            array(
                'mhmrentiva_status_filter'  => 'completed',
                'mhmrentiva_search_booking' => '3726',
            ),
            $permalink
        ));

        $this->assertSame(
            $baseline,
            wp_list_pluck($GLOBALS['wp_query']->posts, 'ID'),
            'The account filter params must not change what the main query returns.'
        );
        $this->assertTrue(is_page($page_id), 'The account filter params must not change which page resolves.');
        $this->assertFalse(is_search(), 'The account filter params must not turn the main query into a search.');
        $this->assertSame('', (string) get_query_var('s'), 'The account filter params must not leak into the core `s` var.');

        wp_delete_post($page_id, true);
    }
}
