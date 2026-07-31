<?php
/**
 * Gate G-B: probes for classes WPCS is satisfied with but WP.org flags.
 *
 * Complementary to bin/check-shape-zero.php (Gate G-A). G-A runs phpcs itself,
 * so it can only ever surface what a phpcs sniff is written to detect. This
 * gate is grep-based on purpose: every one of the five shapes below is a
 * pattern WPCS sniffs pass cleanly today (verified against the T7 rejection
 * examples) but that WP.org's human reviewer flagged anyway. A phpcs-based
 * gate is structurally blind to these; a separate probe script is not.
 *
 * Expected RED today (2026-07-31, WP.org T7 remediation start) -- no fixes
 * have landed yet. Later tasks in docs/plans/2026-07-31-wporg-t7-duzeltme-turu.md
 * drive each probe to zero; this script's exit code is the acceptance test.
 */
$fail = 0;
// grep -P (used by the P5 prefilter below) refuses to run under some locale
// configurations ("supports only unibyte and UTF-8 locales") even when
// LC_CTYPE is already UTF-8 -- observed on the Windows/Git-Bash dev grep
// build, where only LC_ALL (not LC_CTYPE alone) satisfies the check. Forcing
// it here makes the gate portable across that environment and CI's ubuntu
// runner without changing what any pattern matches.
putenv('LC_ALL=C.UTF-8');
$scan = function (string $pattern, string $label, array $dirs = ['src', 'templates']) use (&$fail) {
    $hits = [];
    foreach ($dirs as $d) {
        exec("grep -rnE " . escapeshellarg($pattern) . " $d --include=*.php", $o);
        $hits = array_merge($hits, $o); $o = [];
    }
    printf("G-B [%s]: %d\n", $label, count($hits));
    foreach ($hits as $h) { echo "  $h\n"; }
    if ($hits) { $fail = 1; }
};
// P1: filter_input UNSAFE_RAW/DEFAULT şekli (WPCS susar, WP.org flag'ler)
$scan('filter_input\s*\([^)]*(FILTER_UNSAFE_RAW|FILTER_DEFAULT)', 'filter_input-unsafe');
// P2: CSS bağlamına strip_tags ile giren değer (escape değil)
$scan('wp_add_inline_style\s*\([^)]*wp_strip_all_tags', 'css-context-striptags');
// P3: option'a yazılan map'te anahtar sanitize'ı olmayan array_map (değer-only imza)
$scan("update_option\s*\(\s*'[^']*'\s*,\s*array_map\s*\(\s*'sanitize_text_field'", 'array-key-unsanitized');
// P4: sınıf sabitlerinde 3. parti host string'i (deny-list dahi olsa flag yer)
$scan("(unpkg\.com|jsdelivr|googleapis|cdnjs|maxcdn)", 'const-external-host');
// P5: bileşik-koşul nonce şekli — PHPCS bunu görmüyor, T7'nin gösterdiği DepositAjax:49
// örneği ve aynı ailedeki tüm satırlar bu sonda olmadan taranmaz kalırdı. Çok satırlı
// parantezli if'leri de yakalamak için dosya-bazlı grep -Pzo (multiline) kullanılır.
$scanMultiline = function (string $pattern, string $label) use (&$fail) {
    exec("grep -rlP " . escapeshellarg('wp_verify_nonce') . " src templates --include=*.php", $files);
    $hits = [];
    foreach ($files as $file) {
        $src = file_get_contents($file);
        if (preg_match_all($pattern, $src, $m, PREG_OFFSET_CAPTURE)) {
            foreach ($m[0] as $match) {
                $line = substr_count(substr($src, 0, $match[1]), "\n") + 1;
                $hits[] = "$file:$line";
            }
        }
    }
    printf("G-B [%s]: %d\n", $label, count($hits));
    foreach ($hits as $h) { echo "  $h\n"; }
    if ($hits) { $fail = 1; }
};
$scanMultiline('/(isset\s*\([^)]*\)|empty\s*\([^)]*\)|\'\'\s*===\s*\$\w+)\s*(&&|\|\|)[^;{]*wp_verify_nonce\s*\(/s', 'compound-nonce-condition');
exit($fail);
