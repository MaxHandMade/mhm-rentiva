<?php

/**
 * Benchmark Script for M5 Performance Audit
 * Usage: wp eval-file tests/manual/benchmark_m5.php
 */

if (!defined('ABSPATH')) {
    exit('Direct access not allowed.');
}

if (!defined('SAVEQUERIES')) {
    define('SAVEQUERIES', true);
}

class M5_Benchmark
{

    private $results = [];

    public function run()
    {
        echo "Starting M5 Benchmark...\n";

        if (!class_exists('MHMRentiva\Plugin')) {
            echo "CRITICAL: MHMRentiva\Plugin class not found. Plugin inactive?\n";
            return;
        }

        $vehicle_id = $this->get_or_create_vehicle();
        if (!$vehicle_id) {
            echo "CRITICAL: Could not obtain a valid vehicle ID.\n";
            return;
        }
        echo "Context: Using Vehicle ID #$vehicle_id\n\n";

        $scenarios = [
            'booking_form' => "[rentiva_booking_form id='$vehicle_id']",
            'calendar' => "[rentiva_availability_calendar id='$vehicle_id']",
            'search_results' => '[rentiva_search_results]',
            'vehicle_list' => '[rentiva_vehicles_list]',
        ];

        foreach ($scenarios as $name => $shortcode) {
            echo "Scenario: $name\n";

            // Cold Run
            wp_cache_flush();
            $this->measure($name, 'Cold', $shortcode);

            // Warm Run
            $this->measure($name, 'Warm', $shortcode);

            // Hot Run
            $this->measure($name, 'Hot', $shortcode);
        }

        // Save Results
        file_put_contents(__DIR__ . '/benchmark_results.json', json_encode($this->results, JSON_PRETTY_PRINT));
        echo "Results saved to " . __DIR__ . '/benchmark_results.json' . "\n";
    }

    private function get_or_create_vehicle()
    {
        $any_product = get_posts(['post_type' => 'product', 'posts_per_page' => 1]);
        if (!empty($any_product)) {
            $p = $any_product[0];
            wp_set_object_terms($p->ID, 'mhm_vehicle_rental', 'product_type'); // Ensure it's a rental
            return $p->ID;
        }
        return null; // Should not happen in dev env
    }

    private function measure($scenario, $type, $shortcode)
    {
        global $wpdb;
        gc_collect_cycles();

        $start_time = microtime(true);
        $start_mem = memory_get_peak_usage();
        $start_queries = get_num_queries();

        // EXECUTE
        $output = do_shortcode($shortcode);

        $end_time = microtime(true);
        $end_mem = memory_get_peak_usage();
        $end_queries = get_num_queries();

        $duration = round(($end_time - $start_time) * 1000, 2);
        $memory = round(($end_mem - $start_mem) / 1024 / 1024, 3);
        $queries = $end_queries - $start_queries;

        $asset_count = $this->count_assets();
        $assets_str = "CSS:{$asset_count['css']}/JS:{$asset_count['js']}";
        $len = strlen($output);

        $this->results[$scenario][$type] = [
            'duration' => $duration,
            'memory' => $memory,
            'queries' => $queries,
            'assets' => $assets_str,
            'length' => $len
        ];

        echo "Recorded: $scenario ($type)\n";
    }

    private function count_assets()
    {
        global $wp_scripts, $wp_styles;
        return [
            'js' => count($wp_scripts->queue ?? []),
            'css' => count($wp_styles->queue ?? [])
        ];
    }
}

$benchmark = new M5_Benchmark();
$benchmark->run();
