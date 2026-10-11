<?php
/**
 * Markup gate for the translation catalogs: HTML tags, entities and URLs.
 *
 * A translator may reorder a sentence around its markup; changing the markup
 * itself changes what the page RENDERS, not what it says:
 *
 *   - a dropped `<strong>` loses the emphasis the source asked for;
 *   - a dropped `</a>` or `</span>` swallows the rest of the screen into the
 *     element -- the defect appears far from the string that caused it;
 *   - a changed URL sends the Turkish reader somewhere the English one is not;
 *   - a dropped `&nbsp;` or `&mdash;` re-wraps a price or a label.
 *
 * Nothing else in the pipeline sees this class:
 *   - msgfmt -c validates printf conversions (%s, %d), not markup;
 *   - check-i18n-placeholders.php compares {{dot.path}} / {token} only;
 *   - build-i18n.py --verify-only compares committed catalogs to the committed
 *     .po, and both sides agree on the broken msgstr.
 *
 * What is compared, per translated entry, as MULTISETS (order-free, count-exact):
 *   tags     -- by name and direction: `<strong ...>` -> `<strong>`, `</strong>`,
 *               `<br/>` -> `<br>`. Attributes other than URLs are not compared
 *               (a translated title="" is legitimate).
 *   entities -- `&name;`, `&#123;`, `&#x1F;`. A bare `&` is text, not markup.
 *   URLs     -- `http(s)://...` anywhere, href included, trailing punctuation
 *               trimmed. A localised URL is a finding by design: if one is ever
 *               wanted, it is a decision to make in review, not in a msgstr.
 * A plural entry compares msgstr[0] with msgid and every msgstr[N>0] with
 * msgid_plural, each form on its own -- concatenating them would let one
 * form's surplus hide another's loss.
 *
 * Not compared: untranslated entries (bin/check-po-untranslated.php owns them),
 * the header, obsolete (#~) entries, and meaning.
 *
 * Usage:
 *   php bin/check-i18n-markup.php [<catalog.po>...]
 *   Default scan set: every languages/*.po in this plugin.
 *
 * Exit codes: 0 = clean · 1 = mismatch found · 2 = a named catalog is missing.
 *
 * @package MHM_Rentiva
 */

declare(strict_types=1);

// The shared PO reader. Its CLI block is guarded against being the entry
// script, so requiring it runs nothing.
require_once __DIR__ . '/check-po-untranslated.php';

const MHMRENTIVA_MARKUP_TAG_PATTERN    = '/<\s*(\/?)\s*([A-Za-z][A-Za-z0-9-]*)\b[^<>]*>/';
const MHMRENTIVA_MARKUP_ENTITY_PATTERN = '/&(?:[A-Za-z][A-Za-z0-9]*|#[0-9]+|#[xX][0-9A-Fa-f]+);/';
// The catalog is read raw, so a quote inside a value is `\"`: a backslash ends a URL.
const MHMRENTIVA_MARKUP_URL_PATTERN = '#\bhttps?://[^\s"\'<>\\\\]+#';

/**
 * The markup tokens of one string, as token => count.
 *
 * @return array<string, int>
 */
function mhmrentiva_markup_tokens(string $text): array
{
    $tokens = [];

    if (preg_match_all(MHMRENTIVA_MARKUP_TAG_PATTERN, $text, $m, PREG_SET_ORDER) > 0) {
        foreach ($m as $tag) {
            $key            = '<' . $tag[1] . strtolower($tag[2]) . '>';
            $tokens[ $key ] = ( $tokens[ $key ] ?? 0 ) + 1;
        }
    }

    if (preg_match_all(MHMRENTIVA_MARKUP_ENTITY_PATTERN, $text, $m) > 0) {
        foreach ($m[0] as $entity) {
            $tokens[ $entity ] = ( $tokens[ $entity ] ?? 0 ) + 1;
        }
    }

    if (preg_match_all(MHMRENTIVA_MARKUP_URL_PATTERN, $text, $m) > 0) {
        foreach ($m[0] as $url) {
            $url            = rtrim($url, '.,;:!?)');
            $tokens[ $url ] = ( $tokens[ $url ] ?? 0 ) + 1;
        }
    }

    return $tokens;
}

/**
 * Tokens in $want not matched in $got, one list item per missing occurrence.
 *
 * @param array<string, int> $want
 * @param array<string, int> $got
 * @return array<int, string>
 */
function mhmrentiva_markup_difference(array $want, array $got): array
{
    $out = [];
    foreach ($want as $token => $count) {
        $short = $count - ( $got[ $token ] ?? 0 );
        for ($i = 0; $i < $short; $i++) {
            $out[] = (string) $token;
        }
    }
    sort($out);

    return $out;
}

/**
 * Scan one catalog for markup that differs between source and translation.
 *
 * @param string $path    Catalog to read.
 * @param int    $scanned Out: translated (source, form) pairs where either side carries markup.
 * @return array<int, array{msgid: string, context: string, form: string, missing: array<int, string>, extra: array<int, string>}>
 */
function mhmrentiva_find_markup_mismatches(string $path, int &$scanned = 0): array
{
    $scanned  = 0;
    $contents = file_get_contents($path);
    if ($contents === false) {
        return [];
    }

    $findings = [];

    foreach (mhmrentiva_po_entries($contents) as $entry) {
        if ($entry['msgid'] === '') {
            continue; // Header.
        }

        // (label, source, translation) pairs to compare.
        $pairs = [];
        if ($entry['plural']) {
            foreach ($entry['forms'] as $n => $form) {
                $pairs[] = [
                    'msgstr[' . $n . ']',
                    $n === 0 ? $entry['msgid'] : $entry['msgid_plural'],
                    $form,
                ];
            }
        } else {
            $pairs[] = ['msgstr', $entry['msgid'], $entry['msgstr']];
        }

        foreach ($pairs as [$label, $source, $translation]) {
            if ($translation === '') {
                continue; // Untranslated: a different gate's finding.
            }

            $want = mhmrentiva_markup_tokens($source);
            $got  = mhmrentiva_markup_tokens($translation);
            if ($want === [] && $got === []) {
                continue;
            }

            ++$scanned;

            $missing = mhmrentiva_markup_difference($want, $got);
            $extra   = mhmrentiva_markup_difference($got, $want);

            if ($missing !== [] || $extra !== []) {
                $findings[] = [
                    'msgid'   => $entry['msgid'],
                    'context' => $entry['context'],
                    'form'    => $label,
                    'missing' => $missing,
                    'extra'   => $extra,
                ];
            }
        }
    }

    return $findings;
}

/**
 * CLI entry point.
 *
 * Guarded against the resolved entry script so the PHPUnit gate can require this
 * file for the functions above without the scan running -- and calling exit() --
 * at include time.
 */
if (PHP_SAPI === 'cli' && isset($argv[0]) && realpath($argv[0]) === realpath(__FILE__)) {
    $catalogs = array_slice($argv, 1);
    if ($catalogs === []) {
        $catalogs = glob(dirname(__DIR__) . '/languages/*.po') ?: [];
    }

    if ($catalogs === []) {
        fwrite(STDERR, "[ERROR] Taranacak katalog bulunamadi.\n");
        exit(2);
    }

    $total_findings = 0;
    $total_scanned  = 0;

    foreach ($catalogs as $catalog) {
        if (! is_file($catalog)) {
            fwrite(STDERR, "[ERROR] Katalog diskte yok: {$catalog}\n");
            exit(2);
        }

        $scanned  = 0;
        $findings = mhmrentiva_find_markup_mismatches($catalog, $scanned);
        $total_scanned += $scanned;

        printf("%s · isaretleme tasiyan cevrili dize: %d · uyusmazlik: %d\n", basename($catalog), $scanned, count($findings));

        foreach ($findings as $f) {
            $total_findings++;
            printf("  msgid : %s%s  [%s]\n", $f['context'] !== '' ? $f['context'] . ' | ' : '', $f['msgid'], $f['form']);
            if ($f['missing'] !== []) {
                printf("    EKSIK : %s\n", implode(', ', $f['missing']));
            }
            if ($f['extra'] !== []) {
                printf("    FAZLA : %s\n", implode(', ', $f['extra']));
            }
        }
    }

    if ($total_findings > 0) {
        printf("\n%d uyusmazlik. Ceviri sozcukleri degistirir; HTML etiketlerini,\n", $total_findings);
        echo "varliklari (&nbsp; ...) ve URL'leri kaynaktaki sayida ve bicimde korur.\n";
        exit(1);
    }

    printf("\n[OK] %d dizede etiket/varlik/URL kumeleri birebir esit.\n", $total_scanned);
    exit(0);
}
