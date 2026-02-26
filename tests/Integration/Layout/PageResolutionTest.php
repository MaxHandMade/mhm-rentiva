<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Layout;

use MHMRentiva\Layout\Ingestion\AtomicImporter;
use MHMRentiva\Layout\AdapterRegistry;
use PHPUnit\Framework\TestCase;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Page Resolution Test
 *
 * @package MHMRentiva\Tests\Integration\Layout
 * @since 4.16.0
 */
final class PageResolutionTest extends TestCase
{
    private AtomicImporter $importer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importer = new AtomicImporter();
        AdapterRegistry::boot_defaults();

        $this->delete_by_slug('target-slug');
        $this->delete_by_slug('other-slug');
        $this->delete_by_slug('existing-slug');
        $this->delete_by_slug('dry-run-page');
        $this->delete_by_slug('missing-page');
    }

    private function get_base_manifest(): array
    {
        return [
            'version'     => '1.0.0',
            'source'      => ['stitch_project_id' => 'test'],
            'tokens'      => [],
            'components'  => [],
            'constraints' => ['performance' => ['max_delta_q' => 0]],
            'pages'       => []
        ];
    }

    public function test_resolution_priority_id_over_slug(): void
    {
        $id_target = wp_insert_post(['post_title' => 'Target', 'post_name' => 'target-slug', 'post_type' => 'page', 'post_status' => 'publish']);
        wp_insert_post(['post_title' => 'Other',  'post_name' => 'other-slug',  'post_type' => 'page', 'post_status' => 'publish']);

        $manifest = $this->get_base_manifest();
        $manifest['pages'] = [
            [
                'post_id'     => $id_target,
                'slug'        => 'other-slug',
                'title'       => 'Update',
                'layout'      => 'full-width',
                'composition' => []
            ]
        ];

        $summary = $this->importer->import($manifest, ['create' => true]);
        $this->assertEquals($id_target, $summary[0]['post_id']);
    }

    public function test_resolution_slug_match_is_update(): void
    {
        $post_id = wp_insert_post(['post_title' => 'Existing', 'post_name' => 'existing-slug', 'post_type' => 'page', 'post_status' => 'publish']);

        $manifest = $this->get_base_manifest();
        $manifest['pages'] = [
            [
                'slug'        => 'existing-slug',
                'title'       => 'Update Existing',
                'layout'      => 'full-width',
                'composition' => []
            ]
        ];

        $summary = $this->importer->import($manifest, ['create' => true]);
        $this->assertEquals('update', $summary[0]['status']);
        $this->assertEquals($post_id, $summary[0]['post_id']);
    }

    public function test_resolution_missing_slug_without_create_flag(): void
    {
        $manifest = $this->get_base_manifest();
        $manifest['pages'] = [
            [
                'slug'        => 'missing-page',
                'title'       => 'Will Not Create',
                'layout'      => 'full-width',
                'composition' => []
            ]
        ];

        $summary = $this->importer->import($manifest, ['create' => false]);
        $this->assertEquals('ignore', $summary[0]['status']);
    }

    public function test_dry_run_no_side_effects(): void
    {
        $manifest = $this->get_base_manifest();
        $manifest['pages'] = [
            [
                'slug'        => 'dry-run-page',
                'title'       => 'Dry Run',
                'layout'      => 'full-width',
                'composition' => []
            ]
        ];

        $summary = $this->importer->dry_run($manifest, ['create' => true]);
        $this->assertEquals('create', $summary[0]['status']);
        $this->assertNull(get_page_by_path('dry-run-page', OBJECT, 'page'));
    }

    private function delete_by_slug(string $slug): void
    {
        $post = get_page_by_path($slug, OBJECT, 'page');
        if ($post) {
            wp_delete_post($post->ID, true);
        }
    }
}
