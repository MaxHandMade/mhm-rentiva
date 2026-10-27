<?php
/**
 * The next-rental card of the account dashboard (PR D, Task 3; drawings
 * Hesabim + Hesabim-Mobil): image beside the content on desktop, image on top
 * on phones. Buttons: Details (primary); the secondary "Booking summary"
 * button joins in Task 5.
 *
 * @var array{number:string, status:array{modifier:string,label:string}, in_progress:bool, kicker:string, vehicle:string, image:string, pickup:string, return:string, location:?string, payment:array{label:string,amount_html:?string,note:?string}, details_url:string, free_cancel:?string} $card AccountDashboardView::next_card().
 */

declare(strict_types=1);

use MHMRentiva\Admin\Booking\Pricing\PriceDisplay;
use MHMRentiva\Admin\Core\Utilities\Templates;

if (! defined('ABSPATH')) {
	exit;
}

$mhmrentiva_nr_card = (array) ( $card ?? array() );
if (array() === $mhmrentiva_nr_card) {
	return;
}

$mhmrentiva_nr_uid     = wp_unique_id('rvs-acc-');
$mhmrentiva_nr_payment = (array) $mhmrentiva_nr_card['payment'];
$mhmrentiva_nr_where   = $mhmrentiva_nr_card['location'];
?>
<section class="rvs-account-next" aria-labelledby="<?php echo esc_attr($mhmrentiva_nr_uid . '-title'); ?>" data-testid="account-next-rental">
	<div class="rvs-account-next__media">
		<img src="<?php echo esc_url( (string) $mhmrentiva_nr_card['image']); ?>" alt="" loading="lazy">
	</div>
	<div class="rvs-account-next__body">
		<div class="rvs-account-next__top">
			<span class="rvs-kicker"><?php echo esc_html( (string) $mhmrentiva_nr_card['kicker']); ?></span>
			<?php Templates::render('partials/rvs/status-tag', array( 'status' => $mhmrentiva_nr_card['status'] )); ?>
		</div>
		<h2 id="<?php echo esc_attr($mhmrentiva_nr_uid . '-title'); ?>"><?php echo esc_html( (string) $mhmrentiva_nr_card['vehicle']); ?></h2>
		<dl class="rvs-account-next__facts">
			<?php if ('' !== $mhmrentiva_nr_card['pickup']) : ?>
				<div>
					<dt><?php esc_html_e('Pickup', 'mhm-rentiva'); ?></dt>
					<dd><strong><?php echo esc_html( (string) $mhmrentiva_nr_card['pickup']); ?></strong><?php echo null !== $mhmrentiva_nr_where ? '<span>' . esc_html( (string) $mhmrentiva_nr_where) . '</span>' : ''; ?></dd>
				</div>
			<?php endif; ?>
			<?php if ('' !== $mhmrentiva_nr_card['return']) : ?>
				<div>
					<dt><?php esc_html_e('Return', 'mhm-rentiva'); ?></dt>
					<dd><strong><?php echo esc_html( (string) $mhmrentiva_nr_card['return']); ?></strong><?php echo null !== $mhmrentiva_nr_where ? '<span>' . esc_html( (string) $mhmrentiva_nr_where) . '</span>' : ''; ?></dd>
				</div>
			<?php endif; ?>
			<div>
				<dt><?php echo esc_html( (string) $mhmrentiva_nr_payment['label']); ?></dt>
				<dd>
					<?php if (null !== $mhmrentiva_nr_payment['amount_html']) : ?>
						<strong><?php echo wp_kses( (string) $mhmrentiva_nr_payment['amount_html'], PriceDisplay::allowed_html()); ?></strong>
					<?php endif; ?>
					<?php if (null !== $mhmrentiva_nr_payment['note']) : ?>
						<span><?php echo esc_html( (string) $mhmrentiva_nr_payment['note']); ?></span>
					<?php endif; ?>
				</dd>
			</div>
		</dl>
		<div class="rvs-account__actions rvs-account-next__actions">
			<a class="rvs-btn rvs-btn--primary" href="<?php echo esc_url( (string) $mhmrentiva_nr_card['details_url']); ?>"><?php esc_html_e('Details', 'mhm-rentiva'); ?></a>
		</div>
		<?php if (null !== $mhmrentiva_nr_card['free_cancel']) : ?>
			<p class="rvs-account-next__cancel"><?php echo esc_html( (string) $mhmrentiva_nr_card['free_cancel']); ?></p>
		<?php endif; ?>
	</div>
</section>
