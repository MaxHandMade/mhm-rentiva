<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Core\Services;

use MHMRentiva\Core\Services\TrendMath;
use PHPUnit\Framework\TestCase;

/**
 * Pure math regression guards for trend calculations.
 */
class TrendMathTest extends TestCase
{
    /**
     * Test correct percentage calculation and direction for upward trend.
     */
    public function test_trend_up(): void
    {
        $result = TrendMath::calculate_trend_from_totals(200, 100);

        $this->assertSame(200, $result['current']);
        $this->assertSame(100, $result['previous']);
        $this->assertSame(100, $result['trend']);
        $this->assertSame('up', $result['direction']);
    }

    /**
     * Test correct percentage calculation and direction for downward trend.
     */
    public function test_trend_down(): void
    {
        $result = TrendMath::calculate_trend_from_totals(50, 100);

        $this->assertSame(50, $result['current']);
        $this->assertSame(100, $result['previous']);
        $this->assertSame(-50, $result['trend']);
        $this->assertSame('down', $result['direction']);
    }

    /**
     * Test handling of division by zero (no previous data but current data exists).
     */
    public function test_zero_previous(): void
    {
        $result = TrendMath::calculate_trend_from_totals(50, 0);

        $this->assertSame(50, $result['current']);
        $this->assertSame(0, $result['previous']);
        $this->assertSame(100, $result['trend']); // 0 -> 50 is a 100% gain logic
        $this->assertSame('up', $result['direction']);
    }

    /**
     * Test no change leads to a natural state.
     */
    public function test_no_change(): void
    {
        $result = TrendMath::calculate_trend_from_totals(100, 100);

        $this->assertSame(100, $result['current']);
        $this->assertSame(100, $result['previous']);
        $this->assertSame(0, $result['trend']);
        $this->assertSame('neutral', $result['direction']);
    }

    /**
     * Test completely dormant logic (0 current, 0 previous)
     */
    public function test_all_zeros(): void
    {
        $result = TrendMath::calculate_trend_from_totals(0, 0);

        $this->assertSame(0, $result['current']);
        $this->assertSame(0, $result['previous']);
        $this->assertSame(0, $result['trend']);
        $this->assertSame('neutral', $result['direction']);
    }
}
