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
 *     cannot ever see it: a star-slash-vendor-slash-star <exclude-pattern> sits inside WP.org's
 *     own phpcs-rulesets/plugin-review.xml AND "vendor" is in Plugin Check's
 *     hardcoded ignore-directory default, which the CLI can only add to, never
 *     subtract from. vendor/mhm/ui-core IS a runtime dependency that ships (see
 *     .distignore's "!/vendor/mhm/"), so the gate stages those files at a path
 *     with no "vendor" segment and runs plugin-review.xml over them directly,
 *     using the ruleset and the phpcs binary from the INSTALLED plugin-check --
 *     never a vendored copy.
 *  E. Prints one machine-readable summary line, a per-code breakdown, and every
 *     finding as file:line/code/severity.
 *
 * ACCEPTANCE BAR: 0 errors AND 0 warnings. Exit 0 only then.
 *
 * EXIT CODES
 *   0  clean
 *   1  findings present (errors and/or warnings)
 *   2  the gate could not MEASURE -- Docker, the container, the plugin, the
 *      shipped-file list or plugin-check itself was unavailable. Never
 *      conflated with 0: a gate that cannot run must be loud, not green.
 *
 * WHERE IT RUNS
 *   Locally, against the Docker WP stack (container rentiva-dev-wpcli-1 by
 *   default; override with MHM_GD_CONTAINER / MHM_GD_PLUGIN_DIR / MHM_GD_SLUG).
 *   It does NOT run on GitHub Actions, which has no WordPress install and no
 *   plugin-check plugin -- there it exits 2 and says so. Making it run in CI
 *   means provisioning WP + plugin-check in the workflow; that is not done yet
 *   and this header does not pretend otherwise.
 */

$root = dirname(__DIR__);
chdir($root);

const EXIT_CLEAN = 0;
const EXIT_FINDINGS = 1;
const EXIT_CANNOT_MEASURE = 2;

$container = getenv('MHM_GD_CONTAINER') ?: 'rentiva-dev-wpcli-1';
$slug      = getenv('MHM_GD_SLUG') ?: 'mhm-rentiva';
$pluginDir = getenv('MHM_GD_PLUGIN_DIR') ?: '/var/www/html/wp-content/plugins/' . $slug;
$pcDir     = '/var/www/html/wp-content/plugins/plugin-check';

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
// inside the /var/www/html/... prefix. Any collision would silently shrink the
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
[$rc] = run_cmd(['docker', 'exec', $container, 'test', '-d', $pluginDir]);
if ($rc !== 0) {
    cannot_measure(
        "container '$container' unreachable, or '$pluginDir' missing inside it",
        'start the Docker WP stack, or set MHM_GD_CONTAINER / MHM_GD_PLUGIN_DIR.'
    );
}
[$rc] = run_cmd(['docker', 'exec', $container, 'test', '-f', $pcDir . '/phpcs-rulesets/plugin-review.xml']);
if ($rc !== 0) {
    cannot_measure(
        "the plugin-check plugin is not installed at $pcDir",
        'install it: wp plugin install plugin-check --activate'
    );
}

$pcArgs = [
    'docker', 'exec', $container,
    'php', '-d', 'memory_limit=1024M', '/usr/local/bin/wp',
    'plugin', 'check', $slug,
    '--categories=plugin_repo',
    '--allow-root',
    '--format=json',
    '--path=/var/www/html',
];
if ($excludeDirs) {
    $pcArgs[] = '--exclude-directories=' . implode(',', $excludeDirs);
}
if ($excludeFiles) {
    $pcArgs[] = '--exclude-files=' . implode(',', $excludeFiles);
}

[$rc, $stdout, $stderr] = run_cmd($pcArgs);

// A fatal (the 128M OOM this gate was born from) leaves partial output and
// still exits 0 in some WP-CLI paths. Refuse to read such a run as a result.
$fatalMarkers = ['Allowed memory size', 'Fatal error', 'PHP Fatal', 'Out of memory'];
foreach ($fatalMarkers as $marker) {
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
    $stage = '/tmp/mhm-gd-vendor-scope';
    // Restage from scratch every run so a deleted source file cannot linger.
    $script = 'set -e; rm -rf ' . $stage . '; mkdir -p ' . $stage . ';';
    foreach ($vendorPhp as $p) {
        $destRel = substr($p, 7); // drop the leading "vendor/" -- the segment
                                  // is what both ignore layers key on.
        $script .= ' mkdir -p "' . $stage . '/' . dirname($destRel) . '";';
        $script .= ' cp "' . $pluginDir . '/' . $p . '" "' . $stage . '/' . $destRel . '";';
    }
    $script .= ' php -d memory_limit=1024M ' . $pcDir . '/vendor/bin/phpcs'
             . ' --standard=' . $pcDir . '/phpcs-rulesets/plugin-review.xml'
             . ' --extensions=php --report=json ' . $stage . ' || true;';

    [, $vOut, $vErr] = run_cmd(['docker', 'exec', $container, 'bash', '-c', $script]);
    $start = strpos($vOut, '{"totals"');
    $vJson = $start === false ? null : json_decode(substr($vOut, $start), true);
    if (!is_array($vJson) || !isset($vJson['files'])) {
        cannot_measure(
            'the supplementary vendor/ pass produced no parsable phpcs JSON',
            'stderr: ' . trim($vErr)
        );
    }
    foreach ($vJson['files'] as $absFile => $data) {
        $vendorScanned++;
        foreach ($data['messages'] as $m) {
            $findings[] = [
                'file'     => 'vendor/' . ltrim(str_replace($stage, '', str_replace('\\', '/', $absFile)), '/'),
                'line'     => (int) $m['line'],
                'code'     => (string) $m['source'],
                'severity' => strtolower((string) $m['type']),
            ];
        }
    }
    if ($vendorScanned !== count($vendorPhp)) {
        cannot_measure(
            "the supplementary vendor/ pass scanned $vendorScanned of " . count($vendorPhp) . ' shipped PHP files'
        );
    }
}

// ---------------------------------------------------------------------------
// E. Report.
// ---------------------------------------------------------------------------
$errors   = array_values(array_filter($findings, static fn($f) => $f['severity'] === 'error'));
$warnings = array_values(array_filter($findings, static fn($f) => $f['severity'] === 'warning'));

$byCode = [];
foreach ($findings as $f) {
    $key = $f['severity'] === 'error' ? 'E ' . $f['code'] : 'W ' . $f['code'];
    $byCode[$key] = ($byCode[$key] ?? 0) + 1;
}
arsort($byCode);

$filesWith = count(array_unique(array_column($findings, 'file')));

printf(
    "G-D SUMMARY: errors=%d warnings=%d files_with_findings=%d shipped_files=%d vendor_files_scanned=%d\n",
    count($errors),
    count($warnings),
    $filesWith,
    count($shipped),
    $vendorScanned
);
printf(
    "  tool: wp plugin check %s --categories=plugin_repo (container %s)\n",
    $slug,
    $container
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

exit((count($errors) === 0 && count($warnings) === 0) ? EXIT_CLEAN : EXIT_FINDINGS);
