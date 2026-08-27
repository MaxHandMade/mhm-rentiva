<?php
/**
 * Dependency gate for stylesheets that read `--mhm-*` tokens.
 *
 * A stylesheet that reads a token must be guaranteed the file that defines it.
 * Measured 2026-08-27: the paid plugin enqueues the free plugin's
 * user-dashboard.css with an empty dependency array (VendorLedger.php:36; the
 * same shape at VendorBookings.php:44), and only 2 of its 32 registrations name
 * the canonical handle -- both of them admin. Those pages stay coloured today
 * only because each stylesheet carries its own copied block of declarations.
 * Delete the copies without fixing the dependencies and the pages render with
 * every token unset.
 *
 * This gate answers "does this registration reach the canonical handle?".
 * bin/check-token-definitions.php answers "which files read tokens?". The two
 * are joined here: neither is sufficient alone. With only the definition gate,
 * CI goes green while a page renders uncoloured; with only this one, the copies
 * quietly grow back.
 *
 * Reading is static, not runtime, on purpose: the known defect lives in a vendor
 * shortcode that only enqueues while rendering for a logged-in vendor, so a
 * runtime probe would need that exact session to see it. The cost of reading
 * source is that a call built from variables cannot be decided -- so those are
 * reported one by one, never folded into a count. A threshold can hide a new
 * unreadable call behind an old one; a list cannot.
 *
 * Usage:
 *   php bin/check-style-token-deps.php [--report-only] [path ...]
 */

declare(strict_types=1);

require_once __DIR__ . '/check-token-definitions.php';

/**
 * Splits a call's argument list into per-argument token runs.
 *
 * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens
 *
 * @return array{args: array<int, array<int, array{0: int, 1: string, 2: int}|string>>, end: int}
 */
function mhmrentiva_split_call_args(array $tokens, int $open): array
{
    $depth = 0;
    $args  = array();
    $piece = array();
    $count = count($tokens);

    for ($i = $open; $i < $count; $i++) {
        $token = $tokens[$i];
        $text  = is_array($token) ? $token[1] : $token;

        if ('(' === $text || '[' === $text) {
            $depth++;
            if (1 === $depth) {
                continue;
            }
        } elseif (')' === $text || ']' === $text) {
            $depth--;
            if (0 === $depth) {
                $args[] = $piece;
                return array('args' => $args, 'end' => $i);
            }
        } elseif (',' === $text && 1 === $depth) {
            $args[] = $piece;
            $piece  = array();
            continue;
        }

        $piece[] = $token;
    }

    return array('args' => $args, 'end' => $count - 1);
}

/**
 * @param array<int, array{0: int, 1: string, 2: int}|string> $arg
 *
 * @return list<string>
 */
function mhmrentiva_arg_strings(array $arg): array
{
    $out = array();
    foreach ($arg as $token) {
        if (is_array($token) && T_CONSTANT_ENCAPSED_STRING === $token[0]) {
            $out[] = trim($token[1], "'\"");
        }
    }

    return $out;
}

/**
 * @param array<int, array{0: int, 1: string, 2: int}|string> $arg
 */
function mhmrentiva_arg_has_variable(array $arg): bool
{
    foreach ($arg as $token) {
        if (is_array($token) && in_array($token[0], array(T_VARIABLE, T_OBJECT_OPERATOR, T_DOUBLE_COLON), true)) {
            return true;
        }
    }

    return false;
}

/**
 * @param array<int, array{0: int, 1: string, 2: int}|string> $arg
 *
 * @return list<string>
 */
function mhmrentiva_arg_constants(array $arg): array
{
    $out = array();
    foreach ($arg as $token) {
        if (is_array($token) && T_STRING === $token[0]) {
            $out[] = $token[1];
        }
    }

    return $out;
}

/**
 * Reads dependencies out of a registry array literal.
 *
 * The free plugin declares its core stylesheets as
 * `'handle' => array('url' => …, 'deps' => array(…))` and registers them in a
 * foreach, so both the handle and the dependencies are variables at the call
 * site. That array is the one place the canonical dependency is actually
 * written; a gate that reads only call sites is blind to it and invents
 * failures for everything downstream.
 *
 * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens
 *
 * @return array<string, list<string>>
 */
function mhmrentiva_registry_deps(array $tokens): array
{
    $found = array();
    $count = count($tokens);

    $next_meaningful = static function (array $tokens, int $from, int $count): int {
        for ($j = $from; $j < $count; $j++) {
            $token = $tokens[$j];
            if (is_array($token) && in_array($token[0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true)) {
                continue;
            }
            return $j;
        }

        return $count;
    };

    for ($i = 0; $i < $count; $i++) {
        $token = $tokens[$i];
        if (! is_array($token) || T_CONSTANT_ENCAPSED_STRING !== $token[0]) {
            continue;
        }

        $arrow = $next_meaningful($tokens, $i + 1, $count);
        if ($arrow >= $count || ! is_array($tokens[$arrow]) || T_DOUBLE_ARROW !== $tokens[$arrow][0]) {
            continue;
        }

        $opener = $next_meaningful($tokens, $arrow + 1, $count);
        if ($opener >= $count) {
            continue;
        }
        $opener_text = is_array($tokens[$opener]) ? $tokens[$opener][1] : $tokens[$opener];
        if ('array' !== $opener_text && '[' !== $opener_text) {
            continue;
        }

        $block = mhmrentiva_split_call_args($tokens, $opener);
        $inner = array();
        foreach ($block['args'] as $piece) {
            foreach ($piece as $t) {
                $inner[] = $t;
            }
            $inner[] = ',';
        }

        $deps = mhmrentiva_deps_key($inner);
        if (null !== $deps) {
            $found[trim($token[1], "'\"")] = $deps;
        }
    }

    return $found;
}

/**
 * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens
 *
 * @return list<string>|null
 */
function mhmrentiva_deps_key(array $tokens): ?array
{
    $count = count($tokens);

    for ($i = 0; $i < $count; $i++) {
        $token = $tokens[$i];
        if (! is_array($token) || T_CONSTANT_ENCAPSED_STRING !== $token[0]) {
            continue;
        }
        if ('deps' !== trim($token[1], "'\"")) {
            continue;
        }

        for ($j = $i + 1; $j < $count; $j++) {
            $text = is_array($tokens[$j]) ? $tokens[$j][1] : $tokens[$j];
            if ('array' === $text || '[' === $text) {
                $block = mhmrentiva_split_call_args($tokens, $j);
                $deps  = array();
                foreach ($block['args'] as $piece) {
                    foreach (mhmrentiva_arg_strings($piece) as $dep) {
                        $deps[] = $dep;
                    }
                }

                return $deps;
            }
            if (',' === $text) {
                break;
            }
        }
    }

    return null;
}

/**
 * @param list<string>          $roots
 * @param array<string, string> $constants constant name => tree root
 *
 * @return array{
 *     records: list<array{handle: string, file: string, site: string, deps: list<string>, reaches: bool}>,
 *     failures: list<array{handle: string, file: string, site: string}>,
 *     unresolved: list<array{site: string, reason: string}>,
 *     scanned: array{php: int, calls: int, token_reading_css: int}
 * }
 */
function mhmrentiva_style_dependency_report(array $roots, array $constants, string $canonical_handle): array
{
    $reads  = array();
    $counts = array('php' => 0, 'calls' => 0, 'token_reading_css' => 0);

    foreach ($roots as $root) {
        foreach (mhmrentiva_walk(mhmrentiva_real(rtrim($root, '/')), 'css') as $file) {
            if (mhmrentiva_css_consumptions((string) file_get_contents($file))) {
                $reads[$file] = true;
                $counts['token_reading_css']++;
            }
        }
    }

    $records    = array();
    $unresolved = array();
    $deps_of    = array();

    foreach ($roots as $root) {
        foreach (mhmrentiva_walk(mhmrentiva_real(rtrim($root, '/')), 'php') as $php) {
            if (preg_match('#/(tests|bin)/#', $php)) {
                continue;
            }
            $counts['php']++;
            $tokens = token_get_all((string) file_get_contents($php));
            $total  = count($tokens);

            foreach (mhmrentiva_registry_deps($tokens) as $handle => $deps) {
                $deps_of[$handle] = array_values(array_unique(array_merge($deps_of[$handle] ?? array(), $deps)));
            }

            for ($i = 0; $i < $total; $i++) {
                $token = $tokens[$i];
                if (! is_array($token) || T_STRING !== $token[0]) {
                    continue;
                }
                if (! in_array($token[1], array('wp_enqueue_style', 'wp_register_style'), true)) {
                    continue;
                }

                $counts['calls']++;
                $site  = basename($php) . ':' . $token[2];
                $split = mhmrentiva_split_call_args($tokens, $i);
                $args  = $split['args'];
                $i     = $split['end'];

                // A one-argument call re-enqueues something already registered.
                if (count($args) < 2) {
                    continue;
                }

                $handles = mhmrentiva_arg_strings($args[0]);
                if (1 !== count($handles) || mhmrentiva_arg_has_variable($args[0])) {
                    $unresolved[] = array('site' => $site, 'reason' => 'handle is not a literal');
                    continue;
                }
                $handle = $handles[0];

                if (mhmrentiva_arg_has_variable($args[1])) {
                    $unresolved[] = array('site' => $site, 'reason' => 'source is built from a variable');
                    continue;
                }

                $deps = array();
                if (isset($args[2])) {
                    if (mhmrentiva_arg_has_variable($args[2])) {
                        $unresolved[] = array('site' => $site, 'reason' => 'dependencies are built from a variable');
                        continue;
                    }
                    $deps = mhmrentiva_arg_strings($args[2]);
                }

                $deps_of[$handle] = array_values(array_unique(array_merge($deps_of[$handle] ?? array(), $deps)));

                $file = mhmrentiva_resolve_source($args[1], $constants);
                if (null === $file) {
                    continue;
                }

                $records[] = array(
                    'handle'  => $handle,
                    'file'    => $file,
                    'site'    => $site,
                    'deps'    => $deps,
                    'reaches' => false,
                );
            }
        }
    }

    $failures = array();
    foreach ($records as $index => $record) {
        $reaches = mhmrentiva_reaches($record['handle'], $canonical_handle, $deps_of);
        $records[$index]['reaches'] = $reaches;

        if (! $reaches && isset($reads[$record['file']])) {
            $failures[] = array(
                'handle' => $record['handle'],
                'file'   => $record['file'],
                'site'   => $record['site'],
            );
        }
    }

    return array(
        'records'    => $records,
        'failures'   => $failures,
        'unresolved' => $unresolved,
        'scanned'    => $counts,
    );
}

/**
 * @param array<int, array{0: int, 1: string, 2: int}|string> $arg
 * @param array<string, string>                               $constants
 */
function mhmrentiva_resolve_source(array $arg, array $constants): ?string
{
    $paths = mhmrentiva_arg_strings($arg);
    if (array() === $paths) {
        return null;
    }
    $relative = ltrim(end($paths), '/');

    foreach (mhmrentiva_arg_constants($arg) as $name) {
        if (isset($constants[$name])) {
            $candidate = rtrim($constants[$name], '/') . '/' . $relative;
            if (is_file($candidate)) {
                return mhmrentiva_real($candidate);
            }
        }
    }

    foreach ($constants as $root) {
        $candidate = rtrim($root, '/') . '/' . $relative;
        if (is_file($candidate)) {
            return mhmrentiva_real($candidate);
        }
    }

    return null;
}

/**
 * @param array<string, list<string>> $deps_of
 */
function mhmrentiva_reaches(string $handle, string $target, array $deps_of, array &$seen = array()): bool
{
    if ($handle === $target) {
        return true;
    }
    if (isset($seen[$handle])) {
        return false;
    }
    $seen[$handle] = true;

    foreach ($deps_of[$handle] ?? array() as $dep) {
        if (mhmrentiva_reaches($dep, $target, $deps_of, $seen)) {
            return true;
        }
    }

    return false;
}

/**
 * The set of call sites this gate cannot read, as a comparable signature.
 *
 * A count cannot lock these. Eleven registrations build their handle or source
 * from variables, and each is fine on inspection -- but "eleven" stays eleven if
 * one is fixed and a different one appears, and the new one rides in behind the
 * old number. The baseline is therefore the sorted list of sites, so any
 * substitution is a difference.
 *
 * @param array{unresolved: list<array{site: string, reason: string}>} $report
 */
function mhmrentiva_unresolved_signature(array $report): string
{
    $sites = array_map(
        static fn (array $item): string => $item['site'] . ' -- ' . $item['reason'],
        $report['unresolved']
    );
    sort($sites);

    return implode("\n", $sites);
}

// ---------------------------------------------------------------------------
// CLI.
// ---------------------------------------------------------------------------

if (PHP_SAPI === 'cli' && isset($argv[0]) && realpath($argv[0]) === realpath(__FILE__)) {
    $args        = array_slice($argv, 1);
    $report_only = in_array('--report-only', $args, true);
    $paths       = array_values(array_filter($args, static fn (string $a): bool => '--' !== substr($a, 0, 2)));

    $lite = dirname(__DIR__);
    $pro  = dirname($lite) . '/mhm-rentiva-pro';

    // Default to THIS tree only. The baseline in bin/ is this tree's, and this
    // plugin's CI checks out nothing else -- silently widening the scan to a
    // sibling checkout when one happens to be on disk makes the same command
    // mean two different things, and the baseline then fails on a developer
    // machine while passing in CI. The paid plugin runs this same script from
    // its own job and passes both paths explicitly.
    if (array() === $paths) {
        $paths = array($lite);
    }

    $report = mhmrentiva_style_dependency_report(
        $paths,
        array('MHMRENTIVA_PLUGIN_URL' => $lite, 'MHMRENTIVA_PRO_URL' => $pro),
        'mhm-rentiva-css-variables'
    );

    foreach ($report['failures'] as $failure) {
        printf(
            "[FAIL] %s (%s) reads tokens but does not reach the canonical handle -- %s\n",
            $failure['handle'],
            basename($failure['file']),
            $failure['site']
        );
    }

    $signature = mhmrentiva_unresolved_signature($report);
    // Beside the FIRST scanned tree, not beside this script. The paid plugin runs
    // this same file from its own checkout and scans both trees, so its set of
    // unreadable call sites is larger than this one's; pinning the baseline to
    // the script's directory would measure that set against this plugin's list
    // and report drift that is only a difference of scope.
    $baseline = mhmrentiva_real($paths[0]) . '/bin/token-deps-unresolved.txt';
    $drift     = 0;

    if (in_array('--write-baseline', $args, true)) {
        file_put_contents($baseline, $signature . "\n");
        printf("baseline written: %d site(s)\n", $signature === '' ? 0 : substr_count($signature, "\n") + 1);
        exit(0);
    }

    if (is_file($baseline)) {
        $expected = trim((string) file_get_contents($baseline));
        if ($expected !== trim($signature)) {
            $was = $expected === '' ? array() : explode("\n", $expected);
            $now = trim($signature) === '' ? array() : explode("\n", trim($signature));
            foreach (array_diff($now, $was) as $line) {
                printf("[NEW UNREADABLE] %s\n", $line);
                $drift++;
            }
            foreach (array_diff($was, $now) as $line) {
                printf("[GONE, UPDATE BASELINE] %s\n", $line);
                $drift++;
            }
        }
    } else {
        foreach ($report['unresolved'] as $item) {
            printf("[UNRESOLVED] %s -- %s\n", $item['site'], $item['reason']);
        }
    }

    printf(
        "scanned: %d php, %d registrations, %d token-reading stylesheets\n",
        $report['scanned']['php'],
        $report['scanned']['calls'],
        $report['scanned']['token_reading_css']
    );
    printf("  failures %d · unresolved %d\n", count($report['failures']), count($report['unresolved']));

    // Unreadable sites do not fail on their own -- each of them was inspected
    // once and found sound. What fails is DRIFT: a site that was not on the
    // baseline, or one that left it without the baseline being updated.
    $problems = count($report['failures']) + $drift;

    if ($problems > 0 && ! $report_only) {
        exit(1);
    }

    if ($problems > 0) {
        printf("\nreport-only, not blocking.\n");
    }

    exit(0);
}
