<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Migration;

use MHMRentiva\Admin\Core\Utilities\DatabaseMigrator;
use PHPUnit\Framework\TestCase;

/**
 * The lockstep is two-sided: Lite refuses to migrate under an old add-on.
 *
 * Pro has refused to boot or migrate against an old Lite since 6.0.0. Nothing on
 * Lite's side reciprocated, which left the last cell of the upgrade matrix open:
 * new Lite + OLD add-on ran the whole prefix rename -- including the vendor
 * identity rewrite -- underneath an add-on still querying the old names. Pro's
 * bootstrap records that this combination "produced the white screen".
 *
 * These tests exercise the FAILURE path, which is the only reason
 * pro_satisfies() is a pure function taking the version as an argument rather
 * than reading the constant directly. A constant cannot be redefined, so a check
 * written against MHMRENTIVA_PRO_VERSION could only ever be tested against
 * whichever add-on happens to be installed -- and would pass forever on a
 * machine with a current one. That is exactly how Pro's own floor constant spent
 * its whole life as a declaration nothing enforced.
 *
 * 🔴 EXTENDS TestCase, NOT WP_UnitTestCase, and that is load-bearing rather than
 * a style choice. pro_satisfies() is pure -- version_compare() and nothing else
 * -- so it needs no WordPress. The first version of this class extended
 * WP_UnitTestCase anyway, and its per-test fixture/transaction lifecycle
 * destabilised the whole suite from inside tests/Migration/: the full run went
 * from a steady 7 failures to 25, then 16, then 19, then 9, flapping across
 * unrelated classes, with these eight tests passing every time. Bisected --
 * baseline code 7, my source changes alone 7, source plus this file 9-25, this
 * file converted to TestCase 7 again, all at 1349 tests.
 *
 * The suite has a latent order-dependence that this class merely perturbed; that
 * weakness is real and is reported separately. The rule this leaves behind: a
 * test for a pure function has no business paying for a WordPress fixture.
 *
 * @covers \MHMRentiva\Admin\Core\Utilities\DatabaseMigrator::pro_satisfies
 */
final class ProLockstepGuardTest extends TestCase
{
    /**
     * @dataProvider incompatibleProvider
     */
    public function test_an_old_addon_blocks_the_migration(string $pro): void
    {
        $this->assertFalse(
            DatabaseMigrator::pro_satisfies($pro),
            'Pro ' . $pro . ' predates the rename, so Lite must not migrate underneath it.'
        );
    }

    /**
     * @return array<string, array{0:string}>
     */
    public function incompatibleProvider(): array
    {
        return array(
            'the version that shipped before the rename' => array( '5.2.3' ),
            'the last 5.x'                               => array( '5.2.4' ),
            'a much older build'                         => array( '4.30.0' ),
            // Guards against a naive string comparison, under which '10.0.0'
            // sorts below '6.0.0' and a FUTURE add-on would be refused.
            'not a string sort'                          => array( '5.10.0' ),
        );
    }

    /**
     * @dataProvider compatibleProvider
     */
    public function test_a_current_or_absent_addon_does_not_block(string $pro, string $why): void
    {
        $this->assertTrue(DatabaseMigrator::pro_satisfies($pro), $why);
    }

    /**
     * @return array<string, array{0:string,1:string}>
     */
    public function compatibleProvider(): array
    {
        return array(
            'absent'      => array( '', 'Lite alone must still migrate; no add-on is not a failure.' ),
            'exact floor' => array( '6.0.0', 'The floor itself is compatible -- the comparison is >=, not >.' ),
            'newer'       => array( '6.1.0', 'A newer add-on must not be refused.' ),
            'double digit' => array( '10.0.0', 'A string comparison would wrongly refuse this.' ),
        );
    }

    /**
     * WIRING IS NOT ASSERTED HERE, deliberately, and this note is the record of
     * why rather than an omission.
     *
     * A constant cannot be undefined, so this suite -- which runs with no add-on
     * constant defined -- can only ever exercise the PASSING branch. A test that
     * ran the migration and watched it succeed would assert nothing about the
     * guard; it would be the vacuous-lock shape this round has already found four
     * times. Worse, it would have to replay every migration step, and
     * run_migrations() commits DDL, which escapes the suite's rollback and
     * damages a database shared with every other class.
     *
     * The wiring is instead proven OUT OF PROCESS, against a real WP load with
     * the constant actually defined, in both directions:
     *
     *   old add-on present  -> mhmrentiva_db_version stays behind, notice shown
     *   no add-on present   -> mhmrentiva_db_version advances to CURRENT_VERSION
     *
     * That probe is recorded in the task report. The single call site is what
     * makes it sufficient: all three callers (Plugin.php's admin_init hook and
     * the bootstrap file's two paths) route through run_migrations().
     */
}
