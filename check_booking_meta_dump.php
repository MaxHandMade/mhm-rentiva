<?php
$path = 'c:\xampp\htdocs\otokira\wp-load.php';
require_once $path;

$id = 374;
$all_meta = get_post_meta($id);
print_r($all_meta);

echo "\nChecking mhm_bookings table...\n";
global $wpdb;
$table_name = $wpdb->prefix . 'mhm_bookings';
// Check if table exists
if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
    echo "Table $table_name does not exist.\n";
} else {
    // Try to find by post_id or vehicle_id
    $row = $wpdb->get_row("SELECT * FROM $table_name WHERE post_id = $id");
    if ($row) {
        echo "Found in mhm_bookings: \n";
        print_r($row);
    } else {
        echo "Not found in mhm_bookings by post_id.\n";
    }
}
