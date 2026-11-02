<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Payment\WooCommerce;

use MHMRentiva\Admin\Core\AssetManager;
use MHMRentiva\Admin\Core\ShortcodeUrlManager;
use MHMRentiva\Admin\Core\Utilities\Templates;
use MHMRentiva\Admin\Frontend\Account\AccountController;
use MHMRentiva\Admin\Frontend\Account\WooCommerceIntegration;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * The look of WooCommerce's cart, checkout, thank-you and order-pay pages
 * (PR K, R10, R12-R16): which requests load rvs-checkout.css, the hooks the
 * stylesheet relies on, and the few words those pages need to match the
 * canvas (`Sepet`, `Odeme`, `Tesekkur`).
 *
 * Two reaches. The whole page wears the Rentiva look only on a request of
 * exactly one rental, and on the thank-you page of an order of rentals only
 * (`body.rvs-rental`, CheckoutJourney::is_rental_request()).
 * A mixed or transfer cart keeps the theme's cart and checkout; only Rentiva's
 * own parts are styled there -- its cart and review lines (`rvs-cart-line`) and
 * the payment-type card (`#mhm-booking-payment-type`, already an `.rvs-shell`).
 * The shop's own orders get neither.
 */
final class RentalCheckoutLook {

	/**
	 * The cart and review row class of a Rentiva line.
	 */
	public const LINE_CLASS = 'rvs-cart-line';

	/**
	 * The cart table's column heads for one rental, as the canvas names them.
	 *
	 * @var array<string, string>
	 */
	private static array $heads = array();

	public static function register(): void
	{
		add_filter('woocommerce_cart_item_class', array( self::class, 'line_class' ), 10, 2);
		add_filter('woocommerce_mini_cart_item_class', array( self::class, 'line_class' ), 10, 2);
		add_filter('woocommerce_order_item_class', array( self::class, 'order_line_class' ), 10, 2);

		add_action('woocommerce_before_cart_table', array( self::class, 'open_cart_heads' ), 10, 0);
		add_action('woocommerce_after_cart_table', array( self::class, 'close_cart_heads' ), 10, 0);
		add_action('wp_footer', array( self::class, 'close_cart_heads' ), 0, 0);

		add_action('woocommerce_cart_is_empty', array( self::class, 'render_empty_cart' ), 5, 0);
		add_filter('woocommerce_return_to_shop_redirect', array( self::class, 'return_to_shop_url' ), 10, 1);
		add_filter('woocommerce_return_to_shop_text', array( self::class, 'return_to_shop_text' ), 10, 1);

		// The page title stays WooCommerce's ("Order received"): the journey draws
		// the one "Your booking is received" heading (CheckoutJourney::render_journey()),
		// so a theme that prints its page title shows two different lines, never
		// the same heading twice (Fable PR K M1).
		add_filter('woocommerce_thankyou_order_received_text', array( self::class, 'order_received_text' ), 10, 2);
		// After WooCommerce's own bank details (WC_Gateway_BACS::thankyou_page, 10).
		add_action('woocommerce_thankyou_bacs', array( self::class, 'render_bank_transfer' ), 20, 1);

		// The Cart block and the Mini-Cart block draw their images from the Store
		// API, which knows no vehicle: the injector runs only where they render.
		add_filter('render_block_woocommerce/cart', array( self::class, 'with_block_image_injector' ), 10, 1);
		add_filter('render_block_woocommerce/mini-cart', array( self::class, 'with_block_image_injector' ), 10, 1);
	}

	/**
	 * Load rvs-checkout.css: on a rental's own pages, on a cart or checkout that
	 * holds any Rentiva line (its parts only), and on the empty cart that
	 * points to the vehicles. Called on wp_enqueue_scripts, so it prints in the head.
	 */
	public static function enqueue_assets(): void
	{
		if (self::needs_styles()) {
			AssetManager::enqueue_rvs_checkout();
		}
	}

	private static function needs_styles(): bool
	{
		if (CheckoutJourney::is_rental_request()) {
			return true;
		}

		$on_cart     = function_exists('is_cart') && is_cart();
		$on_checkout = function_exists('is_checkout') && is_checkout()
			&& ! ( function_exists('is_order_received_page') && is_order_received_page() )
			&& ! ( function_exists('is_checkout_pay_page') && is_checkout_pay_page() );
		if (! $on_cart && ! $on_checkout) {
			return false;
		}
		if (WooCommerceBridge::cart_has_booking()) {
			return true;
		}

		return $on_cart && self::cart_is_empty() && self::empty_cart_to_vehicles();
	}

	private static function cart_is_empty(): bool
	{
		return function_exists('WC') && null !== \WC()->cart && \WC()->cart->is_empty();
	}

	/**
	 * @param mixed $class     The row's class.
	 * @param mixed $cart_item The cart line.
	 * @return mixed
	 */
	public static function line_class($class, $cart_item = null)
	{
		if (! is_string($class) || ! is_array($cart_item)) {
			return $class;
		}
		if (! isset($cart_item['mhmrentiva_booking_id']) && ! isset($cart_item['mhmrentiva_booking_data'])) {
			return $class;
		}

		return trim($class . ' ' . self::LINE_CLASS);
	}

	/**
	 * The same class on an order table's Rentiva line (thank-you, order-pay,
	 * My Account's order view): the line carries the booking or its id.
	 *
	 * @param mixed $class The row's class.
	 * @param mixed $item  The order line.
	 * @return mixed
	 */
	public static function order_line_class($class, $item = null)
	{
		if (! is_string($class) || ! ( $item instanceof \WC_Order_Item )) {
			return $class;
		}
		if ('' === (string) $item->get_meta('_mhmrentiva_booking_id', true) && ! is_array($item->get_meta('_mhmrentiva_booking_data', true))) {
			return $class;
		}

		return trim($class . ' ' . self::LINE_CLASS);
	}

	/**
	 * Column heads of one rental's cart table: the line is a rental and what it
	 * shows is what is charged now. WooCommerce prints them, and the same words
	 * as each cell's mobile label, between these two hooks.
	 */
	public static function open_cart_heads(): void
	{
		if (! CheckoutJourney::is_rental_request()) {
			return;
		}

		self::$heads = array(
			'Product'  => __('Rental', 'mhm-rentiva'),
			'Subtotal' => __('Due now', 'mhm-rentiva'),
		);
		add_filter('gettext_woocommerce', array( self::class, 'relabel_head' ), 10, 2);
	}

	public static function close_cart_heads(): void
	{
		remove_filter('gettext_woocommerce', array( self::class, 'relabel_head' ), 10);
		self::$heads = array();
	}

	/**
	 * @param mixed $translation The translated text.
	 * @param mixed $text        The source text.
	 * @return mixed
	 */
	public static function relabel_head($translation, $text)
	{
		return is_string($text) && isset(self::$heads[ $text ]) ? self::$heads[ $text ] : $translation;
	}

	/**
	 * Does an empty cart point to the vehicles instead of the shop? Yes, unless
	 * a site that also sells its own products turns it off.
	 */
	public static function empty_cart_to_vehicles(): bool
	{
		return (bool) apply_filters('mhmrentiva_empty_cart_to_vehicles', true);
	}

	/**
	 * The vehicle list, as My Account's empty states find it.
	 */
	public static function vehicles_url(): string
	{
		$url = ShortcodeUrlManager::get_page_url('rentiva_vehicles_list');
		if (! $url) {
			$url = get_post_type_archive_link('mhmrentiva_vehicle');
		}
		if (! $url) {
			$url = home_url('/');
		}

		// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Public filter name kept for backward compatibility.
		return (string) apply_filters('mhmrentiva_vehicles_page_url', (string) $url);
	}

	/**
	 * The empty cart (canvas `Sepet`, "sepet boş"): a card that sends the
	 * customer to the vehicles, in place of WooCommerce's notice. It keeps the
	 * `wc-empty-cart-message` wrapper: cart.js looks for it after the last line
	 * is removed.
	 */
	public static function render_empty_cart(): void
	{
		if (! self::empty_cart_to_vehicles()) {
			return;
		}

		remove_action('woocommerce_cart_is_empty', 'wc_empty_cart_message', 10);
		Templates::render('partials/rvs/cart-empty', array( 'vehicles_url' => self::vehicles_url() ));
	}

	/**
	 * @param mixed $url The shop page.
	 * @return mixed
	 */
	public static function return_to_shop_url($url)
	{
		return self::empty_cart_to_vehicles() ? self::vehicles_url() : $url;
	}

	/**
	 * @param mixed $text "Return to shop".
	 * @return mixed
	 */
	public static function return_to_shop_text($text)
	{
		return self::empty_cart_to_vehicles() ? __('Browse vehicles', 'mhm-rentiva') : $text;
	}

	/**
	 * @param mixed $message WooCommerce's thank-you sentence (escaped HTML).
	 * @param mixed $order   The order, or false.
	 * @return mixed
	 */
	public static function order_received_text($message, $order = null)
	{
		if (! ( $order instanceof \WC_Order ) || ! CheckoutJourney::is_rental_order($order)) {
			return $message;
		}

		return esc_html__('Thank you. We have your booking; its details are below.', 'mhm-rentiva');
	}

	/**
	 * What to send and how to send the receipt, under WooCommerce's bank
	 * details (R10). The figure to send is the order's total in the order's
	 * currency -- what the bank must receive, tax, coupons and fees included;
	 * the snapshot's deposit is a net price and can differ (Codex PR K 3). Only
	 * the label says "deposit" when the order charges one. What is left for
	 * pickup is the snapshot's. A logged-in customer uploads the receipt on the
	 * booking in My Account; a guest has no account page, so the receipt goes
	 * to the shop's email address.
	 *
	 * @param mixed $order_id The order WooCommerce is thanking for.
	 */
	public static function render_bank_transfer($order_id): void
	{
		$order = wc_get_order( (int) $order_id);
		if (! ( $order instanceof \WC_Order ) || $order->has_status(array( 'failed', 'cancelled', 'refunded' ))) {
			return;
		}
		// Once the shop has confirmed the transfer, nothing is left to send and
		// no receipt to upload: the same state rule as the deposit label, which
		// then reads "Deposit paid" (PR bot P2).
		if (RentalMoneyLabels::STATE_PAID === RentalMoneyLabels::order_state($order)) {
			return;
		}
		// One rental, its remaining payment, or several rentals (each summary
		// card above names its own pickup figure; the card here sends the total).
		if (! CheckoutJourney::is_rental_order($order) && count(CheckoutJourney::order_rentals($order)) < 2) {
			return;
		}

		$snap      = CheckoutJourney::order_rental_data($order);
		$breakdown = null !== $snap ? CheckoutJourney::breakdown_of($snap, RentalMoneyLabels::STATE_TO_SEND) : null;

		$to_send = array(
			'label' => null !== $breakdown && null !== $breakdown['deposit'] ? (string) $breakdown['deposit']['label'] : __('To send', 'mhm-rentiva'),
			// wc_price() with the order's currency is what WC_Order::get_formatted_order_total()
			// prints, without its tax suffix.
			'html'  => (string) wc_price( (float) $order->get_total(), array( 'currency' => $order->get_currency() )),
		);

		$upload_url = '';
		$user       = get_current_user_id();
		if (is_user_logged_in() && $user > 0 && (int) $order->get_customer_id() === $user) {
			$booking_id = CheckoutJourney::booking_id_of($order);
			$upload_url = $booking_id > 0 && WooCommerceIntegration::can_view_booking($booking_id)
				? AccountController::get_booking_view_url($booking_id)
				: wc_get_account_endpoint_url(AccountController::get_endpoint_slug('bookings', 'rentiva-bookings'));
		}

		Templates::render(
			'partials/rvs/checkout-bank-transfer',
			array(
				'transfer' => array(
					'reference'   => (string) $order->get_order_number(),
					'to_send'     => $to_send,
					'on_pickup'   => null !== $breakdown ? $breakdown['on_pickup'] : null,
					'upload_url'  => $upload_url,
					'store_email' => self::store_email(),
				),
			)
		);
	}

	/**
	 * The address the shop's emails come from, else the site's admin address.
	 */
	private static function store_email(): string
	{
		$email = sanitize_email( (string) get_option('woocommerce_email_from_address', ''));
		if ('' === $email) {
			$email = sanitize_email( (string) get_option('admin_email', ''));
		}

		return $email;
	}

	/**
	 * The vehicle images of the cart, for the Cart and Mini-Cart blocks' script.
	 * Enqueued while the block renders, so it prints in the footer of exactly
	 * the pages that draw one.
	 *
	 * @param mixed $html The block's markup.
	 * @return mixed
	 */
	public static function with_block_image_injector($html)
	{
		if (is_admin() || ! WooCommerceBridge::cart_has_booking() || wp_script_is('mhm-rentiva-woocommerce-cart-block', 'enqueued')) {
			return $html;
		}

		wp_enqueue_script(
			'mhm-rentiva-woocommerce-cart-block',
			MHMRENTIVA_PLUGIN_URL . 'assets/js/payment/woocommerce-cart-block.js',
			array(),
			MHMRENTIVA_VERSION,
			true
		);
		wp_localize_script('mhm-rentiva-woocommerce-cart-block', 'mhmRentivaCartImages', WooCommerceBridge::cart_vehicle_images());

		return $html;
	}
}
