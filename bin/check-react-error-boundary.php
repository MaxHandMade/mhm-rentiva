<?php
/**
 * Every React admin screen must mount inside an error boundary.
 *
 * THE DEFECT THIS EXISTS FOR
 *
 * On 2026-08-27 one broken endpoint map took out five of the add-on's admin
 * screens at once. They did not fail the same way, and the difference was
 * exactly one component: three were wrapped in <ErrorBoundary> and showed
 * "an error occurred, please refresh"; two were not, and React unmounted the
 * whole tree -- a blank page under the WordPress chrome, with nothing on screen
 * saying anything was wrong. A third unguarded screen was found in this repo
 * during the sweep, which is the reason this gate exists instead of three edits:
 * "wrap these two" was already the wrong size of answer when it was written.
 *
 * A boundary does not fix anything. It decides whether a failure reads as
 * "this screen is broken" or as "this plugin is broken", and the second one is
 * what an operator reports.
 *
 * WHAT THIS CHECKS
 *
 * Each entry point under src-react/admin/<screen>/index.js that mounts a root
 * must have <ErrorBoundary> inside the render() call. Entry points that mount
 * nothing are skipped and counted.
 *
 * WHAT IT DOES NOT CHECK (say it, or the silence gets read as coverage)
 *
 *   - Whether the boundary is the RIGHT one, or renders anything useful. It
 *     matches the element by name, not by behaviour.
 *   - Boundaries placed deeper in the tree instead of at the mount. That is a
 *     legitimate design this gate would call a failure; the house convention is
 *     to wrap at the entry, and the gate encodes the convention, not a law of
 *     React.
 *   - Whether the component inside actually throws on failure.
 *
 * Exit codes:
 *   0 - every mounting entry point is wrapped
 *   1 - at least one mounts unguarded
 *   2 - the gate could not measure (directory unreadable, or zero entry points
 *       found). Never reported as success: a scan that found nothing would
 *       otherwise print "clean" for every possible defect.
 *
 * Env overrides (how the add-on points this at its own tree):
 *   MHM_REACT_SRC - directory holding <screen>/index.js entry points
 *
 * Usage: php bin/check-react-error-boundary.php
 *
 * @package MHM_Rentiva
 */

declare(strict_types=1);

$root = dirname(__DIR__);

$src_dir = getenv('MHM_REACT_SRC');
$src_dir = is_string($src_dir) && '' !== $src_dir ? $src_dir : $root . '/src-react/admin';

if (! is_dir($src_dir)) {
    fwrite(STDERR, "check-react-error-boundary: cannot read {$src_dir}\n");
    exit(2);
}

$entries = glob(rtrim($src_dir, '/\\') . '/*/index.js');

if (! is_array($entries) || $entries === array()) {
    fwrite(STDERR, "check-react-error-boundary: found ZERO entry points under {$src_dir}.\n");
    fwrite(STDERR, "That is a broken scan, not a clean tree. Refusing to report success.\n");
    exit(2);
}

sort($entries);

$unguarded = array();
$guarded   = array();
$inert     = array();

foreach ($entries as $entry) {
    $body   = (string) file_get_contents($entry);
    $screen = basename(dirname($entry));

    // An entry that never mounts a root cannot leave a screen unguarded.
    if (preg_match('/createRoot\s*\(/', $body) !== 1) {
        $inert[] = $screen;
        continue;
    }

    /*
     * Match the render() argument rather than the whole file: importing
     * ErrorBoundary and then not using it is exactly the half-done edit this
     * gate should catch, and a file-wide grep would call that clean.
     */
    if (preg_match('/\.render\s*\((.*?)\)\s*;/s', $body, $m) !== 1) {
        $unguarded[] = array( 'screen' => $screen, 'why' => 'render() call not found' );
        continue;
    }

    if (strpos($m[1], '<ErrorBoundary') === false) {
        $unguarded[] = array( 'screen' => $screen, 'why' => 'mounts without <ErrorBoundary>' );
        continue;
    }

    $guarded[] = $screen;
}

// Entry points exist but none of them mounts anything: the scan reached files it
// does not understand, not a tree with nothing to guard. Reported as "cannot
// measure" for the same reason as an empty directory -- this gate's own negative
// controls caught it returning 0 here, which is how a gate stops watching while
// still printing green.
if ($guarded === array() && $unguarded === array()) {
    fwrite(STDERR, "check-react-error-boundary: " . count($entries) . " entry point(s) found, but NONE mounts a root.\n");
    fwrite(STDERR, "  Nothing was measured. Refusing to report success.\n");
    exit(2);
}

printf(
    "React error-boundary gate\n  sources  : %s\n  entries  : %d (%d mounting, %d inert)\n  guarded  : %s\n",
    $src_dir,
    count($entries),
    count($guarded) + count($unguarded),
    count($inert),
    $guarded === array() ? '(none)' : implode(', ', $guarded)
);

if ($inert !== array()) {
    printf("  inert    : %s (no createRoot; nothing to guard)\n", implode(', ', $inert));
}

if ($unguarded !== array()) {
    fwrite(STDERR, "\n[X] " . count($unguarded) . " screen(s) mount without an error boundary.\n\n");
    foreach ($unguarded as $item) {
        fwrite(STDERR, sprintf("  %-24s %s\n", $item['screen'], $item['why']));
    }
    fwrite(STDERR, "\n  Without a boundary a render error unmounts the tree: the operator sees a\n");
    fwrite(STDERR, "  blank page under the admin chrome, with nothing saying what failed.\n");
    fwrite(STDERR, "  Wrap the component in <ErrorBoundary> inside render().\n\n");
    exit(1);
}

echo "[OK] every mounting screen is wrapped in an error boundary.\n";
exit(0);
