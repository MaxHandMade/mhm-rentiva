<?php
/**
 * Gate G-D: Plugin Check parity -- the REAL WP.org acceptance bar.
 *
 * G-A (bin/check-shape-zero.php), G-B (bin/check-instrument-gaps.php) and G-C
 * (bin/check-prefix-inventory.php) are narrow probes: G-A only runs three
 * phpcs sniff families (NonceVerification/EscapeOutput/ValidatedSanitizedInput),
 * G-B is five grep-based shapes phpcs is structurally blind to, and G-C is a
 * data-completeness check unrelated to phpcs at all. None of them look at
 * WP.org's Plugin Check tool's OWN ruleset -- and that ruleset is what an
 * actual WP.org submission is graded against. The controller measured this
 * gap directly: with phpcs:ignore suppressions ACTIVE the existing gates
 * read 0/0, but running Plugin Check's ruleset with --ignore-annotations
 * (i.e. what the WP.org reviewer's own tooling sees; suppressions do not
 * blind a human reviewer either) scores 31 ERRORS + 158 WARNINGS across 53
 * files -- including the entire WordPress.DB.PreparedSQL* family (101
 * findings, at ERROR severity in Plugin Check's ruleset) that none of G-A/
 * G-B/G-C were watching.
 *
 * This gate IS that measurement, directly: it runs bin/plugin-check.ruleset.xml
 * (a vendored, runnable copy of the upstream Plugin Check ruleset -- see that
 * file's own header for provenance/version/what was stripped and why) via
 * phpcs, counts ERRORS and WARNINGS separately, and prints a per-sniff
 * breakdown plus a file:line/sniff/severity line for every single finding.
 *
 * The user's stated acceptance bar is 0 errors AND 0 warnings -- not "G-A/
 * G-B/G-C all green". This gate is the one that actually measures that bar.
 * It does NOT fix anything; later tasks in the T7 remediation plan drive it
 * to zero. It is EXPECTED RED today (WP.org T7 remediation, Task 7.5,
 * 2026-08-01) -- no fixes for the PreparedSQL/Nonce/error_log/etc. findings
 * it surfaces have landed yet.
 */
$cmd = 'vendor/bin/phpcs --standard=bin/plugin-check.ruleset.xml --ignore-annotations --report=json '
     . 'src/ templates/ mhm-rentiva.php uninstall.php 2>/dev/null';
exec($cmd, $out, $rc);
$json = json_decode(implode('', $out), true);
if (!is_array($json) || !isset($json['files'])) {
    fwrite(STDERR, "G-D: phpcs çıktısı ayrıştırılamadı (rc=$rc)\n");
    exit(2);
}

$findings = [];
$filesWithFindings = 0;
foreach ($json['files'] as $file => $data) {
    if ($data['messages']) {
        $filesWithFindings++;
    }
    foreach ($data['messages'] as $m) {
        $findings[] = [
            'file'     => $file,
            'line'     => $m['line'],
            'sniff'    => $m['source'],
            'severity' => strtolower($m['type']), // 'error' | 'warning'
        ];
    }
}

$errors = array_values(array_filter($findings, fn($f) => $f['severity'] === 'error'));
$warnings = array_values(array_filter($findings, fn($f) => $f['severity'] === 'warning'));

// Per-sniff breakdown, sorted by count descending (errors and warnings mixed
// per sniff since a single sniff can emit both, e.g. NonceVerification's
// Missing/Recommended sub-sniffs are both under the same family but differ
// in the ruleset's <type> override).
$bySniff = [];
foreach ($findings as $f) {
    $bySniff[$f['sniff']] = ($bySniff[$f['sniff']] ?? 0) + 1;
}
arsort($bySniff);

printf("G-D: errors=%d, warnings=%d (%d of %d scanned files have findings)\n", count($errors), count($warnings), $filesWithFindings, count($json['files']));
echo "  per-sniff breakdown (desc):\n";
foreach ($bySniff as $sniff => $count) {
    printf("    %-4d %s\n", $count, $sniff);
}

if ($findings) {
    echo "  findings:\n";
    foreach ($findings as $f) {
        printf("    %s:%d  %s  %s\n", $f['file'], $f['line'], $f['sniff'], $f['severity']);
    }
}

exit((count($errors) === 0 && count($warnings) === 0) ? 0 : 1);
