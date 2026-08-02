<?php
/**
 * Gate G-C: prefix inventory bijection + migration-map completeness check
 * (WP.org T7 remediation, Task 3).
 *
 * PrefixMigrationMap (src/Admin/Core/Utilities/PrefixMigrationMap.php) is the
 * single source of truth the later rename sweep (Görev 12) and the actual
 * DB migration (Görev 13) both read. This gate cannot itself fix a gap in
 * that map -- it can only refuse to go green while one exists. Six modes:
 *
 *   1. bijection    -- every exact-key family's NEW values are unique
 *                      (no two distinct OLD identifiers collapse onto one
 *                      NEW identifier within the same family).
 *   2. length       -- POST_TYPES new values <= 20 chars, TAXONOMIES <= 32
 *                      (WordPress' hard column limits).
 *   3. coverage     -- every name in bin/prefix-inventory-baseline.txt
 *                      matches some rule in some family; unmatched names
 *                      are listed and fail the gate (no silent skipping).
 *   4. post-sweep   -- (only meaningful AFTER Görev 12's rename sweep has
 *                      run) no OLD identifier from any family still appears
 *                      in src/ or templates/, except the two names in
 *                      PrefixMigrationMap::BOOTSTRAP_FALLBACK_ALLOWLIST.
 *                      Expected RED until that sweep lands.
 *   5. options-calls -- every option-shaped name (mhm_rentiva_ or bare mhm_)
 *                      actually passed to update_option()/get_option()/
 *                      add_option()/delete_option()/register_setting() has
 *                      an EXPLICIT key in PrefixMigrationMap::OPTIONS (or is
 *                      one of the two BOOTSTRAP_FALLBACK_ALLOWLIST names).
 *                      A name only reachable via RUNTIME_STRING_RULES is not
 *                      enough -- that would rename the code string without
 *                      migrating the stored value (Fable uyum denetimi
 *                      MAJOR-3, 2026-07-31).
 *   6. cron-hooks   -- every hook name scheduled via wp_schedule_event()/
 *                      wp_schedule_single_event() has an entry in
 *                      PrefixMigrationMap::CRON_HOOKS (Fable mimari denetimi
 *                      MAJOR-2, 2026-07-31).
 *
 * Usage:
 *   php bin/check-prefix-inventory.php                 # run all 6, report each
 *   php bin/check-prefix-inventory.php --mode=5         # run a single mode
 *   php bin/check-prefix-inventory.php --baseline=path  # override baseline file
 *
 * Exit code: number of modes that reported a failure (0 = all clean). Mode 4
 * is EXPECTED to fail until the Görev 12 sweep lands; the caller (a human,
 * or a later CI job once the sweep exists) is responsible for interpreting
 * that expected-red mode separately from the others.
 */

$root = dirname(__DIR__);

// PrefixMigrationMap.php carries the standard WP direct-access guard
// (`if (!defined('ABSPATH')) exit;`). This script runs as plain CLI PHP,
// not through a WP bootstrap, so a dummy ABSPATH satisfies the guard
// without needing to load WordPress itself.
if (! defined('ABSPATH')) {
    define('ABSPATH', $root . '/');
}
require_once $root . '/src/Admin/Core/Utilities/PrefixMigrationMap.php';

use MHMRentiva\Admin\Core\Utilities\PrefixMigrationMap as Map;

$opts = getopt('', ['mode::', 'baseline::']);
$onlyMode = isset($opts['mode']) ? (int) $opts['mode'] : null;
$baselinePath = $opts['baseline'] ?? ($root . '/bin/prefix-inventory-baseline.txt');

/** @var array<int, string> */
$results = [];
$failCount = 0;

function report(int $mode, string $label, array $violations, array &$results, int &$failCount): void
{
    $ok = empty($violations);
    printf("G-C mode %d [%s]: %s (%d violation%s)\n", $mode, $label, $ok ? 'PASS' : 'FAIL', count($violations), count($violations) === 1 ? '' : 's');
    foreach ($violations as $v) {
        echo "  - $v\n";
    }
    $results[$mode] = $ok ? 'PASS' : 'FAIL';
    if (! $ok) {
        $failCount++;
    }
}

/**
 * All "exact key => new value" families (as opposed to the ordered
 * substitution-rule families POSTMETA_PREFIX_RULES/USERMETA_PREFIX_RULES/
 * RUNTIME_STRING_RULES, where a repeated new PREFIX value is fine by
 * design -- see the class docblock).
 *
 * @return array<string, array<string,string>>
 */
function exactKeyFamilies(): array
{
    return [
        'POST_TYPES'  => Map::POST_TYPES,
        'TAXONOMIES'  => Map::TAXONOMIES,
        'OPTIONS'     => Map::OPTIONS,
        'TABLES'      => Map::TABLES,
        'CRON_HOOKS'  => Map::CRON_HOOKS,
        'COMMENTMETA' => Map::COMMENTMETA,
    ];
}

/**
 * Every OLD identifier/prefix across every family, used by mode 4 (post-sweep).
 *
 * @return array<int, string>
 */
function allOldIdentifiers(): array
{
    $old = [];
    foreach (exactKeyFamilies() as $family) {
        $old = array_merge($old, array_keys($family));
    }
    foreach ([Map::POSTMETA_PREFIX_RULES, Map::USERMETA_PREFIX_RULES, Map::RUNTIME_STRING_RULES] as $ruleset) {
        $old = array_merge($old, array_keys($ruleset));
    }
    return array_values(array_unique($old));
}

/**
 * Every rule/key across every family, used by mode 3 (coverage) as a flat
 * substring-match list. Coverage is a completeness check, not a precision
 * migration-correctness check, so substring matching against the union of
 * all families is the intentionally permissive design here.
 *
 * @return array<int, string>
 */
function allMatchableStrings(): array
{
    $all = allOldIdentifiers();

    // Post-Görev-12 the baseline is regenerated from the RENAMED tree, so its
    // entries are NEW names. Matching them against old keys alone fails every
    // correctly renamed entry -- 839 of them -- which measures the rename rather
    // than the coverage. Both spellings count as covered, and the question the
    // mode answers is stated on its own output line so nobody has to infer it.
    foreach (exactKeyFamilies() as $family) {
        $all = array_merge($all, array_values($family));
    }
    foreach ([Map::POSTMETA_PREFIX_RULES, Map::USERMETA_PREFIX_RULES, Map::RUNTIME_STRING_RULES] as $ruleset) {
        $all = array_merge($all, array_values($ruleset));
    }

    return array_values(array_unique(array_filter($all)));
}

/**
 * Extract the "core identifier" out of one raw baseline-file line, which may
 * be a bare unquoted token (wp_ajax_* grep line) or may carry a function-call
 * prefix before the first quote (transient grep line) or be a plain quoted
 * literal (the two option/generic-string grep lines).
 */
function normalizeBaselineLine(string $line): string
{
    $line = trim($line);
    if ($line === '') {
        return '';
    }
    $firstQuote = strpos($line, "'");
    if ($firstQuote === false) {
        return $line; // bare token, e.g. wp_ajax_mhm_rentiva_something
    }
    $rest = substr($line, $firstQuote + 1);
    $lastQuote = strrpos($rest, "'");
    if ($lastQuote === false) {
        return rtrim($rest, "'");
    }
    return substr($rest, 0, $lastQuote);
}

// ---------------------------------------------------------------------------
// Mode 1: bijection / uniqueness within each exact-key family.
// ---------------------------------------------------------------------------
function runMode1(): array
{
    $violations = [];
    foreach (exactKeyFamilies() as $familyName => $map) {
        $seen = [];
        foreach ($map as $old => $new) {
            if (isset($seen[$new])) {
                $violations[] = "$familyName: '$old' and '{$seen[$new]}' both map to '$new'";
            } else {
                $seen[$new] = $old;
            }
        }
    }
    return $violations;
}

// ---------------------------------------------------------------------------
// Mode 2: post_type <= 20 chars, taxonomy <= 32 chars.
// ---------------------------------------------------------------------------
function runMode2(): array
{
    $violations = [];
    foreach (Map::POST_TYPES as $old => $new) {
        if (strlen($new) > 20) {
            $violations[] = "POST_TYPES: '$new' is " . strlen($new) . " chars (> 20), old='$old'";
        }
    }
    foreach (Map::TAXONOMIES as $old => $new) {
        if (strlen($new) > 32) {
            $violations[] = "TAXONOMIES: '$new' is " . strlen($new) . " chars (> 32), old='$old'";
        }
    }
    return $violations;
}

/**
 * Is this baseline entry in scope for mode 3 at all? The brief scopes mode 3
 * to "her mhm-turevi ad" (every mhm-DERIVED name). Adim 1's second grep line
 * intentionally over-collects every 'booking_*'/'contact_*'/'addon_*'/
 * 'rentiva_*'-stemmed string in src/templates -- including bare (no leading
 * underscore) shortcode tags (rentiva_login, rentiva_search, ...; confirmed
 * DEGISMEYEN/unchanged by the T7 compliance audit's Section E), PHP array
 * keys for internal stats structures ($stats['booking']), Elementor widget
 * search-keyword hints (['mhm', 'rentiva']), and JS/l10n-only string keys
 * (booking_confirmed, contact_form_submission, ...) -- none of which are
 * WP-registered globals (option/hook/CPT/taxonomy/meta) this migration
 * mandate governs. Verified by hand for a sample: every one of those has no
 * leading underscore and no 'mhm' substring anywhere.
 *
 * An entry is in scope here if it is mhm-derived (contains 'mhm', case
 * insensitive) OR carries the leading underscore that signals the hidden
 * meta-key convention POSTMETA_PREFIX_RULES/USERMETA_PREFIX_RULES actually
 * target (_booking_, _contact_, _rentiva_, _mhm_, ...). Everything else is
 * out of this migration's scope and is skipped, not silently passed --
 * skipped entries are never counted toward the mode's PASS.
 */
function isInScopeForMode3(string $name): bool
{
    if ($name === '') {
        return false;
    }
    if (in_array($name, Map::BARE_TOKEN_EXCEPTIONS, true)) {
        return false;
    }
    // The post-rename bare token, for exactly the reason BARE_TOKEN_EXCEPTIONS
    // holds the pre-rename one: 'mhmrentiva' with no suffix is not an option,
    // hook, CPT or meta key this migration governs -- it is the plugin's own
    // token, appearing as an object-cache group name and as the SQL LIKE prefix
    // uninstall uses. It is deliberately NOT added to the matchable rule list
    // instead: 'mhmrentiva' is a substring of nearly every renamed name, so
    // making it matchable would let any string whatsoever satisfy this mode and
    // quietly turn it into a no-op.
    //
    // 🔴 It reaches the baseline because PrefixMigrationMap has no entry for the
    // BARE stem 'mhm_rentiva' (only 'mhm_rentiva_' and 'mhm_rentiva/'), which is
    // the same map gap that made the sweep produce 'mhmrentiva_rentiva%'. Worth
    // closing in the map itself; recorded here rather than silently absorbed.
    if ($name === 'mhmrentiva') {
        return false;
    }
    if (stripos($name, 'mhm') !== false) {
        return true;
    }
    return str_starts_with($name, '_');
}

// ---------------------------------------------------------------------------
// Mode 3: every baseline-inventory name matches some rule somewhere.
// ---------------------------------------------------------------------------
function runMode3(string $baselinePath): array
{
    if (! is_file($baselinePath)) {
        return ["baseline file not found: $baselinePath"];
    }
    $lines = file($baselinePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $matchable = allMatchableStrings();
    $violations = [];
    foreach ($lines as $line) {
        $name = normalizeBaselineLine($line);
        if ($name === '' || ! isInScopeForMode3($name)) {
            continue;
        }
        $matched = false;
        foreach ($matchable as $rule) {
            if ($rule !== '' && str_contains($name, $rule)) {
                $matched = true;
                break;
            }
        }
        if (! $matched) {
            $violations[] = "unmatched baseline entry: '$name' (raw line: '$line')";
        }
    }
    return $violations;
}

// ---------------------------------------------------------------------------
// Mode 4: post-sweep -- no OLD identifier should remain in src/ or templates/,
// except the two BOOTSTRAP_FALLBACK_ALLOWLIST names. Expected RED until
// Görev 12's sweep has actually run.
// ---------------------------------------------------------------------------
/**
 * Every OLD identifier paired with the NEW name it becomes.
 *
 * @return array<int, array{0:string,1:string}>
 */
function allOldNewPairs(): array
{
    $pairs = [];
    foreach (exactKeyFamilies() as $family) {
        foreach ($family as $old => $new) {
            $pairs[] = [$old, $new];
        }
    }
    foreach ([Map::POSTMETA_PREFIX_RULES, Map::USERMETA_PREFIX_RULES, Map::RUNTIME_STRING_RULES] as $ruleset) {
        foreach ($ruleset as $old => $new) {
            $pairs[] = [$old, $new];
        }
    }
    return $pairs;
}

/**
 * Literals that deliberately survive the sweep and must not be read as
 * unconverted leftovers.
 *
 * @return array<int, string>
 */
function deliberateSurvivors(): array
{
    // Read from the TOOL rather than restated here. Every carve-out is a literal
    // the sweep deliberately leaves in its old spelling, so every one of them is
    // also something this mode must not read as an unconverted leftover. Keeping
    // a second copy meant the two drifted the moment a carve-out was added -- the
    // Elementor widget names were carved out and mode 4 immediately reported them
    // as failures.
    require_once dirname(__DIR__) . '/bin/prefix-rename.php';

    return array_merge(
        Map::BOOTSTRAP_FALLBACK_ALLOWLIST,
        \MHMRentiva\Tools\PrefixRenamer::CARVE_OUT_TABLE_LITERALS
    );
}

/**
 * Mode 4, half A -- INDEPENDENT raw search, valid only for self-identifying
 * names.
 *
 * Every old identifier containing an 'mhm' token is unambiguous: no English
 * word, PHP identifier or third-party name in this tree contains it by
 * accident, so finding one is proof of an unconverted leftover. This half owes
 * nothing to the rename tool and would catch a sweep that simply missed files.
 *
 * Two corrections were needed before it could tell the truth at all:
 *   - Three rules have a NEW value CONTAINING their OLD key ('_booking_' ->
 *     '_mhmrentiva_booking_', '_contact_', 'addon_'). Searching raw for the old
 *     key matches the CORRECTLY renamed output, so this mode could never go
 *     green however perfect the sweep. The new value is masked out first.
 *   - Files that hold old names as DATA (PrefixMigrationMap.php above all) are
 *     excluded. The map is the record of the old names; it is supposed to
 *     contain them, and reading it as a leftover made the gate permanently red.
 *
 * @param string $root Plugin root.
 * @return array<int,string>
 */
function runMode4a(string $root): array
{
    $violations = [];
    $contents   = mode4Sources($root);
    $survivors  = deliberateSurvivors();

    foreach (allOldNewPairs() as [$old, $new]) {
        if ($old === '' || stripos($old, 'mhm') === false) {
            continue; // half B's territory.
        }
        if (in_array($old, Map::BOOTSTRAP_FALLBACK_ALLOWLIST, true)) {
            continue;
        }
        $hits = [];
        foreach ($contents as $file => $src) {
            $masked = $src;
            if ($new !== '' && $new !== $old && str_contains($new, $old)) {
                $masked = str_replace($new, "\x00", $masked);
            }
            foreach ($survivors as $survivor) {
                $masked = str_replace($survivor, "\x00", $masked);
            }
            if (str_contains($masked, $old)) {
                $hits[] = substr($file, strlen($root) + 1);
            }
        }
        if (! empty($hits)) {
            $violations[] = "old identifier '$old' still present in: " . implode(', ', array_slice($hits, 0, 3)) . (count($hits) > 3 ? ' (+' . (count($hits) - 3) . ' more)' : '');
        }
    }
    return $violations;
}

/**
 * Mode 4, half B -- residual check for the names a raw search CANNOT test.
 *
 * The bare CPT/taxonomy tokens ('vehicle', 'addon_context', ...) and the four
 * non-mhm meta prefixes ('_booking_', 'addon_', ...) are ordinary English and
 * ordinary PHP. A raw substring search for 'vehicle' matches $vehicle_id, the
 * word "vehicle" in a comment, and VehicleListingAdapter -- so half A cannot
 * express the question. This half asks the rename tool whether it would still
 * change anything, which is precisely "is any unconverted old name left?".
 *
 * 🔴 LIMITATION, STATED RATHER THAN HIDDEN: this half shares its definition of
 * "an old name" with the tool it is checking, so it proves the sweep is
 * complete BY ITS OWN RULES -- not that the rules are right. A name neither the
 * map nor the tool knows about is invisible to both. Half A, mode 3 and mode 5
 * are the independent checks; this one is a consistency check only.
 *
 * @param string $root Plugin root.
 * @return array<int,string>
 */
function runMode4b(string $root): array
{
    $renamerPath = $root . '/bin/prefix-rename.php';
    if (! is_file($renamerPath)) {
        return ['rename tool not found at bin/prefix-rename.php -- cannot verify residual coverage'];
    }
    require_once $renamerPath;

    $violations = [];
    foreach (mode4Sources($root) as $file => $src) {
        $renamer = new \MHMRentiva\Tools\PrefixRenamer();
        [$out]   = $renamer->transform($src, $file);
        if ($out !== $src) {
            $violations[] = 'rename tool would still change ' . substr($file, strlen($root) + 1);
        }
    }
    return $violations;
}

/**
 * The files mode 4 judges: src/ + templates/ PHP, minus the files that carry
 * old names as data by design.
 *
 * @param string $root Plugin root.
 * @return array<string,string> path => contents
 */
function mode4Sources(string $root): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    // 🔴 MUST MATCH bin/prefix-rename.php's target set, file for file.
    //
    // This used to be `src/ + templates/ *.php` while the sweep also rewrote
    // assets/js, src-react, tests, bin and four root files -- so roughly HALF the
    // swept surface was uncertified, and the gate said PASS about code it had
    // never opened. It cost three silently dead admin scripts (a booking-list
    // filter whose screen guard still named the old post type, a bulk-action
    // handler bound to the old $typenow, and a category filter the user could not
    // clear) and it is also why the 'mhmrentiva_rentiva%' corruption of
    // uninstall.php and phpcs.xml had to be found by hand.
    //
    // Any future addition to the tool's target set belongs here in the same
    // commit; the two lists are one decision written twice.
    $files = [];
    exec(
        'find '
        . escapeshellarg($root . '/src') . ' '
        . escapeshellarg($root . '/templates') . ' '
        . escapeshellarg($root . '/assets') . ' '
        . escapeshellarg($root . '/src-react') . ' '
        . escapeshellarg($root . '/tests') . ' '
        . escapeshellarg($root . '/bin')
        . ' -type f \( -name "*.php" -o -name "*.js" -o -name "*.jsx" -o -name "*.json" -o -name "*.css" -o -name "*.xml" -o -name "*.txt" \)'
        . ' -not -path "*/node_modules/*" -not -path "*/vendor/*" -not -path "*/build/*"',
        $files
    );
    foreach (['mhm-rentiva.php', 'uninstall.php', 'phpcs.xml', 'phpstan.neon', 'readme.txt'] as $rootFile) {
        if (is_file($root . '/' . $rootFile)) {
            $files[] = $root . '/' . $rootFile;
        }
    }

    // Must mirror PrefixRenamer::NEVER_SWEEP: these files carry OLD names as
    // DATA, so finding one in them is not evidence of an unconverted leftover.
    $excluded = [
        $root . '/src/Admin/Core/Utilities/PrefixMigrationMap.php',
        $root . '/bin/prefix-rename.php',
        $root . '/bin/prefix-inventory-baseline.txt',
        $root . '/bin/check-prefix-inventory.php',
        $root . '/tests/Tools/PrefixRenamerTest.php',
        $root . '/tests/Tools/NoBareMhmStorageKeysTest.php',
        $root . '/tests/Unit/Core/Utilities/DatabaseCleanerAllowlistTest.php',
        $root . '/tests/Unit/Utilities/UninstallForeignPostSafetyTest.php',
        // Görev 13's migration test: every fixture is an OLD name by design and
        // every assertion reads the new one. Added to PrefixRenamer::NEVER_SWEEP
        // in the same commit -- the two lists are one decision written twice.
        $root . '/tests/Migration/PrefixRenameMigrationTest.php',
    ];

    $cache = [];
    foreach ($files as $f) {
        if (in_array($f, $excluded, true)) {
            continue;
        }
        $src = file_get_contents($f);
        if ($src !== false) {
            $cache[$f] = stripIgnoredRegions($src);
        }
    }
    return $cache;
}

/**
 * Blank out `prefix-rename:ignore-start/end` regions.
 *
 * The gate and the rename tool have to read the SAME annotation, or the marker
 * only half works: the tool would leave a deliberate pre-6.0.0 spelling alone
 * and mode 4a would then report that very literal as an unconverted leftover,
 * making the gate permanently red for doing the right thing. Blanking rather
 * than deleting keeps byte offsets meaningful for anything that reports them.
 *
 * A region is a claim that a human decided this literal must stay -- it is not
 * a way to hide work, because every one of them is ALSO listed with its reason
 * in NoBareMhmStorageKeysTest::INVENTORY, which fails if an entry stops
 * matching the source.
 *
 * @param string $src Source text.
 * @return string Source with marked regions blanked.
 */
function stripIgnoredRegions(string $src): string
{
    $start = 'prefix-rename:ignore-start';
    $end   = 'prefix-rename:ignore-end';

    $out    = '';
    $offset = 0;
    while (true) {
        $s = strpos($src, $start, $offset);
        if ($s === false) {
            $out .= substr($src, $offset);
            break;
        }
        $out .= substr($src, $offset, $s - $offset);
        $e = strpos($src, $end, $s);
        if ($e === false) {
            break; // unterminated: drop the remainder, matching the tool.
        }
        $offset = $e + strlen($end);
    }
    return $out;
}

function runMode4(string $root): array
{
    return array_merge(runMode4a($root), runMode4b($root));
}

// ---------------------------------------------------------------------------
// Mode 5: every option-shaped name actually reaching update_option()/
// get_option()/add_option()/delete_option()/register_setting() has an
// explicit OPTIONS key (or is bootstrap-allowlisted).
// ---------------------------------------------------------------------------
function extractOptionCandidatesFromFile(string $file): array
{
    $src = file_get_contents($file);
    if ($src === false) {
        return [];
    }
    $names = [];

    // (a) direct literal first-arg calls.
    if (preg_match_all("/(?:update_option|get_option|add_option|delete_option)\\(\\s*'((?:mhm_rentiva_|mhmrentiva_|mhm_)[a-z0-9_]*)'/", $src, $m)) {
        $names = array_merge($names, $m[1]);
    }
    // (b) register_setting() 2nd arg (the actual option name).
    if (preg_match_all("/register_setting\\(\\s*'[^']*'\\s*,\\s*'((?:mhm_rentiva_|mhmrentiva_|mhm_)[a-z0-9_]*)'/", $src, $m)) {
        $names = array_merge($names, $m[1]);
    }
    // (c) register_setting() 1st arg (settings group -- often reused as the
    // option name in this codebase, e.g. AddonSettings::PAGE).
    if (preg_match_all("/register_setting\\(\\s*'((?:mhm_rentiva_|mhmrentiva_|mhm_)[a-z0-9_]*)'/", $src, $m)) {
        $names = array_merge($names, $m[1]);
    }
    // (d) `$var = '...';`-style variable assignment that ACTUALLY flows into
    // update_option($var...)/get_option($var...)/add_option($var...)/
    // delete_option($var...) under that same variable name in the same file
    // (Templates.php's getSubjectOverride()/getBodyOverride() $opt switch,
    // WooCommerceIntegration::maybe_flush_rewrite_rules()'s $version_key/
    // $hash_key). Deliberately requires the SAME name to reach one of the
    // four option functions -- a looser "file merely contains get_option("
    // co-occurrence check produced false positives for variables that are
    // actually wp_cache_get()/set_transient() cache keys (BookingColumns::
    // get_booking_stats()'s $cache_key, ShortcodeUrlManager's $transient_key,
    // Mailer's $cache_key) or that are only used for array-key lookup into an
    // ALREADY-fetched settings array rather than a fresh get_option() call
    // (EndpointHelperTrait's $option_key reads $settings[$option_key], it
    // never calls get_option($option_key) itself -- that key lives inside
    // the already-covered 'mhm_rentiva_settings' option's array value).
    if (preg_match_all("/\\\$([A-Za-z_][A-Za-z0-9_]*)\\s*=\\s*'((?:mhm_rentiva_|mhmrentiva_|mhm_)[a-z0-9_]*)'/", $src, $m, PREG_SET_ORDER)) {
        foreach ($m as $varMatch) {
            [, $varName, $varValue] = $varMatch;
            if (preg_match("/(?:update_option|get_option|add_option|delete_option)\\(\\s*\\\${$varName}\\b/", $src)) {
                $names[] = $varValue;
            }
        }
    }
    // (e) field-definition array shape: 'name' => 'checkbox'|'text'|'email'|'html'
    // (EmailTemplates::save_email_fields() consumers).
    if (preg_match_all("/'((?:mhm_rentiva_|mhmrentiva_|mhm_)[a-z0-9_]*)'\\s*=>\\s*'(?:checkbox|text|email|html)'/", $src, $m)) {
        $names = array_merge($names, $m[1]);
    }
    // (f) class constants assigned an option-shaped literal, IF the same file
    // also calls one of the five functions with self::THAT_CONST.
    if (preg_match_all("/const\\s+([A-Z_][A-Z0-9_]*)\\s*=\\s*'((?:mhm_rentiva_|mhmrentiva_|mhm_)[a-z0-9_]*)'/", $src, $m, PREG_SET_ORDER)) {
        foreach ($m as $constMatch) {
            [, $constName, $constValue] = $constMatch;
            if (preg_match("/(?:update_option|get_option|add_option|delete_option|register_setting)\\(\\s*self::{$constName}\\b/", $src)) {
                $names[] = $constValue;
            }
        }
    }
    // (g) foreach-over-array-literal: `$arr = array(...)` (or `[...]`) whose
    // elements are option-shaped string literals, consumed via
    // `foreach ($arr as $k => $v) { ...option_fn($k or $v...); }`. Added in
    // fix round 1 (2026-08-01) after an independent reviewer's re-trace
    // found two real misses this shape produced: DatabaseMigrator::
    // migrate_standalone_settings()'s `$standalone_mapping = array('mhm_transfer_deposit_type'
    // => 'rentiva_transfer_deposit_type', ...); foreach ($standalone_mapping as $old_key => $new_key)
    // { get_option($old_key, ...); }` (KEYS are the option names) and
    // SettingsService::reset_to_defaults_by_tab()'s `$legacy_keys = array('mhm_rentiva_sender_name',
    // ...); foreach ($legacy_keys as $key) { delete_option($key); }` (each
    // plain VALUE is an option name).
    $names = array_merge($names, extractForeachArrayLiteralOptionCandidates($src));

    return array_values(array_unique($names));
}

/**
 * Best-effort, single-file, non-nested-array detector for shape (g). KNOWN
 * LIMITATIONS (documented here rather than silently -- a narrower shape this
 * cannot catch is a residual risk, not a solved problem):
 *   - Only matches a FLAT array literal (`array(...)` or `[...]`) with no
 *     nested array/parenthesis inside it; a nested structure would break the
 *     "first closing `)`/`]`" boundary this uses and either truncate or miss
 *     entries entirely.
 *   - Only resolves the loop variable(s) declared on the SAME `foreach` that
 *     iterates the matched array variable, and only within the same file;
 *     it does not follow the variable across function boundaries or
 *     require-once'd files.
 *   - Requires the option-function call to use the loop variable's exact
 *     name literally (e.g. `get_option($old_key`) -- an intermediate
 *     reassignment (`$x = $old_key; get_option($x)`) is not traced.
 * These limitations mirror shape (d)'s scalar-variable tracer above; both
 * are grep/regex-based by design (matching this gate's existing style), not
 * a full PHP parse. If a future array-driven option cleanup uses a shape
 * outside what's described above, mode 5 will not see it -- the same class
 * of gap this fix round closed for the two cases above, not a guarantee
 * against every possible future case.
 */
function extractForeachArrayLiteralOptionCandidates(string $src): array
{
    $names = [];
    $optionFnPattern = '(?:update_option|get_option|add_option|delete_option)';

    // Find every `$var = array(` or `$var = [` ... up to its first closing
    // `)`/`]` (flat-array assumption -- see limitations above).
    if (! preg_match_all(
        "/\\\$([A-Za-z_][A-Za-z0-9_]*)\\s*=\\s*(?:array\\(|\\[)([^()\\[\\]]*)(?:\\)|\\])/",
        $src,
        $arrMatches,
        PREG_SET_ORDER
    )) {
        return $names;
    }

    foreach ($arrMatches as $arrMatch) {
        [, $arrVarName, $arrBody] = $arrMatch;

        // Does a foreach over this exact array variable exist?
        if (! preg_match(
            "/foreach\\s*\\(\\s*\\\${$arrVarName}\\s+as\\s+\\\$([A-Za-z_][A-Za-z0-9_]*)(?:\\s*=>\\s*\\\$([A-Za-z_][A-Za-z0-9_]*))?\\s*\\)/",
            $src,
            $foreachMatch
        )) {
            continue;
        }
        // `foreach ($arr as $x)` -> $keyVar is null, $soleVar = $x (iterates VALUES).
        // `foreach ($arr as $k => $v)` -> $keyVar = $k, $soleVar = $v (iterates
        // key=>value pairs; PHP's own semantics, not this script's choice).
        $hasArrow = isset($foreachMatch[2]) && $foreachMatch[2] !== '';
        $keyVar   = $hasArrow ? $foreachMatch[1] : null;
        $soleVar  = $hasArrow ? $foreachMatch[2] : $foreachMatch[1];

        $keyVarIsUsed  = $keyVar !== null && (bool) preg_match("/{$optionFnPattern}\\(\\s*\\\${$keyVar}\\b/", $src);
        $soleVarIsUsed = (bool) preg_match("/{$optionFnPattern}\\(\\s*\\\${$soleVar}\\b/", $src);
        if (! $keyVarIsUsed && ! $soleVarIsUsed) {
            continue;
        }

        if ($keyVarIsUsed) {
            // The KEY side of 'key' => 'value' pairs is what reaches the
            // option function (DatabaseMigrator::migrate_standalone_settings()'s
            // `foreach ($standalone_mapping as $old_key => $new_key) { get_option($old_key...) }`).
            if (preg_match_all("/'((?:mhm_rentiva_|mhmrentiva_|mhm_)[a-z0-9_]*)'\\s*=>/", $arrBody, $keyMatches)) {
                $names = array_merge($names, $keyMatches[1]);
            }
        }
        if ($soleVarIsUsed) {
            // Either a plain-list array (`'x', 'y', 'z'`, no `=>` at all --
            // SettingsService::reset_to_defaults_by_tab()'s `$legacy_keys`)
            // where every item is a candidate, or a key=>value array whose
            // VALUE side (not key side) is the one actually used.
            if (preg_match_all("/=>\\s*'((?:mhm_rentiva_|mhmrentiva_|mhm_)[a-z0-9_]*)'/", $arrBody, $valMatches)) {
                $names = array_merge($names, $valMatches[1]);
            }
            if (! str_contains($arrBody, '=>')) {
                if (preg_match_all("/'((?:mhm_rentiva_|mhmrentiva_|mhm_)[a-z0-9_]*)'/", $arrBody, $plainMatches)) {
                    $names = array_merge($names, $plainMatches[1]);
                }
            }
        }
    }

    return $names;
}

function runMode5(string $root): array
{
    $violations = [];
    // BOTH spellings of each bootstrap-fallback name. The map exempts these two
    // options from OPTIONS by design -- they have their own bootstrap path and
    // are read before a map-driven pass could run -- but Görev 13's fallback
    // reads the NEW name first and only then falls back to the old one, so the
    // tree now contains both. Exempting only the old spelling made this mode
    // flag the very rename it exists to certify. The new spelling is DERIVED
    // from the map's own rules rather than typed here, so the exemption cannot
    // drift wider than the two names the map actually names.
    $allowlist = Map::BOOTSTRAP_FALLBACK_ALLOWLIST;
    foreach (Map::BOOTSTRAP_FALLBACK_ALLOWLIST as $bootstrapName) {
        foreach (Map::RUNTIME_STRING_RULES as $oldPrefix => $newPrefix) {
            if (str_starts_with($bootstrapName, $oldPrefix)) {
                $allowlist[] = $newPrefix . substr($bootstrapName, strlen($oldPrefix));
                break;
            }
        }
    }
    $allowlist = array_values(array_unique($allowlist));
    $options = Map::OPTIONS;

    $files = [];
    exec('grep -rlE ' . escapeshellarg("(update_option|get_option|add_option|delete_option|register_setting)\\(") . ' ' . escapeshellarg($root . '/src') . ' --include=*.php', $files);
    // Root-level PHP files (mhm-rentiva.php, uninstall.php) also matter.
    foreach (glob($root . '/*.php') as $rootFile) {
        $files[] = $rootFile;
    }

    $allFound = [];
    foreach (array_unique($files) as $file) {
        $allFound = array_merge($allFound, extractOptionCandidatesFromFile($file));
    }
    $allFound = array_values(array_unique($allFound));

    foreach ($allFound as $name) {
        if (in_array($name, $allowlist, true)) {
            continue;
        }
        // Accept the name as an OLD key or as the NEW value it maps to. Before
        // the Görev 12 sweep the tree holds old keys; after it, the tree holds
        // new values -- and this mode's detector now sees both prefixes, so
        // without the value side it would flag every correctly renamed option.
        // The question is unchanged: is this option-shaped name accounted for
        // in the migration map at all?
        if (array_key_exists($name, $options) || in_array($name, $options, true)) {
            continue;
        }
        // A captured value ending in '_' is a dynamic-prefix fragment (the
        // regex only captures the static portion before a `.` string
        // concatenation, e.g. 'mhm_vehicle_' . $grid_type). If that prefix
        // is already a strict prefix of at least one explicit OPTIONS key,
        // every concrete value this dynamic key can take at runtime is
        // already enumerated (e.g. 'mhm_vehicle_' covers mhm_vehicle_details/
        // features/equipment/settings, all already in OPTIONS) -- so this is
        // not a gap, it is the same family already covered.
        if (str_ends_with($name, '_')) {
            foreach (array_merge(array_keys($options), array_values($options)) as $existing) {
                if (str_starts_with($existing, $name)) {
                    continue 2;
                }
            }
        }
        $violations[] = "option '$name' is read/written via an option function but has no explicit PrefixMigrationMap::OPTIONS entry (neither old key nor new value)";
    }
    return $violations;
}

// ---------------------------------------------------------------------------
// Mode 6: every wp_schedule_event()/wp_schedule_single_event()-driven hook
// name has a CRON_HOOKS entry.
// ---------------------------------------------------------------------------
function extractCronHookCandidatesFromFile(string $file): array
{
    $src = file_get_contents($file);
    if ($src === false) {
        return [];
    }
    $names = [];
    // Direct literal hook name as 3rd arg of wp_schedule_event() or 2nd arg
    // of wp_schedule_single_event().
    if (preg_match_all("/wp_schedule_event\\([^,]+,\\s*[^,]+,\\s*'((?:mhm_rentiva_|mhmrentiva_|mhm_)[a-z0-9_]*)'/", $src, $m)) {
        $names = array_merge($names, $m[1]);
    }
    if (preg_match_all("/wp_schedule_single_event\\([^,]+,\\s*'((?:mhm_rentiva_|mhmrentiva_|mhm_)[a-z0-9_]*)'/", $src, $m)) {
        $names = array_merge($names, $m[1]);
    }
    // self::CONST resolution: wp_schedule_event(..., self::EVENT) where
    // `const EVENT = '...'` lives in the same file.
    if (preg_match_all("/wp_schedule_(?:single_)?event\\([^;]*self::([A-Z_][A-Z0-9_]*)/", $src, $m)) {
        foreach ($m[1] as $constName) {
            if (preg_match("/const\\s+{$constName}\\s*=\\s*'((?:mhm_rentiva_|mhmrentiva_|mhm_)[a-z0-9_]*)'/", $src, $cm)) {
                $names[] = $cm[1];
            }
        }
    }
    return array_values(array_unique($names));
}

function runMode6(string $root): array
{
    $violations = [];
    $cronHooks = Map::CRON_HOOKS;

    $files = [];
    exec('grep -rlE ' . escapeshellarg('wp_schedule_(single_)?event\\(') . ' ' . escapeshellarg($root . '/src') . ' --include=*.php', $files);

    $allFound = [];
    foreach ($files as $file) {
        $allFound = array_merge($allFound, extractCronHookCandidatesFromFile($file));
    }
    $allFound = array_values(array_unique($allFound));

    foreach ($allFound as $name) {
        // Old key or new value -- same reasoning as mode 5.
        if (! array_key_exists($name, $cronHooks) && ! in_array($name, $cronHooks, true)) {
            $violations[] = "cron hook '$name' is scheduled via wp_schedule_event()/wp_schedule_single_event() but has no PrefixMigrationMap::CRON_HOOKS entry (neither old key nor new value)";
        }
    }
    return $violations;
}

// ---------------------------------------------------------------------------
// Dispatch.
// ---------------------------------------------------------------------------
$modes = [
    1 => ['bijection', fn() => runMode1()],
    2 => ['length-limits', fn() => runMode2()],
    // The label states WHICH question this mode answers, because regenerating
    // the baseline after the sweep changed it. Before Görev 12 it asked "is
    // every OLD name in the map?"; now the baseline is extracted from the
    // renamed tree, so it asks "is every name in the tree one the map knows
    // about, in either spelling?". Both are valid; they are not the same.
    3 => ['baseline-coverage (does the map recognise every inventoried name, old spelling or new?)', fn() => runMode3($baselinePath)],
    4 => ['post-sweep-no-old-names', fn() => runMode4($root)],
    5 => ['options-call-coverage', fn() => runMode5($root)],
    6 => ['cron-hook-coverage', fn() => runMode6($root)],
];

foreach ($modes as $num => [$label, $runner]) {
    if ($onlyMode !== null && $onlyMode !== $num) {
        continue;
    }
    report($num, $label, $runner(), $results, $failCount);
}

exit($failCount);
