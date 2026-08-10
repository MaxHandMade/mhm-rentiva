<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\ListTable;

use MHMRentiva\Admin\Booking\ListTable\BookingColumns;
use MHMRentiva\Admin\Utilities\Dashboard\DashboardService;
use WP_UnitTestCase;

/**
 * Semantics-consistency lock (spec v2, Task 1): the booking-list stats band
 * must show the SAME numbers the dashboard shows for the same concepts. Before
 * this round BookingColumns carried its own copy of the SQL — the two surfaces
 * agreed only by coincidence. The band now delegates to DashboardService; this
 * test pins both the delegation (numbers equal) and the absolute values from a
 * non-empty fixture set, so a green run cannot be vacuous (0 == 0).
 */
final class BookingStatsConsistencyTest extends WP_UnitTestCase
{
    private function create_booking(string $status, string $price = ''): int
    {
        $id = self::factory()->post->create(array('post_type' => 'mhmrentiva_booking'));
        update_post_meta($id, '_mhmrentiva_status', $status);
        if ('' !== $price) {
            update_post_meta($id, '_mhmrentiva_total_price', $price);
        }
        return $id;
    }

    public function setUp(): void
    {
        parent::setUp();

        // 2 pending (one priced, to prove pending revenue is EXCLUDED),
        // 1 confirmed (priced), 3 completed (two priced), 1 in_progress,
        // 1 cancelled, and 1 with NO status meta at all — the Fix-D case:
        // an INNER JOIN would drop it from every bucket while the total
        // query still counts it, so All and the per-status sum disagree.
        // COALESCE folds it into pending, same as get_status_breakdown().
        $this->create_booking('pending', '999.00');
        $this->create_booking('pending');
        $this->create_booking('confirmed', '250.00');
        $this->create_booking('completed', '1000.00');
        $this->create_booking('completed', '500.00');
        $this->create_booking('completed');
        $this->create_booking('in_progress');
        $this->create_booking('cancelled');
        self::factory()->post->create(array('post_type' => 'mhmrentiva_booking'));

        wp_cache_delete('mhmrentiva_booking_stats');
    }

    public function test_dashboard_stats_enumerate_every_status(): void
    {
        $stats = DashboardService::get_booking_stats();

        $this->assertSame(9, $stats['total']);
        $this->assertSame(3, $stats['pending'], 'A status-less booking must count as pending, not vanish');
        $this->assertSame(1, $stats['confirmed']);
        $this->assertSame(3, $stats['completed']);
        $this->assertSame(1, $stats['in_progress']);
        $this->assertSame(1, $stats['cancelled']);
    }

    public function test_band_numbers_equal_dashboard_numbers(): void
    {
        $band      = BookingColumns::get_booking_stats();
        $dashboard = DashboardService::get_booking_stats();
        $metrics   = DashboardService::get_dashboard_metrics();

        foreach (array('total', 'pending', 'confirmed', 'completed', 'in_progress', 'cancelled') as $key) {
            $this->assertSame($dashboard[$key], $band[$key], "Band '$key' diverged from dashboard");
        }

        // Revenue: completed(1000+500) + confirmed(250); pending's 999 excluded.
        $this->assertSame(1750.0, (float) $band['monthly_revenue']);
        $this->assertSame((float) $metrics['monthly_revenue'], (float) $band['monthly_revenue']);
    }

    public function test_band_keeps_its_windowed_sub_metrics(): void
    {
        $band = BookingColumns::get_booking_stats();

        // Fixtures were all created "now", so the windows include them. The
        // windowed sub-metric still reads the explicit meta (local query),
        // so the status-less booking is not in this 2.
        $this->assertSame(2, $band['pending_this_week']);
        $this->assertSame(3, $band['completed_this_month']);
        $this->assertArrayHasKey('revenue_trend', $band);
    }
}
