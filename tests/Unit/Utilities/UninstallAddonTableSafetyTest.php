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
     * ...and the sweep actually CONSULTS the carve-out.
     *
     * Asserting the helper's contents was not enough, and mutation proved it:
     * emptying `$protected` at the call site left this class entirely green,
     * because nothing tied the list to the loop that is supposed to honour it.
     * A list nobody reads protects nothing.
     *
     * Deliberately a source-level assertion. The alternative is running
     * uninstall_direct() for real, which deletes the test database; the
     * behavioural proof is done once against the dev database instead, and this
     * keeps the wiring from being removed unnoticed in between.
     */
    public function test_the_orphan_sweep_consults_the_carve_out_and_the_backup_guard(): void
    {
        $source = (string) file_get_contents(
            dirname(__DIR__, 3) . '/src/Admin/Utilities/Uninstall/Uninstaller.php'
        );

        $start = strpos($source, 'public static function uninstall_direct(');
        $this->assertNotFalse($start, 'uninstall_direct() not found -- this assertion is measuring nothing.');

        $sweep = strpos($source, '$orphans = $wpdb->get_col(', $start);
        $this->assertNotFalse($sweep, 'The orphan sweep was not found where this test expects it.');

        $body = substr($source, $start, $sweep - $start + 600);

        $this->assertStringContainsString(
            'self::addon_owned_tables()',
            $body,
            'The orphan sweep does not consult the add-on carve-out, so the broad pattern reaches the add-on\'s tables.'
        );
        $this->assertStringContainsString(
            'self::is_backup_table(',
            $body,
            'The orphan sweep does not consult the backup guard, so recovery copies are dropped whatever $delete_backups says.'
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

        foreach (array( 'wp_mhmrentiva_queue', 'wp_mhmrentiva_ratings', 'wp_mhmrentiva_sessions' ) as $ordinary) {
            $this->assertFalse($reflected->invoke(null, $ordinary), $ordinary . ' is not a recovery copy.');
        }
    }
}
