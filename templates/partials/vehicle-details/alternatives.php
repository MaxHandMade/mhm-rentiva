<?php
/**
 * Vehicle page booking card: free vehicles for a taken window, under the
 * message (owner request 2026-11-02). The one markup for the box's content:
 * the page prints it on first load, the quote endpoint answers with it
 * (VehicleDetails::alternatives_html()) and rvs-vehicle.js drops it in.
 *
 * Each row: a small image (decoration: the title names the car), title, specs
 * line, daily price and a link to that vehicle's page with the same window.
 * The links share one text, so each is described by its car's title.
 *
 * @var list<array<string, mixed>> $alternatives VehicleDetailsView::alternatives().
 */

declare(strict_types=1);

use MHMRentiva\Admin\Booking\Pricing\PriceDisplay;

if (! defined('ABSPATH')) {
	exit;
}

$mhmrentiva_alts = array_slice(
	array_filter(
		(array) ( $alternatives ?? array() ),
		static fn($alt): bool => is_array($alt) && '' !== (string) ( $alt['permalink'] ?? '' )
	),
	0,
	2
);
if (array() === $mhmrentiva_alts) {
	return;
}
$mhmrentiva_alt_html = PriceDisplay::allowed_html();
?>
<p class="rvs-vd__alternatives-title"><?php esc_html_e('Alternative Vehicles', 'mhm-rentiva'); ?></p>
<ul class="rvs-vd__alternatives-list">
	<?php foreach ($mhmrentiva_alts as $mhmrentiva_alt) : ?>
		<?php
		$mhmrentiva_alt_title_id = wp_unique_id('rvs-vd-alt-');
		$mhmrentiva_alt_image    = (string) ( $mhmrentiva_alt['image'] ?? '' );
		$mhmrentiva_alt_specs    = (string) ( $mhmrentiva_alt['specs'] ?? '' );
		$mhmrentiva_alt_price    = (string) ( $mhmrentiva_alt['price_html'] ?? '' );
		?>
		<li class="rvs-vd__alt">
			<?php if ('' !== $mhmrentiva_alt_image) : ?>
				<img class="rvs-vd__alt-img" src="<?php echo esc_url($mhmrentiva_alt_image); ?>" alt="" loading="lazy" width="72" height="54">
			<?php endif; ?>
			<div class="rvs-vd__alt-body">
				<p class="rvs-vd__alt-title" id="<?php echo esc_attr($mhmrentiva_alt_title_id); ?>"><?php echo esc_html( (string) ( $mhmrentiva_alt['title'] ?? '' )); ?></p>
				<?php if ('' !== $mhmrentiva_alt_specs) : ?>
					<p class="rvs-vd__alt-specs"><?php echo esc_html($mhmrentiva_alt_specs); ?></p>
				<?php endif; ?>
				<?php if ('' !== $mhmrentiva_alt_price) : ?>
					<p class="rvs-vd__alt-price"><strong><?php echo wp_kses($mhmrentiva_alt_price, $mhmrentiva_alt_html); ?></strong> <span><?php esc_html_e('/day', 'mhm-rentiva'); ?></span></p>
				<?php endif; ?>
			</div>
			<a class="rvs-btn rvs-btn--ghost rvs-vd__alt-link" href="<?php echo esc_url( (string) $mhmrentiva_alt['permalink']); ?>" aria-describedby="<?php echo esc_attr($mhmrentiva_alt_title_id); ?>"><?php esc_html_e('Select This Vehicle', 'mhm-rentiva'); ?></a>
		</li>
	<?php endforeach; ?>
</ul>
