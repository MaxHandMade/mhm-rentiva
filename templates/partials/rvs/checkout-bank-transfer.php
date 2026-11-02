<?php
/**
 * Under WooCommerce's bank details on a rental's thank-you page (R10, canvas
 * `Tesekkur`, "banka havalesi"): the reference to write, the amount to send,
 * what is left for pickup, and where the receipt goes
 * (RentalCheckoutLook::render_bank_transfer()).
 *
 * @var array<string, mixed> $transfer reference, to_send {label, html}, on_pickup {html}|null, upload_url, store_email.
 *
 * @package MHMRentiva
 */

declare(strict_types=1);

use MHMRentiva\Admin\Booking\Pricing\PriceDisplay;

if (! defined('ABSPATH')) {
	exit;
}

$mhmrentiva_bt        = (array) ( $transfer ?? array() );
$mhmrentiva_bt_send   = (array) ( $mhmrentiva_bt['to_send'] ?? array() );
$mhmrentiva_bt_pickup = is_array($mhmrentiva_bt['on_pickup'] ?? null) ? $mhmrentiva_bt['on_pickup'] : null;
$mhmrentiva_bt_upload = (string) ( $mhmrentiva_bt['upload_url'] ?? '' );
$mhmrentiva_bt_email  = (string) ( $mhmrentiva_bt['store_email'] ?? '' );
$mhmrentiva_bt_ref    = (string) ( $mhmrentiva_bt['reference'] ?? '' );
$mhmrentiva_bt_html   = PriceDisplay::allowed_html();
?>
<div class="rvs-shell rvs-bank-transfer">
	<section class="rvs-bank-transfer__amounts" aria-labelledby="rvs-bank-transfer-title">
		<h3 id="rvs-bank-transfer-title" class="rvs-bank-transfer__title"><?php esc_html_e('Your transfer', 'mhm-rentiva'); ?></h3>
		<div class="rvs-bank-transfer__row">
			<span><?php esc_html_e('Reference', 'mhm-rentiva'); ?></span>
			<strong><?php echo esc_html($mhmrentiva_bt_ref); ?></strong>
		</div>
		<div class="rvs-bank-transfer__row rvs-bank-transfer__row--send">
			<span><?php echo esc_html( (string) ( $mhmrentiva_bt_send['label'] ?? '' )); ?></span>
			<strong><?php echo wp_kses( (string) ( $mhmrentiva_bt_send['html'] ?? '' ), $mhmrentiva_bt_html); ?></strong>
		</div>
		<?php if (null !== $mhmrentiva_bt_pickup) : ?>
			<div class="rvs-bank-transfer__row rvs-bank-transfer__row--pickup">
				<span><?php esc_html_e('Due at pickup', 'mhm-rentiva'); ?></span>
				<span><?php echo wp_kses( (string) ( $mhmrentiva_bt_pickup['html'] ?? '' ), $mhmrentiva_bt_html); ?></span>
			</div>
		<?php endif; ?>
	</section>
	<section class="rvs-bank-transfer__receipt" aria-labelledby="rvs-bank-transfer-receipt">
		<?php if ('' !== $mhmrentiva_bt_upload) : ?>
			<h3 id="rvs-bank-transfer-receipt" class="rvs-bank-transfer__title"><?php esc_html_e('Upload the receipt in My Account', 'mhm-rentiva'); ?></h3>
			<p><?php esc_html_e('After the transfer, add its receipt to your booking. Your booking is confirmed once the payment is approved.', 'mhm-rentiva'); ?></p>
			<a class="rvs-btn rvs-btn--primary" href="<?php echo esc_url($mhmrentiva_bt_upload); ?>"><?php esc_html_e('Upload receipt', 'mhm-rentiva'); ?></a>
		<?php else : ?>
			<h3 id="rvs-bank-transfer-receipt" class="rvs-bank-transfer__title"><?php esc_html_e('Email us the receipt', 'mhm-rentiva'); ?></h3>
			<p>
				<?php
				if ('' !== $mhmrentiva_bt_email) {
					printf(
						/* translators: 1: the shop's email address, 2: the order number. */
						esc_html__('After the transfer, send its receipt to %1$s with your order number %2$s. Your booking is confirmed once the payment is approved.', 'mhm-rentiva'),
						'<a href="' . esc_url('mailto:' . $mhmrentiva_bt_email) . '">' . esc_html($mhmrentiva_bt_email) . '</a>',
						'<strong>' . esc_html($mhmrentiva_bt_ref) . '</strong>'
					);
				} else {
					printf(
						/* translators: %s: the order number. */
						esc_html__('After the transfer, send its receipt to us by email with your order number %s. Your booking is confirmed once the payment is approved.', 'mhm-rentiva'),
						'<strong>' . esc_html($mhmrentiva_bt_ref) . '</strong>'
					);
				}
				?>
			</p>
		<?php endif; ?>
	</section>
</div>
