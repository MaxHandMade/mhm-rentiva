<?php
/**
 * Vehicle page reviews: average, count and the 5..1 distribution, then the
 * rating form (its list and "write a review"). The form keeps its own look
 * until surface 6 (PR F) redraws it.
 *
 * @var array<string, mixed> $view VehicleDetailsView::build().
 */

declare(strict_types=1);

use MHMRentiva\Admin\Core\Utilities\Templates;

if (! defined('ABSPATH')) {
	exit;
}

$mhmrentiva_view    = (array) ( $view ?? array() );
$mhmrentiva_uid     = (string) $mhmrentiva_view['uid'];
$mhmrentiva_reviews = $mhmrentiva_view['reviews'];
?>
<section class="rvs-vd__section rvs-vd__reviews" id="<?php echo esc_attr($mhmrentiva_uid); ?>-reviews" aria-labelledby="<?php echo esc_attr($mhmrentiva_uid); ?>-reviews-title">
	<div class="rvs-vd__section-head">
		<h2 class="rvs-vd__h2" id="<?php echo esc_attr($mhmrentiva_uid); ?>-reviews-title"><?php esc_html_e('Reviews', 'mhm-rentiva'); ?></h2>
		<a class="rvs-btn rvs-btn--ghost" href="#<?php echo esc_attr($mhmrentiva_uid); ?>-review-form"><?php esc_html_e('Write a review', 'mhm-rentiva'); ?></a>
	</div>

	<?php if (is_array($mhmrentiva_reviews)) : ?>
		<div class="rvs-vd__score">
			<div class="rvs-vd__average">
				<span class="rvs-vd__average-value"><?php echo esc_html(number_format_i18n( (float) $mhmrentiva_reviews['average'], 1)); ?></span>
				<span class="rvs-vd__average-count">
					<?php
					/* translators: %s: number of reviews. */
					echo esc_html(sprintf(_n('%s review', '%s reviews', (int) $mhmrentiva_reviews['count'], 'mhm-rentiva'), number_format_i18n( (int) $mhmrentiva_reviews['count'])));
					?>
				</span>
			</div>
			<ul class="rvs-vd__dist" aria-label="<?php esc_attr_e('Rating distribution', 'mhm-rentiva'); ?>">
				<?php foreach ( (array) $mhmrentiva_reviews['distribution'] as $mhmrentiva_row) : ?>
					<li class="rvs-vd__dist-row">
						<span class="rvs-vd__dist-star">
							<?php
							/* translators: %d: star rating, 1 to 5. */
							echo esc_html(sprintf(_n('%d star', '%d stars', (int) $mhmrentiva_row['star'], 'mhm-rentiva'), (int) $mhmrentiva_row['star']));
							?>
						</span>
						<span class="rvs-vd__dist-track" aria-hidden="true"><span class="rvs-vd__dist-fill" style="width: <?php echo esc_attr( (string) (int) $mhmrentiva_row['pct']); ?>%"></span></span>
						<span class="rvs-vd__dist-count"><?php echo esc_html(number_format_i18n( (int) $mhmrentiva_row['count'])); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>

	<div class="rvs-vd__review-form" id="<?php echo esc_attr($mhmrentiva_uid); ?>-review-form">
		<?php Templates::output_shortcode('[rentiva_vehicle_rating_form vehicle_id="' . (int) $mhmrentiva_view['vehicle_id'] . '"]'); ?>
	</div>
</section>
