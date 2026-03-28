<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Layout;

use MHMRentiva\Layout\CompositionBuilder;
use MHMRentiva\Layout\AdapterRegistry;
use PHPUnit\Framework\TestCase;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Performance Delta Q Test
 *
 * Enforces ΔQ <= 0 for the Layout Pipeline rendering phase.
 * Includes warm-up cycles and SAVEQUERIES deterministic reset.
 *
 * @package MHMRentiva\Tests\Integration\Layout
 */
final class PerformanceDeltaQTest extends TestCase
{
    private CompositionBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new CompositionBuilder();
        AdapterRegistry::boot_defaults();

        // Ensure SAVEQUERIES is enabled for monitoring if not already
        if (! defined('SAVEQUERIES')) {
            define('SAVEQUERIES', true);
        }
    }

    /**
     * Measure the performance delta of rendering a composition.
     * 
     * Target: ΔQ (Query Count) should not increase compared to baseline.
     * Target: ΔT (Execution Time) should be within negligible overhead limits.
     */
    public function test_render_performance_delta_q(): void
    {
        global $wpdb;

        $manifest = $this->get_standard_manifest();
        $page = $manifest['pages'][0];

        // 1. Warm-up Phase (3 cycles to prime caches/autoloaders)
        for ($i = 0; $i < 3; $i++) {
            $this->builder->build($manifest, $page);
        }

        // 2. Baseline Measurement (Simulate non-layout render overhead)
        // Reset Query Log
        $wpdb->queries = [];
        $start_time = microtime(true);

        // (Null render or simple shortcode execution baseline)
        do_shortcode('[rentiva_unified_search]');

        $baseline_queries = count($wpdb->queries);
        $baseline_time = microtime(true) - $start_time;

        // 3. Layout Pipeline Measurement
        $wpdb->queries = [];
        $start_time = microtime(true);

        $result = $this->builder->build($manifest, $page);

        $layout_queries = count($wpdb->queries);
        $layout_time = microtime(true) - $start_time;

        $this->assertIsString($result);

        // Delta Q enforcement
        $delta_q = $layout_queries - $baseline_queries;
        $this->assertLessThanOrEqual(
            0,
            $delta_q,
            sprintf('ΔQ Violation: Layout pipeline added %d queries over baseline.', $delta_q)
        );

        // Soft Delta T advisory (neglecting for strict WP-CLI variance)
        // Expected overhead should be < 5ms for assembly logic.
    }

    private function get_standard_manifest(): array
    {
        return [
            'version' => '1.0.0',
            'source'  => [],
            'pages'   => [
                [
                    'id'          => 'p1',
                    'slug'        => 'test',
                    'title'       => 'Test',
                    'layout'      => [],
                    'composition' => [
                        [
                            'component_id' => 'search1',
                            'instance_id'  => 'inst1',
                            'attributes'   => ['layout' => 'horizontal']
                        ]
                    ],
                    'slots'  => [],
                    'assets' => []
                ]
            ],
            'tokens'      => [],
            'components'  => [
                'search1' => ['type' => 'search_hero']
            ],
            'constraints' => [
                'forbidden'   => [],
                'contract'    => [],
                'performance' => ['max_delta_q' => 0]
            ]
        ];
    }
}
