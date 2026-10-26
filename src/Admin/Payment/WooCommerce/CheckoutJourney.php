<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Payment\WooCommerce;

use MHMRentiva\Admin\Addons\AddonManager;
use MHMRentiva\Admin\Booking\Pricing\RentalQuote;
use MHMRentiva\Admin\Core\AssetManager;
use MHMRentiva\Admin\Core\Utilities\Templates;
use MHMRentiva\Admin\Frontend\Account\AccountController;
use MHMRentiva\Admin\Frontend\Shortcodes\Rvs\RvsBreakdown;
use MHMRentiva\Admin\Frontend\Shortcodes\Rvs\RvsSteps;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * The booking journey on WooCommerce's classic checkout and thank-you pages.
 *
 * The checkout shows step 2 of the journey (RvsSteps) and a Rentiva summary --
 * vehicle, window, breakdown -- above the checkout form; the thank-you page
 * shows step 3 and the same summary under "Your booking". Both draw only for
 * exactly one rental and nothing else (a transfer is the add-on's surface).
 *
 * The summary never re-prices: it is built from the snapshot the cart line or
 * the order line carries (quote_from_snapshot()), so its total is the one
 * WooCommerce charges. Styles are enqueued on wp_enqueue_scripts
 * (WooCommerceBridge::enqueue_checkout_styles()); the hooks here only draw.
 *
 * A block checkout never fires these hooks, so administrators get a warning
 * (block_checkout_notice()).
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

		self::render_journey(2, self::summary_vars($data, '', ''));
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
		$order = self::order( (int) $order_id);
		$data  = null !== $order ? self::order_rental_data($order) : null;
		if (null === $order || null === $data) {
			return;
		}

		$link = '';
		$user = get_current_user_id();
		if (is_user_logged_in() && $user > 0 && (int) $order->get_customer_id() === $user) {
			$link = wc_get_account_endpoint_url(AccountController::get_endpoint_slug('bookings', 'rentiva-bookings'));
		}

		self::render_journey(
			$order->has_status('failed') ? 2 : 3,
			self::summary_vars($data, __('Your booking', 'mhm-rentiva'), $link)
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
	 * Enqueue the journey styles when this request will draw it: a checkout with
	 * a single-rental cart, or the order-received page of a single-rental order.
	 * Called from WooCommerceBridge::enqueue_checkout_styles() (wp_enqueue_scripts),
	 * so the styles print in the head.
	 */
	public static function enqueue_assets(): void
	{
		if (self::journey_request()) {
			AssetManager::enqueue_rvs_journey();
		}
	}

	/**
	 * The checkout and thank-you journey depend on WooCommerce's classic
	 * checkout hooks; warn an administrator whose checkout page uses the block.
	 */
	public static function block_checkout_notice(): void
	{
		if (! current_user_can('manage_options') || ! self::is_notice_screen() || ! self::checkout_uses_block()) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p>%s</p></div>',
			esc_html__("Rentiva's booking steps and payment-type choice work with the classic checkout. Your checkout page uses the Checkout block — replace it with the [woocommerce_checkout] shortcode.", 'mhm-rentiva')
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
	 * @param array<string, mixed> $summary_vars summary_vars().
	 */
	private static function render_journey(int $step, array $summary_vars): void
	{
		echo '<div class="rvs-shell rvs-journey">';
		Templates::render(
			'partials/rvs/steps',
			array(
				'items' => RvsSteps::items($step),
			)
		);
		Templates::render('partials/rvs/journey-summary', $summary_vars);
		echo '</div>';
	}

	/**
	 * The variables of templates/partials/rvs/journey-summary.php.
	 *
	 * @param array<string, mixed> $snap Cart or order line booking data.
	 * @return array{summary: array<string, mixed>}
	 */
	private static function summary_vars(array $snap, string $heading, string $link): array
	{
		$total   = (float) ( $snap['total_price'] ?? 0 );
		$deposit = 'full' === (string) ( $snap['payment_type'] ?? '' ) ? 0.0 : (float) ( $snap['deposit_amount'] ?? 0 );
		$rate    = $total > 0 ? $deposit / $total * 100 : 0.0;

		$vehicle_id = (int) ( $snap['vehicle_id'] ?? 0 );

		return array(
			'summary' => array(
				'heading'   => $heading,
				'title'     => $vehicle_id > 0 ? get_the_title($vehicle_id) : '',
				'image_id'  => $vehicle_id > 0 ? (int) get_post_thumbnail_id($vehicle_id) : 0,
				'dates'     => self::window_text($snap),
				'breakdown' => RvsBreakdown::from_quote(self::quote_from_snapshot($snap), $deposit, self::addon_rows($snap), $rate),
				'link'      => $link,
			),
		);
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
	private static function order_rental_data(\WC_Order $order): ?array
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
			global $wp;
			$order_id = isset($wp->query_vars['order-received']) ? absint($wp->query_vars['order-received']) : 0;
			$order    = self::order($order_id);

			return null !== $order && null !== self::order_rental_data($order);
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
