<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Gates;

use PHPUnit\Framework\TestCase;

/**
 * Gate G-D has to run in two places, and this is what keeps it able to.
 *
 * WHY THIS TEST EXISTS
 *
 * G-D drives `wp plugin check`. Locally WordPress is in the Docker stack at
 * /var/www/html; on CI the workflow installs it at /tmp/wp. Everything the gate
 * touches therefore has to come off $wpPath -- and when the gate was first made
 * CI-capable, one thing did not: the throwaway directory it stages the shipped
 * vendor/ files into stayed hardcoded to /var/www/html/wp-content/plugins/...
 *
 * The local run did not catch it, and could not have: it used
 * MHM_GD_WP_PATH=/var/www/html, so the parameterised path and the hardcoded one
 * were the same string. A test whose environment is monochrome measures
 * nothing. CI caught it on the first run -- "mkdir: cannot create directory
 * '/var/www/html/wp-content': Permission denied" -- and only because the gate
 * treats a failed staging as CANNOT MEASURE rather than skipping the vendor
 * pass and reporting a smaller, greener surface.
 *
 * So this test does the one thing a same-root run cannot: it reads the script
 * and asserts no absolute WordPress path survives outside the single line that
 * documents the default. It is a lint, deliberately -- the behavioural proof is
 * CI running the gate against /tmp/wp on every pull request. This is the cheap
 * signal that fires before that, on the machine where the mistake gets made.
 */
final class PluginCheckGatePortabilityTest extends TestCase
{
    private const GATE = 'bin/check-plugin-check-parity.php';

    /**
     * Paths that only exist inside the Docker stack. Any of them appearing in
     * executable code means the gate has quietly re-acquired an assumption
     * about where it runs.
     *
     * @var list<string>
     */
    private const STACK_PATHS = [
        '/var/www/html',
        '/usr/local/bin/wp',
    ];

    private function gate_source(): string
    {
        $path = dirname(__DIR__, 2) . '/' . self::GATE;

        $this->assertFileExists($path, self::GATE . ' is missing');

        return (string) file_get_contents($path);
    }

    /**
     * @return list<array{0:int,1:string}> [line number, line]
     */
    private function lines_naming_the_stack(string $source): array
    {
        $hits = [];

        foreach (explode("\n", $source) as $i => $line) {
            foreach (self::STACK_PATHS as $needle) {
                if (str_contains($line, $needle)) {
                    $hits[] = [$i + 1, trim($line)];
                    continue 2;
                }
            }
        }

        return $hits;
    }

    public function test_the_only_absolute_stack_paths_are_the_documented_defaults(): void
    {
        $hits = $this->lines_naming_the_stack($this->gate_source());

        // Exactly two: the getenv() fallbacks for MHM_GD_WP_PATH and
        // MHM_GD_WP_BIN. Everything else must derive from them.
        $offenders = array_values(array_filter(
            $hits,
            static fn (array $hit): bool => ! str_contains($hit[1], 'getenv(')
        ));

        $this->assertSame(
            [],
            $offenders,
            "Gate G-D names a Docker-stack path outside its getenv() default:\n"
            . implode("\n", array_map(
                static fn (array $hit): string => '  line ' . $hit[0] . ': ' . $hit[1],
                $offenders
            ))
            . "\nDerive it from \$wpPath / \$wpBin instead -- CI installs WordPress somewhere else."
        );
    }

    /**
     * The positive control for the test above.
     *
     * Without it, deleting the two defaults -- or renaming the file the test
     * reads -- would leave an empty hit list and a green assertion that had
     * examined nothing.
     */
    public function test_the_two_documented_defaults_are_still_there(): void
    {
        $hits = $this->lines_naming_the_stack($this->gate_source());

        $this->assertCount(
            2,
            $hits,
            'Expected exactly the two getenv() default lines to name a stack path.'
        );

        foreach ($hits as [$line, $text]) {
            $this->assertStringContainsString(
                'getenv(',
                $text,
                'Line ' . $line . ' names a stack path but is not a getenv() default.'
            );
        }
    }

    /**
     * The staging directory is the one that actually broke, so it gets its own
     * assertion rather than relying on the sweep above to notice it.
     */
    public function test_the_vendor_staging_directory_is_derived_from_the_wordpress_root(): void
    {
        $this->assertMatchesRegularExpression(
            '/\$stage\s*=\s*\$wpPath\s*\./',
            $this->gate_source(),
            'The vendor scope must be staged under $wpPath, not a fixed path.'
        );
    }
}
