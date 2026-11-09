<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Vehicle\Meta;

use MHMRentiva\Admin\Vehicle\Meta\VehicleMeta;
use WP_UnitTestCase;

/**
 * The Vehicle Details box draws what Vehicle Settings › Definitions selects,
 * keeps the ids and data-* the scripts bind (spec §Sözleşmeler), and carries
 * the per-grid order fields the Update saves (PR 4 plan Task 4, r2).
 *
 * @covers \MHMRentiva\Admin\Vehicle\Meta\VehicleMeta::render_meta_box
 * @covers \MHMRentiva\Admin\Vehicle\Meta\VehicleMeta::detail_label_for
 */
final class VehicleDetailsBoxMarkupTest extends WP_UnitTestCase
{
	private \WP_Post $vehicle;

	public function setUp(): void
	{
		parent::setUp();
		wp_set_current_user(self::factory()->user->create(array( 'role' => 'administrator' )));
		$this->vehicle = self::factory()->post->create_and_get(array(
			'post_type'   => 'mhmrentiva_vehicle',
			'post_status' => 'publish',
		));
		update_option('mhmrentiva_selected_details', array( 'price_per_day', 'seats' ));
		update_option('mhmrentiva_selected_features', array( 'air_conditioning', 'navigation' ));
		update_option('mhmrentiva_selected_equipment', array( 'child_seat' ));
	}

	public function tearDown(): void
	{
		remove_all_filters('mhmrentiva_locations');
		foreach (array( 'selected_details', 'selected_features', 'selected_equipment' ) as $name) {
			delete_option('mhmrentiva_' . $name);
		}
		parent::tearDown();
	}

	private function box(): string
	{
		ob_start();
		VehicleMeta::render_meta_box($this->vehicle);
		return (string) ob_get_clean();
	}

	public function test_sections_render_what_vehicle_settings_selects(): void
	{
		update_post_meta($this->vehicle->ID, '_mhmrentiva_features', array( 'navigation' ));

		$html = $this->box();
		$this->assertStringContainsString('data-feature-key="navigation"', $html);
		$this->assertStringContainsString('data-feature-key="air_conditioning"', $html);

		update_option('mhmrentiva_selected_features', array( 'air_conditioning' ));
		$html = $this->box();
		$this->assertStringNotContainsString('data-feature-key="navigation"', $html, 'removed in Vehicle Settings, gone here');
		$this->assertSame(array( 'navigation' ), get_post_meta($this->vehicle->ID, '_mhmrentiva_features', true), 'the vehicle keeps its value');
	}

	public function test_ids_and_data_attributes_are_kept(): void
	{
		$html = $this->box();

		foreach (array( 'id="details-grid"', 'id="features-grid"', 'id="equipment-grid"', 'data-detail-key="price_per_day"', 'data-feature-key="air_conditioning"', 'data-equipment-key="child_seat"' ) as $needle) {
			$this->assertStringContainsString($needle, $html, $needle);
		}
	}

	public function test_each_grid_has_its_empty_order_field(): void
	{
		$html = $this->box();

		foreach (array( 'details', 'features', 'equipment' ) as $grid) {
			$this->assertMatchesRegularExpression('/<input type="hidden" id="' . $grid . '-grid_order" name="' . $grid . '-grid_order" value="" \/>/', $html, $grid);
		}
	}

	public function test_detail_labels_point_at_their_inputs(): void
	{
		$html = $this->box();

		$this->assertStringContainsString('<label class="mhm-detail-label" for="_mhmrentiva_vehicle_status">', $html);
		$this->assertStringContainsString('for="mhmrentiva_price_per_day"', $html);
		$this->assertStringContainsString('id="mhmrentiva_price_per_day"', $html);
	}

	public function test_a_vehicle_without_a_saved_order_uses_the_settings_limits(): void
	{
		// A new vehicle has no _mhmrentiva_details_order. The template used to
		// draw it from a second copy with hard-coded limits (engine size
		// min 0.8): the stored 0 failed the browser's validation and Update
		// would not submit. One loop, the settings' limits, for every vehicle.
		update_option('mhmrentiva_selected_details', array( 'engine_size', 'seats', 'transmission' ));
		delete_post_meta($this->vehicle->ID, '_mhmrentiva_details_order');

		$html = $this->box();

		$this->assertDoesNotMatchRegularExpression('/id="mhmrentiva_engine_size"[^>]*min="0\.8"/', $html);
		$this->assertDoesNotMatchRegularExpression('/id="mhmrentiva_seats"[^>]*max="20"/', $html, 'the seats maximum comes from the settings (default 100)');
		$this->assertSame('', (string) get_post_meta($this->vehicle->ID, '_mhmrentiva_details_order', true), 'drawing the box writes no order for a vehicle that has none');
	}

	public function test_location_card_only_with_locations(): void
	{
		$this->assertStringNotContainsString('vehicle-location', $this->box());

		add_filter(
			'mhmrentiva_locations',
			static function () {
				return array( (object) array( 'id' => 7, 'name' => 'Havalimanı' ) );
			}
		);

		$html = $this->box();
		$this->assertStringContainsString('vehicle-location', $html);
		$this->assertStringContainsString('<label class="mhm-detail-label" for="_mhmrentiva_location_id">', $html);
	}
}
