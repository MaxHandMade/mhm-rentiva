<?php
/**
 * Front-end standard vehicle card (`rvs-` layer).
 *
 * Prints RvsCardView::from_vehicle(); computes nothing itself. Rendered inside
 * a `.rvs-shell` by every listing surface (grid, list, featured, search
 * results, favorites).
 *
 * Variables:
 * - array  $vehicle  Card data (VehiclesList::get_vehicle_data_for_shortcode() shape)
 * - array  $atts     Shortcode attributes
 * - string $layout   'grid' (default) or 'list'
 * - array  $dates    Optional search context (logical keys); empty = the request's
 *
 * @package MHMRentiva
 */

use MHMRentiva\Admin\Booking\Pricing\PriceDisplay;
use MHMRentiva\Admin\Frontend\Shortcodes\Card\RvsCardView;
use MHMRentiva\Frontend\RenderContext;

if (! defined('ABSPATH')) {
	exit;
}

$mhmrentiva_card = RvsCardView::from_vehicle(
	(array) ( $vehicle ?? array() ),
	(array) ( $atts ?? array() ),
	(array) ( $dates ?? array() )
);
$mhmrentiva_card_layout = 'list' === ( $layout ?? 'grid' ) ? 'list' : 'grid';
$mhmrentiva_preview     = RenderContext::current()->is_preview();
$mhmrentiva_show        = $mhmrentiva_card['show'];
$mhmrentiva_fav_label   = $mhmrentiva_card['favorite']['active'] ? __('Remove from favorites', 'mhm-rentiva') : __('Add to favorites', 'mhm-rentiva');
?>
<article class="rvs-card rvs-card--<?php echo esc_attr($mhmrentiva_card_layout); ?><?php echo $mhmrentiva_card['available'] ? '' : ' is-unavailable'; ?>" data-rvs-card data-id="<?php echo esc_attr( (string) $mhmrentiva_card['id']); ?>">
	<?php if ($mhmrentiva_show['image']) : ?>
		<div class="rvs-card__media">
			<a class="rvs-card__media-link" href="<?php echo esc_url($mhmrentiva_card['permalink']); ?>" tabindex="-1" aria-hidden="true">
				<?php if ($mhmrentiva_card['image']['id'] > 0) : ?>
					<?php
					echo wp_get_attachment_image(
						$mhmrentiva_card['image']['id'],
						'medium_large',
						false,
						array(
							'class'   => 'rvs-card__img',
							'alt'     => $mhmrentiva_card['image']['alt'],
							'loading' => 'lazy',
						)
					);
					?>
				<?php elseif ('' !== $mhmrentiva_card['image']['url']) : ?>
					<img class="rvs-card__img" src="<?php echo esc_url($mhmrentiva_card['image']['url']); ?>" alt="<?php echo esc_attr($mhmrentiva_card['image']['alt']); ?>" loading="lazy">
				<?php endif; ?>
			</a>
			<?php if ($mhmrentiva_show['category'] && '' !== $mhmrentiva_card['category']) : ?>
				<span class="rvs-tag rvs-tag--outline rvs-card__tag"><?php echo esc_html($mhmrentiva_card['category']); ?></span>
			<?php endif; ?>
			<?php if ($mhmrentiva_card['favorite']['show']) : ?>
				<button type="button"
					class="rvs-card__fav mhm-vehicle-favorite-btn<?php echo $mhmrentiva_card['favorite']['active'] ? ' is-active' : ''; ?>"
					data-rvs-fav
					data-vehicle-id="<?php echo esc_attr( (string) $mhmrentiva_card['id']); ?>"
					<?php RenderContext::the_nonce_attr_line('mhmrentiva_toggle_favorite'); ?>
					data-testid="vehicle-favorite-btn"
					aria-label="<?php echo esc_attr($mhmrentiva_fav_label); ?>"
					title="<?php echo esc_attr($mhmrentiva_fav_label); ?>"
					aria-pressed="<?php echo $mhmrentiva_card['favorite']['active'] ? 'true' : 'false'; ?>"<?php echo $mhmrentiva_preview ? ' disabled' : ''; ?>>
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true" focusable="false"><path d="M12 20s-7-4.4-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.6-7 10-7 10z"/></svg>
				</button>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<div class="rvs-card__body">
		<div class="rvs-card__head">
			<?php if ($mhmrentiva_show['title']) : ?>
				<h3 class="rvs-card__title"><a href="<?php echo esc_url($mhmrentiva_card['permalink']); ?>"><?php echo esc_html($mhmrentiva_card['title']); ?></a></h3>
			<?php endif; ?>
			<?php if ($mhmrentiva_show['rating']) : ?>
				<span class="rvs-card__rating"><?php echo esc_html($mhmrentiva_card['rating']['label']); ?></span>
			<?php endif; ?>
		</div>

		<?php if ($mhmrentiva_show['specs'] && '' !== $mhmrentiva_card['specs']) : ?>
			<p class="rvs-card__specs"><?php echo esc_html($mhmrentiva_card['specs']); ?></p>
		<?php endif; ?>
		<?php if ('' !== $mhmrentiva_card['terms']) : ?>
			<p class="rvs-card__terms"><?php echo esc_html($mhmrentiva_card['terms']); ?></p>
		<?php endif; ?>

		<div class="rvs-card__foot">
			<?php if ($mhmrentiva_show['price']) : ?>
				<div class="rvs-card__price">
					<div class="rvs-card__amount"><?php echo wp_kses($mhmrentiva_card['price']['primary_html'], PriceDisplay::allowed_html()); ?></div>
					<?php if ('' !== $mhmrentiva_card['price']['secondary']) : ?>
						<div class="rvs-card__per"><?php echo esc_html($mhmrentiva_card['price']['secondary']); ?></div>
					<?php endif; ?>
				</div>
			<?php endif; ?>
			<?php if ($mhmrentiva_card['booking']['show']) : ?>
				<?php if ($mhmrentiva_card['available']) : ?>
					<a class="rvs-btn rvs-btn--primary rvs-card__select" href="<?php echo esc_url($mhmrentiva_card['booking']['url']); ?>" data-testid="vehicle-book-btn"><?php echo esc_html($mhmrentiva_card['booking']['label']); ?></a>
				<?php else : ?>
					<button type="button" class="rvs-btn rvs-btn--primary rvs-card__select" data-testid="vehicle-book-btn" disabled><?php echo esc_html($mhmrentiva_card['booking']['label']); ?></button>
				<?php endif; ?>
			<?php endif; ?>
		</div>

		<?php if ($mhmrentiva_card['compare']['show']) : ?>
			<label class="rvs-check rvs-card__compare">
				<input type="checkbox"
					data-rvs-compare
					data-vehicle-id="<?php echo esc_attr( (string) $mhmrentiva_card['id']); ?>"
					<?php RenderContext::the_nonce_attr_line('mhmrentiva_toggle_compare'); ?>
					data-testid="vehicle-compare-btn"<?php checked($mhmrentiva_card['compare']['active']); ?><?php echo $mhmrentiva_preview ? ' disabled' : ''; ?>>
				<span><?php esc_html_e('Compare', 'mhm-rentiva'); ?></span>
			</label>
		<?php endif; ?>
	</div>
</article>
