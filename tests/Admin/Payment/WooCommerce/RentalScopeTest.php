<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Payment\WooCommerce;

use MHMRentiva\Admin\Payment\WooCommerce\CheckoutJourney;
use MHMRentiva\Admin\Payment\WooCommerce\WooCommerceBridge;
use MHMRentiva\Admin\Settings\Core\SettingsCore;
use MHMRentiva\Tests\Support\WooCommerceFixtures;
use WP_UnitTestCase;

/**
 * PR K, R1-R4: what each hook does for a rental, a shop product, a mixed cart
 * and a transfer. Rules (fields, block checkout) follow any Rentiva line; the
 * look (body class, assets, tax row) follows exactly one rental.
 *
 * @covers \MHMRentiva\Admin\Payment\WooCommerce\CheckoutJourney
 * @covers \MHMRentiva\Admin\Payment\WooCommerce\WooCommerceBridge
 */
final class RentalScopeTest extends WP_UnitTestCase
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
		// Handed back in tearDown(): later tests read handles registered at load.
		$this->scripts_before  = $GLOBALS['wp_scripts'] ?? null;
		$GLOBALS['wp_styles']  = null;
		$GLOBALS['wp_scripts'] = null;
	}

	public function tearDown(): void
	{
		if (function_exists('WC') && WC()->cart) {
			WC()->cart->empty_cart();
		}
		$GLOBALS['wp_styles']  = null;
		$GLOBALS['wp_scripts'] = $this->scripts_before;
		remove_all_filters('woocommerce_is_checkout');
		remove_all_filters('woocommerce_is_order_received_page');
		remove_all_filters('woocommerce_is_cart');
		unset($GLOBALS['wp']->query_vars['order-received'], $GLOBALS['wp']->query_vars['order-pay'], $_GET['key']);
		update_option('woocommerce_tax_display_cart', 'incl');
		parent::tearDown();
	}

	/**
	 * An order page's URL: its id and its key (CheckoutJourney reads both).
	 */
	private function visit(string $endpoint, \WC_Order $order): void
	{
		$GLOBALS['wp']->query_vars[ $endpoint ] = (string) $order->get_id();
		$_GET['key']                            = $order->get_order_key();
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
				'pickup_date'      => '2030-11-06',
				'pickup_time'      => '10:00',
				'dropoff_date'     => '2030-11-09',
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

	private function add_transfer(): void
	{
		$this->add_rental(array( 'booking_type' => 'transfer', 'transfer_origin_id' => 7 ));
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
	 * The four carts every hook is asked about, with a name for the message.
	 *
	 * @return array<string, callable(self): void>
	 */
	private static function carts(): array
	{
		return array(
			'rental'   => static fn(self $t) => $t->add_rental(),
			'product'  => static fn(self $t) => $t->add_product(),
			'mixed'    => static function (self $t): void {
				$t->add_rental();
				$t->add_product();
			},
			'transfer' => static fn(self $t) => $t->add_transfer(),
		);
	}

	/**
	 * Run a question against each cart.
	 *
	 * @return array<string, mixed>
	 */
	private function ask(callable $question): array
	{
		$out = array();
		foreach (self::carts() as $name => $fill) {
			WC()->cart->empty_cart();
			$fill($this);
			$out[ $name ] = $question();
		}
		WC()->cart->empty_cart();

		return $out;
	}

	private function fields(): array
	{
		return array(
			'billing' => array(
				'billing_address_2' => array( 'label' => 'Apartment' ),
				'billing_phone'     => array( 'required' => false, 'label' => 'Phone', 'class' => array( 'form-row-wide' ) ),
				'billing_city'      => array( 'label' => 'Town', 'class' => array( 'form-row-wide' ) ),
			),
		);
	}

	private function order_with(array $meta, ?array $line): \WC_Order
	{
		$order = wc_create_order();
		$item  = new \WC_Order_Item_Product();
		$item->set_product($this->product);
		$item->set_quantity(1);
		$item->set_total(200.0);
		if (null !== $line) {
			$item->add_meta_data('_mhmrentiva_booking_data', $line);
		}
		$order->add_item($item);
		foreach ($meta as $key => $value) {
			$order->update_meta_data($key, $value);
		}
		$order->calculate_totals();
		$order->save();

		return $order;
	}

	// ---------------------------------------------------------- body class (R1)

	public function test_body_class_on_checkout_only_for_exactly_one_rental(): void
	{
		add_filter('woocommerce_is_checkout', '__return_true');

		$this->assertSame(
			array( 'rental' => true, 'product' => false, 'mixed' => false, 'transfer' => false ),
			$this->ask(static fn() => in_array('rvs-rental', CheckoutJourney::body_class(array( 'x' )), true))
		);
	}

	public function test_body_class_needs_a_cart_or_checkout_page(): void
	{
		$this->add_rental();

		$this->assertSame(array( 'x' ), CheckoutJourney::body_class(array( 'x' )), 'Not on any other page.');

		add_filter('woocommerce_is_cart', '__return_true');
		$this->assertContains('rvs-rental', CheckoutJourney::body_class(array( 'x' )));
	}

	public function test_body_class_on_thank_you_follows_the_order_not_the_cart(): void
	{
		add_filter('woocommerce_is_order_received_page', '__return_true');
		$this->add_rental(); // The cart must not answer for the order.

		$this->visit('order-received', $this->order_with(array(), null));
		$this->assertNotContains('rvs-rental', CheckoutJourney::body_class(array()), 'A shop order.');

		$this->visit('order-received', $this->order_with(array(), $this->rental_data()));
		$this->assertContains('rvs-rental', CheckoutJourney::body_class(array()), 'A rental order.');

		$this->visit('order-received', $this->order_with(array(), $this->rental_data(array( 'booking_type' => 'transfer' ))));
		$this->assertNotContains('rvs-rental', CheckoutJourney::body_class(array()), 'A transfer order.');
	}

	public function test_body_class_on_order_pay_for_a_rental_and_for_its_remaining_payment(): void
	{
		add_filter('woocommerce_is_checkout', '__return_true');

		$this->visit('order-pay', $this->order_with(array(), null));
		$this->assertNotContains('rvs-rental', CheckoutJourney::body_class(array()), 'A shop order.');

		$this->visit('order-pay', $this->order_with(array(), $this->rental_data()));
		$this->assertContains('rvs-rental', CheckoutJourney::body_class(array()), 'A rental order.');

		// A remaining-payment line carries no snapshot, only these keys (m7).
		$booking = self::factory()->post->create(array( 'post_type' => 'mhmrentiva_booking', 'post_status' => 'publish' ));
		$this->visit('order-pay', $this->order_with(
			array( '_mhmrentiva_is_remaining_payment' => '1', '_mhmrentiva_booking_id' => $booking ),
			null
		));
		$this->assertContains('rvs-rental', CheckoutJourney::body_class(array()), 'A remaining payment.');

		update_post_meta($booking, '_mhmrentiva_transfer_origin_id', 7);
		$this->assertNotContains('rvs-rental', CheckoutJourney::body_class(array()), 'A transfer\'s remaining payment.');
	}

	public function test_tokens_reach_the_rental_body_scope(): void
	{
		$css = (string) file_get_contents(MHMRENTIVA_PLUGIN_PATH . 'assets/css/rvs/rvs-tokens.css');

		$this->assertMatchesRegularExpression('/\.woocommerce-account \.woocommerce,\s*body\.rvs-rental,\s*\.rvs-cart-line\s*\{/', $css);
	}

	// ------------------------------------------------------- field rules (R3)

	public function test_field_rules_follow_any_rentiva_line(): void
	{
		$answers = $this->ask(
			fn() => array(
				'address_2' => isset(WooCommerceBridge::reorder_checkout_fields($this->fields())['billing']['billing_address_2']),
				'phone'     => WooCommerceBridge::reorder_checkout_fields($this->fields())['billing']['billing_phone']['required'],
				'city'      => WooCommerceBridge::reorder_checkout_fields($this->fields())['billing']['billing_city']['label'],
			)
		);

		$this->assertFalse($answers['rental']['address_2']);
		$this->assertTrue($answers['rental']['phone']);

		// Mixed and transfer: the booking takes its phone from here (M5).
		$this->assertFalse($answers['mixed']['address_2']);
		$this->assertTrue($answers['mixed']['phone']);
		$this->assertFalse($answers['transfer']['address_2']);
		$this->assertTrue($answers['transfer']['phone']);

		// The shop's own products: untouched.
		$this->assertSame(array( 'address_2' => true, 'phone' => false, 'city' => 'Town' ), $answers['product']);
	}

	public function test_return_to_cart_link_only_with_a_rentiva_line(): void
	{
		$links = $this->ask(
			static function () {
				// Beside the heading for one rental, inside the review otherwise (Task 3).
				ob_start();
				WooCommerceBridge::add_return_to_cart_link();
				WooCommerceBridge::add_return_to_cart_link_in_review();
				return 1 === substr_count((string) ob_get_clean(), 'mhm-return-cart-link');
			}
		);

		$this->assertSame(array( 'rental' => true, 'product' => false, 'mixed' => true, 'transfer' => true ), $links);
	}

	// ------------------------------------------------------- tax display (R3)

	public function test_total_suffix_is_stripped_only_on_a_rental_checkout(): void
	{
		$html = '<strong>$10</strong> <small class="includes_tax">(includes $2 tax)</small>';
		add_filter('woocommerce_is_checkout', '__return_true');

		$this->assertSame(
			array( 'rental' => '<strong>$10</strong> ', 'product' => $html, 'mixed' => $html, 'transfer' => $html ),
			$this->ask(static fn() => WooCommerceBridge::clean_checkout_total_html($html))
		);
	}

	public function test_total_suffix_stays_on_the_cart_page(): void
	{
		// The tax row replacing it exists only on the checkout review table: on
		// the classic cart the suffix is the customer's only mention of tax.
		$html = '<strong>$10</strong> <small class="includes_tax">(includes $2 tax)</small>';
		$this->add_rental();

		$this->assertSame($html, WooCommerceBridge::clean_checkout_total_html($html));
	}

	private function tax_row(): string
	{
		ob_start();
		WooCommerceBridge::add_custom_tax_row_checkout();
		return (string) ob_get_clean();
	}

	public function test_tax_row_is_not_doubled_when_prices_show_without_tax(): void
	{
		update_option('woocommerce_calc_taxes', 'yes');
		$rate = \WC_Tax::_insert_tax_rate(
			array(
				'tax_rate_country'  => '',
				'tax_rate'          => '20.0000',
				'tax_rate_name'     => 'VAT',
				'tax_rate_priority' => 1,
				'tax_rate_compound' => 0,
				'tax_rate_shipping' => 1,
				'tax_rate_order'    => 1,
				'tax_rate_class'    => '',
			)
		);
		\WC_Cache_Helper::invalidate_cache_group('taxes');

		$this->add_rental();
		WC()->customer->set_is_vat_exempt(false);
		WC()->cart->calculate_totals();
		$this->assertNotEmpty(WC()->cart->get_tax_totals(), 'Sanity: the cart carries tax.');

		update_option('woocommerce_tax_display_cart', 'incl');
		WC()->cart->tax_display_cart = 'incl';
		$this->assertStringContainsString('mhm-custom-tax-row', $this->tax_row());

		// WooCommerce's review-order.php draws its own tax rows in this mode.
		update_option('woocommerce_tax_display_cart', 'excl');
		WC()->cart->tax_display_cart = 'excl';
		$this->assertSame('', $this->tax_row());

		// Only one rental gets the row at all: a shop cart keeps WooCommerce's.
		update_option('woocommerce_tax_display_cart', 'incl');
		WC()->cart->tax_display_cart = 'incl';
		$this->add_product();
		WC()->cart->calculate_totals();
		$this->assertSame('', $this->tax_row(), 'Mixed cart.');

		\WC_Tax::_delete_tax_rate($rate);
		update_option('woocommerce_calc_taxes', 'no');
	}

	// ---------------------------------------------------------- assets (R3)

	/**
	 * R16: rvs-checkout.css reaches every checkout that holds a Rentiva line
	 * (the whole page for one rental, Rentiva's own parts on a mixed or transfer
	 * cart); the shop's own order never gets it. The old stylesheets and the
	 * old checkout script are gone.
	 */
	public function test_checkout_stylesheet_follows_any_rentiva_line_and_old_assets_are_gone(): void
	{
		add_filter('woocommerce_is_checkout', '__return_true');

		$answers = $this->ask(
			static function () {
				$GLOBALS['wp_styles']  = null;
				$GLOBALS['wp_scripts'] = null;
				WooCommerceBridge::enqueue_checkout_styles();

				return array(
					'css'    => wp_style_is('mhm-rentiva-rvs-checkout', 'enqueued'),
					'old'    => wp_style_is('mhm-rentiva-woocommerce-checkout', 'registered') || wp_style_is('mhm-rentiva-woocommerce-cart', 'registered'),
					'old_js' => wp_script_is('mhm-rentiva-woocommerce-checkout-js', 'registered') || wp_script_is('mhm-rentiva-woocommerce-cart-block', 'enqueued'),
				);
			}
		);

		$yes = array( 'css' => true, 'old' => false, 'old_js' => false );
		$no  = array( 'css' => false, 'old' => false, 'old_js' => false );
		$this->assertSame(array( 'rental' => $yes, 'product' => $no, 'mixed' => $yes, 'transfer' => $yes ), $answers);
		$this->assertFileDoesNotExist(MHMRENTIVA_PLUGIN_PATH . 'assets/css/payment/woocommerce-checkout.css');
		$this->assertFileDoesNotExist(MHMRENTIVA_PLUGIN_PATH . 'assets/css/payment/woocommerce-cart.css');
		$this->assertFileDoesNotExist(MHMRENTIVA_PLUGIN_PATH . 'assets/js/payment/woocommerce-checkout.js');
	}

	public function test_cart_stylesheet_follows_any_rentiva_line(): void
	{
		add_filter('woocommerce_is_cart', '__return_true');

		$this->assertSame(
			array( 'rental' => true, 'product' => false, 'mixed' => true, 'transfer' => true ),
			$this->ask(
				static function () {
					$GLOBALS['wp_styles'] = null;
					WooCommerceBridge::enqueue_checkout_styles();

					return wp_style_is('mhm-rentiva-rvs-checkout', 'enqueued');
				}
			)
		);
	}

	public function test_remaining_payment_page_gets_the_rental_assets_and_a_shop_order_does_not(): void
	{
		add_filter('woocommerce_is_checkout', '__return_true');

		$this->visit('order-pay', $this->order_with(array( '_mhmrentiva_is_remaining_payment' => '1' ), null));
		WooCommerceBridge::enqueue_checkout_styles();
		$this->assertTrue(wp_style_is('mhm-rentiva-rvs-tokens', 'enqueued') || wp_style_is('mhm-rentiva-rvs-booking', 'enqueued'), 'The tokens reach the page.');
		$this->assertTrue(wp_style_is('mhm-rentiva-rvs-checkout', 'enqueued'));

		$GLOBALS['wp_styles'] = null;
		$this->visit('order-pay', $this->order_with(array(), null));
		WooCommerceBridge::enqueue_checkout_styles();
		$this->assertFalse(wp_style_is('mhm-rentiva-rvs-checkout', 'enqueued'));
	}

	// ------------------------------------------------------- block swap (R4)

	/**
	 * @return array<string, mixed>
	 */
	private static function block(string $name): array
	{
		return array( 'blockName' => $name, 'attrs' => array(), 'innerBlocks' => array(), 'innerHTML' => '', 'innerContent' => array() );
	}

	private function classic_cart_setting(string $value): void
	{
		$settings = get_option(SettingsCore::OPTION_NAME, array());
		$settings = is_array($settings) ? $settings : array();
		$settings['mhmrentiva_classic_cart'] = $value;
		update_option(SettingsCore::OPTION_NAME, $settings);
	}

	public function test_cart_block_turns_classic_on_every_request_by_default(): void
	{
		foreach ($this->ask(static fn() => CheckoutJourney::pre_render_block(null, self::block('woocommerce/cart'))) as $cart => $html) {
			$this->assertIsString($html, "Filled cart: {$cart}.");
			$this->assertStringContainsString('woocommerce-cart-form', $html, "Filled cart: {$cart}.");
		}

		// Empty: the classic empty message, which cart.js needs after the last
		// line is removed (B2).
		$html = CheckoutJourney::pre_render_block(null, self::block('woocommerce/cart'));
		$this->assertIsString($html);
		$this->assertStringContainsString('wc-empty-cart-message', $html);
	}

	public function test_cart_block_stays_when_the_setting_is_off(): void
	{
		$this->classic_cart_setting('0');
		$this->add_rental();

		$this->assertNull(CheckoutJourney::pre_render_block(null, self::block('woocommerce/cart')));
	}

	public function test_checkout_block_turns_classic_only_with_a_rentiva_line(): void
	{
		$answers = $this->ask(static fn() => CheckoutJourney::pre_render_block(null, self::block('woocommerce/checkout')));

		$this->assertNull($answers['product'], 'The shop\'s own order keeps its block.');
		foreach (array( 'rental', 'mixed', 'transfer' ) as $cart) {
			$this->assertIsString($answers[ $cart ], $cart);
			$this->assertStringContainsString('woocommerce-checkout', $answers[ $cart ], $cart);
			$this->assertStringNotContainsString('wc-block-checkout', $answers[ $cart ], $cart);
		}
	}

	public function test_block_swap_leaves_other_blocks_and_an_earlier_answer_alone(): void
	{
		$this->add_rental();

		$this->assertNull(CheckoutJourney::pre_render_block(null, self::block('core/paragraph')));
		$this->assertSame('earlier', CheckoutJourney::pre_render_block('earlier', self::block('woocommerce/cart')));
	}

	// ------------------------------------------------------- resource hint (N1)

	public function test_prerender_hint_for_the_checkout_is_dropped(): void
	{
		$page = self::factory()->post->create(array( 'post_type' => 'page', 'post_status' => 'publish' ));
		update_option('woocommerce_checkout_page_id', $page);
		$checkout = (string) get_permalink($page);
		$other    = 'http://example.test/other/';

		$this->assertSame(array( $other ), array_values(CheckoutJourney::resource_hints(array( $checkout, $other ), 'prerender')));
		$this->assertSame(array( array( 'href' => $other ) ), array_values(CheckoutJourney::resource_hints(array( array( 'href' => $checkout ), array( 'href' => $other ) ), 'prerender')));

		// Other relations are not this filter's business.
		$this->assertSame(array( $checkout ), CheckoutJourney::resource_hints(array( $checkout ), 'prefetch'));

		// A kept Cart block with a shop cart: WooCommerce's own hint, harmless.
		$this->classic_cart_setting('0');
		$this->add_product();
		$this->assertSame(array( $checkout ), CheckoutJourney::resource_hints(array( $checkout ), 'prerender'));

		// A kept Cart block with a rental: the checkout turns classic, the hint
		// would spend its notices.
		$this->add_rental();
		$this->assertSame(array(), array_values(CheckoutJourney::resource_hints(array( $checkout ), 'prerender')));
	}
}
