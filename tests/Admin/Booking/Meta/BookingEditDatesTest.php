<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Meta;

use MHMRentiva\Admin\Booking\Core\BookingPricingInputs;
use MHMRentiva\Admin\Booking\Meta\BookingEditCancellation;
use MHMRentiva\Admin\Booking\Meta\BookingEditDates;
use MHMRentiva\Admin\Booking\Meta\BookingEditMetaBox;
use MHMRentiva\Admin\Booking\Meta\BookingEditNotice;
use MHMRentiva\Admin\Booking\Meta\BookingMeta;
use WP_UnitTestCase;

/**
 * Function scan H4: a return before the pickup was saved. The client check
 * was bound to ids that do not exist and the server had none; the day count
 * took the absolute difference, so 1 May -> 27 April read as 4 days.
 *
 * Now both savers refuse the dates (and only the dates): every other field of
 * the Update is saved, the operator is told.
 *
 * @covers \MHMRentiva\Admin\Booking\Meta\BookingEditDates
 */
final class BookingEditDatesTest extends WP_UnitTestCase
{
	private int $vehicle;

	public function setUp(): void
	{
		parent::setUp();
		wp_set_current_user((int) self::factory()->user->create(array( 'role' => 'administrator' )));
		$this->vehicle = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_vehicle', 'post_status' => 'publish' ));
		BookingEditCancellation::reset();
		BookingPricingInputs::reset();
	}

	public function tearDown(): void
	{
		$_POST = array();
		parent::tearDown();
	}

	private function booking(string $pickup = '2099-05-01', string $dropoff = '2099-05-04'): int
	{
		$id = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_booking', 'post_status' => 'publish' ));
		foreach (array(
			'_mhmrentiva_status'       => 'confirmed',
			'_mhmrentiva_vehicle_id'   => (string) $this->vehicle,
			'_mhmrentiva_pickup_date'  => $pickup,
			'_mhmrentiva_start_time'   => '10:00',
			'_mhmrentiva_dropoff_date' => $dropoff,
			'_mhmrentiva_end_time'     => '10:00',
			'_mhmrentiva_rental_days'  => '3',
		) as $key => $value) {
			update_post_meta($id, $key, $value);
		}
		return $id;
	}

	/** @param array<string, string> $fields */
	private function update(int $id, array $fields): void
	{
		add_action('save_post', array( BookingEditMetaBox::class, 'save_booking_details' ), 5);
		add_action('save_post', array( BookingMeta::class, 'save_meta' ), 10, 2);
		$_POST = $fields + array(
			'mhmrentiva_booking_edit_meta_nonce' => wp_create_nonce('mhmrentiva_booking_edit_action'),
			'_wpnonce'                           => wp_create_nonce('update-post_' . $id),
			'mhmrentiva_edit_vehicle_id'         => (string) $this->vehicle,
			'mhmrentiva_edit_pickup_date'        => '2099-05-01',
			'mhmrentiva_edit_pickup_time'        => '10:00',
			'mhmrentiva_edit_dropoff_date'       => '2099-05-04',
			'mhmrentiva_edit_dropoff_time'       => '10:00',
			'mhmrentiva_edit_status'             => 'confirmed',
		);
		wp_update_post(array( 'ID' => $id, 'post_title' => 'Updated' ));
		$_POST = array();
		wp_cache_delete($id, 'post_meta');
	}

	public function test_the_rule(): void
	{
		$this->assertTrue(BookingEditDates::is_reversed('2099-05-04', '10:00', '2099-05-01', '10:00'));
		$this->assertFalse(BookingEditDates::is_reversed('2099-05-01', '10:00', '2099-05-01', '10:00'), 'Equal is left alone: a transfer or same-day booking may carry it.');
		$this->assertTrue(BookingEditDates::is_reversed('2099-05-01', '10:00', '2099-05-01', '09:00'), 'Same day, earlier return.');
		$this->assertFalse(BookingEditDates::is_reversed('2099-05-01', '10:00', '2099-05-01', '18:00'), 'Same day, later return.');
		$this->assertFalse(BookingEditDates::is_reversed('2099-05-01', '', '2099-05-02', ''));
		$this->assertFalse(BookingEditDates::is_reversed('', '', '2099-05-02', ''), 'Nothing to compare.');
	}

	public function test_a_reversed_range_is_not_saved_and_the_operator_is_told(): void
	{
		$id = $this->booking();

		$this->update($id, array(
			'mhmrentiva_edit_pickup_date'   => '2099-05-04',
			'mhmrentiva_edit_dropoff_date'  => '2099-05-01',
			'mhmrentiva_edit_special_notes' => 'kept',
		));

		$this->assertSame('2099-05-01', get_post_meta($id, '_mhmrentiva_pickup_date', true));
		$this->assertSame('2099-05-04', get_post_meta($id, '_mhmrentiva_dropoff_date', true));
		$this->assertSame('3', (string) get_post_meta($id, '_mhmrentiva_rental_days', true));
		$this->assertSame('kept', get_post_meta($id, '_mhmrentiva_special_notes', true), 'The other fields of the Update are saved.');
		$this->assertStringContainsString('The return date is before the pickup date. The dates were not changed.', BookingEditNotice::peek($id));
	}

	public function test_same_day_with_a_later_return_time_is_saved(): void
	{
		$id = $this->booking();

		$this->update($id, array(
			'mhmrentiva_edit_pickup_date'  => '2099-05-02',
			'mhmrentiva_edit_dropoff_date' => '2099-05-02',
			'mhmrentiva_edit_dropoff_time' => '18:00',
		));

		$this->assertSame('2099-05-02', get_post_meta($id, '_mhmrentiva_dropoff_date', true));
		$this->assertSame('18:00', get_post_meta($id, '_mhmrentiva_end_time', true));
		$this->assertSame('', BookingEditNotice::peek($id));
	}

	public function test_a_booking_in_the_past_still_saves_a_notes_only_change(): void
	{
		$id = $this->booking('2020-05-01', '2020-05-04');

		$this->update($id, array(
			'mhmrentiva_edit_pickup_date'   => '2020-05-01',
			'mhmrentiva_edit_dropoff_date'  => '2020-05-04',
			'mhmrentiva_edit_special_notes' => 'returned late',
		));

		$this->assertSame('returned late', get_post_meta($id, '_mhmrentiva_special_notes', true));
		$this->assertSame('', BookingEditNotice::peek($id));
	}
}
