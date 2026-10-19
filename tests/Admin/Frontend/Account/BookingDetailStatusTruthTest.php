<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Frontend\Account;

use MHMRentiva\Admin\Frontend\Account\AccountRenderer;
use WP_UnitTestCase;

/**
 * The customer's booking detail told the customer things that were not true
 * (measured on the dev site, plan item 0.3):
 *
 * - The header said "Your reservation has been successfully completed" for a
 *   pending, unpaid booking and still said it after the customer cancelled.
 * - "Deposit Paid" showed the deposit the booking REQUIRES, money or not.
 * - "Next Steps" (come to pickup, bring the card) showed on cancelled bookings.
 * - A checkout booking whose deposit order was still on hold offered "Pay
 *   Remaining Amount", which opens a second order next to the unpaid one.
 * - The list showed the raw post ID while the detail showed the order number,
 *   so the same booking had two numbers and searching the detail's number
 *   found nothing.
 *
 * @covers \MHMRentiva\Admin\Frontend\Account\AccountRenderer::render_booking_detail
 * @covers \MHMRentiva\Admin\Frontend\Account\AccountRenderer::render_bookings
 */
final class BookingDetailStatusTruthTest extends WP_UnitTestCase
{
	private int $customer_id;

	public function setUp(): void
	{
		parent::setUp();

		$this->customer_id = self::factory()->user->create( array( 'role' => 'customer' ) );
		wp_set_current_user( $this->customer_id );
	}

	public function tearDown(): void
	{
		set_query_var( 'mhmrentiva_search_booking', '' );
		parent::tearDown();
	}

	private function booking( string $status, string $payment_status = 'pending' ): int
	{
		$booking_id = self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_booking',
				'post_status' => 'publish',
			)
		);

		update_post_meta( $booking_id, '_mhmrentiva_customer_user_id', $this->customer_id );
		update_post_meta( $booking_id, '_mhmrentiva_status', $status );
		update_post_meta( $booking_id, '_mhmrentiva_payment_type', 'deposit' );
		update_post_meta( $booking_id, '_mhmrentiva_payment_status', $payment_status );
		update_post_meta( $booking_id, '_mhmrentiva_deposit_amount', '100.00' );
		update_post_meta( $booking_id, '_mhmrentiva_total_price', '300.00' );
		update_post_meta( $booking_id, '_mhmrentiva_remaining_amount', '200.00' );
		update_post_meta( $booking_id, '_mhmrentiva_pickup_date', '2030-05-10' );

		return $booking_id;
	}

	private function order( string $status ): \WC_Order
	{
		$order = wc_create_order( array( 'status' => 'pending' ) );
		$order->set_total( 100 );
		$order->save();
		if ( 'pending' !== $status ) {
			$order->update_status( $status );
		}

		return $order;
	}

	private function detail( int $booking_id ): string
	{
		return AccountRenderer::render_booking_detail( $booking_id, true );
	}

	public function test_a_pending_booking_is_not_called_completed(): void
	{
		$html = $this->detail( $this->booking( 'pending' ) );

		$this->assertStringNotContainsString( 'successfully completed', $html );
		$this->assertStringContainsString( 'Booking Received', $html );
		$this->assertStringNotContainsString( 'rv-next-steps', $html, 'A booking not yet confirmed has no pickup instructions.' );
	}

	public function test_a_cancelled_booking_says_so_and_drops_pickup_instructions(): void
	{
		$html = $this->detail( $this->booking( 'cancelled' ) );

		$this->assertStringNotContainsString( 'successfully completed', $html );
		$this->assertStringContainsString( 'Booking Cancelled', $html );
		$this->assertStringNotContainsString( 'rv-next-steps', $html );
	}

	public function test_a_confirmed_booking_keeps_its_confirmation_and_next_steps(): void
	{
		$html = $this->detail( $this->booking( 'confirmed' ) );

		$this->assertStringContainsString( 'Booking Confirmation', $html );
		$this->assertStringContainsString( 'rv-next-steps', $html );
		$this->assertStringNotContainsString( 'credit card used for the reservation', $html, 'Not every booking was paid by card.' );
	}

	public function test_an_unpaid_deposit_is_not_labelled_paid(): void
	{
		$html = $this->detail( $this->booking( 'pending' ) );

		$this->assertStringNotContainsString( 'Deposit Paid', $html );
		$this->assertStringContainsString( 'Deposit Amount', $html );
	}

	public function test_a_deposit_paid_through_checkout_is_labelled_paid(): void
	{
		$booking_id = $this->booking( 'confirmed', 'paid' );
		update_post_meta( $booking_id, '_mhmrentiva_woocommerce_order_id', $this->order( 'processing' )->get_id() );

		$this->assertStringContainsString( 'Deposit Paid', $this->detail( $booking_id ) );
	}

	public function test_an_on_hold_deposit_order_does_not_offer_the_balance(): void
	{
		$booking_id = $this->booking( 'pending' );
		update_post_meta( $booking_id, '_mhmrentiva_woocommerce_order_id', $this->order( 'on-hold' )->get_id() );

		$this->assertStringNotContainsString(
			'rv-pay-remaining-btn',
			$this->detail( $booking_id ),
			'Paying the balance before the deposit order is paid opens a second order.'
		);
	}

	public function test_the_list_shows_the_same_number_as_the_detail_and_finds_it(): void
	{
		$booking_id = $this->booking( 'pending' );
		$order_id   = $this->order( 'on-hold' )->get_id();
		update_post_meta( $booking_id, '_mhmrentiva_woocommerce_order_id', $order_id );

		$this->assertStringContainsString( '#' . $order_id, $this->detail( $booking_id ), 'Fixture guard: the detail shows the order number.' );

		$list = AccountRenderer::render_bookings();
		$this->assertStringContainsString( '#' . $order_id, $list );
		$this->assertStringNotContainsString( '#' . $booking_id . '<', $list );

		set_query_var( 'mhmrentiva_search_booking', (string) $order_id );
		$this->assertStringContainsString( '#' . $order_id, AccountRenderer::render_bookings(), 'Searching the number the customer sees must find the booking.' );
	}

	public function test_the_dashboard_shows_the_same_number_as_the_detail(): void
	{
		$booking_id = $this->booking( 'confirmed', 'paid' );
		$order_id   = $this->order( 'processing' )->get_id();
		update_post_meta( $booking_id, '_mhmrentiva_woocommerce_order_id', $order_id );

		$dashboard = AccountRenderer::render_dashboard();

		$this->assertStringContainsString( 'Booking #' . $order_id, $dashboard );
		$this->assertStringNotContainsString( 'Booking #' . $booking_id . '<', $dashboard );
	}
}
