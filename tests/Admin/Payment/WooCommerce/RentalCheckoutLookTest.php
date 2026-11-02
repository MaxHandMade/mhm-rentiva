<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Payment\WooCommerce;

use MHMRentiva\Admin\Payment\WooCommerce\RentalCheckoutLook;
use MHMRentiva\Admin\Payment\WooCommerce\WooCommerceBridge;
use MHMRentiva\Tests\Support\WooCommerceFixtures;
use WP_UnitTestCase;

/**
 * PR K Task 3 (R10, R12-R16): the hooks rvs-checkout.css relies on and the
 * words the cart, checkout and thank-you pages carry for one rental. Plugin
 * hooks are not registered under PHPUnit, so each test adds what it reads.
 *
 * @covers \MHMRentiva\Admin\Payment\WooCommerce\RentalCheckoutLook
 * @covers \MHMRentiva\Admin\Payment\WooCommerce\WooCommerceBridge
 */
final class RentalCheckoutLookTest extends WP_UnitTestCase
{
	use WooCommerceFixtures;

	private \WC_Product $product;

	/** @var mixed */
	private $scripts_before = null;

	public function setUp(): void
	{
		parent::setUp();

		$this->require_woocommerce();
		$this->product = $this->ensure_booking_product('100');

		wc_load_cart();
		WC()->cart->empty_cart();
		$this->scripts_before  = $GLOBALS['wp_scripts'] ?? null;
		$GLOBALS['wp_scripts'] = null;
	}

	public function tearDown(): void
	{
		if (function_exists('WC') && WC()->cart) {
			WC()->cart->empty_cart();
		}
		RentalCheckoutLook::close_cart_heads();
		$GLOBALS['wp_scripts'] = $this->scripts_before;
		remove_all_filters('woocommerce_is_checkout');
		remove_all_filters('woocommerce_is_cart');
		remove_all_filters('woocommerce_is_order_received_page');
		remove_all_filters('mhmrentiva_empty_cart_to_vehicles');
		remove_all_filters('mhmrentiva_vehicles_page_url');
		remove_all_actions('woocommerce_before_cart_table');
		remove_all_actions('woocommerce_after_cart_table');
		if (! has_action('woocommerce_cart_is_empty', 'wc_empty_cart_message')) {
			add_action('woocommerce_cart_is_empty', 'wc_empty_cart_message', 10);
		}
		unset($GLOBALS['wp']->query_vars['order-received']);
		wp_set_current_user(0);
		parent::tearDown();
	}

	/**
	 * @param array<string, mixed> $extra
	 * @return array<string, mixed>
	 */
	private function rental_data(array $extra = array()): array
	{
		return array_merge(
			array(
				'vehicle_id'         => self::factory()->post->create(array( 'post_type' => 'mhmrentiva_vehicle', 'post_status' => 'publish' )),
				'pickup_date'        => '2030-11-06',
				'pickup_time'        => '10:00',
				'dropoff_date'       => '2030-11-09',
				'dropoff_time'       => '10:00',
				'payment_type'       => 'deposit',
				'total_price'        => 1000.0,
				'deposit_amount'     => 200.0,
				'remaining_amount'   => 800.0,
				'deposit_percentage' => 20,
				'rental_days'        => 3,
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
	 * @param array<string, mixed>|null $line
	 */
	private function order(?array $line, string $method = 'bacs', int $customer = 0): \WC_Order
	{
		$order = wc_create_order(array( 'customer_id' => $customer ));
		$item  = new \WC_Order_Item_Product();
		$item->set_product($this->product);
		$item->set_quantity(1);
		$item->set_total(200.0);
		if (null !== $line) {
			$item->add_meta_data('_mhmrentiva_booking_data', $line);
		}
		$order->add_item($item);
		$order->set_payment_method($method);
		$order->calculate_totals();
		$order->save();

		return $order;
	}

	private function capture(callable $draw): string
	{
		ob_start();
		$draw();

		return (string) ob_get_clean();
	}

	// ------------------------------------------------------------ line class

	public function test_a_rentiva_cart_line_carries_the_line_class_and_a_shop_line_does_not(): void
	{
		$this->assertSame('cart_item rvs-cart-line', RentalCheckoutLook::line_class('cart_item', array( 'mhmrentiva_booking_data' => array() )));
		$this->assertSame('cart_item rvs-cart-line', RentalCheckoutLook::line_class('cart_item', array( 'mhmrentiva_booking_id' => 5 )));
		$this->assertSame('cart_item', RentalCheckoutLook::line_class('cart_item', array( 'product_id' => 9 )));
	}

	public function test_a_rentiva_order_line_carries_the_line_class(): void
	{
		$rental = $this->order($this->rental_data());
		$shop   = $this->order(null);

		$this->assertSame('order_item rvs-cart-line', RentalCheckoutLook::order_line_class('order_item', current($rental->get_items())));
		$this->assertSame('order_item', RentalCheckoutLook::order_line_class('order_item', current($shop->get_items())));
	}

	// --------------------------------------------------- cart heads (canvas)

	public function test_one_rental_cart_names_its_columns_rental_and_due_now_and_nothing_after(): void
	{
		add_filter('woocommerce_is_cart', '__return_true');
		add_action('woocommerce_before_cart_table', array( RentalCheckoutLook::class, 'open_cart_heads' ), 10, 0);
		add_action('woocommerce_after_cart_table', array( RentalCheckoutLook::class, 'close_cart_heads' ), 10, 0);
		$this->add_rental();
		WC()->cart->calculate_totals();

		$html = $this->capture(static fn() => wc_get_template('cart/cart.php'));

		$this->assertMatchesRegularExpression('/<th scope="col" class="product-name">Rental<\/th>/', $html);
		$this->assertMatchesRegularExpression('/<th scope="col" class="product-subtotal">Due now<\/th>/', $html);
		$this->assertStringContainsString('data-title="Due now"', $html);
		$this->assertSame('Subtotal', __('Subtotal', 'woocommerce'), 'The window closed with the table.');
		$this->assertFalse(has_filter('gettext_woocommerce', array( RentalCheckoutLook::class, 'relabel_head' )));
	}

	public function test_a_mixed_cart_keeps_woocommerce_column_names(): void
	{
		add_filter('woocommerce_is_cart', '__return_true');
		$this->add_rental();
		$this->add_product();

		RentalCheckoutLook::open_cart_heads();

		$this->assertSame('Product', __('Product', 'woocommerce'));
		$this->assertFalse(has_filter('gettext_woocommerce', array( RentalCheckoutLook::class, 'relabel_head' )));
	}

	// ------------------------------------------------------ empty cart (R13)

	public function test_the_empty_cart_sends_the_customer_to_the_vehicles(): void
	{
		add_filter('mhmrentiva_vehicles_page_url', static fn() => 'http://example.org/vehicles/');

		$html = $this->capture(static fn() => RentalCheckoutLook::render_empty_cart());

		$this->assertStringContainsString('wc-empty-cart-message', $html, 'cart.js looks for this wrapper after the last removal (B2).');
		$this->assertStringContainsString('rvs-cart-empty', $html);
		$this->assertStringContainsString('href="http://example.org/vehicles/"', $html);
		$this->assertStringContainsString('Browse vehicles', $html);
		$this->assertFalse(has_action('woocommerce_cart_is_empty', 'wc_empty_cart_message'), 'WooCommerce\'s own notice is not drawn twice.');
		$this->assertSame('http://example.org/vehicles/', RentalCheckoutLook::return_to_shop_url('http://example.org/shop/'));
		$this->assertSame('Browse vehicles', RentalCheckoutLook::return_to_shop_text('Return to shop'));
	}

	public function test_a_shop_that_turns_it_off_keeps_woocommerce_empty_cart(): void
	{
		add_filter('mhmrentiva_empty_cart_to_vehicles', '__return_false');

		$this->assertSame('', $this->capture(static fn() => RentalCheckoutLook::render_empty_cart()));
		$this->assertSame(10, has_action('woocommerce_cart_is_empty', 'wc_empty_cart_message'));
		$this->assertSame('http://example.org/shop/', RentalCheckoutLook::return_to_shop_url('http://example.org/shop/'));
		$this->assertSame('Return to shop', RentalCheckoutLook::return_to_shop_text('Return to shop'));
	}

	// --------------------------------------------------- thank-you (R14, m1)

	public function test_a_rental_order_received_page_has_one_title_and_a_booking_sentence(): void
	{
		$order = $this->order($this->rental_data());
		add_filter('woocommerce_is_order_received_page', '__return_true');
		$GLOBALS['wp']->query_vars['order-received'] = (string) $order->get_id();

		$this->assertSame('Your booking is received', RentalCheckoutLook::order_received_title('Order received'));
		$this->assertStringContainsString('We have your booking', (string) RentalCheckoutLook::order_received_text('Thank you.', $order));

		$shop = $this->order(null);
		$GLOBALS['wp']->query_vars['order-received'] = (string) $shop->get_id();
		$this->assertSame('Order received', RentalCheckoutLook::order_received_title('Order received'));
		$this->assertSame('Thank you.', RentalCheckoutLook::order_received_text('Thank you.', $shop));
	}

	// ------------------------------------------------- bank transfer (R10)

	public function test_a_guest_bank_order_names_the_deposit_to_send_and_emails_the_receipt(): void
	{
		update_option('woocommerce_email_from_address', 'shop@example.org');
		$order = $this->order($this->rental_data());

		$html = $this->capture(static fn() => RentalCheckoutLook::render_bank_transfer($order->get_id()));

		$this->assertStringContainsString('Deposit to send (', $html);
		$this->assertStringContainsString('Due at pickup', $html);
		$this->assertStringContainsString('<strong>' . $order->get_order_number() . '</strong>', $html);
		$this->assertStringContainsString('mailto:shop@example.org', $html);
		$this->assertStringNotContainsString('Upload receipt', $html, 'A guest has no account page.');
	}

	public function test_the_owner_of_a_bank_order_uploads_the_receipt_in_my_account(): void
	{
		$user = self::factory()->user->create(array( 'role' => 'customer' ));
		wp_set_current_user($user);
		$order = $this->order($this->rental_data(), 'bacs', $user);

		$html = $this->capture(static fn() => RentalCheckoutLook::render_bank_transfer($order->get_id()));

		$this->assertStringContainsString('Upload receipt', $html);
		$this->assertStringNotContainsString('mailto:', $html);
	}

	public function test_a_full_payment_bank_order_names_the_total_to_send_without_pickup(): void
	{
		$order = $this->order($this->rental_data(array( 'payment_type' => 'full', 'deposit_amount' => 0.0, 'remaining_amount' => 0.0 )));

		$html = $this->capture(static fn() => RentalCheckoutLook::render_bank_transfer($order->get_id()));

		$this->assertStringContainsString('To send', $html);
		$this->assertStringNotContainsString('Due at pickup', $html);
	}

	public function test_no_transfer_card_for_a_shop_order_or_a_failed_rental(): void
	{
		$this->assertSame('', $this->capture(fn() => RentalCheckoutLook::render_bank_transfer($this->order(null)->get_id())));

		$failed = $this->order($this->rental_data());
		$failed->update_status('failed');
		$this->assertSame('', $this->capture(static fn() => RentalCheckoutLook::render_bank_transfer($failed->get_id())));
	}

	// ----------------------------------------- block image script (m3, R12)

	public function test_the_block_image_script_loads_only_where_a_cart_block_renders_with_a_booking(): void
	{
		RentalCheckoutLook::with_block_image_injector('<div></div>');
		$this->assertFalse(wp_script_is('mhm-rentiva-woocommerce-cart-block', 'enqueued'), 'No booking, no script.');

		$this->add_rental();
		$this->assertSame('<div></div>', RentalCheckoutLook::with_block_image_injector('<div></div>'));
		$this->assertTrue(wp_script_is('mhm-rentiva-woocommerce-cart-block', 'enqueued'));
	}

	public function test_the_block_image_script_has_no_inline_styles(): void
	{
		$js = (string) file_get_contents(MHMRENTIVA_PLUGIN_PATH . 'assets/js/payment/woocommerce-cart-block.js');

		$this->assertStringNotContainsString('style.cssText', $js);
		$this->assertStringNotContainsString('console.log', $js);
	}

	// ---------------------------------------------------- R12 (no inline style)

	public function test_the_vehicle_thumbnail_is_sized_by_class(): void
	{
		$html = WooCommerceBridge::set_universal_vehicle_thumbnail('<img>', array( 'mhmrentiva_booking_data' => array( 'vehicle_id' => 0 ) ), 'k');

		$this->assertStringContainsString('rvs-cart-thumb', $html);
		$this->assertStringNotContainsString('style=', $html);
	}

	public function test_return_to_cart_sits_beside_the_heading_for_a_rental_and_inside_the_review_otherwise(): void
	{
		$this->add_rental();
		$beside = $this->capture(static fn() => WooCommerceBridge::add_return_to_cart_link());
		$inside = $this->capture(static fn() => WooCommerceBridge::add_return_to_cart_link_in_review());
		$this->assertStringContainsString('mhm-return-cart-link', $beside);
		$this->assertStringNotContainsString('style=', $beside);
		$this->assertSame('', $inside);

		$this->add_product();
		$this->assertSame('', $this->capture(static fn() => WooCommerceBridge::add_return_to_cart_link()));
		$this->assertStringContainsString('mhm-return-cart-link', $this->capture(static fn() => WooCommerceBridge::add_return_to_cart_link_in_review()));

		WC()->cart->empty_cart();
		$this->add_product();
		$this->assertSame('', $this->capture(static fn() => WooCommerceBridge::add_return_to_cart_link_in_review()), 'A shop order gets no link.');
	}

	// --------------------------------------------------- billing grid (canvas)

	public function test_a_booking_checkout_locale_keeps_the_canvas_field_order(): void
	{
		add_filter('woocommerce_is_checkout', '__return_true');
		$this->add_rental();

		$all = WooCommerceBridge::booking_address_locale(
			array(
				'TR' => array( 'postcode' => array( 'priority' => 65 ) ),
				'US' => array( 'state' => array( 'label' => 'State' ) ),
			)
		);
		$this->assertSame(31, $all['TR']['postcode']['priority']);
		$this->assertSame(array( 'form-row-last' ), $all['TR']['postcode']['class']);
		$this->assertSame(21, $all['US']['state']['priority']);
		$this->assertSame('State', $all['US']['state']['label'], 'Labels stay the country\'s.');
		$this->assertArrayNotHasKey('city', $all['US'], 'A country entry gains no field it did not set.');

		$default = WooCommerceBridge::booking_address_locale(array( 'first_name' => array( 'priority' => 10 ), 'phone' => array( 'priority' => 100, 'required' => false ) ));
		$this->assertSame(50, $default['phone']['priority']);
		$this->assertTrue($default['phone']['required'], 'The booking\'s phone is required.');
		$this->assertSame(51, $default['email']['priority']);
	}

	public function test_a_shop_checkout_locale_is_untouched(): void
	{
		add_filter('woocommerce_is_checkout', '__return_true');
		$this->add_product();

		$locale = array( 'TR' => array( 'postcode' => array( 'priority' => 65 ) ) );
		$this->assertSame($locale, WooCommerceBridge::booking_address_locale($locale));
	}
}
