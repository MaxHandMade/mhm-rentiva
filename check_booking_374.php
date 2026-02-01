<?php
// Try explicit path
$path = 'c:\xampp\htdocs\otokira\wp-load.php';
if (!file_exists($path)) {
    die("wp-load.php not found at $path");
}
require_once $path;

$ids = [374];
foreach ($ids as $id) {
    try {
        $b = get_post($id);
        if (!$b) {
            echo "Booking $id not found in DB.\n";
            continue;
        }
        echo "ID: " . $b->ID . " | Title: " . $b->post_title . "\n";
        $start = get_post_meta($b->ID, '_mhm_start_date', true);
        $end = get_post_meta($b->ID, '_mhm_end_date', true);
        $vehicle = get_post_meta($b->ID, '_mhm_vehicle_id', true);

        echo "Vehicle ID: " . $vehicle . "\n";
        echo "Start Date Meta: '" . $start . "'\n";
        echo "End Date Meta:   '" . $end . "'\n";
        echo "----------------------------------------\n";
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
