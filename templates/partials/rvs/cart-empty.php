<?php
/**
 * The empty cart of a rental site (canvas `Sepet`, "sepet boş"), in place of
 * WooCommerce's notice (RentalCheckoutLook::render_empty_cart()).
 *
 * The outer element keeps WooCommerce's `wc-empty-cart-message` class: after the
 * last line is removed, cart.js looks for it in the page it fetches.
 *
 * @var string $vehicles_url Where the customer picks a vehicle.
 *
 * @package MHMRentiva
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	exit;
}

$mhmrentiva_ce_url = (string) ( $vehicles_url ?? '' );
?>
<div class="wc-empty-cart-message rvs-shell rvs-empty rvs-cart-empty">
	<h2 class="rvs-cart-empty__title"><?php esc_html_e('Your cart is empty', 'mhm-rentiva'); ?></h2>
	<p class="rvs-cart-empty__text"><?php esc_html_e('Pick a vehicle and dates, and your rental shows here.', 'mhm-rentiva'); ?></p>
	<?php if ('' !== $mhmrentiva_ce_url) : ?>
		<a class="rvs-btn rvs-btn--primary" href="<?php echo esc_url($mhmrentiva_ce_url); ?>"><?php esc_html_e('Browse vehicles', 'mhm-rentiva'); ?></a>
	<?php endif; ?>
</div>
