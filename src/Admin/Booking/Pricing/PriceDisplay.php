<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Booking\Pricing;

use MHMRentiva\Admin\Payment\WooCommerce\WooCommerceBridge;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Shows a Rentiva amount the way WooCommerce will show it at checkout.
 *
 * Rentiva computes amounts in the store's price-entry convention and leaves
 * tax to WooCommerce. Before an amount reaches a visitor it is put into
 * WooCommerce's convention on the booking product:
 *
 * - 'cart' (booking form): the single-line arithmetic WC_Cart_Totals performs
 *   for the booking line -- rates for the customer's location, the non-base
 *   location adjustment, tax rounded per line or at subtotal as the store is
 *   set -- so the figure equals the checkout to the cent. wc_get_price_to_display()
 *   skips that rounding and can be a cent off with two rates.
 * - 'shop' (listings): wc_get_price_to_display(), as WooCommerce prices a product.
 */
final class PriceDisplay {

	/** Booking product id found earlier in this request (0 = not looked up / none). */
	private static int $product_id = 0;

	/**
	 * @return array{amount: float, html: string, suffix: string, tax_label: string}
	 */
	public static function for_cart(float $amount): array
	{
		return self::display($amount, 'cart');
	}

	/**
	 * @return array{amount: float, html: string, suffix: string, tax_label: string}
	 */
	public static function for_shop(float $amount): array
	{
		return self::display($amount, 'shop');
	}

	/**
	 * `suffix` is WooCommerce's price suffix plus, for a tax-exclusive figure,
	 * the "(ex. VAT)" label; `tax_label` is that label alone, for rows that
	 * should say "excluding tax" without repeating the store's suffix.
	 *
	 * @return array{amount: float, html: string, suffix: string, tax_label: string}
	 */
	private static function display(float $amount, string $context): array
	{
		if (! function_exists('wc_get_price_to_display') || ! class_exists('WC_Product_Simple')) {
			return array(
				'amount'    => $amount,
				'html'      => self::format($amount),
				'suffix'    => '',
				'tax_label' => '',
			);
		}

		$product      = self::booking_product();
		$display_mode = get_option('cart' === $context ? 'woocommerce_tax_display_cart' : 'woocommerce_tax_display_shop');

		// Whether tax is actually added to this amount; a VAT-exempt customer, an
		// untaxed product or a 0% rate pays the figure shown.
		$taxed = true;
		if ('cart' === $context) {
			$line  = self::cart_line($product, $amount);
			$shown = 'excl' === $display_mode ? $line['net'] : $line['total'];
			$taxed = abs($line['total'] - $line['net']) > 0.0001;
		} else {
			$shown = (float) wc_get_price_to_display(
				$product,
				array(
					'price'           => $amount,
					'display_context' => $context,
				)
			);
		}

		// WooCommerce labels a tax-exclusive figure "(ex. VAT)" in the cart, and
		// only when the line carries tax (WC_Cart::get_product_subtotal()); without
		// it a visitor would read the amount as the final total.
		$tax_label = '';
		if ($taxed && wc_tax_enabled() && 'excl' === $display_mode && function_exists('WC') && WC()->countries) {
			$tax_label = ' <small class="tax_label">' . esc_html( wp_strip_all_tags( (string) WC()->countries->ex_tax_or_vat() ) ) . '</small>';
		}

		// get_price_suffix() returns its filter's result unescaped and the JS
		// inserts it as HTML.
		$suffix = wp_kses( (string) $product->get_price_suffix( (string) $amount ) . $tax_label, self::allowed_html() );

		return array(
			'amount'    => $shown,
			'html'      => self::format($shown),
			'suffix'    => $suffix,
			'tax_label' => $tax_label,
		);
	}

	/**
	 * One booking line as WC_Cart_Totals totals it (quantity 1, no coupon).
	 *
	 * Mirrors calculate_item_subtotals()/calculate_item_totals()/calculate_totals()
	 * of WooCommerce 11.1: work in cents, adjust a tax-inclusive price for a
	 * non-base location, take unrounded taxes off an inclusive price, round
	 * line tax and the line unless the store rounds at subtotal; a VAT-exempt
	 * customer pays the price less base tax. Not mirrored, because they need the
	 * cart and line objects: the woocommerce_cart_totals_get_item_tax_rates,
	 * woocommerce_calculate_item_totals_taxes, woocommerce_get_discounted_price
	 * and woocommerce_calculated_total filters, coupons and cart fees.
	 *
	 * @return array{total: float, net: float}
	 */
	private static function cart_line(\WC_Product $product, float $amount): array
	{
		if (! wc_tax_enabled() || ! $product->is_taxable()) {
			return array(
				'total' => $amount,
				'net'   => $amount,
			);
		}

		$includes_tax = wc_prices_include_tax();
		$customer     = function_exists('WC') && WC()->customer ? WC()->customer : null;
		$is_exempt    = $customer && $customer->get_is_vat_exempt();
		$rates        = \WC_Tax::get_rates($product->get_tax_class(), $customer);
		$price        = wc_add_number_precision($amount);

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's own filter, read exactly as WC_Cart_Totals::calculate_item_subtotals() reads it so the line agrees with the checkout. Not a hook this plugin defines.
		$adjust_non_base = (bool) apply_filters('woocommerce_adjust_non_base_location_prices', true);

		if ($includes_tax && $is_exempt) {
			// remove_item_base_taxes(): the exempt customer pays the price less the
			// shop's base tax, and no tax is added.
			$remove = $adjust_non_base ? \WC_Tax::get_base_tax_rates($product->get_tax_class('unfiltered')) : $rates;
			$price  = self::round($price - array_sum(\WC_Tax::calc_tax($price, $remove, true)));
		} elseif ($includes_tax && $adjust_non_base) {
			$base_rates = \WC_Tax::get_base_tax_rates($product->get_tax_class('unfiltered'));
			if ($rates !== $base_rates) {
				$base_taxes = \WC_Tax::calc_tax($price, $base_rates, true);
				$new_taxes  = \WC_Tax::calc_tax($price - array_sum($base_taxes), $rates, false);
				$price      = $price - array_sum($base_taxes) + array_sum($new_taxes);
			}
		}

		if ($is_exempt) {
			$exempt = (float) wc_remove_number_precision(self::round($price));
			return array(
				'total' => $exempt,
				'net'   => $exempt,
			);
		}

		$round_at_subtotal = 'yes' === get_option('woocommerce_tax_round_at_subtotal');
		$taxes             = \WC_Tax::calc_tax($price, $rates, $includes_tax);
		$net               = $includes_tax ? $price - array_sum($taxes) : $price;

		$tax_total = 0.0;
		foreach ($taxes as $tax) {
			$tax_total += $round_at_subtotal ? $tax : wc_round_tax_total($tax, 0);
		}
		if (! $round_at_subtotal) {
			$net = self::round($net);
		}

		return array(
			'total' => (float) wc_remove_number_precision(self::round($net + $tax_total)),
			'net'   => (float) wc_remove_number_precision(self::round($net)),
		);
	}

	/**
	 * Whole-cent rounding the way WC_Cart_Totals does it.
	 */
	private static function round(float $value): float
	{
		return class_exists(\Automattic\WooCommerce\Utilities\NumberUtil::class)
			? \Automattic\WooCommerce\Utilities\NumberUtil::round($value)
			: round($value);
	}

	/**
	 * The booking product if it already exists, else an unsaved stand-in with
	 * the same tax properties get_booking_product_id() gives a new one
	 * (taxable, standard class). Nothing is written: this runs while
	 * rendering a page or answering a quote.
	 */
	private static function booking_product(): \WC_Product
	{
		if (function_exists('wc_get_product_id_by_sku') && function_exists('wc_get_product')) {
			// One SKU lookup per request (a quote displays seven amounts). The id is
			// re-checked: a product deleted later in the request is looked up again.
			if (self::$product_id > 0) {
				$product = wc_get_product(self::$product_id);
				if ($product instanceof \WC_Product && WooCommerceBridge::PRODUCT_SKU === $product->get_sku() && 'trash' !== $product->get_status()) {
					return $product;
				}
			}

			self::$product_id = (int) wc_get_product_id_by_sku(WooCommerceBridge::PRODUCT_SKU);
			if (self::$product_id > 0) {
				$product = wc_get_product(self::$product_id);
				if ($product instanceof \WC_Product) {
					return $product;
				}
			}
		}

		return new \WC_Product_Simple();
	}

	/**
	 * Post markup plus <bdi>, which wc_price() wraps every amount in; plain
	 * wp_kses_post() strips it. For printing a price's HTML anywhere.
	 *
	 * @return array<string, array<string, bool>>
	 */
	public static function allowed_html(): array
	{
		$allowed        = wp_kses_allowed_html('post');
		$allowed['bdi'] = array( 'dir' => true );

		return $allowed;
	}

	private static function format(float $amount): string
	{
		return function_exists('wc_price') ? (string) wc_price($amount) : esc_html(\MHMRentiva\Admin\Core\CurrencyHelper::format_price($amount, 2));
	}
}
