<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Payment\WooCommerce;

use Automattic\WooCommerce\StoreApi\Exceptions\RouteException;
use MHMRentiva\Admin\Payment\WooCommerce\WooCommerceBridge;
use MHMRentiva\Tests\Support\WooCommerceFixtures;
use WP_UnitTestCase;

/**
 * PR K, owner decision 2026-11-02: cash on delivery is never offered for a
 * rental, for a full payment or a deposit. Paying in full at the door makes no
 * sense, and a deposit paid at the door is no deposit; what is left is paid at
 * pickup anyway. The store's COD setting stays as it is: a shop order keeps it.
 *
 * Like the other checkout rules (RentalScopeTest), it follows any Rentiva line:
 * a mixed cart is one order, paid with one gateway, so its rental's deposit
 * would be paid at the door too.
 *
 * @covers \MHMRentiva\Admin\Payment\WooCommerce\WooCommerceBridge
 */
final class CashOnDeliveryTest extends WP_UnitTestCase
{
	use WooCommerceFixtures;

	private \WC_Product $product;

	/** @var list<array{0: string, 1: array{0: class-string, 1: string}, 2: int}> */
	private array $hooks_added = array();

	public function setUp(): void
	{
		parent::setUp();

		$this->require_woocommerce();
		$this->product = $this->ensure_booking_product('100');

		wc_load_cart();
		WC()->cart->empty_cart();

		$this->hook('woocommerce_available_payment_gateways', array( WooCommerceBridge::class, 'without_cash_on_delivery' ), 10, 1);
		$this->hook('woocommerce_store_api_checkout_order_processed', array( WooCommerceBridge::class, 'store_api_order_processed' ), 10, 1);
	}

	public function tearDown(): void
	{
		foreach ($this->hooks_added as $hook) {
			remove_filter($hook[0], $hook[1], $hook[2]);
		}
		if (function_exists('WC') && WC()->cart) {
			WC()->cart->empty_cart();
		}
		remove_all_filters('woocommerce_is_checkout');
		unset($GLOBALS['wp']->query_vars['order-pay'], $_GET['key']);
		parent::tearDown();
	}

	/**
	 * The plugin's own hooks are not registered under PHPUnit.
	 *
	 * @param array{0: class-string, 1: string} $callback
	 */
	private function hook(string $name, array $callback, int $priority, int $args): void
	{
		if (false === has_filter($name, $callback)) {
			add_filter($name, $callback, $priority, $args);
			$this->hooks_added[] = array( $name, $callback, $priority );
		}
	}

	/**
	 * Every gateway WooCommerce knows, as the filter receives them.
	 *
	 * @return array<string, \WC_Payment_Gateway>
	 */
	private function gateways(): array
	{
		$gateways = WC()->payment_gateways()->payment_gateways();
		$this->assertArrayHasKey('cod', $gateways, 'Sanity: WooCommerce registers COD.');
		$this->assertArrayHasKey('bacs', $gateways, 'Sanity: and bank transfer.');

		return $gateways;
	}

	/**
	 * @return list<string>
	 */
	private function offered(): array
	{
		return array_keys((array) WooCommerceBridge::without_cash_on_delivery($this->gateways()));
	}

	/**
	 * @param array<string, mixed> $extra
	 * @return array<string, mixed>
	 */
	private function rental_data(array $extra = array()): array
	{
		return array_merge(
			array(
				'vehicle_id'       => self::factory()->post->create(array( 'post_type' => 'mhmrentiva_vehicle', 'post_status' => 'publish' )),
				'pickup_date'      => '2031-04-10',
				'pickup_time'      => '10:00',
				'dropoff_date'     => '2031-04-13',
				'dropoff_time'     => '10:00',
				'payment_type'     => 'deposit',
				'total_price'      => 1000.0,
				'deposit_amount'   => 200.0,
				'remaining_amount' => 800.0,
				'rental_days'      => 3,
			),
			$extra
		);
	}

	/**
	 * @param array<string, mixed> $extra
	 */
	private function add_rental(array $extra = array()): void
	{
		$key = WC()->cart->add_to_cart(
			$this->product->get_id(),
			1,
			0,
			array(),
			array(
				'mhmrentiva_booking_data'    => $this->rental_data($extra),
				'mhmrentiva_booking_price'   => 200.0,
				'mhmrentiva_booking_pending' => true,
			)
		);
		$this->assertIsString($key);
	}

	private function add_product(): void
	{
		$product = new \WC_Product_Simple();
		$product->set_name('Sunglasses');
		$product->set_regular_price('25');
		$product->save();

		$this->assertIsString(WC()->cart->add_to_cart($product->get_id(), 1));
	}

	/**
	 * @param array<string, mixed>      $meta
	 * @param array<string, mixed>|null $line
	 */
	private function order_with(array $meta, ?array $line, string $method = ''): \WC_Order
	{
		$order = wc_create_order();
		$item  = new \WC_Order_Item_Product();
		$item->set_product($this->product);
		$item->set_quantity(1);
		$item->set_total(200.0);
		if (null !== $line) {
			$item->add_meta_data('_mhmrentiva_booking_data', $line);
			$item->add_meta_data('_mhmrentiva_booking_pending', true);
		}
		$order->add_item($item);
		foreach ($meta as $key => $value) {
			$order->update_meta_data($key, $value);
		}
		if ('' !== $method) {
			$order->set_payment_method($method);
		}
		$order->calculate_totals();
		$order->set_status('pending');
		$order->save();

		return $order;
	}

	/**
	 * The order-pay page of this order, as WooCommerce names it: id and key.
	 */
	private function pay_page(\WC_Order $order, string $key = ''): void
	{
		add_filter('woocommerce_is_checkout', '__return_true');
		$GLOBALS['wp']->query_vars['order-pay'] = (string) $order->get_id();
		$_GET['key']                            = '' !== $key ? $key : $order->get_order_key();
	}

	// ------------------------------------------------------------- the cart

	public function test_a_rental_cart_offers_no_cash_on_delivery_and_keeps_the_rest(): void
	{
		$all = array_keys($this->gateways());
		$this->add_rental();

		$offered = $this->offered();

		$this->assertNotContains('cod', $offered);
		$this->assertSame(array_values(array_diff($all, array( 'cod' ))), $offered, 'Every other gateway stays, in its order.');
	}

	public function test_a_full_payment_rental_offers_no_cash_on_delivery_either(): void
	{
		$this->add_rental(array( 'payment_type' => 'full', 'remaining_amount' => 0.0 ));

		$this->assertNotContains('cod', $this->offered());
	}

	public function test_a_shop_cart_keeps_cash_on_delivery(): void
	{
		$this->add_product();

		$this->assertSame(array_keys($this->gateways()), $this->offered());
	}

	public function test_an_empty_cart_keeps_cash_on_delivery(): void
	{
		$this->assertContains('cod', $this->offered());
	}

	public function test_a_mixed_cart_offers_no_cash_on_delivery(): void
	{
		// One order, one gateway: the rental's deposit would be paid at the door.
		$this->add_rental();
		$this->add_product();

		$this->assertNotContains('cod', $this->offered());
	}

	public function test_a_transfer_cart_offers_no_cash_on_delivery(): void
	{
		$this->add_rental(array( 'booking_type' => 'transfer', 'transfer_origin_id' => 7 ));

		$this->assertNotContains('cod', $this->offered());
	}

	public function test_without_cod_in_the_list_nothing_changes(): void
	{
		$this->add_rental();
		$bacs = array( 'bacs' => $this->gateways()['bacs'] );

		$this->assertSame($bacs, WooCommerceBridge::without_cash_on_delivery($bacs));
		$this->assertSame('not a list', WooCommerceBridge::without_cash_on_delivery('not a list'));
	}

	/**
	 * WooCommerce's own list, through the hook: what the classic checkout, the
	 * order-pay form, WC_Checkout::validate_checkout() and the Store API all ask.
	 */
	public function test_woocommerces_own_list_follows_the_cart(): void
	{
		update_option('woocommerce_cod_settings', array( 'enabled' => 'yes', 'title' => 'Cash on delivery', 'enable_for_virtual' => 'yes' ));
		update_option('woocommerce_bacs_settings', array( 'enabled' => 'yes', 'title' => 'Bank transfer' ));
		WC()->payment_gateways()->init();

		$this->add_product();
		$shop = array_keys(WC()->payment_gateways()->get_available_payment_gateways());
		$this->assertContains('cod', $shop, 'The store offers COD for its own products.');

		WC()->cart->empty_cart();
		$this->add_rental();
		$rental = array_keys(WC()->payment_gateways()->get_available_payment_gateways());
		$this->assertNotContains('cod', $rental);
		$this->assertContains('bacs', $rental);

		$this->assertSame('yes', get_option('woocommerce_cod_settings')['enabled'], 'The store setting is not touched.');
	}

	// -------------------------------------------------------------- order-pay

	public function test_order_pay_of_a_rental_order_offers_no_cash_on_delivery(): void
	{
		$this->add_product(); // The cart must not answer for the order.
		$this->pay_page($this->order_with(array(), $this->rental_data()));

		$this->assertNotContains('cod', $this->offered());
	}

	public function test_order_pay_of_a_shop_order_keeps_cash_on_delivery_whatever_the_cart(): void
	{
		$this->add_rental();
		$this->pay_page($this->order_with(array(), null));

		$this->assertContains('cod', $this->offered());
	}

	public function test_order_pay_of_a_remaining_payment_offers_no_cash_on_delivery(): void
	{
		// Its line carries no snapshot, only the order's booking id.
		$booking = self::factory()->post->create(array( 'post_type' => 'mhmrentiva_booking', 'post_status' => 'publish' ));
		$this->pay_page($this->order_with(array( '_mhmrentiva_is_remaining_payment' => '1', '_mhmrentiva_booking_id' => $booking ), null));

		$this->assertNotContains('cod', $this->offered());
	}

	public function test_order_pay_with_a_wrong_key_leaves_the_list_to_woocommerce(): void
	{
		// WooCommerce refuses the page itself; a guessed id must not tell
		// whether that order is a rental (CheckoutJourney::requested_order()).
		$this->add_rental();
		$this->pay_page($this->order_with(array(), $this->rental_data()), 'wc_order_wrong');

		$this->assertContains('cod', $this->offered());
	}

	// --------------------------------------------------------------- Store API

	/**
	 * POST /checkout/{id} (Store API order-pay) asks the gateway list with the
	 * session's cart, not the order: the safety net refuses there, before any
	 * booking or payment.
	 */
	public function test_a_rental_order_on_the_store_api_with_cash_on_delivery_is_refused(): void
	{
		$order = $this->order_with(array(), $this->rental_data(), 'cod');

		try {
			do_action('woocommerce_store_api_checkout_order_processed', $order);
			$this->fail('The request must stop.');
		} catch (RouteException $e) {
			$this->assertSame(400, $e->getCode());
			$this->assertSame('mhmrentiva_booking_refused', $e->getErrorCode());
		}

		$order = wc_get_order($order->get_id());
		$this->assertSame('pending', $order->get_status());
		$this->assertSame('', $order->get_meta('_mhmrentiva_booking_id'), 'No booking was made.');
		$this->assertSame('', $order->get_payment_method(), 'Fable delta8 3: the refused order keeps no COD method.');
		$this->assertSame('', $order->get_payment_method_title());
	}

	/**
	 * The order note names the payment method as the reason, not the
	 * generic booking refusal (PR K COD commit, concern 4).
	 */
	public function test_the_cash_on_delivery_refusal_note_names_the_payment_method(): void
	{
		$order = $this->order_with(array(), $this->rental_data(), 'cod');

		try {
			do_action('woocommerce_store_api_checkout_order_processed', $order);
			$this->fail('The request must stop.');
		} catch (RouteException $e) {
			$this->assertSame('mhmrentiva_booking_refused', $e->getErrorCode());
		}

		$notes = array_map(static fn($n) => (string) $n->content, wc_get_order_notes(array( 'order_id' => $order->get_id() )));
		$this->assertContains('Payment method refused: cash on delivery is not available for a booking. No booking was made and no payment was taken.', $notes);
		$this->assertSame(array(), array_filter($notes, static fn($n) => str_contains($n, 'Booking refused')), 'Not the generic booking refusal.');
	}

	public function test_a_booked_rental_order_on_the_store_api_with_cash_on_delivery_is_refused(): void
	{
		$booking = self::factory()->post->create(array( 'post_type' => 'mhmrentiva_booking', 'post_status' => 'publish' ));
		$order   = $this->order_with(array( '_mhmrentiva_is_remaining_payment' => '1', '_mhmrentiva_booking_id' => $booking ), null, 'cod');

		$this->expectException(RouteException::class);
		do_action('woocommerce_store_api_checkout_order_processed', $order);
	}

	public function test_a_shop_order_on_the_store_api_keeps_cash_on_delivery(): void
	{
		$order = $this->order_with(array(), null, 'cod');

		do_action('woocommerce_store_api_checkout_order_processed', $order);

		$this->assertSame(array(), wc_get_order_notes(array( 'order_id' => $order->get_id() )), 'Nothing refused, nothing noted.');
	}

	/**
	 * The Store API checkout route itself: in REST the gateway list is left
	 * alone, and the order hook refuses the rental order paid with COD before
	 * any booking or payment (Codex PR K delta7).
	 *
	 * Its own process: the route defines WOOCOMMERCE_CHECKOUT (StoreApi\Legacy).
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_the_store_api_checkout_route_refuses_cash_on_delivery_for_a_rental_cart(): void
	{
		update_option('woocommerce_cod_settings', array( 'enabled' => 'yes', 'title' => 'Cash on delivery', 'enable_for_virtual' => 'yes' ));
		WC()->payment_gateways()->init();
		$this->add_rental();

		remove_filter('woocommerce_available_payment_gateways', array( WooCommerceBridge::class, 'without_cash_on_delivery' ), 10);
		$this->assertArrayHasKey('cod', WC()->payment_gateways()->get_available_payment_gateways(), 'Sanity: without the rule WooCommerce would take COD.');
		add_filter('woocommerce_available_payment_gateways', array( WooCommerceBridge::class, 'without_cash_on_delivery' ), 10, 1);

		add_filter('woocommerce_store_api_disable_nonce_check', '__return_true');
		$address = array(
			'first_name' => 'Jane',
			'last_name'  => 'Customer',
			'address_1'  => 'Street 1',
			'city'       => 'Istanbul',
			'postcode'   => '34000',
			'country'    => 'TR',
			'state'      => 'TR34',
			'email'      => 'jane@example.com',
			'phone'      => '5551234567',
		);
		$request = new \WP_REST_Request('POST', '/wc/store/v1/checkout');
		$request->set_header('Content-Type', 'application/json');
		$request->set_body(
			(string) wp_json_encode(
				array(
					'billing_address'  => $address,
					'shipping_address' => $address,
					'payment_method'   => 'cod',
				)
			)
		);
		$response = rest_get_server()->dispatch($request);
		remove_filter('woocommerce_store_api_disable_nonce_check', '__return_true');

		$data = (array) $response->get_data();
		$this->assertSame(400, $response->get_status(), (string) wp_json_encode($data));
		$this->assertSame('mhmrentiva_booking_refused', $data['code'] ?? '', 'REST leaves the list alone; the order hook refuses (ruling delta9).');
		$this->assertSame(0, (int) ( new \WP_Query(array( 'post_type' => 'mhmrentiva_booking', 'post_status' => 'any', 'fields' => 'ids' )) )->found_posts, 'No booking was made.');
	}

	// ------------------------------------- Store API order-pay (delta6)

	/**
	 * POST /wc/store/v1/checkout/{id} for a guest order, paid with COD.
	 *
	 * @return array{0: int, 1: array<string, mixed>}
	 */
	private function pay_order_on_the_route(\WC_Order $order, int $url_id = 0): array
	{
		update_option('woocommerce_cod_settings', array( 'enabled' => 'yes', 'title' => 'Cash on delivery', 'enable_for_virtual' => 'yes' ));
		WC()->payment_gateways()->init();

		add_filter('woocommerce_store_api_disable_nonce_check', '__return_true');
		$address = array(
			'first_name' => 'Jane',
			'last_name'  => 'Customer',
			'address_1'  => 'Street 1',
			'city'       => 'Istanbul',
			'postcode'   => '34000',
			'country'    => 'TR',
			'state'      => 'TR34',
			'email'      => 'jane@example.com',
			'phone'      => '5551234567',
		);
		$request = new \WP_REST_Request('POST', '/wc/store/v1/checkout/' . ( $url_id > 0 ? $url_id : $order->get_id() ));
		if ($url_id > 0) {
			// A query `id` shadows the URL's (WP_REST_Request::get_parameter_order()).
			$request->set_query_params(array( 'id' => $order->get_id() ));
		}
		$request->set_header('Content-Type', 'application/json');
		$request->set_body(
			(string) wp_json_encode(
				array(
					'key'              => $order->get_order_key(),
					'billing_email'    => 'jane@example.com',
					'billing_address'  => $address,
					'shipping_address' => $address,
					'payment_method'   => 'cod',
				)
			)
		);
		$response = rest_get_server()->dispatch($request);
		remove_filter('woocommerce_store_api_disable_nonce_check', '__return_true');

		return array( $response->get_status(), (array) $response->get_data() );
	}

	/**
	 * A guest order: a shop product (null), or a rental line waiting for its booking.
	 *
	 * @param array<string, mixed>|null $line
	 */
	private function guest_order(?array $line): \WC_Order
	{
		$order = wc_create_order();
		$item  = new \WC_Order_Item_Product();
		if (null === $line) {
			$product = new \WC_Product_Simple();
			$product->set_name('Sunglasses');
			$product->set_regular_price('25');
			$product->save();
			$item->set_product($product);
			$item->set_total(25.0);
		} else {
			$item->set_product($this->product);
			$item->set_total(200.0);
			$item->add_meta_data('_mhmrentiva_booking_data', $line);
			$item->add_meta_data('_mhmrentiva_booking_pending', true);
		}
		$item->set_quantity(1);
		$order->add_item($item);
		$order->set_billing_email('jane@example.com');
		$order->set_customer_id(0);
		$order->calculate_totals();
		$order->set_status('pending');
		$order->save();

		return $order;
	}

	/**
	 * Codex delta6 2 / delta7: a shop order paid on the route keeps COD,
	 * whatever the session's cart holds -- the route pays that order, not the
	 * cart, and REST leaves the gateway list alone.
	 *
	 * Its own process: the route defines WOOCOMMERCE_CHECKOUT (StoreApi\Legacy).
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_the_order_pay_route_keeps_cash_on_delivery_for_a_shop_order_with_a_rental_cart(): void
	{
		$order = $this->guest_order(null);
		$this->add_rental();

		list($status, $data) = $this->pay_order_on_the_route($order);

		$this->assertNotSame('woocommerce_rest_checkout_payment_method_disabled', $data['code'] ?? '', (string) wp_json_encode($data));
		$this->assertSame(200, $status, (string) wp_json_encode($data));
		$this->assertSame('cod', wc_get_order($order->get_id())->get_payment_method());
	}

	/**
	 * And a rental order paid on the route with COD is refused, whatever the
	 * cart holds.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_the_order_pay_route_refuses_cash_on_delivery_for_a_rental_order_with_a_shop_cart(): void
	{
		// A payable rental order: the remaining payment of a booking (an order
		// whose booking line still waits is not payable at all).
		$booking = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_booking', 'post_status' => 'publish' ));
		$order   = $this->guest_order(null);
		$order->update_meta_data('_mhmrentiva_is_remaining_payment', '1');
		$order->update_meta_data('_mhmrentiva_booking_id', $booking);
		$order->save();
		$this->add_product();

		list($status, $data) = $this->pay_order_on_the_route($order);

		$this->assertSame(400, $status, (string) wp_json_encode($data));
		$this->assertSame('mhmrentiva_booking_refused', $data['code'] ?? '', 'The order hook decides for the order paid: a rental, so no COD.');
		$this->assertNotSame('processing', wc_get_order($order->get_id())->get_status());
	}

	/**
	 * Fable delta6 F4: a store whose only gateway is COD tells a rental
	 * customer why there is nothing to choose, not WooCommerce's generic line.
	 */
	public function test_a_rental_with_cash_on_delivery_as_the_only_gateway_says_why(): void
	{
		$this->hook('woocommerce_no_available_payment_methods_message', array( WooCommerceBridge::class, 'no_payment_methods_message' ), 10, 1);
		WC()->customer->set_billing_country('TR');
		$generic = 'Sorry, it seems that there are no available payment methods.';
		$this->add_rental();

		$left = WooCommerceBridge::without_cash_on_delivery(array( 'cod' => $this->gateways()['cod'] ));
		$this->assertSame(array(), $left);
		$this->assertSame(
			'Cash on delivery is not offered for rentals, and no other payment method is set up yet. Please contact us to complete your booking.',
			apply_filters('woocommerce_no_available_payment_methods_message', $generic)
		);

		// Another gateway left, or a shop cart: WooCommerce's own message.
		WooCommerceBridge::without_cash_on_delivery($this->gateways());
		$this->assertSame($generic, apply_filters('woocommerce_no_available_payment_methods_message', $generic));
		WC()->cart->empty_cart();
		$this->add_product();
		WooCommerceBridge::without_cash_on_delivery(array( 'cod' => $this->gateways()['cod'] ));
		$this->assertSame($generic, apply_filters('woocommerce_no_available_payment_methods_message', $generic));
	}
	/**
	 * Codex delta7 1: the order WooCommerce pays is $request['id'], and a body
	 * or query `id` shadows the URL's. A rental's URL with the shop order's
	 * `id`: WooCommerce pays the shop order, which keeps COD.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_the_order_pay_route_follows_the_order_woocommerce_pays_when_the_id_is_shadowed(): void
	{
		$booking = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_booking', 'post_status' => 'publish' ));
		$rental  = $this->guest_order(null);
		$rental->update_meta_data('_mhmrentiva_is_remaining_payment', '1');
		$rental->update_meta_data('_mhmrentiva_booking_id', $booking);
		$rental->save();
		$shop = $this->guest_order(null);
		$this->add_rental();

		list($status, $data) = $this->pay_order_on_the_route($shop, $rental->get_id());

		$this->assertSame(200, $status, (string) wp_json_encode($data));
		$this->assertSame('cod', wc_get_order($shop->get_id())->get_payment_method(), 'The shop order is paid with COD.');
		$this->assertNotSame('cod', wc_get_order($rental->get_id())->get_payment_method(), 'The rental order is untouched.');
	}

	/**
	 * Codex delta7 note / Fable delta6 3: a later list in the same process --
	 * one without COD, or a shop cart's -- does not keep the rental's message.
	 */
	public function test_a_later_list_does_not_keep_the_rental_message(): void
	{
		$this->hook('woocommerce_no_available_payment_methods_message', array( WooCommerceBridge::class, 'no_payment_methods_message' ), 10, 1);
		WC()->customer->set_billing_country('TR');
		$generic = 'Sorry, it seems that there are no available payment methods.';
		$this->add_rental();
		WooCommerceBridge::without_cash_on_delivery(array( 'cod' => $this->gateways()['cod'] ));
		$this->assertNotSame($generic, apply_filters('woocommerce_no_available_payment_methods_message', $generic), 'Sanity: the rental message.');

		WC()->cart->empty_cart();
		$this->add_product();
		WooCommerceBridge::without_cash_on_delivery(array());

		$this->assertSame($generic, apply_filters('woocommerce_no_available_payment_methods_message', $generic));
	}

	/**
	 * Fable delta7 1: on the classic checkout a customer with no billing
	 * country yet reads WooCommerce's "fill in your details", not ours.
	 */
	public function test_no_billing_country_keeps_woocommerces_fill_in_message(): void
	{
		$this->hook('woocommerce_no_available_payment_methods_message', array( WooCommerceBridge::class, 'no_payment_methods_message' ), 10, 1);
		WC()->customer->set_billing_country('');
		$fill = 'Please fill in your details above to see available payment methods.';
		$this->add_rental();
		WooCommerceBridge::without_cash_on_delivery(array( 'cod' => $this->gateways()['cod'] ));

		$this->assertSame($fill, apply_filters('woocommerce_no_available_payment_methods_message', $fill));
	}
	/**
	 * A stale REST state, as an integration's caught exception inside
	 * rest_do_request() leaves it: wp_is_rest_endpoint() stays true. The list
	 * keeps COD; the classic checkout still refuses it in validation (Codex
	 * delta8), with one payment error, ours (Fable delta9 2).
	 */
	public function test_with_a_stale_rest_state_the_classic_checkout_still_refuses_cash_on_delivery(): void
	{
		$this->hook('woocommerce_after_checkout_validation', array( WooCommerceBridge::class, 'refuse_cash_on_delivery_at_checkout' ), 20, 2);
		add_filter('wp_is_rest_endpoint', '__return_true');
		try {
			$this->add_rental();
			$this->assertContains('cod', $this->offered(), 'Sanity: the stale state loosens the list.');

			$errors = new \WP_Error();
			// A tampered POST: WooCommerce has already said "Invalid payment method.".
			$errors->add('payment', 'Invalid payment method.');
			do_action('woocommerce_after_checkout_validation', array( 'payment_method' => 'cod' ), $errors);

			$this->assertContains('payment', $errors->get_error_codes(), 'Refused in validation, before WooCommerce calls any gateway.');
			$this->assertSame(array( 'Cash on delivery is not available for a booking. Please choose another payment method.' ), $errors->get_error_messages('payment'), 'Exactly one payment error, ours.');

			$shop = new \WP_Error();
			WC()->cart->empty_cart();
			$this->add_product();
			do_action('woocommerce_after_checkout_validation', array( 'payment_method' => 'cod' ), $shop);
			$this->assertFalse($shop->has_errors(), 'A shop cart keeps COD.');
		} finally {
			remove_filter('wp_is_rest_endpoint', '__return_true');
		}
	}

	/**
	 * The same stale state on the classic order-pay form: WC_Form_Handler::
	 * pay_action() itself, with COD in the list. The refusal's error notice
	 * stops it before process_payment(): the order is not paid, and the method
	 * WooCommerce saved meanwhile is cleared after the action.
	 *
	 * Its own process: a payment that went through would redirect and exit.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_with_a_stale_rest_state_the_classic_order_pay_still_refuses_cash_on_delivery(): void
	{
		update_option('woocommerce_cod_settings', array( 'enabled' => 'yes', 'title' => 'Cash on delivery', 'enable_for_virtual' => 'yes' ));
		WC()->payment_gateways()->init();
		$this->hook('woocommerce_before_pay_action', array( WooCommerceBridge::class, 'refuse_cash_on_delivery_on_pay' ), 5, 1);
		$this->hook('woocommerce_after_pay_action', array( WooCommerceBridge::class, 'forget_refused_cash_on_delivery' ), 10, 1);
		$booking = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_booking', 'post_status' => 'publish' ));
		$order   = $this->order_with(array( '_mhmrentiva_is_remaining_payment' => '1', '_mhmrentiva_booking_id' => $booking ), null);
		add_filter('wp_is_rest_endpoint', '__return_true');
		$this->assertArrayHasKey('cod', WC()->payment_gateways()->get_available_payment_gateways(), 'Sanity: the stale state leaves COD in the list.');

		$GLOBALS['wp']->query_vars['order-pay'] = (string) $order->get_id();
		$_GET['key']                            = $order->get_order_key();
		$_POST['woocommerce_pay']               = '1';
		$_POST['payment_method']                = 'cod';
		$_REQUEST['woocommerce-pay-nonce']      = wp_create_nonce('woocommerce-pay');
		wc_clear_notices();

		\WC_Form_Handler::pay_action();
		while (ob_get_level() > 1) {
			ob_end_clean();
		}

		remove_filter('wp_is_rest_endpoint', '__return_true');
		unset($_POST['woocommerce_pay'], $_POST['payment_method'], $_REQUEST['woocommerce-pay-nonce']);

		$this->assertGreaterThan(0, wc_notice_count('error'), 'The form shows the refusal.');
		$this->assertStringContainsString('Cash on delivery is not available for a booking', (string) ( wc_get_notices('error')[0]['notice'] ?? '' ));
		$this->assertSame('pending', wc_get_order($order->get_id())->get_status(), 'The COD gateway never processed the payment.');
		$this->assertSame('', wc_get_order($order->get_id())->get_payment_method(), 'Codex delta9 3: the refused order keeps no COD method.');
		$this->assertSame('', wc_get_order($order->get_id())->get_payment_method_title());
	}

	/**
	 * Fable delta10 1: with COD out of the list, WooCommerce refuses a tampered
	 * `payment_method=cod` before it writes the method -- the order keeps the
	 * method it was placed with, the cleanup does not blank it.
	 */
	public function test_a_tampered_order_pay_post_keeps_the_orders_own_method(): void
	{
		update_option('woocommerce_cod_settings', array( 'enabled' => 'yes', 'title' => 'Cash on delivery', 'enable_for_virtual' => 'yes' ));
		WC()->payment_gateways()->init();
		$this->hook('woocommerce_available_payment_gateways', array( WooCommerceBridge::class, 'without_cash_on_delivery' ), 10, 1);
		$this->hook('woocommerce_before_pay_action', array( WooCommerceBridge::class, 'refuse_cash_on_delivery_on_pay' ), 5, 1);
		$this->hook('woocommerce_after_pay_action', array( WooCommerceBridge::class, 'forget_refused_cash_on_delivery' ), 10, 1);
		$booking = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_booking', 'post_status' => 'publish' ));
		$order   = $this->order_with(array( '_mhmrentiva_is_remaining_payment' => '1', '_mhmrentiva_booking_id' => $booking ), null, 'bacs');
		$this->pay_page($order);
		$this->assertNotContains('cod', $this->offered(), 'Sanity: on the pay page COD is out of the list.');

		$_POST['woocommerce_pay']               = '1';
		$_POST['payment_method']                = 'cod';
		$_REQUEST['woocommerce-pay-nonce']      = wp_create_nonce('woocommerce-pay');
		wc_clear_notices();

		\WC_Form_Handler::pay_action();
		while (ob_get_level() > 1) {
			ob_end_clean();
		}

		unset($_POST['woocommerce_pay'], $_POST['payment_method'], $_REQUEST['woocommerce-pay-nonce']);

		$this->assertGreaterThan(0, wc_notice_count('error'), 'Sanity: the post was refused.');
		$this->assertSame('bacs', wc_get_order($order->get_id())->get_payment_method(), 'The order keeps the method it was placed with.');
	}

	/**
	 * Every COD refusal and the cleanup are wired by register(), not only by
	 * the tests' own hook() calls (Fable delta10 2).
	 */
	public function test_register_wires_the_cash_on_delivery_hooks(): void
	{
		$wired = array(
			array( 'woocommerce_available_payment_gateways', 'without_cash_on_delivery', 10 ),
			array( 'woocommerce_after_checkout_validation', 'refuse_cash_on_delivery_at_checkout', 20 ),
			array( 'woocommerce_before_pay_action', 'refuse_cash_on_delivery_on_pay', 5 ),
			array( 'woocommerce_after_pay_action', 'forget_refused_cash_on_delivery', 10 ),
			array( 'woocommerce_store_api_checkout_order_processed', 'store_api_order_processed', 10 ),
		);
		// WP_UnitTestCase restores $wp_filter after the test.
		WooCommerceBridge::register();

		foreach ($wired as [ $name, $method, $priority ]) {
			$this->assertSame($priority, has_filter($name, array( WooCommerceBridge::class, $method )), $name . ' -> ' . $method);
		}
	}

	/**
	 * Fable delta9 2: a tampered `payment_method=cod` on the classic checkout
	 * of a rental cart reads one payment error -- ours, not WooCommerce's
	 * "Invalid payment method." next to it.
	 */
	public function test_a_tampered_cash_on_delivery_post_reads_one_payment_error(): void
	{
		$this->hook('woocommerce_after_checkout_validation', array( WooCommerceBridge::class, 'refuse_cash_on_delivery_at_checkout' ), 20, 2);
		$this->add_rental();
		$errors = new \WP_Error();
		$errors->add('payment', 'Invalid payment method.');

		do_action('woocommerce_after_checkout_validation', array( 'payment_method' => 'cod' ), $errors);

		$this->assertSame(array( 'Cash on delivery is not available for a booking. Please choose another payment method.' ), $errors->get_error_messages('payment'));
	}
}
