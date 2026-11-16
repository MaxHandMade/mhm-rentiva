<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Vehicle;

use MHMRentiva\Admin\Booking\Helpers\BookingConflicts;
use MHMRentiva\Admin\Vehicle\Hooks\VehicleTrashWarning;
use WP_UnitTestCase;

/**
 * Manager-lens finding #15 (2026-11-11): a vehicle with upcoming bookings went
 * to the Trash on one click with nothing said. The bookings kept pointing at
 * it; its page answered 404 to the customers holding them.
 *
 * Trashing stays allowed (it is reversible and a retired vehicle is a real
 * case); what changes is that the manager is told, on every path that trashes
 * (row action, bulk action, edit screen), and can put it back the way it was.
 *
 * @covers \MHMRentiva\Admin\Vehicle\Hooks\VehicleTrashWarning
 * @covers \MHMRentiva\Admin\Booking\Helpers\BookingConflicts::upcoming_booking_ids
 */
final class VehicleTrashWarningTest extends WP_UnitTestCase
{
	private int $admin;

	public function setUp(): void
	{
		parent::setUp();
		$this->admin = (int) self::factory()->user->create(array( 'role' => 'administrator' ));
		wp_set_current_user($this->admin);
		// PHPUnit does not boot the plugin's own hooks (fingerprint §Ortam):
		// the chain under test registers itself.
		VehicleTrashWarning::register();
	}

	public function tearDown(): void
	{
		remove_action('trashed_post', array( VehicleTrashWarning::class, 'on_trashed' ));
		remove_action('admin_notices', array( VehicleTrashWarning::class, 'render_notice' ));
		remove_filter('wp_untrash_post_status', array( VehicleTrashWarning::class, 'untrash_status' ));
		delete_transient(VehicleTrashWarning::transient_key($this->admin));
		parent::tearDown();
	}

	private function vehicle(string $title = 'qa-Clio'): int
	{
		return (int) self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_vehicle',
			'post_status' => 'publish',
			'post_title'  => $title,
		));
	}

	private function booking(int $vehicle, string $status, int $start, int $end, array $extra = array()): int
	{
		$id = (int) self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_booking',
			'post_status' => 'publish',
		));
		$meta = array_merge(
			array(
				'_mhmrentiva_vehicle_id'    => (string) $vehicle,
				'_mhmrentiva_status'        => $status,
				'_mhmrentiva_start_ts'      => (string) $start,
				'_mhmrentiva_end_ts'        => (string) $end,
				'_mhmrentiva_pickup_date'   => gmdate('Y-m-d', $start),
				'_mhmrentiva_dropoff_date'  => gmdate('Y-m-d', $end),
			),
			$extra
		);
		foreach ($meta as $key => $value) {
			update_post_meta($id, $key, $value);
		}
		return $id;
	}

	private function notice(): string
	{
		set_current_screen('edit-mhmrentiva_vehicle');
		ob_start();
		VehicleTrashWarning::render_notice();
		return (string) ob_get_clean();
	}

	public function test_upcoming_bookings_are_the_ones_that_still_hold_the_vehicle(): void
	{
		$v    = $this->vehicle();
		$day  = DAY_IN_SECONDS;
		$now  = time();
		$keep = array(
			$this->booking($v, 'confirmed', $now + 10 * $day, $now + 12 * $day),
			$this->booking($v, 'in_progress', $now - $day, $now + $day),
			$this->booking($v, 'pending', $now + 20 * $day, $now + 21 * $day),
		);
		// Over, cancelled, or a pending payment past its deadline: none holds it.
		$this->booking($v, 'confirmed', $now - 10 * $day, $now - 8 * $day);
		$this->booking($v, 'cancelled', $now + 10 * $day, $now + 12 * $day);
		$this->booking($v, 'pending', $now + 30 * $day, $now + 31 * $day, array( '_mhmrentiva_payment_deadline' => gmdate('Y-m-d H:i:s', $now - HOUR_IN_SECONDS) ));
		// Another vehicle's booking.
		$this->booking($this->vehicle('other'), 'confirmed', $now + 10 * $day, $now + 12 * $day);

		$ids = BookingConflicts::upcoming_booking_ids($v);
		sort($keep);
		sort($ids);

		$this->assertSame($keep, $ids);
	}

	public function test_trashing_a_vehicle_with_upcoming_bookings_warns_once_with_restore_and_booking_links(): void
	{
		$v = $this->vehicle('qa-Çöp Testi');
		$b = $this->booking($v, 'confirmed', time() + 5 * DAY_IN_SECONDS, time() + 7 * DAY_IN_SECONDS);

		wp_trash_post($v);
		$html = $this->notice();

		$this->assertStringContainsString('notice-warning', $html);
		$this->assertStringContainsString('qa-Çöp Testi', $html);
		$this->assertStringContainsString('post.php?post=' . $b . '&#038;action=edit', $html, 'Each upcoming booking links to its edit screen.');
		$this->assertStringContainsString('action=untrash', $html, 'The notice offers to restore the vehicle.');
		$this->assertStringContainsString('_wpnonce=' . wp_create_nonce('untrash-post_' . $v), $html, 'The restore link carries core\'s own untrash nonce.');

		$this->assertSame('', $this->notice(), 'The warning is shown once.');
	}

	public function test_no_warning_without_upcoming_bookings_or_for_other_post_types(): void
	{
		$v = $this->vehicle();
		$this->booking($v, 'cancelled', time() + 5 * DAY_IN_SECONDS, time() + 7 * DAY_IN_SECONDS);
		wp_trash_post($v);

		$page = (int) self::factory()->post->create(array( 'post_type' => 'page' ));
		wp_trash_post($page);

		$this->assertSame('', $this->notice());
	}

	public function test_a_vehicle_restored_before_the_notice_is_not_reported(): void
	{
		$v = $this->vehicle();
		$this->booking($v, 'confirmed', time() + 5 * DAY_IN_SECONDS, time() + 7 * DAY_IN_SECONDS);
		wp_trash_post($v);
		wp_untrash_post($v);

		$this->assertSame('', $this->notice());
	}

	public function test_a_restored_vehicle_gets_back_the_status_it_had(): void
	{
		$v = $this->vehicle();
		wp_trash_post($v);
		wp_untrash_post($v);
		$this->assertSame('publish', get_post_status($v), 'Core restores to draft since 5.6; a vehicle comes back as it was.');

		$page = (int) self::factory()->post->create(array( 'post_type' => 'page' ));
		wp_trash_post($page);
		wp_untrash_post($page);
		$this->assertSame('draft', get_post_status($page), 'Other post types keep core\'s behaviour.');
	}
}
