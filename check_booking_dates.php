<?php
require_once 'c:\xampp\htdocs\otokira\wp-load.php'; // Adjust path if necessary, but this is standard for root execution if placed in root or one level deep.
// Since I am placing it in the plugin dir, I need to go up.
// Actually, I'll calculate path.

if (!defined('ABSPATH')) {
    require_once dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/wp-load.php';
}

$args = [
    'post_type' => 'vehicle_booking',
    'posts_per_page' => 5,
    'orderby' => 'ID', // Order by ID DESC to get latest
    'order' => 'DESC'
];
$bookings = get_posts($args);

echo "Found " . count($bookings) . " bookings.\n";

foreach ($bookings as $b) {
    echo "ID: " . $b->ID . " | Title: " . $b->post_title . "\n";
    $start = get_post_meta($b->ID, '_mhm_start_date', true);
    $end = get_post_meta($b->ID, '_mhm_end_date', true);
    $vehicle = get_post_meta($b->ID, '_mhm_vehicle_id', true);

    echo "Vehicle ID: " . $vehicle . "\n";
    echo "Start Date Meta: '" . $start . "'\n";
    echo "End Date Meta:   '" . $end . "'\n";
    echo "----------------------------------------\n";
}
