<?php
/**
 * One vehicle's head in the comparison: picture, name, remove, price, select.
 * The table's column header and the card layout print the same thing.
 *
 * @var array<string, mixed> $column  ComparisonView column.
 * @var array<string, bool>  $show    ComparisonView show flags.
 * @var bool                 $preview Editor preview: the remove button is inert.
 *
 * @package MHMRentiva
 */

declare(strict_types=1);

use MHMRentiva\Admin\Booking\Pricing\PriceDisplay;

if (! defined('ABSPATH')) {
	exit;
}

$mhmrentiva_cmp_image = (array) $column['image'];
?>
<div class="rvs-compare__vehicle">
	<?php if (! empty($show['images'])) : ?>
		<div class="rvs-card__media rvs-compare__media">
			<?php if ( (int) ( $mhmrentiva_cmp_image['id'] ?? 0 ) > 0) : ?>
				<?php
				echo wp_get_attachment_image(
					(int) $mhmrentiva_cmp_image['id'],
					(string) $mhmrentiva_cmp_image['size'],
					false,
					array(
						'class'   => 'rvs-card__img',
						'alt'     => (string) $mhmrentiva_cmp_image['alt'],
						'loading' => 'lazy',
					)
				);
				?>
			<?php elseif ('' !== (string) ( $mhmrentiva_cmp_image['url'] ?? '' )) : ?>
				<img class="rvs-card__img" src="<?php echo esc_url( (string) $mhmrentiva_cmp_image['url']); ?>" alt="<?php echo esc_attr( (string) $mhmrentiva_cmp_image['alt']); ?>" loading="lazy">
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<div class="rvs-compare__name-line">
		<a class="rvs-compare__name" href="<?php echo esc_url( (string) $column['permalink']); ?>"><?php echo esc_html( (string) $column['title']); ?></a>
		<?php if (! empty($show['remove'])) : ?>
			<button type="button" class="rvs-btn rvs-btn--ghost rvs-compare__remove" data-rvs-cmp-remove data-vehicle-id="<?php echo esc_attr( (string) $column['id']); ?>" aria-label="<?php echo esc_attr( (string) $column['remove_label']); ?>"<?php echo ! empty($preview) ? ' disabled' : ''; ?>><?php echo esc_html_x('Remove', 'comparison: take this vehicle out', 'mhm-rentiva'); ?></button>
		<?php endif; ?>
	</div>

	<?php if (! empty($column['booked'])) : ?>
		<p class="rvs-card__booked rvs-compare__booked"><strong><?php esc_html_e('Booked for these dates', 'mhm-rentiva'); ?></strong>
			<?php if ('' !== (string) $column['booked_note']) : ?>
				<span class="rvs-card__booked-when"><?php echo esc_html( (string) $column['booked_note']); ?></span>
			<?php endif; ?>
		</p>
	<?php elseif (! empty($show['prices']) && ! empty($column['has_price'])) : ?>
		<div class="rvs-compare__price">
			<span class="rvs-compare__amount"><?php echo wp_kses( (string) $column['price_html'], PriceDisplay::allowed_html()); ?></span>
			<?php if ('' !== (string) $column['days_label']) : ?>
				<span class="rvs-compare__days"><?php echo esc_html( (string) $column['days_label']); ?></span>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php if (! empty($show['booking']) && '' !== (string) $column['booking_url']) : ?>
		<a class="rvs-btn <?php echo ! empty($column['booked']) ? 'rvs-btn--secondary' : 'rvs-btn--primary'; ?> rvs-compare__select" href="<?php echo esc_url( (string) $column['booking_url']); ?>"><?php echo esc_html( (string) $column['booking_label']); ?></a>
	<?php endif; ?>
</div>
