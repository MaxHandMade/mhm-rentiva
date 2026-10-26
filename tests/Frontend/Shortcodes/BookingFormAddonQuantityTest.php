<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use MHMRentiva\Admin\Core\MetaKeys;
use MHMRentiva\Admin\Frontend\Shortcodes\BookingForm;
use MHMRentiva\Admin\Payment\WooCommerce\WooCommerceBridge;
use MHMRentiva\Tests\Support\WooCommerceFixtures;
use MHMRentiva\Tests\Support\WooCommerceOptionSandbox;
use ReflectionMethod;
use WP_Ajax_UnitTestCase;

/**
 * Aşama 2 PR G (K12): an add-on taken more than once, on the public path.
 *
 * The form posts `addon_qty[<id>]` beside the ticked ids; the quote, the cart,
 * the order line and the stored booking must all carry the same quantity and
 * the same money.
 *
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\BookingForm::ajax_booking_form
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\BookingForm::ajax_calculate_price
 * @covers \MHMRentiva\Admin\Payment\WooCommerce\WooCommerceBridge::add_booking_data_to_cart
 * @covers \MHMRentiva\Admin\Addons\AddonManager::save_booking_addons
 */
final class BookingFormAddonQuantityTest extends WP_Ajax_UnitTestCase
{
	use WooCommerceFixtures;
	use WooCommerceOptionSandbox;

	/** @var string */
	protected $_last_response;

	private int $vehicle_id;

	/** @var array<string, mixed> */
	private array $settings = array();

	public function setUp(): void
	{
		parent::setUp();
		$this->require_woocommerce();
		BookingForm::register();

		$this->vehicle_id = (int) self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_vehicle',
				'post_status' => 'publish',
				'post_title'  => 'Quantity Vehicle',
			)
		);
		update_post_meta($this->vehicle_id, '_mhmrentiva_price_per_day', '1000');
		update_post_meta($this->vehicle_id, '_mhmrentiva_vehicle_status', 'active');

		$this->sandbox_option('woocommerce_calc_taxes', 'no');
		$this->ensure_booking_product('1');

		$this->settings = array(
			'mhmrentiva_rental_deposit_type'        => 'percentage',
			'mhmrentiva_rental_deposit_rate'        => 20,
			'mhmrentiva_vehicle_base_price'         => 1.0,
			'mhmrentiva_vehicle_weekend_multiplier' => 1.0,
			'mhmrentiva_vehicle_min_rental_days'    => 1,
			'mhmrentiva_vehicle_max_rental_days'    => 30,
		);
		add_filter('pre_option_mhmrentiva_settings', array( $this, 'settings_filter' ));

		wp_set_current_user(0);
		wc_load_cart();
		WC()->cart->empty_cart();
	}

	public function tearDown(): void
	{
		remove_filter('pre_option_mhmrentiva_settings', array( $this, 'settings_filter' ));
		$this->restore_sandboxed_options();
		if (function_exists('WC') && isset(WC()->cart)) {
			WC()->cart->empty_cart();
		}
		parent::tearDown();
	}

	/**
	 * @return array<string, mixed>
	 */
	public function settings_filter(): array
	{
		return $this->settings;
	}

	public function test_quote_endpoint_prices_quantities(): void
	{
		$seat = $this->addon('Child seat', '100', 3);

		$quote = $this->dispatch('mhmrentiva_calculate_price', array(
			'addons'    => array( (string) $seat ),
			'addon_qty' => array( $seat => '2' ),
		));

		$this->assertTrue($quote['success'] ?? false, wp_json_encode($quote));
		$this->assertSame(200.0, (float) $quote['data']['addon_total']);
		$this->assertSame(2200.0, (float) $quote['data']['total_price'], '2 days x 1000 + 2 x 100.');
	}

	public function test_submit_stores_list_and_quantities(): void
	{
		$seat = $this->addon('Child seat', '100', 3);
		$gps  = $this->addon('GPS', '50', 1);

		$response = $this->dispatch('mhmrentiva_booking_form', array(
			'addons'    => array( (string) $seat, (string) $gps ),
			'addon_qty' => array( $seat => '2', $gps => '5' ),
		));
		$this->assertTrue($response['success'] ?? false, wp_json_encode($response));

		$data = $this->cart_booking_data();
		$this->assertSame(array( $seat => 2 ), $data['addon_quantities']);
		$this->assertSame(2250.0, (float) $data['total_price']);

		$booking_id = $this->create_booking($data);

		$this->assertSame(array( $seat, $gps ), array_map('intval', get_post_meta($booking_id, MetaKeys::BOOKING_SELECTED_ADDONS, true)));
		$this->assertSame(array( $seat => 2 ), get_post_meta($booking_id, MetaKeys::BOOKING_ADDON_QUANTITIES, true));
		$details = get_post_meta($booking_id, '_mhmrentiva_addon_details', true);
		$this->assertSame(2, $details[0]['quantity']);
		$this->assertSame(200.0, (float) $details[0]['line_total']);
		$this->assertSame(250.0, (float) get_post_meta($booking_id, '_mhmrentiva_addon_total', true));
		$this->assertSame(2250.0, (float) get_post_meta($booking_id, '_mhmrentiva_total_price', true));
	}

	/**
	 * @dataProvider payment_types
	 */
	public function test_quote_equals_checkout_with_quantities(string $payment_type): void
	{
		$seat  = $this->addon('Child seat', '100', 3);
		$extra = array(
			'addons'       => array( (string) $seat ),
			'addon_qty'    => array( $seat => '3' ),
			'payment_type' => $payment_type,
		);

		$quote = $this->dispatch('mhmrentiva_calculate_price', $extra);
		$this->assertTrue($quote['success'] ?? false, wp_json_encode($quote));

		$this->dispatch('mhmrentiva_booking_form', $extra);
		// The checkout runs on the front end. The AJAX harness leaves an admin
		// screen and only filters wp_doing_ajax (no DOING_AJAX constant), which
		// WooCommerceBridge::calculate_totals() reads as wp-admin and skips.
		set_current_screen('front');
		WC()->cart->calculate_totals();

		$due = 'deposit' === $payment_type ? $quote['data']['deposit_amount'] : $quote['data']['total_price'];
		$this->assertSame(round((float) $due, 2), round((float) WC()->cart->get_total('edit'), 2));
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function payment_types(): array
	{
		return array(
			'full'    => array( 'full' ),
			'deposit' => array( 'deposit' ),
		);
	}

	public function test_cart_snapshot_carries_quantity_and_old_cart_is_one(): void
	{
		$seat = $this->addon('Child seat', '100', 3);

		$this->dispatch('mhmrentiva_booking_form', array(
			'addons'    => array( (string) $seat ),
			'addon_qty' => array( $seat => '2' ),
		));
		$lines = $this->cart_booking_data()['addon_lines'];
		$this->assertSame('Child seat × 2', $lines[0]['title']);
		$this->assertSame(200.0, (float) $lines[0]['line_total']);

		// A cart filled before quantities existed: no addon_quantities, no snapshot.
		WC()->cart->empty_cart();
		$key  = WC()->cart->add_to_cart(
			$this->booking_product_id(),
			1,
			0,
			array(),
			array(
				'mhmrentiva_booking_data'    => array(
					'vehicle_id'      => $this->vehicle_id,
					'pickup_date'     => $this->day(10),
					'pickup_time'     => '10:00',
					'dropoff_date'    => $this->day(12),
					'dropoff_time'    => '10:00',
					'payment_type'    => 'full',
					'total_price'     => 2100,
					'rental_days'     => 2,
					'selected_addons' => array( $seat ),
				),
				'mhmrentiva_booking_price'   => 2100.0,
				'mhmrentiva_booking_pending' => true,
			)
		);
		$rows = apply_filters('woocommerce_get_item_data', array(), WC()->cart->get_cart()[ $key ]);
		$keys = array_map(static fn(array $row): string => html_entity_decode(wp_strip_all_tags((string) $row['key']), ENT_QUOTES, 'UTF-8'), $rows);
		$this->assertContains('Child seat', $keys);
	}

	public function test_order_item_meta_label_shows_quantity(): void
	{
		$seat = $this->addon('Child seat', '100', 3);

		$this->dispatch('mhmrentiva_booking_form', array(
			'addons'    => array( (string) $seat ),
			'addon_qty' => array( $seat => '2' ),
		));
		WC()->cart->calculate_totals();

		$order = wc_create_order();
		WC()->checkout()->create_order_line_items($order, WC()->cart);
		$order->save();

		$item = array_values(wc_get_order($order->get_id())->get_items())[0];
		$keys = array();
		foreach ($item->get_meta_data() as $meta) {
			$keys[] = (string) $meta->get_data()['key'];
		}
		$this->assertContains('Child seat × 2', $keys);
	}

	public function test_input_hidden_when_max_is_one(): void
	{
		$seat = $this->addon('Child seat', '100', 3);
		$gps  = $this->addon('GPS', '50', 1);
		add_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false', 999);

		$html = do_shortcode('[rentiva_booking_form vehicle_id="' . $this->vehicle_id . '"]');

		// Aşama 2 PR C: a stepper from 0 to the maximum; at 0 nothing is posted.
		$this->assertSame(1, preg_match('/data-rvs-stepper="' . $seat . '"[^>]*>/', $html, $stepper), 'A service sold up to 3 gets a quantity stepper.');
		$this->assertMatchesRegularExpression('/\bdata-max="3"/', $stepper[0]);
		$this->assertMatchesRegularExpression('/\bdata-min="0"/', $stepper[0]);
		$this->assertSame(1, preg_match('/<input[^>]*name="addon_qty\[' . $seat . '\]"[^>]*>/', $html, $input));
		$this->assertMatchesRegularExpression('/\bvalue="0"/', $input[0]);
		$this->assertMatchesRegularExpression('/\bdisabled\b/', $input[0]);
		$this->assertStringNotContainsString('name="addon_qty[' . $gps . ']"', $html, 'A maximum of 1 stays a checkbox.');
	}

	public function test_quantity_above_max_is_clamped_on_submit(): void
	{
		$seat = $this->addon('Child seat', '100', 3);

		$this->dispatch('mhmrentiva_booking_form', array(
			'addons'    => array( (string) $seat ),
			'addon_qty' => array( $seat => '50' ),
		));

		$data = $this->cart_booking_data();
		$this->assertSame(array( $seat => 3 ), $data['addon_quantities']);
		$this->assertSame(2300.0, (float) $data['total_price']);
	}

	// ------------------------------------------------------------ fixtures

	private function day(int $offset): string
	{
		return gmdate('Y-m-d', strtotime('+' . $offset . ' days'));
	}

	private function addon(string $title, string $price, int $max): int
	{
		$id = (int) self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_addon',
				'post_status' => 'publish',
				'post_title'  => $title,
			)
		);
		update_post_meta($id, 'mhmrentiva_addon_price', $price);
		update_post_meta($id, 'mhmrentiva_addon_enabled', '1');
		if ($max > 1) {
			update_post_meta($id, MetaKeys::ADDON_MAX_QUANTITY, (string) $max);
		}

		return $id;
	}

	private function booking_product_id(): int
	{
		return WooCommerceBridge::get_booking_product_id();
	}

	/**
	 * The live creation path: the order's booking, then the hook that writes
	 * the add-on rows (WooCommerceBridge::create_booking_from_order()).
	 *
	 * @param array<string, mixed> $data Cart booking data.
	 */
	private function create_booking(array $data): int
	{
		$method = new ReflectionMethod(WooCommerceBridge::class, 'create_booking_from_data');
		$method->setAccessible(true);
		$booking_id = $method->invoke(null, $data, 999);
		$this->assertIsInt($booking_id, 'The booking could not be created.');
		do_action('mhmrentiva_booking_created', $booking_id, $data);

		return $booking_id;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function cart_booking_data(): array
	{
		foreach (WC()->cart->get_cart() as $item) {
			if (isset($item['mhmrentiva_booking_data'])) {
				return $item['mhmrentiva_booking_data'];
			}
		}
		$this->fail('No booking reached the cart.');
	}

	/**
	 * @param array<string, mixed> $extra
	 * @return array<string, mixed>
	 */
	private function dispatch(string $action, array $extra = array()): array
	{
		$_POST    = array_merge(
			array(
				'action'              => $action,
				'nonce'               => wp_create_nonce('mhmrentiva_booking_form_nonce'),
				'vehicle_id'          => (string) $this->vehicle_id,
				'pickup_date'         => $this->day(10),
				'pickup_time'         => '10:00',
				'dropoff_date'        => $this->day(12),
				'dropoff_time'        => '10:00',
				'payment_type'        => 'full',
				'customer_first_name' => 'Jane',
				'customer_last_name'  => 'Doe',
				'customer_email'      => 'jane@example.com',
				'customer_phone'      => '5551112233',
			),
			$extra
		);
		$_REQUEST = $_POST;

		$ob_level = ob_get_level();
		try {
			$this->_handleAjax($action);
		} catch (\WPAjaxDieContinueException | \WPAjaxDieStopException $e) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- The payload, not the die, is what is under test.
			unset($e);
		} finally {
			while (ob_get_level() > $ob_level) {
				ob_end_clean();
			}
			while (ob_get_level() < $ob_level) {
				ob_start();
			}
		}

		$raw                  = (string) $this->_last_response;
		$this->_last_response = '';

		$second   = strpos($raw, '{"success"', 1);
		$response = json_decode(false !== $second ? substr($raw, 0, $second) : $raw, true);
		$this->assertIsArray($response, 'AJAX response must decode: ' . $raw);

		return $response;
	}
}
