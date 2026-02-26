<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Layout\Rollback;

use MHMRentiva\Layout\Versioning\LayoutRollbackService;
use MHMRentiva\Layout\Ingestion\AtomicImporter;
use MHMRentiva\Layout\AdapterRegistry;
use WP_UnitTestCase;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Test class for LayoutRollbackService.
 *
 * @group layout
 * @group rollback
 */
class RollbackTest extends WP_UnitTestCase
{
    private $page_id;
    private $hash_v1;
    private $hash_v2;

    public function setUp(): void
    {
        parent::setUp();

        // Register default adapters
        AdapterRegistry::boot_defaults();

        // 1. Create a page with Layout V1
        $manifest_v1 = [
            'version'     => '1.0.0',
            'source'      => 'unit-test',
            'tokens'      => [],
            'components'  => [
                'hero-v1' => ['type' => 'search_hero'],
            ],
            'constraints' => [],
            'pages'       => [
                [
                    'title'       => 'Rollback Test Page',
                    'slug'        => 'rollback-test',
                    'layout'      => 'layout_container',
                    'composition' => [
                        [
                            'component_id' => 'hero-v1',
                            'instance_id'  => 'v1-hero',
                            'attributes'   => [],
                        ],
                    ],
                ],
            ],
        ];

        $importer      = new AtomicImporter();
        $summary       = $importer->import($manifest_v1, ['create' => true]);
        $this->page_id = $summary[0]['post_id'];
        $this->hash_v1 = get_post_meta($this->page_id, '_mhm_layout_hash', true);

        // 2. Update to Layout V2 (Triggers Shift in AtomicImporter)
        $manifest_v2 = [
            'version'     => '1.0.0',
            'source'      => 'unit-test',
            'tokens'      => [],
            'components'  => [
                'hero-v2' => ['type' => 'search_hero'],
            ],
            'constraints' => [],
            'pages'       => [
                [
                    'post_id'     => $this->page_id,
                    'title'       => 'Rollback Test Page',
                    'slug'        => 'rollback-test',
                    'layout'      => 'layout_container',
                    'composition' => [
                        [
                            'component_id' => 'hero-v2',
                            'instance_id'  => 'v2-hero',
                            'attributes'   => [],
                        ],
                    ],
                ],
            ],
        ];

        $importer->import($manifest_v2);
        $this->hash_v2 = get_post_meta($this->page_id, '_mhm_layout_hash', true);

        $this->assertNotEquals($this->hash_v1, $this->hash_v2, 'Hashes must differ for test to work');

        $content = get_post($this->page_id)->post_content;
        $this->assertStringContainsString('v2-hero', $content, 'V2 content must be active before rollback');
    }

    /**
     * Test successful rollback (Flip).
     */
    public function test_rollback_success_flip(): void
    {
        // Perform Rollback
        $result = LayoutRollbackService::rollback($this->page_id);

        $this->assertEquals('success', $result['status']);
        $this->assertEquals($this->hash_v1, $result['new_hash']);

        // Verify DB State (Flip)
        $this->assertEquals($this->hash_v1, get_post_meta($this->page_id, '_mhm_layout_hash', true));
        $this->assertEquals($this->hash_v2, get_post_meta($this->page_id, '_mhm_layout_hash_previous', true));

        // Verify Content (Instance ID should be v1-hero)
        $content = get_post($this->page_id)->post_content;
        $this->assertStringContainsString('v1-hero', $content, 'Rollback failed to restore V1 content');
    }

    /**
     * Test dry-run ensures no side effects.
     */
    public function test_rollback_dry_run_no_side_effects(): void
    {
        $content_before = get_post($this->page_id)->post_content;

        $result = LayoutRollbackService::rollback($this->page_id, true);

        $this->assertEquals('possible', $result['status']);
        $this->assertEquals($this->hash_v1, $result['target_hash']);

        // Ensure no writes
        $this->assertEquals($this->hash_v2, get_post_meta($this->page_id, '_mhm_layout_hash', true));
        $this->assertEquals($content_before, get_post($this->page_id)->post_content);
    }

    /**
     * Test data corruption (Hash mismatch).
     */
    public function test_rollback_hash_mismatch(): void
    {
        update_post_meta($this->page_id, '_mhm_layout_hash_previous', 'wrong');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Hash mismatch');

        LayoutRollbackService::rollback($this->page_id);
    }

    /**
     * Test failure if no previous version.
     */
    public function test_rollback_missing_previous(): void
    {
        $new_page_id = $this->factory->post->create(['post_type' => 'page']);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('No rollback version available');

        LayoutRollbackService::rollback((int) $new_page_id);
    }
}
