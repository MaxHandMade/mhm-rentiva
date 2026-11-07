<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Meta;

use MHMRentiva\Admin\Booking\Actions\DepositManagementAjax;
use MHMRentiva\Admin\Booking\Core\BookingPricingInputs;
use MHMRentiva\Admin\Booking\Core\Status;
use MHMRentiva\Admin\Booking\Meta\BookingEditCancellation;
use MHMRentiva\Admin\Booking\Meta\BookingEditMetaBox;
use MHMRentiva\Admin\Booking\Meta\BookingEditNotice;
use MHMRentiva\Admin\Booking\Meta\BookingMeta;
use MHMRentiva\Admin\Payment\Core\RefundStatus;
use WP_Ajax_UnitTestCase;

/**
 * One cancellation path (spec PR 3b "Tek iptal yolu", plan PR 3b-2 Task 1).
 *
 * Measured before (fonksiyon-taramasi.md §8): choosing Cancelled in the status
 * list and pressing Update only flipped the status -- no refund, no
 * cancellation record, two mails instead of five, no
 * mhmrentiva_booking_cancelled for Pro. Now the edit form hands the booking to
 * CancellationHandler::cancel_booking() exactly as the button does.
 *
 * A cancellation request writes nothing that moves money (Fable plan audit
 * C1): the handler refunds from the STORED total and frees the STORED dates,
 * so a date or add-on changed in the same Update would open a refund for
 * money that was never taken.
 *
 * @covers \MHMRentiva\Admin\Booking\Meta\BookingEditCancellation
 * @covers \MHMRentiva\Admin\Booking\Meta\BookingEditNotice
 */
final class BookingEditCancellationTest extends WP_Ajax_UnitTestCase
{
	private int $admin;
	private int $vehicle;

	public function setUp(): void
	{
		parent::setUp();
		$this->admin   = (int) self::factory()->user->create(array( 'role' => 'administrator' ));
		$this->vehicle = (int) self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_vehicle',
			'post_status' => 'publish',
			'post_title'  => 'Fiat Egea',
		));
		wp_set_current_user($this->admin);
		DepositManagementAjax::register();
		BookingEditCancellation::reset();
		BookingPricingInputs::reset();
		reset_phpmailer_instance();
	}

	public function tearDown(): void
	{
		$_POST = array();
		BookingEditCancellation::reset();
		parent::tearDown();
	}

	/** The §8 start state: confirmed, offline, fully paid 2.550. */
	private function booking(string $status = 'confirmed'): int
	{
		$id = (int) self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_booking',
			'post_status' => 'publish',
		));
		foreach (array(
			'_mhmrentiva_status'           => $status,
			'_mhmrentiva_vehicle_id'       => (string) $this->vehicle,
			'_mhmrentiva_pickup_date'      => '2099-05-01',
			'_mhmrentiva_start_time'       => '10:00',
			'_mhmrentiva_dropoff_date'     => '2099-05-04',
			'_mhmrentiva_end_time'         => '10:00',
			'_mhmrentiva_rental_days'      => '3',
			'_mhmrentiva_total_price'      => '2550',
			'_mhmrentiva_remaining_amount' => '0',
			'_mhmrentiva_payment_status'   => 'paid',
			'_mhmrentiva_payment_type'     => 'full',
			'_mhmrentiva_payment_method'   => 'offline',
			'_mhmrentiva_customer_email'   => 'fonk@example.test',
		) as $key => $value) {
			update_post_meta($id, $key, $value);
		}
		return $id;
	}

	/** @param array<string, mixed> $fields */
	private function update_from_form(int $id, array $fields = array()): void
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
			'mhmrentiva_edit_status'             => 'cancelled',
		);
		wp_update_post(array( 'ID' => $id, 'post_title' => 'Updated' ));
		$_POST = array();
	}

	private function cancel_with_button(int $id): void
	{
		$_POST = array(
			'nonce'      => wp_create_nonce('mhmrentiva_deposit_management_action'),
			'booking_id' => $id,
		);
		try {
			$this->_handleAjax('mhmrentiva_deposit_cancel_booking');
		} catch (\WPAjaxDieContinueException | \WPAjaxDieStopException $e) {
			// wp_send_json_* ends the request.
		}
		$_POST = array();
	}

	/** @return array<string, mixed> */
	private function outcome(int $id): array
	{
		wp_cache_delete($id, 'post_meta');
		$data = get_post_meta($id, '_mhmrentiva_cancellation_data', true);
		$logs = (array) get_post_meta($id, '_mhmrentiva_booking_logs', true);
		return array(
			'status'          => Status::get($id),
			'payment_status'  => get_post_meta($id, '_mhmrentiva_payment_status', true),
			'refund_status'   => RefundStatus::get($id),
			'refunded_amount' => get_post_meta($id, '_mhmrentiva_refunded_amount', true),
			'refund_asked'    => '' !== (string) get_post_meta($id, '_mhmrentiva_refund_requested_at', true),
			'cancelled_by'    => is_array($data) ? ( $data['cancelled_by'] ?? null ) : null,
			'logs'            => array_values(array_map(static fn($l) => $l['action'] ?? '', $logs)),
			'total'           => get_post_meta($id, '_mhmrentiva_total_price', true),
			'dates'           => get_post_meta($id, '_mhmrentiva_pickup_date', true) . '..' . get_post_meta($id, '_mhmrentiva_dropoff_date', true),
		);
	}

	private function mails(): int
	{
		return count(tests_retrieve_phpmailer_instance()->mock_sent);
	}

	public function test_list_cancel_equals_button_cancel(): void
	{
		$by_form   = $this->booking();
		$by_button = $this->booking();

		$before         = did_action('mhmrentiva_booking_cancelled');
		$this->update_from_form($by_form);
		$form_actions = did_action('mhmrentiva_booking_cancelled') - $before;
		$form_mails   = $this->mails();

		reset_phpmailer_instance();
		$before = did_action('mhmrentiva_booking_cancelled');
		$this->cancel_with_button($by_button);
		$button_actions = did_action('mhmrentiva_booking_cancelled') - $before;
		$button_mails   = $this->mails();

		$this->assertSame(1, $form_actions, 'The form fires mhmrentiva_booking_cancelled once.');
		$this->assertSame($button_actions, $form_actions);
		$this->assertSame($button_mails, $form_mails, 'Same mails as the button.');
		$this->assertSame($this->outcome($by_button), $this->outcome($by_form));
		$this->assertSame(Status::CANCELLED, Status::get($by_form));
		$this->assertTrue($this->outcome($by_form)['refund_asked'], 'The refund started.');
	}

	public function test_form_cancel_fires_booking_cancelled_once_with_the_stored_dates(): void
	{
		$id   = $this->booking();
		$seen = array();
		add_action(
			'mhmrentiva_booking_cancelled',
			static function ($booking_id, $user_id, $reason) use (&$seen) {
				$seen[] = array( $booking_id, $user_id, $reason, get_post_meta($booking_id, '_mhmrentiva_pickup_date', true) );
			},
			10,
			3
		);

		$this->update_from_form($id, array( 'mhmrentiva_edit_pickup_date' => '2099-06-01', 'mhmrentiva_edit_dropoff_date' => '2099-06-09' ));

		$this->assertSame(array( array( $id, $this->admin, '', '2099-05-01' ) ), $seen);
	}

	public function test_cancel_with_changed_dates_refunds_the_stored_total_and_keeps_the_dates(): void
	{
		$id = $this->booking();

		$this->update_from_form($id, array( 'mhmrentiva_edit_pickup_date' => '2099-06-01', 'mhmrentiva_edit_dropoff_date' => '2099-06-09' ));

		$outcome = $this->outcome($id);
		$this->assertSame(Status::CANCELLED, $outcome['status']);
		$this->assertSame('2550', $outcome['total'], 'Nothing re-priced the booking.');
		$this->assertSame('2099-05-01..2099-05-04', $outcome['dates']);
		$this->assertStringContainsString('Other changes in the form were not saved', BookingEditNotice::peek($id));
	}

	public function test_an_unchanged_form_raises_no_other_changes_notice(): void
	{
		$id = $this->booking();

		$this->update_from_form($id);

		$this->assertSame('', BookingEditNotice::peek($id));
	}

	public function test_cancel_with_changed_vehicle_does_not_re_enter_save_post(): void
	{
		$id    = $this->booking();
		$other = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_vehicle', 'post_status' => 'publish' ));
		$runs  = 0;
		add_action('save_post_mhmrentiva_booking', static function () use (&$runs) {
			++$runs;
		});

		$this->update_from_form($id, array( 'mhmrentiva_edit_vehicle_id' => (string) $other ));

		$this->assertSame(1, $runs, 'The vehicle change (and its nested wp_update_post) did not run.');
		$this->assertSame((string) $this->vehicle, get_post_meta($id, '_mhmrentiva_vehicle_id', true));
		$this->assertSame(Status::CANCELLED, Status::get($id));
	}

	public function test_handler_runs_once_per_update(): void
	{
		$id   = $this->booking();
		$runs = 0;
		add_action('mhmrentiva_booking_cancelled', static function () use (&$runs) {
			++$runs;
		});

		$this->update_from_form($id);

		$this->assertSame(1, $runs);
		$this->assertSame('', BookingEditNotice::peek($id), 'already_cancelled from a second run would be a notice.');
	}

	public function test_in_progress_booking_is_not_cancelled_from_the_form(): void
	{
		$id = $this->booking('in_progress');

		$this->update_from_form($id);

		$this->assertSame('in_progress', Status::get($id));
		$this->assertSame('', (string) get_post_meta($id, '_mhmrentiva_refund_requested_at', true));
		$this->assertStringContainsString('A rental that has started cannot be cancelled here.', BookingEditNotice::peek($id));
	}

	public function test_completed_booking_is_not_cancelled_from_the_form(): void
	{
		$id = $this->booking('completed');

		$this->update_from_form($id);

		$this->assertSame('completed', Status::get($id));
		$this->assertStringContainsString('This booking cannot be cancelled.', BookingEditNotice::peek($id));
	}

	public function test_actor_without_money_rights_is_not_offered_cancelled_and_a_forged_post_is_refused(): void
	{
		// Editing a booking needs manage_options, which also grants money
		// rights; an actor who may edit but not cancel exists where a site
		// narrows mhmrentiva_may_move_money (the documented seam).
		$id = $this->booking();
		add_filter('mhmrentiva_may_move_money', static fn($allowed, $booking_id, $actor, $surface) => 'cancel' === $surface ? false : $allowed, 10, 4);
		$this->assertTrue(current_user_can('edit_post', $id), 'Fixture: the actor may edit the booking.');

		ob_start();
		BookingEditMetaBox::render(get_post($id));
		$html = (string) ob_get_clean();
		$this->assertStringNotContainsString('value="cancelled"', $html);

		$this->update_from_form($id);

		$this->assertSame('confirmed', Status::get($id));
		$this->assertNotSame('', BookingEditNotice::peek($id));
	}

	public function test_cancelled_booking_keeps_cancelled_selected_for_an_actor_who_may_not_cancel(): void
	{
		$id = $this->booking('cancelled');
		add_filter('mhmrentiva_may_move_money', static fn($allowed, $booking_id, $actor, $surface) => 'cancel' === $surface ? false : $allowed, 10, 4);

		ob_start();
		BookingEditMetaBox::render(get_post($id));
		$html = (string) ob_get_clean();
		$this->assertMatchesRegularExpression('/value="cancelled"\s+selected/', $html);

		// A notes-only Update posts the stored status back.
		$this->update_from_form($id, array( 'mhmrentiva_edit_special_notes' => 'called the customer' ));

		$this->assertSame(Status::CANCELLED, Status::get($id));
	}

	public function test_other_status_changes_still_use_update_status(): void
	{
		$id = $this->booking();

		$this->update_from_form($id, array( 'mhmrentiva_edit_status' => 'completed' ));

		$this->assertSame('completed', Status::get($id));
		$this->assertSame('', (string) get_post_meta($id, '_mhmrentiva_refund_requested_at', true));
	}

	public function test_already_cancelled_booking_saved_again_raises_no_notice(): void
	{
		$id = $this->booking();
		$this->update_from_form($id);
		BookingEditCancellation::reset();
		BookingEditNotice::take($id);

		$this->update_from_form($id);

		$this->assertSame('', BookingEditNotice::peek($id));
		$this->assertSame(Status::CANCELLED, Status::get($id));
	}

	public function test_special_notes_are_saved_with_the_cancellation(): void
	{
		$id = $this->booking();

		$this->update_from_form($id, array( 'mhmrentiva_edit_special_notes' => 'customer asked by phone' ));

		$this->assertSame('customer asked by phone', get_post_meta($id, '_mhmrentiva_special_notes', true));
		$this->assertSame(Status::CANCELLED, Status::get($id));
	}

	public function test_a_refusal_drops_core_updated_message(): void
	{
		$id = $this->booking('completed');

		$this->update_from_form($id);

		$location = apply_filters('redirect_post_location', admin_url('post.php?post=' . $id . '&action=edit&message=1'), $id);
		$this->assertStringNotContainsString('message=1', $location);
	}

	/**
	 * Fable code audit M-7: without the edit box's own nonce its saver does not
	 * run; the priority-10 saver then runs the cancellation itself, through the
	 * same handler.
	 */
	public function test_the_priority_10_saver_alone_runs_the_same_cancellation(): void
	{
		$id = $this->booking();
		$this->update_from_form($id, array( 'mhmrentiva_booking_edit_meta_nonce' => 'not-a-nonce' ));

		$outcome = $this->outcome($id);
		$this->assertSame(Status::CANCELLED, $outcome['status']);
		$this->assertTrue($outcome['refund_asked']);
		$this->assertSame(array( 'booking_cancelled' ), $outcome['logs']);
	}

	public function test_a_refused_cancellation_writes_only_the_notes(): void
	{
		$id = $this->booking('in_progress');

		$this->update_from_form($id, array(
			'mhmrentiva_edit_pickup_date'   => '2099-06-01',
			'mhmrentiva_edit_dropoff_date'  => '2099-06-09',
			'mhmrentiva_edit_special_notes' => 'asked to cancel',
		));

		$outcome = $this->outcome($id);
		$this->assertSame('in_progress', $outcome['status']);
		$this->assertSame('2099-05-01..2099-05-04', $outcome['dates']);
		$this->assertSame('2550', $outcome['total']);
		$this->assertSame('asked to cancel', get_post_meta($id, '_mhmrentiva_special_notes', true));
	}
}
