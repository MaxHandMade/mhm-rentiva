<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Utilities;

use MHMRentiva\Admin\Utilities\Uninstall\Uninstaller;
use ReflectionMethod;
use WP_UnitTestCase;

/**
 * Lite's uninstall must not delete the add-on's tables.
 *
 * Owner decision, 2026-08-02, reversing the previous behaviour. Six tables --
 * ledger, commission_policy, vendor_reports, background_jobs, payout_audit and
 * key_registry -- are created, read and written by the add-on; Lite queries none
 * of them outside schema and cleanup plumbing. They hold the commission ledger,
 * payout statements and audit trail, and the keys that SIGN the ledger. Dropping
 * them when somebody removes Lite destroys append-only financial history
 * belonging to a different product, on a site that may be reinstalling.
 *
 * Two mechanisms have to agree, which is why both are asserted here: taking the
 * names out of the explicit whitelist achieves nothing on its own, because the
 * orphan safety net's `{prefix}mhmrentiva_%` pattern still matches every one of
 * them.
 *
 * @covers \MHMRentiva\Admin\Utilities\Uninstall\Uninstaller
 */
final class UninstallAddonTableSafetyTest extends WP_UnitTestCase
{
    /**
     * Tables this test creates. DDL commits, so the suite's rollback cannot
     * undo them.
     *
     * @var list<string>
     */
    private array $temp_tables = array();

    /**
     * Plugin tables that existed before the test ran.
     *
     * @var list<string>
     */
    private array $tables_at_entry = array();

    protected function setUp(): void
    {
        parent::setUp();

        $this->tables_at_entry = $this->plugin_table_names();
    }

    /**
     * 🔴 The regression lock for this class's own destructiveness.
     *
     * A test cannot notice its own side effects from the inside: when the
     * capture/create order in the sweep test was wrong, it destroyed the add-on's
     * real ledger table and still reported OK (4 tests). Green was never the
     * signal. So the invariant is asserted from OUTSIDE the test body -- whatever
     * plugin table was here on the way in is here on the way out.
     *
     * This is what makes the ordering rule enforced rather than merely written
     * down; reverting that order turns this red instead of silently dropping a
     * table into the next run of a shared database.
     */
    protected function tearDown(): void
    {
        global $wpdb;

        parent::tearDown();

        foreach ($this->temp_tables as $table) {
            $wpdb->query($wpdb->prepare('DROP TABLE IF EXISTS %i', $table));
        }
        $this->temp_tables = array();

        $vanished = array_diff($this->tables_at_entry, $this->plugin_table_names());

        $this->assertSame(
            array(),
            array_values($vanished),
            'This test destroyed plugin tables that existed before it ran, in a database shared '
            . "with every other class and with the add-on's suite."
        );
    }

    /**
     * @return list<string>
     */
    private function plugin_table_names(): array
    {
        global $wpdb;

        $found = array();

        foreach (array( 'mhmrentiva\_%', 'rentiva\_%', 'mhm\_%' ) as $like) {
            $found = array_merge(
                $found,
                (array) $wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->prefix . $like))
            );
        }

        sort($found);

        return array_values(array_unique($found));
    }

    private function use_real_tables(): void
    {
        remove_filter('query', array( $this, '_create_temporary_tables' ));
        remove_filter('query', array( $this, '_drop_temporary_tables' ));
    }

    /**
     * Suffixes of the add-on's six tables, current spelling.
     *
     * @var list<string>
     */
    private const ADDON_TABLES = array(
        'mhmrentiva_ledger',
        'mhmrentiva_commission_policy',
        'mhmrentiva_vendor_reports',
        'mhmrentiva_background_jobs',
        'mhmrentiva_payout_audit',
        'mhmrentiva_key_registry',
    );

    /**
     * The pre-6.0.0 spelling of a current table name.
     *
     * The dangerous one: a site that has not run the add-on's rename still
     * carries this name, so it is what Lite's uninstall would actually find.
     */
    private function legacy_name(string $current): string
    {
        // prefix-rename:ignore-start
        return str_replace('mhmrentiva_', 'mhm_rentiva_', $current);
        // prefix-rename:ignore-end
    }

    /**
     * @return array<int,string>
     */
    private function invoke(string $method): array
    {
        $reflected = new ReflectionMethod(Uninstaller::class, $method);
        $reflected->setAccessible(true);

        return (array) $reflected->invoke(null);
    }

    /**
     * Mechanism 1: the explicit drop list names none of them, in either spelling.
     */
    public function test_the_drop_list_names_none_of_the_addon_tables(): void
    {
        global $wpdb;

        $listed = $this->invoke('get_all_plugin_tables');

        foreach (self::ADDON_TABLES as $suffix) {
            $this->assertNotContains(
                $wpdb->prefix . $suffix,
                $listed,
                $suffix . ' is the add-on\'s table and Lite\'s uninstall must not drop it.'
            );

            // The pre-6.0.0 spelling is the one a site that has not run the
            // add-on's rename still carries -- the more dangerous of the two.
            $legacy = $this->legacy_name($suffix);
            $this->assertNotContains(
                $wpdb->prefix . $legacy,
                $listed,
                $legacy . ' is the add-on\'s table under its pre-6.0.0 name.'
            );
        }
    }

    /**
     * Mechanism 2: and the broad orphan pattern is carved out by identity, so
     * removing them from the list above is not silently undone.
     */
    public function test_the_orphan_pattern_carve_out_covers_both_spellings(): void
    {
        global $wpdb;

        $protected = $this->invoke('addon_owned_tables');

        foreach (self::ADDON_TABLES as $suffix) {
            $legacy = $this->legacy_name($suffix);

            $this->assertContains($wpdb->prefix . $suffix, $protected, $suffix . ' is not carved out of the orphan sweep.');
            $this->assertContains($wpdb->prefix . $legacy, $protected, $legacy . ' is not carved out of the orphan sweep.');
        }

        // Every add-on table matches the broad pattern, which is exactly why the
        // carve-out is needed rather than merely tidy.
        foreach (self::ADDON_TABLES as $suffix) {
            $this->assertStringStartsWith(
                $wpdb->prefix . 'mhmrentiva_',
                $wpdb->prefix . $suffix,
                'Premise: the orphan pattern would match this table.'
            );
        }
    }

    /**
     * ...and the sweep actually SPARES them, run for real.
     *
     * The previous version looked for the strings addon_owned_tables() and
     * is_backup_table( inside a window of the source. Mutation showed that was
     * only half a lock: deleting the CALL made it red, but deleting the
     * in_array() FILTER while leaving the assignment kept it green and dropped
     * all six add-on tables. A lock a substring satisfies is not a lock on
     * behaviour.
     *
     * So this runs the real uninstall against real tables. The positive control
     * matters as much as the negative one: a genuine orphan must be dropped in
     * the same run, or "the add-on's table survived" would also be true of a
     * sweep that never executed.
     *
     * "The list holds the right names" is a SEPARATE question, and is asserted
     * separately by test_the_orphan_pattern_carve_out_covers_both_spellings()
     * for all six tables in both spellings. This one asks only whether the sweep
     * honours the list it is given.
     *
     * 🔴 uninstall_direct() is DESTRUCTIVE and its DDL commits, so it outlives
     * the suite's per-test rollback AND the run itself -- this database is
     * shared with every other test class and with the add-on's suite. The
     * schema is captured before and replayed after; without that, dropping
     * Lite's transfer tables here surfaces as unrelated failures in a later
     * run, which is exactly the cross-run damage this round has already chased
     * twice. Two rules keep the safety net from having a hole in the middle:
     * capture happens before this test's own DDL, and an already-existing table
     * is never re-created.
     */
    public function test_the_real_uninstall_spares_addon_tables_and_still_drops_orphans(): void
    {
        global $wpdb;

        $this->use_real_tables();

        $addon  = $wpdb->prefix . 'mhmrentiva_ledger';
        $orphan = $wpdb->prefix . 'mhmrentiva_zz_probe_orphan';
        $backup = $wpdb->prefix . 'mhmrentiva_merge_losers_backup_20200101_000000_probe';

        // 🔴 CAPTURE FIRST. $addon is not a probe name -- it is the add-on's real
        // table, and it has to be, because the protection it is testing is keyed
        // by exact name. An earlier version of this test captured AFTER its own
        // DROP/CREATE below, so the real ledger's schema and rows were destroyed
        // before anything was recorded, $restore held only the stand-in, and
        // tearDown() then dropped that too. The test written to stop cross-run
        // schema damage was doing cross-run schema damage.
        $restore = $this->capture_plugin_schema();
        $this->assertNotEmpty($restore, 'Premise: there is plugin schema to protect, so the restore is doing something.');

        // ...and never overwrite a table that is already there. If the add-on is
        // checked out, its ledger exists with real rows; the test only needs the
        // table to EXIST, so an existing one is left exactly as it is. Only what
        // this test actually creates goes on the tearDown list.
        foreach (array( $addon, $orphan, $backup ) as $table) {
            if ($this->table_exists($table)) {
                continue;
            }

            $wpdb->query($wpdb->prepare('CREATE TABLE %i ( id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, PRIMARY KEY (id) )', $table));
            $this->temp_tables[] = $table;
        }

        foreach (array( $addon, $orphan, $backup ) as $table) {
            $this->assertTrue($this->table_exists($table), 'Premise: ' . $table . ' must exist before the uninstall.');
            // And that the broad orphan pattern really would reach it -- without
            // this, "the add-on table survived" could just mean the sweep never
            // looked at it.
            $this->assertStringStartsWith($wpdb->prefix . 'mhmrentiva_', $table, 'Premise: the orphan pattern would match this table.');
        }

        Uninstaller::uninstall_direct(false);

        $spared_addon  = $this->table_exists($addon);
        $spared_backup = $this->table_exists($backup);
        $dropped       = ! $this->table_exists($orphan);

        $this->replay_plugin_schema($restore);

        $this->assertTrue($spared_addon, "Lite's uninstall dropped an add-on table.");
        $this->assertTrue($spared_backup, "Lite's uninstall dropped a recovery copy without being asked to.");
        $this->assertTrue($dropped, 'The orphan sweep did not run at all, so sparing the add-on table proves nothing.');
    }

    /**
     * SHOW CREATE TABLE for every plugin table, so the run can put back what
     * uninstall_direct() destroys in this shared database.
     *
     * @return array<string,string> table name => CREATE statement
     */
    private function capture_plugin_schema(): array
    {
        global $wpdb;

        $captured = array();

        foreach (array( 'mhmrentiva\_%', 'rentiva\_%', 'mhm\_%' ) as $like) {
            $tables = (array) $wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->prefix . $like));

            foreach ($tables as $table) {
                $row = $wpdb->get_row($wpdb->prepare('SHOW CREATE TABLE %i', $table), ARRAY_N);
                if (is_array($row) && isset($row[1])) {
                    $captured[ $table ] = (string) $row[1];
                }
            }
        }

        return $captured;
    }

    /**
     * @param array<string,string> $schema
     */
    private function replay_plugin_schema(array $schema): void
    {
        global $wpdb;

        foreach ($schema as $table => $create) {
            if ($this->table_exists($table)) {
                continue;
            }

            $wpdb->query($create);
        }
    }

    private function table_exists(string $table): bool
    {
        global $wpdb;

        return null !== $wpdb->get_var(
            $wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))
        );
    }

    /**
     * Recovery copies are gated on the owner's answer, not dropped regardless.
     *
     * `is_backup_table()` is what excludes them from the broad sweep; the
     * $delete_backups branch is what puts them back in when the owner asked.
     */
    public function test_backup_tables_are_recognised_so_the_flag_can_gate_them(): void
    {
        $reflected = new ReflectionMethod(Uninstaller::class, 'is_backup_table');
        $reflected->setAccessible(true);

        // prefix-rename:ignore-start
        $backups = array(
            'wp_mhmrentiva_postmeta_backup_invalid_20260320_092228',
            'wp_mhmrentiva_merge_losers_backup_20260802_065325_xIgGvQ',
            'wp_mhm_postmeta_backup_invalid_20260320_092228',
        );
        // prefix-rename:ignore-end

        foreach ($backups as $backup) {
            $this->assertTrue($reflected->invoke(null, $backup), $backup . ' must be recognised as a recovery copy.');
        }

        foreach (array(
            'wp_mhmrentiva_queue',
            'wp_mhmrentiva_ratings',
            'wp_mhmrentiva_sessions',
            // 🔴 The case the anchoring exists for, and the one the first
            // version of this list was missing: a real DATA table whose NAME
            // contains the word backup. A bare substring test calls it a
            // recovery copy, the orphan sweep then skips it, and it survives
            // only because the explicit whitelist happens to run first --
            // an ordering dependency nothing asserts. Without this fixture the
            // anchoring was unlocked: reverting it to strpos() left this test
            // green.
            'wp_mhmrentiva_backup_records',
        ) as $ordinary) {
            $this->assertFalse($reflected->invoke(null, $ordinary), $ordinary . ' is not a recovery copy.');
        }
    }
}
