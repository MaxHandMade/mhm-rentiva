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
 * Multi-Page Atomic Import Test
 *
 * @package MHMRentiva\Tests\Integration\Layout
 * @since 4.16.0
 */
final class MultiPageAtomicImportTest extends TestCase
{
    private AtomicImporter $importer;
    private array $manifest;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importer = new AtomicImporter();
        AdapterRegistry::boot_defaults();

        $fixture_path = plugin_dir_path(__FILE__) . '../../fixtures/multi-page-manifest.json';
        $this->manifest = json_decode((string) file_get_contents($fixture_path), true);

        // Cleanup
        $this->delete_by_slug('alpha-page');
        $this->delete_by_slug('beta-page');
    }

    public function test_successful_multi_page_create(): void
    {
        $summary = $this->importer->import($this->manifest, ['create' => true]);

        $this->assertCount(2, $summary);
        $this->assertEquals('create', $summary[0]['status']);
        $this->assertEquals('create', $summary[1]['status']);

        $alpha = get_page_by_path('alpha-page', OBJECT, 'page');
        $beta  = get_page_by_path('beta-page', OBJECT, 'page');

        $this->assertNotNull($alpha);
        $this->assertNotNull($beta);
        $this->assertEquals($this->manifest, get_post_meta($alpha->ID, '_mhm_layout_manifest', true));
    }

    public function test_successful_multi_page_update(): void
    {
        $id1 = wp_insert_post(['post_title' => 'Alpha', 'post_name' => 'alpha-page', 'post_type' => 'page', 'post_status' => 'publish']);
        $id2 = wp_insert_post(['post_title' => 'Beta', 'post_name' => 'beta-page', 'post_type' => 'page', 'post_status' => 'publish']);

        $summary = $this->importer->import($this->manifest, ['create' => true]);

        $this->assertCount(2, $summary);
        $this->assertEquals('update', $summary[0]['status']);
        $this->assertEquals('update', $summary[1]['status']);
        $this->assertEquals($id1, $summary[0]['post_id']);
    }

    private function delete_by_slug(string $slug): void
    {
        $post = get_page_by_path($slug, OBJECT, 'page');
        if ($post) {
            wp_delete_post($post->ID, true);
        }
    }
}
