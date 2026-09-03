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
 * A manifest ID must not be able to overwrite something that is not a page.
 *
 * Resolution by slug asks get_page_by_path() for a page, so it cannot stray.
 * Resolution by ID asks get_post() for anything at all: a stale or mistyped ID
 * in a manifest overwrites whatever post carries that number -- a blog post, a
 * vehicle, an order note -- with layout markup, and stamps layout meta on it.
 *
 * @group layout
 * @group ingestion
 */
class PostTypeGuardTest extends WP_UnitTestCase
{
    private LayoutEngine $engine;
    private AtomicImporter $importer;

    public function setUp(): void
    {
        parent::setUp();

        $this->engine   = LayoutEngineFactory::engine();
        $this->importer = new AtomicImporter($this->engine);
    }

    /**
     * An ID pointing at a blog post must not be treated as a layout page.
     */
    public function test_an_id_pointing_at_another_post_type_is_not_overwritten(): void
    {
        $blog_post = (int) self::factory()->post->create([
            'post_type'    => 'post',
            'post_title'   => 'An ordinary blog post',
            'post_content' => 'Written by a human.',
            'post_status'  => 'publish',
        ]);

        $summary = $this->importer->import($this->manifest($blog_post));

        $this->assertSame(
            'Written by a human.',
            get_post($blog_post)->post_content,
            'A manifest ID that lands on a blog post must not overwrite it.'
        );
        $this->assertNotSame(
            'update',
            $summary[0]['status'] ?? '',
            'The importer must not report this as a page it updated.'
        );
    }

    /**
     * Single-page manifest pointing at a given post ID.
     *
     * @param int $post_id Target ID written into the manifest.
     * @return array
     */
    private function manifest(int $post_id): array
    {
        return [
            'version'     => '1.0.0',
            'source'      => 'unit-test',
            'tokens'      => [],
            'components'  => [
                'hero' => ['type' => 'search_hero'],
            ],
            'constraints' => [],
            'pages'       => [
                [
                    'post_id'     => $post_id,
                    'title'       => 'Guarded',
                    'slug'        => 'post-type-guard',
                    'layout'      => 'layout_container',
                    'composition' => [
                        [
                            'component_id' => 'hero',
                            'instance_id'  => 'guard-hero',
                            'attributes'   => [],
                        ],
                    ],
                ],
            ],
        ];
    }
}
