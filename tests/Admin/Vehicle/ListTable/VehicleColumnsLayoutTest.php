<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Vehicle\ListTable;

use MHMRentiva\Admin\Vehicle\ListTable\VehicleColumns;
use WP_UnitTestCase;

/**
 * Faz 1b Task 3 — column consolidation on the vehicle list: Seats +
 * Transmission + Fuel become one Features chip cell whose quick-edit
 * fields move with it (same input names, save path untouched), the
 * Features header sorts by seat count, and Featured renders a star.
 */
final class VehicleColumnsLayoutTest extends WP_UnitTestCase
{
    public function test_column_set_matches_the_mockup_row(): void
    {
        $cols = VehicleColumns::columns(array(
            'cb'       => '<input type="checkbox" />',
            'title'    => 'Title',
            'comments' => 'Comments',
            'taxonomy-mhmrentiva_vehicle_category' => 'Categories',
            'date'     => 'Date',
        ));

        // Rich Vehicle cell replaces title/comments/taxonomy/License Plate.
        $this->assertArrayHasKey('mhmrentiva_vehicle', $cols);
        $this->assertArrayHasKey('mhmrentiva_week', $cols);
        $this->assertArrayHasKey('mhmrentiva_features', $cols);
        $this->assertArrayNotHasKey('title', $cols);
        $this->assertArrayNotHasKey('comments', $cols);
        $this->assertArrayNotHasKey('taxonomy-mhmrentiva_vehicle_category', $cols);
        $this->assertArrayNotHasKey('mhmrentiva_license_plate', $cols);
        $this->assertArrayNotHasKey('mhmrentiva_seats', $cols);
        $this->assertArrayNotHasKey('mhmrentiva_transmission', $cols);
        $this->assertArrayNotHasKey('mhmrentiva_fuel_type', $cols);
        $this->assertSame('date', array_key_last($cols));
    }

    public function test_vehicle_cell_is_the_primary_column_only_on_this_screen(): void
    {
        $this->assertSame('mhmrentiva_vehicle', VehicleColumns::primary_column('title', 'edit-mhmrentiva_vehicle'));
        $this->assertSame('title', VehicleColumns::primary_column('title', 'edit-post'));
    }

    public function test_vehicle_cell_renders_title_meta_line_and_plate_contract(): void
    {
        $vehicle = self::factory()->post->create(array('post_type' => 'mhmrentiva_vehicle', 'post_title' => 'Corolla X'));
        update_post_meta($vehicle, '_mhmrentiva_license_plate', '34 ZZ 999');

        ob_start();
        VehicleColumns::render('mhmrentiva_vehicle', $vehicle);
        $html = ob_get_clean();

        $this->assertStringContainsString('Corolla X', $html);
        $this->assertStringContainsString('rv-vhl-vehicle__meta', $html);
        $this->assertStringContainsString('data-plate="34 ZZ 999"', $html);
        $this->assertStringContainsString('34 ZZ 999', $html);
    }

    public function test_week_strip_marks_booked_and_blocked_days(): void
    {
        $vehicle = self::factory()->post->create(array('post_type' => 'mhmrentiva_vehicle'));

        $today    = current_time('Y-m-d');
        $tomorrow = gmdate('Y-m-d', strtotime('+1 day', strtotime($today)));

        $booking = self::factory()->post->create(array('post_type' => 'mhmrentiva_booking'));
        update_post_meta($booking, '_mhmrentiva_status', 'confirmed');
        update_post_meta($booking, '_mhmrentiva_vehicle_id', $vehicle);
        update_post_meta($booking, '_mhmrentiva_pickup_date', $today);
        update_post_meta($booking, '_mhmrentiva_dropoff_date', $tomorrow);

        $blocked_day = gmdate('Y-m-d', strtotime('+3 days', strtotime($today)));
        // BlockedDatesMetaBox's own key — NOT MetaKeys::VEHICLE_BLOCKED_DATES,
        // which names a different (legacy) key the accessor never reads.
        update_post_meta($vehicle, '_mhmrentiva_blocked_dates', wp_json_encode(array($blocked_day)));

        // The per-request booking map is a static cache; reset it so this
        // test sees its own fixtures regardless of run order.
        $prop = new \ReflectionProperty(VehicleColumns::class, 'week_bookings_map');
        $prop->setAccessible(true);
        $prop->setValue(null, null);

        $strip = VehicleColumns::get_week_strip($vehicle);

        $this->assertCount(7, $strip);
        $this->assertSame('is-confirmed', $strip[0]['class'], 'Today is booked (confirmed)');
        $this->assertSame('is-confirmed', $strip[1]['class'], 'Tomorrow is booked (confirmed)');
        $this->assertSame('is-blocked', $strip[3]['class'], 'Blocked day wins');
        $this->assertSame('is-free', $strip[6]['class'], 'Untouched day is free');
    }

    public function test_features_cell_renders_all_three_values_as_chips(): void
    {
        $vehicle = self::factory()->post->create(array('post_type' => 'mhmrentiva_vehicle'));
        update_post_meta($vehicle, '_mhmrentiva_seats', 5);
        // 'auto' is the canonical stored key (the registered sanitizer
        // normalizes variants to it) — the same value the quick-edit
        // <select> options carry, which is exactly why the data attribute
        // must expose the RAW key, not the localized label.
        update_post_meta($vehicle, '_mhmrentiva_transmission', 'auto');
        update_post_meta($vehicle, '_mhmrentiva_fuel_type', 'diesel');

        ob_start();
        VehicleColumns::render('mhmrentiva_features', $vehicle);
        $html = ob_get_clean();

        $this->assertStringContainsString('rv-vhl-feature', $html);
        $this->assertStringContainsString('5 seats', $html);
        // Quick-edit prefill contract (Fable finding — scraping translated
        // chip text broke silently when the columns merged).
        $this->assertStringContainsString('data-seats="5"', $html);
        $this->assertStringContainsString('data-transmission="auto"', $html);
        $this->assertStringContainsString('data-fuel="diesel"', $html);
    }

    public function test_features_header_sorts_by_seats(): void
    {
        $sortable = VehicleColumns::sortable(array());

        $this->assertSame('mhmrentiva_seats', $sortable['mhmrentiva_features']);
        $this->assertArrayNotHasKey('mhmrentiva_seats', $sortable);
    }

    public function test_quick_edit_fields_reanchored_to_the_features_column(): void
    {
        ob_start();
        VehicleColumns::quick_edit_fields('mhmrentiva_features', 'mhmrentiva_vehicle');
        $html = ob_get_clean();

        $this->assertStringContainsString('name="mhmrentiva_seats"', $html);
        $this->assertStringContainsString('name="mhmrentiva_transmission"', $html);
        $this->assertStringContainsString('name="mhmrentiva_fuel_type"', $html);
    }

    public function test_featured_cell_renders_a_star_not_text(): void
    {
        $vehicle = self::factory()->post->create(array('post_type' => 'mhmrentiva_vehicle'));
        update_post_meta($vehicle, '_mhmrentiva_featured', '1');

        ob_start();
        VehicleColumns::render('mhmrentiva_featured', $vehicle);
        $html = ob_get_clean();

        $this->assertStringContainsString('rv-vhl-star is-featured', $html);
        // Same prefill contract as Features: the checkbox reads data-featured,
        // not the (translated) cell text.
        $this->assertStringContainsString('data-featured="1"', $html);
    }
}
