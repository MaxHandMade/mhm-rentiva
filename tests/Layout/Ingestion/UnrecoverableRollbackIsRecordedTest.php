<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Layout\Ingestion;

use MHMRentiva\Layout\Ingestion\AtomicImporter;
use MHMRentiva\Layout\LayoutEngineFactory;
use MHMRentiva\Layout\Observability\LayoutAuditService;
use MHMUiCore\Layout\LayoutEngine;
use WP_UnitTestCase;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * When the internal rollback cannot restore a page, that must leave a trace.
 *
 * The rollback path discards the return of wp_update_post(), so a restore that
 * WordPress refuses looks exactly like one that worked: the batch raises about
 * the original failure and the half-written page is never mentioned. That is
 * the one state where content and metadata genuinely disagree on disk.
 *
 * @group layout
 * @group ingestion
 */
class UnrecoverableRollbackIsRecordedTest extends WP_UnitTestCase
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

        // Empty content on purpose: it is what the restore will try to write
        // back, and it gives the fixture a deterministic way to refuse it.
        $this->good_page = (int) wp_insert_post([
            'post_type'    => 'page',
            'post_title'   => 'Good',
            'post_name'    => 'unrecoverable-good',
            'post_content' => '',
            'post_status'  => 'publish',
        ]);

        $this->doomed_page = (int) wp_insert_post([
            'post_type'    => 'page',
            'post_title'   => 'Doomed',
            'post_name'    => 'unrecoverable-doomed',
            'post_content' => 'placeholder',
            'post_status'  => 'publish',
        ]);
    }

    /**
     * A restore WordPress refused must be recorded on the page it left behind.
     */
    public function test_a_restore_that_wordpress_refuses_is_recorded_in_the_audit_log(): void
    {
        $doomed = $this->doomed_page;

        // Fails the batch on the doomed page, and fails the restore of the good
        // page -- the restore is the only write that carries empty content.
        $reject = static function ($maybe_empty, $postarr) use ($doomed) {
            if ((int) ( $postarr['ID'] ?? 0 ) === $doomed) {
                return true;
            }

            return '' === (string) ( $postarr['post_content'] ?? '' ) ? true : $maybe_empty;
        };

        add_filter('wp_insert_post_empty_content', $reject, 10, 2);

        try {
            $this->importer->import($this->manifest());
        } catch (\Exception $e) {
            unset($e);
        } finally {
            remove_filter('wp_insert_post_empty_content', $reject, 10);
        }

        $operations = array_column(LayoutAuditService::get_events($this->good_page), 'operation');

        $this->assertContains(
            'restore_failed',
            $operations,
            'The page was left mid-write, so the failed restore must be recorded on it.'
        );
        $this->assertNotContains(
            'import',
            $operations,
            'The batch was rolled back, so nothing may claim an import happened.'
        );
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
                $page('unrecoverable-good', 'Good', $this->good_page),
                $page('unrecoverable-doomed', 'Doomed', $this->doomed_page),
            ],
        ];
    }
}
