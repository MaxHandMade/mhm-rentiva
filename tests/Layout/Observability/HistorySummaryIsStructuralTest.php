<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Layout\Observability;

use MHMRentiva\Layout\Observability\LayoutAuditService;
use MHMRentiva\Layout\Observability\LayoutHistoryService;
use WP_UnitTestCase;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * The history service must report facts, not sentences.
 *
 * 'N/A', 'Unknown' and "IMPORT (date by actor)" are display decisions in this
 * plugin's language. Baked into the service, they cannot be translated by
 * whoever renders them, and a caller that wants the raw absence has to parse
 * English back out of it. The service answers with values; the caller writes
 * the words.
 *
 * @group layout
 * @group observability
 */
class HistorySummaryIsStructuralTest extends WP_UnitTestCase
{
    private int $post_id;

    public function setUp(): void
    {
        parent::setUp();

        $this->post_id = (int) self::factory()->post->create(['post_type' => 'page']);
    }

    /**
     * A page with no layout yet reports empty values, not the words for them.
     */
    public function test_absent_values_are_empty_not_spelled_out(): void
    {
        $summary = LayoutHistoryService::get_summary($this->post_id);

        $this->assertSame('', $summary['current_hash'], 'An absent hash is an empty value.');
        $this->assertSame('', $summary['current_date'], 'An absent date is an empty value.');
        $this->assertSame('', $summary['previous_hash'], 'An absent previous hash is an empty value.');
        $this->assertSame('', $summary['previous_date'], 'An absent previous date is an empty value.');
        $this->assertNull($summary['last_operation'], 'No events means no last operation, not the word for it.');
    }

    /**
     * A recorded event is handed over as its parts, not as a rendered sentence.
     */
    public function test_the_last_operation_is_handed_over_as_its_parts(): void
    {
        LayoutAuditService::log_import($this->post_id, 'old-hash', 'new-hash');

        $last = LayoutHistoryService::get_summary($this->post_id)['last_operation'];

        $this->assertIsArray($last, 'The caller formats the sentence, so it needs the parts.');
        $this->assertSame('import', $last['operation'], 'The operation is a code, not a heading.');
        $this->assertArrayHasKey('timestamp', $last);
        $this->assertArrayHasKey('actor', $last);
    }

    /**
     * With no logged-in user the actor is unknown -- and says so by being empty.
     */
    public function test_an_unknown_actor_is_empty_rather_than_named_in_english(): void
    {
        wp_set_current_user(0);

        LayoutAuditService::log_import($this->post_id, '', 'a-hash');

        $events = LayoutAuditService::get_events($this->post_id);
        $last   = end($events);

        $this->assertSame(
            '',
            $last['actor'],
            'No user means no actor name; "System" is a word the reader chooses.'
        );
    }
}
