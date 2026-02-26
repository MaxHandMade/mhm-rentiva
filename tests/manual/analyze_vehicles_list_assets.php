<?php

/**
 * Asset Analyzer for M5 Step 3B
 * Usage: wp eval-file tests/manual/analyze_vehicles_list_assets.php
 */

if (!defined('ABSPATH')) exit;

// Ensure Plugin Active (Check only)
if (!class_exists('MHMRentiva\Plugin')) {
    echo "CRITICAL: MHMRentiva\Plugin class not found. Is the plugin active?\n";
    exit(1);
}

class M5_Asset_Analyzer
{

    public function run()
    {
        echo "Starting Asset Analysis for [rentiva_vehicles_list]...\n\n";

        // Flush 
        wp_cache_flush();
        global $wp_styles;

        // Mock Enqueue
        do_action('wp_enqueue_scripts');
        do_shortcode('[rentiva_vehicles_list]');

        $log = "=== M5 Asset Audit: Vehicles List ===\n\n";

        $queue = $wp_styles->queue;
        $mhm_assets = [];
        $other_assets = [];

        foreach ($queue as $handle) {
            $obj = $wp_styles->registered[$handle] ?? null;
            if (!$obj) continue;

            $src = $obj->src;
            if (is_string($src) && strpos($src, 'mhm-rentiva') !== false) {
                $mhm_assets[] = $this->analyze_handle($handle, $obj);
            } else {
                $other_assets[] = $handle;
            }
        }

        $log .= "Total MHM Assets: " . count($mhm_assets) . "\n";
        $log .= "Total Other Assets: " . count($other_assets) . "\n\n";

        $log .= "--- Plugin CSS Details ---\n";
        foreach ($mhm_assets as $asset) {
            $log .= "[{$asset['handle']}]\n";
            $log .= "    File: {$asset['file']}\n";
            $log .= "    Status: {$asset['status']}\n";
            $log .= "    Deps: {$asset['deps']}\n\n";
        }

        file_put_contents(__DIR__ . '/m5_asset_audit_log.txt', $log);
        echo "Log saved to " . __DIR__ . '/m5_asset_audit_log.txt';
    }

    private function analyze_handle($handle, $obj)
    {
        $src = basename($obj->src);

        $status = "Unknown";
        if (strpos($handle, 'global') !== false) $status = "Always-on (Global)";
        elseif (strpos($handle, 'block') !== false) $status = "Block (Conditional?)";
        elseif (strpos($handle, 'shortcode') !== false) $status = "Shortcode Specific";
        elseif (strpos($handle, 'vehicle-card') !== false) $status = "Component: Card";
        elseif (strpos($handle, 'datepicker') !== false) $status = "Lib: Datepicker";
        elseif (strpos($handle, 'notification') !== false) $status = "Component: Notification";

        return [
            'handle' => $handle,
            'file' => $src,
            'status' => $status,
            'deps' => implode(', ', $obj->deps)
        ];
    }
}

$analyzer = new M5_Asset_Analyzer();
$analyzer->run();
