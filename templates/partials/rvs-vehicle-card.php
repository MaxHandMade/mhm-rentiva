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
 * - array  $extra    Optional, search sections only: `where` (the car's pickup point)
 *                    or `booked` (booked on the dates: note + "see from that date").
 *                    Without it the card is the one every list shows.
 *
 * @package MHMRentiva
 */

use MHMRentiva\Admin\Booking\Pricing\PriceDisplay;
use MHMRentiva\Admin\Frontend\Shortcodes\Card\RvsCardView;
use MHMRentiva\Frontend\RenderContext;

if (! defined('ABSPATH')) {
	exit;
}

$mhmrentiva_card        = RvsCardView::from_vehicle(
	(array) ( $vehicle ?? array() ),
	(array) ( $atts ?? array() ),
	(array) ( $dates ?? array() ),
	(array) ( $extra ?? array() )
);
$mhmrentiva_card_layout = 'list' === ( $layout ?? 'grid' ) ? 'list' : 'grid';
$mhmrentiva_preview     = RenderContext::current()->is_preview();
$mhmrentiva_show        = $mhmrentiva_card['show'];
$mhmrentiva_fav_label   = $mhmrentiva_card['favorite']['active'] ? __('Remove from favorites', 'mhm-rentiva') : __('Add to favorites', 'mhm-rentiva');
?>
<article class="rvs-card rvs-card--<?php echo esc_attr($mhmrentiva_card_layout); ?><?php echo $mhmrentiva_card['available'] ? '' : ' is-unavailable'; ?><?php echo $mhmrentiva_card['booked'] ? ' is-booked' : ''; ?>" data-rvs-card data-testid="vehicle-card" data-id="<?php echo esc_attr( (string) $mhmrentiva_card['id']); ?>">
	<?php if ($mhmrentiva_show['image']) : ?>
		<div class="rvs-card__media">
			<a class="rvs-card__media-link" href="<?php echo esc_url($mhmrentiva_card['permalink']); ?>" tabindex="-1" aria-hidden="true">
				<?php if ($mhmrentiva_card['image']['id'] > 0) : ?>
					<?php
					echo wp_get_attachment_image(
						$mhmrentiva_card['image']['id'],
						$mhmrentiva_card['image']['size'],
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
			<?php if ($mhmrentiva_card['booked']) : ?>
				<span class="rvs-tag rvs-card__tag rvs-card__tag--booked"><?php esc_html_e('Booked', 'mhm-rentiva'); ?></span>
			<?php elseif ($mhmrentiva_show['category'] && '' !== $mhmrentiva_card['category']) : ?>
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
		<?php if ('' !== $mhmrentiva_card['brand']) : ?>
			<span class="rvs-kicker rvs-card__brand"><?php echo esc_html($mhmrentiva_card['brand']); ?></span>
		<?php endif; ?>
		<div class="rvs-card__head">
			<?php if ($mhmrentiva_show['title']) : ?>
				<h3 class="rvs-card__title"><a href="<?php echo esc_url($mhmrentiva_card['permalink']); ?>"><?php echo esc_html($mhmrentiva_card['title']); ?></a></h3>
			<?php endif; ?>
			<?php if ($mhmrentiva_show['rating']) : ?>
				<?php
				$mhmrentiva_rating = esc_html($mhmrentiva_card['rating']['label']);
				if ($mhmrentiva_card['rating']['show_count']) {
					$mhmrentiva_rating .= ' <span class="rvs-card__rating-count">(' . esc_html(number_format_i18n($mhmrentiva_card['rating']['count'])) . ')</span>';
				}
				?>
				<span class="rvs-card__rating"><?php echo wp_kses_post($mhmrentiva_rating); ?></span>
			<?php endif; ?>
		</div>

		<?php if ('' !== $mhmrentiva_card['status']) : ?>
			<span class="rvs-tag rvs-card__status <?php echo $mhmrentiva_card['available'] ? 'rvs-tag--info is-available' : 'rvs-tag--alert is-unavailable'; ?>"><?php echo esc_html($mhmrentiva_card['status']); ?></span>
		<?php endif; ?>
		<?php if ('' !== $mhmrentiva_card['description']) : ?>
			<p class="rvs-card__desc"><?php echo esc_html(wp_strip_all_tags($mhmrentiva_card['description'])); ?></p>
		<?php endif; ?>
		<?php if ($mhmrentiva_show['specs'] && '' !== $mhmrentiva_card['specs']) : ?>
			<p class="rvs-card__specs"><?php echo esc_html($mhmrentiva_card['specs']); ?></p>
		<?php endif; ?>
		<?php if ('' !== $mhmrentiva_card['where']) : ?>
			<p class="rvs-card__where"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true" focusable="false"><path d="M12 21s-7-6.3-7-11a7 7 0 0 1 14 0c0 4.7-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/></svg><span>
			<?php
			/* translators: %s: the pickup point of this car. */
			printf(esc_html__('Pickup: %s', 'mhm-rentiva'), '<strong>' . esc_html($mhmrentiva_card['where']) . '</strong>');
			?>
			</span></p>
		<?php endif; ?>
		<?php if ($mhmrentiva_card['booked']) : ?>
			<p class="rvs-card__booked"><strong><?php esc_html_e('Booked for these dates', 'mhm-rentiva'); ?></strong>
			<?php if ('' !== $mhmrentiva_card['booked_note']) : ?>
				<span class="rvs-card__booked-when"><?php echo esc_html($mhmrentiva_card['booked_note']); ?></span>
			<?php endif; ?>
			</p>
			<?php if ('' !== $mhmrentiva_card['booked_url']) : ?>
				<a class="rvs-btn rvs-btn--secondary rvs-card__later" href="<?php echo esc_url($mhmrentiva_card['booked_url']); ?>"><?php esc_html_e('See from that date', 'mhm-rentiva'); ?></a>
			<?php endif; ?>
		<?php else : ?>
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
		<?php endif; ?>
	</div>
</article>
