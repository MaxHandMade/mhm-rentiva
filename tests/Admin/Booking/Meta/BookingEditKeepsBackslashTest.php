<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Meta;

use MHMRentiva\Admin\Booking\Meta\BookingEditMetaBox;
use MHMRentiva\Admin\Booking\Meta\BookingMeta;
use MHMRentiva\Admin\Booking\Helpers\Util;
use WP_UnitTestCase;

/**
 * The booking edit screen keeps a backslash the operator typed in the special
 * notes and the receipt note (Fable #175 delta M-A).
 *
 * A real request arrives slashed; VerifiedRequest::textarea() unslashes once,
 * and update_post_meta() unslashes AGAIN -- a value handed to it unslashed
 * loses its backslash ("ref BANK\6" → "ref BANK6"). #175 fixed the
 * cancellation path; this is the normal Update path, both savers.
 */
final class BookingEditKeepsBackslashTest extends WP_UnitTestCase
{
	private const NOTE = 'ref BANK\6 -- C:\kasa';

	private int $vehicle_id;

	public function setUp(): void
	{
		parent::setUp();
		$this->vehicle_id = (int) self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_vehicle',
			'post_status' => 'publish',
		));
		update_post_meta($this->vehicle_id, '_mhmrentiva_price_per_day', '1000');
		update_post_meta($this->vehicle_id, '_mhmrentiva_vehicle_status', 'active');
		wp_set_current_user(self::factory()->user->create(array( 'role' => 'administrator' )));
		BookingMeta::reset_edit_availability_verdicts();
		$_POST = array();
	}

	public function tearDown(): void
	{
		$_POST = array();
		BookingMeta::reset_edit_availability_verdicts();
		parent::tearDown();
	}

	private function booking(): int
	{
		$range = Util::parse_datetimes('2099-05-10', '10:00', '2099-05-12', '10:00');

		return (int) self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_booking',
			'post_status' => 'publish',
			'meta_input'  => array(
				'_mhmrentiva_vehicle_id'   => $this->vehicle_id,
				'_mhmrentiva_status'       => 'confirmed',
				'_mhmrentiva_payment_type' => 'full',
				'_mhmrentiva_pickup_date'  => '2099-05-10',
				'_mhmrentiva_dropoff_date' => '2099-05-12',
				'_mhmrentiva_start_time'   => '10:00',
				'_mhmrentiva_end_time'     => '10:00',
				'_mhmrentiva_pickup_time'  => '10:00',
				'_mhmrentiva_dropoff_time' => '10:00',
				'_mhmrentiva_start_ts'     => $range['start_ts'],
				'_mhmrentiva_end_ts'       => $range['end_ts'],
			),
		));
	}

	/**
	 * The edit form as one click on "Update" sends it -- slashed, like a real request.
	 *
	 * @param array<string, string> $extra
	 */
	private function post(int $booking_id, array $extra = array()): void
	{
		$_POST = wp_slash(array_merge(
			array(
				'_wpnonce'                      => wp_create_nonce('update-post_' . $booking_id),
				'mhmrentiva_edit_vehicle_id'    => (string) $this->vehicle_id,
				'mhmrentiva_edit_pickup_date'   => '2099-05-10',
				'mhmrentiva_edit_pickup_time'   => '10:00',
				'mhmrentiva_edit_dropoff_date'  => '2099-05-12',
				'mhmrentiva_edit_dropoff_time'  => '10:00',
				'mhmrentiva_edit_status'        => 'confirmed',
				'mhmrentiva_edit_special_notes' => self::NOTE,
			),
			$extra
		));
	}

	public function test_the_edit_box_saver_keeps_the_special_notes_backslash(): void
	{
		$id = $this->booking();
		$this->post($id);

		BookingEditMetaBox::save_booking_details($id);

		$this->assertSame(self::NOTE, get_post_meta($id, '_mhmrentiva_special_notes', true));
	}

	public function test_the_booking_meta_saver_keeps_the_special_notes_backslash(): void
	{
		$id = $this->booking();
		$this->post($id);

		BookingMeta::save_meta($id, get_post($id));

		$this->assertSame(self::NOTE, get_post_meta($id, '_mhmrentiva_special_notes', true));
	}

	public function test_both_savers_together_keep_the_special_notes_backslash(): void
	{
		$id = $this->booking();
		$this->post($id);

		BookingEditMetaBox::save_booking_details($id);
		BookingMeta::save_meta($id, get_post($id));

		$this->assertSame(self::NOTE, get_post_meta($id, '_mhmrentiva_special_notes', true));
	}

	public function test_the_receipt_note_keeps_its_backslash(): void
	{
		$id = $this->booking();
		$this->post($id, array(
			'mhmrentiva_receipt_nonce' => wp_create_nonce('mhmrentiva_receipt_review'),
			'mhmrentiva_receipt_note'  => self::NOTE,
		));

		BookingMeta::save_meta($id, get_post($id));

		$this->assertSame(self::NOTE, get_post_meta($id, '_mhmrentiva_receipt_note', true));
	}
}
