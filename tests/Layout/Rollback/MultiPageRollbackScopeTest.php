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
 * Rollback scope: rolling one page back must not rewrite its siblings.
 *
 * A manifest may describe several pages. Rolling back a single page is a
 * request about that page -- the other pages of the same manifest were never
 * asked to move, and their metadata is not flipped, so rewriting their content
 * would leave content and metadata contradicting each other silently.
 *
 * @group layout
 * @group rollback
 */
class MultiPageRollbackScopeTest extends WP_UnitTestCase
{
    private LayoutEngine $engine;
    private int $alpha_id;
    private int $beta_id;

    public function setUp(): void
    {
        parent::setUp();

        $this->engine = LayoutEngineFactory::engine();

        $importer = new AtomicImporter($this->engine);
        $summary  = $importer->import($this->manifest('v1'), ['create' => true]);

        $this->alpha_id = (int) $summary[0]['post_id'];
        $this->beta_id  = (int) $summary[1]['post_id'];

        $importer->import($this->manifest('v2', $this->alpha_id, $this->beta_id));

        $this->assertStringContainsString(
            'alpha-v2',
            get_post($this->alpha_id)->post_content,
            'Fixture precondition: alpha must carry v2 before the rollback.'
        );
        $this->assertStringContainsString(
            'beta-v2',
            get_post($this->beta_id)->post_content,
            'Fixture precondition: beta must carry v2 before the rollback.'
        );
    }

    /**
     * Rolling alpha back must leave beta's content where it was.
     */
    public function test_rolling_back_one_page_leaves_its_sibling_content_untouched(): void
    {
        LayoutRollbackService::rollback($this->alpha_id, false, $this->engine);

        $this->assertStringContainsString(
            'alpha-v1',
            get_post($this->alpha_id)->post_content,
            'Sanity: the rollback target itself must move back to v1.'
        );
        $this->assertStringContainsString(
            'beta-v2',
            get_post($this->beta_id)->post_content,
            'Beta was not the rollback target, so its content must not move.'
        );
    }

    /**
     * Beta's stored hash must keep describing beta's actual content.
     */
    public function test_sibling_metadata_still_describes_its_own_content(): void
    {
        $beta_hash_before = get_post_meta($this->beta_id, '_mhmrentiva_layout_hash', true);

        LayoutRollbackService::rollback($this->alpha_id, false, $this->engine);

        $this->assertSame(
            $beta_hash_before,
            get_post_meta($this->beta_id, '_mhmrentiva_layout_hash', true),
            'Beta was not the rollback target, so its hash must not move.'
        );
        $this->assertStringContainsString(
            'beta-v2',
            get_post($this->beta_id)->post_content,
            'Beta still advertises the v2 hash, so its content must still be v2.'
        );
    }

    /**
     * A rollback that wrote nothing must not report success.
     *
     * The stored manifest resolves alpha by slug, because alpha was created
     * from a manifest that carried no post ID. An editor renaming the page
     * breaks that resolution: the replay then writes nothing at all, while the
     * meta flip below still advertises the old version as current.
     */
    public function test_rollback_does_not_flip_meta_when_nothing_was_written(): void
    {
        wp_update_post(['ID' => $this->alpha_id, 'post_name' => 'alpha-renamed-by-an-editor']);

        $hash_before    = get_post_meta($this->alpha_id, '_mhmrentiva_layout_hash', true);
        $content_before = get_post($this->alpha_id)->post_content;
        $reported       = null;

        try {
            $reported = LayoutRollbackService::rollback($this->alpha_id, false, $this->engine);
        } catch (\Exception $e) {
            $reported = null;
        }

        $this->assertNull($reported, 'A rollback that resolved no page must raise, not return success.');
        $this->assertSame(
            $hash_before,
            get_post_meta($this->alpha_id, '_mhmrentiva_layout_hash', true),
            'Nothing was written, so the hash must not claim the previous version is now current.'
        );
        $this->assertSame(
            $content_before,
            get_post($this->alpha_id)->post_content,
            'Nothing was written, so the content must be untouched.'
        );
    }

    /**
     * Build a two-page manifest whose instance IDs carry the version marker.
     *
     * @param string $version  Version marker written into each instance ID.
     * @param int    $alpha_id Existing alpha post ID, 0 to resolve by slug.
     * @param int    $beta_id  Existing beta post ID, 0 to resolve by slug.
     * @return array
     */
    private function manifest(string $version, int $alpha_id = 0, int $beta_id = 0): array
    {
        $page = static function (string $slug, string $title, string $instance, int $post_id): array {
            $page = [
                'title'       => $title,
                'slug'        => $slug,
                'layout'      => 'layout_container',
                'composition' => [
                    [
                        'component_id' => 'hero',
                        'instance_id'  => $instance,
                        'attributes'   => [],
                    ],
                ],
            ];

            if ($post_id > 0) {
                $page['post_id'] = $post_id;
            }

            return $page;
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
                $page('rollback-scope-alpha', 'Alpha', 'alpha-' . $version, $alpha_id),
                $page('rollback-scope-beta', 'Beta', 'beta-' . $version, $beta_id),
            ],
        ];
    }
}
