<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Vehicle\Meta;

use MHMRentiva\Admin\Vehicle\Meta\BlockedDatesMetaBox;
use WP_UnitTestCase;

/**
 * Unit tests for the pure single-date toggle used by the calendar quick-block feature.
 * Operates purely on postmeta — no `vehicle` CPT registration or capability needed
 * (capability is enforced in the AJAX wrapper, which is not unit-tested here).
 */
class BlockedDatesToggleTest extends WP_UnitTestCase
{
    private int $vehicle_id;

    public function setUp(): void
    {
        parent::setUp();
        $this->vehicle_id = self::factory()->post->create();
    }

    /** @test */
    public function test_toggle_adds_an_absent_date(): void
    {
        $result = BlockedDatesMetaBox::toggle_blocked_date($this->vehicle_id, '2026-07-15');

        $this->assertSame(true, $result['blocked']);
        $this->assertSame(1, $result['count']);
        $this->assertContains('2026-07-15', BlockedDatesMetaBox::get_blocked_dates($this->vehicle_id));
    }

    /** @test */
    public function test_toggle_removes_a_present_date(): void
    {
        BlockedDatesMetaBox::toggle_blocked_date($this->vehicle_id, '2026-07-15');

        $result = BlockedDatesMetaBox::toggle_blocked_date($this->vehicle_id, '2026-07-15');

        $this->assertSame(false, $result['blocked']);
        $this->assertSame(0, $result['count']);
        $this->assertNotContains('2026-07-15', BlockedDatesMetaBox::get_blocked_dates($this->vehicle_id));
    }

    /** @test */
    public function test_invalid_date_returns_wp_error_and_does_not_persist(): void
    {
        $result = BlockedDatesMetaBox::toggle_blocked_date($this->vehicle_id, '2026-13-99');

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('invalid_date', $result->get_error_code());
        $this->assertSame(array(), BlockedDatesMetaBox::get_blocked_dates($this->vehicle_id));
    }

    /** @test */
    public function test_stored_dates_are_sorted_and_unique(): void
    {
        BlockedDatesMetaBox::toggle_blocked_date($this->vehicle_id, '2026-07-20');
        BlockedDatesMetaBox::toggle_blocked_date($this->vehicle_id, '2026-07-05');
        BlockedDatesMetaBox::toggle_blocked_date($this->vehicle_id, '2026-07-20'); // remove

        $this->assertSame(array('2026-07-05'), BlockedDatesMetaBox::get_blocked_dates($this->vehicle_id));
    }
}
