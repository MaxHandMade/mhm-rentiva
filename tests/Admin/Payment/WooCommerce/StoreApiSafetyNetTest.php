<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Payment\WooCommerce;

use Automattic\WooCommerce\StoreApi\Exceptions\RouteException;
use MHMRentiva\Admin\Payment\WooCommerce\WooCommerceBridge;
use MHMRentiva\Tests\Support\WooCommerceFixtures;
use WP_UnitTestCase;

/**
 * PR K, R5: a rental order that still reaches the Store API gets its booking.
 * The snapshot's payment type stands (r2 B1); a date conflict stops the request
 * before any payment, on either checkout (r2 M3, r3 N4), so no paid or on-hold
 * order is left without a booking.
 *
 * @covers \MHMRentiva\Admin\Payment\WooCommerce\WooCommerceBridge
 */
final class StoreApiSafetyNetTest extends WP_UnitTestCase
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
	 * An order as WooCommerce leaves it when the Store API hook fires: pending,
	 * its line carrying the snapshot and priced at what the snapshot charges.
	 *
	 * @param array<string, mixed> ...$snapshots
	 */
	private function pending_order(float $total, array ...$snapshots): \WC_Order
	{
		$order = wc_create_order();
		foreach ($snapshots as $snapshot) {
			$item = new \WC_Order_Item_Product();
			$item->set_product($this->product);
			$item->set_quantity(1);
			$item->set_subtotal($total);
			$item->set_total($total);
			$item->add_meta_data('_mhmrentiva_booking_data', $snapshot);
			$item->add_meta_data('_mhmrentiva_booking_pending', true);
			$order->add_item($item);
		}
		$order->calculate_totals();
		$order->set_status('pending');
		$order->save();

		return $order;
	}

	private function store_api(\WC_Order $order): void
	{
		do_action('woocommerce_store_api_checkout_order_processed', $order);
	}

	private function bookings_of_vehicle(): int
	{
		return count(
			get_posts(
				array(
					'post_type'   => 'mhmrentiva_booking',
					'post_status' => 'any',
					'numberposts' => -1,
					'fields'      => 'ids',
					'meta_key'    => '_mhmrentiva_vehicle_id',
					'meta_value'  => $this->vehicle_id,
				)
			)
		);
	}

	public function test_a_deposit_order_books_a_deposit(): void
	{
		$order = $this->pending_order(200.0, $this->snapshot());

		$this->store_api($order);

		$order      = wc_get_order($order->get_id());
		$booking_id = (int) $order->get_meta('_mhmrentiva_booking_id');

		$this->assertGreaterThan(0, $booking_id, 'The block path creates the booking.');
		$this->assertSame(200.0, (float) $order->get_total());
		$this->assertSame('deposit', $order->get_meta('_mhmrentiva_wc_payment_type'));
		$this->assertSame('deposit', get_post_meta($booking_id, '_mhmrentiva_payment_type', true));
		$this->assertSame(800.0, (float) get_post_meta($booking_id, '_mhmrentiva_remaining_amount', true));
		$this->assertSame(1000.0, (float) get_post_meta($booking_id, '_mhmrentiva_total_price', true));
	}

	public function test_a_full_order_books_a_full_payment(): void
	{
		$order = $this->pending_order(1000.0, $this->snapshot(array( 'payment_type' => 'full', 'deposit_amount' => 200.0, 'remaining_amount' => 0.0 )));

		$this->store_api($order);

		$order      = wc_get_order($order->get_id());
		$booking_id = (int) $order->get_meta('_mhmrentiva_booking_id');

		$this->assertSame(1000.0, (float) $order->get_total());
		$this->assertSame('full', $order->get_meta('_mhmrentiva_wc_payment_type'));
		$this->assertSame('full', get_post_meta($booking_id, '_mhmrentiva_payment_type', true));
		$this->assertSame(0.0, (float) get_post_meta($booking_id, '_mhmrentiva_remaining_amount', true));
	}

	public function test_the_order_notes_the_path_it_took(): void
	{
		$order = $this->pending_order(200.0, $this->snapshot());

		$this->store_api($order);

		$notes = wc_get_order_notes(array( 'order_id' => $order->get_id() ));
		$this->assertNotEmpty(array_filter($notes, static fn($n) => str_contains($n->content, 'block checkout')));
	}

	public function test_no_second_booking_from_the_thank_you_fallback_or_a_retry(): void
	{
		$order = $this->pending_order(200.0, $this->snapshot());

		$this->store_api($order);
		$order->update_status('processing');
		WooCommerceBridge::create_booking_from_order_fallback($order->get_id());
		$this->store_api(wc_get_order($order->get_id()));

		$this->assertSame(1, $this->bookings_of_vehicle());
	}

	public function test_a_shop_order_is_left_alone(): void
	{
		$order = wc_create_order();
		$item  = new \WC_Order_Item_Product();
		$item->set_product($this->product);
		$item->set_quantity(1);
		$order->add_item($item);
		$order->save();

		$this->store_api($order);

		$order = wc_get_order($order->get_id());
		$this->assertSame('', $order->get_meta('_mhmrentiva_wc_payment_type'));
		$this->assertSame(array(), wc_get_order_notes(array( 'order_id' => $order->get_id() )));
	}

	public function test_a_conflict_stops_the_store_api_request_before_payment(): void
	{
		$this->store_api($this->pending_order(200.0, $this->snapshot()));
		$this->assertSame(1, $this->bookings_of_vehicle());

		$second = $this->pending_order(200.0, $this->snapshot());

		try {
			$this->store_api($second);
			$this->fail('The request must stop.');
		} catch (RouteException $e) {
			$this->assertSame(400, $e->getCode());
			$this->assertStringContainsString('Fiat Egea', $e->getMessage());
		}

		$second = wc_get_order($second->get_id());
		$this->assertSame('pending', $second->get_status(), 'Not cancelled: nothing a gateway could revive.');
		$this->assertSame('', $second->get_meta('_mhmrentiva_booking_id'));
		$this->assertSame(1, $this->bookings_of_vehicle());
	}

	public function test_a_conflict_stops_the_classic_checkout_before_payment(): void
	{
		$this->store_api($this->pending_order(200.0, $this->snapshot()));

		$second = $this->pending_order(200.0, $this->snapshot());

		try {
			do_action('woocommerce_checkout_order_processed', $second->get_id(), array(), $second);
			$this->fail('WC_Checkout catches this and shows it as a notice.');
		} catch (\Exception $e) {
			$this->assertStringContainsString('Fiat Egea', $e->getMessage());
		}

		$second = wc_get_order($second->get_id());
		$this->assertSame('pending', $second->get_status());
		$this->assertSame('', $second->get_meta('_mhmrentiva_booking_id'));
	}

	public function test_a_later_line_conflict_leaves_no_half_booked_order(): void
	{
		$other = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_vehicle', 'post_status' => 'publish' ));
		$this->store_api($this->pending_order(200.0, $this->snapshot()));

		$created = 0;
		$count   = static function () use (&$created): void {
			++$created;
		};
		add_action('mhmrentiva_booking_created', $count);

		$order = $this->pending_order(200.0, $this->snapshot(array( 'vehicle_id' => $other )), $this->snapshot());

		try {
			$this->store_api($order);
			$this->fail('The request must stop.');
		} catch (RouteException $e) {
			$this->assertSame(400, $e->getCode());
		}
		remove_action('mhmrentiva_booking_created', $count);

		$order = wc_get_order($order->get_id());
		$this->assertSame('', $order->get_meta('_mhmrentiva_booking_id'), 'A retry must not skip the lines.');
		$this->assertSame(0, $created, 'Nobody was told about a booking that does not stand.');
		$this->assertSame(
			array(),
			get_posts(array( 'post_type' => 'mhmrentiva_booking', 'post_status' => 'any', 'fields' => 'ids', 'meta_key' => '_mhmrentiva_vehicle_id', 'meta_value' => $other ))
		);
		foreach ($order->get_items() as $item) {
			$this->assertTrue((bool) $item->get_meta('_mhmrentiva_booking_pending'));
			$this->assertSame('', $item->get_meta('_mhmrentiva_booking_id'));
		}
	}

	public function test_the_thank_you_fallback_still_cancels_rather_than_throws(): void
	{
		$this->store_api($this->pending_order(200.0, $this->snapshot()));

		$late = $this->pending_order(200.0, $this->snapshot());
		$late->update_status('processing');
		WooCommerceBridge::create_booking_from_order_fallback($late->get_id());

		$this->assertSame('cancelled', wc_get_order($late->get_id())->get_status());
	}

	public function test_cart_errors_report_a_conflict_without_removing_the_line(): void
	{
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

		$errors = new \WP_Error();
		WooCommerceBridge::store_api_cart_errors($errors, WC()->cart);
		$this->assertFalse($errors->has_errors(), 'Free dates: no error.');

		$this->store_api($this->pending_order(200.0, $this->snapshot()));

		WooCommerceBridge::store_api_cart_errors($errors, WC()->cart);
		$this->assertTrue($errors->has_errors());
		$this->assertStringContainsString('Fiat Egea', $errors->get_error_message());
		$this->assertArrayHasKey($key, WC()->cart->get_cart(), 'The customer still sees what was refused.');
	}
}
