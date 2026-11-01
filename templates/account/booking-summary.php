<?php
/**
 * The printable booking summary (PR D, K14, R12): a document of its own.
 *
 * Loaded by WordPress's template loader through BookingSummaryPage::
 * template_include(), so there is no theme header or footer and wp_head never
 * runs; only the summary's own style handles are printed. The print button
 * calls window.print() (a tiny inline script) and @media print hides it.
 * Not an invoice, and it says so.
 */

declare(strict_types=1);

use MHMRentiva\Admin\Booking\Pricing\PriceDisplay;
use MHMRentiva\Admin\Core\AssetManager;
use MHMRentiva\Admin\Core\Utilities\Templates;
use MHMRentiva\Admin\Frontend\Account\BookingSummaryPage;

if (! defined('ABSPATH')) {
	exit;
}

$mhmrentiva_sum      = BookingSummaryPage::current();
$mhmrentiva_sum_kses = PriceDisplay::allowed_html();
$mhmrentiva_sum_num  = (string) ( $mhmrentiva_sum['number'] ?? '' );
/* translators: %s: booking number shown to the customer (the order number when there is one). */
$mhmrentiva_sum_head = sprintf(__('Booking #%s', 'mhm-rentiva'), $mhmrentiva_sum_num);
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo('charset'); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<title><?php echo esc_html(__('Booking summary', 'mhm-rentiva') . ' · ' . $mhmrentiva_sum_head); ?></title>
	<?php AssetManager::print_rvs_summary_styles(); ?>
</head>
<body class="rvs-shell rvs-summary">
	<main class="rvs-summary__page">
		<header class="rvs-summary__head">
			<div class="rvs-summary__brand">
				<?php if ('' !== (string) ( $mhmrentiva_sum['logo_url'] ?? '' )) : ?>
					<img class="rvs-summary__logo" src="<?php echo esc_url( (string) $mhmrentiva_sum['logo_url']); ?>" alt="">
				<?php endif; ?>
				<span class="rvs-summary__site"><?php echo esc_html( (string) ( $mhmrentiva_sum['site_name'] ?? '' )); ?></span>
			</div>
			<button type="button" class="rvs-btn rvs-btn--secondary rvs-summary__print" data-rvs-print><?php esc_html_e('Print or save as PDF', 'mhm-rentiva'); ?></button>
		</header>

		<div class="rvs-summary__title">
			<span class="rvs-kicker"><?php esc_html_e('Booking summary', 'mhm-rentiva'); ?></span>
			<h1><?php echo esc_html($mhmrentiva_sum_head); ?></h1>
			<?php Templates::render('partials/rvs/status-tag', array( 'status' => (array) ( $mhmrentiva_sum['status'] ?? array() ) )); ?>
		</div>

		<dl class="rvs-summary__facts">
			<?php if ('' !== (string) ( $mhmrentiva_sum['customer'] ?? '' )) : ?>
				<div>
					<dt><?php esc_html_e('Customer', 'mhm-rentiva'); ?></dt>
					<dd><?php echo esc_html( (string) $mhmrentiva_sum['customer']); ?></dd>
				</div>
			<?php endif; ?>
			<div>
				<dt><?php esc_html_e('Vehicle', 'mhm-rentiva'); ?></dt>
				<dd><?php echo esc_html( (string) ( $mhmrentiva_sum['vehicle'] ?? '' )); ?></dd>
			</div>
			<?php if ('' !== (string) ( $mhmrentiva_sum['pickup'] ?? '' )) : ?>
				<div>
					<dt><?php esc_html_e('Pickup', 'mhm-rentiva'); ?></dt>
					<dd><?php echo esc_html( (string) $mhmrentiva_sum['pickup']); ?></dd>
				</div>
			<?php endif; ?>
			<?php if ('' !== (string) ( $mhmrentiva_sum['return'] ?? '' )) : ?>
				<div>
					<dt><?php esc_html_e('Return', 'mhm-rentiva'); ?></dt>
					<dd><?php echo esc_html( (string) $mhmrentiva_sum['return']); ?></dd>
				</div>
			<?php endif; ?>
			<?php if ('' !== (string) ( $mhmrentiva_sum['location'] ?? '' )) : ?>
				<div>
					<dt><?php esc_html_e('Location', 'mhm-rentiva'); ?></dt>
					<dd><?php echo esc_html( (string) $mhmrentiva_sum['location']); ?></dd>
				</div>
			<?php endif; ?>
		</dl>

		<section class="rvs-summary__money" data-rvs-summary-money>
			<h2 class="rvs-kicker"><?php esc_html_e('Payment', 'mhm-rentiva'); ?></h2>
			<?php
			Templates::render(
				'partials/rvs/breakdown',
				array(
					'breakdown'    => (array) ( $mhmrentiva_sum['breakdown'] ?? array() ),
					'deposit_paid' => ! empty($mhmrentiva_sum['deposit_paid']),
				)
			);
			?>
			<?php foreach ( (array) ( $mhmrentiva_sum['paid_rows'] ?? array() ) as $mhmrentiva_sum_row) : ?>
				<div class="rvs-row rvs-summary__paid rvs-summary__paid--<?php echo esc_attr( (string) $mhmrentiva_sum_row['kind']); ?>">
					<span><?php echo esc_html( (string) $mhmrentiva_sum_row['label']); ?></span>
					<span><?php echo wp_kses( (string) $mhmrentiva_sum_row['html'], $mhmrentiva_sum_kses); ?></span>
				</div>
			<?php endforeach; ?>
		</section>

		<footer class="rvs-summary__foot">
			<p>
				<?php
				/* translators: %s: date the booking was created. */
				echo esc_html(sprintf(__('Created %s', 'mhm-rentiva'), (string) ( $mhmrentiva_sum['created'] ?? '' )));
				?>
			</p>
			<p><?php esc_html_e('This summary is not an invoice.', 'mhm-rentiva'); ?></p>
		</footer>
	</main>
	<?php wp_print_inline_script_tag("document.addEventListener('click',function(e){var b=e.target.closest&&e.target.closest('[data-rvs-print]');if(b){window.print();}});"); ?>
</body>
</html>
