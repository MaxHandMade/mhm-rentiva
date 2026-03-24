<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound, WordPress.Security.NonceVerification.Recommended


/**
 * Selected Vehicle Summary Partial (Booking Context)
 *
 * Specialized, compact layout for the booking form.
 *
 * @var array $vehicle Standardized vehicle data (from prepare_selected_vehicle).
 * @var array $atts    Shortcode attributes.
 *
 * @package MHMRentiva
 */

use MHMRentiva\Helpers\Icons;

if (! defined('ABSPATH')) {
	exit;
}

// SSOT Data Layer.
include 'vehicle-card-base.php';

// Filter out unwanted actions for booking context.
$show_compare = false;
$show_booking = false;
$display_title = $title ?? ($vehicle_title ?? ($vehicle['title'] ?? ''));
$model_year = (string) ($vehicle['year'] ?? get_post_meta((int) $vehicle_id, 'mhm_vehicle_year', true));
if ($model_year === '') {
	$model_year = (string) get_post_meta((int) $vehicle_id, '_mhm_vehicle_year', true);
}

$display_category = '';
if (! empty($category_name)) {
	$display_category = (string) $category_name;
}
?>

<div class="rv-selected-vehicle rv-card rv-vehicle-summary" data-id="<?php echo esc_attr($vehicle_id); ?>">
	<div class="rv-sv__media">
		<?php if ($image_url) : ?>
			<?php
			$thumbnail_id = get_post_thumbnail_id($vehicle_id);
			if ($thumbnail_id) {
				echo wp_get_attachment_image(
					$thumbnail_id,
					'large',
					false,
					array(
						'class'   => 'rv-sv__img',
						'alt'     => esc_attr($image_alt),
						'loading' => 'eager',
					)
				);
			} else {
				echo '<img src="' . esc_url($image_url) . '" alt="' . esc_attr($image_alt) . '" class="rv-sv__img" loading="eager">';
			}
			?>
		<?php else : ?>
			<div class="rv-sv__placeholder">
				<?php Icons::render('car', array('width' => '48', 'height' => '48', 'class' => 'rv-sv__placeholder-icon')); ?>
			</div>
		<?php endif; ?>

		<?php if ($show_fav) : ?>
			<button class="rv-sv__favorite mhm-vehicle-favorite-btn <?php echo esc_attr($is_favorite ? 'is-active' : ''); ?>"
				data-vehicle-id="<?php echo esc_attr($vehicle_id); ?>"
				data-nonce="<?php echo esc_attr(wp_create_nonce('mhm_rentiva_toggle_favorite')); ?>"
				aria-label="<?php echo esc_attr__('Toggle favorite', 'mhm-rentiva'); ?>">
				<?php Icons::render('heart', array('class' => 'rv-heart-icon')); ?>
			</button>
		<?php endif; ?>
		<?php if ($show_rating) : ?>
			<div class="rv-sv__rating-inline rv-sv__rating-overlay">
				<span class="rv-sv__rating-star" aria-hidden="true">&#9733;</span>
				<span class="rv-sv__rating-value"><?php echo esc_html(number_format((float) $rating_avg, 1)); ?></span>
				<span class="rv-sv__rating-count">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d: total rating count */
							__('(%d reviews)', 'mhm-rentiva'),
							intval($rating_count)
						)
					);
					?>
				</span>
			</div>
		<?php endif; ?>

	</div>

	<div class="rv-sv__content">
		<div class="rv-sv__top">
			<?php if ($display_category !== '') : ?>
				<div class="rv-sv__category"><?php echo esc_html($display_category); ?></div>
			<?php endif; ?>
		</div>
		<div class="rv-sv__title-wrap">
			<h3 class="rv-sv__title"><?php echo esc_html($display_title); ?></h3>
		</div>

		<?php if ($show_features && ! empty($features)) : ?>
			<div class="rv-sv__meta">
				<?php
				foreach ($features as $feature) :

					$feature_label = (string) ($feature['text'] ?? '');
					$feature_svg = isset($feature['svg']) ? (string) $feature['svg'] : '';
					?>
					<span class="rv-sv__chip rv-chip" title="<?php echo esc_attr($feature_label); ?>">
						<?php if ($feature_svg !== '') : ?>
							<?php echo wp_kses($feature_svg, $allowed_svg_tags); ?>
						<?php endif; ?>
						<?php echo esc_html($feature_label); ?>
					</span>
					<?php
				endforeach;
				?>
			</div>
		<?php endif; ?>
	</div>
</div>
