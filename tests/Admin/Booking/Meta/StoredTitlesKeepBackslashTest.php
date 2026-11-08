<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Meta;

use MHMRentiva\Admin\Addons\AddonManager;
use MHMRentiva\Admin\Booking\Helpers\Util;
use MHMRentiva\Admin\Booking\Meta\BookingEditMetaBox;
use MHMRentiva\Admin\Booking\Meta\BookingMeta;
use WP_UnitTestCase;

/**
 * Backslash class, second write (2026-11-08): a title read back from the
 * database (vehicle, add-on) keeps its backslash, and rewriting it into a
 * booking -- the booking title on a vehicle change, the add-on lines -- went
 * through an unslashing writer without wp_slash().
 */
final class StoredTitlesKeepBackslashTest extends WP_UnitTestCase
{
	public function tearDown(): void
	{
		$_POST = array();
		BookingMeta::reset_edit_availability_verdicts();
		parent::tearDown();
	}

	private function post_with_title(string $type, string $title): int
	{
		return (int) wp_insert_post(wp_slash(array(
			'post_type'   => $type,
			'post_status' => 'publish',
			'post_title'  => $title,
		)));
	}

	public function test_add_on_lines_keep_the_add_on_title(): void
	{
		$addon = $this->post_with_title(\MHMRentiva\Admin\Addons\AddonPostType::POST_TYPE, 'Koltuk A\B');
		$this->assertSame('Koltuk A\B', get_post($addon)->post_title, 'Fixture: the title is stored with its backslash.');
		update_post_meta($addon, 'mhmrentiva_addon_price', '10');
		update_post_meta($addon, 'mhmrentiva_addon_enabled', '1');
		$booking = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_booking' ));

		AddonManager::save_booking_addons($booking, array( 'selected_addons' => array( $addon ), 'rental_days' => 2 ));

		$lines = (array) get_post_meta($booking, '_mhmrentiva_addon_details', true);
		$this->assertNotEmpty($lines);
		$this->assertSame('Koltuk A\B', $lines[0]['title'] ?? null);
	}

	/**
	 * Fable #177 M1: the third writer of the add-on lines,
	 * BookingMeta::recalculate_booking_costs(), on a date change saved by the
	 * BookingMeta saver alone.
	 */
	public function test_a_date_change_keeps_the_add_on_title_in_the_lines(): void
	{
		wp_set_current_user(self::factory()->user->create(array( 'role' => 'administrator' )));
		$vehicle = $this->post_with_title('mhmrentiva_vehicle', 'Araç');
		update_post_meta($vehicle, '_mhmrentiva_price_per_day', '100');
		update_post_meta($vehicle, '_mhmrentiva_vehicle_status', 'active');
		$addon = $this->post_with_title(\MHMRentiva\Admin\Addons\AddonPostType::POST_TYPE, 'Koltuk A\B');
		update_post_meta($addon, 'mhmrentiva_addon_price', '10');
		update_post_meta($addon, 'mhmrentiva_addon_enabled', '1');
		$range   = Util::parse_datetimes('2099-07-10', '10:00', '2099-07-12', '10:00');
		$booking = (int) self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_booking',
			'post_status' => 'publish',
			'meta_input'  => array(
				'_mhmrentiva_vehicle_id'   => $vehicle,
				'_mhmrentiva_status'       => 'confirmed',
				'_mhmrentiva_payment_type' => 'full',
				'_mhmrentiva_pickup_date'  => '2099-07-10',
				'_mhmrentiva_dropoff_date' => '2099-07-12',
				'_mhmrentiva_pickup_time'  => '10:00',
				'_mhmrentiva_dropoff_time' => '10:00',
				'_mhmrentiva_start_time'   => '10:00',
				'_mhmrentiva_end_time'     => '10:00',
				'_mhmrentiva_start_ts'     => $range['start_ts'],
				'_mhmrentiva_end_ts'       => $range['end_ts'],
			),
		));
		AddonManager::save_booking_addons($booking, array( 'selected_addons' => array( $addon ), 'rental_days' => 2 ));
		$_POST = wp_slash(array(
			'_wpnonce'                     => wp_create_nonce('update-post_' . $booking),
			'mhmrentiva_edit_vehicle_id'   => (string) $vehicle,
			'mhmrentiva_edit_pickup_date'  => '2099-07-10',
			'mhmrentiva_edit_pickup_time'  => '10:00',
			'mhmrentiva_edit_dropoff_date' => '2099-07-14',
			'mhmrentiva_edit_dropoff_time' => '10:00',
			'mhmrentiva_edit_status'       => 'confirmed',
			'selected_addons'              => array( (string) $addon ),
		));

		BookingMeta::save_meta($booking, get_post($booking));

		$this->assertSame('2099-07-14', get_post_meta($booking, '_mhmrentiva_dropoff_date', true), 'Fixture: the dates moved.');
		$lines = (array) get_post_meta($booking, '_mhmrentiva_addon_details', true);
		$this->assertSame('Koltuk A\B', $lines[0]['title'] ?? null);
	}

	public function test_the_booking_title_keeps_the_new_vehicles_title(): void
	{
		wp_set_current_user(self::factory()->user->create(array( 'role' => 'administrator' )));
		$old = $this->post_with_title('mhmrentiva_vehicle', 'Eski Araç');
		$new = $this->post_with_title('mhmrentiva_vehicle', 'Fiat A\B');
		foreach (array( $old, $new ) as $vehicle) {
			update_post_meta($vehicle, '_mhmrentiva_price_per_day', '100');
			update_post_meta($vehicle, '_mhmrentiva_vehicle_status', 'active');
		}
		$range   = Util::parse_datetimes('2099-06-10', '10:00', '2099-06-12', '10:00');
		$booking = (int) self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_booking',
			'post_status' => 'publish',
			'meta_input'  => array(
				'_mhmrentiva_vehicle_id'   => $old,
				'_mhmrentiva_status'       => 'confirmed',
				'_mhmrentiva_payment_type' => 'full',
				'_mhmrentiva_pickup_date'  => '2099-06-10',
				'_mhmrentiva_dropoff_date' => '2099-06-12',
				'_mhmrentiva_pickup_time'  => '10:00',
				'_mhmrentiva_dropoff_time' => '10:00',
				'_mhmrentiva_start_time'   => '10:00',
				'_mhmrentiva_end_time'     => '10:00',
				'_mhmrentiva_start_ts'     => $range['start_ts'],
				'_mhmrentiva_end_ts'       => $range['end_ts'],
			),
		));
		$_POST = wp_slash(array(
			'_wpnonce'                     => wp_create_nonce('update-post_' . $booking),
			'mhmrentiva_edit_vehicle_id'   => (string) $new,
			'mhmrentiva_edit_pickup_date'  => '2099-06-10',
			'mhmrentiva_edit_pickup_time'  => '10:00',
			'mhmrentiva_edit_dropoff_date' => '2099-06-12',
			'mhmrentiva_edit_dropoff_time' => '10:00',
			'mhmrentiva_edit_status'       => 'confirmed',
		));

		BookingEditMetaBox::save_booking_details($booking);

		$this->assertSame((string) $new, (string) get_post_meta($booking, '_mhmrentiva_vehicle_id', true), 'Fixture: the vehicle moved.');
		$this->assertStringContainsString('Fiat A\B', get_post($booking)->post_title);
	}
}
