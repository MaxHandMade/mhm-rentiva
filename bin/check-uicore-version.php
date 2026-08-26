<?php
/**
 * Locks the ui-core version literal to the package actually installed.
 *
 * mhm-rentiva.php calls mhmuicore_register( '<version>', ... ) with a hand-written
 * string. It has to be hand-written: registration runs before any ui-core bootstrap
 * loads, so the value cannot be read from the package at that point.
 *
 * Why that matters. The registry keeps one entry per registrant and boots the HIGHEST
 * version among them (vendor/mhm/ui-core/register.php). A literal lower than the copy
 * it describes does not fail loudly -- it makes this plugin under-report itself, so a
 * second plugin shipping an OLDER ui-core wins the selection and its bootstrap is the
 * one that loads. That is precisely the failure the version-aware loader exists to
 * prevent, and it is invisible while this plugin is the only registrant.
 *
 * Measured on 2026-08-26: the literal said 0.2.0 while vendor carried 0.3.2, and it had
 * drifted through three package releases without anything noticing. An independent audit
 * found it, not a gate -- hence this gate.
 *
 * Exit codes:
 *   0 - the literal matches the installed package
 *   1 - mismatch
 *   2 - the gate could not run (file or version unreadable). Never reported as success:
 *       a gate that cannot see its inputs must fail loudly rather than pass vacuously.
 *
 * Usage: php bin/check-uicore-version.php
 *
 * @package MHM_Rentiva
 */

declare(strict_types=1);

$root = dirname(__DIR__);

$plugin_file    = $root . '/mhm-rentiva.php';
$bootstrap_file = $root . '/vendor/mhm/ui-core/bootstrap.php';

foreach (array($plugin_file, $bootstrap_file) as $required) {
    if (! is_readable($required)) {
        fwrite(STDERR, "check-uicore-version: cannot read {$required}\n");
        fwrite(STDERR, "Run `composer install` first; this gate needs the installed package.\n");
        exit(2);
    }
}

$plugin_src    = (string) file_get_contents($plugin_file);
$bootstrap_src = (string) file_get_contents($bootstrap_file);

// The literal is the first argument of the mhmuicore_register() call.
if (preg_match('/mhmuicore_register\(\s*[\'"]([^\'"]+)[\'"]/', $plugin_src, $m) !== 1) {
    fwrite(STDERR, "check-uicore-version: no mhmuicore_register( '<version>' ) call found in mhm-rentiva.php\n");
    exit(2);
}
$declared = $m[1];

if (preg_match('/define\(\s*[\'"]MHMUICORE_VERSION[\'"]\s*,\s*[\'"]([^\'"]+)[\'"]/', $bootstrap_src, $m) !== 1) {
    fwrite(STDERR, "check-uicore-version: MHMUICORE_VERSION not found in the installed package\n");
    exit(2);
}
$installed = $m[1];

if ($declared !== $installed) {
    fwrite(STDERR, "\n[X] ui-core version literal is out of step with the installed package.\n\n");
    fwrite(STDERR, "  mhm-rentiva.php registers : {$declared}\n");
    fwrite(STDERR, "  vendor/mhm/ui-core is     : {$installed}\n\n");
    fwrite(STDERR, "  Update the literal in the mhmuicore_register() call. Under-reporting lets a\n");
    fwrite(STDERR, "  plugin shipping an older ui-core win the version selection.\n\n");
    exit(1);
}

echo "[OK] ui-core version literal matches the installed package ({$installed}).\n";
exit(0);
