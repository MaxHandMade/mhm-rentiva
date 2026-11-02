<?php
/**
 * Review summary: the average, the count and the 5..1 distribution.
 * Used by the rating form and the vehicle page.
 *
 * @var array<string, mixed> $summary    ReviewsView::summary().
 * @var bool                 $show_stars Draw the stars under the average.
 */

declare(strict_types=1);

use MHMRentiva\Admin\Core\Utilities\Templates;

if (! defined('ABSPATH')) {
	exit;
}

$mhmrentiva_rv_summary = (array) ( $summary ?? array() );
?>
<div class="rvs-review-summary">
	<div class="rvs-review-summary__average">
		<span class="rvs-review-summary__value"><?php echo esc_html( (string) $mhmrentiva_rv_summary['average_text']); ?></span>
		<?php if (! empty($show_stars)) : ?>
			<?php
			Templates::render(
				'partials/rvs/review-stars',
				array(
					'rating' => (int) $mhmrentiva_rv_summary['stars'],
					'label'  => (string) $mhmrentiva_rv_summary['stars_label'],
				)
			);
			?>
		<?php endif; ?>
		<span class="rvs-review-summary__count"><?php echo esc_html( (string) $mhmrentiva_rv_summary['count_text']); ?></span>
	</div>
	<ul class="rvs-review-summary__dist" aria-label="<?php esc_attr_e('Rating distribution', 'mhm-rentiva'); ?>">
		<?php foreach ( (array) $mhmrentiva_rv_summary['distribution'] as $mhmrentiva_rv_row) : ?>
			<li class="rvs-review-summary__row">
				<span class="rvs-review-summary__star">
					<span aria-hidden="true"><?php echo esc_html( (string) (int) $mhmrentiva_rv_row['star']); ?>&#9733;</span>
					<span class="rvs-sr-only"><?php echo esc_html( (string) $mhmrentiva_rv_row['label']); ?></span>
				</span>
				<span class="rvs-review-summary__track" aria-hidden="true"><span class="rvs-review-summary__fill" style="width: <?php echo esc_attr( (string) (int) $mhmrentiva_rv_row['pct']); ?>%"></span></span>
				<span class="rvs-review-summary__num"><?php echo esc_html( (string) $mhmrentiva_rv_row['count_text']); ?></span>
			</li>
		<?php endforeach; ?>
	</ul>
</div>
