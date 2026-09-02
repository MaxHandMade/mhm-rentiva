<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Core;

use WP_UnitTestCase;

/**
 * Network-wide activation has to reach EVERY site in the network, and the two
 * ways it failed to were both invisible on a small test network.
 *
 * 1. WP_Site_Query defaults `number` to 100 (wp-includes/class-wp-site-query.php,
 *    __construct()). A caller that does not say otherwise silently gets the
 *    first hundred sites. On a network of 40 that is indistinguishable from
 *    "all of them"; on a network of 400 the plugin activates on a quarter of
 *    the sites and reports success, and the operator finds out when a subsite
 *    has no tables.
 *
 * 2. `'public' => 1` dropped every non-public site. A subsite marked
 *    not-public is still a site that needs its schema -- being unlisted is a
 *    directory decision, not a statement that the plugin should be half
 *    installed there.
 *
 * The defaults for `public`, `archived`, `spam` and `deleted` are all null,
 * i.e. no constraint. `deleted` is the one worth constraining on purpose: a
 * deleted site is one WordPress itself treats as gone.
 *
 * @group multisite
 *   Opted in to the multisite run (composer test:multisite). get_sites() on a
 *   single site is not the code path this protects.
 *
 * @covers ::mhmrentiva_network_site_ids
 */
final class NetworkActivationSitesTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // The function memoises into the object cache; a value left by an
        // earlier test would make every assertion below measure that value
        // instead of the query.
        wp_cache_delete('mhmrentiva_network_blogs');
    }

    protected function tearDown(): void
    {
        wp_cache_delete('mhmrentiva_network_blogs');
        remove_all_actions('parse_site_query');
        parent::tearDown();
    }

    private function skip_unless_network(): void
    {
        if (! is_multisite()) {
            $this->markTestSkipped('Multisite-only; run via: composer test:multisite');
        }
    }

    /**
     * Asserting the query vars rather than building 101 sites: the cap is a
     * property of the query, and a fixture large enough to cross it would add
     * minutes to every run to re-measure what core already documents.
     */
    public function test_it_does_not_inherit_the_hundred_site_cap(): void
    {
        $this->skip_unless_network();

        $seen = null;
        add_action(
            'parse_site_query',
            static function ($query) use (&$seen): void {
                $seen = $query->query_vars;
            }
        );

        mhmrentiva_network_site_ids();

        $this->assertIsArray($seen, 'The activation path did not run a site query at all.');
        $this->assertSame(
            0,
            $seen['number'],
            'number must be 0 (unlimited). Leaving it at the WP_Site_Query '
            . 'default of 100 activates the plugin on the first hundred sites '
            . 'of a larger network and calls that success.'
        );
        $this->assertNull(
            $seen['public'],
            'Constraining `public` skips subsites that still need their schema.'
        );
    }

    public function test_a_non_public_site_is_still_activated(): void
    {
        $this->skip_unless_network();

        // Creating a site fires the plugin's own wpmu_new_blog handler
        // (mhm-rentiva.php), which core deprecated in 5.1 in favour of
        // wp_initialize_site. Declared, not silenced: these are the first
        // tests that create sites at runtime, so they are also the first to
        // exercise that handler, and the declaration is where the open debt
        // is visible rather than hidden.
        $this->setExpectedDeprecated('wpmu_new_blog');

        $public_id     = self::factory()->blog->create();
        $non_public_id = self::factory()->blog->create(array( 'public' => 0 ));

        $ids = mhmrentiva_network_site_ids();

        $this->assertContains($public_id, $ids);
        $this->assertContains(
            $non_public_id,
            $ids,
            'A non-public subsite was skipped. Not being listed in the network '
            . 'directory is not a reason to leave a site without tables.'
        );
    }

    public function test_a_deleted_site_is_left_out(): void
    {
        $this->skip_unless_network();

        // Creating a site fires the plugin's own wpmu_new_blog handler
        // (mhm-rentiva.php), which core deprecated in 5.1 in favour of
        // wp_initialize_site. Declared, not silenced: these are the first
        // tests that create sites at runtime, so they are also the first to
        // exercise that handler, and the declaration is where the open debt
        // is visible rather than hidden.
        $this->setExpectedDeprecated('wpmu_new_blog');

        $live_id    = self::factory()->blog->create();
        $deleted_id = self::factory()->blog->create();
        update_blog_details($deleted_id, array( 'deleted' => 1 ));

        wp_cache_delete('mhmrentiva_network_blogs');
        $ids = mhmrentiva_network_site_ids();

        $this->assertContains($live_id, $ids);
        $this->assertNotContains(
            $deleted_id,
            $ids,
            'A site WordPress marks deleted should not be activated into.'
        );
    }

    public function test_it_returns_integers(): void
    {
        $this->skip_unless_network();

        $ids = mhmrentiva_network_site_ids();

        $this->assertNotEmpty($ids);
        foreach ($ids as $id) {
            $this->assertIsInt($id, 'switch_to_blog() is given these directly.');
        }
    }
}
