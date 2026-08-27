<?php
/**
 * Every REST member a React screen calls must exist in the endpoint map it imports.
 *
 * THE DEFECT THIS EXISTS FOR
 *
 * The add-on's five React screens read their endpoints out of THIS plugin's map,
 * which reaches them as a byte copy made by the add-on's sync-shared-react.js.
 * On 2026-08-08 the WP.org carve (0ecc9368) removed the paid sections from that
 * map -- correctly, because this plugin may not reference paid endpoints -- and
 * nothing noticed the add-on depended on them. `rentivaApi.reports` became
 * undefined, `.getSummary` threw, and all five of its screens broke: two had no
 * error boundary and died outright, three degraded to "could not load".
 *
 * It stayed invisible for nineteen days. Nothing caught it, and the reasons are
 * what this gate is shaped against:
 *
 *   - check-react-imports measures PACKAGE imports against package.json. A map
 *     KEY is not a package, so it looked at that code and saw nothing wrong.
 *   - PHPStan and PHPCS do not read JavaScript.
 *   - Neither PHPUnit suite mounts React.
 *   - Those screens had never been opened in a browser.
 *
 * The missing measurement was never subtle: nobody compared the members the
 * screens CALL against the members the map DEFINES. That comparison is cheap and
 * static, and it is this file.
 *
 * WHY IT LIVES HERE AND THE ADD-ON CALLS IT
 *
 * The defect happened on the add-on's side, but this repository is the one that
 * can host the checker: its CI is single-repo by design (this plugin is public
 * and WP.org-bound; it must not need a private sibling checked out to go green),
 * whereas the add-on's CI already checks this tree out as a sibling. So the
 * checker lives here with defaults describing THIS plugin, and the add-on runs
 * the same file against its own map through the env overrides below -- the same
 * cross-repo shape as check-react-imports.php and build-i18n.py.
 *
 * WHAT THIS DOES NOT COVER (say it, or the silence gets read as coverage)
 *
 *   - Whether a defined member's PATH matches a route WordPress registers. That
 *     is a runtime fact; this gate only knows the map's own text.
 *   - Members reached dynamically (rentivaApi[ x ][ y ]). Only literal property
 *     access is found. There are none today; a future one is invisible.
 *   - Argument counts and shapes.
 *
 * Exit codes:
 *   0 - every called member is defined
 *   1 - at least one called member is missing from the map
 *   2 - the gate could not measure (unreadable input, an extractor that found
 *       nothing, or a symbol nothing calls). Never reported as success: a parser
 *       that matched zero members would otherwise print "clean" for every
 *       possible defect.
 *
 * Env overrides (how the add-on points this at its own tree):
 *   MHM_API_MAP    - path to the endpoint map file
 *   MHM_API_SRC    - directory of React sources to scan
 *   MHM_API_SYMBOL - the exported object's name as used at the call sites
 *
 * Usage: php bin/check-react-api-map.php
 *
 * @package MHM_Rentiva
 */

declare(strict_types=1);

$root = dirname(__DIR__);

$map_file = getenv('MHM_API_MAP');
$src_dir  = getenv('MHM_API_SRC');
$symbol   = getenv('MHM_API_SYMBOL');

$map_file = is_string($map_file) && '' !== $map_file ? $map_file : $root . '/src-react/shared/api/rentiva.js';
$src_dir  = is_string($src_dir) && '' !== $src_dir ? $src_dir : $root . '/src-react/admin';
$symbol   = is_string($symbol) && '' !== $symbol ? $symbol : 'rentivaApi';

if (! is_readable($map_file)) {
    fwrite(STDERR, "check-react-api-map: cannot read the endpoint map: {$map_file}\n");
    exit(2);
}
if (! is_dir($src_dir)) {
    fwrite(STDERR, "check-react-api-map: cannot read the source directory: {$src_dir}\n");
    exit(2);
}

/**
 * Section => list of member names declared in the map.
 *
 * Brace-depth walk rather than one big regex: the map is a nested object
 * literal, and depth is exactly what tells a section apart from a member.
 *
 * @param string $source Map file contents.
 * @return array<string, string[]>
 */
function mhmrentiva_api_map_members(string $source): array
{
    $out     = array();
    $depth   = 0;
    $section = null;

    foreach (preg_split('/\R/', $source) ?: array() as $line) {
        $trimmed = trim($line);

        if (1 === $depth && preg_match('/^([A-Za-z_]\w*)\s*:\s*\{/', $trimmed, $m) === 1) {
            $section         = $m[1];
            $out[ $section ] = array();
        } elseif (2 === $depth && null !== $section
            && preg_match('/^([A-Za-z_]\w*)\s*:/', $trimmed, $m) === 1) {
            $out[ $section ][] = $m[1];
        }

        $depth += substr_count($line, '{') - substr_count($line, '}');
        if ($depth < 2) {
            $section = ( 1 === $depth ) ? $section : null;
        }
    }

    return array_map('array_unique', $out);
}

$map = mhmrentiva_api_map_members((string) file_get_contents($map_file));

$defined = 0;
foreach ($map as $members) {
    $defined += count($members);
}

if ($defined === 0) {
    fwrite(STDERR, "check-react-api-map: the extractor found ZERO members in {$map_file}.\n");
    fwrite(STDERR, "That is a broken parser, not a clean map. Refusing to report success.\n");
    exit(2);
}

// Self-check the extractor before trusting it: a name that is definitely not in
// the map must not come back as defined. A parser that answers "yes" to
// everything would pass every real comparison below for the wrong reason.
foreach ($map as $members) {
    if (in_array('mhmNotARealMember', $members, true)) {
        fwrite(STDERR, "check-react-api-map: extractor self-check failed (matched a fabricated member).\n");
        exit(2);
    }
}

/** @var array<string, array<string, string[]>> $used section => member => files */
$used     = array();
$scanned  = 0;
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src_dir, FilesystemIterator::SKIP_DOTS));

foreach ($iterator as $file) {
    if (! $file->isFile() || ! in_array($file->getExtension(), array('js', 'jsx'), true)) {
        continue;
    }
    ++$scanned;
    $body = (string) file_get_contents($file->getPathname());

    // \s* between the parts on purpose: a call may wrap, and one add-on screen
    // does exactly that (`rentivaProApi.reports\n\t.getSummary(`).
    if (preg_match_all('/' . preg_quote($symbol, '/') . '\s*\.\s*([A-Za-z_]\w*)\s*\.\s*([A-Za-z_]\w*)/', $body, $hits, PREG_SET_ORDER) < 1) {
        continue;
    }
    foreach ($hits as $hit) {
        $used[ $hit[1] ][ $hit[2] ][] = $file->getPathname();
    }
}

if (0 === $scanned) {
    fwrite(STDERR, "check-react-api-map: scanned ZERO source files under {$src_dir}.\n");
    exit(2);
}

// A map with members but no call sites means the scan did not reach the code it
// was aimed at -- a renamed symbol, a moved directory, a broken pattern. Reported
// as "cannot measure", not as clean: this is the shape in which a gate quietly
// stops watching and keeps printing green. Caught in this gate's own negative
// controls, where pointing MHM_API_SYMBOL at a name nobody uses returned 0.
if ($used === array()) {
    fwrite(STDERR, "check-react-api-map: the map defines {$defined} member(s) but NOTHING calls '{$symbol}'.\n");
    fwrite(STDERR, "  Scanned {$scanned} file(s) under {$src_dir}.\n");
    fwrite(STDERR, "  Either the exported symbol was renamed at the call sites, or the sources\n");
    fwrite(STDERR, "  moved. Refusing to report success on a comparison that had nothing to compare.\n");
    exit(2);
}

$missing = array();
$calls   = 0;

foreach ($used as $section => $members) {
    foreach ($members as $member => $files) {
        ++$calls;
        if (! isset($map[ $section ]) || ! in_array($member, $map[ $section ], true)) {
            $missing[] = array(
                'section' => $section,
                'member'  => $member,
                'file'    => $files[0],
                'known'   => isset($map[ $section ]),
            );
        }
    }
}

// Dead map entries are reported, never fatal: an unused endpoint is rot, not a
// break, and failing on it would tempt someone to delete a member a screen is
// about to need.
$unused = array();
foreach ($map as $section => $members) {
    foreach ($members as $member) {
        if (! isset($used[ $section ][ $member ])) {
            $unused[] = "{$section}.{$member}";
        }
    }
}

printf(
    "React API map gate\n  map      : %s\n  symbol   : %s\n  sources  : %d file(s) under %s\n  defined  : %d member(s) in %d section(s)\n  called   : %d distinct member(s)\n",
    $map_file,
    $symbol,
    $scanned,
    $src_dir,
    $defined,
    count($map),
    $calls
);

if ($unused !== array()) {
    printf("  unused   : %d (reported, not fatal): %s\n", count($unused), implode(', ', $unused));
}

if ($missing !== array()) {
    fwrite(STDERR, "\n[X] " . count($missing) . " called member(s) do not exist in the map.\n\n");
    foreach ($missing as $item) {
        fwrite(
            STDERR,
            sprintf(
                "  %s.%s%s\n      first seen: %s\n",
                $item['section'],
                $item['member'],
                $item['known'] ? '' : '   (the whole section is missing)',
                $item['file']
            )
        );
    }
    fwrite(STDERR, "\n  At runtime this is `Cannot read properties of undefined` on the screen's\n");
    fwrite(STDERR, "  first render. A screen without an error boundary dies outright.\n\n");
    exit(1);
}

echo "[OK] every called member exists in the map.\n";
exit(0);
