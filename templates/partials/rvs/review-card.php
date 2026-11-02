<?php
/**
 * One review of the rating form's list. The card knows no layout: the caller's list
 * places it. The testimonials page draws its own card (partials/rvs/testimonial-card),
 * from the same ReviewsView::card() values and the same review-stars partial.
 *
 * Script hooks (vehicle-rating-form.js): [data-rvs-review-edit], [data-rvs-review-delete].
 *
 * @var array<string, mixed> $review ReviewsView::card().
 */

declare(strict_types=1);

use MHMRentiva\Admin\Core\Utilities\Templates;

if (! defined('ABSPATH')) {
	exit;
}

$mhmrentiva_rc          = (array) ( $review ?? array() );
$mhmrentiva_rc_disabled = ! empty($mhmrentiva_rc['disabled']) ? ' disabled' : '';
$mhmrentiva_rc_classes  = 'rvs-review' . ( empty($mhmrentiva_rc['show_avatar']) ? ' rvs-review--no-avatar' : '' );
?>
<article class="<?php echo esc_attr($mhmrentiva_rc_classes); ?>" data-comment-id="<?php echo esc_attr( (string) (int) $mhmrentiva_rc['id']); ?>">
	<div class="rvs-review__head">
		<?php if (! empty($mhmrentiva_rc['show_avatar'])) : ?>
			<span class="rvs-review__avatar" aria-hidden="true"><?php echo esc_html( (string) $mhmrentiva_rc['initial']); ?></span>
		<?php endif; ?>
		<span class="rvs-review__who">
			<strong class="rvs-review__name"><?php echo esc_html( (string) $mhmrentiva_rc['name']); ?></strong>
			<?php if (! empty($mhmrentiva_rc['verified'])) : ?>
				<span class="rvs-tag rvs-tag--info"><?php esc_html_e('Verified rental', 'mhm-rentiva'); ?></span>
			<?php endif; ?>
			<?php if (! empty($mhmrentiva_rc['pending'])) : ?>
				<?php
				Templates::render(
					'partials/rvs/status-tag',
					array(
						'status' => array(
							'modifier' => 'pending',
							'label'    => __('Under review', 'mhm-rentiva'),
						),
					)
				);
				?>
			<?php endif; ?>
		</span>
		<span class="rvs-review__meta">
			<?php if ( (int) $mhmrentiva_rc['rating'] > 0) : ?>
				<?php
				Templates::render(
					'partials/rvs/review-stars',
					array(
						'rating' => (int) $mhmrentiva_rc['rating'],
						'label'  => (string) $mhmrentiva_rc['stars_label'],
					)
				);
				?>
			<?php endif; ?>
			<?php if ( (int) $mhmrentiva_rc['rating'] > 0 && '' !== (string) $mhmrentiva_rc['date']) : ?>
				<span aria-hidden="true">&middot;</span>
			<?php endif; ?>
			<?php if ('' !== (string) $mhmrentiva_rc['date']) : ?>
				<span class="rvs-review__date"><?php echo esc_html( (string) $mhmrentiva_rc['date']); ?></span>
			<?php endif; ?>
		</span>
	</div>
	<?php if ('' !== (string) $mhmrentiva_rc['vehicle']) : ?>
		<span class="rvs-review__vehicle"><?php echo esc_html( (string) $mhmrentiva_rc['vehicle']); ?></span>
	<?php endif; ?>
	<div class="rvs-review__text"><?php echo wp_kses_post( (string) $mhmrentiva_rc['text']); ?></div>
	<?php if (! empty($mhmrentiva_rc['pending'])) : ?>
		<span class="rvs-help"><?php esc_html_e('Once approved, it is visible to everyone and counted in the summary.', 'mhm-rentiva'); ?></span>
	<?php endif; ?>
	<?php if (! empty($mhmrentiva_rc['can_edit']) || ! empty($mhmrentiva_rc['can_delete'])) : ?>
		<div class="rvs-review__actions">
			<?php if (! empty($mhmrentiva_rc['can_edit'])) : ?>
				<button type="button" class="rvs-btn rvs-btn--secondary" data-rvs-review-edit data-rating="<?php echo esc_attr( (string) (int) $mhmrentiva_rc['raw_rating']); ?>" data-comment="<?php echo esc_attr( (string) $mhmrentiva_rc['text']); ?>"<?php echo esc_attr($mhmrentiva_rc_disabled); ?>><?php esc_html_e('Edit', 'mhm-rentiva'); ?></button>
			<?php endif; ?>
			<?php if (! empty($mhmrentiva_rc['can_delete'])) : ?>
				<button type="button" class="rvs-btn rvs-review__delete" data-rvs-review-delete<?php echo esc_attr($mhmrentiva_rc_disabled); ?>><?php esc_html_e('Delete', 'mhm-rentiva'); ?></button>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</article>
