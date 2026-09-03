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
 * A failed import must leave a previously untouched page exactly as it was.
 *
 * The internal rollback restores from a snapshot taken with get_post_meta(),
 * which answers '' for keys that do not exist. Writing that '' back does not
 * restore the absence -- it creates the key. A page that never carried layout
 * metadata ends up carrying a full set of empty ones.
 *
 * @group layout
 * @group ingestion
 */
class FailedImportLeavesNoTraceTest extends WP_UnitTestCase
{
    private LayoutEngine $engine;
    private AtomicImporter $importer;
    private int $good_page;
    private int $doomed_page;

    public function setUp(): void
    {
        parent::setUp();

        $this->engine   = LayoutEngineFactory::engine();
        $this->importer = new AtomicImporter($this->engine);

        $this->good_page   = (int) self::factory()->post->create([
            'post_type'   => 'page',
            'post_name'   => 'trace-good',
            'post_status' => 'publish',
        ]);
        $this->doomed_page = (int) self::factory()->post->create([
            'post_type'   => 'page',
            'post_name'   => 'trace-doomed',
            'post_status' => 'publish',
        ]);

        $this->assertSame(
            [],
            $this->layout_meta_of($this->good_page),
            'Fixture precondition: the page starts with no layout metadata.'
        );
    }

    /**
     * The batch fails on the second page; the first must keep its clean slate.
     */
    public function test_a_failed_batch_leaves_no_layout_meta_on_a_previously_clean_page(): void
    {
        $doomed = $this->doomed_page;
        $reject = static function ($maybe_empty, $postarr) use ($doomed) {
            return ( (int) ( $postarr['ID'] ?? 0 ) === $doomed ) ? true : $maybe_empty;
        };

        add_filter('wp_insert_post_empty_content', $reject, 10, 2);

        $raised = false;

        try {
            $this->importer->import($this->manifest());
        } catch (\Exception $e) {
            $raised = true;
        } finally {
            remove_filter('wp_insert_post_empty_content', $reject, 10);
        }

        $this->assertTrue($raised, 'Fixture precondition: the batch must fail on the doomed page.');
        $this->assertSame(
            [],
            $this->layout_meta_of($this->good_page),
            'The batch was rolled back, so the page must carry no layout metadata at all.'
        );
    }

    /**
     * Layout-owned meta keys currently stored on a post.
     *
     * @param int $post_id Post to inspect.
     * @return array<string, mixed>
     */
    private function layout_meta_of(int $post_id): array
    {
        $found = [];

        foreach ((array) get_post_meta($post_id) as $key => $value) {
            if (str_starts_with((string) $key, '_mhmrentiva_layout') || '_wp_page_template' === $key) {
                $found[ $key ] = $value;
            }
        }

        return $found;
    }

    /**
     * Two-page manifest targeting the fixture pages by ID.
     *
     * @return array
     */
    private function manifest(): array
    {
        $page = static function (string $slug, string $title, int $post_id): array {
            return [
                'post_id'     => $post_id,
                'title'       => $title,
                'slug'        => $slug,
                'layout'      => 'layout_container',
                'composition' => [
                    [
                        'component_id' => 'hero',
                        'instance_id'  => 'hero-' . $slug,
                        'attributes'   => [],
                    ],
                ],
            ];
        };

        return [
            'version'     => '1.0.0',
            'source'      => 'unit-test',
            'tokens'      => [],
            'components'  => [
                'hero' => ['type' => 'search_hero'],
            ],
            'constraints' => [],
            'pages'       => [
                $page('trace-good', 'Good', $this->good_page),
                $page('trace-doomed', 'Doomed', $this->doomed_page),
            ],
        ];
    }
}
