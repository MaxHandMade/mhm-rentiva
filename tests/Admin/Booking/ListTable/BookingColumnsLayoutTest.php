<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\ListTable;

use MHMRentiva\Admin\Booking\ListTable\BookingColumns;
use WP_UnitTestCase;

/**
 * Task 4 of the Faz 1a list transform — column consolidation:
 * title header becomes Customer (the cell already shows "Name - phone"),
 * the standalone License Plate and Days columns are gone (both live as
 * sub-lines now), Payment precedes Status, and the secondary money/type
 * columns are hidden by default but stay reachable via Screen Options.
 */
final class BookingColumnsLayoutTest extends WP_UnitTestCase
{
    public function test_column_set_and_order(): void
    {
        $cols = BookingColumns::columns(array(
            'cb'    => '<input type="checkbox" />',
            'title' => 'Title',
            'date'  => 'Date',
        ));

        $this->assertArrayNotHasKey('mhmrentiva_booking_license_plate', $cols);
        $this->assertArrayNotHasKey('mhmrentiva_booking_days', $cols);
        $this->assertSame('Customer', $cols['title']);

        $keys = array_keys($cols);
        $this->assertLessThan(
            array_search('mhmrentiva_booking_status', $keys, true),
            array_search('mhmrentiva_booking_payment', $keys, true),
            'Payment column must precede Status'
        );
        $this->assertSame('date', end($keys));
    }

    public function test_no_columns_are_hidden_by_default(): void
    {
        // User decision 2026-08-10: every column visible by default, trimming
        // is the user's own Screen Options choice. The class must not hook
        // default_hidden_columns at all.
        $this->assertFalse(method_exists(BookingColumns::class, 'default_hidden_columns'));
    }

    public function test_vehicle_cell_plate_subline_reads_the_populated_meta_key(): void
    {
        $vehicle_id = self::factory()->post->create(array('post_type' => 'mhmrentiva_vehicle', 'post_title' => 'Corolla'));
        update_post_meta($vehicle_id, '_mhmrentiva_license_plate', '34 ABC 123');

        $booking_id = self::factory()->post->create(array('post_type' => 'mhmrentiva_booking'));
        update_post_meta($booking_id, '_mhmrentiva_vehicle_id', $vehicle_id);

        ob_start();
        BookingColumns::render('mhmrentiva_booking_vehicle', $booking_id);
        $html = ob_get_clean();

        $this->assertStringContainsString('34 ABC 123', $html);
    }

    public function test_dates_cell_carries_the_day_count_subline(): void
    {
        $booking_id = self::factory()->post->create(array('post_type' => 'mhmrentiva_booking'));
        update_post_meta($booking_id, '_mhmrentiva_pickup_date', '2026-08-01');
        update_post_meta($booking_id, '_mhmrentiva_dropoff_date', '2026-08-04');
        update_post_meta($booking_id, '_mhmrentiva_rental_days', 3);

        ob_start();
        BookingColumns::render('mhmrentiva_booking_dates', $booking_id);
        $html = ob_get_clean();

        $this->assertStringContainsString('date-days', $html);
        $this->assertStringContainsString('3 days', $html);
    }
}
