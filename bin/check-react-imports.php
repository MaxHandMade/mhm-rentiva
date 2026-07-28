<?php
/**
 * React import-declaration gate: every third-party module src-react/ imports
 * must be declared in the package.json that builds it.
 *
 * Why this gate exists: RevenueChart.jsx imported `chart.js/auto` from
 * 4ab50447 (2026-05-20) until b534cb53 (2026-07-28) while chart.js was
 * declared in neither package.json nor package-lock.json. It built anyway,
 * because somebody had installed chart.js into node_modules by hand and npm
 * left it there as `extraneous`. Nothing measured the gap for two months:
 * PHPUnit, PHPCS, PHPStan and the six carve gates all read PHP, the committed
 * build/admin/*.js masked it from every runtime check, and the shipped ZIP
 * was fine. Only a clean checkout would have failed -- `npm ci` prunes
 * undeclared packages, so `npm run build` would die on an unresolved module.
 *
 * This is an ABSENCE defect, and absence is what linters miss: eslint flags
 * an import it cannot resolve in *its* tree, not one that resolves from a
 * package nobody promised to install. So the gate compares two sets that must
 * agree -- what the sources import, and what the manifest promises.
 *
 * WHAT THIS GATE CANNOT SEE (printed on every run, because a tool that
 * under-reports while printing "0 found" is the false confidence that let 500
 * phpcs suppressions read as clean):
 *
 *   - Version correctness. It asserts chart.js is declared, not that the
 *     declared range matches the version actually bundled.
 *   - Whether a declared package is still imported (dead declarations).
 *   - Whether a builder exists at all. A repo can pass by declaring
 *     everything and still have no webpack config -- see the Pro entry below.
 *   - Anything outside src-react/. Imports in assets/js/ are hand-written
 *     browser scripts with no bundler; they are out of scope by design.
 *   - Transitive imports inside node_modules.
 *   - An import statement that does not begin its own line. This is the price
 *     of not inventing packages out of JSX prose; see $specifiers below.
 *
 * Exit codes: 0 = every import is declared (or declared-pending), 1 = an
 * undeclared import, an unreadable manifest, or a malformed package.json.
 *
 * @package MHM_Rentiva
 */

declare(strict_types=1);

/**
 * Modules the build never resolves from node_modules.
 *
 * @wordpress/dependency-extraction-webpack-plugin (shipped inside
 * @wordpress/scripts) rewrites these to WordPress script handles instead of
 * bundling them, so they must NOT be required in package.json. Evidence, not
 * belief: build/admin/dashboard.asset.php lists exactly
 * 'react-jsx-runtime', 'wp-api-fetch', 'wp-components', 'wp-element',
 * 'wp-i18n' as its WP dependencies -- the @wordpress/* imports became handles
 * -- while chart.js appears in none of the .asset.php files because it is
 * compiled into the bundle.
 */
const EXTERNALISED_PREFIXES = array(
    '@wordpress/',
);

/**
 * Bare specifiers the same plugin externalises without a namespace prefix.
 */
const EXTERNALISED_EXACT = array(
    'react',
    'react-dom',
    'react-jsx-runtime',
    'jquery',
    'lodash',
    'moment',
);

/**
 * Repos audited beyond this one, and what is known about them.
 *
 * mhm-rentiva-pro received 5 React screens in Task A11a (78e9cf4) -- reports,
 * messages, export, vendor-reports, vendor-management -- and Lite's
 * webpack.config.js dropped their entries at the same time. Pro therefore
 * tracks 54 source files under src-react/ that NOTHING can rebuild: no
 * package.json, no package-lock.json, no webpack.config.js, no node_modules.
 * Its build/admin/*.js are frozen artefacts produced by Lite's toolchain
 * before the carve.
 *
 * This is DECLARED PENDING, not accepted: the gate prints it on every run and
 * exits 0, because deciding between "give Pro a build chain" and "freeze the
 * sources and say so" is a scope call that has not been made. When it is
 * made, delete this entry -- if Pro gets a manifest the audit below covers it
 * automatically.
 */
const PENDING_TARGETS = array(
    'mhm-rentiva-pro' => 'sources tracked, no manifest and no builder (Task A11a carve); rebuild path undecided',
);

$root = dirname(__DIR__);

/**
 * Normalise a path to forward-slashed form so Windows and Linux agree.
 */
$normalise = static function (string $path): string {
    return str_replace('\\', '/', $path);
};

/**
 * Collect every .js/.jsx file under a directory.
 *
 * @return string[]
 */
$sources = static function (string $dir) use ($normalise): array {
    if (! is_dir($dir)) {
        return array();
    }

    $found    = array();
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (! $file->isFile()) {
            continue;
        }

        $extension = strtolower($file->getExtension());

        if ($extension === 'js' || $extension === 'jsx') {
            $found[] = $normalise($file->getPathname());
        }
    }

    sort($found);

    return $found;
};

/**
 * Extract every bare module specifier a file imports.
 *
 * Four forms are matched, because catching only `import X from '<x>'` would
 * miss the sibling spellings that reach node_modules just as effectively:
 * side-effect `import '<x>'`, re-`export ... from '<x>'`, `require('<x>')`
 * and dynamic `import('<x>')`.
 *
 * The import/export forms are anchored to the start of a line and a captured
 * specifier may contain neither a newline nor whitespace. Both restrictions
 * are load-bearing: an unanchored `from\s*['"]...['"]` search matches ordinary
 * JSX prose. In mhm-rentiva-pro it invented three packages out of
 * `aria-label="... from"` followed by `type="date"` on the next line, because
 * the attribute's closing quote opened the match and the capture ran across
 * the newline to the next quote. A gate that reports packages nobody imports
 * is worse than no gate: the first person to see `[UNDECLARED] type=` learns
 * to ignore its output.
 *
 * The cost of anchoring is a blind spot, declared in the header: an import
 * that does not begin its line is not seen. ES module imports must be
 * top-level statements, and everything in src-react/ is hand-written, so this
 * trades a real false-positive class for a hypothetical false negative.
 *
 * @return string[] Specifiers exactly as written.
 */
$specifiers = static function (string $code): array {
    // Strip comments first, so commented-out imports and prose about imports
    // cannot contribute. Order matters: block comments before line comments.
    $stripped = preg_replace('#/\*[\s\S]*?\*/#', '', $code);
    $stripped = $stripped === null ? $code : $stripped;
    $stripped = preg_replace('#^[ \t]*//.*$#m', '', $stripped);
    $stripped = $stripped === null ? $code : $stripped;

    $patterns = array(
        // Line-anchored `import ... from '<x>'`, including the multi-line
        // `import {\n a,\n b\n} from '<x>'` shape.
        '/^[ \t]*import\b[^;\'"]{0,400}?\bfrom[ \t]*[\'"]([^\'"\s]+)[\'"]/m',
        // Line-anchored `export ... from '<x>'` re-exports.
        '/^[ \t]*export\b[^;\'"]{0,400}?\bfrom[ \t]*[\'"]([^\'"\s]+)[\'"]/m',
        // Line-anchored side-effect import with no binding: import '<x>';
        '/^[ \t]*import[ \t]*[\'"]([^\'"\s]+)[\'"]/m',
        // require('<x>') and dynamic import('<x>') -- call syntax is specific
        // enough that it needs no line anchor.
        '/\b(?:require|import)[ \t]*\([ \t]*[\'"]([^\'"\s]+)[\'"][ \t]*\)/',
    );

    $found = array();

    foreach ($patterns as $pattern) {
        if (preg_match_all($pattern, $stripped, $matches) > 0) {
            foreach ($matches[1] as $specifier) {
                $found[] = $specifier;
            }
        }
    }

    return $found;
};

/**
 * Reduce a specifier to the package name npm would install.
 *
 * 'chart.js/auto' => 'chart.js', '@wordpress/element' => '@wordpress/element'.
 * Returns null for anything that is not a bare package import.
 */
$packageName = static function (string $specifier): ?string {
    if ($specifier === '') {
        return null;
    }

    // Relative, absolute, protocol-prefixed, alias -- not a node_modules lookup.
    $firstChar = $specifier[0];

    if ($firstChar === '.' || $firstChar === '/' || $firstChar === '~' || $firstChar === '!') {
        return null;
    }

    if (strpos($specifier, 'node:') === 0 || preg_match('#^[a-z]+:#', $specifier) === 1) {
        return null;
    }

    $segments = explode('/', $specifier);

    if ($firstChar === '@') {
        if (count($segments) < 2) {
            return null;
        }

        return $segments[0] . '/' . $segments[1];
    }

    return $segments[0];
};

/**
 * True when the module is rewritten to a WP script handle rather than bundled.
 */
$isExternalised = static function (string $package): bool {
    if (in_array($package, EXTERNALISED_EXACT, true)) {
        return true;
    }

    foreach (EXTERNALISED_PREFIXES as $prefix) {
        if (strpos($package, $prefix) === 0) {
            return true;
        }
    }

    return false;
};

/**
 * Audit one repo root. Returns [undeclared, importedCount, fileCount, note].
 */
$audit = static function (string $repoRoot) use (
    $sources,
    $specifiers,
    $packageName,
    $isExternalised,
    $normalise
): array {
    $reactDir = $repoRoot . '/src-react';
    $files    = $sources($reactDir);

    if ($files === array()) {
        return array(array(), array(), 0, 'no src-react/ directory');
    }

    $imported = array();

    foreach ($files as $file) {
        $code = file_get_contents($file);

        if ($code === false) {
            continue;
        }

        foreach ($specifiers($code) as $specifier) {
            $package = $packageName($specifier);

            if ($package === null || $isExternalised($package)) {
                continue;
            }

            $relative = str_replace($normalise($repoRoot) . '/', '', $file);

            $imported[$package][$relative] = true;
        }
    }

    ksort($imported);

    $manifestPath = $repoRoot . '/package.json';

    if (! is_file($manifestPath)) {
        return array($imported, $imported, count($files), 'NO package.json');
    }

    $raw = file_get_contents($manifestPath);

    if ($raw === false) {
        return array($imported, $imported, count($files), 'package.json unreadable');
    }

    $manifest = json_decode($raw, true);

    if (! is_array($manifest)) {
        return array($imported, $imported, count($files), 'package.json is not valid JSON');
    }

    $declared = array_merge(
        array_keys((array) ($manifest['dependencies'] ?? array())),
        array_keys((array) ($manifest['devDependencies'] ?? array())),
        array_keys((array) ($manifest['peerDependencies'] ?? array())),
        array_keys((array) ($manifest['optionalDependencies'] ?? array()))
    );

    $undeclared = array();

    foreach ($imported as $package => $usedIn) {
        if (! in_array($package, $declared, true)) {
            $undeclared[$package] = $usedIn;
        }
    }

    return array($imported, $undeclared, count($files), '');
};

echo "React import-declaration gate\n";
echo "=============================\n\n";

echo "Not checked by this gate (see the docblock for why):\n";
echo "  - whether a declared version range matches the bundled version\n";
echo "  - dead declarations (declared but no longer imported)\n";
echo "  - whether a builder (webpack config) exists at all\n";
echo "  - imports outside src-react/, and imports inside node_modules\n";
echo "  - an import statement that does not begin its own line\n\n";

echo 'Treated as WordPress-provided, so deliberately NOT required in package.json: ';
echo implode(', ', EXTERNALISED_PREFIXES) . '* plus ' . implode(', ', EXTERNALISED_EXACT) . "\n\n";

$failed = false;

// --- This repo ------------------------------------------------------------

list($imported, $undeclared, $fileCount, $note) = $audit($root);

echo "TARGET: " . basename($root) . " (this repo)\n";

if ($note === 'no src-react/ directory') {
    echo "  no src-react/ -- nothing to audit\n\n";
} else {
    echo "  scanned {$fileCount} source file(s), found " . count($imported) . " bundled package(s)\n";

    foreach ($imported as $package => $usedIn) {
        $status = isset($undeclared[$package]) ? 'UNDECLARED' : 'declared';
        echo "    [{$status}] {$package}  <- " . implode(', ', array_keys($usedIn)) . "\n";
    }

    if ($note !== '') {
        echo "  manifest problem: {$note}\n";
        $failed = true;
    }

    if ($undeclared !== array()) {
        $failed = true;
        echo "\n  FAIL: " . count($undeclared) . " import(s) resolve from node_modules but are\n";
        echo "  promised by no manifest. `npm ci` prunes undeclared packages, so a clean\n";
        echo "  checkout cannot build. Declare them in package.json:\n";

        foreach ($undeclared as $package => $usedIn) {
            echo "    npm install --save-prod {$package}\n";
        }
    } else {
        echo "  OK: every bundled import is declared\n";
    }

    echo "\n";
}

// --- Sibling repos --------------------------------------------------------

foreach (PENDING_TARGETS as $sibling => $reason) {
    $siblingRoot = dirname($root) . '/' . $sibling;

    echo "TARGET: {$sibling}\n";

    if (! is_dir($siblingRoot)) {
        echo "  NOT PRESENT at " . $normalise($siblingRoot) . " -- NOT AUDITED.\n";
        echo "  This gate did not look at it. In a single-repo checkout (CI) that is\n";
        echo "  expected; do not read this run as covering it.\n\n";
        continue;
    }

    list($siblingImported, $siblingUndeclared, $siblingFiles, $siblingNote) = $audit($siblingRoot);

    echo "  scanned {$siblingFiles} source file(s), found " . count($siblingImported) . " bundled package(s)\n";

    foreach ($siblingImported as $package => $usedIn) {
        echo "    {$package}  <- " . implode(', ', array_keys($usedIn)) . "\n";
    }

    if ($siblingNote === 'NO package.json') {
        echo "\n  DECLARED PENDING (exit 0, on purpose):\n";
        echo "    {$reason}\n";
        echo "  Consequence today: editing those sources changes nothing users receive --\n";
        echo "  the shipped build/admin/*.js cannot be regenerated from them.\n";
        echo "  This entry is temporary. Delete it from PENDING_TARGETS once the scope\n";
        echo "  call is made; a Pro package.json makes the audit above cover it.\n\n";
        continue;
    }

    if ($siblingUndeclared !== array()) {
        $failed = true;
        echo "\n  FAIL: undeclared import(s): " . implode(', ', array_keys($siblingUndeclared)) . "\n\n";
        continue;
    }

    echo "  OK: every bundled import is declared\n\n";
}

if ($failed) {
    fwrite(STDERR, "React import-declaration gate FAILED.\n");
    exit(1);
}

echo "React import-declaration gate PASSED.\n";
exit(0);
