<?php
/**
 * Shipped-surface invariant gate: the ZIP must contain EXACTLY the pinned list.
 *
 * WHY THIS EXISTS
 *
 * bin/check-manifest.php pins src/. Nothing pinned the rest, and the rest is
 * most of the plugin: assets/, templates/, src-react/, languages/, build/, the
 * root files, and vendor/mhm/ui-core -- 588 files against src/'s 270. The ZIP is
 * how WordPress.org reviewers and every user meet this plugin, and its contents
 * drifted in both directions without a single gate objecting:
 *
 *   - INTO the ZIP. A phpunit --log-junit file written to ./build/ took the
 *     shipped surface from 588 to 589 (measured 2026-08-30). build/ ships,
 *     because the compiled admin bundles live there, so anything else dropped
 *     into it ships too.
 *   - OUT OF the ZIP, which is worse. Pro was shipping the ui-core package's
 *     README because a .distignore re-exclusion sat in the wrong order, and
 *     that was invisible until somebody staged the shape by hand. The same
 *     ordering mistake in the other direction drops a file the plugin needs at
 *     runtime, and the first report is a fatal on a user's site.
 *
 * A count alone would not do: 588 stays 588 when one file is added and another
 * is dropped in the same change. The comparison is by name, both directions.
 *
 * WHERE THE TRUTH COMES FROM
 *
 * The list is READ from the ZIP builder -- `bin/build-release.py
 * --list-shipped`, the same is_excluded() walk that stages the release ZIP --
 * and never restated here. Restating .distignore in a second place is exactly
 * how the old G-D gate drifted out of agreement with the ZIP it claimed to
 * measure. If .distignore changes, the ZIP and this gate change together or
 * not at all.
 *
 * The pin lives next to this gate and is tracked. bin/check-manifest.php's
 * docblock records why that matters: a gate whose input is not in the
 * repository is not coverage, it is a script.
 *
 * ABOUT vendor/mhm/ui-core
 *
 * 24 of the 588 are not tracked by git -- they are composer-installed, pinned
 * by composer.lock. Including them is deliberate. A ui-core release that
 * changes which files the package ships changes what this plugin ships to
 * WordPress.org, and that should require someone to look: Phase 2 took the
 * bundled package from 12 files to 24, which is precisely the kind of change
 * worth seeing in a diff rather than discovering in a ZIP.
 *
 * Exit codes: 0 = matches, 1 = drift, 2 = cannot measure.
 *
 * Regenerate after an intended change, in the same commit that causes it:
 *   php bin/check-shipped-surface.php --write
 *
 * @package Mhm_Rentiva
 */

declare(strict_types=1);

const EXIT_CLEAN = 0;
const EXIT_DRIFT = 1;
const EXIT_CANNOT_MEASURE = 2;

$root = dirname(__DIR__);
chdir($root);

$pinPath = getenv('MHM_SHIPPED_SURFACE_PIN');
if (false === $pinPath || '' === $pinPath) {
    $pinPath = $root . '/bin/shipped-surface-final.txt';
}

$write = in_array('--write', array_slice($argv, 1), true);

/**
 * Run a command without going through a shell.
 *
 * @param string[] $argv Command and arguments.
 * @return array{0:int,1:string}
 */
function shipped_run(array $argv): array
{
    $spec = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    // Silenced deliberately: this probes for python3 vs python vs py, and a
    // missing one is an expected answer carried by the exit code.
    $proc = @proc_open($argv, $spec, $pipes);
    if (! is_resource($proc)) {
        return [-1, ''];
    }
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return [proc_close($proc), (string) $stdout];
}

$actual = null;
foreach (['python3', 'python', 'py'] as $py) {
    [$rc, $out] = shipped_run([$py, 'bin/build-release.py', '--list-shipped']);
    if (0 === $rc && '' !== trim($out)) {
        $actual = preg_split('/\R/', trim($out));
        break;
    }
}

if (null === $actual) {
    fwrite(STDERR, "[CANNOT MEASURE] could not obtain the shipped file list.\n");
    fwrite(STDERR, "  needs a working `python bin/build-release.py --list-shipped` (Python 3).\n");
    fwrite(STDERR, "  refusing to report success: a gate that cannot run is not a gate that passed.\n");
    exit(EXIT_CANNOT_MEASURE);
}

sort($actual);

if ($write) {
    file_put_contents($pinPath, implode("\n", $actual) . "\n");
    printf("[WROTE] %s -- %d file(s).\n", $pinPath, count($actual));
    printf("  Commit this together with the change that justifies it.\n");
    exit(EXIT_CLEAN);
}

if (! is_file($pinPath)) {
    fwrite(STDERR, "[CANNOT MEASURE] pin not found: {$pinPath}\n");
    fwrite(STDERR, "  generate it with: php bin/check-shipped-surface.php --write\n");
    exit(EXIT_CANNOT_MEASURE);
}

$pinned = preg_split('/\R/', trim((string) file_get_contents($pinPath)));
$pinned = array_values(array_filter($pinned, static fn (string $l): bool => '' !== trim($l)));
sort($pinned);

if ([] === $pinned) {
    fwrite(STDERR, "[CANNOT MEASURE] the pin is empty: {$pinPath}\n");
    fwrite(STDERR, "  an empty pin would make any tree 'match by subtraction'.\n");
    exit(EXIT_CANNOT_MEASURE);
}

$added   = array_values(array_diff($actual, $pinned));
$removed = array_values(array_diff($pinned, $actual));

printf(
    "SHIPPED SURFACE: pinned=%d actual=%d added=%d removed=%d\n",
    count($pinned),
    count($actual),
    count($added),
    count($removed)
);

if ([] === $added && [] === $removed) {
    print "  [OK] the ZIP contains exactly the pinned surface.\n";
    exit(EXIT_CLEAN);
}

foreach ($added as $path) {
    printf("  [+] now shipping, not in the pin: %s\n", $path);
}
foreach ($removed as $path) {
    printf("  [-] pinned but NO LONGER shipping: %s\n", $path);
}

fwrite(STDERR, "\n");
fwrite(STDERR, "The release ZIP no longer matches the pinned surface.\n");
fwrite(STDERR, "  [+] lines are files that would now reach users. A stray build artefact or a\n");
fwrite(STDERR, "      .distignore re-exclusion in the wrong order both look like this.\n");
fwrite(STDERR, "  [-] lines are worse: a file the plugin may need at runtime stopped shipping,\n");
fwrite(STDERR, "      and the first report of that is usually a fatal on somebody's site.\n");
fwrite(STDERR, "  If the change is intended, regenerate the pin in the same commit:\n");
fwrite(STDERR, "      php bin/check-shipped-surface.php --write\n");

exit(EXIT_DRIFT);
