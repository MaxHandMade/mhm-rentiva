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
