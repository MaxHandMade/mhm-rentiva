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
	 * The currency a booking's remaining amount is in: the store's own setting,
	 * read unfiltered. A currency switcher filters get_woocommerce_currency()
	 * per visitor; the booking's prices are never converted.
	 */
	public static function debt_currency(): string
	{
		return (string) get_option('woocommerce_currency', '');
	}

	/**
	 * The precision of that amount: the store's own decimals setting, read
	 * without the wc_get_price_decimals filter a currency switcher answers per
	 * visitor. Money::toMinor() follows the filter, so an order built and a
	 * payment checked under two different visitors would measure the same
	 * debt in two scales (Codex r11 1).
	 */
	public static function debt_decimals(): int
	{
		return absint(get_option('woocommerce_price_num_decimals', 2));
	}

	/** A remaining amount (major units) at the debt's own precision. */
	public static function debt_minor(float|string $major): int
	{
		return (int) round( (float) $major * ( 10 ** self::debt_decimals() ));
	}

	/** An amount_verdict() answer: the order asks for exactly what the booking owes. */
	public const VERDICT_MATCH = 'match';

	/** An amount_verdict() answer: the order asks for anything else, or the booking owes nothing. */
	public const VERDICT_MISMATCH = 'mismatch';

	/**
	 * Does this remaining-payment order ask for what the booking owes?
	 *
	 * The one rule, used where an order is reused, where it is about to take
	 * money and where its payment lands (PR-2 r5): it had two copies that had
	 * already drifted apart, and a third would have drifted too. The order
	 * carries the gross amount it was built for (`_mhmrentiva_remaining_due_minor`,
	 * stamped at build time); its own total can differ through tax for the
	 * billing address, so the total is the fallback only for an order built
	 * before the stamp existed. The amount is a number in the store's own
	 * currency: an order in any other currency asks for a different sum. A
	 * booking that owes nothing is never matched.
	 *
	 * @param int $owed_minor What the booking owes, at the debt's precision (debt_minor()).
	 */
	public static function amount_verdict(\WC_Order $order, int $owed_minor): string
	{
		if ( (string) $order->get_currency() !== self::debt_currency() || $owed_minor <= 0) {
			return self::VERDICT_MISMATCH;
		}

		$stamp = $order->get_meta('_mhmrentiva_remaining_due_minor', true);
		$asks  = is_numeric($stamp) ? (int) $stamp : self::debt_minor( (string) $order->get_total());

		return $asks === $owed_minor ? self::VERDICT_MATCH : self::VERDICT_MISMATCH;
	}

	/** Was the order stamped with the amount it was built for (PR-1 onwards)? */
	public static function is_stamped(\WC_Order $order): bool
	{
		return is_numeric($order->get_meta('_mhmrentiva_remaining_due_minor', true));
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
		// A hybrid booking -- deposit taken offline, remainder through WooCommerce
		// -- cannot be represented honestly: PaymentState reads the offline channel
		// only when there is no paid WC order, so building this order would make an
		// already-received offline payment disappear from paid(). Refuse only when
		// there is such a payment to lose: a booking with no proven payment yet is
		// the ordinary "manual booking, send the customer a payment link" flow, and
		// it stays supported.
		if (self::is_hybrid_booking($booking_id)) {
			return new \WP_Error(
				'mhmrentiva_hybrid_booking_refused',
				__('This booking was already paid outside WooCommerce; collect the remaining balance the same way.', 'mhm-rentiva')
			);
		}

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
	 * Whether a booking has money that arrived outside WooCommerce and no paid
	 * WooCommerce order to represent it -- the shape a hybrid booking (deposit
	 * taken offline, remainder through WooCommerce) would leave behind.
	 *
	 * This is deliberately PaymentState's question, not
	 * BookingQueryHelper::resolve_wc_order_id()'s. resolve_wc_order_id() asks
	 * whether an order ID is present on the booking, not whether money
	 * arrived -- and checkout binds a `pending` order at creation time, before
	 * any payment happens. A booking whose deposit was taken in cash still
	 * carries that pending order's ID in meta, so this guard asked
	 * resolve_wc_order_id() <= 0 until 6.1.0, found an ID, stayed silent, and
	 * let the hybrid this method exists to catch get created anyway.
	 * PaymentState::orders() only counts orders whose get_date_paid() is set,
	 * so a pending order does not count as "there is a WooCommerce order"
	 * here -- closing exactly that gap.
	 *
	 * The single authority for this question: callers (this class and
	 * DepositManagementAjax) must call it rather than re-deriving the
	 * predicate, or the two copies drift.
	 *
	 * The move to `orders() === [] && paid() > 0` also narrows the guard,
	 * not only widens it: paid()'s offline leg is max(0, total - remaining),
	 * so a deposit booking where the vehicle carries no deposit value
	 * (DepositCalculator::calculate_deposit() then sets remaining === total)
	 * now leaves the guard silent where the old status-only check would have
	 * refused it. That is chosen, not overlooked -- there is no offline
	 * money for such a booking to lose, so building the order for the whole
	 * balance is honest. See HybridBookingGuardTest::
	 * test_a_no_deposit_booking_with_total_equal_to_remaining_leaves_the_guard_silent().
	 */
	public static function is_hybrid_booking(int $booking_id): bool
	{
		$state = \MHMRentiva\Admin\Payment\Core\PaymentState::forBooking($booking_id);

		return $state->orders() === array() && $state->paid() > 0;
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

		// Reuse an existing pending remaining-payment order to avoid duplicates --
		// only while it asks for what the booking owes now. An admin re-price
		// moves the remaining amount (H2 face 2); an order built for the old
		// amount is not handed out again. It is not cancelled either: cancelling
		// an order runs the booking's cancellation hooks, and a payment started
		// on it could still complete. It simply cannot take money any more:
		// WooCommerceBridge::stale_remaining_order_notice() refuses it before the
		// gateway.
		//
		// A failed order is reused too (PR-2 A2): the customer retries a declined
		// card on the same order, and building a new one beside it would turn
		// the order they are retrying into one that is no longer the link.
		$due_minor                   = self::debt_minor($remaining_amount);
		$existing_remaining_order_id = (int) get_post_meta($booking_id, '_mhmrentiva_remaining_order_id', true);
		if ($existing_remaining_order_id) {
			$existing_order = wc_get_order($existing_remaining_order_id);
			if (
				$existing_order
				&& in_array($existing_order->get_status(), array( 'pending', 'on-hold', 'failed' ), true)
				&& self::VERDICT_MATCH === self::amount_verdict($existing_order, $due_minor)
			) {
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
		// Priced and totalled at the store's own precision: a currency switcher
		// answers wc_get_price_decimals() with the visitor's, and a 0-decimal
		// visitor would round 2,050.40 owed to an order of 2,050 (Codex r11 1).
		// Every step of the build sits inside the try, so the pin is removed
		// whatever throws (Codex r12 1).
		$pin_precision = static function () {
			return self::debt_decimals();
		};
		add_filter('wc_get_price_decimals', $pin_precision, PHP_INT_MAX);
		try {
			$order = self::build_remaining_order($booking_id, $customer_user_id, $product, $vehicle_name, $remaining_amount, $due_minor, (int) $original_order_id);
		} finally {
			remove_filter('wc_get_price_decimals', $pin_precision, PHP_INT_MAX);
		}

		if (is_wp_error($order)) {
			return $order;
		}

		// Persist remaining order ID on the booking so we can reuse it
		update_post_meta($booking_id, '_mhmrentiva_remaining_order_id', $order->get_id());

		return $order;
	}

	/**
	 * The remaining-payment order itself, built, totalled and saved. Called with
	 * the price precision pinned to the debt's own.
	 */
	private static function build_remaining_order(int $booking_id, int $customer_user_id, \WC_Product $product, string $vehicle_name, float|string $remaining_amount, int $due_minor, int $original_order_id): \WC_Order|\WP_Error
	{
		$order = wc_create_order(array(
			'customer_id' => $customer_user_id,
			'status'      => 'pending',
		));

		if (is_wp_error($order)) {
			return new \WP_Error('mhmrentiva_order_create_failed', __('Failed to create payment order. Please try again.', 'mhm-rentiva'));
		}

		// The remaining amount is a number in the store's own currency.
		// wc_create_order() takes the filterable get_woocommerce_currency(), which
		// a per-visitor currency switcher answers with the visitor's currency, and
		// nothing converts the number (Codex r10 1).
		$order->set_currency(self::debt_currency());

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
			// The order's billing block stays WooCommerce's own: its billing phone
			// first, like the address fields beside it; CustomerContact only fills
			// a phone WooCommerce does not have.
			$order->set_billing_phone( (string) get_user_meta($customer_user_id, 'billing_phone', true) ?: \MHMRentiva\Admin\Customers\CustomerContact::phone( (int) $customer_user_id));
			$order->set_billing_address_1( (string) get_user_meta($customer_user_id, 'billing_address_1', true));
			$order->set_billing_city( (string) get_user_meta($customer_user_id, 'billing_city', true));
			$order->set_billing_postcode( (string) get_user_meta($customer_user_id, 'billing_postcode', true));
			$order->set_billing_country( (string) get_user_meta($customer_user_id, 'billing_country', true));
		}

		// HPOS-compatible order meta
		$order->update_meta_data('_mhmrentiva_booking_id', $booking_id);
		$order->update_meta_data('_mhmrentiva_is_remaining_payment', '1');
		// The gross amount this order was built for, in minor units: its own
		// total is recalculated with tax for the billing address and can differ.
		// The pay-time check compares the booking's current remaining with this.
		$order->update_meta_data('_mhmrentiva_remaining_due_minor', (string) $due_minor);
		if ($original_order_id) {
			$order->update_meta_data('_mhmrentiva_original_order_id', $original_order_id);
		}

		$order->calculate_totals();
		$order->save();

		return $order;
	}
}
