<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Payment;

use MHMRentiva\Admin\Frontend\Account\AccountController;
use MHMRentiva\Admin\Payment\WooCommerce\CheckoutJourney;
use MHMRentiva\Admin\Payment\WooCommerce\WooCommerceBridge;
use MHMRentiva\Tests\Support\WooCommerceFixtures;
use WP_UnitTestCase;

/**
 * The booking journey on WooCommerce's classic checkout and thank-you pages:
 * the step bar and a Rentiva summary, drawn from the cart or order line
 * snapshot -- never re-priced -- and the admin warning for a block checkout.
 *
 * @covers \MHMRentiva\Admin\Payment\WooCommerce\CheckoutJourney
 * @covers \MHMRentiva\Admin\Payment\WooCommerce\WooCommerceBridge::rental_cart_data
 */
final class CheckoutJourneyTest extends WP_UnitTestCase
{
	use WooCommerceFixtures;

	private \WC_Product $product;

	private int $vehicle_id;

	public function setUp(): void
	{
		parent::setUp();

		$this->require_woocommerce();

		$this->vehicle_id = self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_vehicle',
				'post_title'  => 'Fiat Egea',
				'post_status' => 'publish',
			)
		);
		update_post_meta($this->vehicle_id, '_mhmrentiva_price_per_day', '300');

		$this->product = $this->ensure_booking_product('100');

		wc_load_cart();
		WC()->cart->empty_cart();
		$GLOBALS['wp_styles'] = null;
	}

	public function tearDown(): void
	{
		if (function_exists('WC') && WC()->cart) {
			WC()->cart->empty_cart();
		}
		$GLOBALS['wp_styles'] = null;
		remove_all_filters('woocommerce_is_checkout');
		remove_all_filters('woocommerce_is_order_received_page');
		unset($GLOBALS['wp']->query_vars['order-received']);
		parent::tearDown();
	}

	/**
	 * What the booking form puts in the cart (BookingForm, add_booking_data_to_cart).
	 *
	 * @param array<string, mixed> $extra
	 * @return array<string, mixed>
	 */
	private function rental_data(array $extra = array()): array
	{
		return array_merge(
			array(
				'vehicle_id'       => $this->vehicle_id,
				'pickup_date'      => '2026-11-06',
				'pickup_time'      => '10:00',
				'dropoff_date'     => '2026-11-09',
				'dropoff_time'     => '10:00',
				'payment_type'     => 'deposit',
				'total_price'      => 1000.0,
				'deposit_amount'   => 200.0,
				'remaining_amount' => 800.0,
				'rental_days'      => 3,
				'addon_lines'      => array(
					array(
						'title'      => 'Child seat × 2',
						'line_total' => 120.0,
					),
				),
			),
			$extra
		);
	}

	/**
	 * @param array<string, mixed> $extra
	 * @return array<string, mixed>
	 */
	private function transfer_data(array $extra = array()): array
	{
		return $this->rental_data(
			array_merge(
				array(
					'booking_type'       => 'transfer',
					'transfer_origin_id' => 7,
				),
				$extra
			)
		);
	}

	/**
	 * @param array<string, mixed> $booking_data
	 */
	private function add_rental(array $booking_data): void
	{
		$key = WC()->cart->add_to_cart(
			$this->product->get_id(),
			1,
			0,
			array(),
			array(
				'mhmrentiva_booking_data'    => $booking_data,
				'mhmrentiva_booking_price'   => 200.0,
				'mhmrentiva_booking_pending' => true,
			)
		);
		$this->assertIsString($key, 'The rental could not be added to the cart.');
	}

	private function add_plain_product(): void
	{
		$product = new \WC_Product_Simple();
		$product->set_name('Sunglasses');
		$product->set_regular_price('25');
		$product->set_price('25');
		$product->save();

		$this->assertIsString(WC()->cart->add_to_cart($product->get_id(), 1), 'The product could not be added to the cart.');
	}

	private function checkout_html(): string
	{
		ob_start();
		CheckoutJourney::render_checkout();
		return (string) ob_get_clean();
	}

	private function thankyou_html(int $order_id): string
	{
		ob_start();
		CheckoutJourney::render_thankyou($order_id);
		return (string) ob_get_clean();
	}

	/**
	 * An order whose line items carry these `_mhmrentiva_booking_data` values
	 * (null: a plain product line), as checkout leaves it before the booking
	 * exists -- no `_mhmrentiva_booking_id` anywhere.
	 *
	 * @param array<int, mixed> $lines
	 */
	private function order_with_lines(array $lines, int $customer_id = 0): \WC_Order
	{
		$order = wc_create_order(array( 'customer_id' => $customer_id ));
		foreach ($lines as $meta) {
			$item = new \WC_Order_Item_Product();
			$item->set_product($this->product);
			$item->set_quantity(1);
			$item->set_total(200.0);
			if (null !== $meta) {
				$item->add_meta_data('_mhmrentiva_booking_data', $meta);
			}
			$order->add_item($item);
		}
		$order->calculate_totals();
		$order->save();

		return $order;
	}

	/**
	 * The 1-based position of the step item carrying aria-current="step", or 0.
	 */
	private static function current_step(string $html): int
	{
		preg_match_all('/<li class="rvs-steps__item[^"]*"([^>]*)>/', $html, $m);
		foreach ($m[1] as $i => $attrs) {
			if (str_contains($attrs, 'aria-current="step"')) {
				return $i + 1;
			}
		}

		return 0;
	}

	private static function plain(string $html): string
	{
		return trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(wp_strip_all_tags($html), ENT_QUOTES, 'UTF-8')));
	}

	private static function money(float $amount): string
	{
		return self::plain(\MHMRentiva\Admin\Booking\Pricing\PriceDisplay::for_cart($amount)['html']);
	}

	// ------------------------------------------------------------- checkout

	public function test_journey_skips_non_rental_carts(): void
	{
		// Empty cart.
		$this->assertNull(WooCommerceBridge::rental_cart_data());
		$this->assertSame('', $this->checkout_html());

		// A plain product only.
		$this->add_plain_product();
		$this->assertSame('', $this->checkout_html(), 'A product cart has no journey.');

		// A rental beside a product (Fable A1).
		$this->add_rental($this->rental_data());
		$this->assertNull(WooCommerceBridge::rental_cart_data());
		$this->assertSame('', $this->checkout_html(), 'A mixed cart has no journey.');

		// Two rentals.
		WC()->cart->empty_cart();
		$this->add_rental($this->rental_data());
		$this->add_rental($this->rental_data(array( 'pickup_date' => '2026-12-01' )));
		$this->assertNull(WooCommerceBridge::rental_cart_data());
		$this->assertSame('', $this->checkout_html(), 'Two rentals have no single journey.');

		// A transfer.
		WC()->cart->empty_cart();
		$this->add_rental($this->transfer_data());
		$this->assertNull(WooCommerceBridge::rental_cart_data());
		$this->assertSame('', $this->checkout_html(), 'A transfer is the add-on\'s surface.');

		// Control: one rental, nothing else.
		WC()->cart->empty_cart();
		$this->add_rental($this->rental_data());
		$this->assertIsArray(WooCommerceBridge::rental_cart_data());
		$this->assertNotSame('', $this->checkout_html());
	}

	public function test_checkout_summary_uses_cart_snapshot(): void
	{
		$this->add_rental($this->rental_data());

		$html = $this->checkout_html();
		$text = self::plain($html);

		$this->assertStringContainsString('rvs-shell', $html);
		$this->assertSame(2, self::current_step($html), 'The checkout is step 2.');
		$this->assertStringContainsString('Fiat Egea', $text);
		$this->assertStringContainsString('3 days', $text);
		$this->assertStringContainsString('Child seat × 2', $text);
		$this->assertStringContainsString(self::money(120.0), $text);
		// The rental row is the total less the add-ons, as the snapshot carries it.
		$this->assertStringContainsString('Rental, 3 days', $text);
		$this->assertStringContainsString(self::money(880.0), $text);
		$this->assertStringContainsString(self::money(1000.0), $text);
		$this->assertStringContainsString(self::money(200.0), $text, 'Deposit row.');
		$this->assertStringContainsString(self::money(800.0), $text, 'Due at pickup row.');

		// No re-pricing: a new daily price does not move the summary.
		update_post_meta($this->vehicle_id, '_mhmrentiva_price_per_day', '999');
		$this->assertSame($html, $this->checkout_html());
	}

	public function test_quote_from_snapshot_maps_the_snapshot(): void
	{
		$quote = CheckoutJourney::quote_from_snapshot($this->rental_data());

		$this->assertSame(3, $quote->days);
		$this->assertSame(array(), $quote->day_lines);
		$this->assertSame(0.0, $quote->daily_price);
		$this->assertSame(1.0, $quote->base_multiplier);
		$this->assertSame(1.0, $quote->weekend_multiplier);
		$this->assertSame(0, $quote->weekend_days);
		$this->assertSame(0.0, $quote->weekend_surcharge);
		$this->assertSame(120.0, $quote->addon_total);
		$this->assertSame(880.0, $quote->rental_amount);
		$this->assertSame(1000.0, $quote->full_amount);
	}

	public function test_checkout_summary_follows_payment_type(): void
	{
		$this->add_rental($this->rental_data(array( 'payment_type' => 'full' )));

		$html = $this->checkout_html();
		$this->assertStringNotContainsString('rvs-breakdown__deposit', $html);
		$this->assertStringNotContainsString('rvs-breakdown__pickup', $html);

		$fragments = CheckoutJourney::order_review_fragments(
			array( '.woocommerce-checkout-review-order-table' => '<table class="woocommerce-checkout-review-order-table"></table>' )
		);

		$this->assertArrayHasKey('.woocommerce-checkout-review-order-table', $fragments, 'WooCommerce\'s own fragments stay.');
		$this->assertArrayHasKey('.rvs-journey__summary', $fragments);
		$this->assertStringStartsWith('<div class="rvs-journey__summary"', $fragments['.rvs-journey__summary']);
		$this->assertStringNotContainsString('rvs-breakdown__deposit', $fragments['.rvs-journey__summary']);

		// Back to deposit: the fragment names the deposit again.
		WC()->cart->empty_cart();
		$this->add_rental($this->rental_data());
		$fragments = CheckoutJourney::order_review_fragments(array());
		$this->assertStringContainsString('rvs-breakdown__deposit', $fragments['.rvs-journey__summary']);

		// No rental: the fragments come back untouched.
		WC()->cart->empty_cart();
		$this->assertSame(array( 'a' => 'b' ), CheckoutJourney::order_review_fragments(array( 'a' => 'b' )));
	}

	public function test_checkout_assets_are_enqueued_before_head(): void
	{
		$this->add_rental($this->rental_data());
		$page = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => '[woocommerce_checkout]',
			)
		);
		$this->go_to(get_permalink($page));
		add_filter('woocommerce_is_checkout', '__return_true');
		add_action('wp_enqueue_scripts', array( WooCommerceBridge::class, 'enqueue_checkout_styles' ), 20);

		ob_start();
		wp_head();
		$head = (string) ob_get_clean();

		remove_action('wp_enqueue_scripts', array( WooCommerceBridge::class, 'enqueue_checkout_styles' ), 20);

		$this->assertTrue(wp_style_is('mhm-rentiva-rvs-forms', 'enqueued') || wp_style_is('mhm-rentiva-rvs-forms', 'done'));
		$this->assertTrue(wp_style_is('mhm-rentiva-rvs-booking', 'done'), 'The journey styles print in the head.');
		$this->assertMatchesRegularExpression('/<link[^>]+id=[\'"]mhm-rentiva-rvs-forms-css[\'"]/', $head);
	}

	public function test_checkout_assets_skip_a_non_rental_checkout(): void
	{
		$this->add_plain_product();
		add_filter('woocommerce_is_checkout', '__return_true');

		WooCommerceBridge::enqueue_checkout_styles();

		$this->assertFalse(wp_style_is('mhm-rentiva-rvs-forms', 'enqueued'));
	}

	// ------------------------------------------------------------- thank-you

	public function test_thankyou_reads_item_meta_before_booking_exists(): void
	{
		$order = $this->order_with_lines(array( $this->rental_data() ));
		$order->update_status('processing');
		$this->assertEmpty($order->get_meta('_mhmrentiva_booking_id'));

		$html = $this->thankyou_html($order->get_id());
		$text = self::plain($html);

		$this->assertSame(3, self::current_step($html), 'The thank-you page is step 3.');
		$this->assertStringContainsString('Your booking', $text);
		$this->assertStringContainsString('Fiat Egea', $text);
		$this->assertStringContainsString(self::money(1000.0), $text);

		// No rental meta, non-array meta, no order at all: nothing, and no notice.
		$this->assertSame('', $this->thankyou_html($this->order_with_lines(array( null ))->get_id()));
		$this->assertSame('', $this->thankyou_html($this->order_with_lines(array( 'not-an-array' ))->get_id()));
		$this->assertSame('', $this->thankyou_html(999999));
		$this->assertSame('', $this->thankyou_html(0));
	}

	public function test_thankyou_needs_exactly_one_rental_line(): void
	{
		$this->assertSame('', $this->thankyou_html($this->order_with_lines(array( $this->rental_data(), $this->rental_data() ))->get_id()), 'Two rentals.');
		$this->assertSame('', $this->thankyou_html($this->order_with_lines(array( $this->rental_data(), null ))->get_id()), 'Rental and product.');
		$this->assertSame('', $this->thankyou_html($this->order_with_lines(array( $this->transfer_data() ))->get_id()), 'Transfer.');
	}

	public function test_failed_order_does_not_mark_confirmation(): void
	{
		$order = $this->order_with_lines(array( $this->rental_data() ));
		$order->update_status('failed');

		$html = $this->thankyou_html($order->get_id());

		$this->assertSame(2, self::current_step($html), 'A failed payment is still on step 2.');
		$this->assertStringContainsString('rvs-journey__summary', $html);
	}

	public function test_my_bookings_link_only_for_the_logged_in_owner(): void
	{
		$url = wc_get_account_endpoint_url(AccountController::get_endpoint_slug('bookings', 'rentiva-bookings'));

		// Guest order, nobody logged in.
		wp_set_current_user(0);
		$guest = $this->order_with_lines(array( $this->rental_data() ));
		$html  = $this->thankyou_html($guest->get_id());
		$this->assertNotSame('', $html);
		$this->assertStringNotContainsString('My bookings', $html);

		// The owner.
		$owner = self::factory()->user->create(array( 'role' => 'customer' ));
		wp_set_current_user($owner);
		$own  = $this->order_with_lines(array( $this->rental_data() ), $owner);
		$html = $this->thankyou_html($own->get_id());
		$this->assertStringContainsString('My bookings', $html);
		$this->assertStringContainsString('href="' . esc_url($url) . '"', $html);

		// Someone else, logged in.
		wp_set_current_user(self::factory()->user->create(array( 'role' => 'customer' )));
		$this->assertStringNotContainsString('My bookings', $this->thankyou_html($own->get_id()));
		$this->assertStringNotContainsString('My bookings', $this->thankyou_html($guest->get_id()));
	}

	public function test_thankyou_assets_are_enqueued_for_a_rental_order(): void
	{
		$order = $this->order_with_lines(array( $this->rental_data() ));
		add_filter('woocommerce_is_order_received_page', '__return_true');
		$GLOBALS['wp']->query_vars['order-received'] = (string) $order->get_id();

		WooCommerceBridge::enqueue_checkout_styles();

		$this->assertTrue(wp_style_is('mhm-rentiva-rvs-booking', 'enqueued'));

		$GLOBALS['wp_styles']                       = null;
		$GLOBALS['wp']->query_vars['order-received'] = (string) $this->order_with_lines(array( null ))->get_id();
		WooCommerceBridge::enqueue_checkout_styles();
		$this->assertFalse(wp_style_is('mhm-rentiva-rvs-booking', 'enqueued'));
	}

	// ------------------------------------------------------------- admin notice

	private function notice_html(): string
	{
		ob_start();
		CheckoutJourney::block_checkout_notice();
		return (string) ob_get_clean();
	}

	private function use_checkout_page(string $content): void
	{
		$page = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => $content,
			)
		);
		update_option('woocommerce_checkout_page_id', $page);
	}

	public function test_block_checkout_warns_admin_only(): void
	{
		$warning = esc_html__("Rentiva's booking steps and payment-type choice work with the classic checkout. Your checkout page uses the Checkout block — replace it with the [woocommerce_checkout] shortcode.", 'mhm-rentiva');

		$this->use_checkout_page('<!-- wp:woocommerce/checkout /-->');

		wp_set_current_user(self::factory()->user->create(array( 'role' => 'administrator' )));
		set_current_screen('edit-mhmrentiva_vehicle');
		$this->assertStringContainsString($warning, $this->notice_html(), 'Rentiva screen.');

		set_current_screen('plugins');
		$this->assertStringContainsString($warning, $this->notice_html(), 'Plugins screen.');

		set_current_screen('dashboard');
		$this->assertSame('', $this->notice_html(), 'Not on unrelated screens.');

		set_current_screen('edit-mhmrentiva_vehicle');
		wp_set_current_user(self::factory()->user->create(array( 'role' => 'subscriber' )));
		$this->assertSame('', $this->notice_html(), 'Not for a subscriber.');

		wp_set_current_user(self::factory()->user->create(array( 'role' => 'administrator' )));
		$this->use_checkout_page('<!-- wp:shortcode -->[woocommerce_checkout]<!-- /wp:shortcode -->');
		$this->assertSame('', $this->notice_html(), 'A shortcode checkout needs no warning.');

		set_current_screen('front');
	}
}
