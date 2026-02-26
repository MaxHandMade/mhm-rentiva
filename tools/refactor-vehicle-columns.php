<?php
if (! defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__, 4) . '/');
}

if (! defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI refactor output.

$file = dirname(__DIR__) . '/src/Admin/Vehicle/ListTable/VehicleColumns.php';
$content = file_get_contents($file);

// 1. Status Render Refactor
$pattern1 = '/case \'mhm_available\':.*?v = self::normalize_availability\(\$v\);/s';
$replacement1 = "case 'mhm_available':
				\$v = \\MHMRentiva\\Admin\\Vehicle\\Helpers\\VehicleDataHelper::get_status(\$post_id);";
$content = preg_replace($pattern1, $replacement1, $content);

// 2. Featured Render Refactor
$pattern2 = '/case \'mhm_featured\':.*?is_featured = get_post_meta\(\$post_id, \\\\MHMRentiva\\\\Admin\\\\Core\\\\MetaKeys::VEHICLE_FEATURED, true\) === \'1\';/s';
$replacement2 = "case 'mhm_featured':
				\$is_featured = \\MHMRentiva\\Admin\\Vehicle\\Helpers\\VehicleDataHelper::is_featured(\$post_id);";
$content = preg_replace($pattern2, $replacement2, $content);

// 3. Filter Apply Refactor
$pattern3 = '/\'key\'     => \\\\MHMRentiva\\\\Admin\\\\Core\\\\MetaKeys::VEHICLE_AVAILABILITY,/';
$replacement3 = "'key'     => \\MHMRentiva\\Admin\\Core\\MetaKeys::VEHICLE_STATUS,";
$content = preg_replace($pattern3, $replacement3, $content);

file_put_contents($file, $content);
echo "VehicleColumns.php refactored successfully.\n";
