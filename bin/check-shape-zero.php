<?php
/**
 * Gate G-A: zero visible security shapes under --ignore-annotations.
 *
 * Allowed residual class (ONLY ONE): NonceVerification.Recommended on
 * capability-gated admin list-table DISPLAY params (sort/filter/paginate GET
 * reads). These are enumerated in $allowed below with a HARD CEILING; the gate
 * fails if a new one appears or if any other family fires at all.
 *
 * 🔴 AbstractListTable.php is DELIBERATELY EXCLUDED from $allowedFiles even
 * though its get_text_param() is a display-only GET read of the same shape as
 * the four files below. Reason: AbstractListTable.php:450 is one of the FIVE
 * shown examples in the T7 rejection letter itself. Leaving a reviewer-named
 * file in a residual class this gate never surfaces again risks the exact
 * "same category resurfaces" outcome T4 said ends the review permanently.
 *
 * Görev 9 resolved the OTHER two families in that file to zero (the
 * filter_input() readers became canonical reads; the $_SERVER and $_POST reads
 * are sanitized on their own lines, the latter next to the nonce check in
 * handle_bulk_actions()). What remains, and what this gate now counts as HARD,
 * is NonceVerification.Recommended on the single surviving $_GET reader:
 *
 *   AbstractListTable::get_text_param()  ->  3 hits over 2 lines
 *
 * It cannot be resolved by the get_query_var() mechanism the four allowed files
 * use. Those live on edit.php, where wp_edit_posts_query() calls wp() and so
 * WP::parse_request() fills the query vars; this base class serves
 * `admin.php?page=...` screens, and wp-admin/admin.php never calls wp() — a
 * query var would read back empty and sorting/search/filter preservation would
 * silently die. Nor can it be nonce-gated: these are bookmarkable display
 * params. It is therefore named explicitly in the Görev 17 response letter
 * (see that task's Adım 1), never annotated away.
 * Rationale: recorded in docs/plans/2026-07-31-wporg-t7-duzeltme-turu.md.
 */
$families = [
    'WordPress.Security.NonceVerification.Missing'                => 0,
    'WordPress.Security.EscapeOutput'                             => 0, // tüm alt-sniff'ler
    'WordPress.Security.ValidatedSanitizedInput'                  => 0,
];
$cmd = 'vendor/bin/phpcs --standard=phpcs.xml --ignore-annotations --report=json '
     . '--sniffs=WordPress.Security.NonceVerification,WordPress.Security.EscapeOutput,WordPress.Security.ValidatedSanitizedInput '
     . 'src/ templates/ mhm-rentiva.php uninstall.php 2>/dev/null';
exec($cmd, $out, $rc);
$json = json_decode(implode('', $out), true);
if (!is_array($json)) { fwrite(STDERR, "G-A: phpcs çıktısı ayrıştırılamadı\n"); exit(2); }
$violations = [];
foreach (($json['files'] ?? []) as $file => $data) {
    foreach ($data['messages'] as $m) { $violations[] = [$file, $m['line'], $m['source']]; }
}
// Recommended-display kalıntıları: dosya bazlı sabit tavan (AbstractListTable.php YOK — yukarıdaki gerekçe).
$allowedFiles = ['LogColumns.php', 'BookingColumns.php', 'VehicleColumns.php', 'AddonListTable.php'];
$hard = []; $residual = 0;
// $families'i (yukarıda tanımlı) dolduran gerçek sayaç: brief'teki taslak bu diziyi
// tanımlıyor ama hiç kullanmıyordu -- "aile başına sayı" arayüz sözü bu değişiklikle
// tutuluyor. NonceVerification.Recommended tavan-dışı (hard) isabetler üç aileden
// hiçbirine girmez (Missing değil, Escape/Sanitize hiç değil), o yüzden ayrı sayılır --
// aksi halde aile toplamı hard toplamını açıklamadan sessizce eksik kalırdı.
$nonceRecommendedHard = 0;
foreach ($violations as [$f, $l, $s]) {
    $isRecommended = str_contains($s, 'NonceVerification.Recommended');
    $isAllowedFile = (bool) array_filter($allowedFiles, fn($a) => str_ends_with($f, $a));
    if ($isRecommended && $isAllowedFile) { $residual++; continue; }

    if (str_contains($s, 'NonceVerification.Missing')) {
        $families['WordPress.Security.NonceVerification.Missing']++;
    } elseif ($isRecommended) {
        $nonceRecommendedHard++;
    } elseif (str_contains($s, 'EscapeOutput')) {
        $families['WordPress.Security.EscapeOutput']++;
    } elseif (str_contains($s, 'ValidatedSanitizedInput')) {
        $families['WordPress.Security.ValidatedSanitizedInput']++;
    }

    $hard[] = "$f:$l  $s";
}
$CEILING = 0; // Görev 10 sonunda ölçülen gerçek kalıntıyla güncellenir ve bir daha ARTAMAZ.
printf("G-A: hard=%d, residual-display=%d (tavan %d)\n", count($hard), $residual, $CEILING);
printf(
    "  families: NonceVerification.Missing=%d, EscapeOutput=%d, ValidatedSanitizedInput=%d, NonceVerification.Recommended(non-residual, hard)=%d\n",
    $families['WordPress.Security.NonceVerification.Missing'],
    $families['WordPress.Security.EscapeOutput'],
    $families['WordPress.Security.ValidatedSanitizedInput'],
    $nonceRecommendedHard
);
foreach ($hard as $h) { echo "  $h\n"; }
exit ((count($hard) > 0 || $residual > $CEILING) ? 1 : 0);
