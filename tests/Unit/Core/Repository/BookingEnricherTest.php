<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Core\Repository;

use MHMRentiva\Core\Repository\BookingEnricher;

/**
 * Behaviour-preserving contract for the shared customer-info enrichment
 * extracted from DashboardService::get_recent_bookings_paginated() and
 * ReportRepository::get_upcoming_operations_paginated().
 *
 * The correctness-critical invariant (proven from code, NOT from memory which
 * wrongly claimed the two blocks were byte-identical): phone enrichment must
 * happen ONLY when the row already carries a 'customer_phone' key. Dashboard
 * rows have no such key (must stay untouched); Report rows do (must keep phone
 * fallback). Getting this wrong is a silent regression in one of the two paths.
 */
class BookingEnricherTest extends \WP_UnitTestCase
{
    private function make_booking(): int
    {
        return self::factory()->post->create([
            'post_type'   => 'vehicle_booking',
            'post_status' => 'publish',
        ]);
    }

    public function test_skips_rows_that_already_have_a_customer_name(): void
    {
        $rows = [
            ['id' => 123, 'customer_name' => 'Existing Name'],
        ];
        BookingEnricher::enrich_customer_info($rows);

        $this->assertSame('Existing Name', $rows[0]['customer_name']);
    }

    public function test_fills_customer_name_from_wordpress_user_fallback(): void
    {
        $user_id = self::factory()->user->create([
            'first_name' => 'Ada',
            'last_name'  => 'Lovelace',
        ]);
        $booking_id = $this->make_booking();
        update_post_meta($booking_id, '_mhmrentiva_customer_user_id', $user_id);

        $rows = [
            ['id' => $booking_id, 'customer_name' => ''],
        ];
        BookingEnricher::enrich_customer_info($rows);

        $this->assertSame('Ada Lovelace', $rows[0]['customer_name']);
    }

    public function test_phone_is_NOT_added_when_row_has_no_customer_phone_key(): void
    {
        // Mirrors DashboardService rows: no 'customer_phone' key at all.
        $user_id = self::factory()->user->create([
            'first_name' => 'Grace',
            'last_name'  => 'Hopper',
        ]);
        update_user_meta($user_id, 'phone', '555-0100');
        $booking_id = $this->make_booking();
        update_post_meta($booking_id, '_mhmrentiva_customer_user_id', $user_id);

        $rows = [
            ['id' => $booking_id, 'customer_name' => ''],
        ];
        BookingEnricher::enrich_customer_info($rows);

        $this->assertSame('Grace Hopper', $rows[0]['customer_name']);
        $this->assertArrayNotHasKey(
            'customer_phone',
            $rows[0],
            'Dashboard-style rows must not gain a customer_phone key'
        );
    }

    public function test_phone_is_filled_when_row_has_empty_customer_phone_key(): void
    {
        // Mirrors ReportRepository rows: 'customer_phone' key present (empty).
        $user_id = self::factory()->user->create([
            'first_name' => 'Katherine',
            'last_name'  => 'Johnson',
        ]);
        update_user_meta($user_id, 'phone', '555-0199');
        $booking_id = $this->make_booking();
        update_post_meta($booking_id, '_mhmrentiva_customer_user_id', $user_id);

        $rows = [
            ['id' => $booking_id, 'customer_name' => '', 'customer_phone' => ''],
        ];
        BookingEnricher::enrich_customer_info($rows);

        $this->assertSame('Katherine Johnson', $rows[0]['customer_name']);
        $this->assertSame('555-0199', $rows[0]['customer_phone']);
    }

    public function test_existing_phone_is_not_overwritten(): void
    {
        $user_id = self::factory()->user->create([
            'first_name' => 'Annie',
            'last_name'  => 'Easley',
        ]);
        update_user_meta($user_id, 'phone', '555-9999');
        $booking_id = $this->make_booking();
        update_post_meta($booking_id, '_mhmrentiva_customer_user_id', $user_id);

        $rows = [
            ['id' => $booking_id, 'customer_name' => '', 'customer_phone' => '555-0001'],
        ];
        BookingEnricher::enrich_customer_info($rows);

        $this->assertSame('555-0001', $rows[0]['customer_phone']);
    }
}
