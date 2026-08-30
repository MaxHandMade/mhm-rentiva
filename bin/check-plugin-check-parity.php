<?php
/**
 * Gate G-D: WP.org's Plugin Check -- the REAL tool, on the REAL shipped surface.
 *
 * WHAT CHANGED AND WHY (2026-08-02)
 * ---------------------------------
 * This gate used to run phpcs against bin/plugin-check.ruleset.xml, a vendored
 * copy of Plugin Check's ruleset, over the hardcoded path list
 * "src/ templates/ mhm-rentiva.php uninstall.php". Both halves were wrong:
 *
 *  1. WRONG TOOL. The vendored copy was missing sniffs the real ruleset runs --
 *     most importantly WordPress.Security.EscapeOutput -- and had the four
 *     PluginCheck.* custom sniffs stripped (they only resolve when the
 *     plugin-check plugin is installed). It is also phpcs-only, so it could not
 *     see ANY of the ~18 non-PHPCS checks in the plugin_repo category
 *     (readme/stable-tag, hidden files, trademarks, prefixing, file types,
 *     direct file access, ...). It reported "1 error / 56 warnings"; the real
 *     tool on the same tree reported four errors, two of which
 *     (EscapeOutput.OutputNotEscaped in DatabaseCleanupPage) the vendored copy
 *     was structurally blind to -- in the exact sniff family the fifth WP.org
 *     rejection punished.
 *
 *  2. WRONG SCOPE. The hardcoded path list omitted vendor/mhm/ui-core/,
 *     languages/*.l10n.php, assets/blocks/unified-search/index.php and
 *     build/*.asset.php -- all of which ship. Restating .distignore in a second
 *     place is what let that drift happen, so the scope is no longer restated:
 *     it is READ from the ZIP builder (`bin/build-release.py --list-shipped`,
 *     which uses the very same is_excluded() walk that stages the release ZIP).
 *     If .distignore changes, the ZIP and this gate change together or not at
 *     all.
 *
 * WHAT IT DOES NOW
 * ----------------
 *  A. Asks bin/build-release.py for the shipped file list (the ZIP's contents).
 *  B. Turns the complement of that list into --exclude-directories /
 *     --exclude-files arguments, then self-checks that no exclusion token can
 *     collide with a shipped path (Plugin Check matches directories by the
 *     substring "/<token>/" and files by the suffix "/<token>", so an
 *     unlucky token could silently un-scan shipped code -- the gate aborts
 *     rather than under-report).
 *  C. Runs `wp plugin check <slug> --categories=plugin_repo` inside the Docker
 *     WP stack, at memory_limit=1024M. This matters: at PHP's default 128M the
 *     run OOMs mid-way and prints a fatal, which reads like "0 findings" to
 *     anything that only looks at the exit code or the tail of the output.
 *  D. Runs a supplementary pass over the shipped part of vendor/. Plugin Check
 *     cannot ever see it: a star-slash-vendor-slash-star <exclude-pattern> sits
 *     inside WP.org's own phpcs-rulesets/plugin-review.xml AND "vendor" is in
 *     Plugin Check's hardcoded ignore-directory default, which the CLI can only
 *     add to, never subtract from. vendor/mhm/ui-core IS a runtime dependency
 *     that ships (see .distignore's "!/vendor/mhm/"), so the gate restages those
 *     files as a throwaway plugin at a path with no "vendor" segment and runs
 *     THE SAME `wp plugin check` over them, discarding everything that is not
 *     one of the staged files. It does not hand-pick a ruleset: the plugin_repo
 *     category runs 13 separate phpcs checks under three different standards
 *     ('plugin-review.xml', 'WordPress', 'PluginCheck'), and EscapeOutput
 *     belongs to Late_Escaping_Check under 'WordPress' -- not to
 *     plugin-review.xml, which emits no EscapeOutput error at all.
 *  E. Prints one machine-readable summary line, a per-code breakdown, and every
 *     finding as file:line/code/severity.
 *
 * ACCEPTANCE BAR: 0 errors AND every warning accounted for -- one to one -- by
 * the documented acceptance file bin/gd-accepted-warnings.json (owner decision
 * 2026-08-08: the residual 25 SchemaChange DDL + 22 read-only NonceVerification
 * GET reads were each worked and justified; zero suppressions). The file maps
 * "file|code" to an accepted count. A warning above its accepted count fails
 * the gate (new finding); an accepted count above the actual count also fails
 * (stale baseline -- the file may only shrink, regenerate it in the same
 * change). Errors are NEVER acceptable. Regenerate after a justified change
 * with: php bin/check-plugin-check-parity.php --write-accepted-warnings
 *
 * EXIT CODES
 *   0  clean
 *   1  findings present (errors and/or warnings)
 *   2  the gate could not MEASURE -- Docker, the container, the plugin, the
 *      shipped-file list or plugin-check itself was unavailable. Never
 *      conflated with 0: a gate that cannot run must be loud, not green.
 *
 * WHERE IT RUNS
 *   Two environments, one gate. Locally it drives the Docker WP stack
 *   (container rentiva-dev-wpcli-1 by default). On CI the workflow installs
 *   WordPress and plugin-check onto the runner itself and sets
 *   MHM_GD_CONTAINER='' , which drops the `docker exec` prefix and runs the
 *   very same commands directly.
 *
 *   Overrides: MHM_GD_CONTAINER ('' or 'local' = no Docker) /
 *   MHM_GD_WP_PATH (WordPress root) / MHM_GD_WP_BIN (wp-cli binary) /
 *   MHM_GD_PLUGIN_DIR / MHM_GD_SLUG.
 *
 *   Until 2026-08-30 this said the gate could not run on GitHub Actions, and
 *   that was true: every command was prefixed with `docker exec` and three
 *   paths were hardcoded to the stack's layout, so CI could only ever reach
 *   exit 2. The step was continue-on-error, which meant a gate nothing
 *   enforced -- and it had been failing since 2026-08-14 with four warnings
 *   nobody saw.
 */

$root = dirname(__DIR__);
chdir($root);

const EXIT_CLEAN = 0;
const EXIT_FINDINGS = 1;
const EXIT_CANNOT_MEASURE = 2;

// An UNSET variable means "the usual Docker stack"; an explicitly EMPTY one
// means "WordPress is right here". getenv() returns false when unset and ''
// when set-but-empty, and those two must not collapse -- ?: would turn the
// deliberate '' back into the default and silently re-enable Docker on CI.
$containerEnv = getenv('MHM_GD_CONTAINER');
$container    = false === $containerEnv ? 'rentiva-dev-wpcli-1' : $containerEnv;
if ('local' === $container) {
    $container = '';
}

$slug      = getenv('MHM_GD_SLUG') ?: 'mhm-rentiva';
$wpPath    = rtrim(getenv('MHM_GD_WP_PATH') ?: '/var/www/html', '/');
$wpBin     = getenv('MHM_GD_WP_BIN') ?: '/usr/local/bin/wp';
$pluginDir = getenv('MHM_GD_PLUGIN_DIR') ?: $wpPath . '/wp-content/plugins/' . $slug;
$pcDir     = $wpPath . '/wp-content/plugins/plugin-check';

/**
 * Prefix a command so it runs where WordPress lives.
 *
 * With a container name, through `docker exec`; without one, directly. The
 * argument vector is otherwise identical, which is the point: CI and a
 * developer's machine must run the same command, not two that resemble each
 * other.
 *
 * @param string[] $argv
 * @return string[]
 */
function in_wp(array $argv): array
{
    global $container;

    return '' === $container ? $argv : array_merge(['docker', 'exec', $container], $argv);
}

/**
 * Run a command without going through a shell.
 *
 * proc_open() with an ARRAY command line bypasses the shell on both POSIX and
 * Windows, so nothing here depends on quoting rules. The previous version of
 * this gate hardcoded `2>/dev/null` into a shell string, which is a syntax
 * error under Windows cmd -- the gate simply could not run on the machine it
 * was written on.
 *
 * @param string[] $argv Command and arguments.
 * @return array{0:int,1:string,2:string} [exit code, stdout, stderr]
 */
function run_cmd(array $argv): array
{
    $spec = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    // Silenced deliberately: callers probe for optional binaries (python3 vs
    // python vs py), and a missing one is an expected answer returned through
    // the exit code, not a condition worth printing.
    $proc = @proc_open($argv, $spec, $pipes);
    if (!is_resource($proc)) {
        return [-1, '', 'proc_open failed for: ' . implode(' ', $argv)];
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return [proc_close($proc), $stdout, $stderr];
}

function cannot_measure(string $why, string $how = ''): void
{
    fwrite(STDERR, "G-D CANNOT MEASURE: $why\n");
    if ($how !== '') {
        fwrite(STDERR, "  $how\n");
    }
    exit(EXIT_CANNOT_MEASURE);
}

// ---------------------------------------------------------------------------
// A. Shipped surface, read from the ZIP builder (never restated here).
// ---------------------------------------------------------------------------
$shipped = null;
foreach (['python3', 'python', 'py'] as $py) {
    [$rc, $out] = run_cmd([$py, 'bin/build-release.py', '--list-shipped']);
    if ($rc === 0 && trim($out) !== '') {
        $shipped = preg_split('/\R/', trim($out));
        break;
    }
}
if ($shipped === null) {
    cannot_measure(
        'could not obtain the shipped file list',
        'needs a working `python bin/build-release.py --list-shipped` (Python 3).'
    );
}
$shippedSet = array_fill_keys($shipped, true);

// Every ancestor directory of a shipped file is itself shipped.
$shippedDirs = ['' => true];
foreach ($shipped as $rel) {
    $parts = explode('/', $rel);
    array_pop($parts);
    for ($i = 1; $i <= count($parts); $i++) {
        $shippedDirs[implode('/', array_slice($parts, 0, $i))] = true;
    }
}

// ---------------------------------------------------------------------------
// B. Complement of the shipped surface -> Plugin Check exclusion arguments.
//    Walk only as deep as needed: a directory holding nothing shipped becomes
//    a single exclusion token and is not descended into.
// ---------------------------------------------------------------------------
$excludeDirs  = [];
$excludeFiles = [];

$walk = function (string $relDir) use (&$walk, $root, $shippedSet, $shippedDirs, &$excludeDirs, &$excludeFiles): void {
    $abs = $relDir === '' ? $root : $root . '/' . $relDir;
    $entries = @scandir($abs);
    if ($entries === false) {
        return;
    }
    foreach ($entries as $e) {
        if ($e === '.' || $e === '..') {
            continue;
        }
        $rel = $relDir === '' ? $e : $relDir . '/' . $e;
        if (is_dir($abs . '/' . $e)) {
            if (isset($shippedDirs[$rel])) {
                $walk($rel);
            } else {
                $excludeDirs[] = $rel;
            }
        } elseif (!isset($shippedSet[$rel])) {
            $excludeFiles[] = $rel;
        }
    }
};
$walk('');
sort($excludeDirs);
sort($excludeFiles);

// Self-check. Plugin Check matches an excluded directory by the substring
// "/<token>/" and an excluded file by the suffix "/<token>", both against the
// ABSOLUTE path -- so a token can over-match, either inside the plugin or
// inside the WordPress-root prefix. Any collision would silently shrink the
// scanned surface, which is the precise failure this rewrite exists to end.
$collisions = [];
foreach ($shipped as $rel) {
    $absPath = $pluginDir . '/' . $rel;
    foreach ($excludeDirs as $d) {
        if (strpos($absPath, '/' . $d . '/') !== false) {
            $collisions[] = "shipped '$rel' would be skipped by --exclude-directories token '$d'";
        }
    }
    foreach ($excludeFiles as $f) {
        if (substr($absPath, -strlen('/' . $f)) === '/' . $f) {
            $collisions[] = "shipped '$rel' would be skipped by --exclude-files token '$f'";
        }
    }
}
if ($collisions) {
    fwrite(STDERR, "G-D: exclusion tokens collide with shipped paths:\n");
    foreach (array_slice(array_unique($collisions), 0, 20) as $c) {
        fwrite(STDERR, "  $c\n");
    }
    cannot_measure('scope derivation is unsafe (see collisions above)');
}

// ---------------------------------------------------------------------------
// C. The real tool.
// ---------------------------------------------------------------------------
[$rc] = run_cmd(in_wp(['test', '-d', $pluginDir]));
if ($rc !== 0) {
    cannot_measure(
        '' === $container
            ? "'$pluginDir' does not exist"
            : "container '$container' unreachable, or '$pluginDir' missing inside it",
        'start the Docker WP stack, or set MHM_GD_CONTAINER / MHM_GD_WP_PATH / MHM_GD_PLUGIN_DIR.'
    );
}
[$rc] = run_cmd(in_wp(['test', '-f', $pcDir . '/phpcs-rulesets/plugin-review.xml']));
if ($rc !== 0) {
    cannot_measure(
        "the plugin-check plugin is not installed at $pcDir",
        'install it: wp plugin install plugin-check --activate'
    );
}

$pcArgs = in_wp([
    'php', '-d', 'memory_limit=1024M', $wpBin,
    'plugin', 'check', $slug,
    '--categories=plugin_repo',
    '--allow-root',
    '--format=json',
    '--path=' . $wpPath,
]);
if ($excludeDirs) {
    $pcArgs[] = '--exclude-directories=' . implode(',', $excludeDirs);
}
if ($excludeFiles) {
    $pcArgs[] = '--exclude-files=' . implode(',', $excludeFiles);
}

[$rc, $stdout, $stderr] = run_cmd($pcArgs);
assert_real_result($rc, $stdout, $stderr);

/**
 * Refuse to read a crashed run as a result.
 *
 * This is the whole reason the gate exists in its current form. The first
 * measurement of this round was taken from a `wp plugin check` invocation that
 * silently OOM'd at PHP's default 128M, printed a fatal, and produced short
 * output that read as "0 findings" to anything glancing at the tail. A gate
 * whose failure mode looks like a pass is worse than no gate.
 */
function assert_real_result(int $rc, string $stdout, string $stderr): void
{
    foreach (['Allowed memory size', 'Fatal error', 'PHP Fatal', 'Out of memory'] as $marker) {
        if (stripos($stdout, $marker) !== false || stripos($stderr, $marker) !== false) {
            cannot_measure(
                "the Plugin Check run hit a PHP fatal ('$marker') -- its output is NOT a result",
                'raise memory_limit in this script; the run needs well above PHP\'s 128M default.'
            );
        }
    }
    if (trim($stdout) === '' && $rc !== 0) {
        cannot_measure("the Plugin Check run failed (rc=$rc): " . trim($stderr));
    }
}

/**
 * Parse `wp plugin check --format=json` output.
 *
 * The command does NOT emit one JSON document: it emits a "FILE: <path>" line
 * followed by a JSON array of that file's findings, repeated per file.
 *
 * @return array<int, array{file:string,line:int,code:string,severity:string}>
 */
function parse_plugin_check_json(string $stdout): array
{
    $findings = [];
    $file = null;
    foreach (preg_split('/\R/', $stdout) as $line) {
        if (strncmp($line, 'FILE: ', 6) === 0) {
            $file = trim(substr($line, 6));
            continue;
        }
        $line = trim($line);
        if ($line === '' || $line[0] !== '[') {
            continue;
        }
        $rows = json_decode($line, true);
        if (!is_array($rows)) {
            continue;
        }
        foreach ($rows as $r) {
            $findings[] = [
                'file'     => (string) ($file ?? '?'),
                'line'     => (int) ($r['line'] ?? 0),
                'code'     => (string) ($r['code'] ?? '?'),
                'severity' => strtolower((string) ($r['type'] ?? 'error')),
            ];
        }
    }
    return $findings;
}

$findings = parse_plugin_check_json($stdout);

// ---------------------------------------------------------------------------
// D. Supplementary pass over the shipped part of vendor/, which Plugin Check
//    structurally refuses to look at (see the header, point D).
// ---------------------------------------------------------------------------
$vendorShipped = array_values(array_filter($shipped, static fn($p) => strncmp($p, 'vendor/', 7) === 0));
$vendorPhp = array_values(array_filter($vendorShipped, static fn($p) => substr($p, -4) === '.php'));
$vendorScanned = 0;

if ($vendorPhp) {
    // Stage the shipped vendor PHP as a THROWAWAY PLUGIN, then run the same
    // `wp plugin check` over it. Running phpcs against plugin-review.xml by
    // hand -- the obvious shortcut, and this gate's first draft -- covers only
    // ONE of the 13 phpcs-based checks in the plugin_repo category. The others
    // use the 'WordPress' and 'PluginCheck' standards with their own sniff
    // selections, and between them they own EscapeOutput (Late_Escaping_Check),
    // DirectDatabaseQuery (Direct_DB_Queries_Check), NonceVerification and
    // Prefixing. Verified: `echo $_GET['x']` under plugin-review.xml alone
    // produces the three ValidatedSanitizedInput warnings and NO EscapeOutput
    // error. A supplementary pass blind to the exact family that got this
    // plugin rejected would have been worse than none.
    $vendorSlug = $slug . '-gd-vendorscope';
    $stage      = $wpPath . '/wp-content/plugins/' . $vendorSlug;

    $script = 'set -e; rm -rf ' . $stage . '; mkdir -p ' . $stage . ';';
    foreach ($vendorPhp as $p) {
        // Drop the leading "vendor/": that literal path segment is what both
        // ignore layers key on, and it is the only reason this code is
        // invisible to the tool in its real location.
        $destRel = substr($p, 7);
        $script .= ' mkdir -p "' . $stage . '/' . dirname($destRel) . '";';
        $script .= ' cp "' . $pluginDir . '/' . $p . '" "' . $stage . '/' . $destRel . '";';
    }
    // Plugin Check needs a plugin header to accept the directory at all. This
    // synthetic file's own findings are discarded below -- only the staged
    // vendor paths are kept, so nothing this gate reports comes from it.
    $script .= " printf '<?php\\n/**\\n * Plugin Name: GD vendor scope\\n */\\n' > "
             . $stage . '/' . $vendorSlug . '.php;';

    [$vRc, $vOut, $vErr] = run_cmd(in_wp(['bash', '-c', $script]));
    if ($vRc !== 0) {
        cannot_measure('could not stage the vendor scope: ' . trim($vErr . ' ' . $vOut));
    }

    // Plugin Check only emits a FILE: block for files that HAVE findings, so
    // "nothing came back" and "nothing was scanned" look identical in its
    // output. Count what actually landed on disk instead of assuming.
    [, $vCount] = run_cmd(in_wp(['bash', '-c', 'find ' . $stage . ' -name "*.php" | wc -l']));
    // -1 for the synthetic plugin-header file staged alongside the sources.
    $vendorScanned = max(0, (int) trim($vCount) - 1);
    if ($vendorScanned !== count($vendorPhp)) {
        run_cmd(in_wp(['rm', '-rf', $stage]));
        cannot_measure(
            'vendor scope staged ' . $vendorScanned . ' of ' . count($vendorPhp) . ' shipped PHP files'
        );
    }

    [$vRc, $vOut, $vErr] = run_cmd(in_wp([
        'php', '-d', 'memory_limit=1024M', $wpBin,
        'plugin', 'check', $vendorSlug,
        '--categories=plugin_repo',
        '--allow-root',
        '--format=json',
        '--path=' . $wpPath,
    ]));
    // Tear down before acting on the result, so a findings-driven exit cannot
    // leave a stray plugin directory behind.
    run_cmd(in_wp(['rm', '-rf', $stage]));
    assert_real_result($vRc, $vOut, $vErr);

    $stagedRel = [];
    foreach ($vendorPhp as $p) {
        $stagedRel[substr($p, 7)] = true;
    }
    foreach (parse_plugin_check_json($vOut) as $f) {
        if (!isset($stagedRel[$f['file']])) {
            // Findings on the synthetic header file, or repo-level checks
            // (readme, stable tag, ...) that belong to the real plugin's run,
            // not to this scope-recovery pass.
            continue;
        }
        $f['file'] = 'vendor/' . $f['file'];
        $findings[] = $f;
    }
}

// ---------------------------------------------------------------------------
// E. Report.
// ---------------------------------------------------------------------------
$errors   = array_values(array_filter($findings, static fn($f) => $f['severity'] === 'error'));
$warnings = array_values(array_filter($findings, static fn($f) => $f['severity'] === 'warning'));

// ---------------------------------------------------------------------------
// E0. Documented warning acceptance (owner decision 2026-08-08).
//     Keyed by "file|code" with a count -- line numbers drift, counts do not.
//     The comparison is exact in both directions: an actual count above the
//     accepted one is a NEW finding, an accepted count above the actual one is
//     a STALE baseline entry. Both fail, so the file can only ever shrink in
//     step with the code. Errors never consult this file.
// ---------------------------------------------------------------------------
$acceptedFile = __DIR__ . '/gd-accepted-warnings.json';

$actualCounts = [];
foreach ($warnings as $w) {
    $key = $w['file'] . '|' . $w['code'];
    $actualCounts[$key] = ($actualCounts[$key] ?? 0) + 1;
}

if (in_array('--write-accepted-warnings', $argv ?? [], true)) {
    $existing = is_file($acceptedFile) ? json_decode((string) file_get_contents($acceptedFile), true) : null;
    ksort($actualCounts);
    $doc = [
        '_purpose' => 'Gate G-D documented warning acceptance. Every entry was individually audited; see reasons. Regenerate ONLY alongside the code change that justifies it: php bin/check-plugin-check-parity.php --write-accepted-warnings',
        'reasons'  => is_array($existing['reasons'] ?? null) ? $existing['reasons'] : [
            'WordPress.DB.DirectDatabaseQuery.SchemaChange'    => 'Mandatory DDL: activation, migration, uninstall and index-retirement code must CREATE/ALTER/DROP the plugin\'s own tables; WordPress ships no API for that.',
            'WordPress.Security.NonceVerification.Recommended' => 'Read-only $_GET reads (list filters, pagination, tab state) that mutate nothing; each occurrence audited in the 2026-08 T8/T9 rounds, zero suppressions added.',
        ],
        'accepted' => $actualCounts,
    ];
    file_put_contents(
        $acceptedFile,
        json_encode($doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
    );
    echo 'G-D: wrote ' . count($actualCounts) . " accepted-warning entries to bin/gd-accepted-warnings.json\n";
}

$acceptedCounts = [];
if (is_file($acceptedFile)) {
    $decoded = json_decode((string) file_get_contents($acceptedFile), true);
    if (!is_array($decoded) || !is_array($decoded['accepted'] ?? null)) {
        cannot_measure('bin/gd-accepted-warnings.json exists but is not valid JSON with an "accepted" map');
    }
    foreach ($decoded['accepted'] as $key => $count) {
        $acceptedCounts[(string) $key] = (int) $count;
    }
}

$newWarnings   = [];
$staleAccepted = [];
foreach ($actualCounts as $key => $count) {
    if ($count > ($acceptedCounts[$key] ?? 0)) {
        $newWarnings[$key] = $count - ($acceptedCounts[$key] ?? 0);
    }
}
foreach ($acceptedCounts as $key => $count) {
    if ($count > ($actualCounts[$key] ?? 0)) {
        $staleAccepted[$key] = $count - ($actualCounts[$key] ?? 0);
    }
}

$byCode = [];
foreach ($findings as $f) {
    $key = $f['severity'] === 'error' ? 'E ' . $f['code'] : 'W ' . $f['code'];
    $byCode[$key] = ($byCode[$key] ?? 0) + 1;
}
arsort($byCode);

$filesWith = count(array_unique(array_column($findings, 'file')));

printf(
    "G-D SUMMARY: errors=%d warnings=%d accepted=%d new_warnings=%d stale_accepted=%d files_with_findings=%d shipped_files=%d vendor_files_scanned=%d\n",
    count($errors),
    count($warnings),
    array_sum($acceptedCounts),
    array_sum($newWarnings),
    array_sum($staleAccepted),
    $filesWith,
    count($shipped),
    $vendorScanned
);
printf(
    "  tool: wp plugin check %s --categories=plugin_repo (%s)\n",
    $slug,
    '' === $container ? 'local WordPress at ' . $wpPath : 'container ' . $container
);
printf(
    "  scope: derived from .distignore via `bin/build-release.py --list-shipped` -- %d excluded dirs, %d excluded files\n",
    count($excludeDirs),
    count($excludeFiles)
);

echo "  per-code breakdown (desc):\n";
foreach ($byCode as $code => $count) {
    printf("    %-4d %s\n", $count, $code);
}

if ($findings) {
    echo "  findings:\n";
    usort($findings, static fn($a, $b) => [$a['file'], $a['line']] <=> [$b['file'], $b['line']]);
    foreach ($findings as $f) {
        printf("    %s:%d  %s  %s\n", $f['file'], $f['line'], $f['code'], $f['severity']);
    }
}

if ($newWarnings) {
    echo "  NEW warnings above the documented acceptance (fix them or justify + regenerate the acceptance file):\n";
    foreach ($newWarnings as $key => $over) {
        printf("    +%d  %s\n", $over, $key);
    }
}
if ($staleAccepted) {
    echo "  STALE acceptance entries (the warning is gone -- shrink the file in this same change):\n";
    foreach ($staleAccepted as $key => $short) {
        printf("    -%d  %s\n", $short, $key);
    }
}
if (count($errors) === 0 && $newWarnings === [] && $staleAccepted === [] && count($warnings) > 0) {
    printf("  all %d warnings are accounted for by bin/gd-accepted-warnings.json (documented acceptance)\n", count($warnings));
}

exit((count($errors) === 0 && $newWarnings === [] && $staleAccepted === []) ? EXIT_CLEAN : EXIT_FINDINGS);
