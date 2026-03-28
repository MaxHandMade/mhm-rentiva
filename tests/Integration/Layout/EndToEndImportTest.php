<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Layout;

use MHMRentiva\Layout\CLI\LayoutImportCommand;
use PHPUnit\Framework\TestCase;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * End-To-End Layout Import Pipeline Test
 * 
 * Verifies that the full pipeline (Validation -> Composition -> Ingestion)
 * works as expected through the CLI command.
 * 
 * @package MHMRentiva\Tests\Integration\Layout
 * @since 4.14.0
 */
final class EndToEndImportTest extends TestCase
{
    private LayoutImportCommand $command;
    private int $test_post_id;

    protected function setUp(): void
    {
        parent::setUp();
        $this->command = new LayoutImportCommand();

        // Create a blank page for testing updates
        $this->test_post_id = wp_insert_post([
            'post_title'   => 'E2E Test Page',
            'post_content' => '',
            'post_status'  => 'publish',
            'post_type'    => 'page'
        ]);
    }

    protected function tearDown(): void
    {
        wp_delete_post($this->test_post_id, true);
        parent::tearDown();
    }

    /**
     * Test full pipeline persistence.
     * Manifest-driven resolution (v4.16+)
     */
    public function test_full_pipeline_persistence(): void
    {
        $manifest_path = $this->create_temp_manifest();

        $args = [$manifest_path];
        $assoc_args = []; // --post_id is deprecated in favor of manifest-driven resolution

        // Execute command logic
        $this->command->import($args, $assoc_args);

        // 1. Verify Post Content
        $post = get_post($this->test_post_id);
        $this->assertStringContainsString('mhm-layout-component', $post->post_content);
        $this->assertStringContainsString('[rentiva_unified_search', $post->post_content);

        // 2. Verify Post Meta (SSOT Manifest)
        $meta = get_post_meta($this->test_post_id, '_mhm_layout_manifest', true);
        $this->assertIsArray($meta);
        $this->assertEquals('1.0.0', $meta['version']);
        $this->assertEquals('proj_e2e', $meta['source']['stitch_project_id']);

        if (file_exists($manifest_path)) {
            unlink($manifest_path);
        }
    }

    private function create_temp_manifest(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'manifest');
        $data = [
            'version' => '1.0.0',
            'source'  => ['stitch_project_id' => 'proj_e2e'],
            'pages'   => [
                [
                    'post_id'     => $this->test_post_id, // v4.16+ mapping
                    'id'          => 'p1',
                    'slug'        => 'e2e',
                    'title'       => 'E2E',
                    'layout'      => 'full-width',
                    'composition' => [
                        [
                            'component_id' => 's1',
                            'instance_id'  => 'inst1',
                            'attributes'   => ['layout' => 'horizontal']
                        ]
                    ]
                ]
            ],
            'tokens'      => [],
            'components'  => [
                's1' => ['type' => 'search_hero']
            ],
            'constraints' => [
                'performance' => ['max_delta_q' => 0]
            ]
        ];
        file_put_contents($path, json_encode($data));
        return $path;
    }
}
