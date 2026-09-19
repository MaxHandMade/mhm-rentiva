<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Gates;

use WP_UnitTestCase;

/**
 * No stylesheet may follow the operating system's colour preference on its own.
 *
 * Rentiva's pages sit inside someone else's theme. When a stylesheet darkens
 * Rentiva's surfaces under `@media (prefers-color-scheme: dark)`, it does so
 * whatever the theme is doing -- and on a light theme the result is a dark
 * island in a white page, with text that belongs to neither side.
 *
 * Measured 2026-09-19 on the dev site (Astra, light), OS set to dark:
 *   - My Account: 11 text runs under 3:1, the welcome line at 1.14:1 (light
 *     token text on the theme's white) and the card headings at 1.47:1 (the
 *     theme's dark heading colour on a Rentiva card the tokens had darkened);
 *   - the booking form, contact form, testimonials and vehicle comparison
 *     demos each painted dark containers into the light page.
 * With the OS set to light every one of those pages measured clean, and with
 * the blocks removed the dark-OS measurement matched the light one.
 *
 * The visitor's OS is not a stand-in for the theme. Today Rentiva's TEXT
 * token follows the theme (`--wp--preset--color--text`) while its SURFACES
 * are committed light (`--mhm-bg-card` resolves to the `white` preset), so a
 * dark theme is not yet fully served -- but the removed blocks never served
 * it either: they switched on the visitor's OS, and agreed with the theme
 * only when the two happened to match. Making surfaces follow the theme is
 * its own job; this gate only keeps the OS out of the decision.
 *
 * The one way in is the plugin's own Dark Mode setting, which marks the page
 * with a body class. Its "Auto" option does consult the OS -- but only on the
 * admin screens that setting governs, and only because the site owner left
 * it on Auto. A rule keyed to that class is following the setting.
 *
 * This was diagnosed once before and fixed one screen at a time --
 * addons-screen.css records the decision, booking-list.css pinned its own
 * text colours around the token block -- while the blocks themselves stayed.
 * The gate is what stops the next stylesheet from adding one.
 *
 * WHERE IT STARTS: every *.css under assets/css, src-react and build/admin
 * (src-react/shared/admin.css is enqueued as-is; src-react/admin/ compiles
 * into build/admin, which is git-tracked and SHIPPED, so the compiled copy is
 * scanned too). It flags any `prefers-color-scheme` media block, light or
 * dark, because a dark default with a light override is the same decision
 * written backwards; a media block nested inside a rule; and an `@import`
 * carrying the query. A selector list is split on top-level commas only, and
 * the opt-in class counts only where it must match: not inside :not()/:has(),
 * not inside a multi-alternative :is()/:where() unless every alternative is
 * opted in (`:is(.mhm-dark-mode, .x)` also matches `.x`), and never inside a
 * quoted string (quoted bytes are masked before the structure is read, so
 * `[data-label=")"]` cannot hide a top-level comma either).
 * Pro's tests/Unit/Css/ProNoOsDarkModeWithoutOptInTest.php
 * carries the same matcher and fixtures -- change both together.
 *
 * WHAT IT CANNOT SEE: the bundled ui-core kit and assets/vendor (other
 * projects' files); `<link media="...">` or `matchMedia()` in PHP/JS; a
 * `light-dark()` value or a `color-scheme` declaration. None of those exists
 * in Rentiva's own code today (measured 2026-09-19). If one arrives, extend
 * this gate rather than writing a second.
 */
final class NoOsDarkModeWithoutOptInTest extends WP_UnitTestCase
{
    /**
     * Body classes the plugin's own Dark Mode setting controls.
     */
    private const OPT_IN_CLASSES = array( '.mhm-dark-mode', '.mhm-auto-dark-mode' );

    /**
     * Scan roots, relative to the plugin root.
     */
    private const ROOTS = array( 'assets/css', 'src-react', 'build/admin' );

    public function test_os_colour_preference_is_only_followed_behind_the_plugin_setting(): void
    {
        $plugin     = dirname(__DIR__, 2);
        $scanned    = array();
        $violations = array();

        foreach (self::ROOTS as $root) {
            $scanned[ $root ] = 0;
            $files            = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($plugin . '/' . $root, \FilesystemIterator::SKIP_DOTS));
            foreach ($files as $file) {
                if ('css' !== $file->getExtension()) {
                    continue;
                }
                ++$scanned[ $root ];

                $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($plugin) + 1));
                foreach ($this->os_dark_selectors((string) file_get_contents($file->getPathname())) as $hit) {
                    $violations[] = sprintf('%s:%d  %s', $relative, $hit['line'], $hit['selector']);
                }
            }
        }

        // 55, 5 and 8 on 2026-09-19. The floors only have to tell "wrong path" from "clean tree".
        $this->assertFileExists($plugin . '/assets/css/core/css-variables.css', 'The token file is not where this gate looks -- re-point the scan root.');
        $this->assertFileExists($plugin . '/src-react/shared/admin.css', 'src-react/shared/admin.css moved -- re-point the scan root.');
        $this->assertGreaterThan(10, $scanned['assets/css'], 'The scan found almost no stylesheets under assets/css -- the path is wrong, not the tree clean.');
        $this->assertGreaterThan(0, $scanned['src-react'], 'The scan found no stylesheets under src-react -- the path is wrong, not the tree clean.');
        $this->assertGreaterThan(0, $scanned['build/admin'], 'The scan found no stylesheets under build/admin -- the path is wrong, not the tree clean.');
        $this->assertSame(
            array(),
            $violations,
            "These selectors follow the OS colour preference without the plugin's Dark Mode class, so they "
            . "repaint Rentiva against whatever the theme is doing. Remove the block (the theme owns the colours) "
            . 'or key it to ' . implode(' / ', self::OPT_IN_CLASSES) . ":\n" . implode("\n", $violations)
        );
    }

    /**
     * The check itself, fed known input -- so a green run means the tree is clean,
     * not that the parser stopped seeing anything. This is the gate's first
     * fixture, kept verbatim so a matcher change is measured against it.
     */
    public function test_the_scanner_flags_a_bare_block_and_passes_a_keyed_one(): void
    {
        $css = "/* @media (prefers-color-scheme: dark) { .in-a-comment { color: #fff; } } */\n"
            . ".light { color: #000; }\n"
            . "@media (prefers-color-scheme: dark) {\n"
            . "\t:root { --mhm-bg-card: #2c3338; }\n"
            . "\tbody.mhm-dark-mode .keyed, .bare-too { color: #fff; }\n"
            . "}\n"
            . "@media screen and (prefers-color-scheme:dark) {\n"
            . "\t.mhm-auto-dark-mode .keyed { color: #fff; }\n"
            . "}\n";

        $this->assertSame(
            array(
                array( 'line' => 4, 'selector' => ':root' ),
                array( 'line' => 5, 'selector' => '.bare-too' ),
            ),
            $this->os_dark_selectors($css)
        );
    }

    /**
     * The shapes that walked past the first matcher (independent review, 2026-09-19).
     */
    public function test_the_scanner_sees_the_shapes_that_bypassed_it(): void
    {
        $css = ".x { color: #eee; }\n"
            . "@media (prefers-color-scheme: light) {\n"
            . "\t.x { color: #111; }\n"
            . "}\n"
            . "@media not all and (prefers-color-scheme: light) {\n"
            . "\t.y { color: #fff; }\n"
            . "}\n"
            . ".nested {\n"
            . "\t@media (prefers-color-scheme: dark) {\n"
            . "\t\tcolor: #fff;\n"
            . "\t}\n"
            . "}\n"
            . "@import url(dark.css) (prefers-color-scheme: dark);\n"
            . "@media (prefers-color-scheme: dark) {\n"
            . "\t:not(.mhm-dark-mode) .negated { color: #fff; }\n"
            . "\tbody:not(.wp-admin).mhm-dark-mode .keyed { color: #fff; }\n"
            . "\t:not(:is(.mhm-dark-mode)) .nested-negation { color: #fff; }\n"
            . "\tbody:is(.mhm-dark-mode) .positive-is { color: #fff; }\n"
            . "}\n"
            . "@media (prefers-color-scheme: dark) {\n"
            . "\t@supports (display: grid) {\n"
            . "\t\t.mhm-dark-mode .in-supports { color: #fff; }\n"
            . "\t}\n"
            . "}\n"
            . "@media (prefers-color-scheme: dark) {\n"
            . "\t.mhm-dark-mode .a:is(.b, .c) { color: #fff; }\n"
            . "\t:not(.foo, .mhm-dark-mode) .card { color: #fff; }\n"
            . "\tbody:is(.mhm-dark-mode, .x) .card { color: #fff; }\n"
            . "\t:has(.mhm-dark-mode) .card { color: #fff; }\n"
            . "}\n"
            . "@media (prefers-color-scheme: dark) {\n"
            . "\tbody:is(.mhm-dark-mode, .mhm-auto-dark-mode) .card { color: #fff; }\n"
            . "\t.mhm-dark-mode [data-label=\")\"], .bare { color: #fff; }\n"
            . "\t[data-x=\".mhm-dark-mode\"] .card { color: #fff; }\n"
            . "}\n";

        $this->assertSame(
            array(
                array( 'line' => 3, 'selector' => '.x' ),
                array( 'line' => 6, 'selector' => '.y' ),
                array( 'line' => 9, 'selector' => '(declarations directly inside a nested media block)' ),
                array( 'line' => 13, 'selector' => '@import url(dark.css) (prefers-color-scheme: dark)' ),
                array( 'line' => 15, 'selector' => ':not(.mhm-dark-mode) .negated' ),
                array( 'line' => 17, 'selector' => ':not(:is(.mhm-dark-mode)) .nested-negation' ),
                array( 'line' => 27, 'selector' => ':not(.foo, .mhm-dark-mode) .card' ),
                array( 'line' => 28, 'selector' => 'body:is(.mhm-dark-mode, .x) .card' ),
                array( 'line' => 29, 'selector' => ':has(.mhm-dark-mode) .card' ),
                array( 'line' => 33, 'selector' => '.bare' ),
                array( 'line' => 34, 'selector' => '[data-x=".mhm-dark-mode"] .card' ),
            ),
            $this->os_dark_selectors($css)
        );
    }

    /**
     * Places inside a prefers-color-scheme media block that carry no opt-in class.
     *
     * @return list<array{line:int, selector:string}>
     */
    private function os_dark_selectors(string $css): array
    {
        // Blank out comments but keep their newlines, so reported lines stay true.
        $css = (string) preg_replace_callback(
            '#/\*.*?\*/#s',
            static fn(array $m): string => preg_replace('/[^\n]/', ' ', $m[0]),
            $css
        );

        $line_at = static fn(int $pos): int => substr_count($css, "\n", 0, $pos) + 1;
        $hits    = array();

        preg_match_all('/@import[^;]*prefers-color-scheme[^;]*/i', $css, $imports, PREG_OFFSET_CAPTURE);
        foreach ($imports[0] as $import) {
            $hits[] = array( 'line' => $line_at($import[1]), 'selector' => trim($import[0]) );
        }

        $offset = 0;
        while (preg_match('/@media[^{;]*prefers-color-scheme[^{]*\{/i', $css, $m, PREG_OFFSET_CAPTURE, $offset)) {
            $start = $m[0][1] + strlen($m[0][0]);
            $depth = 1;
            $i     = $start;
            $len   = strlen($css);
            for (; $i < $len && $depth > 0; $i++) {
                if ('{' === $css[ $i ]) {
                    ++$depth;
                } elseif ('}' === $css[ $i ]) {
                    --$depth;
                }
            }
            $body   = substr($css, $start, $i - 1 - $start);
            $offset = $i;

            // Each rule's prelude: the text before a '{' at the block's top level.
            preg_match_all('/([^{}]+)\{[^{}]*\}/', $body, $rules, PREG_OFFSET_CAPTURE);
            foreach ($rules[1] as $rule) {
                $line = $line_at($start + $rule[1] + strlen($rule[0]) - strlen(ltrim($rule[0])));
                foreach (self::split_selector_list($rule[0]) as $selector) {
                    $selector = trim((string) preg_replace('/\s+/', ' ', $selector));
                    if ('' === $selector || $this->is_opted_in($selector)) {
                        continue;
                    }
                    $hits[] = array( 'line' => $line, 'selector' => $selector );
                }
            }

            // Declarations left over once the rules are gone: CSS nesting, where the
            // media block sits inside a rule and styles it directly. Its selector is
            // the enclosing rule's, which this parser does not resolve -- so it is
            // flagged whole; unnest it (or key the outer rule and restructure).
            // Wrappers of nested at-rules (@supports, @layer) are stripped first; only a
            // `property: value` left standing is a declaration.
            $leftover = (string) preg_replace('/[^{}]+\{[^{}]*\}/', '', $body);
            $leftover = (string) preg_replace('/@[^{}]*\{|\}/', '', $leftover);
            if (str_contains($leftover, ':')) {
                $hits[] = array( 'line' => $line_at($m[0][1]), 'selector' => '(declarations directly inside a nested media block)' );
            }
        }

        usort($hits, static fn(array $a, array $b): int => $a['line'] <=> $b['line']);
        return $hits;
    }

    /**
     * A selector list split on its TOP-LEVEL commas only: the comma inside
     * `:is(.a, .b)` or `:not(.a, .b)` separates arguments, not selectors.
     *
     * @return list<string>
     */
    private static function split_selector_list(string $list): array
    {
        $parts  = array();
        $depth  = 0;
        $start  = 0;
        $masked = self::mask_strings($list);
        $len    = strlen($masked);
        for ($i = 0; $i < $len; $i++) {
            $c = $masked[ $i ];
            if ('(' === $c || '[' === $c) {
                ++$depth;
            } elseif (')' === $c || ']' === $c) {
                --$depth;
            } elseif (',' === $c && 0 === $depth) {
                $parts[] = substr($list, $start, $i - $start);
                $start   = $i + 1;
            }
        }
        $parts[] = substr($list, $start);
        return $parts;
    }

    /**
     * The string with every quoted string's bytes (quotes included) replaced by
     * `_`, so a `)`, `]`, `,` or class name inside `[attr=")"]` can neither move
     * the structure nor pass for an opt-in. Length is kept, so offsets found on
     * the masked copy index the original.
     */
    private static function mask_strings(string $s): string
    {
        return (string) preg_replace_callback(
            '/"(?:[^"\\\\]|\\\\.)*"|\'(?:[^\'\\\\]|\\\\.)*\'/s',
            static fn(array $m): string => str_repeat('_', strlen($m[0])),
            $s
        );
    }

    /**
     * The part of a (string-masked) selector that must match for the rule to apply.
     *
     * `:not(...)` and `:has(...)` arguments never guarantee a class on the
     * matched element's ancestry, so they are dropped. `:is(...)`/`:where(...)`
     * must match one alternative: kept when every alternative is itself opted in
     * (`:is(.mhm-dark-mode, .mhm-auto-dark-mode)`), dropped otherwise --
     * `:is(.mhm-dark-mode, .x)` also matches `.x` with dark mode off.
     */
    private static function positive_part(string $selector): string
    {
        $out = '';
        $len = strlen($selector);
        for ($i = 0; $i < $len; $i++) {
            if (':' !== $selector[ $i ] || ! preg_match('/\G:(not|has|is|where)\(/i', $selector, $m, 0, $i)) {
                $out .= $selector[ $i ];
                continue;
            }
            $open  = $i + strlen($m[0]) - 1;
            $depth = 0;
            for ($j = $open; $j < $len; $j++) {
                if ('(' === $selector[ $j ]) {
                    ++$depth;
                } elseif (')' === $selector[ $j ] && 0 === --$depth) {
                    break;
                }
            }
            $argument = substr($selector, $open + 1, $j - $open - 1);
            if (in_array(strtolower($m[1]), array( 'is', 'where' ), true)) {
                $alternatives = self::split_selector_list($argument);
                if (1 === count($alternatives) || array() === array_filter($alternatives, static fn(string $a): bool => ! self::is_opted_in($a))) {
                    $out .= ' ' . self::positive_part($alternatives[0]) . ' ';
                }
            }
            $i = $j;
        }
        return $out;
    }

    private static function is_opted_in(string $selector): bool
    {
        // Only a class in a position that must match counts as an opt-in; quoted
        // strings are masked first, so a class name inside one never counts.
        $positive = self::positive_part(self::mask_strings($selector));
        foreach (self::OPT_IN_CLASSES as $class) {
            if (preg_match('/' . preg_quote($class, '/') . '(?![\w-])/', $positive)) {
                return true;
            }
        }
        return false;
    }
}
