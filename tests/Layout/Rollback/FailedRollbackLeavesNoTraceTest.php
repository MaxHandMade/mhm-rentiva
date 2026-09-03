<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Layout\Rollback;

use MHMRentiva\Layout\Ingestion\AtomicImporter;
use MHMRentiva\Layout\LayoutEngineFactory;
use MHMRentiva\Layout\Versioning\LayoutRollbackService;
use MHMUiCore\Layout\LayoutEngine;
use WP_UnitTestCase;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * A failed rollback restores the snapshot -- it must not invent meta from it.
 *
 * The snapshot is read with get_post_meta(), which cannot tell "absent" from
 * "empty". _wp_page_template is the clearest case: the forward path never
 * writes it, so a page that has no page template acquires an empty one the
 * first time a rollback fails on it.
 *
 * @group layout
 * @group rollback
 */
class FailedRollbackLeavesNoTraceTest extends WP_UnitTestCase
{
    private LayoutEngine $engine;
    private int $page_id;

    public function setUp(): void
    {
        parent::setUp();

        $this->engine = LayoutEngineFactory::engine();

        $importer      = new AtomicImporter($this->engine);
        $summary       = $importer->import($this->manifest('v1'), ['create' => true]);
        $this->page_id = (int) $summary[0]['post_id'];

        $importer->import($this->manifest('v2', $this->page_id));

        $this->assertFalse(
            metadata_exists('post', $this->page_id, '_wp_page_template'),
            'Fixture precondition: the forward path never writes _wp_page_template.'
        );
    }

    /**
     * A rollback that aborts must leave the page's meta set as it found it.
     */
    public function test_a_failed_rollback_does_not_create_meta_the_page_never_had(): void
    {
        // Corrupt the stored hash so the rollback aborts after snapshotting.
        update_post_meta($this->page_id, '_mhmrentiva_layout_hash_previous', 'wrong');

        try {
            LayoutRollbackService::rollback($this->page_id, false, $this->engine);
        } catch (\Exception $e) {
            unset($e);
        }

        $this->assertFalse(
            metadata_exists('post', $this->page_id, '_wp_page_template'),
            'The page had no page template, so a failed rollback must not create one.'
        );
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
            'title'       => 'Failed Rollback Trace Page',
            'slug'        => 'failed-rollback-trace',
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
