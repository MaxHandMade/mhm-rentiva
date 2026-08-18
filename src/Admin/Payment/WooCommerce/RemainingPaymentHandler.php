<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Payment\WooCommerce;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * RemainingPaymentHandler
 *
 * Handles frontend AJAX flow for "Pay Remaining Amount" on deposit bookings.
 * Creates a minimal WooCommerce order for the remaining amount and redirects
 * the customer to the native WC order-pay page.
 *
 * HPOS-compatible: all order reads/writes use WC order object methods.
 */
final class RemainingPaymentHandler {

	/**
	 * Register AJAX hooks.
	 */
	public static function register(): void
	{
		add_action('wp_ajax_mhmrentiva_pay_remaining', array( self::class, 'ajax_create_remaining_order' ));
		add_action('wp_ajax_nopriv_mhmrentiva_pay_remaining', array( self::class, 'ajax_create_remaining_order' ));
	}

	/**
	 * AJAX handler: create (or retrieve) a remaining-payment WC order and
	 * return the checkout payment URL.
	 */
	public static function ajax_create_remaining_order(): void
	{
		$booking_id = isset($_POST['booking_id']) ? (int) $_POST['booking_id'] : 0;

		if (! $booking_id) {
			wp_send_json_error(array( 'message' => __('Invalid booking.', 'mhm-rentiva') ));
		}

		// Nonce verification
		$nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
		if (! wp_verify_nonce($nonce, 'mhmrentiva_pay_remaining_' . $booking_id)) {
			wp_send_json_error(array( 'message' => __('Security check failed.', 'mhm-rentiva') ));
		}

		// Must be logged in
		if (! is_user_logged_in()) {
			wp_send_json_error(array( 'message' => __('You must be logged in.', 'mhm-rentiva') ));
		}

		$current_user_id  = get_current_user_id();
		$customer_user_id = (int) get_post_meta($booking_id, '_mhmrentiva_customer_user_id', true);
		$is_admin         = current_user_can('manage_options');

		// Ownership check
		if (! $is_admin && $customer_user_id !== $current_user_id) {
			wp_send_json_error(array( 'message' => __('Access denied.', 'mhm-rentiva') ));
		}

		$order = self::get_or_create_remaining_order($booking_id);

		if (is_wp_error($order)) {
			wp_send_json_error(array( 'message' => $order->get_error_message() ));
			return;
		}

		wp_send_json_success(array( 'payment_url' => $order->get_checkout_payment_url() ));
	}

	/**
	 * Create (or reuse an existing pending/on-hold) WC order for a booking's
	 * remaining amount. Pure order logic — no AJAX/nonce/ownership concerns,
	 * safe to call from both the customer-facing AJAX handler above and
	 * admin-initiated flows (see DepositManagementAjax::send_remaining_payment_link()).
	 *
	 * @param int $booking_id Booking post ID.
	 * @return \WC_Order|\WP_Error
	 */
	public static function get_or_create_remaining_order(int $booking_id): \WC_Order|\WP_Error
	{
		// The existing-order lookup and the wc_create_order() that follows it must
		// be one critical section. Before 6.0.3 they were not: two concurrent
		// callers (a double click, two tabs, a re-sent payment link) could both
		// read "no remaining order", both create one, and both write the meta. The
		// booking keeps the last ID written; the other order survives as a live
		// pending order for money that is already accounted for.
		$result = \MHMRentiva\Admin\Booking\Helpers\Locker::withBookingLock(
			$booking_id,
			static fn() => self::resolve_remaining_order($booking_id)
		);

		if ($result instanceof \WC_Order || $result instanceof \WP_Error) {
			return $result;
		}

		return new \WP_Error('mhmrentiva_order_create_failed', __('Failed to create payment order. Please try again.', 'mhm-rentiva'));
	}

	/**
	 * Resolve (reuse or create) the remaining-payment order for a booking.
	 *
	 * Always called inside Locker::withBookingLock() by
	 * get_or_create_remaining_order(); it must not be called directly, or the
	 * check-then-create sequence loses its critical section.
	 *
	 * @param int $booking_id Booking post ID.
	 * @return \WC_Order|\WP_Error
	 */
	private static function resolve_remaining_order(int $booking_id): \WC_Order|\WP_Error
	{
		// Waiting for the lock is only half of it: the caller's ownership check
		// (ajax_create_remaining_order(), and DepositManagementAjax) reads booking
		// meta BEFORE the lock, and update_meta_cache() pulls the booking's whole
		// meta set into the request cache at that moment. Without this the lookup
		// below is served from a snapshot taken before the other request committed,
		// so both requests decide "no remaining order exists" and both create one.
		// Serialisation without freshness is not mutual exclusion.
		wp_cache_delete($booking_id, 'post_meta');

		// Must be a deposit booking with remaining amount > 0
		$payment_type     = get_post_meta($booking_id, '_mhmrentiva_payment_type', true);
		$remaining_amount = (float) get_post_meta($booking_id, '_mhmrentiva_remaining_amount', true);

		if ($payment_type !== 'deposit' || $remaining_amount <= 0) {
			return new \WP_Error('mhmrentiva_no_remaining_amount', __('No remaining amount due for this booking.', 'mhm-rentiva'));
		}

		// Check for an existing pending remaining-payment order to avoid duplicates
		$existing_remaining_order_id = (int) get_post_meta($booking_id, '_mhmrentiva_remaining_order_id', true);
		if ($existing_remaining_order_id) {
			$existing_order = wc_get_order($existing_remaining_order_id);
			if ($existing_order && in_array($existing_order->get_status(), array( 'pending', 'on-hold' ), true)) {
				return $existing_order;
			}
		}

		// Resolve the booking product by SKU
		$product_id = wc_get_product_id_by_sku(WooCommerceBridge::PRODUCT_SKU);
		if (! $product_id) {
			return new \WP_Error('mhmrentiva_no_booking_product', __('Booking product not found. Please contact support.', 'mhm-rentiva'));
		}

		$product = wc_get_product($product_id);
		if (! $product) {
			return new \WP_Error('mhmrentiva_booking_product_load_failed', __('Booking product could not be loaded.', 'mhm-rentiva'));
		}

		$customer_user_id = (int) get_post_meta($booking_id, '_mhmrentiva_customer_user_id', true);

		// Vehicle name for line item label
		$vehicle_id   = (int) get_post_meta($booking_id, '_mhmrentiva_vehicle_id', true);
		$vehicle      = $vehicle_id ? get_post($vehicle_id) : null;
		$vehicle_name = $vehicle ? $vehicle->post_title : __('Vehicle', 'mhm-rentiva');

		// Original WC order ID
		$original_order_id = \MHMRentiva\Admin\Core\Utilities\BookingQueryHelper::resolve_wc_order_id($booking_id);

		// -----------------------------------------------------------------------
		// Create the WC order
		// -----------------------------------------------------------------------
		$order = wc_create_order(array(
			'customer_id' => $customer_user_id,
			'status'      => 'pending',
		));

		if (is_wp_error($order)) {
			return new \WP_Error('mhmrentiva_order_create_failed', __('Failed to create payment order. Please try again.', 'mhm-rentiva'));
		}

		// Add line item: use the booking product but override name & price
		$item_name = sprintf(
			/* translators: 1: vehicle name, 2: booking ID */
			__('Remaining Payment - %1$s #%2$d', 'mhm-rentiva'),
			$vehicle_name,
			$booking_id
		);

		$item = new \WC_Order_Item_Product();
		$item->set_product($product);
		$item->set_name($item_name);
		$item->set_quantity(1);

		// $remaining_amount is tax-INCLUSIVE (gross). WC_Order_Item::set_subtotal()
		// expects net (pre-tax) when prices_include_tax is on, then
		// calculate_totals() re-adds tax. Without this conversion we double-tax
		// the customer (gross + tax_on_gross), inflating the order total.
		// wc_get_price_excluding_tax() handles tax class + zone automatically.
		$net_amount = function_exists( 'wc_get_price_excluding_tax' )
			? (float) wc_get_price_excluding_tax(
				$product,
				array(
					'price' => $remaining_amount,
					'qty'   => 1,
				)
			)
			: (float) $remaining_amount;

		$item->set_subtotal($net_amount);
		$item->set_total($net_amount);

		// Item meta so handle_order_status_change can pick up the booking
		$item->add_meta_data('_mhmrentiva_booking_id', $booking_id, true);

		$order->add_item($item);

		// Billing address from customer user meta
		$user_info = get_userdata($customer_user_id);
		if ($user_info) {
			$order->set_billing_first_name( (string) get_user_meta($customer_user_id, 'billing_first_name', true) ?: $user_info->first_name);
			$order->set_billing_last_name( (string) get_user_meta($customer_user_id, 'billing_last_name', true) ?: $user_info->last_name);
			$order->set_billing_email( (string) $user_info->user_email);
			$order->set_billing_phone( (string) get_user_meta($customer_user_id, 'billing_phone', true));
			$order->set_billing_address_1( (string) get_user_meta($customer_user_id, 'billing_address_1', true));
			$order->set_billing_city( (string) get_user_meta($customer_user_id, 'billing_city', true));
			$order->set_billing_postcode( (string) get_user_meta($customer_user_id, 'billing_postcode', true));
			$order->set_billing_country( (string) get_user_meta($customer_user_id, 'billing_country', true));
		}

		// HPOS-compatible order meta
		$order->update_meta_data('_mhmrentiva_booking_id', $booking_id);
		$order->update_meta_data('_mhmrentiva_is_remaining_payment', '1');
		if ($original_order_id) {
			$order->update_meta_data('_mhmrentiva_original_order_id', $original_order_id);
		}

		$order->calculate_totals();
		$order->save();

		// Persist remaining order ID on the booking so we can reuse it
		update_post_meta($booking_id, '_mhmrentiva_remaining_order_id', $order->get_id());

		return $order;
	}
}
