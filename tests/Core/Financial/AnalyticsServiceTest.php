<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Core\Financial;

use MHMRentiva\Core\Financial\AnalyticsService;

/**
 * PHPUnit tests for AnalyticsService financial reporting logic.
 *
 * Tests are structured around 3 critical scenarios:
 *  1. Growth % returns null when previous period revenue is zero.
 *  2. Commission_refund entries correctly reduce net (clearer proof than revenue_period alone).
 *  3. Avg booking value calculation excludes refund rows from booking count.
 *
 * These integration tests operate against a real test database via WP_UnitTestCase.
 * Each test inserts clean ledger rows, asserts, and is isolated by setUp/tearDown.
 *
 * @since 4.21.0
 */
class AnalyticsServiceTest extends \WP_UnitTestCase
{
    private int $vendor_id = 999;
    private string $table;

    public function setUp(): void
    {
        parent::setUp();

        global $wpdb;
        $this->table = $wpdb->prefix . 'mhm_rentiva_ledger';

        // Clean ledger rows for vendor 999 before each test.
        $wpdb->delete($this->table, array('vendor_id' => $this->vendor_id), array('%d'));
    }

    public function tearDown(): void
    {
        global $wpdb;
        $wpdb->delete($this->table, array('vendor_id' => $this->vendor_id), array('%d'));

        parent::tearDown();
    }

	// -------------------------------------------------------------------------
	// Test 1: Growth rate returns NULL when previous period revenue = 0
	// -------------------------------------------------------------------------

    /**
     * @test
     * When a vendor has revenue in the current 7d window but ZERO in the previous 7d,
     * get_growth_rate() must return null, not 0.0.
     *
     * 0.0 = "no change". null = "no comparison baseline" (different semantics).
     */
    public function test_growth_rate_returns_null_when_previous_period_is_zero(): void
    {
        $now_ts = time();

        // Insert a cleared credit in the current 7d window (today).
        $this->insert_ledger_row(
            'commission_credit',
            500.0,
            'cleared',
            gmdate('Y-m-d H:i:s', $now_ts - DAY_IN_SECONDS) // yesterday
        );

        // No rows in the previous window (8–14 days ago).
        $growth = AnalyticsService::get_growth_rate($this->vendor_id, 7, $now_ts);

        $this->assertNull(
            $growth,
            'Growth rate must be null when previous period has no cleared revenue.'
        );
    }

	// -------------------------------------------------------------------------
	// Test 2: Refund entries correctly reduce NET revenue
	// -------------------------------------------------------------------------

    /**
     * @test
     * A commission_refund row with a negative amount must reduce the net period revenue.
     *
     * Setup:
     *   1 credit:  +1000.00
     *   1 refund:  -200.00 (partial refund)
     *   Expected net: 800.00
     */
    public function test_refund_reduces_net_revenue_correctly(): void
    {
        $now_ts     = time();
        $from_ts    = $now_ts - (7 * DAY_IN_SECONDS);
        $window_mid = $from_ts + (3 * DAY_IN_SECONDS);

        $this->insert_ledger_row(
            'commission_credit',
            1000.0,
            'cleared',
            gmdate('Y-m-d H:i:s', $window_mid)
        );

        $this->insert_ledger_row(
            'commission_refund',
            -200.0,
            'cleared',
            gmdate('Y-m-d H:i:s', $window_mid + DAY_IN_SECONDS)
        );

        // Payout debit — must be excluded from revenue (excluded by type filter).
        $this->insert_ledger_row(
            'payout_debit',
            -500.0,
            'cleared',
            gmdate('Y-m-d H:i:s', $window_mid + (2 * DAY_IN_SECONDS))
        );

        $net = AnalyticsService::get_revenue_period($this->vendor_id, $from_ts, $now_ts);

        $this->assertSame(
            800.0,
            $net,
            'Net revenue must be credit + refund (1000 + -200 = 800). Payout must be excluded.'
        );
    }

	// -------------------------------------------------------------------------
	// Test 3: Avg booking value correctly excludes refund rows from booking count
	// -------------------------------------------------------------------------

    /**
     * @test
     * avg_booking_value = SUM(amount) / COUNT(DISTINCT booking_id) for commission_credit only.
     *
     * Setup:
     *   Booking 1: commission_credit 400.00
     *   Booking 2: commission_credit 600.00
     *   Booking 1: commission_refund -100.00  (refund for booking 1 — must NOT inflate booking count)
     *
     * Expected: avg = (400 + 600) / 2 = 500.00
     * If refund was counted as a booking: avg = 1000 / 3 = 333.33 (wrong)
     */
    public function test_avg_booking_value_excludes_refund_rows_from_booking_count(): void
    {
        $now_ts  = time();
        $from_ts = $now_ts - (30 * DAY_IN_SECONDS);
        $mid     = $from_ts + (10 * DAY_IN_SECONDS);

        $this->insert_ledger_row(
            'commission_credit',
            400.0,
            'cleared',
            gmdate('Y-m-d H:i:s', $mid),
            1001
        ); // booking_id = 1001

        $this->insert_ledger_row(
            'commission_credit',
            600.0,
            'cleared',
            gmdate('Y-m-d H:i:s', $mid + DAY_IN_SECONDS),
            1002
        ); // booking_id = 1002

        $this->insert_ledger_row(
            'commission_refund',
            -100.0,
            'cleared',
            gmdate('Y-m-d H:i:s', $mid + (2 * DAY_IN_SECONDS)),
            1001
        ); // refund, same booking 1001

        $avg = AnalyticsService::get_avg_booking_value($this->vendor_id, $from_ts, $now_ts);

        $this->assertSame(
            500.0, // (400 + 600) / 2 — NOT (400+600) / 3
            $avg,
            'Avg booking value must use COUNT(DISTINCT booking_id) from commission_credit only.'
        );
    }

	// -------------------------------------------------------------------------
	// Test 4: Sparkline always returns exactly window_days entries
	// -------------------------------------------------------------------------

    /**
     * @test
     * get_sparkline_data() must return EXACTLY window_days float entries.
     * Days with no data must be 0.0. No sparse arrays, no missing keys.
     * This deterministic length guarantees correct SVG x-coordinate math.
     */
    public function test_sparkline_returns_exactly_window_days_entries_with_zero_backfill(): void
    {
        $now_ts  = time();
        $from_ts = $now_ts - (7 * DAY_IN_SECONDS);

        // Only day 3 has activity.
        $this->insert_ledger_row(
            'commission_credit',
            300.0,
            'cleared',
            gmdate('Y-m-d H:i:s', $from_ts + (3 * DAY_IN_SECONDS))
        );

        $points = AnalyticsService::get_sparkline_data($this->vendor_id, $from_ts, $now_ts, 7);

        $this->assertCount(7, $points, 'Sparkline must return exactly 7 data points for a 7-day window.');

        foreach ($points as $i => $value) {
            $this->assertIsFloat($value, "Point at index {$i} must be float.");
        }

        $non_zero = array_filter($points, static fn(float $v): bool => $v !== 0.0);
        $this->assertCount(1, $non_zero, 'Only 1 day has activity — all others must be 0.0.');
    }

	// -------------------------------------------------------------------------
	// Test 5: Avg booking value returns 0.0 (division-by-zero guard)
	// -------------------------------------------------------------------------

    /**
     * @test
     * When no cleared commission_credit rows exist in window,
     * get_avg_booking_value() must return 0.0, never trigger division-by-zero.
     * Guards the new-vendor edge case.
     */
    public function test_avg_booking_value_returns_zero_when_no_cleared_credits_exist(): void
    {
        $now_ts  = time();
        $from_ts = $now_ts - (30 * DAY_IN_SECONDS);

        // Refund-only row (no commission_credit — should not count).
        $this->insert_ledger_row(
            'commission_refund',
            -50.0,
            'cleared',
            gmdate('Y-m-d H:i:s', $from_ts + DAY_IN_SECONDS)
        );

        // Uncleared credit (status = pending — must be excluded).
        $this->insert_ledger_row(
            'commission_credit',
            500.0,
            'pending',
            gmdate('Y-m-d H:i:s', $from_ts + (2 * DAY_IN_SECONDS))
        );

        $avg = AnalyticsService::get_avg_booking_value($this->vendor_id, $from_ts, $now_ts);

        $this->assertSame(0.0, $avg, 'Must return 0.0 (not division-by-zero) when no cleared credits exist.');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function insert_ledger_row(
        string $type,
        float $amount,
        string $status,
        string $created_at,
        int $booking_id = 1001,
        string $uuid_suffix = ''
    ): void {
        global $wpdb;

        $uuid = substr(md5('test_' . $type . '_' . $amount . '_' . $uuid_suffix . '_' . microtime(true)), 0, 36);

        $res = $wpdb->insert(
            $this->table,
            array(
                'transaction_uuid' => $uuid,
                'vendor_id'        => $this->vendor_id,
                'booking_id'       => $booking_id,
                'order_id'         => null,
                'type'             => $type,
                'amount'           => $amount,
                'gross_amount'     => abs($amount),
                'commission_amount' => null,
                'commission_rate'  => null,
                'currency'         => 'TRY',
                'context'          => 'vendor',
                'status'           => $status,
                'created_at'       => $created_at,
            ),
            array('%s', '%d', '%d', '%d', '%s', '%f', '%f', '%f', '%f', '%s', '%s', '%s', '%s')
        );
        if ($res === false) {
            throw new \RuntimeException('INSERT FAILED: ' . $wpdb->last_error);
        }
    }
}
