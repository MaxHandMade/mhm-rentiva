<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Layout;

use MHMRentiva\Layout\Ingestion\AtomicImporter;
use MHMRentiva\Layout\AdapterRegistry;
use PHPUnit\Framework\TestCase;
use Exception;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Atomic Rollback Test
 */
final class AtomicRollbackTest extends TestCase
{
    private AtomicImporter $importer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importer = new AtomicImporter();
        AdapterRegistry::boot_defaults();

        $this->delete_by_slug('rollback-good');
        $this->delete_by_slug('rollback-bad');
        $this->delete_by_slug('rollback-update');
    }

    private function get_base_manifest(): array
    {
        return [
            'version'     => '1.0.0',
            'source'      => ['stitch_project_id' => 'test'],
            'tokens'      => [],
            'components'  => [
                'comp_valid' => [
                    'type' => 'search_hero',
                    'renderer' => 'shortcode:search'
                ]
            ],
            'constraints' => ['performance' => ['max_delta_q' => 0]],
            'pages'       => []
        ];
    }

    public function test_rollback_on_failed_page_composition(): void
    {
        $manifest = $this->get_base_manifest();
        $manifest['pages'] = [
            [
                'id'    => 'good_page',
                'slug'  => 'rollback-good',
                'title' => 'Good Page',
                'layout' => 'full-width',
                'composition' => [
                    ['component_id' => 'comp_valid', 'instance_id' => 'v1']
                ]
            ],
            [
                'id'    => 'bad_page',
                'slug'  => 'rollback-bad',
                'title' => 'Bad Page',
                'layout' => 'full-width',
                'composition' => [
                    ['component_id' => 'invalid_comp', 'instance_id' => 'err_1']
                ]
            ]
        ];

        try {
            $this->importer->import($manifest, ['create' => true]);
            $this->fail('Import should have failed.');
        } catch (Exception $e) {
            $this->assertStringContainsString('Composition error', $e->getMessage());
        }

        wp_cache_flush();
        $good_post = get_page_by_path('rollback-good', OBJECT, 'page');
        $this->assertNull($good_post, 'Good page should have been rolled back (deleted).');
    }

    public function test_rollback_restores_original_content(): void
    {
        $orig_content = 'Original Content';
        $post_id = wp_insert_post([
            'post_title'   => 'Rollback Update',
            'post_name'    => 'rollback-update',
            'post_content' => $orig_content,
            'post_status'  => 'publish',
            'post_type'    => 'page'
        ]);

        $manifest = $this->get_base_manifest();
        $manifest['pages'] = [
            [
                'slug'  => 'rollback-update',
                'title' => 'Rollback Update',
                'layout' => 'full-width',
                'composition' => [
                    ['component_id' => 'comp_valid', 'instance_id' => 'v1']
                ]
            ],
            [
                'slug'  => 'impossible',
                'title' => 'Fail Page',
                'layout' => 'full-width',
                'composition' => [['component_id' => 'fails', 'instance_id' => 'err_1']]
            ]
        ];

        try {
            $this->importer->import($manifest, ['create' => true]);
        } catch (Exception $e) {
            // Expected
        }

        wp_cache_flush();
        clean_post_cache($post_id);

        $post = get_post($post_id);
        $this->assertEquals($orig_content, $post->post_content, 'Rollback failed to restore content.');
    }

    private function delete_by_slug(string $slug): void
    {
        $post = get_page_by_path($slug, OBJECT, 'page');
        if ($post) {
            wp_delete_post($post->ID, true);
        }
        wp_cache_flush();
    }
}
