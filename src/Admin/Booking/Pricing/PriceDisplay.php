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
 * tax to WooCommerce. Before an amount reaches a visitor it goes through
 * wc_get_price_to_display() on the booking product, so the tax-inclusive or
 * tax-exclusive figure matches the store's own setting: 'cart' for the
 * booking form (the same option the checkout uses), 'shop' for listings.
 */
final class PriceDisplay {

	/**
	 * @return array{amount: float, html: string, suffix: string}
	 */
	public static function for_cart(float $amount): array
	{
		return self::display($amount, 'cart');
	}

	/**
	 * @return array{amount: float, html: string, suffix: string}
	 */
	public static function for_shop(float $amount): array
	{
		return self::display($amount, 'shop');
	}

	/**
	 * @return array{amount: float, html: string, suffix: string}
	 */
	private static function display(float $amount, string $context): array
	{
		$product = self::booking_product();

		if (null === $product || ! function_exists('wc_get_price_to_display')) {
			return array(
				'amount' => $amount,
				'html'   => self::format($amount),
				'suffix' => '',
			);
		}

		$shown  = (float) wc_get_price_to_display(
			$product,
			array(
				'price'           => $amount,
				'display_context' => $context,
			)
		);
		$suffix = (string) $product->get_price_suffix( (string) $amount );

		// WooCommerce labels a tax-exclusive figure "(ex. VAT)" in the cart;
		// without it a visitor would read the amount as the final total.
		if (wc_tax_enabled() && 'excl' === get_option('cart' === $context ? 'woocommerce_tax_display_cart' : 'woocommerce_tax_display_shop') && function_exists('WC') && WC()->countries) {
			$suffix .= ' <small class="tax_label">' . WC()->countries->ex_tax_or_vat() . '</small>';
		}

		return array(
			'amount' => $shown,
			'html'   => self::format($shown),
			'suffix' => $suffix,
		);
	}

	/**
	 * The booking product if it already exists. Never creates it: this runs
	 * while rendering a page or answering a quote, where nothing is written.
	 */
	private static function booking_product(): ?\WC_Product
	{
		if (! function_exists('wc_get_product_id_by_sku') || ! function_exists('wc_get_product')) {
			return null;
		}

		$product_id = (int) wc_get_product_id_by_sku(WooCommerceBridge::PRODUCT_SKU);
		if ($product_id <= 0) {
			return null;
		}

		$product = wc_get_product($product_id);

		return $product instanceof \WC_Product ? $product : null;
	}

	private static function format(float $amount): string
	{
		return function_exists('wc_price') ? (string) wc_price($amount) : \MHMRentiva\Admin\Core\CurrencyHelper::format_price($amount, 2);
	}
}
