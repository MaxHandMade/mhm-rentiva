<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Frontend\Account;

use MHMRentiva\Admin\Frontend\Account\AccountRenderer;
use MHMRentiva\Admin\Payment\Core\PaymentState;
use MHMRentiva\Tests\Support\WooCommerceFixtures;
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
	use WooCommerceFixtures;

	private int $customer_id;

	public function setUp(): void
	{
		parent::setUp();

		$this->require_woocommerce();
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

	/**
	 * An order with a product line, like a real checkout. Without a line,
	 * WooCommerce skips 'processing' as a paid state and never writes
	 * date_paid, so PaymentState would not count it (Fable 0.3 M1).
	 */
	private function unpaid_order( string $status ): \WC_Order
	{
		$order = wc_create_order( array( 'status' => 'pending' ) );
		$item  = new \WC_Order_Item_Product();
		$item->set_product( $this->ensure_booking_product( '100' ) );
		$item->set_quantity( 1 );
		$item->set_subtotal( 100.0 );
		$item->set_total( 100.0 );
		$order->add_item( $item );
		$order->calculate_totals();
		$order->save();
		$order->update_status( $status );

		return $order;
	}

	/**
	 * A deposit paid through checkout, proven through the WooCommerce channel:
	 * payment_status stays 'pending' so the offline channel cannot supply it.
	 */
	private function booking_paid_through_checkout( string $status ): int
	{
		$booking_id = $this->booking( $status, 'pending' );
		$this->create_paid_order_for_booking( $booking_id, '100' );
		$this->assertNotSame( array(), PaymentState::forBooking( $booking_id )->orders(), 'Fixture guard: the order must count as paid.' );

		return $booking_id;
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

	public function test_a_rental_in_progress_is_not_told_to_come_to_pickup(): void
	{
		$html = $this->detail( $this->booking( 'in_progress' ) );

		$this->assertStringNotContainsString( 'successfully completed', $html );
		$this->assertStringNotContainsString( 'rv-next-steps', $html, 'The vehicle is already with the customer.' );
	}

	public function test_a_booking_awaiting_payment_says_so(): void
	{
		$html = $this->detail( $this->booking( 'pending_payment' ) );

		$this->assertStringContainsString( 'Awaiting Payment', $html );
		$this->assertStringNotContainsString( 'confirm it shortly', $html, 'Nothing happens until the customer pays.' );
		$this->assertStringContainsString( 'Pending Payment', $html, 'The status row uses the readable label.' );
		$this->assertStringNotContainsString( 'Pending_payment', $html );
	}

	public function test_an_unpaid_deposit_is_not_labelled_paid(): void
	{
		$html = $this->detail( $this->booking( 'pending' ) );

		$this->assertStringNotContainsString( 'Deposit Paid', $html );
		$this->assertStringContainsString( 'Deposit Amount', $html );
	}

	public function test_a_deposit_paid_through_checkout_is_labelled_paid(): void
	{
		$booking_id = $this->booking_paid_through_checkout( 'confirmed' );

		$this->assertStringContainsString( 'Deposit Paid', $this->detail( $booking_id ) );
	}

	public function test_an_on_hold_deposit_order_does_not_offer_the_balance(): void
	{
		$booking_id = $this->booking( 'pending' );
		update_post_meta( $booking_id, '_mhmrentiva_woocommerce_order_id', $this->unpaid_order( 'on-hold' )->get_id() );

		$this->assertStringNotContainsString(
			'rv-pay-remaining-btn',
			$this->detail( $booking_id ),
			'Paying the balance before the deposit order is paid opens a second order.'
		);
	}

	public function test_the_list_shows_the_same_number_as_the_detail_and_finds_it(): void
	{
		$booking_id = $this->booking( 'pending' );
		$order_id   = $this->unpaid_order( 'on-hold' )->get_id();
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
		$booking_id = $this->booking_paid_through_checkout( 'confirmed' );
		$order_id   = (int) get_post_meta( $booking_id, '_mhmrentiva_woocommerce_order_id', true );

		$dashboard = AccountRenderer::render_dashboard();

		$this->assertStringContainsString( 'Booking #' . $order_id, $dashboard );
		$this->assertStringNotContainsString( 'Booking #' . $booking_id . '<', $dashboard );
	}
}
