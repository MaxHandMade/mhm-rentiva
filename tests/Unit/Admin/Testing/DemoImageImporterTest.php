<?php
declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

namespace MHMRentiva\Tests\Unit\Admin\Testing;

use MHMRentiva\Admin\Testing\DemoImageImporter;
use WP_UnitTestCase;

/**
 * DemoImageImporter Test Suite
 *
 * Verifies Media Library import, meta tagging, and cleanup behaviour.
 *
 * @package MHMRentiva\Tests\Unit\Admin\Testing
 * @since   4.25.1
 */
final class DemoImageImporterTest extends WP_UnitTestCase
{
    // -------------------------------------------------------------------------
    // get_images_dir
    // -------------------------------------------------------------------------

    public function test_get_images_dir_contains_expected_path(): void
    {
        $dir = DemoImageImporter::get_images_dir();

        $this->assertStringContainsString('assets/demo/images', $dir);
    }

    // -------------------------------------------------------------------------
    // get_available_images
    // -------------------------------------------------------------------------

    public function test_get_available_images_returns_array(): void
    {
        $images = DemoImageImporter::get_available_images();

        $this->assertIsArray($images);
    }

    // -------------------------------------------------------------------------
    // import
    // -------------------------------------------------------------------------

    public function test_import_returns_zero_for_nonexistent_file(): void
    {
        $result = DemoImageImporter::import('nonexistent.webp');

        $this->assertSame(0, $result);
    }

    // -------------------------------------------------------------------------
    // cleanup
    // -------------------------------------------------------------------------

    public function test_cleanup_removes_demo_attachments(): void
    {
        // Create a mock attachment post tagged as demo.
        $attach_id = wp_insert_attachment(
            array(
                'post_title'     => 'Demo Test Image',
                'post_status'    => 'inherit',
                'post_mime_type' => 'image/webp',
                'post_type'      => 'attachment',
            )
        );

        $this->assertGreaterThan(0, $attach_id, 'Mock attachment creation failed.');

        update_post_meta($attach_id, '_mhm_is_demo', '1');

        $result = DemoImageImporter::cleanup();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('count', $result);
        $this->assertSame(1, $result['count']);

        // The attachment should no longer exist.
        $post = get_post($attach_id);
        $this->assertNull($post, 'Demo attachment should have been deleted by cleanup().');
    }

    public function test_cleanup_returns_count_array(): void
    {
        // Ensure no demo attachments exist in clean state before this test.
        $result = DemoImageImporter::cleanup();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('count', $result);
    }

    public function test_import_succeeds_with_real_demo_image(): void
    {
        $importer = new \ReflectionClass( DemoImageImporter::class );
        $images_dir = DemoImageImporter::get_images_dir();

        // Skip if no demo images exist (e.g. missing assets in CI)
        $available = DemoImageImporter::get_available_images();
        if ( empty( $available ) ) {
            $this->markTestSkipped( 'No demo images found in assets/demo/images/' );
        }

        $filename   = array_key_first( $available );
        $attach_id  = DemoImageImporter::import( $filename );

        $this->assertGreaterThan( 0, $attach_id, 'import() should return a positive attachment ID' );
        $this->assertSame( '1', get_post_meta( $attach_id, '_mhm_is_demo', true ) );

        // Cleanup after test
        wp_delete_attachment( $attach_id, true );
    }
}
