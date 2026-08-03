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
/**
 * "Cannot measure" is a THIRD outcome, and it must be loud.
 *
 * This gate spent the whole remediation round unrunnable from the Windows host
 * and nobody noticed, because it failed by exiting 2 -- and 2 is neither 0 nor
 * 1, so anything checking "did it fail?" read it as green. A gate that cannot
 * run must be impossible to mistake for a gate that passed.
 */
const GA_PASS          = 0;
const GA_FAIL          = 1;
const GA_CANNOT_MEASURE = 2;

function ga_cannot_measure(string $why, string $how = ''): void
{
    fwrite(STDERR, "G-A CANNOT MEASURE: $why\n");
    if ($how !== '') {
        fwrite(STDERR, "  $how\n");
    }
    fwrite(STDERR, "  This is NOT a pass. Exit code " . GA_CANNOT_MEASURE . " means the gate did not run.\n");
    exit(GA_CANNOT_MEASURE);
}

/**
 * Run phpcs without a shell.
 *
 * The previous version built a shell string ending in `2>/dev/null` and handed
 * it to exec(). cmd.exe cannot parse that redirection, so on Windows the gate
 * exited 2 with "Sistem belirtilen yolu bulamadi" and phpcs never ran at all.
 * proc_open() with an ARRAY argv bypasses the shell on both POSIX and Windows,
 * so no quoting rule applies and stderr is captured on a pipe rather than
 * redirected by the shell. Same fix bin/check-plugin-check-parity.php already
 * carries; this file simply never got it.
 *
 * @param string[] $argv
 * @return array{0:int,1:string,2:string} [exit code, stdout, stderr]
 */
function ga_run(array $argv): array
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

// vendor/bin/phpcs is a PHP script, not a native executable. On POSIX its
// shebang hides that; on Windows there is no shebang and no .bat alongside it,
// so proc_open() cannot start it directly -- which is why the first version of
// this fix still could not run. Invoking it through PHP_BINARY is correct on
// both platforms and depends on no shell.
$phpcs = 'vendor/bin/phpcs';
if (!is_file($phpcs)) {
    ga_cannot_measure('phpcs not found at ' . $phpcs, 'Run composer install first.');
}

[$rc, $stdout, $stderr] = ga_run([
    PHP_BINARY,
    $phpcs,
    '--standard=phpcs.xml',
    '--ignore-annotations',
    '--report=json',
    '--sniffs=WordPress.Security.NonceVerification,WordPress.Security.EscapeOutput,WordPress.Security.ValidatedSanitizedInput',
    'src/', 'templates/', 'mhm-rentiva.php', 'uninstall.php',
]);

// phpcs exits 0 (clean) or 1 (violations found); anything else means it did not
// complete, and its own stderr is the only useful thing we have to report.
if ($rc !== 0 && $rc !== 1) {
    ga_cannot_measure(
        'phpcs exited ' . $rc . ' (expected 0 or 1)',
        trim($stderr) !== '' ? trim($stderr) : 'no stderr output'
    );
}

$json = json_decode($stdout, true);
if (!is_array($json)) {
    ga_cannot_measure(
        'phpcs output could not be parsed as JSON',
        trim($stderr) !== '' ? trim($stderr) : 'stdout was ' . strlen($stdout) . ' bytes'
    );
}
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
