<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Tools;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Protected meta keys must never be registered with a public auth callback.
 *
 * WordPress treats a meta key beginning with `_` as protected: it is hidden from
 * the custom-fields UI and, when exposed through `show_in_rest`, its read and
 * write permission is decided by `auth_callback`. Setting that to
 * `__return_true` says "anyone may edit this", so any authenticated user --
 * a Subscriber -- can write it through `/wp-json/wp/v2/<type>/<id>`. On this
 * plugin's meta that means booking prices, vehicle status and payment fields.
 *
 * `DatabaseInitialization` registered sixty-nine such keys exactly that way.
 * It was never reachable -- its only entry point, `on_activation()`, was not
 * wired to `register_activation_hook()`, so the calls never ran -- and the file
 * was deleted rather than kept, because a trap that arms itself the moment
 * someone wires the obvious-looking method is not made safe by being dormant.
 *
 * This guard exists so the shape cannot come back unnoticed. It scans source,
 * not runtime: a class that registers nothing today still ships the code.
 *
 * @package MHMRentiva\Tests\Tools
 */
final class NoPublicAuthCallbackOnProtectedMetaTest extends TestCase
{
    public function test_no_shipped_file_grants_public_auth_on_meta(): void
    {
        $offenders = array();

        foreach ($this->shipped_php_files() as $path) {
            foreach (preg_split('/\R/', (string) file_get_contents($path)) ?: array() as $index => $line) {
                if (1 === preg_match("/'auth_callback'\s*=>\s*'__return_true'/", $line)) {
                    $offenders[] = sprintf('%s:%d', $this->relative($path), $index + 1);
                }
            }
        }

        $this->assertSame(
            array(),
            $offenders,
            "A protected meta key registered with `auth_callback => '__return_true'` is writable\n"
                . "by any logged-in user over the REST API. Give it a capability check instead:\n"
                . implode("\n", $offenders)
        );
    }

    /**
     * Guards the scan itself: a glob that matched nothing would make the
     * assertion above pass while reading no code at all.
     */
    public function test_the_scan_actually_reads_the_plugin_source(): void
    {
        $this->assertGreaterThan(
            250,
            count(iterator_to_array($this->shipped_php_files())),
            'The scan found implausibly few PHP files.'
        );
    }

    /**
     * @return iterable<int, string>
     */
    private function shipped_php_files(): iterable
    {
        $root = dirname(__DIR__, 2);

        foreach (array( '/src', '/templates' ) as $dir) {
            if (! is_dir($root . $dir)) {
                continue;
            }

            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . $dir));
            foreach ($it as $file) {
                if ('php' === $file->getExtension()) {
                    yield $file->getPathname();
                }
            }
        }
    }

    private function relative(string $path): string
    {
        return str_replace(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR, '', $path);
    }
}
