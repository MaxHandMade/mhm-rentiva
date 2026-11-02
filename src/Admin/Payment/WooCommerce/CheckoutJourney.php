<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Payment\WooCommerce;

use MHMRentiva\Admin\Addons\AddonManager;
use MHMRentiva\Admin\Booking\Pricing\RentalQuote;
use MHMRentiva\Admin\Core\AssetManager;
use MHMRentiva\Admin\Core\Utilities\Templates;
use MHMRentiva\Admin\Frontend\Account\AccountController;
use MHMRentiva\Admin\Frontend\Account\BookingSummaryPage;
use MHMRentiva\Admin\Frontend\Account\WooCommerceIntegration;
use MHMRentiva\Admin\Frontend\Shortcodes\Rvs\RvsBreakdown;
use MHMRentiva\Admin\Frontend\Shortcodes\Rvs\RvsSteps;
use MHMRentiva\Admin\Settings\Core\SettingsCore;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * The booking journey on WooCommerce's classic checkout and thank-you pages.
 *
 * The checkout shows step 2 of the journey (RvsSteps) and a Rentiva summary --
 * vehicle, window, breakdown -- above the checkout form; the thank-you page
 * shows step 3 and the same summary under "Your booking". Both draw only for
 * exactly one rental and nothing else (a transfer is the add-on's surface);
 * the thank-you page also for an order of several rentals, one card each.
 *
 * The summary never re-prices: it is built from the snapshot the cart line or
 * the order line carries (quote_from_snapshot()), so its total is the one
 * WooCommerce charges. Styles are enqueued on wp_enqueue_scripts
 * (WooCommerceBridge::enqueue_checkout_styles()); the hooks here only draw.
 *
 * A Cart or Checkout block never fires these hooks, so pre_render_block() draws
 * the classic page in its place: the cart on every request (setting
 * mhmrentiva_classic_cart, default on), the checkout whenever a Rentiva line is
 * in the cart. Administrators are told (block_checkout_notice()); the Store API
 * safety net behind this is in WooCommerceBridge::store_api_order_processed().
 *
 * The `rvs-rental` body class (body_class()) marks the requests the Rentiva look
 * applies to. It follows the journey's rule: one rental and nothing else.
 */
final class CheckoutJourney {

	/**
	 * The fragment key WooCommerce's checkout.js replaces after update_checkout.
	 */
	public const SUMMARY_SELECTOR = '.rvs-journey__summary';

	public static function register(): void
	{
		add_action('woocommerce_before_checkout_form', array( self::class, 'render_checkout' ), 5, 0);
		add_action('woocommerce_before_thankyou', array( self::class, 'render_thankyou' ), 10, 1);
		add_filter('woocommerce_update_order_review_fragments', array( self::class, 'order_review_fragments' ), 10, 1);
		add_action('admin_notices', array( self::class, 'block_checkout_notice' ));
		add_filter('body_class', array( self::class, 'body_class' ));
		add_filter('pre_render_block', array( self::class, 'pre_render_block' ), 10, 2);
		add_filter('wp_resource_hints', array( self::class, 'resource_hints' ), 11, 2);
		RentalMoneyLabels::register();
		RentalCheckoutLook::register();
	}

	/**
	 * @param mixed $classes The body classes.
	 * @return mixed
	 */
	public static function body_class($classes)
	{
		if (is_array($classes) && self::is_rental_request()) {
			$classes[] = 'rvs-rental';
		}

		return $classes;
	}

	/**
	 * Is this the cart, checkout, thank-you or order-pay page of exactly one
	 * rental -- or the thank-you page of an order of rentals only? A transfer, a
	 * mixed cart and the shop's own orders are not. The order pages answer from
	 * the order (named with its key): their cart is empty or about something
	 * else.
	 */
	public static function is_rental_request(): bool
	{
		if (function_exists('is_order_received_page') && is_order_received_page()) {
			$order = self::requested_order('order-received');

			return null !== $order && ( self::is_rental_order($order) || count(self::order_rentals($order)) > 1 );
		}

		if (function_exists('is_checkout_pay_page') && is_checkout_pay_page()) {
			$order = self::requested_order('order-pay');

			return null !== $order && self::is_rental_order($order);
		}

		$on_page = ( function_exists('is_cart') && is_cart() ) || ( function_exists('is_checkout') && is_checkout() );

		return $on_page && null !== WooCommerceBridge::rental_cart_data();
	}

	/**
	 * One rental line, or the remaining payment of a rental: that order's line
	 * carries no snapshot, only the order's own `_mhmrentiva_is_remaining_payment`
	 * and `_mhmrentiva_booking_id` (RemainingPaymentHandler).
	 */
	public static function is_rental_order(\WC_Order $order): bool
	{
		if (null !== self::order_rental_data($order)) {
			return true;
		}
		if ('1' !== (string) $order->get_meta('_mhmrentiva_is_remaining_payment', true)) {
			return false;
		}

		return (int) get_post_meta(self::booking_id_of($order), '_mhmrentiva_transfer_origin_id', true) <= 0;
	}

	/**
	 * Draw the classic page instead of the Cart or Checkout block. Runs before
	 * the block does, so no checkout-draft order, React bundle or Store API
	 * hydration exists for it. A checkout endpoint needs nothing here: the block
	 * hands those to the shortcode itself, and the cart is empty by then.
	 *
	 * @param mixed                $pre          An earlier filter's answer.
	 * @param array<string, mixed> $parsed_block The block about to render.
	 * @return mixed
	 */
	public static function pre_render_block($pre, $parsed_block)
	{
		if (null !== $pre || is_admin() || ( defined('REST_REQUEST') && REST_REQUEST )) {
			return $pre;
		}

		$name = (string) ( $parsed_block['blockName'] ?? '' );
		if ('woocommerce/cart' === $name && self::classic_cart_enabled()) {
			return self::classic_page('cart');
		}
		if ('woocommerce/checkout' === $name && WooCommerceBridge::cart_has_booking()) {
			return self::classic_page('checkout');
		}

		return null;
	}

	/**
	 * WooCommerce's own classic-shortcode block loads the classic scripts in time
	 * on a block theme, where wp_head runs after the template.
	 */
	private static function classic_page(string $page): string
	{
		if (! \WP_Block_Type_Registry::get_instance()->is_registered('woocommerce/classic-shortcode')) {
			return do_shortcode('[woocommerce_' . $page . ']');
		}

		return render_block(
			array(
				'blockName'    => 'woocommerce/classic-shortcode',
				'attrs'        => array( 'shortcode' => $page ),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);
	}

	private static function classic_cart_enabled(): bool
	{
		return '1' === (string) SettingsCore::get('mhmrentiva_classic_cart', '1');
	}

	/**
	 * A Cart block page tells the browser to prerender the checkout. A block
	 * checkout could afford that; a classic one prints and spends the session's
	 * notices on that hidden request, so the customer never sees them.
	 *
	 * @param mixed $urls     The hints.
	 * @param mixed $relation prefetch, prerender...
	 * @return mixed
	 */
	public static function resource_hints($urls, $relation)
	{
		if ('prerender' !== $relation || ! is_array($urls) || ! function_exists('wc_get_page_id')) {
			return $urls;
		}
		if (! self::classic_cart_enabled() && ! WooCommerceBridge::cart_has_booking()) {
			return $urls;
		}

		$checkout = (string) get_permalink( (int) wc_get_page_id('checkout'));

		return array_filter(
			$urls,
			static fn($hint) => $checkout !== ( is_array($hint) ? (string) ( $hint['href'] ?? '' ) : (string) $hint )
		);
	}

	/**
	 * Step bar and summary above the checkout form, for a single-rental cart.
	 */
	public static function render_checkout(): void
	{
		$data = WooCommerceBridge::rental_cart_data();
		if (null === $data) {
			return;
		}

		self::render_journey(2, array( self::summary_vars($data, '', '') ));
	}

	/**
	 * Step bar, "Your booking" and the My bookings link on the order-received
	 * page. Read from the order line meta: this hook runs before the fallback
	 * that creates the booking, so the booking may not exist yet. A failed
	 * payment stays on step 2 -- WooCommerce's "pay again" follows below.
	 *
	 * @param mixed $order_id The order WooCommerce is thanking for.
	 */
	public static function render_thankyou($order_id): void
	{
		$order   = self::order( (int) $order_id);
		$rentals = null !== $order ? self::order_rentals($order) : array();
		if (null === $order || array() === $rentals) {
			return;
		}

		$link  = '';
		$owner = false;
		$user  = get_current_user_id();
		if (is_user_logged_in() && $user > 0 && (int) $order->get_customer_id() === $user) {
			$link  = wc_get_account_endpoint_url(AccountController::get_endpoint_slug('bookings', 'rentiva-bookings'));
			$owner = true;
		}

		// Several rentals in one order: one summary card each, under the same
		// step bar and heading; WooCommerce's order table below carries the
		// order's own totals. The order-level state names each deposit row.
		$state     = RentalMoneyLabels::order_state($order);
		$several   = count($rentals) > 1;
		$summaries = array();
		foreach ($rentals as $rental) {
			// The printable booking summary, once the booking exists and is the
			// customer's (the booking may not be created yet on this hook).
			$booking_id   = (int) $rental['item']->get_meta('_mhmrentiva_booking_id', true);
			$booking_id   = $booking_id > 0 || $several ? $booking_id : self::booking_id_of($order);
			$summary_link = $owner && $booking_id > 0 && WooCommerceIntegration::can_view_booking($booking_id) ? BookingSummaryPage::url($booking_id) : '';

			$vars = self::summary_vars($rental['data'], $several ? '' : __('Your booking', 'mhm-rentiva'), $link, $summary_link, $state);

			// The deposit row names what this order charges now, so its figure
			// is the order's, in the order's currency -- tax, coupon and fees
			// included -- not the snapshot's net deposit (Codex PR K 3; the bank
			// card below says the same). With several rentals, each line's own
			// total with its tax.
			if (is_array($vars['summary']['breakdown']['deposit'] ?? null)) {
				$charged = $several ? (float) $order->get_line_total($rental['item'], true) : (float) $order->get_total();
				$vars['summary']['breakdown']['deposit']['amount'] = $charged;
				$vars['summary']['breakdown']['deposit']['html']   = (string) wc_price($charged, array( 'currency' => $order->get_currency() ));
			}
			$summaries[] = $vars;
		}

		$failed = $order->has_status('failed');
		self::render_journey(
			$failed ? 2 : 3,
			$summaries,
			$failed ? '' : ( $several ? __('Your bookings are received', 'mhm-rentiva') : __('Your booking is received', 'mhm-rentiva') )
		);
	}

	/**
	 * The summary again after every update_checkout, so a payment-type switch
	 * (ajax_update_payment_type() writes it to the cart) shows or drops the
	 * deposit rows. The value is the element's outer HTML: checkout.js calls
	 * `$(key).replaceWith(value)`.
	 *
	 * @param mixed $fragments WooCommerce's order-review fragments.
	 * @return mixed
	 */
	public static function order_review_fragments($fragments)
	{
		if (! is_array($fragments)) {
			return $fragments;
		}

		$data = WooCommerceBridge::rental_cart_data();
		if (null === $data) {
			return $fragments;
		}

		$fragments[ self::SUMMARY_SELECTOR ] = (string) Templates::render('partials/rvs/journey-summary', self::summary_vars($data, '', ''), true);

		return $fragments;
	}

	/**
	 * Enqueue the journey styles and tokens for a rental request (body_class()):
	 * also the remaining-payment page, which draws no journey but reads the tokens.
	 * Called from WooCommerceBridge::enqueue_checkout_styles() (wp_enqueue_scripts),
	 * so the styles print in the head.
	 */
	public static function enqueue_assets(): void
	{
		if (self::journey_request() || self::is_rental_request()) {
			AssetManager::enqueue_rvs_journey();
		}
		RentalCheckoutLook::enqueue_assets();
	}

	/**
	 * Tell an administrator whose cart or checkout page uses a block what the
	 * customer gets instead, and that a shop order keeps the Checkout block.
	 */
	public static function block_checkout_notice(): void
	{
		if (! current_user_can('manage_options') || ! self::is_notice_screen() || ! ( self::checkout_uses_block() || self::cart_uses_block() )) {
			return;
		}

		printf(
			'<div class="notice notice-info"><p>%s</p></div>',
			esc_html__('Your cart or checkout page uses a WooCommerce block. Rentiva shows the classic cart, and the classic checkout whenever a booking is in the cart, so the booking steps and the payment-type choice keep working. An order without a booking still uses the Checkout block. To keep the Cart block, turn off "Classic cart page" in the Payment tab of the Rentiva settings.', 'mhm-rentiva')
		);
	}

	/**
	 * The booking's price as the snapshot carries it, in RentalQuote form for
	 * RvsBreakdown: one rental row (total less add-ons), no day lines, no
	 * weekend row. RentalPricing is not called.
	 *
	 * @param array<string, mixed> $snap Cart or order line booking data.
	 */
	public static function quote_from_snapshot(array $snap): RentalQuote
	{
		$addon_total = 0.0;
		foreach (self::addon_rows($snap) as $row) {
			$addon_total += $row['amount'];
		}
		$total = (float) ( $snap['total_price'] ?? 0 );

		return new RentalQuote(
			(int) ( $snap['rental_days'] ?? 0 ),
			array(),
			0.0,
			1.0,
			1.0,
			0,
			0.0,
			$total - $addon_total,
			$addon_total,
			$total
		);
	}

	/**
	 * The snapshot's add-on rows, labelled as the breakdown shows them.
	 *
	 * @param array<string, mixed> $snap
	 * @return list<array{label: string, amount: float}>
	 */
	private static function addon_rows(array $snap): array
	{
		$rows  = array();
		$lines = $snap['addon_lines'] ?? array();
		if (! is_array($lines)) {
			return $rows;
		}
		foreach ($lines as $line) {
			if (is_array($line)) {
				$rows[] = array(
					'label'  => AddonManager::line_label($line),
					'amount' => (float) ( $line['line_total'] ?? 0 ),
				);
			}
		}

		return $rows;
	}

	/**
	 * Steps and summary inside one shell.
	 *
	 * @param list<array<string, mixed>> $summaries summary_vars(), one per rental.
	 * @param string                     $headline  Heading above the steps; '' for none.
	 */
	private static function render_journey(int $step, array $summaries, string $headline = ''): void
	{
		echo '<div class="rvs-shell rvs-journey">';
		// The thank-you page's one "booking received" heading. The page title
		// stays WooCommerce's: a theme that hides it leaves this, a theme that
		// shows it reads "Order received" above (R14, Fable PR K M1).
		if ('' !== $headline) {
			echo '<h2 class="rvs-journey__headline">' . esc_html($headline) . '</h2>';
		}
		Templates::render(
			'partials/rvs/steps',
			array(
				'items' => RvsSteps::items($step),
			)
		);
		foreach ($summaries as $summary_vars) {
			Templates::render('partials/rvs/journey-summary', $summary_vars);
		}
		echo '</div>';
	}

	/**
	 * The variables of templates/partials/rvs/journey-summary.php.
	 *
	 * @param array<string, mixed> $snap Cart or order line booking data.
	 * @return array{summary: array<string, mixed>}
	 */
	private static function summary_vars(array $snap, string $heading, string $link, string $summary_link = '', string $state = RentalMoneyLabels::STATE_DUE): array
	{
		$vehicle_id = (int) ( $snap['vehicle_id'] ?? 0 );

		return array(
			'summary' => array(
				'heading'   => $heading,
				'title'     => $vehicle_id > 0 ? get_the_title($vehicle_id) : '',
				'image_id'  => $vehicle_id > 0 ? (int) get_post_thumbnail_id($vehicle_id) : 0,
				'dates'     => self::window_text($snap),
				'breakdown' => self::breakdown_of($snap, $state),
				'link'      => $link,
				'summary'   => $summary_link,
				'paid'      => RentalMoneyLabels::STATE_PAID === $state,
			),
		);
	}

	/**
	 * The snapshot's breakdown, its deposit row named after how the money stands.
	 * The rate is the snapshot's own: the setting may have changed since.
	 *
	 * @param array<string, mixed> $snap Cart or order line booking data.
	 * @return array<string, mixed> RvsBreakdown::from_quote().
	 */
	public static function breakdown_of(array $snap, string $state = RentalMoneyLabels::STATE_DUE): array
	{
		$deposit = 'full' === (string) ( $snap['payment_type'] ?? '' ) ? 0.0 : (float) ( $snap['deposit_amount'] ?? 0 );
		$rate    = (float) ( $snap['deposit_percentage'] ?? 0 );

		$breakdown = RvsBreakdown::from_quote(self::quote_from_snapshot($snap), $deposit, self::addon_rows($snap), $rate);
		if (null !== $breakdown['deposit']) {
			$breakdown['deposit']['label'] = RentalMoneyLabels::deposit_label($state, $rate);
		}

		return $breakdown;
	}

	/**
	 * Does this rental order charge a deposit now and leave the rest for pickup?
	 * A remaining-payment order carries no snapshot and does not.
	 */
	public static function order_is_split(\WC_Order $order): bool
	{
		$snap = self::order_rental_data($order);
		if (null === $snap) {
			return false;
		}

		return null !== self::breakdown_of($snap)['deposit'];
	}

	/**
	 * "6 November 2026 10:00 → 9 November 2026 10:00 · 3 days", from the
	 * snapshot's dates and day count; '' without both dates.
	 *
	 * @param array<string, mixed> $snap
	 */
	private static function window_text(array $snap): string
	{
		$start = (string) ( $snap['pickup_date'] ?? '' );
		$end   = (string) ( $snap['dropoff_date'] ?? '' );
		if ('' === $start || '' === $end) {
			return '';
		}

		$format = (string) get_option('date_format', 'Y-m-d');
		$from   = trim(date_i18n($format, (int) strtotime($start . ' 00:00:00 UTC')) . ' ' . (string) ( $snap['pickup_time'] ?? '' ));
		$to     = trim(date_i18n($format, (int) strtotime($end . ' 00:00:00 UTC')) . ' ' . (string) ( $snap['dropoff_time'] ?? '' ));
		$text   = $from . ' → ' . $to;

		$days = (int) ( $snap['rental_days'] ?? 0 );
		if ($days > 0) {
			/* translators: %d: number of rental days. */
			$text .= ' · ' . sprintf(_n('%d day', '%d days', $days, 'mhm-rentiva'), $days);
		}

		return $text;
	}

	/**
	 * The booking the order created: the order's own meta, else its line's;
	 * 0 when none exists yet.
	 */
	public static function booking_id_of(\WC_Order $order): int
	{
		$booking_id = (int) $order->get_meta('_mhmrentiva_booking_id', true);
		if ($booking_id > 0) {
			return $booking_id;
		}
		foreach ($order->get_items() as $item) {
			$booking_id = (int) $item->get_meta('_mhmrentiva_booking_id', true);
			if ($booking_id > 0) {
				return $booking_id;
			}
		}

		return 0;
	}

	/**
	 * The order an order-received or order-pay URL names, only with its own key:
	 * WooCommerce draws nothing for a wrong key, and neither does Rentiva -- a
	 * guessed id must not tell whether that order is a rental (Fable PR K m2).
	 */
	private static function requested_order(string $endpoint): ?\WC_Order
	{
		global $wp;

		$order = self::order(isset($wp->query_vars[ $endpoint ]) ? absint($wp->query_vars[ $endpoint ]) : 0);
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The order key is the credential WooCommerce itself checks on these pages (WC_Shortcode_Checkout reads the same parameter); read-only, nothing is written.
		$key = sanitize_text_field(wp_unslash( (string) ( $_GET['key'] ?? '' )));
		if (null === $order || '' === $key || ! hash_equals($order->get_order_key(), $key)) {
			return null;
		}

		return $order;
	}

	/**
	 * The order this order-pay URL names, with its own key; null otherwise.
	 */
	public static function pay_page_order(): ?\WC_Order
	{
		return self::requested_order('order-pay');
	}

	/**
	 * Every line of the order, when every line is a rental (no shop product, no
	 * transfer): its line and booking data. Empty otherwise.
	 *
	 * @return list<array{item: \WC_Order_Item, data: array<string, mixed>}>
	 */
	public static function order_rentals(\WC_Order $order): array
	{
		$rentals = array();
		foreach ($order->get_items() as $item) {
			$data = $item->get_meta('_mhmrentiva_booking_data', true);
			if (! is_array($data) || WooCommerceBridge::is_transfer_booking($data)) {
				return array();
			}
			$rentals[] = array(
				'item' => $item,
				'data' => $data,
			);
		}

		return $rentals;
	}

	private static function order(int $order_id): ?\WC_Order
	{
		if ($order_id <= 0 || ! function_exists('wc_get_order')) {
			return null;
		}
		$order = wc_get_order($order_id);

		return $order instanceof \WC_Order ? $order : null;
	}

	/**
	 * The booking data of the order's one line when that line is a rental
	 * (`_mhmrentiva_booking_data`) and the order has no other line; null for
	 * none, several, a mixed order or a transfer.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function order_rental_data(\WC_Order $order): ?array
	{
		$items = $order->get_items();
		if (1 !== count($items)) {
			return null;
		}

		$item = reset($items);
		$data = $item instanceof \WC_Order_Item ? $item->get_meta('_mhmrentiva_booking_data', true) : null;
		if (! is_array($data) || WooCommerceBridge::is_transfer_booking($data)) {
			return null;
		}

		return $data;
	}

	/**
	 * Will this front-end request draw the journey?
	 */
	private static function journey_request(): bool
	{
		if (function_exists('is_order_received_page') && is_order_received_page()) {
			$order = self::requested_order('order-received');

			return null !== $order && array() !== self::order_rentals($order);
		}

		return function_exists('is_checkout') && is_checkout() && null !== WooCommerceBridge::rental_cart_data();
	}

	/**
	 * Rentiva's own admin screens and the plugins screen.
	 */
	private static function is_notice_screen(): bool
	{
		$screen = function_exists('get_current_screen') ? get_current_screen() : null;
		if (! $screen) {
			return false;
		}

		return 'plugins' === $screen->id || AssetManager::is_rentiva_admin_page();
	}

	/**
	 * Does the cart page use the Cart block? WooCommerce's own helper, as in
	 * checkout_uses_block().
	 */
	private static function cart_uses_block(): bool
	{
		$utils = '\Automattic\WooCommerce\Blocks\Utils\CartCheckoutUtils';

		return class_exists($utils) && is_callable(array( $utils, 'is_cart_block_default' )) && (bool) call_user_func(array( $utils, 'is_cart_block_default' ));
	}

	/**
	 * Does the checkout use the Checkout block? WooCommerce's own helper first
	 * (it also reads a block theme's checkout template); without it, the
	 * checkout page's content.
	 */
	private static function checkout_uses_block(): bool
	{
		$utils = '\Automattic\WooCommerce\Blocks\Utils\CartCheckoutUtils';
		if (class_exists($utils) && is_callable(array( $utils, 'is_checkout_block_default' ))) {
			return (bool) call_user_func(array( $utils, 'is_checkout_block_default' ));
		}

		if (! function_exists('wc_get_page_id')) {
			return false;
		}
		$page_id = (int) wc_get_page_id('checkout');
		if ($page_id <= 0) {
			return false;
		}
		$page = get_post($page_id);

		return $page instanceof \WP_Post && has_block('woocommerce/checkout', $page);
	}
}
