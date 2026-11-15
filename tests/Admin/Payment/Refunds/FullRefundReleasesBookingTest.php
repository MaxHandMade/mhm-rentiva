<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Payment\Refunds;

use MHMRentiva\Admin\Booking\Core\Status;
use MHMRentiva\Admin\Booking\Helpers\BookingConflicts;
use MHMRentiva\Tests\Support\WooCommerceFixtures;
use WP_UnitTestCase;

/**
 * A booking refunded in full from the WooCommerce order screen before the
 * rental is over must let go of the car.
 *
 * Measured on a dev site (2026-11-15): a confirmed booking whose order was
 * refunded in full kept `_mhmrentiva_status = confirmed` with
 * `_mhmrentiva_payment_status = refunded`. Both refund paths
 * (handle_order_status_change()'s 'refunded' case and handle_order_refunded())
 * asked Status::update_status(..., 'refunded'), which Status::can_transition()
 * allows only from 'completed' -- so the call failed silently and the booking
 * went on holding its dates (BookingConflicts counts confirmed).
 *
 * The model is not changed: the booking status describes the rental and the
 * payment status the money (CancellationInitiatesRefundTest). A rental that
 * will not happen is CANCELLED, its money REFUNDED; a completed rental refunded
 * in full stays 'refunded' as before.
 *
 * @covers \MHMRentiva\Admin\Payment\WooCommerce\WooCommerceBridge::handle_order_refunded
 * @covers \MHMRentiva\Admin\Payment\WooCommerce\WooCommerceBridge::handle_order_status_change
 */
final class FullRefundReleasesBookingTest extends WP_UnitTestCase
{
	use WooCommerceFixtures;

	private int $vehicle_id;
	private int $booking_id;
	private int $start;
	private int $end;

	public function setUp(): void
	{
		parent::setUp();
		$this->require_woocommerce();

		$this->vehicle_id = (int) self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_vehicle',
				'post_status' => 'publish',
			)
		);

		$this->start = strtotime('+20 days 10:00');
		$this->end   = strtotime('+23 days 10:00');

		$this->booking_id = (int) self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_booking',
				'post_status' => 'publish',
			)
		);
		update_post_meta($this->booking_id, '_mhmrentiva_vehicle_id', $this->vehicle_id);
		update_post_meta($this->booking_id, '_mhmrentiva_start_ts', $this->start);
		update_post_meta($this->booking_id, '_mhmrentiva_end_ts', $this->end);
		update_post_meta($this->booking_id, '_mhmrentiva_pickup_date', wp_date('Y-m-d', $this->start));
		update_post_meta($this->booking_id, '_mhmrentiva_dropoff_date', wp_date('Y-m-d', $this->end));
		update_post_meta($this->booking_id, '_mhmrentiva_pickup_time', '10:00');
		update_post_meta($this->booking_id, '_mhmrentiva_dropoff_time', '10:00');
		update_post_meta($this->booking_id, '_mhmrentiva_payment_status', 'paid');
	}

	/** @return array<string, array{0: string}> */
	public static function rentals_not_started(): array
	{
		return array(
			'confirmed'        => array( Status::CONFIRMED ),
			'pending'          => array( Status::PENDING ),
			'pending payment'  => array( Status::PENDING_PAYMENT ),
		);
	}

	/**
	 * @dataProvider rentals_not_started
	 */
	public function test_a_full_refund_cancels_a_rental_that_has_not_started_and_frees_the_car(string $status): void
	{
		$order = $this->paid_order('300', $status);

		$this->assertTrue(BookingConflicts::is_booked($this->vehicle_id, $this->start, $this->end), 'Guard: the booking holds its dates before the refund.');

		$this->refund($order, '300');

		$this->assertSame(Status::CANCELLED, Status::get($this->booking_id), 'A rental that will not happen is cancelled.');
		$this->assertSame('refunded', (string) get_post_meta($this->booking_id, '_mhmrentiva_payment_status', true), 'The money state is unchanged: refunded.');
		$this->assertFalse(BookingConflicts::is_booked($this->vehicle_id, $this->start, $this->end), 'The refunded booking must not keep holding the car.');
	}

	/**
	 * The WooCommerce-screen refund already mails the customer about the refund
	 * (handle_order_refunded() -> RefundNotifications). The cancellation that
	 * frees the car is the same event: the customer gets that one mail, not a
	 * second "booking cancelled" on top (BookingNotifications'
	 * owned-notice rule, as AutoCancel uses it). Admin notices are untouched.
	 */
	public function test_the_customer_gets_one_mail_for_a_refund_that_cancels_the_rental(): void
	{
		if (false === has_action('mhmrentiva_booking_status_changed', array( \MHMRentiva\Admin\Emails\Notifications\BookingNotifications::class, 'on_status_changed' ))) {
			\MHMRentiva\Admin\Emails\Notifications\BookingNotifications::register();
		}
		update_post_meta($this->booking_id, '_mhmrentiva_contact_email', 'customer@example.test');
		update_post_meta($this->booking_id, '_mhmrentiva_customer_email', 'customer@example.test');
		$order = $this->paid_order('300', Status::CONFIRMED);

		$subjects = array();
		$spy      = static function (array $args) use (&$subjects): array {
			if ('customer@example.test' === ( $args['to'] ?? '' )) {
				$subjects[] = (string) ( $args['subject'] ?? '' );
			}
			return $args;
		};
		add_filter('wp_mail', $spy, 999);
		$this->refund($order, '300');
		remove_filter('wp_mail', $spy, 999);

		$this->assertSame(Status::CANCELLED, Status::get($this->booking_id), 'Guard: the refund cancelled the rental.');
		$this->assertCount(1, $subjects, 'One customer mail for one event; got: ' . implode(' | ', $subjects));
	}

	/**
	 * The car is with the customer: its dates stay held, or the same car could be
	 * sold again for days it is out. The money state still says refunded.
	 */
	public function test_a_rental_in_progress_refunded_in_full_keeps_its_dates(): void
	{
		$order = $this->paid_order('300', Status::IN_PROGRESS);

		$this->refund($order, '300');

		$this->assertSame(Status::IN_PROGRESS, Status::get($this->booking_id));
		$this->assertSame('refunded', (string) get_post_meta($this->booking_id, '_mhmrentiva_payment_status', true));
		$this->assertTrue(BookingConflicts::is_booked($this->vehicle_id, $this->start, $this->end));
	}

	/**
	 * Deposit + remaining: draining the deposit order alone is not a full refund
	 * of the booking, so the rental stays; refunding both legs cancels it.
	 */
	public function test_a_two_order_booking_is_cancelled_only_when_both_orders_are_refunded(): void
	{
		$deposit   = $this->paid_order('30', Status::CONFIRMED);
		$remaining = $this->create_paid_order_for_booking($this->booking_id, '70');
		update_post_meta($this->booking_id, '_mhmrentiva_remaining_order_id', $remaining->get_id());
		foreach ($remaining->get_items() as $item) {
			$item->add_meta_data('_mhmrentiva_booking_id', $this->booking_id, true);
			$item->save();
		}
		update_post_meta($this->booking_id, '_mhmrentiva_status', Status::CONFIRMED);

		$this->refund($deposit, '30');

		$this->assertSame(Status::CONFIRMED, Status::get($this->booking_id), 'One drained leg is not the whole booking.');
		$this->assertTrue(BookingConflicts::is_booked($this->vehicle_id, $this->start, $this->end));

		$this->refund($remaining, '70');

		$this->assertSame(Status::CANCELLED, Status::get($this->booking_id), 'Both legs refunded: the rental will not happen.');
		$this->assertFalse(BookingConflicts::is_booked($this->vehicle_id, $this->start, $this->end));
	}

	public function test_a_completed_rental_refunded_in_full_is_still_marked_refunded(): void
	{
		$order = $this->paid_order('300', Status::COMPLETED);

		$this->refund($order, '300');

		$this->assertSame(Status::REFUNDED, Status::get($this->booking_id));
	}

	public function test_a_partial_refund_leaves_the_rental_as_it_was(): void
	{
		$order = $this->paid_order('300', Status::CONFIRMED);

		$this->refund($order, '100');

		$this->assertSame(Status::CONFIRMED, Status::get($this->booking_id));
		$this->assertSame('partially_refunded', (string) get_post_meta($this->booking_id, '_mhmrentiva_payment_status', true));
	}

	public function test_a_cancelled_booking_refunded_in_full_stays_cancelled(): void
	{
		$order = $this->paid_order('300', Status::CANCELLED);

		$this->refund($order, '300');

		$this->assertSame(Status::CANCELLED, Status::get($this->booking_id));
	}

	/**
	 * A paid order bound to the booking on the order AND its line item, as
	 * production writes it, so both refund paths see it.
	 */
	private function paid_order(string $total, string $status): \WC_Order
	{
		$order = $this->create_paid_order_for_booking($this->booking_id, $total);
		foreach ($order->get_items() as $item) {
			$item->add_meta_data('_mhmrentiva_booking_id', $this->booking_id, true);
			$item->save();
		}
		// The fixture's own 'processing' step ran before the item knew the booking;
		// set the rental state the test is about directly.
		update_post_meta($this->booking_id, '_mhmrentiva_status', $status);
		update_post_meta($this->booking_id, '_mhmrentiva_payment_status', 'paid');

		return $order;
	}

	private function refund(\WC_Order $order, string $amount): void
	{
		$refund = wc_create_refund(
			array(
				'order_id'       => $order->get_id(),
				'amount'         => $amount,
				'reason'         => 'B1',
				'refund_payment' => false,
				'restock_items'  => false,
			)
		);
		$this->assertNotWPError($refund);
		clean_post_cache($this->booking_id);
		wp_cache_delete($this->booking_id, 'post_meta');
	}
}
