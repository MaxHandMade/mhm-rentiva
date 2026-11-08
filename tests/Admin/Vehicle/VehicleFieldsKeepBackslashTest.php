<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Vehicle;

use MHMRentiva\Admin\Vehicle\ListTable\VehicleColumns;
use MHMRentiva\Admin\Vehicle\Meta\VehicleMeta;
use WP_UnitTestCase;

/**
 * Backslash class (2026-11-08): the vehicle's text details (plate, colour,
 * brand ...) are unslashed on input and update_post_meta() unslashes again.
 * Three writers: the details box (save_meta), the meta box handler
 * (save_vehicle_meta) and the list's quick edit.
 */
final class VehicleFieldsKeepBackslashTest extends WP_UnitTestCase
{
	private const PLATE = '34 AB\12';

	private int $vehicle_id;

	public function setUp(): void
	{
		parent::setUp();
		$this->vehicle_id = (int) self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_vehicle',
			'post_status' => 'publish',
		));
		wp_set_current_user(self::factory()->user->create(array( 'role' => 'administrator' )));
	}

	public function tearDown(): void
	{
		$_POST    = array();
		$_REQUEST = array();
		parent::tearDown();
	}

	/** A real request arrives slashed. */
	private function meta_box_post(array $fields): void
	{
		$_POST = wp_slash(array_merge(
			array( 'mhmrentiva_vehicle_meta_nonce' => wp_create_nonce('mhmrentiva_vehicle_meta_action') ),
			$fields
		));
	}

	public function test_the_details_box_keeps_the_backslash(): void
	{
		$this->meta_box_post(array(
			'mhmrentiva_license_plate' => self::PLATE,
			'mhmrentiva_color'         => 'Gri\Gümüş',
		));

		VehicleMeta::save_meta($this->vehicle_id, get_post($this->vehicle_id));

		$this->assertSame(self::PLATE, get_post_meta($this->vehicle_id, '_mhmrentiva_license_plate', true));
		$this->assertSame('Gri\Gümüş', get_post_meta($this->vehicle_id, '_mhmrentiva_color', true));
	}

	public function test_the_meta_box_handler_keeps_the_backslash(): void
	{
		$this->meta_box_post(array(
			'_mhmrentiva_license_plate' => self::PLATE,
			'_mhmrentiva_brand'         => 'Marka\X',
		));

		VehicleMeta::save_vehicle_meta($this->vehicle_id, array());

		$this->assertSame(self::PLATE, get_post_meta($this->vehicle_id, '_mhmrentiva_license_plate', true));
		$this->assertSame('Marka\X', get_post_meta($this->vehicle_id, '_mhmrentiva_brand', true));
	}

	public function test_quick_edit_keeps_the_backslash(): void
	{
		$_POST = wp_slash(array(
			'mhmrentiva_vehicle_quick_edit_nonce' => wp_create_nonce('mhmrentiva_vehicle_quick_edit'),
			'post_ID'                             => $this->vehicle_id,
			'mhmrentiva_license_plate'            => self::PLATE,
		));
		$_REQUEST = $_POST;

		VehicleColumns::save_quick_edit($this->vehicle_id);

		$this->assertSame(self::PLATE, get_post_meta($this->vehicle_id, '_mhmrentiva_license_plate', true));
	}
}
