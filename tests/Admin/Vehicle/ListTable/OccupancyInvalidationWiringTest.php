<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Vehicle\ListTable;

use MHMRentiva\Admin\Booking\Core\Hooks as BookingHooks;
use MHMRentiva\Admin\Booking\Core\Status;
use MHMRentiva\Admin\Core\Utilities\OccupancyMapService;
use MHMRentiva\Admin\Vehicle\ListTable\VehicleColumns;
use WP_UnitTestCase;

/**
 * Admin Faz 2 Task 2 — WHEN OccupancyMapService::invalidate() fires.
 *
 * Booking saves and booking status changes must both clear the
 * `mhmrentiva_vehicle_stats_*` transient prefix (occupancy maps included);
 * the vehicle-save path this task does NOT touch must keep behaving exactly
 * as it did before.
 *
 * The booking-save path was measurably broken before this task:
 * VehicleColumns::register() wired `save_post_mhmrentiva_booking` to
 * clear_vehicle_cache(), but that method opens with a
 * `get_post_type($post_id) === 'mhmrentiva_vehicle'` guard — always false on
 * a booking save, so the prefix DELETE never ran.
 *
 * @covers \MHMRentiva\Admin\Vehicle\ListTable\VehicleColumns
 * @covers \MHMRentiva\Admin\Booking\Core\Hooks
 */
final class OccupancyInvalidationWiringTest extends WP_UnitTestCase
{
	public function setUp(): void
	{
		parent::setUp();
		OccupancyMapService::reset_memo();

		// In production these are wired via Plugin::initialize_admin_services()
		// (VehicleColumns) — is_admin()-gated, so absent from the $wp_filter
		// snapshot WP_UnitTestCase restores before every test (same reason
		// BookingColumnsTitleEscapingTest re-registers BookingColumns) — and
		// via Plugin::initialize_services() -> initialize_remaining_services()
		// (Booking\Core\Hooks), which is UNCONDITIONAL and therefore already
		// present in that snapshot. Re-registering Hooks here is a defensive
		// no-op: WP dedupes identical static-method array callbacks.
		VehicleColumns::register();
		BookingHooks::register();
	}

	public function tearDown(): void
	{
		OccupancyMapService::reset_memo();
		OccupancyMapService::invalidate();
		parent::tearDown();
	}

	/**
	 * Populates the occmap transient for the standard 7-day window and
	 * returns [$start, $end] so callers can check the same key afterwards.
	 *
	 * @return array{0: string, 1: string}
	 */
	private function seed_occmap_transient(): array
	{
		$start = gmdate('Y-m-d');
		$end   = gmdate('Y-m-d', strtotime('+6 days'));
		OccupancyMapService::get_map($start, $end);

		return array( $start, $end );
	}

	private function occmap_transient_exists(string $start, string $end): bool
	{
		global $wpdb;

		$row = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
				'_transient_mhmrentiva_vehicle_stats_occmap_' . $start . '_' . $end
			)
		);

		return null !== $row;
	}

	/**
	 * The measured latent bug: a booking save must invalidate the
	 * mhmrentiva_vehicle_stats_ prefix (occupancy maps included). Before the
	 * fix, save_post_mhmrentiva_booking called clear_vehicle_cache(), whose
	 * get_post_type() === 'mhmrentiva_vehicle' guard always fails for a
	 * booking post — the DELETE never ran and this assertion failed RED.
	 */
	public function test_booking_save_clears_occupancy_transients(): void
	{
		$booking = self::factory()->post->create( array( 'post_type' => 'mhmrentiva_booking' ) );

		list($start, $end) = $this->seed_occmap_transient();
		$this->assertTrue( $this->occmap_transient_exists( $start, $end ), 'Precondition: transient must exist before the save.' );

		wp_update_post( array( 'ID' => $booking, 'post_title' => 'Triggers save_post' ) );

		$this->assertFalse( $this->occmap_transient_exists( $start, $end ), 'save_post_mhmrentiva_booking must invalidate the occupancy transient.' );
	}

	/**
	 * Status changes made via Status::update_status() go through
	 * update_post_meta(), not save_post — the only signal is the
	 * mhmrentiva_booking_status_changed action Status::update_status() fires.
	 */
	public function test_status_change_clears_occupancy_transients(): void
	{
		$booking = self::factory()->post->create( array( 'post_type' => 'mhmrentiva_booking' ) );
		update_post_meta( $booking, '_mhmrentiva_status', Status::PENDING );

		list($start, $end) = $this->seed_occmap_transient();
		$this->assertTrue( $this->occmap_transient_exists( $start, $end ), 'Precondition: transient must exist before the status change.' );

		$updated = Status::update_status( $booking, Status::CONFIRMED );
		$this->assertTrue( $updated, 'Precondition: the pending -> confirmed transition must succeed.' );

		$this->assertFalse( $this->occmap_transient_exists( $start, $end ), 'mhmrentiva_booking_status_changed must invalidate the occupancy transient.' );
	}

	/**
	 * Regression guard: the vehicle-save cache-clearing path this task does
	 * NOT touch must keep behaving exactly as before.
	 */
	public function test_vehicle_save_behavior_unchanged(): void
	{
		$vehicle = self::factory()->post->create( array( 'post_type' => 'mhmrentiva_vehicle' ) );

		list($start, $end) = $this->seed_occmap_transient();
		$this->assertTrue( $this->occmap_transient_exists( $start, $end ), 'Precondition: transient must exist before the save.' );

		wp_update_post( array( 'ID' => $vehicle, 'post_title' => 'Triggers save_post' ) );

		$this->assertFalse( $this->occmap_transient_exists( $start, $end ), 'save_post_mhmrentiva_vehicle must still invalidate the occupancy transient.' );
	}
}
