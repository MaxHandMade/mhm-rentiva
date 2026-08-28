<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Gates;

use WP_UnitTestCase;

/**
 * readme.txt's Changelog section must fit what wordpress.org actually renders.
 *
 * The directory's readme parser truncates a section at 5,000 characters. Nothing
 * fails when it does: the page simply shows a cut-off changelog, and the release
 * that overflowed it looks complete to everyone who wrote it.
 *
 * On 2026-08-29 that section stood at 33,379 characters -- more than six times the
 * budget -- while its own opening sentence claimed it was kept "within the length
 * WordPress.org's readme parser renders". The claim had been in the file long
 * enough that nobody re-measured it, and Plugin Check's
 * readme_parser_warnings_trimmed_section_changelog warning sat unread among a
 * hundred others.
 *
 * This gate exists because the section overflows again on its own: every release
 * adds an entry and nothing removes one. Left to a warning, the next round
 * discovers the truncation after publishing, if at all. Left to a test, the round
 * that overflows it is told while it can still decide what to drop -- and the
 * decision is a decision rather than an accident.
 *
 * WHAT THIS DOES NOT COVER: it measures the Changelog section only. Description,
 * Installation and FAQ are capped the same way and are not asserted here, because
 * they do not grow release by release. If one of them starts to, give it its own
 * case rather than widening this one into a loop nobody reads.
 */
final class ReadmeChangelogLengthTest extends WP_UnitTestCase
{
    /**
     * wordpress.org's per-section cap.
     */
    private const LIMIT = 5000;

    public function test_the_changelog_section_fits_what_wordpress_org_renders(): void
    {
        $readme = (string) file_get_contents(dirname(__DIR__, 2) . '/readme.txt');

        $start = strpos($readme, '== Changelog ==');
        $this->assertNotFalse(
            $start,
            'readme.txt has no Changelog section. Either it was renamed -- in which case '
            . 're-point this gate -- or it is gone, which wordpress.org will notice before '
            . 'we do.'
        );

        $end = strpos($readme, '== Upgrade Notice ==', $start);
        $this->assertNotFalse(
            $end,
            'The Changelog section is no longer followed by Upgrade Notice, so this gate '
            . 'cannot tell where it ends. Re-point it at whatever now follows.'
        );

        $section = substr($readme, $start, $end - $start);

        // A guard that measures nothing would pass here just as quietly as one that
        // works, so prove the section was actually found before trusting its length.
        $this->assertStringContainsString(
            '= ' . MHMRENTIVA_VERSION . ' =',
            $section,
            'The slice this gate measured does not contain an entry for the version being '
            . 'shipped. Either the release forgot its changelog entry, or the slice is '
            . 'wrong and its length means nothing.'
        );

        $this->assertLessThan(
            self::LIMIT,
            strlen($section),
            sprintf(
                'readme.txt\'s Changelog section is %d characters; wordpress.org renders at '
                . 'most %d and silently truncates the rest. Drop the oldest entries rather '
                . 'than shortening the newest -- the complete history already ships as '
                . 'changelog.json and changelog-tr.json, which have no such limit.',
                strlen($section),
                self::LIMIT
            )
        );
    }
}
