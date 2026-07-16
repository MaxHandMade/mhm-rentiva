<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Tools;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Lite must never advertise Pro.
 *
 * Owner decision (2026-07-16, carveout/faz1-exit-decisions.md "Karar A"): a
 * feature Lite does not have simply does not render -- no "available in Pro", no
 * purchase CTA, no comparison table, no upsell of any kind. This is the
 * compliance-critical rule for the WP.org submission, and no other gate covers
 * it: PHPStan and check-guarded-refs judge symbols, not copy.
 *
 * This is a source scan rather than a render test on purpose -- upsell copy is
 * exactly the kind of thing that gets reintroduced in a surface nobody thought to
 * render in a test.
 *
 * @package MHMRentiva\Tests\Tools
 */
final class NoProMentionInLiteTest extends TestCase
{

    /**
     * Copy patterns that sell Pro. Deliberately narrow: this must not fire on the
     * plugin's legitimate domain vocabulary, which is full of innocent collisions --
     * "License Plate" (a vehicle field), "a valid driving license is required" (a
     * rental term), "Similar Premium Vehicles" (a vehicle class), and the
     * Mode::isPro() ? 'Pro' : 'Lite' edition badge, which renders "Lite" in Lite and
     * only says "Pro" when the Pro add-on is actually installed.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public function upsell_pattern_provider(): array
    {
        return array(
            'available in Pro'   => array( '/available in (the )?pro\b/i', 'tells the user a feature is available in Pro' ),
            'requires Pro'       => array( '/requires? (rentiva )?pro\b/i', 'tells the user a feature requires Pro' ),
            'upgrade to Pro'     => array( '/upgrade to (rentiva )?pro\b/i', 'asks the user to upgrade' ),
            'Pro version'        => array( '/\bpro version\b/i', 'refers the user to the Pro version' ),
            'unlock Pro'         => array( '/unlock (pro|premium)\b/i', 'promises unlocking Pro features' ),
            'get a license'      => array( '/get a licen[sc]e/i', 'is a purchase CTA' ),
            'purchase page'      => array( '#wpalemi\.com/rentiva#i', 'links the product purchase page' ),
            'buy a license'      => array( '/buy a licen[sc]e/i', 'is a purchase CTA' ),
            'product url helper' => array( '/get_product_url|mhm_rentiva_product_url/', 'is the purchase-URL helper' ),
        );
    }

    /**
     * @dataProvider upsell_pattern_provider
     */
    public function test_no_upsell_copy_ships_in_lite(string $pattern, string $why): void
    {
        $offenders = array();

        foreach ($this->shipped_php_files() as $path) {
            $code = (string) file_get_contents($path);

            foreach (preg_split('/\R/', $code) ?: array() as $index => $line) {
                if (1 === preg_match($pattern, $line)) {
                    $offenders[] = sprintf('%s:%d  %s', $this->relative($path), $index + 1, trim($line));
                }
            }
        }

        $this->assertSame(
            array(),
            $offenders,
            sprintf("Copy that %s must not ship in Lite:\n%s", $why, implode("\n", $offenders))
        );
    }

    /**
     * Guards the scan itself: a glob that silently matched nothing would make every
     * assertion above pass while checking no code at all.
     */
    public function test_the_scan_actually_reads_the_plugin_source(): void
    {
        $files = iterator_to_array($this->shipped_php_files());

        $this->assertGreaterThan(250, count($files), 'The scan found implausibly few PHP files.');
    }

    /**
     * @return iterable<int, string>
     */
    private function shipped_php_files(): iterable
    {
        foreach (array( 'src', 'templates' ) as $dir) {
            $root = dirname(__DIR__, 2) . '/' . $dir;
            if (! is_dir($root)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ('php' === $file->getExtension()) {
                    yield $file->getPathname();
                }
            }
        }
    }

    private function relative(string $path): string
    {
        return str_replace(array( dirname(__DIR__, 2) . DIRECTORY_SEPARATOR, '\\' ), array( '', '/' ), $path);
    }
}
