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
     * The same rule, applied to the docs.
     *
     * README.md shipped a full crippleware-era comparison table ("Maximum 5
     * Vehicles", "For unlimited access, please check the Pro version") long after
     * the limits themselves were deleted, because `.distignore` excludes
     * README-tr.md but NOT README.md — so it went out in the ZIP advertising
     * restrictions the plugin does not have. Nothing caught it: the PHP scan above
     * never looked at Markdown.
     *
     * readme.txt is included too. It is the WordPress.org listing page, so a false
     * claim there is the most public one the project can make.
     *
     * @dataProvider upsell_pattern_provider
     */
    public function test_no_upsell_copy_ships_in_the_docs(string $pattern, string $why): void
    {
        $offenders = array();

        foreach ($this->doc_files() as $path) {
            foreach (preg_split('/\R/', (string) file_get_contents($path)) ?: array() as $index => $line) {
                if ($this->is_wporg_metadata_header($line)) {
                    continue;
                }

                if (1 === preg_match($pattern, $line)) {
                    $offenders[] = sprintf('%s:%d  %s', basename($path), $index + 1, trim($line));
                }
            }
        }

        $this->assertSame(
            array(),
            $offenders,
            sprintf("Copy that %s must not ship in Lite's docs:\n%s", $why, implode("\n", $offenders))
        );
    }

    /**
     * The docs must not describe caps the plugin does not enforce.
     *
     * Distinct from the upsell patterns: "Maximum 5 Vehicles" names no edition and
     * makes no CTA, so every pattern above misses it — yet it is the single most
     * misleading thing the README said, because readme.txt promises the opposite
     * ("There are no caps of any kind").
     *
     * @dataProvider crippleware_claim_provider
     */
    public function test_the_docs_do_not_advertise_limits_that_do_not_exist(string $pattern, string $why): void
    {
        $offenders = array();

        foreach ($this->doc_files() as $path) {
            foreach (preg_split('/\R/', (string) file_get_contents($path)) ?: array() as $index => $line) {
                if (1 === preg_match($pattern, $line)) {
                    $offenders[] = sprintf('%s:%d  %s', basename($path), $index + 1, trim($line));
                }
            }
        }

        $this->assertSame(
            array(),
            $offenders,
            sprintf("The docs claim %s, which this build does not enforce:\n%s", $why, implode("\n", $offenders))
        );
    }

    /**
     * Limit/comparison copy, in both English and Turkish. README-tr.md does not ship
     * in the ZIP, but the GitHub repo is public, and it carried the same table.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public function crippleware_claim_provider(): array
    {
        return array(
            'Lite vs Pro table'  => array( '/lite\s*(vs\.?|ve|and|&)\s*pro/i', 'an edition comparison table' ),
            'maximum N things'   => array( '/\bmaximum\s+\d+\s+\w/i', 'a hard cap on some quantity' ),
            'N vehicles max'     => array( '/\b\d+\s+vehicles?\s*\|/i', 'a vehicle cap in a comparison table' ),
            'unlimited upsell'   => array( '/unlimited\s+(in|with)\s+pro/i', 'that a cap lifts in Pro' ),
            'TR maksimum'        => array( '/\bmaksimum\s+\d+/i', 'a hard cap (TR)' ),
            'TR en fazla'        => array( '/\ben fazla\s+\d+/i', 'a hard cap (TR)' ),
            'TR sinirsiz upsell' => array( '/pro.{0,10}(da|de)\s+sınırsız/iu', 'that a cap lifts in Pro (TR)' ),
            'TR lisans upsell'   => array( '/pro\s+sürüm/iu', 'the Pro edition (TR)' ),
        );
    }

    /**
     * WordPress.org requires these headers, and `Plugin URI` legitimately points at
     * the plugin's own home page. That URL matches the "purchase page" pattern, but
     * a required metadata header is not an upsell CTA — the rule bans *selling* Pro
     * inside the product, not declaring where the plugin lives.
     */
    private function is_wporg_metadata_header(string $line): bool
    {
        return 1 === preg_match('/^\s*(Plugin URI|Author URI|License URI|Donate link)\s*:/i', $line);
    }

    /**
     * @return iterable<int, string>
     */
    private function doc_files(): iterable
    {
        foreach (array( 'README.md', 'README-tr.md', 'readme.txt' ) as $name) {
            $path = dirname(__DIR__, 2) . '/' . $name;
            if (is_file($path)) {
                yield $path;
            }
        }
    }

    /**
     * Guards the doc scan: if a rename ever made doc_files() yield nothing, every
     * doc assertion above would pass while reading no documentation at all.
     */
    public function test_the_scan_actually_reads_the_docs(): void
    {
        $files = iterator_to_array($this->doc_files());

        $this->assertCount(3, $files, 'Expected README.md, README-tr.md and readme.txt to be scanned.');

        foreach ($files as $path) {
            $this->assertNotSame('', trim((string) file_get_contents($path)), basename($path) . ' is empty.');
        }
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
