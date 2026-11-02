<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Payment\WooCommerce;

use MHMRentiva\Admin\Core\CurrencyHelper;
use MHMRentiva\Admin\Payment\WooCommerce\CheckoutJourney;
use MHMRentiva\Admin\Payment\WooCommerce\RentalMoneyLabels;
use MHMRentiva\Admin\Payment\WooCommerce\WooCommerceBridge;
use MHMRentiva\Tests\Support\WooCommerceFixtures;
use WP_UnitTestCase;

/**
 * PR K, R6-R9, R11, r2 M1, M2, M4: what the money rows are called. The label
 * follows the rental and the order's state; emails, My Account and the
 * order-pay form keep WooCommerce's words. The real templates are drawn.
 *
 * @covers \MHMRentiva\Admin\Payment\WooCommerce\RentalMoneyLabels
 * @covers \MHMRentiva\Admin\Payment\WooCommerce\CheckoutJourney
 */
final class RentalMoneyLabelsTest extends WP_UnitTestCase
{
	use WooCommerceFixtures;

	private \WC_Product $product;

	private int $vehicle_id;

	/** @var list<array{0: string, 1: callable, 2: int}> */
	private array $hooks_added = array();

	public function setUp(): void
	{
		parent::setUp();

		$this->require_woocommerce();
		$this->product    = $this->ensure_booking_product('100');
		$this->vehicle_id = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_vehicle', 'post_status' => 'publish', 'post_title' => 'Fiat Egea' ));

		wc_load_cart();
		WC()->cart->empty_cart();

		$this->hook('woocommerce_before_cart_totals', array( RentalMoneyLabels::class, 'render_cart_card' ), 10, 0);
		$this->hook('woocommerce_cart_totals_before_order_total', array( RentalMoneyLabels::class, 'open_cart_window' ), 10, 0);
		$this->hook('woocommerce_cart_totals_after_order_total', array( RentalMoneyLabels::class, 'close_window' ), 10, 0);
		$this->hook('woocommerce_review_order_before_order_total', array( RentalMoneyLabels::class, 'open_cart_window' ), 10, 0);
		$this->hook('woocommerce_review_order_after_order_total', array( RentalMoneyLabels::class, 'close_window' ), 10, 0);
		$this->hook('woocommerce_before_thankyou', array( CheckoutJourney::class, 'render_thankyou' ), 10, 1);
		$this->hook('woocommerce_before_thankyou', array( RentalMoneyLabels::class, 'open_thankyou_window' ), 20, 1);
		$this->hook('woocommerce_thankyou', array( RentalMoneyLabels::class, 'close_window' ), 0, 0);
		$this->hook('woocommerce_order_details_before_order_table', array( RentalMoneyLabels::class, 'open_order_details_window' ), 10, 1);
		$this->hook('woocommerce_order_details_after_order_table', array( RentalMoneyLabels::class, 'close_order_details_window' ), 10, 0);
	}

	public function tearDown(): void
	{
		foreach ($this->hooks_added as $hook) {
			remove_filter($hook[0], $hook[1], $hook[2]);
		}
		RentalMoneyLabels::close_window();
		RentalMoneyLabels::close_order_details_window();
		remove_all_filters('woocommerce_is_order_received_page');
		if (function_exists('WC') && WC()->cart) {
			WC()->cart->empty_cart();
		}
		parent::tearDown();
	}

	private function hook(string $name, callable $callback, int $priority, int $args): void
	{
		if (false === has_filter($name, $callback)) {
			add_filter($name, $callback, $priority, $args);
			$this->hooks_added[] = array( $name, $callback, $priority );
		}
	}

	/**
	 * @param array<string, mixed> $extra
	 * @return array<string, mixed>
	 */
	private function snapshot(array $extra = array()): array
	{
		return array_merge(
			array(
				'vehicle_id'         => $this->vehicle_id,
				'pickup_date'        => '2031-03-10',
				'pickup_time'        => '10:00',
				'dropoff_date'       => '2031-03-13',
				'dropoff_time'       => '10:00',
				'payment_type'       => 'deposit',
				'deposit_amount'     => 100.0,
				'remaining_amount'   => 900.0,
				'deposit_percentage' => 10.0,
				'total_price'        => 1000.0,
				'rental_days'        => 3,
				'selected_addons'    => array(),
			),
			$extra
		);
	}

	/**
	 * @param array<string, mixed> $snapshot
	 */
	private function add_rental(array $snapshot, float $price = 100.0): void
	{
		$key = WC()->cart->add_to_cart(
			$this->product->get_id(),
			1,
			0,
			array(),
			array(
				'mhmrentiva_booking_data'    => $snapshot,
				'mhmrentiva_booking_price'   => $price,
				'mhmrentiva_booking_pending' => true,
			)
		);
		$this->assertIsString($key);
	}

	private function add_shop_product(): void
	{
		$product = new \WC_Product_Simple();
		$product->set_name('Sunglasses');
		$product->set_regular_price('25');
		$product->save();

		$this->assertIsString(WC()->cart->add_to_cart($product->get_id(), 1));
	}

	/**
	 * @param array<string, mixed> $snapshot
	 */
	private function order(array $snapshot, string $method, string $status, float $total = 100.0): \WC_Order
	{
		$order = wc_create_order();
		$item  = new \WC_Order_Item_Product();
		$item->set_product($this->product);
		$item->set_quantity(1);
		$item->set_subtotal($total);
		$item->set_total($total);
		$item->add_meta_data('_mhmrentiva_booking_data', $snapshot);
		$order->add_item($item);
		$order->set_billing_email('jane@example.com');
		$order->set_payment_method($method);
		$order->calculate_totals();
		$order->set_status($status);
		$order->save();

		return wc_get_order($order->get_id());
	}

	private function capture(callable $draw): string
	{
		ob_start();
		$draw();

		return (string) ob_get_clean();
	}

	private function cart_totals(): string
	{
		WC()->cart->calculate_totals();

		return $this->capture(static fn() => wc_get_template('cart/cart-totals.php'));
	}

	private function review_order(): string
	{
		WC()->cart->calculate_totals();

		return $this->capture(static fn() => wc_get_template('checkout/review-order.php'));
	}

	private function thankyou(\WC_Order $order): string
	{
		add_filter('woocommerce_is_order_received_page', '__return_true');

		return $this->capture(static fn() => wc_get_template('checkout/thankyou.php', array( 'order' => $order )));
	}

	private function assert_no_window_left(): void
	{
		$this->assertSame('Total', __('Total', 'woocommerce'), 'The gettext window stayed open.');
		$this->assertSame('Total:', __('Total:', 'woocommerce'), 'The gettext window stayed open.');
		$this->assertFalse(has_filter('gettext_woocommerce', array( RentalMoneyLabels::class, 'relabel' )));
		$this->assertFalse(has_filter('woocommerce_get_order_item_totals', array( RentalMoneyLabels::class, 'relabel_order_total' )));
	}

	public function test_a_rental_cart_says_due_now_only_on_the_total_row(): void
	{
		$this->add_rental($this->snapshot());

		$html = $this->cart_totals();

		$this->assertMatchesRegularExpression('/order-total">\s*<th>Due now<\/th>\s*<td data-title="Due now">/', $html);
		$this->assertStringContainsString('<th>Subtotal</th>', $html);
		$this->assert_no_window_left();
	}

	public function test_the_checkout_review_says_due_now_also_when_redrawn_twice(): void
	{
		$this->add_rental($this->snapshot());

		foreach (array( 1, 2 ) as $round) {
			$this->assertMatchesRegularExpression('/order-total">\s*<th>Due now<\/th>/', $this->review_order(), 'Round ' . $round);
			$this->assert_no_window_left();
		}
	}

	public function test_other_carts_keep_the_word_total(): void
	{
		$carts = array(
			'product'  => function (): void {
				$this->add_shop_product();
			},
			'mixed'    => function (): void {
				$this->add_rental($this->snapshot());
				$this->add_shop_product();
			},
			'transfer' => function (): void {
				$this->add_rental($this->snapshot(array( 'booking_type' => 'transfer', 'transfer_origin_id' => 7 )));
			},
		);

		foreach ($carts as $name => $fill) {
			WC()->cart->empty_cart();
			$fill();

			foreach (array( $this->cart_totals(), $this->review_order() ) as $html) {
				$this->assertMatchesRegularExpression('/order-total">\s*<th>Total<\/th>/', $html, $name);
				$this->assertStringNotContainsString('Due now', $html, $name);
				$this->assertStringNotContainsString('rvs-cart-totals-card', $html, $name);
			}
		}
	}

	public function test_the_cart_card_shows_the_breakdown_of_the_snapshot(): void
	{
		$this->add_rental($this->snapshot());

		$html = $this->cart_totals();

		$this->assertStringContainsString('rvs-cart-totals-card', $html);
		$this->assertStringContainsString('Deposit (' . CurrencyHelper::format_percentage(10.0) . ')', $html);
		$this->assertStringContainsString('Due at pickup', $html);
		$this->assertLessThan(strpos($html, 'class="order-total"'), strpos($html, 'rvs-breakdown__total'));
	}

	/**
	 * @return array<string, array{0: string, 1: string, 2: string, 3: string, 4: string, 5: bool}>
	 */
	public static function thankyou_matrix(): array
	{
		$card = 'mhm_polar_sandbox';

		return array(
			'bacs on hold, deposit'    => array( 'bacs', 'on-hold', 'deposit', 'Deposit to send (%s)', 'Deposit to send:', false ),
			'cheque on hold, deposit'  => array( 'cheque', 'on-hold', 'deposit', 'Deposit to send (%s)', 'Deposit to send:', false ),
			'cod processing, deposit'  => array( 'cod', 'processing', 'deposit', 'Deposit due at pickup (%s)', 'Deposit due at pickup:', false ),
			'card processing, deposit' => array( $card, 'processing', 'deposit', 'Deposit paid (%s)', 'Paid:', true ),
			'card pending, deposit'    => array( $card, 'pending', 'deposit', 'Deposit (%s)', 'Total:', false ),
			'bacs on hold, full'       => array( 'bacs', 'on-hold', 'full', '', 'To send:', false ),
			'cod processing, full'     => array( 'cod', 'processing', 'full', '', 'Due at pickup:', false ),
			'card processing, full'    => array( $card, 'processing', 'full', '', 'Paid:', true ),
			'card pending, full'       => array( $card, 'pending', 'full', '', 'Total:', false ),
		);
	}

	/**
	 * @dataProvider thankyou_matrix
	 */
	public function test_the_thankyou_page_says_how_the_money_stands(string $method, string $status, string $type, string $deposit_label, string $total_label, bool $paid): void
	{
		$snapshot = 'full' === $type ? $this->snapshot(array( 'payment_type' => 'full', 'remaining_amount' => 0.0 )) : $this->snapshot();
		$order    = $this->order($snapshot, $method, $status, 'full' === $type ? 1000.0 : 100.0);

		$html = $this->thankyou($order);

		$this->assertMatchesRegularExpression('/woocommerce-order-overview__total total">\s*' . preg_quote($total_label, '/') . '/', $html);
		$this->assertMatchesRegularExpression('/<th scope="row">' . preg_quote($total_label, '/') . '<\/th>/', $html, 'The order table.');
		if ('' !== $deposit_label) {
			$this->assertStringContainsString(sprintf($deposit_label, CurrencyHelper::format_percentage(10.0)), $html);
		} else {
			$this->assertStringNotContainsString('rvs-breakdown__deposit', $html);
		}
		if (! $paid) {
			$this->assertStringNotContainsString('Paid:', $html);
			$this->assertStringNotContainsString('Deposit paid', $html);
			$this->assertStringNotContainsString('rvs-breakdown__deposit--paid', $html);
		} elseif ('' !== $deposit_label) {
			$this->assertStringContainsString('rvs-breakdown__deposit--paid', $html);
		}
		$this->assert_no_window_left();
	}

	public function test_a_failed_order_never_says_paid_or_to_send(): void
	{
		foreach (array( 'bacs', 'cod', 'mhm_polar_sandbox' ) as $method) {
			$html = $this->thankyou($this->order($this->snapshot(), $method, 'failed'));

			$this->assertStringContainsString('woocommerce-thankyou-order-failed', $html);
			$this->assertMatchesRegularExpression('/<th scope="row">Total:<\/th>/', $html, $method);
			$this->assertStringContainsString('Deposit (' . CurrencyHelper::format_percentage(10.0) . ')', $html, $method);
			foreach (array( 'Paid:', 'Deposit paid', 'to send', 'due at pickup' ) as $word) {
				$this->assertStringNotContainsString($word, $html, $method . ': ' . $word);
			}
			$this->assert_no_window_left();
		}
	}

	public function test_a_shop_order_keeps_its_labels_on_the_thankyou_page(): void
	{
		$product = new \WC_Product_Simple();
		$product->set_regular_price('25');
		$product->save();
		$order = wc_create_order();
		$order->add_product($product, 1);
		$order->set_payment_method('bacs');
		$order->calculate_totals();
		$order->set_status('on-hold');
		$order->save();

		$html = $this->thankyou($order);

		$this->assertMatchesRegularExpression('/woocommerce-order-overview__total total">\s*Total:/', $html);
		$this->assertMatchesRegularExpression('/<th scope="row">Total:<\/th>/', $html);
	}

	public function test_a_mixed_order_keeps_its_labels_on_the_thankyou_page(): void
	{
		$order   = $this->order($this->snapshot(), 'mhm_polar_sandbox', 'processing');
		$product = new \WC_Product_Simple();
		$product->set_regular_price('25');
		$product->save();
		$order->add_product($product, 1);
		$order->calculate_totals();
		$order->save();

		$html = $this->thankyou(wc_get_order($order->get_id()));

		$this->assertStringNotContainsString('Paid:', $html);
	}

	public function test_emails_keep_woocommerces_words_even_on_the_thankyou_request(): void
	{
		add_filter('woocommerce_is_order_received_page', '__return_true');

		foreach (array( array( 'bacs', 'on-hold', 'WC_Email_Customer_On_Hold_Order' ), array( 'mhm_polar_sandbox', 'processing', 'WC_Email_Customer_Processing_Order' ) ) as $case) {
			$order = $this->order($this->snapshot(), $case[0], $case[1]);
			$args  = array(
				'order'         => $order,
				'sent_to_admin' => false,
				'email'         => WC()->mailer()->get_emails()[ $case[2] ],
			);

			foreach (array( wc_get_template_html('emails/email-order-details.php', $args + array( 'plain_text' => false )), wc_get_template_html('emails/plain/email-order-details.php', $args + array( 'plain_text' => true )) ) as $body) {
				$this->assertStringContainsString('Total:', $body, $case[2]);
				foreach (array( 'Paid:', 'Deposit paid', 'to send:', 'Due now' ) as $word) {
					$this->assertStringNotContainsString($word, $body, $case[2]);
				}
			}
		}
	}

	public function test_my_account_keeps_woocommerces_words(): void
	{
		$order = $this->order($this->snapshot(), 'mhm_polar_sandbox', 'processing');

		$html = $this->capture(static fn() => woocommerce_order_details_table($order->get_id()));

		$this->assertMatchesRegularExpression('/<th scope="row">Total:<\/th>/', $html);
		$this->assertStringNotContainsString('Paid:', $html);
	}

	public function test_the_order_pay_form_keeps_woocommerces_words(): void
	{
		$order = $this->order($this->snapshot(), 'bacs', 'pending');

		$html = $this->capture(
			static fn() => wc_get_template(
				'checkout/form-pay.php',
				array(
					'order'              => $order,
					'available_gateways' => array(),
					'order_button_text'  => 'Pay',
				)
			)
		);

		$this->assertStringContainsString('Total:', $html);
		$this->assertStringNotContainsString('Due now', $html);
		$this->assertStringNotContainsString('Paid:', $html);
	}

	public function test_the_deposit_rate_comes_from_the_snapshot_not_from_division(): void
	{
		$breakdown = CheckoutJourney::breakdown_of($this->snapshot(array( 'deposit_amount' => 99.8 )));
		$this->assertSame('Deposit (' . CurrencyHelper::format_percentage(10.0) . ')', $breakdown['deposit']['label']);

		$no_rate = $this->snapshot(array( 'deposit_amount' => 99.8 ));
		unset($no_rate['deposit_percentage']);
		$this->assertSame('Deposit', CheckoutJourney::breakdown_of($no_rate)['deposit']['label']);
	}

	public function test_a_paid_deposit_label_needs_no_rate(): void
	{
		$this->assertSame('Deposit paid', RentalMoneyLabels::deposit_label(RentalMoneyLabels::STATE_PAID, 0.0));
		$this->assertSame('Deposit to send', RentalMoneyLabels::deposit_label(RentalMoneyLabels::STATE_TO_SEND, 0.0));
		$this->assertSame('Deposit due at pickup', RentalMoneyLabels::deposit_label(RentalMoneyLabels::STATE_AT_PICKUP, 0.0));
	}

	public function test_the_payment_type_card_is_an_rvs_option_with_its_hook(): void
	{
		$this->add_rental($this->snapshot());
		WC()->cart->calculate_totals();

		$html = $this->capture(array( WooCommerceBridge::class, 'display_payment_type_field' ));

		$this->assertStringContainsString('<h3>', $html);
		$this->assertSame(2, substr_count($html, 'data-rvs-payment-option'));
		$this->assertSame(2, substr_count($html, 'class="rvs-option '));
		$this->assertStringNotContainsString('mhm-payment-type-option', $html);
		$this->assertStringContainsString('<bdi>', $html, 'The amounts keep wc_price() markup.');
		$this->assertMatchesRegularExpression('/rvs-option--selected" data-rvs-payment-option>\s*<input type="radio"\s+name="mhmrentiva_booking_payment_type"\s+value="deposit"/', $html);
	}

	public function test_without_a_deposit_only_full_payment_is_offered(): void
	{
		$this->add_rental($this->snapshot(array( 'payment_type' => 'full', 'deposit_amount' => 1000.0, 'remaining_amount' => 0.0, 'deposit_percentage' => 0 )), 1000.0);
		WC()->cart->calculate_totals();

		$html = $this->capture(array( WooCommerceBridge::class, 'display_payment_type_field' ));

		$this->assertSame(1, substr_count($html, 'data-rvs-payment-option'));
		$this->assertStringNotContainsString('value="deposit"', $html);
		$this->assertMatchesRegularExpression('/value="full"\s+checked=/', $html);
	}
}
