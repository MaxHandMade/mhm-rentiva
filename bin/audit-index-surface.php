<?php
/**
 * Audit: the plugin's core-table index creation/drop surface.
 *
 * WHAT THIS IS FOR
 * -----------------
 * Görev 1 of the 2026-08-03 T8 remediation round deletes the plugin's
 * core-table index-management subsystem in DatabaseMigrator.php
 * (add_performance_indexes() / add_missing_indexes() / rebuild_indexes() /
 * the index half of rollback_migration() -- the independent Fable audit for
 * this round recommends REMOVE 20/20 on exactly that surface). This tool is
 * the before/after instrument for that deletion. Run before Görev 1, its
 * numbers are the baseline; run after, both should read at or near zero. If
 * this tool cannot see an index creator, the deletion cannot be proven --
 * that is why its own blind spots are declared below rather than hidden.
 *
 * TWO HALVES
 * ----------
 *  (a) SOURCE SCAN (pure filesystem, no Docker needed). Tokenizes every
 *      .php file under the scanned roots with token_get_all() and looks for
 *      $wpdb->query(...) calls. A call whose full argument text contains the
 *      literal substring "CREATE INDEX" (case-insensitive) counts as a
 *      creator; one containing "DROP INDEX" counts as a dropper.
 *      token_get_all() is used instead of a line-oriented regex because at
 *      least one current call site (DatabaseMigrator::create_key_registry_table())
 *      wraps its statement in a multi-line $wpdb->prepare(...) call, and
 *      because the tokenizer already knows that parentheses and keywords
 *      *inside* a quoted SQL string are not PHP syntax -- a hand-rolled
 *      paren-counter would have to reimplement that distinction itself and
 *      would be one embedded "(" away from miscounting.
 *  (b) LIVE DB SCAN (needs Docker). Shells into the wp-cli container --
 *      same host-orchestrator pattern as bin/check-plugin-check-parity.php,
 *      which recorded for G-D at final-zip-report.md:890: "G-D must be run
 *      from the host, not inside rentiva-dev-wpcli-1: it shells out to
 *      docker exec itself" -- and reads information_schema.STATISTICS for
 *      wp_posts / wp_postmeta / wp_usermeta. An index there is counted as
 *      plugin-owned when its name starts with "idx_": every WordPress-core
 *      index on these three tables was checked against this run's own live
 *      data (PRIMARY, post_id, meta_key, post_name, post_author,
 *      post_parent, type_status_author, type_status_date, user_id -- none
 *      start with "idx_"), so the heuristic needs no hardcoded name list
 *      and still counts correctly for orphaned legacy names the current
 *      prefix-rename:ignore-start -- legacy name mentioned as DATA in this sentence, not a live identifier for the sweep to rename.
 *      source no longer creates (the pre-rename idx_mhm_* set living
 *      prefix-rename:ignore-end
 *      alongside today's idx_mhmrentiva_* set is exactly such a case).
 *
 * THIS IS A REPORT, NOT A GATE
 * -----------------------------
 * There is no pass/fail threshold and no findings-based exit code. It
 * always exits 0 once it has printed a summary line -- including when the
 * live half could not be measured (live_owned=NA). Printing 0 for an
 * unmeasured value would be a silent false green; NA says plainly that the
 * live half did not run. The only non-zero exit is EXIT_ERROR, reserved for
 * an invocation that cannot even attempt the source half (see below).
 *
 * BLIND SPOTS -- printed at the top of every run, not just written here:
 *  - dynamic index-name generation (name assembled at runtime from a
 *    variable, not present as a literal in the SQL text the tokenizer sees)
 *  - dbDelta()-driven schema (only $wpdb->query() call sites are scanned;
 *    a CREATE TABLE string handed to dbDelta(), including inline KEY/INDEX
 *    clauses, is invisible to this tool by design -- dbDelta is not query())
 *  - SQL assembled by string concatenation across several statements or
 *    helper calls before it ever reaches a single $wpdb->query() argument
 *  - the Pro-edition codebase (a different repository; this tool only reads
 *    the plugin tree it is run from)
 *  - vendor/ dependency code, and the bin/ | tests/ | build/ | node_modules/
 *    | carveout/ | languages/ trees (out of the scanned roots; see "scanned
 *    roots" in the printed output)
 *  - phrasing: only the literal phrases "CREATE INDEX" and "DROP INDEX" are
 *    matched. "ALTER TABLE ... ADD INDEX" / "ADD UNIQUE INDEX" / "ADD KEY"
 *    (an equally valid way to create an index) is NOT matched by the
 *    creator side. "ALTER TABLE ... DROP INDEX" IS incidentally matched by
 *    the dropper side, only because that literal phrase happens to appear
 *    inside it -- this is a real asymmetry in MySQL's own grammar (DROP
 *    reuses the word "INDEX" after ALTER TABLE; ADD does not use "CREATE"),
 *    not a design choice, and it is why per-hit table labels are printed:
 *    so a reader can see which hits are the standalone-statement shape and
 *    which are ALTER-TABLE-phrased.
 *  - the per-hit "table" label (below) is a literal-text match against the
 *    same captured argument buffer, not data-flow analysis -- a table name
 *    held in a variable that is itself assigned from $wpdb->postmeta a few
 *    lines away would not be recognised, and would print as "other".
 *
 * WHERE IT RUNS
 *   From the host, in the plugin root (`php bin/audit-index-surface.php`).
 *   Override the live-DB container with MHM_AIS_CONTAINER if it differs
 *   from the Docker WP stack's default.
 */

$root = dirname(__DIR__);
chdir($root);

const EXIT_OK    = 0;
const EXIT_ERROR = 1;

/**
 * Run a command without going through a shell.
 *
 * Same reason as bin/check-plugin-check-parity.php and bin/check-shape-zero.php:
 * an ARRAY argv to proc_open() bypasses the shell on both POSIX and Windows,
 * so nothing here depends on quoting rules -- this project is developed on a
 * Windows host, where a hardcoded shell string (e.g. one ending in
 * `2>/dev/null`) simply fails to launch.
 *
 * @param string[] $argv Command and arguments.
 * @return array{0:int,1:string,2:string} [exit code, stdout, stderr]
 */
function run_cmd(array $argv): array
{
    $spec = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
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

// ---------------------------------------------------------------------------
// (a) Source scan.
// ---------------------------------------------------------------------------

/**
 * Recursively list .php files under a root-relative path.
 *
 * @return list<string> Paths relative to the plugin root, forward-slashed.
 */
function list_php_files(string $rootDir, string $relPath): array
{
    $abs = $rootDir . '/' . $relPath;
    if (is_file($abs)) {
        return substr($abs, -4) === '.php' ? [$relPath] : [];
    }
    if (!is_dir($abs)) {
        return [];
    }
    $entries = @scandir($abs);
    if ($entries === false) {
        return [];
    }
    sort($entries);
    $out = [];
    foreach ($entries as $e) {
        if ($e === '.' || $e === '..') {
            continue;
        }
        $childRel = $relPath === '' ? $e : $relPath . '/' . $e;
        if (is_dir($abs . '/' . $e)) {
            $out = array_merge($out, list_php_files($rootDir, $childRel));
        } elseif (substr($e, -4) === '.php') {
            $out[] = $childRel;
        }
    }
    return $out;
}

/**
 * Text of one token, whether it is the array form [id, text, line] or a
 * single-character punctuation token (a plain string).
 *
 * @param array{0:int,1:string,2:int}|string $tok
 */
function tok_text($tok): string
{
    return is_array($tok) ? $tok[1] : $tok;
}

/**
 * Index of the next non-trivia token at or after $i (skips whitespace and
 * comments, which PHP is free to insert between any two meaningful tokens).
 *
 * @param list<array{0:int,1:string,2:int}|string> $tokens
 */
function skip_trivia(array $tokens, int $i): int
{
    $n = count($tokens);
    while ($i < $n) {
        $t = $tokens[$i];
        if (is_array($t) && ($t[0] === T_WHITESPACE || $t[0] === T_COMMENT || $t[0] === T_DOC_COMMENT)) {
            $i++;
            continue;
        }
        break;
    }
    return $i;
}

/**
 * Best-effort table label for one $wpdb->query() call's captured argument
 * text. Recognises the three core-table properties this codebase's index
 * statements use, however the table name reaches the SQL -- interpolated
 * directly ("... ON {$wpdb->postmeta} ...") or passed as a separate
 * $wpdb->prepare() %i argument (both forms are present in the buffer,
 * because it is captured from the FULL call including prepare()).
 * Anything else (the plugin's own custom tables, e.g. mhmrentiva_key_registry)
 * is labelled 'other'. See the header's "table label" blind-spot note.
 */
function guess_table_label(string $buf): string
{
    foreach (['postmeta', 'usermeta', 'posts'] as $prop) {
        if (str_contains($buf, '$wpdb->' . $prop)) {
            return 'wp_' . $prop;
        }
    }
    return 'other';
}

/**
 * Find every $wpdb->query(...) call in one file's token stream and classify
 * its argument text.
 *
 * @return list<array{line:int,kind:string,table:string}> kind is 'creator'
 *         or 'dropper'; a call is reported once per kind it matches (never
 *         both in this codebase today, but the two checks are independent).
 */
function scan_file_for_wpdb_query(string $absPath): array
{
    $src = file_get_contents($absPath);
    if ($src === false) {
        fwrite(STDERR, "  warning: could not read $absPath, skipped\n");
        return [];
    }

    $tokens = token_get_all($src);
    $n      = count($tokens);
    $hits   = [];

    for ($i = 0; $i < $n; $i++) {
        $tok = $tokens[$i];
        if (!(is_array($tok) && $tok[0] === T_VARIABLE && $tok[1] === '$wpdb')) {
            continue;
        }
        $callLine = $tok[2];

        // Expect: T_VARIABLE '$wpdb'  T_OBJECT_OPERATOR '->'  T_STRING 'query'  '('
        $j = skip_trivia($tokens, $i + 1);
        if (!isset($tokens[$j]) || !(is_array($tokens[$j]) && $tokens[$j][0] === T_OBJECT_OPERATOR)) {
            continue;
        }
        $j = skip_trivia($tokens, $j + 1);
        if (!isset($tokens[$j]) || !(is_array($tokens[$j]) && $tokens[$j][0] === T_STRING && $tokens[$j][1] === 'query')) {
            continue;
        }
        $j = skip_trivia($tokens, $j + 1);
        if (!isset($tokens[$j]) || tok_text($tokens[$j]) !== '(') {
            continue;
        }

        // Collect the balanced argument-list text (paren depth tracked only
        // against real '(' / ')' punctuation tokens -- parentheses that are
        // part of a string literal's own text, e.g. "meta_key(50)", arrive
        // as one multi-character string token and never equal '(' or ')').
        $depth = 0;
        $buf   = '';
        for (; $j < $n; $j++) {
            $text = tok_text($tokens[$j]);
            if ($text === '(') {
                $depth++;
                if ($depth === 1) {
                    continue; // opening paren of the query() call itself
                }
            } elseif ($text === ')') {
                $depth--;
                if ($depth === 0) {
                    break;
                }
            }
            $buf .= $text;
        }

        $upper = strtoupper($buf);
        $table = guess_table_label($buf);
        if (str_contains($upper, 'CREATE INDEX')) {
            $hits[] = ['line' => $callLine, 'kind' => 'creator', 'table' => $table];
        }
        if (str_contains($upper, 'DROP INDEX')) {
            $hits[] = ['line' => $callLine, 'kind' => 'dropper', 'table' => $table];
        }
    }
    return $hits;
}

// "The plugin's own hand-written PHP" -- everything that ships runtime logic,
// minus vendor/ (dependencies), bin/ (tooling, and outside phpcs.xml's scope
// for the same reason), tests/ (test code), build/ + node_modules/
// (generated/JS-dependency output), carveout/ (tooling + inventories that
// .distignore itself excludes from the ZIP) and languages/ (compiled
// translation data, not logic).
$scanRoots = ['src', 'src-react', 'assets', 'templates', 'mhm-rentiva.php', 'uninstall.php'];

$existingRoots = array_values(array_filter($scanRoots, static fn($r) => file_exists($root . '/' . $r)));
if ($existingRoots === []) {
    fwrite(STDERR, "AUDIT-INDEX-SURFACE ERROR: none of the scan roots exist under $root -- wrong cwd?\n");
    fwrite(STDERR, '  expected one of: ' . implode(', ', $scanRoots) . "\n");
    exit(EXIT_ERROR);
}

$phpFiles = [];
foreach ($scanRoots as $r) {
    $phpFiles = array_merge($phpFiles, list_php_files($root, $r));
}
sort($phpFiles);

$creators = [];
$droppers = [];
foreach ($phpFiles as $rel) {
    foreach (scan_file_for_wpdb_query($root . '/' . $rel) as $hit) {
        $row = ['file' => $rel, 'line' => $hit['line'], 'table' => $hit['table']];
        if ($hit['kind'] === 'creator') {
            $creators[] = $row;
        } else {
            $droppers[] = $row;
        }
    }
}

// ---------------------------------------------------------------------------
// (b) Live DB scan.
// ---------------------------------------------------------------------------
$container = getenv('MHM_AIS_CONTAINER') ?: 'rentiva-dev-wpcli-1';

$liveOwned = null; // null => NA; the report never prints 0 for "could not measure"
$liveDetail = [];
$liveWhy   = '';

[$rc] = run_cmd(['docker', 'exec', $container, 'test', '-d', '/var/www/html']);
if ($rc !== 0) {
    $liveWhy = "container '$container' unreachable (docker exec rc=$rc) -- is the Docker WP stack running?";
} else {
    $sql = 'SELECT DISTINCT TABLE_NAME, INDEX_NAME FROM information_schema.STATISTICS '
         . "WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('wp_posts','wp_postmeta','wp_usermeta') "
         . 'ORDER BY TABLE_NAME, INDEX_NAME';
    // WP_CLI_PHP_ARGS raises the memory limit: wp-cli's default 128M OOMs
    // partway through bootstrapping WP inside this container (a known trap
    // for this round, recorded in progress.md's "Yeni oturum" notes).
    $inner = 'cd /var/www/html && '
           . "WP_CLI_PHP_ARGS='-d memory_limit=512M' wp db query \"$sql\" --allow-root --skip-column-names";
    [$rc, $stdout, $stderr] = run_cmd(['docker', 'exec', $container, 'bash', '-c', $inner]);
    if ($rc !== 0) {
        $liveWhy = "wp db query failed inside '$container' (rc=$rc): "
                 . trim($stderr !== '' ? $stderr : $stdout);
    } else {
        foreach (preg_split('/\R/', trim($stdout)) as $line) {
            if (trim($line) === '') {
                continue;
            }
            $parts = explode("\t", $line);
            if (count($parts) < 2) {
                continue;
            }
            [$table, $indexName] = $parts;
            // Plugin-owned vs WordPress-core-own: see the header's "idx_
            // prefix" note. Live-verified against this exact query's own
            // output that no core index on these three tables starts with
            // "idx_" (PRIMARY, post_id, meta_key, post_name, post_author,
            // post_parent, type_status_author, type_status_date, user_id).
            if (str_starts_with($indexName, 'idx_')) {
                $liveDetail[] = $table . '.' . $indexName;
            }
        }
        $liveOwned = count($liveDetail);
    }
}

// ---------------------------------------------------------------------------
// Report.
// ---------------------------------------------------------------------------
printf(
    "INDEX SURFACE: creators=%d droppers=%d live_owned=%s\n",
    count($creators),
    count($droppers),
    $liveOwned === null ? 'NA' : (string) $liveOwned
);

echo "  this is a report, not a gate -- no pass/fail threshold, always exits 0\n";
echo "  blind spots (declared, not hidden -- full explanation in this file's header):\n";
echo "    - dynamic index-name generation (name built at runtime, not a source literal)\n";
echo "    - dbDelta()-driven schema (only \$wpdb->query() call sites are scanned)\n";
echo "    - SQL assembled by string concatenation before it reaches \$wpdb->query()\n";
echo "    - the Pro-edition codebase (different repository, not visible here)\n";
echo "    - vendor/, bin/, tests/, build/, node_modules/, carveout/, languages/ (out of scanned roots)\n";
echo "    - only the literal phrases \"CREATE INDEX\" / \"DROP INDEX\" are matched (see header: ALTER-TABLE asymmetry)\n";
echo "    - per-hit table labels are a literal-text match, not data-flow analysis\n";
if ($liveOwned !== null) {
    echo "    - live_owned uses an \"idx_\" name-prefix heuristic to separate plugin-owned indexes from WP core's own\n";
}

printf("  scanned roots (source half): %s\n", implode(', ', $existingRoots));
if (count($existingRoots) < count($scanRoots)) {
    $missing = array_values(array_diff($scanRoots, $existingRoots));
    printf("    (not present in this tree, skipped: %s)\n", implode(', ', $missing));
}

if ($liveOwned === null) {
    printf("  live DB: CANNOT MEASURE -- %s\n", $liveWhy);
} else {
    printf(
        "  live DB tool: docker exec %s ... information_schema.STATISTICS (wp_posts, wp_postmeta, wp_usermeta)\n",
        $container
    );
}

$byTable = static function (array $rows): string {
    $counts = [];
    foreach ($rows as $r) {
        $counts[$r['table']] = ($counts[$r['table']] ?? 0) + 1;
    }
    arsort($counts);
    $parts = [];
    foreach ($counts as $t => $c) {
        $parts[] = "$t=$c";
    }
    return $parts === [] ? '(none)' : implode(' ', $parts);
};

printf("  creators (%d) by table: %s\n", count($creators), $byTable($creators));
foreach ($creators as $c) {
    printf("    %s:%d  [%s]\n", $c['file'], $c['line'], $c['table']);
}
printf("  droppers (%d) by table: %s\n", count($droppers), $byTable($droppers));
foreach ($droppers as $d) {
    printf("    %s:%d  [%s]\n", $d['file'], $d['line'], $d['table']);
}

if ($liveOwned !== null) {
    printf("  live_owned detail (%d distinct table.index_name on wp_posts/wp_postmeta/wp_usermeta):\n", $liveOwned);
    foreach ($liveDetail as $d) {
        echo "    $d\n";
    }
}

exit(EXIT_OK);
