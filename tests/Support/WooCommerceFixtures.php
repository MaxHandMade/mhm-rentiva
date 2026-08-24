<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Support;

use MHMRentiva\Admin\Payment\WooCommerce\WooCommerceBridge;

/**
 * Fixtures every WooCommerce-backed test needs, in one place.
 *
 * The booking product is the awkward one. WooCommerceBridge resolves it by SKU
 * and there can only ever be one product carrying that SKU -- WooCommerce
 * enforces uniqueness through a lookup table that does not always come back
 * with the test transaction, so a test that creates the product in setUp()
 * passes alone and throws "Invalid or duplicated SKU" as soon as a sibling test
 * has already run. Resolve-or-create is both the fix and the truthful model:
 * in production the product is created once and found thereafter.
 */
trait WooCommerceFixtures
{
	/**
	 * The single product WooCommerceBridge puts booking line items on.
	 */
	protected function ensure_booking_product(string $price = '1'): \WC_Product
	{
		$existing_id = (int) wc_get_product_id_by_sku(WooCommerceBridge::PRODUCT_SKU);

		if ($existing_id > 0) {
			$product = wc_get_product($existing_id);

			if ($product instanceof \WC_Product) {
				return $product;
			}
		}

		$product = new \WC_Product_Simple();
		$product->set_sku(WooCommerceBridge::PRODUCT_SKU);
		$product->set_regular_price($price);
		$product->set_price($price);
		$product->save();

		return $product;
	}

	/**
	 * Skip with a reason that names the harness, not the feature.
	 *
	 * A test that says "WooCommerce not loaded" when WooCommerce IS loaded sends
	 * the reader after the wrong thing; this one only fires when the environment
	 * gate would have failed too.
	 */
	protected function require_woocommerce(): void
	{
		if (! class_exists('WooCommerce') || ! function_exists('wc_create_order')) {
			$this->markTestSkipped(
				'WooCommerce is absent from this test environment -- see WooCommerceTestEnvironmentTest, which fails for the same reason.'
			);
		}
	}

	/**
	 * A paid WooCommerce order, bound to a booking in both directions.
	 *
	 * Both directions are load-bearing, not redundant:
	 * WooCommerceBridge::get_booking_id_from_order() reads order meta
	 * `_mhmrentiva_booking_id` to find the booking; BookingQueryHelper::resolve_wc_order_id()
	 * reads booking meta `_mhmrentiva_woocommerce_order_id` to find the order. Wire only
	 * one and a refund/payment test measures the wrong channel (or the early return).
	 *
	 * `_mhmrentiva_woocommerce_order_id` is written only the first time this is called
	 * for a given booking, mirroring RemainingPaymentHandler::create() (:284), which
	 * stamps a second order's id onto `_mhmrentiva_remaining_order_id` and never touches
	 * the primary key. A test that calls this twice for the same booking to seed a
	 * deposit-plus-remaining pair therefore keeps both orders distinguishable; an
	 * unconditional overwrite here would collapse the pair into one order id shared by
	 * both meta keys the moment the second call ran.
	 *
	 * ensure_booking_product() resolves an existing product by SKU after the first
	 * call in a run, so its price argument is ignored past that point -- the item's
	 * subtotal/total are set explicitly here and calculate_totals() derives the
	 * order total from them.
	 *
	 * What this does NOT wire: `_mhmrentiva_booking_id` on the order's LINE ITEM.
	 * Production always sets it there too (WooCommerceBridge.php :911 at checkout,
	 * :1026 on booking-created-from-order, RemainingPaymentHandler.php :256 on the
	 * remaining-payment order), and WooCommerceBridge::handle_order_status_change()
	 * reads ONLY that copy, never the order's. A test built on this fixture alone
	 * therefore cannot exercise that handler at all -- its whole switch stays
	 * structurally dead, silently, for whatever status transition the test drives.
	 * Measured directly (2026-08-20): adding the item meta here flips a fresh
	 * booking's `_mhmrentiva_status` to 'confirmed' the moment this method's own
	 * `update_status('processing')` call runs, for every caller of this trait, none
	 * of which currently assert against it. That is too large a blast radius for one
	 * fix to carry incidentally; a caller that specifically needs
	 * handle_order_status_change() to see its order should wire the item meta itself
	 * (RefundSingleWriterTest::wire_line_item_booking_id() is one way to do it) rather
	 * than assume this method provides it.
	 */
	protected function create_paid_order_for_booking( int $booking_id, string $total ): \WC_Order
	{
		$product = $this->ensure_booking_product($total);

		$order = wc_create_order(array( 'status' => 'pending' ));
		$item  = new \WC_Order_Item_Product();
		$item->set_product($product);
		$item->set_quantity(1);
		$item->set_subtotal((float) $total);
		$item->set_total((float) $total);
		$order->add_item($item);
		$order->calculate_totals();
		$order->save();
		$order->update_status('processing');

		$order->update_meta_data('_mhmrentiva_booking_id', $booking_id);
		$order->save();

		if ((int) get_post_meta($booking_id, '_mhmrentiva_woocommerce_order_id', true) <= 0) {
			update_post_meta($booking_id, '_mhmrentiva_woocommerce_order_id', $order->get_id());
		}

		return $order;
	}
}
