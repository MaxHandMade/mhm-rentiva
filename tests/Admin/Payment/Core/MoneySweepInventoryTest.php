<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Payment\Core;

use WP_UnitTestCase;

/**
 * The sweep's own lock.
 *
 * A sweep that fixes N surfaces and writes N tests proves nothing about
 * coverage: the missed member is never random, it is the busiest one. The
 * spec's own inventory listed sixteen members because its tool started at
 * src/; the seventeenth lives in assets/js and is the browser half of the very
 * form the sixteenth renders.
 */
final class MoneySweepInventoryTest extends WP_UnitTestCase
{
    /** @var list<string> Temp dirs to clean up. */
    private array $temp_dirs = array();

    protected function tearDown(): void
    {
        foreach ($this->temp_dirs as $dir) {
            if (! is_dir($dir)) {
                continue;
            }
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($files as $file) {
                $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }
            rmdir($dir);
        }
        $this->temp_dirs = array();
        parent::tearDown();
    }

    private function runProbe(string $extra_arg = ''): array
    {
        $root = dirname(__DIR__, 4);
        $cmd  = escapeshellcmd(PHP_BINARY) . ' ' . escapeshellarg($root . '/bin/audit-fixed-minor-scale.php')
            . ' --json' . ($extra_arg !== '' ? ' ' . $extra_arg : '');

        $out = shell_exec($cmd);
        $this->assertIsString($out, 'The probe produced no output at all.');

        $decoded = json_decode($out, true);
        $this->assertIsArray($decoded, 'The probe did not emit JSON: ' . $out);

        return $decoded;
    }

    public function test_the_probe_reaches_a_meaningful_number_of_files(): void
    {
        $result = $this->runProbe();

        // The brief's own draft asserted > 500, measured against a larger
        // notion of "the tree". The probe's actual starting set -- tracked
        // *.php/*.js minus vendor/, node_modules/, build/, tests/, bin/ -- is
        // 404 files as of this branch (git ls-files, counted by hand before
        // writing this bound). 300 stays well clear of that real number while
        // still ruling out "scanned almost nothing".
        $this->assertGreaterThan(
            300,
            $result['scanned'],
            'A probe that scanned almost nothing reports "clean" and is telling the truth about the wrong set.'
        );
    }

    public function test_the_probe_still_sees_a_planted_member(): void
    {
        $result = $this->runProbe('--self-test');

        $this->assertNotEmpty(
            $result['findings'],
            'With --self-test the probe scans a fixture that DOES contain the shape. Empty here means the probe is broken, not the tree clean.'
        );

        // assertNotEmpty alone would also pass if the probe found the RIGHT
        // shape at the WRONG place, or the wrong shape entirely. Pin the
        // finding to the fixture's actual planted line, not just its
        // existence.
        $this->assertCount(1, $result['findings'], 'The fixture plants exactly one real member; the other two lines are negative controls.');
        $finding = $result['findings'][0];
        $this->assertSame('bin/fixtures/fixed-minor-scale-fixture.txt', $finding['file']);
        $this->assertSame(4, $finding['line']);
        $this->assertSame('$refund_amount_kurus = (int) round( $refund_amount * 100 );', $finding['code']);
    }

    /**
     * Container-side lock on the exact defect this probe once had: an empty
     * starting set (here, a synthetic repo with no tracked *.php/*.js files)
     * must exit 2 and say it could not measure, never print a clean bill.
     * This runs entirely inside the container, where the Windows cmd.exe
     * quoting bug that produced the empty set never showed -- it locks the
     * CONTRACT ("empty set is never success"), not the platform-specific
     * cause.
     */
    public function test_the_probe_refuses_success_over_an_empty_starting_set(): void
    {
        $tmp = sys_get_temp_dir() . '/afms_empty_probe_' . uniqid();
        $this->temp_dirs[] = $tmp;

        mkdir($tmp . '/bin', 0777, true);
        copy(
            dirname(__DIR__, 4) . '/bin/audit-fixed-minor-scale.php',
            $tmp . '/bin/audit-fixed-minor-scale.php'
        );
        file_put_contents($tmp . '/README.txt', "no php or js tracked here\n");

        $git = static function (string $args) use ($tmp): void {
            shell_exec('git -C ' . escapeshellarg($tmp) . ' ' . $args . ' 2>&1');
        };
        $git('init -q');
        $git('-c user.email=probe@example.com -c user.name=probe add README.txt');
        $git('-c user.email=probe@example.com -c user.name=probe commit -q -m init');

        $cmd = escapeshellcmd(PHP_BINARY) . ' ' . escapeshellarg($tmp . '/bin/audit-fixed-minor-scale.php') . ' 2>&1';
        exec($cmd, $output, $exit_code);
        $combined = implode("\n", $output);

        $this->assertSame(2, $exit_code, "An empty starting set must exit 2 (CANNOT MEASURE), not 0. Output:\n" . $combined);
        $this->assertStringContainsString('CANNOT MEASURE', $combined);
        $this->assertStringNotContainsString('No fixed-100 money conversions found', $combined);
    }

    /**
     * Phase-close review, item 3: the empty-set refusal above was gated on
     * `! $self_test`, so --self-test was the one path it did not cover. A
     * missing or renamed fixture left $scanned at 0 there too, but the guard
     * never ran -- --self-test printed "Scanned 0 files ... No fixed-100
     * money conversions found" and exited 0, the exact contract violation
     * test_the_probe_refuses_success_over_an_empty_starting_set() locks for
     * the main path. This is the sibling lock for --self-test: no git
     * repository needed, since --self-test never calls git ls-files -- only
     * the fixture file has to be absent.
     */
    public function test_the_probe_refuses_success_over_a_missing_self_test_fixture(): void
    {
        $tmp = sys_get_temp_dir() . '/afms_missing_fixture_' . uniqid();
        $this->temp_dirs[] = $tmp;

        mkdir($tmp . '/bin', 0777, true);
        copy(
            dirname(__DIR__, 4) . '/bin/audit-fixed-minor-scale.php',
            $tmp . '/bin/audit-fixed-minor-scale.php'
        );
        // Deliberately no bin/fixtures/ directory at all, so the fixture the
        // script expects at bin/fixtures/fixed-minor-scale-fixture.txt is
        // unreadable -- the same shape a rename or a bad merge would produce.

        $cmd = escapeshellcmd(PHP_BINARY) . ' ' . escapeshellarg($tmp . '/bin/audit-fixed-minor-scale.php') . ' --self-test 2>&1';
        exec($cmd, $output, $exit_code);
        $combined = implode("\n", $output);

        $this->assertSame(2, $exit_code, "A missing self-test fixture must exit 2 (CANNOT MEASURE), not 0. Output:\n" . $combined);
        $this->assertStringContainsString('CANNOT MEASURE', $combined);
        $this->assertStringNotContainsString('No fixed-100 money conversions found', $combined);
    }

    public function test_the_probe_ignores_percentage_arithmetic(): void
    {
        $result = $this->runProbe('--self-test');

        foreach ($result['findings'] as $finding) {
            $this->assertStringNotContainsStringIgnoringCase(
                'percentage',
                $finding['code'],
                'Percentages share the literal 100 and none of the defect.'
            );
        }
    }

    public function test_the_probe_ignores_commented_out_code(): void
    {
        $result = $this->runProbe('--self-test');

        foreach ($result['findings'] as $finding) {
            $this->assertStringNotContainsStringIgnoringCase(
                'negative control',
                $finding['code'],
                'A comment is not a conversion, and commented-out code does not execute -- the probe must skip lines that are comments before matching the shape.'
            );
        }
    }

    public function test_no_fixed_scale_member_survives_the_sweep(): void
    {
        $result = $this->runProbe();

        $this->assertSame(
            array(),
            $result['findings'],
            "Unswept members:\n" . print_r($result['findings'], true)
        );
    }
}
