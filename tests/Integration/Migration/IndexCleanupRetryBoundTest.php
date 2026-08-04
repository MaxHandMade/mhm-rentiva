<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Migration;

use MHMRentiva\Admin\Core\Utilities\DatabaseMigrator;
use MHMRentiva\Admin\Core\Utilities\RetiredIndexes;
use WP_UnitTestCase;

/**
 * S-1 / arch-I3 (WP.org T8 fix wave, group A): the pre-fix contract was
 * "failed drop -> no stamp -> retry from scratch next request", with no
 * upper bound. Plugin.php registers run_migrations() on `admin_init` with no
 * capability gate, and `admin_init` fires for an unauthenticated
 * admin-post.php/admin-ajax.php request -- so on an install where the DROP
 * can never succeed (a DB user without INDEX/ALTER grants, ordinary on
 * managed hosting), every such request replayed the entire migration body
 * forever: ~10 tables of dbDelta, up to 35 SHOW/DROP statements, and one
 * error-level log write (a CPT post + 6 postmeta rows) per replay.
 *
 * DatabaseMigrator::run_migrations() now takes two optional seam parameters
 * threaded straight into RetiredIndexes::drop() -- $index_cleanup_expected
 * and $index_cleanup_runner -- so a test can force a controlled index-drop
 * failure against a disposable fixture table without ever touching a real
 * WordPress core table (same technique IndexCleanupMigrationTest already
 * uses for RetiredIndexes::drop() directly). Every existing call site
 * (Plugin.php's admin_init hook, mhm-rentiva.php's two lanes, every prior
 * test) calls run_migrations() with zero arguments and is unaffected.
 *
 * @covers \MHMRentiva\Admin\Core\Utilities\DatabaseMigrator::run_migrations
 */
final class IndexCleanupRetryBoundTest extends WP_UnitTestCase
{
    private const DB_VERSION        = 'mhmrentiva_db_version';
    private const ATTEMPTS_OPTION   = 'mhmrentiva_index_cleanup_attempts';
    private const UNFINISHED_OPTION = 'mhmrentiva_index_cleanup_unfinished';

    /**
     * Table-suffix key RetiredIndexes::drop() expects: it strips the first 3
     * characters ('wp_') and re-prepends $wpdb->prefix, exactly like
     * IndexCleanupMigrationTest::FIXTURE_SUFFIX.
     */
    private const FIXTURE_SUFFIX = 'wp_t8_retry_fixture';

    private string $fixture;

    private mixed $previous_db_version = false;

    protected function setUp(): void
    {
        parent::setUp();
        global $wpdb;
        $this->fixture = $wpdb->prefix . 't8_retry_fixture';
        $wpdb->query("CREATE TABLE {$this->fixture} (post_id BIGINT UNSIGNED NOT NULL, meta_key VARCHAR(255), meta_value LONGTEXT)");

        $this->previous_db_version = get_option(self::DB_VERSION);
        delete_option(self::ATTEMPTS_OPTION);
        delete_option(self::UNFINISHED_OPTION);
    }

    protected function tearDown(): void
    {
        global $wpdb;
        $wpdb->query("DROP TABLE IF EXISTS {$this->fixture}");

        if (false === $this->previous_db_version) {
            delete_option(self::DB_VERSION);
        } else {
            update_option(self::DB_VERSION, $this->previous_db_version);
        }
        delete_option(self::ATTEMPTS_OPTION);
        delete_option(self::UNFINISHED_OPTION);
        delete_option('mhmrentiva_plugin_version');

        parent::tearDown();
    }

    /**
     * Opens the version gate so run_migrations()'s body actually executes.
     */
    private function open_gate(): void
    {
        update_option(self::DB_VERSION, '1.0.0');
    }

    /**
     * One column-signature row -- same shape as
     * IndexCleanupMigrationTest::sig().
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

    /**
     * @return array<string, array<string, list<array{seq:int, col:string, sub:?int, non_unique:int, type:string}>>>
     */
    private function fixture_expected(): array
    {
        return array(
            self::FIXTURE_SUFFIX => array(
                'idx_t8_retry' => array($this->sig(1, 'meta_key', 50)),
            ),
        );
    }

    private function create_fixture_index(): void
    {
        global $wpdb;
        $wpdb->query("CREATE INDEX idx_t8_retry ON {$this->fixture} (meta_key(50))");
    }

    private function always_fail_runner(): \Closure
    {
        return static fn (): bool => false;
    }

    /**
     * (a) Transient failure: retried on the next call, then succeeds ->
     * stamped, attempts reset.
     */
    public function test_transient_failure_is_retried_then_succeeds_and_resets_attempts(): void
    {
        $this->open_gate();
        $this->create_fixture_index();
        $expected = $this->fixture_expected();

        DatabaseMigrator::run_migrations($expected, $this->always_fail_runner());

        $this->assertSame(
            '1.0.0',
            get_option(self::DB_VERSION),
            'A failed drop must not stamp the version.'
        );
        $this->assertSame(1, (int) get_option(self::ATTEMPTS_OPTION));

        global $wpdb;
        $this->assertNotNull(
            RetiredIndexes::signature($wpdb, $this->fixture, 'idx_t8_retry'),
            'Premise: the forced failure must not actually have dropped the index.'
        );

        // Next request: no forced failure this time (the real runner is used,
        // and the fixture index genuinely exists), so the drop succeeds --
        // modelling a transient failure (e.g. a lock, a permissions gap mid
        // deploy) that clears itself.
        DatabaseMigrator::run_migrations($expected);

        $this->assertNotSame(
            '1.0.0',
            get_option(self::DB_VERSION),
            'A successful cleanup must stamp the version.'
        );
        $this->assertNull(
            RetiredIndexes::signature($wpdb, $this->fixture, 'idx_t8_retry'),
            'The index must actually be gone once the retry succeeds.'
        );
        $this->assertFalse(
            get_option(self::ATTEMPTS_OPTION),
            'A successful run must reset the attempt counter (so a future version bump starts its own budget fresh).'
        );
        $this->assertFalse(get_option(self::UNFINISHED_OPTION));
    }

    /**
     * (b) Permanent failure: after the retry budget is exhausted, the version
     * stamps WITH the outstanding names on record, and no further request
     * replays the DDL -- the injected runner is not called again.
     */
    public function test_permanent_failure_stamps_with_diagnostics_after_budget_and_stops_retrying(): void
    {
        $this->open_gate();
        $this->create_fixture_index();
        $expected = $this->fixture_expected();
        $max      = DatabaseMigrator::INDEX_CLEANUP_MAX_ATTEMPTS;
        $this->assertGreaterThan(1, $max, 'Premise: the budget must allow more than one attempt to be meaningful.');

        for ($i = 1; $i < $max; $i++) {
            DatabaseMigrator::run_migrations($expected, $this->always_fail_runner());
            $this->assertSame(
                '1.0.0',
                get_option(self::DB_VERSION),
                "Attempt {$i}/{$max}: still under budget, must not stamp yet."
            );
            $this->assertSame($i, (int) get_option(self::ATTEMPTS_OPTION));
        }

        // The Nth (final) attempt exhausts the budget.
        DatabaseMigrator::run_migrations($expected, $this->always_fail_runner());

        $this->assertNotSame(
            '1.0.0',
            get_option(self::DB_VERSION),
            'Once the budget is exhausted the migration must stamp so it stops retrying, even though the drop never succeeded.'
        );
        $this->assertSame(
            array('idx_t8_retry'),
            get_option(self::UNFINISHED_OPTION),
            'The still-undropped names must be on record for diagnostics.'
        );
        $this->assertFalse(
            get_option(self::ATTEMPTS_OPTION),
            'The attempt counter must not survive past the stamp -- a future version bump must start its own budget at zero.'
        );

        // One more simulated request (e.g. another unauthenticated
        // admin-post.php hit): the version gate is now shut, so
        // run_migrations() must return before ever reaching the index
        // cleanup step. A spy runner proves it: if it were invoked even
        // once more, this assertion fails.
        $calls = 0;
        $spy   = static function () use (&$calls): bool {
            ++$calls;
            return false;
        };
        DatabaseMigrator::run_migrations($expected, $spy);

        $this->assertSame(
            0,
            $calls,
            'A stamped migration must not run any further DDL on subsequent requests.'
        );
    }

    /**
     * (c) The pre-existing failed -> no-stamp behaviour still holds within
     * the budget (the lock arch-I3 said was code-reading only).
     */
    public function test_failure_within_budget_does_not_stamp(): void
    {
        $this->open_gate();
        $this->create_fixture_index();

        DatabaseMigrator::run_migrations($this->fixture_expected(), $this->always_fail_runner());

        $this->assertSame(
            '1.0.0',
            get_option(self::DB_VERSION),
            'failed => no stamp must still hold for a single failure, well within the retry budget.'
        );
    }

    /**
     * Reconciliation lock (arch-I3 point 4): Lane A (mhm-rentiva.php:287-290)
     * stamps `mhmrentiva_plugin_version` unconditionally, regardless of
     * whether run_migrations() actually finished. That stamp must have no
     * bearing on whether Plugin.php:568's `admin_init` registration keeps
     * retrying the DB-version-gated migration -- the two lanes track two
     * different things (code version vs. schema version) and must not be
     * able to shadow one another.
     */
    public function test_lane_a_plugin_version_stamp_does_not_suppress_the_admin_init_retry(): void
    {
        $this->open_gate();
        $this->create_fixture_index();
        $expected = $this->fixture_expected();

        DatabaseMigrator::run_migrations($expected, $this->always_fail_runner());
        $this->assertSame('1.0.0', get_option(self::DB_VERSION), 'Premise: first attempt failed and did not stamp.');

        // Simulate Lane A: it calls run_migrations() then unconditionally
        // stamps mhmrentiva_plugin_version, independent of the outcome.
        update_option('mhmrentiva_plugin_version', defined('MHMRENTIVA_VERSION') ? MHMRENTIVA_VERSION : '6.0.1');

        // Simulate the NEXT request's Plugin.php:568 admin_init call -- a
        // spy proves the retry still happens.
        $calls = 0;
        $spy   = static function () use (&$calls): bool {
            ++$calls;
            return false;
        };
        DatabaseMigrator::run_migrations($expected, $spy);

        $this->assertGreaterThan(
            0,
            $calls,
            "Lane A's unconditional mhmrentiva_plugin_version stamp must not suppress the admin_init retry -- mhmrentiva_db_version is the only gate run_migrations() itself reads."
        );
    }
}
