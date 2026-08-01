<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$targets = [$root . '/src', $root . '/templates'];
/*
 * The limit/upsell API was always a set of Mode/Restrictions STATIC METHODS
 * (Mode::maxVehicles(), Mode::allowedGateways(), ...), so those patterns match
 * the call shape `::name(` rather than the bare identifier.
 *
 * Matching bare identifiers produced false positives that have nothing to do
 * with licensing and would have to be renamed purely to appease this script:
 *   - $allowedGateways in BookingColumns/LogColumns is a local listing EVERY
 *     WooCommerce gateway for an admin filter dropdown.
 *   - 'maxVehicles' in AllowlistRegistry is the camelCase alias of the
 *     `max_vehicles` shortcode attribute -- a user's own display cap.
 * Scoping to `::name(` still catches any resurrection of the real API, which is
 * what this gate exists to prevent.
 *
 * Distinctive names (LiteOverflow, ProFeatureNotice, ...) stay unscoped: they
 * cannot collide with ordinary code.
 */
$forbidden = [
    'LiteOverflow' => 'catalog-overflow limiter',
    'ProFeatureNotice' => 'upsell notice',
    'displayLimitNotice' => 'limit notice',
    '::\\s*maxVehicles\\s*\\(' => 'vehicle limit', '::\\s*maxBookings\\s*\\(' => 'booking limit',
    '::\\s*maxCustomers\\s*\\(' => 'customer limit', '::\\s*maxAddons\\s*\\(' => 'addon limit',
    '::\\s*maxGalleryImages\\s*\\(' => 'gallery limit', '::\\s*maxTransferRoutes\\s*\\(' => 'route limit',
    '::\\s*reportsMaxRangeDays\\s*\\(' => 'report-range limit', '::\\s*reportsMaxRows\\s*\\(' => 'report-row limit',
    '::\\s*allowedGateways\\s*\\(' => 'gateway restriction', 'MAX_ADDONS_LITE' => 'addon cap const',
    'get_comparison_table_data' => 'upsell comparison', 'render_comparison_table' => 'upsell comparison',
    'get_pro_features_list' => 'Pro feature upsell list', '::\\s*featureEnabled\\s*\\(' => 'deprecated insecure gate',
    'mhmrentiva_lite_' => 'artificial-limit filter',
    '\\bRestrictions::' => 'license restriction engine',
];
$hits = [];
foreach ($targets as $dir) {
    if (!is_dir($dir)) { continue; }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if ($file->getExtension() !== 'php') { continue; }
        $code = (string) file_get_contents($file->getPathname());
        $rel = str_replace([$root . DIRECTORY_SEPARATOR, '\\'], ['', '/'], $file->getPathname());
        foreach ($forbidden as $p => $why) { if (preg_match('/' . $p . '/', $code)) { $hits[] = "$rel  [$p]  ($why)"; } }
    }
}
foreach (glob($root . '/*.php') as $f) {
    $code = (string) file_get_contents($f);
    foreach ($forbidden as $p => $why) { if (preg_match('/' . $p . '/', $code)) { $hits[] = basename($f) . "  [$p]  ($why)"; } }
}
if ($hits !== []) { echo "Crippleware found in Lite:\n\n" . implode("\n", $hits) . "\n\n" . count($hits) . " found.\n"; exit(1); }
echo "[OK] Lite is crippleware-free.\n"; exit(0);
