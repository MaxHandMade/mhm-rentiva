<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Tools;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * No hook name of ours may contain a slash.
 *
 * A slash is not a valid PHP prefix, and Plugin Check says so:
 * WordPress.NamingConventions.PrefixAllGlobals.InvalidPrefixPassed. The tool is
 * right. `mhmrentiva/currency_symbol` cannot be a prefix in the sense the sniff
 * means, so the whole family reads as unprefixed to WordPress.org's tooling
 * however unique it looks to a human.
 *
 * This is a SHAPE gate, not a list of the names that happened to be wrong on the
 * day it was written. A new `apply_filters('mhmrentiva/thing/x')` fails here
 * immediately, which is the only version of this worth having -- the three names
 * the tool surfaced were a sample, and the sweep found roughly thirty.
 *
 * THIRD-PARTY HOOKS ARE NOT OURS TO RENAME and must keep their slashes:
 * `elementor/widgets/register`, `elementor_one/*`, `metform/after_load`,
 * `rank_math/frontend/canonical`. Those names are defined by other plugins, and
 * rewriting our call sites would simply stop us listening to them -- the same
 * external-contract rule that protects MHM_RENTIVA_MIGRATION_FALLBACK. So this
 * matches only literals carrying OUR prefix.
 *
 * @coversNothing
 */
final class SlashHookShapeTest extends TestCase
{
    /**
     * Directories that ship. Tooling under bin/ and this suite are excluded:
     * they discuss the old shape on purpose.
     *
     * @var list<string>
     */
    private const SHIPPED_DIRS = array( 'src', 'templates', 'assets' );

    /**
     * Matched in HOOK-CALL POSITION, not anywhere a string happens to look like
     * one. That precision replaces what would otherwise have to be a file
     * carve-out: PrefixMigrationMap::RUNTIME_STRING_RULES legitimately contains
     * `'mhm_rentiva/' => 'mhmrentiva/'`, a migration rule that DESCRIBES the old
     * shape. Rewriting it would collapse it into a duplicate of the rule below
     * it and corrupt the owner-approved contract. A carve-out for that file
     * would have blinded the gate to every real hook in it; anchoring on the
     * call instead keeps the whole tree in scope.
     */
    private const HOOK_CALL_PATTERN = '#(?:apply_filters|apply_filters_ref_array|do_action|do_action_ref_array'
        . '|add_filter|add_action|has_filter|has_action|remove_filter|remove_action)\s*\(\s*'
        . '[\'"](mhm_?rentiva/[a-zA-Z0-9_/]*)[\'"]#';

    public function test_no_hook_name_of_ours_contains_a_slash(): void
    {
        $offenders = array();

        foreach (self::SHIPPED_DIRS as $dir) {
            $root = dirname(__DIR__, 2) . '/' . $dir;
            if (! is_dir($root)) {
                continue;
            }

            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

            foreach ($files as $file) {
                if (! $file->isFile() || ! preg_match('/\.(php|js)$/', $file->getFilename())) {
                    continue;
                }

                $source = (string) file_get_contents($file->getPathname());

                // Our prefix, in either spelling, followed by a slash.
                if (preg_match_all(self::HOOK_CALL_PATTERN, $source, $matches)) {
                    foreach ($matches[1] as $hit) {
                        $offenders[] = str_replace(dirname(__DIR__, 2) . '/', '', $file->getPathname()) . ' -> ' . $hit;
                    }
                }
            }
        }

        sort($offenders);

        $this->assertSame(
            array(),
            $offenders,
            "A hook name of ours contains a slash, which Plugin Check reports as an invalid prefix.\n"
            . "Use underscores: 'mhmrentiva_testimonials_limit', not 'mhmrentiva/testimonials/limit'.\n"
            . implode("\n", $offenders)
        );
    }

    /**
     * ...and the gate must not be blind to the thing it is guarding.
     *
     * Without this, a regex that matched nothing at all would pass the test
     * above forever. It proves the pattern still recognises the shape.
     */
    public function test_the_detector_still_recognises_a_slash_hook(): void
    {
        $sample = "apply_filters('mhmrentiva/testimonials/limit', 6);";

        $this->assertSame(
            1,
            preg_match(self::HOOK_CALL_PATTERN, $sample),
            'The detector no longer matches the shape it exists to find.'
        );

        $this->assertSame(
            0,
            preg_match(self::HOOK_CALL_PATTERN, "add_action('elementor/widgets/register', \$cb);"),
            "A third-party hook must not be flagged -- those slashes belong to somebody else's plugin."
        );

        $this->assertSame(
            0,
            preg_match(self::HOOK_CALL_PATTERN, "'mhm_rentiva/' => 'mhmrentiva/', // slash-stili filter'lar"),
            'A RUNTIME_STRING_RULES entry describing the old shape is not a hook call. Flagging it would '
            . 'push someone to "fix" the migration map into a duplicate rule.'
        );
    }
}
