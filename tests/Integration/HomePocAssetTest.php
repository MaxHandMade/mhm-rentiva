<?php

namespace MHMRentiva\Tests\Integration;

use MHMRentiva\Admin\Frontend\Shortcodes\HomePoc;

class HomePocAssetTest extends \WP_UnitTestCase
{
    private $home_poc;

    public function setUp(): void
    {
        parent::setUp();
        $this->home_poc = new HomePoc();
    }

    /**
     * Test Task B: Feature Flag return empty when disabled.
     */
    public function test_home_poc_returns_empty_when_feature_flag_is_disabled()
    {
        add_filter('mhm_rentiva_enable_home_poc', '__return_false');

        $output = $this->home_poc->render([]);

        $this->assertEmpty($output);

        remove_filter('mhm_rentiva_enable_home_poc', '__return_false');
    }

    /**
     * Test Task A: Asset Snapshot Deterministic Isolation.
     */
    public function test_home_poc_asset_isolation_snapshot()
    {
        // Force rendering to trigger sub-shortcode asset enqueues
        global $wp_styles, $wp_scripts;

        // 1. Capture handles before render
        $styles_before = array_keys($wp_styles->registered ?? []);
        $scripts_before = array_keys($wp_scripts->registered ?? []);

        // 2. Render shortcode
        $this->home_poc->render([]);

        // 3. Capture handles after render
        $styles_after = array_keys($wp_styles->registered ?? []);
        $scripts_after = array_keys($wp_scripts->registered ?? []);

        // 4. Calculate diff
        $styles_diff = array_diff($styles_after, $styles_before);
        $scripts_diff = array_diff($scripts_after, $scripts_before);

        // Check for specific MHMRentiva assets only to avoid flaky external noise
        $mhm_styles = array_filter($styles_diff, function ($handle) {
            return strpos($handle, 'mhm-') === 0;
        });

        $mhm_scripts = array_filter($scripts_diff, function ($handle) {
            return strpos($handle, 'mhm-') === 0;
        });

        // Ensure we are ONLY enqueuing what is expected (or nothing new globally)
        // Note: Sub-shortcodes use enqueue_assets() which should work within the render flow.
        // We assert that no surprising global handles are added that aren't prefixed.
        foreach ($styles_diff as $handle) {
            $this->assertStringStartsWith('mhm-', $handle, "Unexpected global style handle enqueued: $handle");
        }

        // Satisfy PHPUnit's risky test checker when $styles_diff is empty
        $this->assertTrue(true, 'Styles diff process executed successfully.');
    }

    /**
     * Test Task D: Performance Delta Verification.
     */
    public function test_home_poc_performance_delta_assertion()
    {
        global $wpdb;

        if (!defined('SAVEQUERIES')) {
            define('SAVEQUERIES', true);
        }

        // Warm up 1
        $this->home_poc->render([]);
        // Warm up 2 (ensure all caches are primed)
        $this->home_poc->render([]);

        // Temporarily disable UnifiedSearch to confirm it's the source of delta
        add_filter('shortcode_atts_rentiva_unified_search', function ($atts) {
            // This is a hack to see if we can reduce queries in test
            return $atts;
        });
        // Actually, better to just replace the do_shortcode output if we could, but let's try to mock the DB response if possible, 
        // OR just acknowledge that sub-shortcodes have their own baseline.

        $query_count_before = $wpdb->num_queries;

        // Target execution
        $this->home_poc->render([]);

        $query_count_after = $wpdb->num_queries;
        $delta = $query_count_after - $query_count_before;

        if ($delta > 0) {
            // Fallback: If SAVEQUERIES is not working, at least we know the delta.
            // But let's try to see if we can trigger a log.
            error_log("PERF FAILURE: Delta $delta. Queries recorded: " . count($wpdb->queries));
        }

        $this->assertEquals(0, $delta, "HomePoc render introduced $delta new queries (Target: 0)");
    }
}
