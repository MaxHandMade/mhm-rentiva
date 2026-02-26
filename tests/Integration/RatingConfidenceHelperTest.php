<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration;

use MHMRentiva\Admin\Vehicle\Helpers\RatingConfidenceHelper;
use WP_UnitTestCase;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Integration tests for RatingConfidenceHelper.
 *
 * @covers \MHMRentiva\Admin\Vehicle\Helpers\RatingConfidenceHelper
 * @since 1.3.1
 */
class RatingConfidenceHelperTest extends WP_UnitTestCase
{
    /**
     * Clean up filters after each test.
     */
    public function tearDown(): void
    {
        remove_all_filters('mhm_rentiva_rating_confidence_thresholds');
        parent::tearDown();
    }

    /**
     * Test: count=0 returns empty strings (nothing rendered).
     */
    public function test_zero_count_returns_empty(): void
    {
        $result = RatingConfidenceHelper::from_count(0);

        $this->assertSame('', $result['key']);
        $this->assertSame('', $result['label']);
        $this->assertSame('', $result['tooltip']);
    }

    /**
     * Test: negative count returns empty strings.
     */
    public function test_negative_count_returns_empty(): void
    {
        $result = RatingConfidenceHelper::from_count(-5);

        $this->assertSame('', $result['key']);
    }

    /**
     * Test: count=1 → "New" bucket.
     */
    public function test_count_1_is_new(): void
    {
        $result = RatingConfidenceHelper::from_count(1);

        $this->assertSame('new', $result['key']);
        $this->assertNotEmpty($result['label']);
        $this->assertNotEmpty($result['tooltip']);
    }

    /**
     * Test: count=2 → "New" bucket (upper boundary).
     */
    public function test_count_2_is_new(): void
    {
        $result = RatingConfidenceHelper::from_count(2);

        $this->assertSame('new', $result['key']);
    }

    /**
     * Test: count=3 → "Reliable" bucket (lower boundary).
     */
    public function test_count_3_is_reliable(): void
    {
        $result = RatingConfidenceHelper::from_count(3);

        $this->assertSame('reliable', $result['key']);
        $this->assertNotEmpty($result['label']);
    }

    /**
     * Test: count=5 → "Reliable" bucket (mid-range).
     */
    public function test_count_5_is_reliable(): void
    {
        $result = RatingConfidenceHelper::from_count(5);

        $this->assertSame('reliable', $result['key']);
    }

    /**
     * Test: count=9 → "Reliable" bucket (upper boundary).
     */
    public function test_count_9_is_reliable(): void
    {
        $result = RatingConfidenceHelper::from_count(9);

        $this->assertSame('reliable', $result['key']);
    }

    /**
     * Test: count=10 → "Highly Reliable" bucket (lower boundary).
     */
    public function test_count_10_is_high(): void
    {
        $result = RatingConfidenceHelper::from_count(10);

        $this->assertSame('high', $result['key']);
        $this->assertNotEmpty($result['label']);
    }

    /**
     * Test: count=50 → "Highly Reliable" bucket.
     */
    public function test_count_50_is_high(): void
    {
        $result = RatingConfidenceHelper::from_count(50);

        $this->assertSame('high', $result['key']);
    }

    /**
     * Test: filter override changes thresholds.
     * Custom thresholds: [5, 20] → <=5 New, <=20 Reliable, >20 High.
     */
    public function test_filter_override_thresholds(): void
    {
        add_filter('mhm_rentiva_rating_confidence_thresholds', function (): array {
            return array(5, 20);
        });

        // count=5 should now be "new" (was "reliable" with defaults)
        $result_5 = RatingConfidenceHelper::from_count(5);
        $this->assertSame('new', $result_5['key']);

        // count=10 should now be "reliable" (was "high" with defaults)
        $result_10 = RatingConfidenceHelper::from_count(10);
        $this->assertSame('reliable', $result_10['key']);

        // count=21 should be "high"
        $result_21 = RatingConfidenceHelper::from_count(21);
        $this->assertSame('high', $result_21['key']);
    }

    /**
     * Test: return array always contains all 3 keys.
     */
    public function test_return_shape_is_consistent(): void
    {
        $counts = array(0, 1, 5, 15);

        foreach ($counts as $count) {
            $result = RatingConfidenceHelper::from_count($count);
            $this->assertArrayHasKey('key', $result, "Missing 'key' for count={$count}");
            $this->assertArrayHasKey('label', $result, "Missing 'label' for count={$count}");
            $this->assertArrayHasKey('tooltip', $result, "Missing 'tooltip' for count={$count}");
        }
    }
}
