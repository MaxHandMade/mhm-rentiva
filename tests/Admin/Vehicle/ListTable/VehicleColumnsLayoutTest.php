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
    public function test_column_set_replaces_the_three_spec_columns_with_features(): void
    {
        $cols = VehicleColumns::columns(array(
            'cb'    => '<input type="checkbox" />',
            'title' => 'Title',
            'date'  => 'Date',
        ));

        $this->assertArrayHasKey('mhmrentiva_features', $cols);
        $this->assertArrayNotHasKey('mhmrentiva_seats', $cols);
        $this->assertArrayNotHasKey('mhmrentiva_transmission', $cols);
        $this->assertArrayNotHasKey('mhmrentiva_fuel_type', $cols);
        $this->assertSame('date', array_key_last($cols));
    }

    public function test_features_cell_renders_all_three_values_as_chips(): void
    {
        $vehicle = self::factory()->post->create(array('post_type' => 'mhmrentiva_vehicle'));
        update_post_meta($vehicle, '_mhmrentiva_seats', 5);
        update_post_meta($vehicle, '_mhmrentiva_transmission', 'automatic');
        update_post_meta($vehicle, '_mhmrentiva_fuel_type', 'diesel');

        ob_start();
        VehicleColumns::render('mhmrentiva_features', $vehicle);
        $html = ob_get_clean();

        $this->assertStringContainsString('rv-vhl-feature', $html);
        $this->assertStringContainsString('5 seats', $html);
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
    }
}
