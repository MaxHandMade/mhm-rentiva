<?php
/**
 * Gate G-A: zero visible security shapes under --ignore-annotations.
 *
 * Allowed residual class (ONLY ONE): NonceVerification.Recommended on
 * sanitized, read-only routing/display GET parameters. These are enumerated by
 * file below with per-file and global HARD CEILINGS; the gate fails if a new
 * file appears, an existing file grows, or any other security family fires.
 *
 * The reviewer-named AbstractListTable implementation was unreachable from any
 * rendered screen and has been removed rather than allowlisted. The residual
 * files below contain only live, read-only display parameters.
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
$allowedByFile = [
    'AbstractPostType.php'        => 2,
    'CustomersPage.php'          => 4,
    'EmailTemplates.php'         => 2,
    'AccountController.php'      => 6,
    'UserDashboard.php'          => 2,
    'Settings.php'               => 2,
    'SetupWizard.php'            => 2,
    'VehicleSettings.php'        => 2,
];
$residualByFile = array_fill_keys(array_keys($allowedByFile), 0);
$hard = []; $residual = 0;
// $families'i (yukarıda tanımlı) dolduran gerçek sayaç: brief'teki taslak bu diziyi
// tanımlıyor ama hiç kullanmıyordu -- "aile başına sayı" arayüz sözü bu değişiklikle
// tutuluyor. NonceVerification.Recommended tavan-dışı (hard) isabetler üç aileden
// hiçbirine girmez (Missing değil, Escape/Sanitize hiç değil), o yüzden ayrı sayılır --
// aksi halde aile toplamı hard toplamını açıklamadan sessizce eksik kalırdı.
$nonceRecommendedHard = 0;
foreach ($violations as [$f, $l, $s]) {
    $isRecommended = str_contains($s, 'NonceVerification.Recommended');
    $basename = basename($f);
    $isAllowedFile = array_key_exists($basename, $allowedByFile);
    if ($isRecommended && $isAllowedFile) {
        $residual++;
        $residualByFile[$basename]++;
        continue;
    }

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
$CEILING = 22; // 2026-08-08 gerçek Plugin Check satır envanteri; bir daha ARTAMAZ.
$overCeiling = [];
foreach ($residualByFile as $file => $count) {
    if ($count > $allowedByFile[$file]) {
        $overCeiling[] = "$file: $count > {$allowedByFile[$file]}";
    }
}
// The shipped surface phpcs.xml refuses to look at.
//
// The scan above runs --standard=phpcs.xml, and phpcs.xml excludes */vendor/*.
// vendor/mhm/ui-core SHIPS (.distignore re-includes it), so its runtime code
// was never measured by any gate: G-A could not see it, and the Plugin Check
// parity gate that can see it obeys phpcs:ignore. A suppressed EscapeOutput in
// there would have passed every gate we own. Proven, not assumed: a file with
// `echo $_GET['x'];` placed in vendor/mhm/ui-core/src/ produced 0 findings
// under --standard=phpcs.xml and 4 under --standard=WordPress.
//
// Zero tolerance rather than a ceiling: these paths are clean today (measured),
// and there is no legacy debt here to grandfather.
$extraPaths = array_values(array_filter(
    ['vendor/mhm/', 'assets/', 'languages/'],
    static fn(string $p): bool => is_dir($p)
));

$extraFindings = [];
if ($extraPaths !== []) {
    [$rc2, $stdout2, $stderr2] = ga_run(array_merge([
        PHP_BINARY,
        $phpcs,
        '--standard=WordPress',
        '--ignore-annotations',
        '--report=json',
        '--sniffs=WordPress.Security.NonceVerification,WordPress.Security.EscapeOutput,WordPress.Security.ValidatedSanitizedInput',
    ], $extraPaths));

    if ($rc2 !== 0 && $rc2 !== 1) {
        ga_cannot_measure(
            'phpcs exited ' . $rc2 . ' on the shipped-but-unscanned paths (expected 0 or 1)',
            trim($stderr2) !== '' ? trim($stderr2) : 'no stderr output'
        );
    }

    $json2 = json_decode($stdout2, true);
    if (!is_array($json2)) {
        ga_cannot_measure('phpcs returned unparseable JSON for the shipped-but-unscanned paths', substr($stdout2, 0, 400));
    }

    foreach (($json2['files'] ?? []) as $file => $data) {
        foreach (($data['messages'] ?? []) as $msg) {
            $extraFindings[] = sprintf('%s:%d  %s', $file, $msg['line'] ?? 0, $msg['source'] ?? '?');
        }
    }
}

printf("G-A: hard=%d, residual-display=%d (tavan %d)\n", count($hard), $residual, $CEILING);
printf("  shipped-but-unscanned paths (%s): %d finding(s)\n", implode(' ', $extraPaths), count($extraFindings));
foreach ($extraFindings as $f) { echo "  $f\n"; }
printf(
    "  families: NonceVerification.Missing=%d, EscapeOutput=%d, ValidatedSanitizedInput=%d, NonceVerification.Recommended(non-residual, hard)=%d\n",
    $families['WordPress.Security.NonceVerification.Missing'],
    $families['WordPress.Security.EscapeOutput'],
    $families['WordPress.Security.ValidatedSanitizedInput'],
    $nonceRecommendedHard
);
foreach ($hard as $h) { echo "  $h\n"; }
foreach ($overCeiling as $row) { echo "  residual ceiling exceeded: $row\n"; }
exit ((count($hard) > 0 || $residual > $CEILING || count($overCeiling) > 0 || count($extraFindings) > 0) ? 1 : 0);
