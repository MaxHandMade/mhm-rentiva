<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Payment\WooCommerce;

use MHMRentiva\Tests\Support\WooCommerceFixtures;
use MHMRentiva\Tests\Support\WooCommerceOptionSandbox;
use WP_UnitTestCase;

/**
 * What a booking's order line says once the order exists -- the rows the
 * order-received page, My Account, the order emails and the admin order screen
 * all print from the saved order item meta.
 *
 * Two defects, both measured on a live transfer order
 * (docs/superpowers/audits/2026-10-20-sinif-supurmesi/video-sepet-adi-gorsel.md §4):
 * a transfer carried a return date row (a transfer has no return; the cart
 * already hid it), and an add-on was listed three times -- Lite's
 * "Add-on Services: <names>" summary, Pro's "<add-on>: <price>" line and Pro's
 * "Add-ons total". The cart shows one "<add-on>: <price>" row per add-on, so
 * the order now does the same: Lite writes those rows for a rental, and the
 * add-on that prices transfer add-ons writes them for a transfer.
 *
 * Goes through WC_Checkout::create_order_line_items(), the path checkout takes.
 *
 * @covers \MHMRentiva\Admin\Payment\WooCommerce\WooCommerceBridge::add_order_item_meta
 * @covers \MHMRentiva\Admin\Payment\WooCommerce\WooCommerceBridge::get_item_data
 */
final class OrderLineBookingMetaTest extends WP_UnitTestCase
{
	use WooCommerceFixtures;
	use WooCommerceOptionSandbox;

	private \WC_Product $product;

	private int $vehicle_id;

	public function setUp(): void
	{
		parent::setUp();

		$this->require_woocommerce();

		$this->vehicle_id = self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_vehicle',
				'post_title'  => 'Mercedes-Benz Vito',
				'post_status' => 'publish',
			)
		);

		$this->product = $this->ensure_booking_product('100');

		wc_load_cart();
		WC()->cart->empty_cart();
	}

	public function tearDown(): void
	{
		if (function_exists('WC') && WC()->cart) {
			WC()->cart->empty_cart();
		}
		$this->restore_sandboxed_options();
		parent::tearDown();
	}

	private function addon(string $title, float $price, string $pricing_type): int
	{
		$id = self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_addon',
				'post_title'  => $title,
				'post_status' => 'publish',
			)
		);
		update_post_meta($id, 'mhmrentiva_addon_price', (string) $price);
		update_post_meta($id, '_mhmrentiva_addon_pricing_type', $pricing_type);

		return $id;
	}

	/**
	 * @param array<string, mixed> $extra
	 * @return array<string, mixed>
	 */
	private function rental_data(array $extra = array()): array
	{
		return array_merge(
			array(
				'vehicle_id'   => $this->vehicle_id,
				'pickup_date'  => '2026-07-05',
				'pickup_time'  => '10:00',
				'dropoff_date' => '2026-07-08',
				'dropoff_time' => '11:30',
				'payment_type' => 'full',
				'total_price'  => 300,
				'rental_days'  => 3,
				'guests'       => 2,
			),
			$extra
		);
	}

	/**
	 * What Pro's transfer checkout puts in the cart (TransferCartIntegration).
	 *
	 * @param array<string, mixed> $extra
	 * @return array<string, mixed>
	 */
	private function transfer_data(array $extra = array()): array
	{
		return array_merge(
			array(
				'vehicle_id'              => $this->vehicle_id,
				'pickup_date'             => '2026-10-31',
				'pickup_time'             => '10:00',
				// Pro fills the drop-off with the estimated arrival.
				'dropoff_date'            => '2026-10-31',
				'dropoff_time'            => '11:10',
				'payment_type'            => 'deposit',
				'remaining_amount'        => 90.0,
				'total_price'             => 100,
				'rental_days'             => 1,
				'booking_type'            => 'transfer',
				'transfer_origin_id'      => 7,
				'transfer_destination_id' => 8,
			),
			$extra
		);
	}

	/**
	 * @param array<string, mixed> $booking_data
	 */
	private function add_booking(array $booking_data): string
	{
		$key = WC()->cart->add_to_cart(
			$this->product->get_id(),
			1,
			0,
			array(),
			array(
				'mhmrentiva_booking_data'    => $booking_data,
				'mhmrentiva_booking_price'   => 50.0,
				'mhmrentiva_booking_pending' => true,
			)
		);
		$this->assertIsString($key, 'The booking could not be added to the cart.');

		return $key;
	}

	/**
	 * The visible rows of the saved order line, key => value, in order.
	 *
	 * @param array<string, mixed> $booking_data
	 * @return array<int, array{0: string, 1: string}>
	 */
	private function order_rows(array $booking_data): array
	{
		$this->add_booking($booking_data);
		WC()->cart->calculate_totals();

		$order = wc_create_order();
		WC()->checkout()->create_order_line_items($order, WC()->cart);
		$order->save();

		$item = array_values(wc_get_order($order->get_id())->get_items())[0];
		$rows = array();
		foreach ($item->get_meta_data() as $meta) {
			$data = $meta->get_data();
			if (str_starts_with((string) $data['key'], '_')) {
				continue;
			}
			$rows[] = array( (string) $data['key'], is_scalar($data['value']) ? (string) $data['value'] : '' );
		}

		return $rows;
	}

	/**
	 * @param array<int, array{0: string, 1: string}> $rows
	 * @return array<int, string>
	 */
	private function keys(array $rows): array
	{
		return array_map(static fn(array $row): string => $row[0], $rows);
	}

	/**
	 * @param array<int, array{0: string, 1: string}> $rows
	 */
	private function count_rows_naming(array $rows, string $needle): int
	{
		$count = 0;
		foreach ($rows as $row) {
			if (str_contains($row[0], $needle) || str_contains($row[1], $needle)) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * A cart row's key as the visitor reads it (the bridge escapes its keys).
	 *
	 * @param mixed $key
	 */
	private static function label($key): string
	{
		return html_entity_decode(wp_strip_all_tags((string) $key), ENT_QUOTES, 'UTF-8');
	}

	/**
	 * A price as plain text, its symbol as a character (not an entity).
	 */
	private static function plain(string $html): string
	{
		return html_entity_decode(wp_strip_all_tags($html), ENT_QUOTES, 'UTF-8');
	}

	// ------------------------------------------------------------- dates

	public function test_a_rental_order_line_carries_pickup_and_return(): void
	{
		$keys = $this->keys($this->order_rows($this->rental_data()));

		$this->assertContains('Pickup Date & Time', $keys);
		$this->assertContains('Return Date & Time', $keys);
	}

	public function test_a_transfer_order_line_has_no_return_row(): void
	{
		$keys = $this->keys($this->order_rows($this->transfer_data()));

		$this->assertContains('Pickup Date & Time', $keys);
		$this->assertNotContains('Return Date & Time', $keys);
		$this->assertNotContains('Dropoff', $keys);
	}

	public function test_a_transfer_marked_only_by_its_origin_has_no_return_row(): void
	{
		$data = $this->transfer_data();
		unset($data['booking_type']);

		$this->assertNotContains('Return Date & Time', $this->keys($this->order_rows($data)));
	}

	public function test_the_order_line_uses_the_carts_date_labels(): void
	{
		// The cart says "Pickup Date & Time" / "Return Date & Time"; the order
		// said "Pickup" / "Dropoff" -- two names for one row across one purchase.
		$cart = WC()->cart;
		$key  = $this->add_booking($this->rental_data());
		$cart_keys = array_map(
			static fn(array $row): string => self::label($row['key']),
			apply_filters('woocommerce_get_item_data', array(), $cart->get_cart()[ $key ])
		);
		$cart->empty_cart();

		$order_keys = $this->keys($this->order_rows($this->rental_data()));

		foreach (array( 'Pickup Date & Time', 'Return Date & Time' ) as $label) {
			$this->assertContains($label, $cart_keys);
			$this->assertContains($label, $order_keys);
		}
	}

	public function test_a_transfer_cart_line_has_no_return_row(): void
	{
		$key  = $this->add_booking($this->transfer_data());
		$keys = array_map(
			static fn(array $row): string => self::label($row['key']),
			apply_filters('woocommerce_get_item_data', array(), WC()->cart->get_cart()[ $key ])
		);

		$this->assertContains('Pickup Date & Time', $keys);
		$this->assertNotContains('Return Date & Time', $keys);
	}

	// ------------------------------------------------------------ add-ons

	public function test_a_rental_lists_each_add_on_once_with_its_price(): void
	{
		$insurance = $this->addon('Full Insurance', 25.0, 'per_booking');
		$seat      = $this->addon('Child Seat', 10.0, 'per_day');

		$rows = $this->order_rows($this->rental_data(array( 'selected_addons' => array( $insurance, $seat ) )));

		$this->assertSame(1, $this->count_rows_naming($rows, 'Full Insurance'), wp_json_encode($rows));
		$this->assertSame(1, $this->count_rows_naming($rows, 'Child Seat'), wp_json_encode($rows));
		$this->assertNotContains('Add-on Services', $this->keys($rows));

		$by_key = array_column($rows, 1, 0);
		$this->assertSame(self::plain(wc_price(25.0)), $by_key['Full Insurance']);
		// Per day, three rental days.
		$this->assertSame(self::plain(wc_price(30.0)), $by_key['Child Seat']);
	}

	public function test_a_rental_cart_line_shows_each_add_on_with_its_price(): void
	{
		$insurance = $this->addon('Full Insurance', 25.0, 'per_booking');

		$key  = $this->add_booking($this->rental_data(array( 'selected_addons' => array( $insurance ) )));
		$rows = apply_filters('woocommerce_get_item_data', array(), WC()->cart->get_cart()[ $key ]);

		$by_key = array();
		foreach ($rows as $row) {
			$by_key[ self::label($row['key']) ] = (string) $row['value'];
		}
		$this->assertArrayHasKey('Full Insurance', $by_key);
		$this->assertSame(wp_kses_post(wc_price(25.0)), $by_key['Full Insurance']);
	}

	public function test_lite_leaves_a_transfers_add_ons_to_the_add_on(): void
	{
		// Pro prices transfer add-ons (per passenger, per transfer) and writes
		// their rows; a Lite row beside them was the duplicate.
		$insurance = $this->addon('Full Insurance', 25.0, 'per_booking');

		$rows = $this->order_rows(
			$this->transfer_data(
				array(
					'selected_addons' => array( $insurance ),
					'addon_details'   => array(
						array(
							'id'         => $insurance,
							'title'      => 'Full Insurance',
							'line_total' => 25.0,
						),
					),
					'addon_total'     => 25.0,
				)
			)
		);

		$this->assertSame(0, $this->count_rows_naming($rows, 'Full Insurance'), wp_json_encode($rows));
		$this->assertNotContains('Add-on Services', $this->keys($rows));
	}

	// ------------------------------------------------- order meta is plain text

	public function test_order_prices_keep_their_symbol_in_a_plain_text_email(): void
	{
		// The value is stored as text: a plain-text email strips tags, and a
		// stored "&#8378;" entity came out as nothing ("30,00 ").
		$this->sandbox_option('woocommerce_currency', 'TRY');
		$insurance = $this->addon('Full Insurance', 25.0, 'per_booking');

		$rows   = $this->order_rows(
			$this->rental_data(
				array(
					'selected_addons'  => array( $insurance ),
					'payment_type'     => 'deposit',
					'remaining_amount' => 90.0,
				)
			)
		);
		$by_key = array_column($rows, 1, 0);

		foreach (array( 'Full Insurance', 'Remaining Amount' ) as $label) {
			$this->assertArrayHasKey($label, $by_key, wp_json_encode($rows));
			$value = $by_key[ $label ];
			$this->assertStringNotContainsString('<', $value, $label);
			$this->assertStringNotContainsString('&', $value, $label);
			$this->assertStringContainsString('₺', wp_strip_all_tags($value), $label);
		}
	}

	public function test_an_add_on_named_with_a_leading_underscore_stays_visible(): void
	{
		// WooCommerce hides order meta whose key starts with "_".
		$addon = $this->addon('_Insurance', 25.0, 'per_booking');

		$rows = $this->order_rows($this->rental_data(array( 'selected_addons' => array( $addon ) )));

		$this->assertContains('Insurance', $this->keys($rows), wp_json_encode($rows));
	}

	/**
	 * @dataProvider underscore_only_names
	 */
	public function test_an_add_on_named_only_with_underscores_stays_visible(string $title): void
	{
		$addon = $this->addon($title, 25.0, 'per_booking');

		$rows = $this->order_rows($this->rental_data(array( 'selected_addons' => array( $addon ) )));

		$this->assertContains('Add-ons', $this->keys($rows), wp_json_encode($rows));
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function underscore_only_names(): array
	{
		return array(
			'underscores'            => array( '___' ),
			'underscores and spaces' => array( '_ _' ),
		);
	}

	public function test_a_per_passenger_add_on_shows_what_the_rental_charges(): void
	{
		// The rental's price (quote and DepositCalculator) counts an add-on by
		// days only; the row said price x guests and did not add up to the order.
		$addon = $this->addon('Driver', 10.0, 'per_passenger');
		update_post_meta($this->vehicle_id, '_mhmrentiva_price_per_day', '100');
		$data  = $this->rental_data(array( 'selected_addons' => array( $addon ), 'guests' => 3 ));

		$charged = \MHMRentiva\Admin\Vehicle\Deposit\DepositCalculator::calculate_booking_deposit(
			$this->vehicle_id,
			3,
			'full',
			array( $addon ),
			(int) strtotime('2026-07-05 10:00')
		);
		$by_key = array_column($this->order_rows($data), 1, 0);

		$this->assertSame(self::plain(wc_price(10.0)), $by_key['Driver'] ?? null);
		$this->assertEqualsWithDelta(
			10.0,
			(float) $charged['addon_total'],
			0.001,
			'the charge counts the add-on once'
		);
	}

	public function test_an_add_on_without_a_name_keeps_a_visible_row(): void
	{
		$addon = $this->addon('', 25.0, 'per_booking');

		$rows = $this->order_rows($this->rental_data(array( 'selected_addons' => array( $addon ) )));

		$this->assertContains('Add-ons', $this->keys($rows), wp_json_encode($rows));
	}

	public function test_the_order_lists_the_add_on_price_the_cart_was_charged(): void
	{
		// The charge is fixed when the booking enters the cart; an add-on
		// re-priced (or deleted) before checkout must not change its row.
		$insurance = $this->addon('Full Insurance', 25.0, 'per_booking');
		$this->assertTrue(
			\MHMRentiva\Admin\Payment\WooCommerce\WooCommerceBridge::add_booking_data_to_cart(
				$this->rental_data(array( 'selected_addons' => array( $insurance ) )),
				50.0
			)
		);

		update_post_meta($insurance, 'mhmrentiva_addon_price', '99');
		wp_update_post(array( 'ID' => $insurance, 'post_title' => 'Renamed' ));
		WC()->cart->calculate_totals();

		$order = wc_create_order();
		WC()->checkout()->create_order_line_items($order, WC()->cart);
		$order->save();
		$item = array_values(wc_get_order($order->get_id())->get_items())[0];

		$rows = array();
		foreach ($item->get_meta_data() as $meta) {
			$data                           = $meta->get_data();
			$rows[ (string) $data['key'] ] = is_scalar($data['value']) ? (string) $data['value'] : '';
		}

		$this->assertSame(self::plain(wc_price(25.0)), $rows['Full Insurance'] ?? null, wp_json_encode($rows));
		$this->assertArrayNotHasKey('Renamed', $rows);
	}
}
