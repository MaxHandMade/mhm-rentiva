<?php
/**
 * Placeholder gate for the translation catalogs.
 *
 * A translator can change a placeholder, and that silently changes BEHAVIOUR.
 * Measured 2026-08-24 on languages/mhm-rentiva-tr_TR.po:
 *
 *   msgid  "Refund Processed for Booking #{{booking.order_id}}"
 *   msgstr "Rezervasyon #{{booking.id}} icin Geri Odeme Isleme Alindi"
 *
 * {{booking.order_id}} is the WooCommerce order number -- Templates.php:586
 * maps the customer-facing token to it on purpose, because that is the number
 * a customer recognises. {{booking.id}} is the internal booking post id. So on
 * a Turkish site the SUBJECT printed 9517 while the BODY printed 9516: two
 * different numbers for the same booking, in one e-mail. Seven subjects carried
 * the same substitution.
 *
 * Nothing else catches this class:
 *   - msgfmt -c checks printf conversions (%s, %d), not {{dot.path}} or {token}.
 *   - build-i18n.py --verify-only compares committed catalogs to the committed
 *     .po; both sides agree on the wrong placeholder.
 *   - PHPUnit never renders a translated subject.
 * The defect is invisible until a customer reads the e-mail.
 *
 * Two failure shapes, both real:
 *   MISSING -- in msgid, absent from msgstr: the value never prints at all.
 *   EXTRA   -- in msgstr, absent from msgid: either an unresolved literal token
 *              or, as here, a DIFFERENT field silently substituted.
 *
 * Usage:
 *   php bin/check-i18n-placeholders.php [<catalog.po>...]
 *   Default scan set: every languages/*.po in this plugin.
 *
 * Exit codes: 0 = clean · 1 = mismatch found · 2 = a named catalog is missing.
 *
 * @package MHM_Rentiva
 */

declare(strict_types=1);

/**
 * Both formats Templates::replace_placeholders() resolves, in its two passes:
 * {{dot.path}} first, then {token}.
 */
const MHMRENTIVA_PLACEHOLDER_PATTERN = '/\{\{[A-Za-z0-9_.]+\}\}|\{[A-Za-z0-9_]+\}/';

/**
 * Scan one catalog for placeholder sets that differ between msgid and msgstr.
 *
 * Entries whose msgid carries no placeholder are skipped, and so are untranslated
 * ones: an empty msgstr is a different finding with its own gate, and reporting
 * it here would bury this one.
 *
 * @param string $path            Catalog to read.
 * @param int    $scanned         Out: how many translated placeholder-bearing entries were compared.
 * @return array<int, array{msgid: string, missing: array<int, string>, extra: array<int, string>}>
 */
function mhmrentiva_find_placeholder_mismatches(string $path, int &$scanned = 0): array
{
    $scanned  = 0;
    $contents = file_get_contents($path);
    if ($contents === false) {
        return [];
    }

    $findings = [];

    foreach (preg_split('/\R\R+/', $contents) ?: [] as $block) {
        $msgid  = '';
        $msgstr = '';
        $target = null;

        foreach (preg_split('/\R/', $block) ?: [] as $line) {
            if (strpos($line, 'msgid_plural') === 0) {
                // A plural entry's msgstr[N] lines belong to a different shape;
                // its singular msgid is still compared via the msgid branch.
                $target = null;
                continue;
            }
            if (strpos($line, 'msgid') === 0) {
                $target = 'id';
            } elseif (strpos($line, 'msgstr') === 0) {
                $target = 'str';
            } elseif ($line === '' || $line[0] !== '"') {
                continue;
            }

            if (preg_match('/"(.*)"\s*$/', $line, $m) !== 1) {
                continue;
            }
            if ($target === 'id') {
                $msgid .= $m[1];
            } elseif ($target === 'str') {
                $msgstr .= $m[1];
            }
        }

        if ($msgid === '' || $msgstr === '') {
            continue;
        }

        preg_match_all(MHMRENTIVA_PLACEHOLDER_PATTERN, $msgid, $want);
        if ($want[0] === []) {
            continue;
        }

        ++$scanned;

        preg_match_all(MHMRENTIVA_PLACEHOLDER_PATTERN, $msgstr, $got);

        $missing = array_values(array_unique(array_diff($want[0], $got[0])));
        $extra   = array_values(array_unique(array_diff($got[0], $want[0])));

        if ($missing !== [] || $extra !== []) {
            sort($missing);
            sort($extra);
            $findings[] = [
                'msgid'   => $msgid,
                'missing' => $missing,
                'extra'   => $extra,
            ];
        }
    }

    return $findings;
}

/**
 * CLI entry point.
 *
 * Guarded against the resolved entry script so the PHPUnit gate can require this
 * file for the function above without the scan running -- and calling exit() --
 * at include time. PHPUnit itself runs under the cli SAPI, so PHP_SAPI alone
 * would not separate them.
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
        $findings = mhmrentiva_find_placeholder_mismatches($catalog, $scanned);
        $total_scanned += $scanned;

        printf("%s · yer tutucu tasiyan cevrili dize: %d · uyusmazlik: %d\n", basename($catalog), $scanned, count($findings));

        foreach ($findings as $f) {
            $total_findings++;
            printf("  msgid : %s\n", $f['msgid']);
            if ($f['missing'] !== []) {
                printf("    EKSIK : %s  (bu deger hic basilmaz)\n", implode(', ', $f['missing']));
            }
            if ($f['extra'] !== []) {
                printf("    FAZLA : %s  (cozulmeyen token ya da BASKA bir alan)\n", implode(', ', $f['extra']));
            }
        }
    }

    if ($total_findings > 0) {
        printf("\n%d uyusmazlik. Ceviri yer tutucuyu DEGISTIREMEZ: kaynaktaki\n", $total_findings);
        echo "her yer tutucu cevirisinde birebir ayni yazilmalidir. Sozcukleri cevir,\n";
        echo "tokenlari kopyala.\n";
        exit(1);
    }

    printf("\n[OK] %d dizede yer tutucu kumeleri birebir esit.\n", $total_scanned);
    exit(0);
}
