<?php

/**
 * Query Analyzer for M5 Step 3A
 * Usage: wp eval-file tests/manual/analyze_search_queries.php
 */

if (!defined('ABSPATH')) exit;

if (!defined('SAVEQUERIES')) {
    define('SAVEQUERIES', true);
}

// Ensure Plugin Active (Check only)
if (!class_exists('MHMRentiva\Plugin')) {
    echo "CRITICAL: MHMRentiva\Plugin class not found. Is the plugin active?\n";
    exit(1);
}

class M5_Query_Analyzer
{

    public function run()
    {
        echo "Starting Query Analysis for [rentiva_search_results]...\n\n";

        // Warm up cache first
        wp_cache_flush();
        do_shortcode('[rentiva_search_results]'); // Cold
        do_shortcode('[rentiva_search_results]'); // Warm 1

        // HOT RUN - Capture Queries
        global $wpdb;
        $wpdb->queries = []; // Reset log inside WPDB

        echo "Executing HOT Run...\n";
        $start = microtime(true);
        $output = do_shortcode('[rentiva_search_results]');
        $duration = (microtime(true) - $start) * 1000;

        echo "Duration: " . round($duration, 2) . "ms\n";
        echo "Captured Queries: " . count($wpdb->queries) . "\n\n";

        $by_type = [];

        foreach ($wpdb->queries as $q) {
            $sql = $q[0];
            $time = $q[1];
            $stack = $q[2];

            // Categorize
            $type = 'Other';
            if (strpos($sql, 'wp_users') !== false) $type = 'User/Auth';
            elseif (strpos($sql, 'wp_posts') !== false) $type = 'WP_Query (Posts)';
            elseif (strpos($sql, 'postmeta') !== false) $type = 'WP_Query (Meta)';
            elseif (strpos($sql, 'wp_options') !== false) $type = 'Options';
            elseif (strpos($sql, 'terms') !== false) $type = 'Taxonomy';

            $by_type[$type][] = [
                'sql' => $sql,
                'time' => $time,
                'stack' => $stack
            ];
        }

        // Output Report to File
        $log = "=== M5 Search Query Analysis ===\n";
        $log .= "Duration: " . round($duration, 2) . "ms\n";
        $log .= "Captured Queries: " . count($wpdb->queries) . "\n\n";

        foreach ($by_type as $type => $queries) {
            $log .= "=== $type (" . count($queries) . ") ===\n";
            foreach ($queries as $i => $q) {
                $sql = substr(preg_replace('/\s+/', ' ', $q['sql']), 0, 200);
                $log .= "[$i] $sql\n";

                // Stack Trace - Find plugin caller
                $stack_parts = explode(',', $q['stack']);
                $caller = 'Unknown';
                foreach (array_reverse($stack_parts) as $s) {
                    if (strpos($s, 'MHMRentiva') !== false) {
                        $caller = $s;
                        break;
                    }
                }
                if ($caller === 'Unknown') $caller = end($stack_parts); // Fallback

                $log .= "    Caller: $caller\n";
                $log .= "    Time: {$q['time']}s\n\n";
            }
        }

        file_put_contents(__DIR__ . '/m5_search_query_log.txt', $log);
        echo "Log saved to " . __DIR__ . '/m5_search_query_log.txt';
    }
}

$analyzer = new M5_Query_Analyzer();
$analyzer->run();
