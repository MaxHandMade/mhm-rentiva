<?php
/**
 * Untranslated-entry gate for the translation catalogs.
 *
 * bin/check-i18n-placeholders.php skips untranslated entries and says why: "an
 * empty msgstr is a different finding with its own gate". Until this file that
 * gate did not exist, in either plugin -- the sentence promised a check nobody
 * had written. build-i18n.py --verify-only compares committed catalogs to the
 * committed .po and passes an empty msgstr; msgfmt --statistics is not installed
 * in this environment (neither in the wpcli container nor on the host).
 *
 * A finding is an entry WordPress will show in its source language, or show as
 * a guess:
 *   empty                -- msgstr is empty (every line of it, not just the first)
 *   missing_msgstr       -- the entry has no msgstr at all
 *   missing_plural_form  -- a plural entry lacks msgstr[N] for an N the catalog's
 *                           own Plural-Forms header requires
 *   fuzzy                -- translated but flagged fuzzy. msgfmt would drop it;
 *                           `wp i18n make-mo` / `make-php` do NOT: gettext's Po
 *                           extractor stores fuzzy as a flag only, and the
 *                           generators skip only disabled (#~) or empty entries
 *                           (measured in the installed WP-CLI, 2026-09-13). So a
 *                           guess ships as if it were reviewed.
 *
 * The catalog is read the way WP-CLI reads it, because WP-CLI compiles what
 * WordPress loads (gettext's Extractors/Po.php in the installed phar): every
 * line is trim()med, so a directive may be indented, and a line that is empty
 * after trimming -- or is a bare "#" -- ends the entry. Values are gathered
 * across continuation lines before anything is judged. Each of these was learned
 * from a wrong "clean":
 *   - a multi-line msgid opens with `msgid ""`, the header's own first line, and
 *     a line-by-line check skipped it as the header;
 *   - Pro's tr_TR catalog has 119 valid translations whose first line is
 *     `msgstr ""`;
 *   - PCRE's \R without /u also matches the byte 0x85, the last byte of U+2705,
 *     and cut three real entries in two;
 *   - splitting only on EMPTY lines merged an entry after a tab-only separator
 *     into the header, and column-0 anchors ignored indented entries entirely
 *     (both from Codex's review of Lite #48).
 *
 * Not findings: the header entry, and obsolete (#~) entries, which WP-CLI does
 * not compile.
 *
 * mhmrentiva_po_entries() is also the reader check-i18n-placeholders.php uses:
 * one reader for both gates, so a parsing defect is fixed once.
 *
 * Usage:
 *   php bin/check-po-untranslated.php [<catalog.po>...]
 *   php bin/check-po-untranslated.php --plugin-dir <plugin root>
 *   Default scan set: every languages/*.po in this plugin.
 *
 * Exit codes: 0 = clean · 1 = untranslated entries found · 2 = nothing to read
 * (a named catalog is missing, or the scan set is empty). Never a silent pass.
 *
 * @package MHM_Rentiva
 */

declare(strict_types=1);

/**
 * Every entry of a PO catalog, header included, as WP-CLI would read it.
 *
 * Obsolete (#~) entries are not returned: all of their lines are comments, so
 * they never gain a msgid. The header is the entry whose joined msgid and
 * context are both empty; callers decide what to do with it.
 *
 * @return array<int, array{context: string, msgid: string, plural: bool, msgstr: string, has_msgstr: bool, forms: array<int, string>, fuzzy: bool}>
 */
function mhmrentiva_po_entries(string $contents): array
{
    $blank = static function (): array {
        return [
            'context'    => '',
            'msgid'      => '',
            'has_msgid'  => false,
            'plural'     => false,
            'msgstr'     => '',
            'has_msgstr' => false,
            'forms'      => [],
            'fuzzy'      => false,
        ];
    };

    $entries = [];
    $entry   = $blank();
    $target  = null;

    // Line endings are spelled out, not \R: without /u, \R also matches the
    // byte 0x85 (NEL), the last byte of U+2705 (E2 9C 85). /u is not the fix
    // either: invalid UTF-8 makes preg_split() return false, and `?: []` would
    // turn that into a clean scan of nothing.
    $lines   = preg_split('/\r\n|\n|\r/', $contents) ?: [];
    $lines[] = ''; // Ends the last entry exactly as a separator would.

    foreach ($lines as $raw) {
        // trim(), as WP-CLI does. Its default character set does not include
        // 0x85, so this cannot re-open the \R defect.
        $line = trim($raw);

        if ($line === '' || $line === '#') {
            if ($entry['has_msgid']) {
                unset($entry['has_msgid']);
                $entries[] = $entry;
            }
            $entry  = $blank();
            $target = null;
            continue;
        }

        if (strpos($line, '#,') === 0) {
            if (preg_match('/(^|[\s,])fuzzy(,|\s|$)/', substr($line, 2)) === 1) {
                $entry['fuzzy'] = true;
            }
            continue;
        }

        // Every other comment, including obsolete `#~` lines.
        if ($line[0] === '#') {
            continue;
        }

        if (preg_match('/^(msgctxt|msgid_plural|msgid|msgstr(?:\[(\d+)\])?)\s+"(.*)"$/', $line, $m) === 1) {
            $keyword = $m[1];
            $value   = $m[3];

            if ($keyword === 'msgctxt') {
                $target            = 'context';
                $entry['context'] .= $value;
            } elseif ($keyword === 'msgid_plural') {
                // The plural source is not judged; its msgstr[N] lines are.
                $entry['plural'] = true;
                $target          = null;
            } elseif ($keyword === 'msgid') {
                $entry['has_msgid'] = true;
                $target             = 'msgid';
                $entry['msgid']    .= $value;
            } elseif (isset($m[2]) && $m[2] !== '') {
                $entry['has_msgstr']       = true;
                $target                    = (int) $m[2];
                $entry['forms'][ $target ] = ( $entry['forms'][ $target ] ?? '' ) . $value;
            } else {
                $entry['has_msgstr'] = true;
                $target              = 'msgstr';
                $entry['msgstr']    .= $value;
            }
            continue;
        }

        if ($target !== null && preg_match('/^"(.*)"$/', $line, $m) === 1) {
            if (is_int($target)) {
                $entry['forms'][ $target ] .= $m[1];
            } else {
                $entry[ $target ] .= $m[1];
            }
        }
    }

    return $entries;
}

/**
 * Scan one catalog for entries that are not translated.
 *
 * @param string $path    Catalog to read.
 * @param int    $scanned Out: live entries examined (header and obsolete excluded).
 * @return array<int, array{msgid: string, context: string, reason: string}>
 */
function mhmrentiva_find_untranslated_entries(string $path, int &$scanned = 0): array
{
    $scanned  = 0;
    $contents = file_get_contents($path);
    if ($contents === false) {
        return [];
    }

    $findings = [];
    $nplurals = 2;

    foreach (mhmrentiva_po_entries($contents) as $entry) {
        // The header is the entry whose JOINED msgid is empty. A multi-line
        // msgid also opens with `msgid ""`, which is why this is judged after
        // the entry has been gathered and not on its first line.
        if ($entry['msgid'] === '' && $entry['context'] === '') {
            if (preg_match('/nplurals\s*=\s*(\d+)/', $entry['msgstr'], $np) === 1 && (int) $np[1] > 0) {
                $nplurals = (int) $np[1];
            }
            continue;
        }

        ++$scanned;

        $reason = null;

        if (! $entry['has_msgstr']) {
            $reason = 'missing_msgstr';
        } elseif ($entry['plural']) {
            for ($i = 0; $i < $nplurals; $i++) {
                if (! array_key_exists($i, $entry['forms'])) {
                    $reason = 'missing_plural_form';
                    break;
                }
                if ($entry['forms'][ $i ] === '') {
                    $reason = 'empty';
                    break;
                }
            }
        } elseif ($entry['msgstr'] === '') {
            $reason = 'empty';
        }

        if ($reason === null && $entry['fuzzy']) {
            $reason = 'fuzzy';
        }

        if ($reason !== null) {
            $findings[] = [
                'msgid'   => $entry['msgid'],
                'context' => $entry['context'],
                'reason'  => $reason,
            ];
        }
    }

    return $findings;
}

/**
 * CLI entry point.
 *
 * Guarded against the resolved entry script so the PHPUnit gate -- and
 * check-i18n-placeholders.php, which require()s this file for its reader -- can
 * load the functions above without the scan running and calling exit().
 */
if (PHP_SAPI === 'cli' && isset($argv[0]) && realpath($argv[0]) === realpath(__FILE__)) {
    $args       = array_slice($argv, 1);
    $plugin_dir = dirname(__DIR__);
    $catalogs   = [];

    for ($i = 0, $n = count($args); $i < $n; $i++) {
        if ($args[ $i ] === '--plugin-dir') {
            if (! isset($args[ $i + 1 ])) {
                fwrite(STDERR, "[ERROR] --plugin-dir bir dizin bekliyor.\n");
                exit(2);
            }
            $plugin_dir = rtrim($args[ ++$i ], '/\\');
            continue;
        }
        $catalogs[] = $args[ $i ];
    }

    if ($catalogs === []) {
        $catalogs = glob($plugin_dir . '/languages/*.po') ?: [];
    }

    if ($catalogs === []) {
        fwrite(STDERR, "[ERROR] Taranacak katalog bulunamadi: {$plugin_dir}/languages/*.po\n");
        exit(2);
    }

    $labels = [
        'empty'               => 'BOS',
        'missing_msgstr'      => 'MSGSTR YOK',
        'missing_plural_form' => 'COGUL FORM EKSIK',
        'fuzzy'               => 'FUZZY (tahmin, gozden gecirilmemis)',
    ];

    $total_findings = 0;
    $total_scanned  = 0;

    foreach ($catalogs as $catalog) {
        if (! is_file($catalog)) {
            fwrite(STDERR, "[ERROR] Katalog diskte yok: {$catalog}\n");
            exit(2);
        }

        $scanned  = 0;
        $findings = mhmrentiva_find_untranslated_entries($catalog, $scanned);
        $total_scanned += $scanned;

        printf("%s · incelenen girdi: %d · cevrilmemis: %d\n", basename($catalog), $scanned, count($findings));

        foreach ($findings as $f) {
            $total_findings++;
            $shown = strlen($f['msgid']) > 120 ? substr($f['msgid'], 0, 117) . '...' : $f['msgid'];
            printf(
                "  %-20s %s%s\n",
                $labels[ $f['reason'] ] ?? $f['reason'],
                $f['context'] !== '' ? '[' . $f['context'] . '] ' : '',
                $shown
            );
        }
    }

    if ($total_findings > 0) {
        printf("\n%d cevrilmemis girdi. WordPress bunlari kaynak dilde (ya da tahmin olarak) gosterir.\n", $total_findings);
        exit(1);
    }

    printf("\n[OK] %d girdinin hepsi cevrilmis.\n", $total_scanned);
    exit(0);
}
