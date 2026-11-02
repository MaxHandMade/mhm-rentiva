<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Payment\WooCommerce;

use Automattic\WooCommerce\StoreApi\Exceptions\RouteException;
use MHMRentiva\Admin\Booking\Core\Status;
use MHMRentiva\Admin\Payment\WooCommerce\WooCommerceBridge;
use MHMRentiva\Tests\Support\WooCommerceFixtures;
use WP_UnitTestCase;

/**
 * PR K final fixes, Codex 1 and 2: a booking id on the order is not enough.
 *
 * 1. A failed payment cancels the booking; another customer books the dates;
 *    the first order is paid again. Every path that can take that money --
 *    the Store API retry, the classic order-pay form, a classic checkout that
 *    resumes the order, a status change that confirms -- asks under the
 *    vehicle's lock whether the booking still holds its dates.
 * 2. A retry of the same order does not collide with its own booking.
 *
 * @covers \MHMRentiva\Admin\Payment\WooCommerce\WooCommerceBridge
 */
final class RepaymentClaimTest extends WP_UnitTestCase
{
	use WooCommerceFixtures;

	private \WC_Product $product;

	private int $vehicle_id;

	public function setUp(): void
	{
		parent::setUp();

		$this->require_woocommerce();
		$this->product    = $this->ensure_booking_product('100');
		$this->vehicle_id = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_vehicle', 'post_status' => 'publish', 'post_title' => 'Fiat Egea' ));
		update_post_meta($this->vehicle_id, '_mhmrentiva_price_per_day', 100);

		wc_load_cart();
		WC()->cart->empty_cart();
		wc_clear_notices();

		add_action('woocommerce_store_api_checkout_order_processed', array( WooCommerceBridge::class, 'store_api_order_processed' ), 10, 1);
		add_action('woocommerce_checkout_order_processed', array( WooCommerceBridge::class, 'create_booking_from_order' ), 10, 3);
	}

	public function tearDown(): void
	{
		remove_action('woocommerce_store_api_checkout_order_processed', array( WooCommerceBridge::class, 'store_api_order_processed' ), 10);
		remove_action('woocommerce_checkout_order_processed', array( WooCommerceBridge::class, 'create_booking_from_order' ), 10);
		if (function_exists('WC') && WC()->cart) {
			WC()->cart->empty_cart();
		}
		if (function_exists('WC') && WC()->session) {
			WC()->session->set('order_awaiting_payment', null);
			WC()->session->set('store_api_draft_order', null);
		}
		wc_clear_notices();
		parent::tearDown();
	}

	/**
	 * @param array<string, mixed> $extra
	 * @return array<string, mixed>
	 */
	private function snapshot(array $extra = array()): array
	{
		return array_merge(
			array(
				'vehicle_id'          => $this->vehicle_id,
				'pickup_date'         => '2031-03-10',
				'pickup_time'         => '10:00',
				'dropoff_date'        => '2031-03-13',
				'dropoff_time'        => '10:00',
				'guests'              => 2,
				'customer_user_id'    => 0,
				'customer_name'       => 'Jane Customer',
				'customer_first_name' => 'Jane',
				'customer_last_name'  => 'Customer',
				'customer_email'      => 'jane@example.com',
				'customer_phone'      => '5551234567',
				'payment_type'        => 'deposit',
				'deposit_amount'      => 200.0,
				'remaining_amount'    => 800.0,
				'payment_display'     => 'Deposit',
				'total_price'         => 1000.0,
				'rental_days'         => 3,
				'selected_addons'     => array(),
			),
			$extra
		);
	}

	/**
	 * @param array<string, mixed> $snapshot
	 */
	private function pending_line(\WC_Order $order, array $snapshot): void
	{
		$item = new \WC_Order_Item_Product();
		$item->set_product($this->product);
		$item->set_quantity(1);
		$item->set_subtotal(200.0);
		$item->set_total(200.0);
		$item->add_meta_data('_mhmrentiva_booking_data', $snapshot);
		$item->add_meta_data('_mhmrentiva_booking_pending', true);
		$order->add_item($item);
	}

	private function pending_order(): \WC_Order
	{
		$order = wc_create_order();
		$this->pending_line($order, $this->snapshot());
		$order->calculate_totals();
		$order->set_status('pending');
		$order->save();

		return $order;
	}

	private function store_api(\WC_Order $order): void
	{
		do_action('woocommerce_store_api_checkout_order_processed', $order);
	}

	/**
	 * An order whose first payment failed: booked, then the failure cancelled
	 * the booking (handle_order_status_change(), `failed`).
	 *
	 * @return array{0: \WC_Order, 1: int}
	 */
	private function failed_order(): array
	{
		$order = $this->pending_order();
		$this->store_api($order);
		$order      = wc_get_order($order->get_id());
		$booking_id = (int) $order->get_meta('_mhmrentiva_booking_id');
		$this->assertGreaterThan(0, $booking_id);

		$order->update_status('failed');
		WooCommerceBridge::handle_order_status_change($order->get_id(), 'pending', 'failed', $order);
		$this->assertSame('cancelled', get_post_meta($booking_id, '_mhmrentiva_status', true));

		return array( wc_get_order($order->get_id()), $booking_id );
	}

	/**
	 * Another customer takes the same dates.
	 */
	private function someone_else_books(): int
	{
		$other = $this->pending_order();
		$this->store_api($other);
		$booking_id = (int) wc_get_order($other->get_id())->get_meta('_mhmrentiva_booking_id');
		$this->assertGreaterThan(0, $booking_id, 'A cancelled booking holds nothing.');

		return $booking_id;
	}

	private function bookings_of_vehicle(): int
	{
		$ids = get_posts(
			array(
				'post_type'   => 'mhmrentiva_booking',
				'post_status' => 'any',
				'numberposts' => -1,
				'fields'      => 'ids',
				'meta_key'    => '_mhmrentiva_vehicle_id',
				'meta_value'  => $this->vehicle_id,
			)
		);

		return count($ids);
	}

	/**
	 * @return list<string>
	 */
	private function notes(\WC_Order $order): array
	{
		return array_map(static fn($n) => (string) $n->content, wc_get_order_notes(array( 'order_id' => $order->get_id() )));
	}

	// ------------------------------------------------- Codex 1: double booking

	public function test_failed_then_booked_by_another_then_paid_again_on_the_store_api_is_stopped(): void
	{
		list($order, $booking_id) = $this->failed_order();
		$this->someone_else_books();

		try {
			$this->store_api($order);
			$this->fail('POST /checkout/{id} must stop before the gateway.');
		} catch (RouteException $e) {
			$this->assertSame(400, $e->getCode());
			$this->assertStringContainsString('Fiat Egea', $e->getMessage());
		}

		$this->assertSame('cancelled', get_post_meta($booking_id, '_mhmrentiva_status', true), 'Not revived over the other booking.');
		$this->assertNotEmpty(array_filter($this->notes($order), static fn($n) => str_contains($n, 'Booking refused')), 'The order says why it was not paid.');
	}

	public function test_failed_then_paid_again_with_the_dates_still_free_takes_them_back(): void
	{
		list($order, $booking_id) = $this->failed_order();

		$this->store_api($order);

		$this->assertSame('pending', get_post_meta($booking_id, '_mhmrentiva_status', true));
		$this->assertGreaterThan(current_time('mysql', true), (string) get_post_meta($booking_id, '_mhmrentiva_payment_deadline', true), 'A fresh deadline: the dates are held again.');
		$this->assertSame(1, $this->bookings_of_vehicle(), 'No second booking.');

		WooCommerceBridge::handle_order_status_change($order->get_id(), 'failed', 'processing', $order);
		$this->assertSame('confirmed', get_post_meta($booking_id, '_mhmrentiva_status', true));
	}

	public function test_the_classic_order_pay_form_is_stopped_before_the_gateway(): void
	{
		list($order, $booking_id) = $this->failed_order();
		$this->someone_else_books();

		WooCommerceBridge::claim_before_pay($order);

		$this->assertSame(1, wc_notice_count('error'), 'WC_Form_Handler::pay_action() pays only while there is no error notice.');
		$this->assertSame('cancelled', get_post_meta($booking_id, '_mhmrentiva_status', true));
	}

	public function test_the_classic_order_pay_form_passes_a_booking_that_holds_its_dates(): void
	{
		$order = $this->pending_order();
		$this->store_api($order);

		WooCommerceBridge::claim_before_pay(wc_get_order($order->get_id()));

		$this->assertSame(0, wc_notice_count('error'));
	}

	public function test_a_classic_checkout_resuming_the_failed_order_is_stopped(): void
	{
		list($order, $booking_id) = $this->failed_order();
		$this->someone_else_books();

		// WC_Checkout::create_order() resumes a pending|failed order: every line
		// removed and added again from the cart, the order meta left as it was.
		$order->remove_order_items();
		$this->pending_line($order, $this->snapshot());
		$order->save();

		try {
			do_action('woocommerce_checkout_order_processed', $order->get_id(), array(), $order);
			$this->fail('The request must stop: WC_Checkout turns this into a notice, before the gateway.');
		} catch (\Exception $e) {
			$this->assertStringContainsString('Fiat Egea', $e->getMessage());
		}

		$this->assertSame('cancelled', get_post_meta($booking_id, '_mhmrentiva_status', true));
		$this->assertSame(2, $this->bookings_of_vehicle(), 'No third booking.');
	}

	public function test_a_payment_landing_on_a_booking_that_lost_its_dates_confirms_nothing(): void
	{
		list($order, $booking_id) = $this->failed_order();
		$this->someone_else_books();

		// A gateway's late callback, or an operator marking the order paid.
		$order->update_status('processing');
		WooCommerceBridge::handle_order_status_change($order->get_id(), 'failed', 'processing', $order);

		$this->assertSame('cancelled', get_post_meta($booking_id, '_mhmrentiva_status', true), 'Not confirmed over the other booking.');
		$this->assertNotEmpty(array_filter($this->notes(wc_get_order($order->get_id())), static fn($n) => str_contains($n, 'was not confirmed')), 'The operator is told to return the money.');
	}

	public function test_a_pending_booking_past_its_deadline_is_asked_too(): void
	{
		$order = $this->pending_order();
		$this->store_api($order);
		$order      = wc_get_order($order->get_id());
		$booking_id = (int) $order->get_meta('_mhmrentiva_booking_id');

		// Past its payment deadline it holds nothing (BookingConflicts PAYMENT_SQL).
		update_post_meta($booking_id, '_mhmrentiva_payment_deadline', gmdate('Y-m-d H:i:s', time() - HOUR_IN_SECONDS));
		$this->someone_else_books();

		WooCommerceBridge::claim_before_pay($order);

		$this->assertSame(1, wc_notice_count('error'));
	}

	public function test_a_remaining_payment_never_revives_a_cancelled_booking(): void
	{
		list($order, $booking_id) = $this->failed_order();

		$remaining = wc_create_order();
		$item      = new \WC_Order_Item_Product();
		$item->set_product($this->product);
		$item->set_quantity(1);
		$item->set_total(800.0);
		$item->add_meta_data('_mhmrentiva_booking_id', $booking_id, true);
		$remaining->add_item($item);
		$remaining->update_meta_data('_mhmrentiva_booking_id', $booking_id);
		$remaining->update_meta_data('_mhmrentiva_is_remaining_payment', '1');
		$remaining->calculate_totals();
		$remaining->save();

		WooCommerceBridge::claim_before_pay($remaining);

		$this->assertSame(1, wc_notice_count('error'), 'Free dates or not: the deposit side of that money is not known.');
		$this->assertSame('cancelled', get_post_meta($booking_id, '_mhmrentiva_status', true));
	}

	// ------------------------------------------------ Codex 2: self conflict

	/**
	 * The cart line of a checkout retry: the gateway threw after the booking
	 * was made, the order stays pending and the line still says pending.
	 *
	 * @return array{0: \WC_Order, 1: int, 2: string}
	 */
	private function retried_checkout(string $session_key): array
	{
		$order = $this->pending_order();
		$this->store_api($order);
		$booking_id = (int) wc_get_order($order->get_id())->get_meta('_mhmrentiva_booking_id');

		$key = WC()->cart->add_to_cart(
			$this->product->get_id(),
			1,
			0,
			array(),
			array(
				'mhmrentiva_booking_data'    => $this->snapshot(),
				'mhmrentiva_booking_price'   => 200.0,
				'mhmrentiva_booking_pending' => true,
			)
		);
		$this->assertIsString($key);
		WC()->session->set($session_key, $order->get_id());

		return array( wc_get_order($order->get_id()), $booking_id, $key );
	}

	public function test_a_store_api_retry_does_not_collide_with_its_own_booking(): void
	{
		$this->retried_checkout('store_api_draft_order');

		$errors = new \WP_Error();
		WooCommerceBridge::store_api_cart_errors($errors, WC()->cart);

		$this->assertFalse($errors->has_errors(), 'The customer is not refused their own booking.');
	}

	public function test_a_classic_retry_does_not_collide_with_its_own_booking(): void
	{
		list(, , $key) = $this->retried_checkout('order_awaiting_payment');

		WooCommerceBridge::validate_checkout_availability();
		$this->assertSame(0, wc_notice_count('error'));

		WooCommerceBridge::validate_cart_availability();
		$this->assertSame(0, wc_notice_count('error'));
		$this->assertArrayHasKey($key, WC()->cart->get_cart(), 'The line is not removed from the cart.');
	}

	public function test_a_retry_still_collides_with_someone_elses_booking(): void
	{
		// The other order's booking first, then a retry of an order that made
		// none: only the retried order's own booking is left out.
		$this->store_api($this->pending_order());

		$mine = wc_create_order();
		$mine->set_status('pending');
		$mine->save();
		WC()->session->set('store_api_draft_order', $mine->get_id());
		WC()->cart->add_to_cart(
			$this->product->get_id(),
			1,
			0,
			array(),
			array(
				'mhmrentiva_booking_data'    => $this->snapshot(),
				'mhmrentiva_booking_price'   => 200.0,
				'mhmrentiva_booking_pending' => true,
			)
		);

		$errors = new \WP_Error();
		WooCommerceBridge::store_api_cart_errors($errors, WC()->cart);

		$this->assertTrue($errors->has_errors());
	}

	public function test_a_resumed_classic_order_relinks_its_own_booking(): void
	{
		$order = $this->pending_order();
		$this->store_api($order);
		$order      = wc_get_order($order->get_id());
		$booking_id = (int) $order->get_meta('_mhmrentiva_booking_id');

		$order->remove_order_items();
		$this->pending_line($order, $this->snapshot());
		$order->save();

		do_action('woocommerce_checkout_order_processed', $order->get_id(), array(), $order);

		$order = wc_get_order($order->get_id());
		$item  = current($order->get_items());
		$this->assertSame($booking_id, (int) $item->get_meta('_mhmrentiva_booking_id'), 'The fresh line carries the booking, so the status handler finds it.');
		$this->assertFalse((bool) $item->get_meta('_mhmrentiva_booking_pending'));
		$this->assertSame(1, $this->bookings_of_vehicle());
		$this->assertSame(Status::PENDING, get_post_meta($booking_id, '_mhmrentiva_status', true));
	}
}
