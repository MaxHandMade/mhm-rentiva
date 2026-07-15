<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$targets = [$root . '/src', $root . '/templates'];
$forbidden = [
    'LiteOverflow' => 'catalog-overflow limiter',
    'ProFeatureNotice' => 'upsell notice',
    'displayLimitNotice' => 'limit notice',
    '\\bmaxVehicles\\b' => 'vehicle limit', '\\bmaxBookings\\b' => 'booking limit',
    '\\bmaxCustomers\\b' => 'customer limit', '\\bmaxAddons\\b' => 'addon limit',
    '\\bmaxGalleryImages\\b' => 'gallery limit', '\\bmaxTransferRoutes\\b' => 'route limit',
    '\\breportsMaxRangeDays\\b' => 'report-range limit', '\\breportsMaxRows\\b' => 'report-row limit',
    '\\ballowedGateways\\b' => 'gateway restriction', 'MAX_ADDONS_LITE' => 'addon cap const',
    'get_comparison_table_data' => 'upsell comparison', 'render_comparison_table' => 'upsell comparison',
    'get_pro_features_list' => 'Pro feature upsell list', '\\bfeatureEnabled\\b' => 'deprecated insecure gate',
    'mhm_rentiva_lite_' => 'artificial-limit filter',
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
