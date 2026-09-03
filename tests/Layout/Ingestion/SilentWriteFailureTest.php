<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Layout\Ingestion;

use MHMRentiva\Layout\Ingestion\AtomicImporter;
use MHMRentiva\Layout\LayoutEngineFactory;
use MHMUiCore\Layout\LayoutEngine;
use WP_UnitTestCase;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * A content write that fails must not leave the metadata claiming it succeeded.
 *
 * wp_update_post() reports failure by return value, not by raising. When that
 * return is discarded the meta is written anyway, so the stored hash describes
 * a layout the page never received -- and the next import skips the page as
 * "identical" because the hash already matches.
 *
 * @group layout
 * @group ingestion
 */
class SilentWriteFailureTest extends WP_UnitTestCase
{
    private LayoutEngine $engine;
    private AtomicImporter $importer;
    private int $page_id;
    private string $hash_v1;

    public function setUp(): void
    {
        parent::setUp();

        $this->engine   = LayoutEngineFactory::engine();
        $this->importer = new AtomicImporter($this->engine);

        $summary       = $this->importer->import($this->manifest('v1'), ['create' => true]);
        $this->page_id = (int) $summary[0]['post_id'];
        $this->hash_v1 = (string) get_post_meta($this->page_id, '_mhmrentiva_layout_hash', true);

        $this->assertNotSame('', $this->hash_v1, 'Fixture precondition: v1 must be stored.');
    }

    /**
     * A rejected content write must not be followed by a new hash.
     */
    public function test_a_rejected_content_write_does_not_advertise_the_new_hash(): void
    {
        add_filter('wp_insert_post_empty_content', '__return_true');

        try {
            $this->importer->import($this->manifest('v2', $this->page_id));
        } catch (\Exception $e) {
            // Raising is the correct outcome; the assertions below check the state.
            unset($e);
        } finally {
            remove_filter('wp_insert_post_empty_content', '__return_true');
        }

        $this->assertSame(
            $this->hash_v1,
            (string) get_post_meta($this->page_id, '_mhmrentiva_layout_hash', true),
            'The write was rejected, so the stored hash must still describe v1.'
        );
        $this->assertStringContainsString(
            'hero-v1',
            get_post($this->page_id)->post_content,
            'The write was rejected, so the content must still be v1.'
        );
    }

    /**
     * The importer must report the failure to its caller instead of returning a summary.
     */
    public function test_a_rejected_content_write_is_reported_to_the_caller(): void
    {
        add_filter('wp_insert_post_empty_content', '__return_true');

        $raised = false;

        try {
            $this->importer->import($this->manifest('v2', $this->page_id));
        } catch (\Exception $e) {
            $raised = true;
        } finally {
            remove_filter('wp_insert_post_empty_content', '__return_true');
        }

        $this->assertTrue($raised, 'A failed content write must reach the caller, not be swallowed.');
    }

    /**
     * Build a single-page manifest whose instance ID carries the version marker.
     *
     * @param string $version Version marker written into the instance ID.
     * @param int    $post_id Existing post ID, 0 to resolve by slug.
     * @return array
     */
    private function manifest(string $version, int $post_id = 0): array
    {
        $page = [
            'title'       => 'Silent Write Failure Page',
            'slug'        => 'silent-write-failure',
            'layout'      => 'layout_container',
            'composition' => [
                [
                    'component_id' => 'hero',
                    'instance_id'  => 'hero-' . $version,
                    'attributes'   => [],
                ],
            ],
        ];

        if ($post_id > 0) {
            $page['post_id'] = $post_id;
        }

        return [
            'version'     => '1.0.0',
            'source'      => 'unit-test',
            'tokens'      => [],
            'components'  => [
                'hero' => ['type' => 'search_hero'],
            ],
            'constraints' => [],
            'pages'       => [$page],
        ];
    }
}
