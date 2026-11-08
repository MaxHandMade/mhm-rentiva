<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Payment\WooCommerce;

use MHMRentiva\Admin\Payment\WooCommerce\WooCommerceBridge;
use MHMRentiva\Tests\Support\WooCommerceFixtures;
use WP_UnitTestCase;

/**
 * The booking snapshot an order line carries keeps the backslashes a customer
 * typed, and so does the booking made from it.
 *
 * WooCommerce's WC_Data_Store_WP::add_meta() slashes a STRING meta value before
 * add_metadata() unslashes it, but passes an ARRAY through as it is -- and
 * add_metadata() unslashes every value. The snapshot is an array, so every "\"
 * in it was lost in the saved row. The classic checkout reloads the order from
 * the database before the order-placed hook (WC_Checkout::process_checkout(),
 * wc_get_order($order_id)), so the booking was made from the lossy row; the
 * Store API hands the in-memory order on and was not affected. Free text in a
 * snapshot: the signed-in customer's name and phone (BookingForm), the add-on
 * line titles, and Pro's transfer add-on titles.
 *
 * Plan: docs/superpowers/plans/2026-11-08-ters-bolu-odeme-siniri.md (r2).
 *
 * @covers \MHMRentiva\Admin\Payment\WooCommerce\WooCommerceBridge::keep_booking_snapshot_backslashes
 * @covers \MHMRentiva\Admin\Payment\WooCommerce\WooCommerceBridge::add_order_item_meta
 */
final class OrderLineBookingSnapshotBackslashTest extends WP_UnitTestCase
{
	use WooCommerceFixtures;

	private const NAME  = 'Ali\\Veli';
	private const FIRST = 'Ali\\';
	private const LAST  = '\\Veli';
	private const PHONE = '0\\555 123';
	private const ADDON = 'Child seat \\ booster';
	private const TRANSFER_ADDON = 'Meet \\ greet';

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

		// As RepaymentClaimTest: a nested START TRANSACTION would commit the
		// test's own transaction.
		add_filter('query', static function ($query) {
			return preg_match('/^\s*(START TRANSACTION|COMMIT|ROLLBACK)\b/i', (string) $query) ? 'SELECT 1' : $query;
		});
	}

	public function tearDown(): void
	{
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
				'customer_name'       => self::NAME,
				'customer_first_name' => self::FIRST,
				'customer_last_name'  => self::LAST,
				'customer_email'      => 'ali@example.com',
				'customer_phone'      => self::PHONE,
				'payment_type'        => 'full',
				'deposit_amount'      => 0.0,
				'remaining_amount'    => 0.0,
				'payment_display'     => 'Full',
				'total_price'         => 300.0,
				'rental_days'         => 3,
				'selected_addons'     => array(),
				'addon_lines'         => array( array( 'id' => 0, 'title' => self::ADDON, 'line_total' => 0.0 ) ),
				'addon_details'       => array( array( 'title' => self::TRANSFER_ADDON, 'line_total' => 0.0 ) ),
			),
			$extra
		);
	}

	/**
	 * The classic checkout's way to the line: the cart, then
	 * WC_Checkout::create_order_line_items() (which fires
	 * woocommerce_checkout_create_order_line_item -> add_order_item_meta()).
	 *
	 * @param array<string, mixed> $snapshot
	 */
	private function order_from_cart(array $snapshot): \WC_Order
	{
		$key = WC()->cart->add_to_cart(
			$this->product->get_id(),
			1,
			0,
			array(),
			array(
				'mhmrentiva_booking_data'    => $snapshot,
				'mhmrentiva_booking_price'   => 300.0,
				'mhmrentiva_booking_pending' => true,
			)
		);
		$this->assertIsString($key, 'The booking could not be added to the cart.');
		WC()->cart->calculate_totals();

		$order = wc_create_order();
		WC()->checkout()->create_order_line_items($order, WC()->cart);
		$order->set_status('pending');
		$order->save();

		return $order;
	}

	/** The order as a new request would read it: no object cache, no HPOS order cache. */
	private function fresh_order(int $order_id): \WC_Order
	{
		if (class_exists(\Automattic\WooCommerce\Caches\OrderCache::class)) {
			wc_get_container()->get(\Automattic\WooCommerce\Caches\OrderCache::class)->remove($order_id);
		}
		wp_cache_flush();

		$order = wc_get_order($order_id);
		$this->assertInstanceOf(\WC_Order::class, $order);

		return $order;
	}

	/**
	 * The saved row itself, read past every WooCommerce layer.
	 *
	 * @return array<string, mixed>
	 */
	private function raw_snapshot(\WC_Order $order): array
	{
		global $wpdb;

		$item_id = (int) array_key_first($order->get_items());
		$value   = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT meta_value FROM {$wpdb->prefix}woocommerce_order_itemmeta WHERE order_item_id = %d AND meta_key = %s",
				$item_id,
				'_mhmrentiva_booking_data'
			)
		);
		$snapshot = maybe_unserialize((string) $value);
		$this->assertIsArray($snapshot, 'The line has no saved snapshot.');

		return $snapshot;
	}

	/**
	 * @param array<string, mixed> $snapshot
	 */
	private function assert_free_text_kept(array $snapshot, string $where): void
	{
		$this->assertSame(self::NAME, $snapshot['customer_name'], "customer_name, $where");
		$this->assertSame(self::FIRST, $snapshot['customer_first_name'], "customer_first_name, $where");
		$this->assertSame(self::LAST, $snapshot['customer_last_name'], "customer_last_name, $where");
		$this->assertSame(self::PHONE, $snapshot['customer_phone'], "customer_phone, $where");
		$this->assertSame(self::ADDON, $snapshot['addon_lines'][0]['title'], "addon_lines[].title, $where");
		$this->assertSame(self::TRANSFER_ADDON, $snapshot['addon_details'][0]['title'], "addon_details[].title, $where");
	}

	private function assert_booking_kept(int $booking_id, string $where): void
	{
		$this->assertGreaterThan(0, $booking_id, "No booking was made, $where.");
		$this->assertSame(self::NAME, get_post_meta($booking_id, '_mhmrentiva_customer_name', true), "booking customer name, $where");
		$this->assertSame(self::PHONE, get_post_meta($booking_id, '_mhmrentiva_customer_phone', true), "booking customer phone, $where");
	}

	public function test_the_saved_row_keeps_every_backslash_of_the_snapshot(): void
	{
		$order = $this->order_from_cart($this->snapshot());

		$this->assert_free_text_kept($this->raw_snapshot($order), 'saved row');
		$this->assert_free_text_kept($this->fresh_order($order->get_id())->get_items()[array_key_first($order->get_items())]->get_meta('_mhmrentiva_booking_data', true), 'reloaded order');
	}

	public function test_the_in_memory_snapshot_is_not_slashed_twice(): void
	{
		$order = $this->order_from_cart($this->snapshot());

		$item = $order->get_items()[array_key_first($order->get_items())];
		$this->assert_free_text_kept($item->get_meta('_mhmrentiva_booking_data', true), 'same object after save');
	}

	public function test_the_classic_checkout_books_from_the_reloaded_order_without_losing_a_backslash(): void
	{
		$order = $this->order_from_cart($this->snapshot());

		// WC_Checkout::process_checkout(): the hook gets wc_get_order($order_id).
		$reloaded = $this->fresh_order($order->get_id());
		WooCommerceBridge::create_booking_from_order($reloaded->get_id(), array(), $reloaded);

		$this->assert_booking_kept((int) $this->fresh_order($order->get_id())->get_meta('_mhmrentiva_booking_id'), 'classic checkout');
	}

	public function test_the_store_api_books_from_the_in_memory_order_without_doubling_a_backslash(): void
	{
		$order = $this->order_from_cart($this->snapshot());

		// The Store API hands the order it built on (OrderController).
		WooCommerceBridge::store_api_order_processed($order);

		$this->assert_booking_kept((int) $this->fresh_order($order->get_id())->get_meta('_mhmrentiva_booking_id'), 'Store API');
	}

	public function test_the_thank_you_fallback_books_from_the_saved_row_without_losing_a_backslash(): void
	{
		$order = $this->order_from_cart($this->snapshot());
		$order->set_status('processing');
		$order->save();

		WooCommerceBridge::create_booking_from_order_fallback($order->get_id());

		$this->assert_booking_kept((int) $this->fresh_order($order->get_id())->get_meta('_mhmrentiva_booking_id'), 'thank-you fallback');
	}

	public function test_a_snapshot_without_a_backslash_is_written_once(): void
	{
		$rewrites = 0;
		$count    = static function ($check, $mid, $value, $key) use (&$rewrites) {
			if ('_mhmrentiva_booking_data' === $key) {
				++$rewrites;
			}
			return $check;
		};
		add_filter('update_order_item_metadata_by_mid', $count, 10, 4);

		$this->order_from_cart(
			$this->snapshot(
				array(
					'customer_name'       => 'Ali Veli',
					'customer_first_name' => 'Ali',
					'customer_last_name'  => 'Veli',
					'customer_phone'      => '0555 123',
					'addon_lines'         => array(),
					'addon_details'       => array(),
				)
			)
		);
		remove_filter('update_order_item_metadata_by_mid', $count, 10);

		$this->assertSame(0, $rewrites, 'Nothing to restore, so no second write.');
	}

	public function test_only_the_booking_snapshot_is_rewritten(): void
	{
		$order = wc_create_order();
		$item  = new \WC_Order_Item_Product();
		$item->set_product($this->product);
		$item->add_meta_data('_some_other_array', array( 'note' => 'C:\\path' ));
		$order->add_item($item);
		$order->save();

		global $wpdb;
		$raw = maybe_unserialize(
			(string) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT meta_value FROM {$wpdb->prefix}woocommerce_order_itemmeta WHERE order_item_id = %d AND meta_key = %s",
					$item->get_id(),
					'_some_other_array'
				)
			)
		);

		// WooCommerce's own behaviour for any other array: the listener does not
		// widen its reach beyond the key it owns.
		$this->assertSame(array( 'note' => 'C:path' ), $raw);
	}
}
