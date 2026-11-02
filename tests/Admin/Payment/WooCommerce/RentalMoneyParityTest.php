<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Payment\WooCommerce;

use MHMRentiva\Admin\Booking\Pricing\PriceDisplay;
use MHMRentiva\Admin\Core\MetaKeys;
use MHMRentiva\Admin\Payment\WooCommerce\CheckoutJourney;
use MHMRentiva\Admin\Payment\WooCommerce\RemainingPaymentHandler;
use MHMRentiva\Admin\Payment\WooCommerce\RentalMoneyLabels;
use MHMRentiva\Admin\Payment\WooCommerce\WooCommerceBridge;
use MHMRentiva\Admin\Settings\Core\SettingsSanitizer;
use MHMRentiva\Admin\Vehicle\Deposit\DepositCalculator;
use MHMRentiva\Tests\Support\WooCommerceFixtures;
use MHMRentiva\Tests\Support\WooCommerceOptionSandbox;
use WP_UnitTestCase;

/**
 * PR K, R7, R8, R11, r2 M7: one figure everywhere. The same booking is followed
 * from the form's quote through the cart, the checkout, the classic and Store
 * API order, the thank-you page, the customer email and the order-pay page of
 * the remaining amount.
 *
 * @covers \MHMRentiva\Admin\Payment\WooCommerce\RentalMoneyLabels
 * @covers \MHMRentiva\Admin\Payment\WooCommerce\CheckoutJourney
 * @covers \MHMRentiva\Admin\Payment\WooCommerce\WooCommerceBridge
 */
final class RentalMoneyParityTest extends WP_UnitTestCase
{
	use WooCommerceFixtures;
	use WooCommerceOptionSandbox;

	private int $vehicle_id;

	private int $customer_id;

	private int $tax_rate_id;

	/** @var list<array{0: string, 1: callable, 2: int}> */
	private array $hooks_added = array();

	public function setUp(): void
	{
		parent::setUp();
		$this->require_woocommerce();

		$this->vehicle_id  = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_vehicle', 'post_status' => 'publish', 'post_title' => 'Fiat Egea' ));
		$this->customer_id = (int) self::factory()->user->create(array( 'role' => 'customer' ));
		update_post_meta($this->vehicle_id, '_mhmrentiva_price_per_day', '1000');

		$this->ensure_booking_product('1');

		$this->sandbox_option('woocommerce_calc_taxes', 'yes');
		$this->sandbox_option('woocommerce_tax_based_on', 'base');
		$this->sandbox_option('woocommerce_default_customer_address', 'base');
		$this->sandbox_option('woocommerce_tax_display_shop', 'excl');
		$this->sandbox_option('woocommerce_tax_round_at_subtotal', 'no');

		$this->tax_rate_id = (int) \WC_Tax::_insert_tax_rate(
			array(
				'tax_rate_country'  => '',
				'tax_rate_state'    => '',
				'tax_rate'          => '20.0000',
				'tax_rate_name'     => 'VAT',
				'tax_rate_priority' => 1,
				'tax_rate_compound' => 0,
				'tax_rate_shipping' => 1,
				'tax_rate_order'    => 1,
				'tax_rate_class'    => '',
			)
		);

		wc_load_cart();
		WC()->cart->empty_cart();

		$this->hook('woocommerce_before_calculate_totals', array( WooCommerceBridge::class, 'calculate_totals' ), 10, 1);
		$this->hook('woocommerce_checkout_create_order_line_item', array( WooCommerceBridge::class, 'add_order_item_meta' ), 10, 4);
		$this->hook('woocommerce_store_api_checkout_order_processed', array( WooCommerceBridge::class, 'store_api_order_processed' ), 10, 1);
		$this->hook('woocommerce_before_cart_totals', array( RentalMoneyLabels::class, 'render_cart_card' ), 10, 0);
		$this->hook('woocommerce_before_thankyou', array( CheckoutJourney::class, 'render_thankyou' ), 10, 1);
		$this->hook('woocommerce_before_thankyou', array( RentalMoneyLabels::class, 'open_thankyou_window' ), 20, 1);
		$this->hook('woocommerce_thankyou', array( RentalMoneyLabels::class, 'close_window' ), 0, 0);
	}

	public function tearDown(): void
	{
		foreach ($this->hooks_added as $hook) {
			remove_filter($hook[0], $hook[1], $hook[2]);
		}
		RentalMoneyLabels::close_window();
		remove_all_filters('woocommerce_get_price_suffix');
		remove_all_filters('woocommerce_is_order_received_page');
		if (function_exists('WC') && WC()->cart) {
			WC()->cart->empty_cart();
		}
		parent::tearDown();

		// Locker::withBookingLock() commits the test's transaction, so the rollback
		// above brings the tax rate and the store options back: undo them after it,
		// for good.
		\WC_Tax::_delete_tax_rate($this->tax_rate_id);
		$this->restore_sandboxed_options();
		$GLOBALS['wpdb']->query('COMMIT');
	}

	private function hook(string $name, callable $callback, int $priority, int $args): void
	{
		if (false === has_filter($name, $callback)) {
			add_filter($name, $callback, $priority, $args);
			$this->hooks_added[] = array( $name, $callback, $priority );
		}
	}

	private function set_store(string $prices_include_tax, string $display, int $rate): void
	{
		$this->sandbox_option('woocommerce_prices_include_tax', $prices_include_tax);
		$this->sandbox_option('woocommerce_tax_display_cart', $display);
		$this->sandbox_option('woocommerce_price_display_suffix', '');

		$settings = SettingsSanitizer::sanitize(array( 'current_active_tab' => 'vehicle' ));
		$settings['mhmrentiva_rental_deposit_type'] = $rate > 0 ? 'percentage' : 'none';
		$settings['mhmrentiva_rental_deposit_rate'] = $rate;
		$this->sandbox_option('mhmrentiva_settings', $settings);
		update_post_meta($this->vehicle_id, '_mhmrentiva_deposit', 0 === $rate ? '0' : (string) $rate);
	}

	/**
	 * @return array<int>
	 */
	private function addon(float $price): array
	{
		$id = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_addon', 'post_status' => 'publish', 'post_title' => 'Child seat' ));
		update_post_meta($id, 'mhmrentiva_addon_price', (string) $price);
		update_post_meta($id, '_mhmrentiva_addon_pricing_type', 'flat');
		update_post_meta($id, 'mhmrentiva_addon_enabled', '1');
		update_post_meta($id, MetaKeys::ADDON_MAX_QUANTITY, '3');

		return array( $id );
	}

	/**
	 * What BookingForm hands to the gateway.
	 *
	 * @param array<int>      $addons
	 * @param array<int, int> $quantities
	 * @return array{0: array<string, mixed>, 1: float}
	 */
	private function booking(string $type, array $addons = array(), array $quantities = array()): array
	{
		$quote = DepositCalculator::calculate_booking_deposit($this->vehicle_id, 3, $type, $addons, 0, $quantities);
		$this->assertTrue($quote['success']);

		$snapshot = array(
			'vehicle_id'          => $this->vehicle_id,
			'pickup_date'         => '2031-03-10',
			'pickup_time'         => '10:00',
			'dropoff_date'        => '2031-03-13',
			'dropoff_time'        => '10:00',
			'guests'              => 2,
			'customer_user_id'    => $this->customer_id,
			'customer_name'       => 'Jane Customer',
			'customer_first_name' => 'Jane',
			'customer_last_name'  => 'Customer',
			'customer_email'      => 'jane@example.com',
			'customer_phone'      => '5551234567',
			'payment_type'        => $quote['payment_type'],
			'deposit_amount'      => $quote['deposit_amount'],
			'remaining_amount'    => $quote['remaining_amount'],
			'deposit_type'        => $quote['deposit_type'],
			'deposit_percentage'  => $quote['deposit_percentage'],
			'payment_display'     => $quote['payment_display'],
			'total_price'         => $quote['total_amount'],
			'rental_days'         => 3,
			'selected_addons'     => $addons,
			'addon_quantities'    => $quantities,
		);

		return array( $snapshot, 'deposit' === $quote['payment_type'] ? (float) $quote['deposit_amount'] : (float) $quote['total_amount'] );
	}

	private function capture(callable $draw): string
	{
		ob_start();
		$draw();

		return (string) ob_get_clean();
	}

	private function money(float $amount): string
	{
		return $this->plain(PriceDisplay::for_cart($amount)['html']);
	}

	private function plain(string $html): string
	{
		$text = html_entity_decode(wp_strip_all_tags($html), ENT_QUOTES, 'UTF-8');

		return (string) preg_replace('/\s+/u', ' ', str_replace("\xC2\xA0", ' ', $text));
	}

	private function classic_order(): \WC_Order
	{
		$order_id = WC()->checkout()->create_order(
			array(
				'payment_method'     => 'bacs',
				'billing_first_name' => 'Jane',
				'billing_last_name'  => 'Customer',
				'billing_email'      => 'jane@example.com',
				'billing_phone'      => '5551234567',
				'billing_country'    => 'TR',
			)
		);
		$this->assertIsInt($order_id, 'create_order() failed.');

		return wc_get_order($order_id);
	}

	/**
	 * @return array<string, array{0: string, 1: string, 2: int, 3: string, 4: bool}>
	 */
	public static function stores(): array
	{
		return array(
			'net prices, shown with tax, deposit'     => array( 'no', 'incl', 10, 'deposit', false ),
			'net prices, shown without tax, deposit'  => array( 'no', 'excl', 10, 'deposit', false ),
			'gross prices, shown with tax, deposit'   => array( 'yes', 'incl', 10, 'deposit', false ),
			'gross prices, shown without tax, deposit' => array( 'yes', 'excl', 10, 'deposit', false ),
			'gross prices, shown with tax, full'      => array( 'yes', 'incl', 10, 'full', false ),
			'net prices, shown without tax, full'     => array( 'no', 'excl', 10, 'full', false ),
			'no deposit'                              => array( 'yes', 'incl', 0, 'deposit', false ),
			'add-on with quantity, gross'             => array( 'yes', 'incl', 10, 'deposit', true ),
			'add-on with quantity, net, excl'         => array( 'no', 'excl', 10, 'deposit', true ),
		);
	}

	/**
	 * @dataProvider stores
	 */
	public function test_one_figure_from_the_form_to_the_customer_email(string $includes, string $display, int $rate, string $type, bool $with_addon): void
	{
		$this->set_store($includes, $display, $rate);
		$addons     = $with_addon ? $this->addon(25.0) : array();
		$quantities = $with_addon ? array( $addons[0] => 2 ) : array();

		list( $snapshot, $charge ) = $this->booking($type, $addons, $quantities);
		$is_split  = 'deposit' === $snapshot['payment_type'];
		$remaining = (float) $snapshot['remaining_amount'];
		$now       = $this->money($charge);
		$total     = $this->money( (float) $snapshot['total_price']);

		if ($with_addon) {
			$this->assertSame(3050.0, (float) $snapshot['total_price'], 'The add-on is in the total, quantity included.');
		}

		// Cart.
		$this->assertTrue(WooCommerceBridge::add_booking_data_to_cart($snapshot, $charge));
		WC()->cart->calculate_totals();
		$charged = (float) WC()->cart->get_total('edit');
		$line_shown = 'excl' === $display ? (float) WC()->cart->get_subtotal() : (float) WC()->cart->get_subtotal() + (float) WC()->cart->get_subtotal_tax();
		$this->assertSame(PriceDisplay::for_cart($charge)['amount'], round($line_shown, 2), 'What the cart shows for the line is the figure the form quoted.');

		$card = $this->plain($this->capture(array( RentalMoneyLabels::class, 'render_cart_card' )));
		$this->assertStringContainsString($total, $card);
		if ($is_split) {
			$this->assertStringContainsString($now, $card);
			$this->assertStringContainsString($this->money($remaining), $card);
			$this->assertStringContainsString('Deposit (' . \MHMRentiva\Admin\Core\CurrencyHelper::format_percentage( (float) $rate) . ')', $card);
		} else {
			$this->assertStringNotContainsString('Deposit', $card);
			$this->assertStringNotContainsString('Due at pickup', $card);
		}
		if ($with_addon) {
			$this->assertStringContainsString('Child seat × 2', $card);
			$this->assertStringContainsString($this->money(50.0), $card);
		}

		$cart_item = current(WC()->cart->get_cart());
		$rows      = $this->plain( (string) wp_json_encode(WooCommerceBridge::get_item_data(array(), $cart_item), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
		if ($is_split) {
			$this->assertStringContainsString($this->money($remaining), $rows, 'The cart line remaining goes through PriceDisplay.');
		}

		// Checkout.
		$options = $this->plain($this->capture(array( WooCommerceBridge::class, 'display_payment_type_field' )));
		$this->assertStringContainsString($total, $options);
		if ($is_split) {
			$this->assertStringContainsString($now, $options);
			$this->assertStringContainsString($this->money($remaining), $options);
		}
		$journey = $this->plain($this->capture(array( CheckoutJourney::class, 'render_checkout' )));
		$this->assertStringContainsString($total, $journey);
		$this->assertSame($is_split, str_contains($journey, 'Due at pickup'));

		// Order. Both routes meet here: Store API books the same order.
		$order = $this->classic_order();
		$this->assertSame($charged, (float) $order->get_total(), 'The order total is the cart total.');

		do_action('woocommerce_store_api_checkout_order_processed', $order);
		$order      = wc_get_order($order->get_id());
		$booking_id = (int) $order->get_meta('_mhmrentiva_booking_id');
		$this->assertGreaterThan(0, $booking_id);
		$this->assertSame($charged, (float) $order->get_total(), 'Store API order: nothing moved.');
		$this->assertSame($remaining, (float) get_post_meta($booking_id, '_mhmrentiva_remaining_amount', true));
		$this->assertSame( (float) $snapshot['total_price'], (float) get_post_meta($booking_id, '_mhmrentiva_total_price', true));

		// Thank-you.
		$order->set_status('on-hold');
		$order->save();
		add_filter('woocommerce_is_order_received_page', '__return_true');
		$thanks = $this->plain($this->capture(static fn() => wc_get_template('checkout/thankyou.php', array( 'order' => $order ))));
		remove_filter('woocommerce_is_order_received_page', '__return_true');
		$this->assertStringContainsString($total, $thanks);
		$this->assertStringContainsString($this->plain(wc_price($charged)), $thanks);
		if ($is_split) {
			$this->assertStringContainsString($now, $thanks);
			$this->assertStringContainsString($this->money($remaining), $thanks);
		}

		// Customer email.
		$body = $this->plain(
			wc_get_template_html(
				'emails/email-order-details.php',
				array(
					'order'         => $order,
					'sent_to_admin' => false,
					'plain_text'    => false,
					'email'         => WC()->mailer()->get_emails()['WC_Email_Customer_On_Hold_Order'],
				)
			)
		);
		$this->assertStringContainsString($this->plain(wc_price($charged)), $body);
		if ($is_split) {
			$this->assertStringContainsString($this->money($remaining), $body, 'The order line remaining in the email.');
		}

		// Order-pay of the remaining amount.
		if ($is_split) {
			$pay = RemainingPaymentHandler::get_or_create_remaining_order($booking_id);
			$this->assertInstanceOf(\WC_Order::class, $pay);
			$pay_shown = 'excl' === $display ? (float) $pay->get_subtotal() : (float) $pay->get_total();
			$this->assertEqualsWithDelta(PriceDisplay::for_cart($remaining)['amount'], $pay_shown, 0.001, 'The remaining payment charges the figure shown.');
		}
	}

	public function test_a_price_suffix_stays_on_the_total_and_the_cart_card_shows_it(): void
	{
		$this->set_store('yes', 'incl', 10);
		update_option('woocommerce_price_display_suffix', 'VAT incl.');

		list( $snapshot, $charge ) = $this->booking('deposit');
		WooCommerceBridge::add_booking_data_to_cart($snapshot, $charge);
		WC()->cart->calculate_totals();

		$card = $this->capture(array( RentalMoneyLabels::class, 'render_cart_card' ));

		$this->assertMatchesRegularExpression('/rvs-breakdown__suffix[^>]*>.*VAT incl\./s', $card);
		$this->assertSame(1, substr_count($card, 'VAT incl.'), 'Only the total carries the suffix.');
		$this->assertSame(PriceDisplay::for_cart($charge)['amount'], (float) WC()->cart->get_total('edit'), 'A suffix moves no figure.');
	}

	public function test_a_full_payment_after_a_deposit_quote_leaves_no_remaining_anywhere(): void
	{
		$this->set_store('yes', 'incl', 10);

		list( $snapshot ) = $this->booking('deposit');
		$snapshot['payment_type']     = 'full';
		$snapshot['remaining_amount'] = 0;
		WooCommerceBridge::add_booking_data_to_cart($snapshot, (float) $snapshot['total_price']);
		WC()->cart->calculate_totals();

		$card = $this->capture(array( RentalMoneyLabels::class, 'render_cart_card' ));

		$this->assertStringNotContainsString('rvs-breakdown__deposit', $card);
		$this->assertStringNotContainsString('rvs-breakdown__pickup', $card);
		$this->assertSame( (float) $snapshot['total_price'], (float) WC()->cart->get_total('edit'));
	}

	public function test_the_rate_on_the_snapshot_survives_a_change_of_the_setting(): void
	{
		$this->set_store('yes', 'incl', 10);
		list( $snapshot, $charge ) = $this->booking('deposit');
		WooCommerceBridge::add_booking_data_to_cart($snapshot, $charge);

		$this->set_store('yes', 'incl', 25);
		WC()->cart->calculate_totals();

		$card = $this->capture(array( RentalMoneyLabels::class, 'render_cart_card' ));

		$this->assertStringContainsString('Deposit (' . \MHMRentiva\Admin\Core\CurrencyHelper::format_percentage(10.0) . ')', $card);
		$this->assertStringContainsString($this->money($charge), $this->plain($card));
	}
}
