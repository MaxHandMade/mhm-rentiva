<?php
/**
 * Manifest invariant gate: src/ must be EXACTLY the Lite keep-list.
 *
 * The carve-out is a whitelist, not a blacklist: carveout/lite-manifest-final.txt
 * names every PHP file the Lite build is allowed to ship, and this script asserts
 * the tree matches it in both directions.
 *
 * Both directions matter, and for different reasons:
 *
 *   - A file in src/ but NOT in the manifest is a Pro file that crept back --
 *     the exact silent-regression this carve-out exists to prevent. A blacklist
 *     ("delete these") cannot detect it; only a whitelist can.
 *   - A file in the manifest but NOT in src/ means the manifest is lying about
 *     what Lite ships, which quietly rots every decision made from it.
 *
 * Paths are compared repo-relative with forward slashes so the result is
 * identical on Windows and Linux.
 *
 * Exit codes: 0 = tree matches manifest, 1 = mismatch (or manifest missing).
 *
 * @package MHM_Rentiva
 */

declare(strict_types=1);

$root     = dirname(__DIR__);
$manifest = $root . '/carveout/lite-manifest-final.txt';

if (! is_file($manifest)) {
    fwrite(STDERR, "Manifest not found: carveout/lite-manifest-final.txt\n");
    exit(1);
}

/**
 * Normalise a path to repo-relative, forward-slashed form.
 */
$relative = static function (string $path) use ($root): string {
    $path = str_replace('\\', '/', $path);
    $base = str_replace('\\', '/', $root) . '/';

    if (strpos($path, $base) === 0) {
        $path = substr($path, strlen($base));
    }

    return $path;
};

// Expected: the keep-list.
$expected = [];
foreach (file($manifest, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $line = trim($line);
    if ($line !== '' && strpos($line, '#') !== 0) {
        $expected[] = str_replace('\\', '/', $line);
    }
}
sort($expected);
$expected = array_values(array_unique($expected));

// Actual: every .php file under src/.
$actual   = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root . '/src', RecursiveDirectoryIterator::SKIP_DOTS)
);
foreach ($iterator as $file) {
    if ($file->getExtension() === 'php') {
        $actual[] = $relative($file->getPathname());
    }
}
sort($actual);

$unexpected = array_values(array_diff($actual, $expected));   // in src/, not in manifest
$missing    = array_values(array_diff($expected, $actual));   // in manifest, not in src/

if ($unexpected !== [] || $missing !== []) {
    echo "src/ does not match the Lite manifest.\n\n";

    if ($unexpected !== []) {
        echo "In src/ but NOT in the manifest (Pro file crept back into Lite?):\n\n";
        echo '  ' . implode("\n  ", $unexpected) . "\n\n";
    }

    if ($missing !== []) {
        echo "In the manifest but NOT in src/ (removed too much, or manifest is stale?):\n\n";
        echo '  ' . implode("\n  ", $missing) . "\n\n";
    }

    printf(
        "%d unexpected, %d missing (manifest: %d, src: %d).\n",
        count($unexpected),
        count($missing),
        count($expected),
        count($actual)
    );
    exit(1);
}

printf("[OK] src == Lite manifest (%d files)\n", count($actual));
exit(0);
