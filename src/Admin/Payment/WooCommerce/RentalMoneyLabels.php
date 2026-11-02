<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Payment\WooCommerce;

use MHMRentiva\Admin\Core\CurrencyHelper;
use MHMRentiva\Admin\Core\Utilities\Templates;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * What the money rows of one rental's cart, checkout and thank-you pages are
 * called. A rental line is priced at what is charged now, so WooCommerce's
 * "Total" is the amount due now, not the rental's total: that one sits in the
 * breakdown card, under its own "Total".
 *
 * The templates hardcode those labels, so they change through gettext, and only
 * between the two hooks around the row: the filter is added right before the
 * label is translated and removed right after. Everything else keeps
 * WooCommerce's words: emails, My Account and the order-pay form never open a
 * window. No amount is computed here.
 */
final class RentalMoneyLabels {

	/**
	 * How an order's money stands, in this order: a failed, cancelled or refunded
	 * order is due; money that arrived (WooCommerce wrote a paid date) is paid,
	 * whatever the method -- a card order, a bank order the shop confirmed, a
	 * cash order completed at pickup; an offline method without that date is
	 * still to send or to pay at pickup; anything else is due.
	 *
	 * One exception to the paid date: the cash gateway puts its order straight
	 * into `processing`, and WooCommerce writes the paid date on reaching that
	 * status (WC_Order::maybe_set_date_paid(); the booking product is virtual
	 * but not downloadable, so it "needs processing"). A cash order still in
	 * `processing` has not been paid.
	 */
	public const STATE_DUE       = 'due';
	public const STATE_PAID      = 'paid';
	public const STATE_TO_SEND   = 'to_send';
	public const STATE_AT_PICKUP = 'at_pickup';

	private static string $from = '';

	private static string $to = '';

	private static string $order_total_label = '';

	private static int $order_total_id = 0;

	public static function register(): void
	{
		add_action('woocommerce_before_cart_totals', array( self::class, 'render_cart_card' ), 10, 0);

		add_action('woocommerce_cart_totals_before_order_total', array( self::class, 'open_cart_window' ), 10, 0);
		add_action('woocommerce_cart_totals_after_order_total', array( self::class, 'close_window' ), 10, 0);
		add_action('woocommerce_review_order_before_order_total', array( self::class, 'open_cart_window' ), 10, 0);
		add_action('woocommerce_review_order_after_order_total', array( self::class, 'close_window' ), 10, 0);

		add_action('woocommerce_before_thankyou', array( self::class, 'open_thankyou_window' ), 20, 1);
		add_action('woocommerce_thankyou', array( self::class, 'close_window' ), 0, 0);

		add_action('woocommerce_order_details_before_order_table', array( self::class, 'open_order_details_window' ), 10, 1);
		add_action('woocommerce_order_details_after_order_table', array( self::class, 'close_order_details_window' ), 10, 0);

		// A theme's own template may leave out a closing hook: no label outlives the page.
		add_action('wp_footer', array( self::class, 'close_all' ), 0, 0);
		add_action('shutdown', array( self::class, 'close_all' ), 0, 0);
	}

	public static function close_all(): void
	{
		self::close_window();
		self::close_order_details_window();
	}

	/**
	 * The rental's breakdown above WooCommerce's cart totals, for one rental and
	 * nothing else. The same data as the checkout's summary.
	 */
	public static function render_cart_card(): void
	{
		$data = WooCommerceBridge::rental_cart_data();
		if (null === $data) {
			return;
		}

		echo '<div class="rvs-shell rvs-cart-totals-card">';
		Templates::render(
			'partials/rvs/breakdown',
			array(
				'breakdown'   => CheckoutJourney::breakdown_of($data),
				'collapsible' => false,
			)
		);
		echo '</div>';
	}

	public static function open_cart_window(): void
	{
		if (null !== WooCommerceBridge::rental_cart_data()) {
			self::open('Total', __('Due now', 'mhm-rentiva'));
		}
	}

	/**
	 * The thank-you overview's "Total:". The window closes before the gateway's
	 * own output, or at the latest on woocommerce_thankyou.
	 *
	 * @param mixed $order_id The order WooCommerce is thanking for.
	 */
	public static function open_thankyou_window($order_id): void
	{
		$label = self::thankyou_total_label( (int) $order_id);
		if ('' === $label) {
			return;
		}

		$order = wc_get_order( (int) $order_id);
		if ($order instanceof \WC_Order) {
			add_action('woocommerce_thankyou_' . $order->get_payment_method(), array( self::class, 'close_window' ), 0, 0);
		}

		self::open('Total:', $label);
	}

	/**
	 * The order table on the thank-you page. The same template serves My
	 * Account, so the page is checked; emails do not fire this hook.
	 *
	 * @param mixed $order The order of the table.
	 */
	public static function open_order_details_window($order): void
	{
		if (! ( $order instanceof \WC_Order ) || ! is_order_received_page()) {
			return;
		}

		self::$order_total_label = self::thankyou_total_label($order->get_id());
		self::$order_total_id    = $order->get_id();
		if ('' !== self::$order_total_label) {
			add_filter('woocommerce_get_order_item_totals', array( self::class, 'relabel_order_total' ), 10, 2);
		}
	}

	public static function close_order_details_window(): void
	{
		remove_filter('woocommerce_get_order_item_totals', array( self::class, 'relabel_order_total' ), 10);
		self::$order_total_label = '';
		self::$order_total_id    = 0;
	}

	/**
	 * Only the order whose table is being drawn.
	 *
	 * @param mixed $rows  WooCommerce's order total rows.
	 * @param mixed $order The order the rows belong to.
	 * @return mixed
	 */
	public static function relabel_order_total($rows, $order = null)
	{
		if (! ( $order instanceof \WC_Order ) || $order->get_id() !== self::$order_total_id) {
			return $rows;
		}
		if (is_array($rows) && isset($rows['order_total']) && is_array($rows['order_total'])) {
			$rows['order_total']['label'] = self::$order_total_label;
		}

		return $rows;
	}

	public static function close_window(): void
	{
		remove_filter('gettext_woocommerce', array( self::class, 'relabel' ), 10);
		self::$from = '';
		self::$to   = '';
	}

	/**
	 * @param mixed $translation The translated text.
	 * @param mixed $text        The source text.
	 * @return mixed
	 */
	public static function relabel($translation, $text)
	{
		return $text === self::$from ? self::$to : $translation;
	}

	/**
	 * Money received, to be sent, to be paid at pickup, or none of these yet.
	 */
	public static function order_state(\WC_Order $order): string
	{
		if ($order->has_status(array( 'failed', 'cancelled', 'refunded' ))) {
			return self::STATE_DUE;
		}

		$method = $order->get_payment_method();
		if (null !== $order->get_date_paid() && ! ( 'cod' === $method && $order->has_status('processing') )) {
			return self::STATE_PAID;
		}

		if (in_array($method, array( 'bacs', 'cheque' ), true)) {
			return self::STATE_TO_SEND;
		}
		if ('cod' === $method) {
			return self::STATE_AT_PICKUP;
		}

		return self::STATE_DUE;
	}

	/**
	 * The deposit row's label. The rate is the one the snapshot was priced with;
	 * without one the label has no rate, it is never worked out from the amounts.
	 */
	public static function deposit_label(string $state, float $rate): string
	{
		if ($rate <= 0) {
			switch ($state) {
				case self::STATE_PAID:
					return __('Deposit paid', 'mhm-rentiva');
				case self::STATE_TO_SEND:
					return __('Deposit to send', 'mhm-rentiva');
				case self::STATE_AT_PICKUP:
					return __('Deposit due at pickup', 'mhm-rentiva');
				default:
					return __('Deposit', 'mhm-rentiva');
			}
		}

		$percent = CurrencyHelper::format_percentage($rate);
		switch ($state) {
			case self::STATE_PAID:
				/* translators: %s: deposit percentage, e.g. "10%". */
				return sprintf(__('Deposit paid (%s)', 'mhm-rentiva'), $percent);
			case self::STATE_TO_SEND:
				/* translators: %s: deposit percentage, e.g. "10%". */
				return sprintf(__('Deposit to send (%s)', 'mhm-rentiva'), $percent);
			case self::STATE_AT_PICKUP:
				/* translators: %s: deposit percentage, e.g. "10%". */
				return sprintf(__('Deposit due at pickup (%s)', 'mhm-rentiva'), $percent);
			default:
				/* translators: %s: deposit percentage, e.g. "10%". */
				return sprintf(__('Deposit (%s)', 'mhm-rentiva'), $percent);
		}
	}

	/**
	 * The thank-you label of the order's total; '' keeps WooCommerce's "Total:"
	 * (a shop order, a failed order, a card order not paid yet). The summary's
	 * "Due at pickup" row is the remaining amount, so a deposit's own row says
	 * what it is rather than repeating that name for another figure.
	 */
	private static function thankyou_total_label(int $order_id): string
	{
		$order = $order_id > 0 ? wc_get_order($order_id) : null;
		if (! ( $order instanceof \WC_Order ) || ! CheckoutJourney::is_rental_order($order)) {
			return '';
		}

		$is_deposit = CheckoutJourney::order_is_split($order);
		switch (self::order_state($order)) {
			case self::STATE_PAID:
				return __('Paid:', 'mhm-rentiva');
			case self::STATE_TO_SEND:
				return $is_deposit ? __('Deposit to send:', 'mhm-rentiva') : __('To send:', 'mhm-rentiva');
			case self::STATE_AT_PICKUP:
				return $is_deposit ? __('Deposit due at pickup:', 'mhm-rentiva') : __('Due at pickup:', 'mhm-rentiva');
			default:
				return '';
		}
	}

	private static function open(string $from, string $to): void
	{
		self::$from = $from;
		self::$to   = $to;
		add_filter('gettext_woocommerce', array( self::class, 'relabel' ), 10, 2);
	}
}
