<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Helpers;

use MHMRentiva\Admin\Booking\Helpers\CancellationHandler;
use WP_UnitTestCase;

/**
 * Independent review finding H-01, which confirms a debt this project recorded
 * from audit round R18 on 2026-08-09 and never closed.
 *
 * CancellationHandler reads ownership from _mhmrentiva_customer_id in two
 * places. Nothing in either edition writes that key -- the field every writer
 * and every other reader uses is _mhmrentiva_customer_user_id
 * (WooCommerceBridge::create_booking_from_data(), the manual booking metabox,
 * the portal metabox, AccountController::can_access_receipt()). The read
 * therefore yields '' which casts to 0, never matches a real user id, and the
 * customer is refused.
 *
 * It fails closed, so it is not an IDOR -- it is a feature that has never worked:
 * a customer cannot cancel their own booking, and user_can_cancel() lies to the
 * UI about whether the button should even be offered.
 *
 * @covers \MHMRentiva\Admin\Booking\Helpers\CancellationHandler::cancel_booking
 * @covers \MHMRentiva\Admin\Booking\Helpers\CancellationHandler::user_can_cancel
 */
final class CancellationOwnershipTest extends WP_UnitTestCase
{
	private int $customer_id;
	private int $stranger_id;
	private int $booking_id;

	public function setUp(): void
	{
		parent::setUp();

		$this->customer_id = (int) self::factory()->user->create(array( 'role' => 'customer' ));
		$this->stranger_id = (int) self::factory()->user->create(array( 'role' => 'customer' ));

		$this->booking_id = (int) self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_booking',
			'post_status' => 'publish',
			'post_author' => 1,
		));

		// Written exactly as the live booking path writes it.
		update_post_meta($this->booking_id, '_mhmrentiva_customer_user_id', $this->customer_id);
		update_post_meta($this->booking_id, '_mhmrentiva_status', 'confirmed');
		// Comfortably inside the cancellation window.
		update_post_meta($this->booking_id, '_mhmrentiva_cancellation_deadline', gmdate('Y-m-d H:i:s', strtotime('+10 days')));
		update_post_meta($this->booking_id, '_mhmrentiva_pickup_date', gmdate('Y-m-d', strtotime('+14 days')));
	}

	/**
	 * RED before the fix: ownership is read from a key nobody writes, so the
	 * booking's own customer is refused with permission_denied.
	 */
	public function test_booking_owner_may_cancel_their_own_booking(): void
	{
		wp_set_current_user($this->customer_id);

		$result = CancellationHandler::cancel_booking($this->booking_id, $this->customer_id, 'Change of plans');

		if (is_wp_error($result)) {
			$this->assertNotSame(
				'permission_denied',
				$result->get_error_code(),
				'The customer the booking belongs to must not be refused on ownership. Error: ' . $result->get_error_message()
			);
		}

		$this->assertTrue(true);
	}

	/**
	 * The UI predicate must agree with the action.
	 */
	public function test_user_can_cancel_agrees_for_the_owner(): void
	{
		wp_set_current_user($this->customer_id);

		$this->assertTrue(
			CancellationHandler::user_can_cancel($this->booking_id, $this->customer_id),
			'user_can_cancel() must say yes for the booking owner, or the UI hides a button that would have worked.'
		);
	}

	/**
	 * Negative control: the guard must still refuse somebody else. Without this
	 * the fix could be "return true" and both tests above would pass.
	 */
	public function test_another_customer_may_not_cancel(): void
	{
		wp_set_current_user($this->stranger_id);

		$this->assertFalse(
			CancellationHandler::user_can_cancel($this->booking_id, $this->stranger_id),
			'A different customer must not be able to cancel this booking.'
		);

		$result = CancellationHandler::cancel_booking($this->booking_id, $this->stranger_id, 'Not mine');

		$this->assertTrue(is_wp_error($result), 'Cancelling somebody else\'s booking must fail.');
		$this->assertSame('permission_denied', $result->get_error_code());
	}

	/**
	 * Bookings written by an older version carry the legacy key. Reading it as a
	 * fallback keeps those cancellable instead of stranding them.
	 */
	public function test_legacy_ownership_key_is_still_honoured(): void
	{
		$legacy_booking = (int) self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_booking',
			'post_status' => 'publish',
		));
		update_post_meta($legacy_booking, '_mhmrentiva_customer_id', $this->customer_id);
		update_post_meta($legacy_booking, '_mhmrentiva_status', 'confirmed');
		update_post_meta($legacy_booking, '_mhmrentiva_cancellation_deadline', gmdate('Y-m-d H:i:s', strtotime('+10 days')));
		update_post_meta($legacy_booking, '_mhmrentiva_pickup_date', gmdate('Y-m-d', strtotime('+14 days')));

		wp_set_current_user($this->customer_id);

		$this->assertTrue(
			CancellationHandler::user_can_cancel($legacy_booking, $this->customer_id),
			'A booking carrying only the legacy ownership key must remain cancellable by its owner.'
		);
	}
}
