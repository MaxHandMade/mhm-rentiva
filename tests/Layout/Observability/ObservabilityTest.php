<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Layout\Observability;

use MHMRentiva\Layout\Observability\LayoutAuditService;
use MHMRentiva\Layout\Observability\LayoutHistoryService;
use MHMRentiva\Layout\Observability\LayoutDiffService;
use WP_UnitTestCase;

class ObservabilityTest extends WP_UnitTestCase
{
    private int $post_id;

    public function setUp(): void
    {
        parent::setUp();
        $this->post_id = $this->factory->post->create(['post_type' => 'page']);
    }

    /**
     * Test Audit Log Append and Retention Cap (200)
     */
    public function test_audit_log_retention_cap()
    {
        // Append 250 events
        for ($i = 1; $i <= 250; $i++) {
            LayoutAuditService::append_event($this->post_id, ['test' => $i]);
        }

        $events = LayoutAuditService::get_events($this->post_id);

        $this->assertCount(200, $events);
        $this->assertEquals(250, $events[199]['test']); // Last one
        $this->assertTrue($events[199]['truncated']);
    }

    /**
     * Test History Service
     */
    public function test_history_service()
    {
        update_post_meta($this->post_id, '_mhm_layout_hash', 'current-hash');
        update_post_meta($this->post_id, '_mhm_layout_hash_previous', 'prev-hash');

        $summary = LayoutHistoryService::get_summary($this->post_id);

        $this->assertEquals('current-hash', $summary['current_hash']);
        $this->assertEquals('prev-hash', $summary['previous_hash']);
    }

    /**
     * Test Diff Service
     */
    public function test_diff_service()
    {
        $curr = [
            'tokens'     => ['color' => '#fff', 'size' => '10px'],
            'components' => ['hero' => ['type' => 'search_hero']],
        ];
        $prev = [
            'tokens'     => ['color' => '#000'],
            'components' => ['old' => ['type' => 'footer']],
        ];

        $diff = LayoutDiffService::diff($curr, $prev);

        // Tokens
        $this->assertContains('size', $diff['tokens']['added']);
        $this->assertArrayHasKey('color', $diff['tokens']['changed']);
        $this->assertEquals('#000', $diff['tokens']['changed']['color']['from']);

        // Components
        $this->assertContains('hero', $diff['components']['added']);
        $this->assertContains('old', $diff['components']['removed']);
    }
}
