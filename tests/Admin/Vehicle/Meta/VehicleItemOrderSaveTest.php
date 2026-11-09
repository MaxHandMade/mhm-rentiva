<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Vehicle\Meta;

use MHMRentiva\Admin\Vehicle\Meta\VehicleMeta;
use WP_UnitTestCase;

/**
 * The order of the detail, feature and equipment items is saved per vehicle,
 * with the Update (PR 4 plan r2/r3). Before this, nothing saved it: the
 * *-grid_order fields the save path reads were never printed, and the AJAX
 * saver failed its nonce on every request -- and, had it passed, rewrote the
 * global mhmrentiva_vehicle_* label options without the keys it was not sent
 * (Codex PR 4 plan Y1).
 *
 * @covers \MHMRentiva\Admin\Vehicle\Meta\VehicleMeta
 */
final class VehicleItemOrderSaveTest extends WP_UnitTestCase
{
	private int $vehicle_id;

	public function setUp(): void
	{
		parent::setUp();
		$this->vehicle_id = (int) self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_vehicle',
				'post_status' => 'publish',
			)
		);
		wp_set_current_user(self::factory()->user->create(array( 'role' => 'administrator' )));
		// The keys the grids draw come from the Vehicle Settings selection.
		update_option('mhmrentiva_selected_details', array( 'price_per_day', 'seats', 'doors' ));
		update_option('mhmrentiva_selected_features', array( 'air_conditioning', 'navigation', 'bluetooth' ));
		update_option('mhmrentiva_selected_equipment', array( 'child_seat', 'first_aid_kit' ));
	}

	public function tearDown(): void
	{
		$_POST = array();
		foreach (array( 'selected_details', 'selected_features', 'selected_equipment','vehicle_details', 'vehicle_features', 'vehicle_equipment' ) as $name) {
			delete_option('mhmrentiva_' . $name);
		}
		parent::tearDown();
	}

	/**
	 * @param array<string, string> $fields
	 */
	private function save(array $fields): void
	{
		$_POST = array_merge(
			array( 'mhmrentiva_vehicle_meta_nonce' => wp_create_nonce('mhmrentiva_vehicle_meta_action') ),
			$fields
		);
		VehicleMeta::save_meta($this->vehicle_id, get_post($this->vehicle_id));
	}

	/** @return list<string> */
	private function order(string $grid): array
	{
		return (array) get_post_meta($this->vehicle_id, '_mhmrentiva_' . $grid . '_order', true);
	}

	public function test_update_saves_each_grid_order_per_vehicle(): void
	{
		$this->save(array(
			'details-grid_order'   => wp_json_encode(array( 'seats', 'price_per_day' )),
			'features-grid_order'  => wp_json_encode(array( 'navigation', 'air_conditioning' )),
			'equipment-grid_order' => wp_json_encode(array( 'first_aid_kit', 'child_seat' )),
		));

		$this->assertSame(array( 'seats', 'price_per_day' ), $this->order('details'));
		$this->assertSame(array( 'navigation', 'air_conditioning' ), $this->order('features'));
		$this->assertSame(array( 'first_aid_kit', 'child_seat' ), $this->order('equipment'));
	}

	public function test_empty_order_fields_leave_the_saved_order_alone(): void
	{
		update_post_meta($this->vehicle_id, '_mhmrentiva_features_order', array( 'bluetooth', 'navigation' ));

		$this->save(array(
			'details-grid_order'   => '',
			'features-grid_order'  => '',
			'equipment-grid_order' => '',
		));

		$this->assertSame(array( 'bluetooth', 'navigation' ), $this->order('features'));
	}

	public function test_unknown_keys_are_dropped(): void
	{
		$this->save(array(
			'features-grid_order' => wp_json_encode(array( 'navigation', 'not_a_feature', '<script>', 'air_conditioning' )),
		));

		$this->assertSame(array( 'navigation', 'air_conditioning' ), $this->order('features'));
	}

	public function test_global_field_label_options_are_untouched(): void
	{
		$labels = array(
			'details'   => array( 'seats' => 'Koltuk (özel)', 'doors' => 'Kapı (özel)' ),
			'features'  => array( 'navigation' => 'Navigasyon (özel)', 'bluetooth' => 'BT (özel)' ),
			'equipment' => array( 'child_seat' => 'Bebek koltuğu (özel)', 'first_aid_kit' => 'Zincir (özel)' ),
		);
		foreach ($labels as $grid => $map) {
			update_option('mhmrentiva_vehicle_' . $grid, $map);
		}

		$this->save(array(
			'details-grid_order'   => wp_json_encode(array( 'seats' )),
			'features-grid_order'  => wp_json_encode(array( 'navigation' )),
			'equipment-grid_order' => wp_json_encode(array( 'child_seat' )),
		));

		foreach ($labels as $grid => $map) {
			$this->assertSame($map, get_option('mhmrentiva_vehicle_' . $grid), $grid . ': keys not in the order keep their label');
		}
	}

	public function test_item_order_ajax_action_is_gone(): void
	{
		VehicleMeta::register();

		$this->assertFalse(has_action('wp_ajax_mhmrentiva_save_item_order'));
		$this->assertFalse(method_exists(VehicleMeta::class, 'ajax_save_item_order'));
	}
}
