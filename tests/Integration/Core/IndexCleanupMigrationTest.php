<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Core;

use MHMRentiva\Admin\Core\Utilities\RetiredIndexes;
use WP_UnitTestCase;

/**
 * RetiredIndexes::drop() — the single source of truth DatabaseMigrator and
 * uninstall.php both call to remove the 35 indexes this plugin used to create
 * on WordPress CORE tables (wp_posts / wp_postmeta / wp_usermeta).
 *
 * Every case below runs against a throwaway FIXTURE table, never against the
 * real wp_postmeta -- DDL on the live core table is exactly what this task
 * removes, and a test suite that reintroduced it to prove the removal would
 * be self-defeating. The fixture's shape (post_id BIGINT, meta_key VARCHAR(255),
 * meta_value LONGTEXT) mirrors wp_postmeta closely enough that the same
 * signatures (prefix lengths on meta_key/meta_value) behave identically.
 *
 * drop()'s contract, exercised one case per method:
 *  1. an index whose NAME and SIGNATURE both match $expected -- ours -- is dropped.
 *  2. an index whose name matches but whose signature does not -- not ours,
 *     a site owner or another plugin could have created it -- survives, and
 *     is reported under 'skipped' rather than silently ignored.
 *  3. an index never named in $expected at all is untouched.
 *  4. running drop() again after a successful drop is a no-op: 'dropped' is
 *     empty (nothing left to do), not an error.
 *  5. a DROP that fails (forced via the injected $runner seam) is reported
 *     under 'failed', and critically -- the index is confirmed still present
 *     afterward, so a failure can never masquerade as success.
 */
final class IndexCleanupMigrationTest extends WP_UnitTestCase
{
    private string $fixture;

    /**
     * The table-suffix key RetiredIndexes::drop() expects in $expected: it
     * strips the first 3 characters ('wp_') and re-prepends $wpdb->prefix,
     * so this works under any real prefix, test or otherwise.
     */
    private const FIXTURE_SUFFIX = 'wp_t8_idx_fixture';

    protected function setUp(): void
    {
        parent::setUp();
        global $wpdb;
        $this->fixture = $wpdb->prefix . 't8_idx_fixture';
        $wpdb->query("CREATE TABLE {$this->fixture} (post_id BIGINT UNSIGNED NOT NULL, meta_key VARCHAR(255), meta_value LONGTEXT)");
    }

    protected function tearDown(): void
    {
        global $wpdb;
        $wpdb->query("DROP TABLE IF EXISTS {$this->fixture}");
        parent::tearDown();
    }

    /**
     * One column-signature row, in the exact key order/types both
     * RetiredIndexes::LIST and RetiredIndexes::signature() use.
     *
     * @return array{seq:int, col:string, sub:?int, non_unique:int, type:string}
     */
    private function sig(int $seq, string $col, ?int $sub = null): array
    {
        return array(
            'seq'        => $seq,
            'col'        => $col,
            'sub'        => $sub,
            'non_unique' => 1,
            'type'       => 'BTREE',
        );
    }

    public function test_index_matching_name_and_signature_is_dropped(): void
    {
        global $wpdb;
        $wpdb->query("CREATE INDEX idx_t8_a ON {$this->fixture} (meta_key(50), post_id)");

        $expected = array(
            self::FIXTURE_SUFFIX => array(
                'idx_t8_a' => array($this->sig(1, 'meta_key', 50), $this->sig(2, 'post_id')),
            ),
        );

        $result = RetiredIndexes::drop($wpdb, $expected);

        $this->assertSame(array('idx_t8_a'), $result['dropped']);
        $this->assertSame(array(), $result['skipped']);
        $this->assertSame(array(), $result['failed']);
        $this->assertNull(
            RetiredIndexes::signature($wpdb, $this->fixture, 'idx_t8_a'),
            'The index must actually be gone from the table, not just reported dropped.'
        );
    }

    public function test_same_name_different_signature_is_skipped_not_dropped(): void
    {
        global $wpdb;
        // Same NAME as the expected entry below, but a DIFFERENT column list --
        // stands in for a site owner's or another plugin's same-named index.
        $wpdb->query("CREATE INDEX idx_t8_b ON {$this->fixture} (post_id)");

        $expected = array(
            self::FIXTURE_SUFFIX => array(
                'idx_t8_b' => array($this->sig(1, 'meta_key', 50), $this->sig(2, 'post_id')),
            ),
        );

        $result = RetiredIndexes::drop($wpdb, $expected);

        $this->assertSame(array(), $result['dropped']);
        $this->assertSame(array('idx_t8_b'), $result['skipped']);
        $this->assertSame(array(), $result['failed']);
        $this->assertNotNull(
            RetiredIndexes::signature($wpdb, $this->fixture, 'idx_t8_b'),
            'A same-named index with a different signature is not ours and must not be touched.'
        );
    }

    public function test_unrelated_index_is_untouched(): void
    {
        global $wpdb;
        $wpdb->query("CREATE INDEX idx_t8_unrelated ON {$this->fixture} (meta_value(20))");

        // $expected names a DIFFERENT index that does not exist on the fixture
        // at all -- drop() must find nothing to do and must not touch
        // idx_t8_unrelated, which it was never told about.
        $expected = array(
            self::FIXTURE_SUFFIX => array(
                'idx_t8_c' => array($this->sig(1, 'meta_key', 50)),
            ),
        );

        $result = RetiredIndexes::drop($wpdb, $expected);

        $this->assertSame(array(), $result['dropped']);
        $this->assertSame(array(), $result['skipped']);
        $this->assertSame(array(), $result['failed']);
        $this->assertNotNull(
            RetiredIndexes::signature($wpdb, $this->fixture, 'idx_t8_unrelated'),
            'An index never named in $expected must survive untouched.'
        );
    }

    public function test_second_run_is_idempotent_with_an_empty_dropped_list(): void
    {
        global $wpdb;
        $wpdb->query("CREATE INDEX idx_t8_d ON {$this->fixture} (meta_key(50))");

        $expected = array(
            self::FIXTURE_SUFFIX => array(
                'idx_t8_d' => array($this->sig(1, 'meta_key', 50)),
            ),
        );

        $first = RetiredIndexes::drop($wpdb, $expected);
        $this->assertSame(array('idx_t8_d'), $first['dropped'], 'Premise: the first run actually drops it.');

        $second = RetiredIndexes::drop($wpdb, $expected);

        $this->assertSame(array(), $second['dropped'], 'A second run must find nothing left to drop.');
        $this->assertSame(array(), $second['skipped'], 'An already-absent index is not "not ours" -- it is just gone.');
        $this->assertSame(array(), $second['failed']);
    }

    public function test_forced_drop_failure_is_reported_and_the_index_survives(): void
    {
        global $wpdb;
        $wpdb->query("CREATE INDEX idx_t8_e ON {$this->fixture} (meta_key(50))");

        $expected = array(
            self::FIXTURE_SUFFIX => array(
                'idx_t8_e' => array($this->sig(1, 'meta_key', 50)),
            ),
        );

        // The error seam: production passes no $runner and drop() falls back to
        // $wpdb->query(); this test injects a runner that always reports
        // failure without ever touching the database, forcing the failure path.
        $result = RetiredIndexes::drop($wpdb, $expected, static fn (): bool => false);

        $this->assertSame(array(), $result['dropped']);
        $this->assertSame(array(), $result['skipped']);
        $this->assertSame(array('idx_t8_e'), $result['failed']);
        $this->assertNotNull(
            RetiredIndexes::signature($wpdb, $this->fixture, 'idx_t8_e'),
            'A reported failure must be true: the index must still be there.'
        );
    }

    /**
     * usermeta is a GLOBAL table -- WordPress never re-prefixes it per site,
     * so drop() must resolve it via $wpdb->usermeta, not $wpdb->prefix .
     * 'usermeta' (which would name a non-existent table on any subsite other
     * than the one $wpdb->prefix currently points at).
     *
     * Proven here WITHOUT touching the real wp_usermeta table and WITHOUT
     * real multisite blog-switching: $wpdb->usermeta is a plain public
     * property, so it is redirected at the fixture for the duration of one
     * call and restored in `finally`. If drop() ever regresses to
     * $wpdb->prefix . 'usermeta', it looks at the real (untouched, and here
     * index-free) usermeta table instead of the fixture, finds nothing
     * there, and 'dropped' comes back empty -- this assertion goes red.
     */
    public function test_usermeta_resolves_via_the_global_wpdb_property_not_a_per_site_prefix(): void
    {
        global $wpdb;
        $wpdb->query("CREATE INDEX idx_t8_usermeta ON {$this->fixture} (meta_key(50))");

        $expected = array(
            'wp_usermeta' => array(
                'idx_t8_usermeta' => array($this->sig(1, 'meta_key', 50)),
            ),
        );

        $original_usermeta = $wpdb->usermeta;
        $wpdb->usermeta     = $this->fixture;
        try {
            $result = RetiredIndexes::drop($wpdb, $expected);
        } finally {
            $wpdb->usermeta = $original_usermeta;
        }

        $this->assertSame(
            array('idx_t8_usermeta'),
            $result['dropped'],
            'drop() must resolve the "wp_usermeta" suffix via $wpdb->usermeta (redirected here to the fixture), not via $wpdb->prefix . "usermeta".'
        );
    }

    /**
     * Canary for tests/phpunit/multisite.xml (Adım 2/12): if that config stops
     * actually enabling multisite -- a typo, a schema drift, WP core changing
     * how WP_TESTS_MULTISITE is consumed -- this is the test that says so,
     * instead of the multisite run silently exercising the single-site code
     * path under a different config file. Skipped (not failed) under the
     * plugin's normal phpunit.xml, where WP_TESTS_MULTISITE=0 by design.
     */
    public function test_multisite_xml_config_actually_enables_multisite(): void
    {
        if (! defined('WP_TESTS_MULTISITE') || 1 !== (int) WP_TESTS_MULTISITE) {
            $this->markTestSkipped(
                'Multisite-only sentinel; run via: vendor/bin/phpunit -c tests/phpunit/multisite.xml --filter IndexCleanupMigrationTest'
            );
        }

        $this->assertTrue(
            is_multisite(),
            'tests/phpunit/multisite.xml must actually enable multisite, not merely define the constant.'
        );
    }
}
