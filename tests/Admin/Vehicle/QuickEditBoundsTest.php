<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Vehicle;

use MHMRentiva\Admin\Vehicle\ListTable\VehicleColumns;
use WP_UnitTestCase;

/**
 * Quick edit writes the same meta keys as the full vehicle editor, so it must
 * apply the same bounds.
 *
 * The full editor clamps `_mhmrentiva_price_per_day` to >= 0 and
 * `_mhmrentiva_seats` to [1, max_seats] (`VehicleMeta::sanitize_meta_value`).
 * The quick-edit save mapped the same keys straight through `floatval` and
 * `intval`, so the row list offered a way around the editor's own validation:
 * a negative daily price is storable, and it multiplies into every rental total
 * downstream.
 *
 * Two surfaces writing one meta key must agree about what that key may hold —
 * otherwise the stricter one is decoration.
 *
 * @covers \MHMRentiva\Admin\Vehicle\ListTable\VehicleColumns::save_quick_edit
 */
final class QuickEditBoundsTest extends WP_UnitTestCase
{
	private int $vehicle_id;

	public function setUp(): void
	{
		parent::setUp();

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->vehicle_id = self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_vehicle',
				'post_status' => 'publish',
			)
		);
	}

	public function tearDown(): void
	{
		$_POST    = array();
		$_REQUEST = array();
		parent::tearDown();
	}

	private function quick_edit( array $fields ): void
	{
		$_POST = array_merge(
			array(
				
				'mhmrentiva_vehicle_quick_edit_nonce' => wp_create_nonce( 'mhmrentiva_vehicle_quick_edit' ),
				'post_ID' => $this->vehicle_id,
			),
			$fields
		);
		$_REQUEST = $_POST;

		VehicleColumns::save_quick_edit( $this->vehicle_id );
	}

	public function test_a_negative_daily_price_cannot_be_stored(): void
	{
		update_post_meta( $this->vehicle_id, '_mhmrentiva_price_per_day', 250 );

		$this->quick_edit( array( 'mhmrentiva_price_per_day' => '-500' ) );

		$this->assertGreaterThanOrEqual(
			0,
			(float) get_post_meta( $this->vehicle_id, '_mhmrentiva_price_per_day', true ),
			'A negative daily price multiplies into every rental total.'
		);
	}

	public function test_seats_cannot_be_zero(): void
	{
		update_post_meta( $this->vehicle_id, '_mhmrentiva_seats', 5 );

		$this->quick_edit( array( 'mhmrentiva_seats' => '0' ) );

		$this->assertGreaterThanOrEqual(
			1,
			(int) get_post_meta( $this->vehicle_id, '_mhmrentiva_seats', true )
		);
	}

	public function test_seats_cannot_exceed_the_configured_maximum(): void
	{
		update_post_meta( $this->vehicle_id, '_mhmrentiva_seats', 5 );

		$this->quick_edit( array( 'mhmrentiva_seats' => '99999' ) );

		$stored = (int) get_post_meta( $this->vehicle_id, '_mhmrentiva_seats', true );

		$this->assertLessThanOrEqual(
			100,
			$stored,
			'Quick edit accepted a seat count the full editor would have rejected.'
		);
	}

	/**
	 * The clamp must not break ordinary editing.
	 */
	public function test_a_valid_price_and_seat_count_still_save(): void
	{
		$this->quick_edit(
			array(
				'mhmrentiva_price_per_day' => '349.50',
				'mhmrentiva_seats'         => '7',
			)
		);

		$this->assertSame( 349.5, (float) get_post_meta( $this->vehicle_id, '_mhmrentiva_price_per_day', true ) );
		$this->assertSame( 7, (int) get_post_meta( $this->vehicle_id, '_mhmrentiva_seats', true ) );
	}
}
